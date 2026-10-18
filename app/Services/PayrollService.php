<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Exceptions\NotFoundException;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Transaction;
use App\Models\TransactionAccount;
use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected HolidayService $holidayService
    ) {}

    public function getEligibleUsers()
    {
        return User::whereNotNull( 'hourly_rate' )->where( 'active', true )->get();
    }

    public function getUnpaidAttendance( $userId, $start, $end )
    {
        return Attendance::forUser( $userId )
            ->forPayPeriod( $start, $end )
            ->where( 'status', Attendance::STATUS_CLOCKED_OUT )
            ->whereNotNull( 'total_hours' )
            ->where( 'total_hours', '>', 0 )
            ->whereDoesntHave( 'payrollItem' )
            ->orderBy( 'clock_in_at' )
            ->get();
    }

    /**
     * Get effective net hours for an attendance record.
     * Favors net_hours if set, otherwise computes total_hours - break_hours.
     */
    private function getEffectiveNetHours( Attendance $attendance ): float
    {
        $netHours = (float) ( $attendance->net_hours ?? 0 );
        if ( $netHours > 0 ) {
            return $netHours;
        }
        return round(
            (float) ( $attendance->total_hours ?? 0 )
            - (float) ( $attendance->break_hours ?? 0 ),
            2
        );
    }

    public function getPeriodBoundaries( $periodType, $referenceDate = null )
    {
        $ref = $referenceDate ? Carbon::parse( $referenceDate ) : now();

        return match ( $periodType ) {
            PayrollRun::PERIOD_WEEKLY => [
                'start' => ( clone $ref )->startOfWeek()->toDateString(),
                'end' => ( clone $ref )->endOfWeek()->toDateString(),
            ],
            PayrollRun::PERIOD_BI_WEEKLY => [
                'start' => $ref->day <= 15
                    ? ( clone $ref )->startOfMonth()->toDateString()
                    : ( clone $ref )->startOfMonth()->addDays( 15 )->toDateString(),
                'end' => $ref->day <= 15
                    ? ( clone $ref )->startOfMonth()->addDays( 15 )->toDateString()
                    : ( clone $ref )->endOfMonth()->toDateString(),
            ],
            PayrollRun::PERIOD_MONTHLY => [
                'start' => ( clone $ref )->startOfMonth()->toDateString(),
                'end' => ( clone $ref )->endOfMonth()->toDateString(),
            ],
            default => throw new NotAllowedException( __( 'Invalid period type.' ) ),
        };
    }

    public function createDraft( $userId, $periodStart, $periodEnd, $periodType = PayrollRun::PERIOD_BI_WEEKLY )
    {
        $user = User::findOrFail( $userId );

        if ( $user->hourly_rate === null ) {
            throw new NotAllowedException( __( 'This user does not have an hourly rate set.' ) );
        }

        $existingRun = PayrollRun::where( 'user_id', $userId )
            ->where( 'period_start', $periodStart )
            ->where( 'period_end', $periodEnd )
            ->whereIn( 'status', [ PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_POSTED ] )
            ->first();

        if ( $existingRun ) {
            throw new NotAllowedException( __( 'A payroll run already exists for this employee and period.' ) );
        }

        $attendanceRecords = $this->getUnpaidAttendance( $userId, $periodStart, $periodEnd );
        $overtimeRequests  = app( OvertimeRequestService::class )->getApprovedForPeriod( $userId, $periodStart, $periodEnd );

        $totalHours    = 0;
        $grossPay      = 0;
        $totalRegular  = 0;
        $totalOT       = 0;
        $totalHoliday  = 0;
        $totalNightDiff = 0;

        DB::beginTransaction();

        try {
            $run = new PayrollRun;
            $run->user_id      = $userId;
            $run->period_start = $periodStart;
            $run->period_end   = $periodEnd;
            $run->period_type  = $periodType;
            $run->total_hours  = 0;
            $run->hourly_rate  = $user->hourly_rate;
            $run->gross_pay    = 0;
            $run->status       = PayrollRun::STATUS_DRAFT;
            $run->author_id    = Auth::id();
            $run->save();

            // Build overtime lookup: date => total OT hours
            $otLookup = [];
            foreach ( $overtimeRequests as $ot ) {
                $dateKey = $ot->date instanceof Carbon ? $ot->date->toDateString() : $ot->date;
                $otLookup[$dateKey] = (float) $ot->total_hours;
            }

            foreach ( $attendanceRecords as $attendance ) {
                $hourlyRate   = (float) $user->hourly_rate;
                $netHours     = $this->getEffectiveNetHours( $attendance );
                $breakHours   = (float) $attendance->break_hours;
                $clockInDate  = Carbon::parse( $attendance->clock_in_at );
                $clockOutDate = $attendance->clock_out_at ? Carbon::parse( $attendance->clock_out_at ) : null;
                $dateKey      = $clockInDate->toDateString();

                // --- Regular hours: capped at 8 ---
                $regularHours  = min( $netHours, 8 );
                $declaredOT    = $otLookup[$dateKey] ?? 0;

                // --- Overtime from filing ---
                // OT hours are hours BEYOND 8 that were pre-filed
                $overtimeHours = max( 0, min( $declaredOT, $netHours - $regularHours ) );

                // --- Regular pay ---
                $regularPay = round( $regularHours * $hourlyRate, 2, PHP_ROUND_HALF_DOWN );

                // --- Holiday multiplier ---
                $holidayMultiplier = $this->holidayService->getMultiplierForDate( $clockInDate );
                $isHoliday         = $holidayMultiplier > 1.0;

                // --- Holiday pay: regular hours × (holiday_multiplier - 1) ---
                // Base regular pay is already computed; holiday premium is the extra above base
                $holidayPremium = $isHoliday
                    ? round( $regularHours * $hourlyRate * ( $holidayMultiplier - 1 ), 2, PHP_ROUND_HALF_DOWN )
                    : 0.00;

                // --- Overtime rate multiplier (PH law) ---
                $otMultiplier = $this->holidayService->getOvertimeRateMultiplier( $clockInDate );
                $overtimePay  = round( $overtimeHours * $hourlyRate * $otMultiplier, 2, PHP_ROUND_HALF_DOWN );

                // --- Night differential (10pm-6am) ---
                $nightDiffHours = 0;
                $nightDiffPay   = 0.00;

                if ( $clockOutDate ) {
                    $nightDiffHours = $this->computeNightDiffHours( $clockInDate, $clockOutDate );
                    $nightDiffPay   = round( $nightDiffHours * $hourlyRate * 0.10, 2, PHP_ROUND_HALF_DOWN );
                }

                // --- Total pay for this day ---
                $dayPay = $regularPay + $holidayPremium + $overtimePay + $nightDiffPay;

                $item = new PayrollRunItem;
                $item->payroll_run_id   = $run->id;
                $item->attendance_id    = $attendance->id;
                $item->user_id          = $userId;
                $item->date             = $dateKey;
                $item->clock_in         = $attendance->clock_in_at;
                $item->clock_out        = $attendance->clock_out_at;
                $item->hours_worked     = $netHours;
                $item->hourly_rate      = $hourlyRate;
                $item->pay_amount       = $dayPay;
                $item->regular_hours    = $regularHours;
                $item->regular_pay      = $regularPay;
                $item->overtime_hours   = $overtimeHours;
                $item->overtime_pay     = $overtimePay;
                $item->holiday_hours    = $isHoliday ? $regularHours : 0;
                $item->holiday_pay      = $holidayPremium;
                $item->night_diff_hours = $nightDiffHours;
                $item->night_diff_pay   = $nightDiffPay;
                $item->break_deduction  = $breakHours;
                $item->save();

                $totalHours    += $netHours;
                $grossPay      += $dayPay;
                $totalRegular  += $regularPay;
                $totalOT       += $overtimePay;
                $totalHoliday  += $holidayPremium;
                $totalNightDiff += $nightDiffPay;
            }

            $run->total_hours = round( $totalHours, 2 );
            $run->gross_pay   = round( $grossPay, 2 );
            $run->save();

            DB::commit();

            return [
                'status'  => 'success',
                'message' => __( 'Payroll draft created.' ),
                'data'    => [
                    'run' => $run->load( 'items' ),
                ],
            ];
        } catch ( \Exception $e ) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Compute night differential hours (10pm-6am) within a shift.
     */
    private function computeNightDiffHours( Carbon $clockIn, Carbon $clockOut )
    {
        $hours = 0;
        $current = $clockIn->copy()->startOfHour();

        while ( $current < $clockOut ) {
            $hourEnd = $current->copy()->addHour();
            $sliceEnd = $hourEnd->min( $clockOut );
            $hour = (int) $current->format( 'G' );

            if ( $hour >= 22 || $hour < 6 ) {
                $hours += round( $current->diffInMinutes( $sliceEnd ) / 60, 2 );
            }

            $current = $hourEnd;
        }

        return round( $hours, 2 );
    }

    public function postRun( $runId )
    {
        $run = PayrollRun::with( 'user' )->findOrFail( $runId );

        if ( $run->status !== PayrollRun::STATUS_DRAFT ) {
            throw new NotAllowedException( __( 'Only draft runs can be posted.' ) );
        }

        if ( $run->gross_pay <= 0 ) {
            throw new NotAllowedException( __( 'Cannot post a payroll run with zero gross pay.' ) );
        }

        $salariesAccount = TransactionAccount::where( 'name', 'Salaries And Wages' )
            ->where( 'category_identifier', 'expenses' )
            ->first();

        if ( ! $salariesAccount ) {
            throw new NotFoundException( __( 'The Salaries And Wages account is not configured.' ) );
        }

        DB::beginTransaction();

        try {
            $periodLabel = $run->period_start . ' — ' . $run->period_end;

            $transaction = new Transaction;
            $transaction->name           = sprintf( __( 'Payroll: %s — %s' ), $run->user->username, $periodLabel );
            $transaction->value          = $run->gross_pay;
            $transaction->type           = Transaction::TYPE_DIRECT;
            $transaction->account_id     = $salariesAccount->id;
            $transaction->active         = false;
            $transaction->scheduled_date = ns()->date->toDateTimeString();
            $transaction->description    = sprintf( __( 'Salary payment for period %s to %s' ), $run->period_start, $run->period_end );
            $transaction->author_id      = Auth::id();
            $transaction->save();

            $history = new TransactionHistory;
            $history->transaction_id           = $transaction->id;
            $history->operation                = 'debit';
            $history->value                    = $run->gross_pay;
            $history->transaction_account_id   = $salariesAccount->id;
            $history->name                     = $transaction->name;
            $history->status                   = TransactionHistory::STATUS_ACTIVE;
            $history->trigger_date             = ns()->date->toDateTimeString();
            $history->type                     = Transaction::TYPE_DIRECT;
            $history->author_id                = Auth::id();
            TransactionHistory::withoutEvents( function () use ( $history ) {
                $history->save();
            } );

            $run->transaction_id = $transaction->id;
            $run->status         = PayrollRun::STATUS_POSTED;
            $run->save();

            DB::commit();

            return [
                'status'  => 'success',
                'message' => __( 'Payroll run posted and accounting entry created.' ),
                'data'    => [
                    'run' => $run->fresh( [ 'items', 'transaction' ] ),
                ],
            ];
        } catch ( \Exception $e ) {
            DB::rollBack();
            throw $e;
        }
    }

    public function voidRun( $runId )
    {
        $run = PayrollRun::with( 'transaction' )->findOrFail( $runId );

        if ( $run->status !== PayrollRun::STATUS_POSTED ) {
            throw new NotAllowedException( __( 'Only posted runs can be voided.' ) );
        }

        DB::beginTransaction();

        try {
            if ( $run->transaction_id ) {
                TransactionHistory::where( 'transaction_id', $run->transaction_id )->delete();
                Transaction::where( 'id', $run->transaction_id )->delete();
                $run->transaction_id = null;
            }

            $run->status = PayrollRun::STATUS_VOID;
            $run->save();

            DB::commit();

            return [
                'status'  => 'success',
                'message' => __( 'Payroll run voided and accounting entry removed.' ),
                'data'    => [
                    'run' => $run->fresh(),
                ],
            ];
        } catch ( \Exception $e ) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteDraft( $runId )
    {
        $run = PayrollRun::findOrFail( $runId );

        if ( $run->status !== PayrollRun::STATUS_DRAFT ) {
            throw new NotAllowedException( __( 'Only draft runs can be deleted.' ) );
        }

        $run->items()->delete();
        $run->delete();

        return [
            'status'  => 'success',
            'message' => __( 'Payroll draft deleted.' ),
        ];
    }

    public function getRuns( $filters = [] )
    {
        $query = PayrollRun::with( [ 'user', 'author' ] );

        if ( ! empty( $filters['user_id'] ) ) {
            $query->where( 'user_id', $filters['user_id'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $query->where( 'status', $filters['status'] );
        }

        if ( ! empty( $filters['period_start'] ) ) {
            $query->where( 'period_start', '>=', $filters['period_start'] );
        }

        if ( ! empty( $filters['period_end'] ) ) {
            $query->where( 'period_end', '<=', $filters['period_end'] );
        }

        return $query->orderBy( 'id', 'desc' )->paginate( 50 );
    }

    public function getRunWithItems( $runId )
    {
        return PayrollRun::with( [ 'user', 'author', 'items.attendance', 'transaction' ] )->findOrFail( $runId );
    }

    public function getPayslip( $runId )
    {
        $run = $this->getRunWithItems( $runId );

        return [
            'status' => 'success',
            'data'   => compact( 'run' ),
        ];
    }

    public function getPayrollSummary( $startDate = null, $endDate = null )
    {
        $query = PayrollRun::where( 'status', PayrollRun::STATUS_POSTED );

        if ( $startDate ) {
            $query->where( 'period_start', '>=', $startDate );
        }

        if ( $endDate ) {
            $query->where( 'period_end', '<=', $endDate );
        }

        $runs = $query->get();

        return [
            'total_employees' => $runs->unique( 'user_id' )->count(),
            'total_hours'     => round( $runs->sum( 'total_hours' ), 2 ),
            'total_gross_pay' => round( $runs->sum( 'gross_pay' ), 2 ),
            'total_runs'      => $runs->count(),
        ];
    }

    public function recalculateRun( $runId )
    {
        $run = PayrollRun::with( 'items' )->findOrFail( $runId );

        if ( $run->status !== PayrollRun::STATUS_DRAFT ) {
            throw new NotAllowedException( __( 'Only draft runs can be recalculated.' ) );
        }

        // Delete old items and recreate using the same attendance records
        $attendanceIds = $run->items->pluck( 'attendance_id' )->toArray();
        $run->items()->delete();

        // Re-fetch attendance records by their IDs (they may have updated net_hours, breaks, etc.)
        $attendanceRecords = Attendance::whereIn( 'id', $attendanceIds )->get();
        $overtimeRequests  = app( OvertimeRequestService::class )
            ->getApprovedForPeriod( $run->user_id, $run->period_start, $run->period_end );

        $otLookup = [];
        foreach ( $overtimeRequests as $ot ) {
            $dateKey = $ot->date instanceof Carbon ? $ot->date->toDateString() : $ot->date;
            $otLookup[$dateKey] = (float) $ot->total_hours;
        }

        $totalHours     = 0;
        $grossPay       = 0;
        $hourlyRate     = (float) $run->hourly_rate;

        foreach ( $attendanceRecords as $attendance ) {
            $netHours     = $this->getEffectiveNetHours( $attendance );
            $breakHours   = (float) $attendance->break_hours;
            $clockInDate  = Carbon::parse( $attendance->clock_in_at );
            $clockOutDate = $attendance->clock_out_at ? Carbon::parse( $attendance->clock_out_at ) : null;
            $dateKey      = $clockInDate->toDateString();

            $regularHours  = min( $netHours, 8 );
            $declaredOT    = $otLookup[$dateKey] ?? 0;
            $overtimeHours = max( 0, min( $declaredOT, $netHours - $regularHours ) );
            $regularPay    = round( $regularHours * $hourlyRate, 2, PHP_ROUND_HALF_DOWN );

            $holidayMultiplier = $this->holidayService->getMultiplierForDate( $clockInDate );
            $isHoliday         = $holidayMultiplier > 1.0;
            $holidayPremium    = $isHoliday
                ? round( $regularHours * $hourlyRate * ( $holidayMultiplier - 1 ), 2, PHP_ROUND_HALF_DOWN )
                : 0.00;

            $otMultiplier = $this->holidayService->getOvertimeRateMultiplier( $clockInDate );
            $overtimePay  = round( $overtimeHours * $hourlyRate * $otMultiplier, 2, PHP_ROUND_HALF_DOWN );

            $nightDiffHours = 0;
            $nightDiffPay   = 0.00;
            if ( $clockOutDate ) {
                $nightDiffHours = $this->computeNightDiffHours( $clockInDate, $clockOutDate );
                $nightDiffPay   = round( $nightDiffHours * $hourlyRate * 0.10, 2, PHP_ROUND_HALF_DOWN );
            }

            $dayPay = $regularPay + $holidayPremium + $overtimePay + $nightDiffPay;

            $item = new PayrollRunItem;
            $item->payroll_run_id   = $run->id;
            $item->attendance_id    = $attendance->id;
            $item->user_id          = $run->user_id;
            $item->date             = $dateKey;
            $item->clock_in         = $attendance->clock_in_at;
            $item->clock_out        = $attendance->clock_out_at;
            $item->hours_worked     = $netHours;
            $item->hourly_rate      = $hourlyRate;
            $item->pay_amount       = $dayPay;
            $item->regular_hours    = $regularHours;
            $item->regular_pay      = $regularPay;
            $item->overtime_hours   = $overtimeHours;
            $item->overtime_pay     = $overtimePay;
            $item->holiday_hours    = $isHoliday ? $regularHours : 0;
            $item->holiday_pay      = $holidayPremium;
            $item->night_diff_hours = $nightDiffHours;
            $item->night_diff_pay   = $nightDiffPay;
            $item->break_deduction  = $breakHours;
            $item->save();

            $totalHours += $netHours;
            $grossPay   += $dayPay;
        }

        $run->total_hours = round( $totalHours, 2 );
        $run->gross_pay   = round( $grossPay, 2 );
        $run->save();

        return [
            'status'  => 'success',
            'message' => __( 'Payroll run recalculated.' ),
            'data'    => [
                'run' => $run->fresh( 'items' ),
            ],
        ];
    }
}
