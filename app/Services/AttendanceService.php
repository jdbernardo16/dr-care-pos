<?php

namespace App\Services;

use App\Exceptions\NotAllowedException;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceDeviceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceService
{
    public function clockIn( $userId, $note = null )
    {
        $activeRecords = Attendance::forUser( $userId )
            ->clockedInOrOnBreak()
            ->get();

        $activeToday = $activeRecords->first( fn( $record ) => $record->clock_in_at->isToday() );

        if ( $activeToday instanceof Attendance ) {
            throw new NotAllowedException( __( 'You\'re already clocked in or on break. Please clock out first.' ) );
        }

        foreach ( $activeRecords as $record ) {
            $this->closeStaleShift( $record );
        }

        $attendance = new Attendance;
        $attendance->user_id = $userId;
        $attendance->clock_in_at = now();
        $attendance->clock_in_ip = request()->ip();
        $attendance->clock_in_device_id = request()->header( 'X-Device-Id' );
        $attendance->clock_in_note = $note;
        $attendance->status = Attendance::STATUS_CLOCKED_IN;
        $attendance->author_id = Auth::id();
        $attendance->save();

        return [
            'status' => 'success',
            'message' => __( 'You\'ve successfully clocked in.' ),
            'data' => compact( 'attendance' ),
        ];
    }

    public function clockOut( $userId, $note = null )
    {
        $activeRecord = Attendance::forUser( $userId )
            ->clockedInOrOnBreak()
            ->first();

        if ( ! $activeRecord instanceof Attendance ) {
            throw new NotAllowedException( __( 'No active clock-in record found.' ) );
        }

        // If on break, auto end break first
        if ( $activeRecord->status === Attendance::STATUS_ON_BREAK ) {
            $this->endBreak( $activeRecord );
        }

        $clockIn = Carbon::parse( $activeRecord->clock_in_at );
        $clockOut = now();
        $totalMinutes = $clockIn->diffInMinutes( $clockOut );
        $breakMinutes = (float) $activeRecord->break_hours * 60;
        $netMinutes = $totalMinutes - $breakMinutes;
        $netHours = round( $netMinutes / 60, 2 );

        $activeRecord->clock_out_at = $clockOut;
        $activeRecord->clock_out_ip = request()->ip();
        $activeRecord->clock_out_device_id = request()->header( 'X-Device-Id' );
        $activeRecord->clock_out_note = $note;
        $activeRecord->total_hours = round( $totalMinutes / 60, 2 );
        $activeRecord->net_hours = $netHours;
        $activeRecord->status = Attendance::STATUS_CLOCKED_OUT;
        $activeRecord->save();

        return [
            'status' => 'success',
            'message' => __( 'You\'ve successfully clocked out.' ),
            'data' => [
                'attendance' => $activeRecord,
                'total_hours' => $activeRecord->total_hours,
                'break_hours' => $activeRecord->break_hours,
                'net_hours' => $netHours,
            ],
        ];
    }

    public function breakIn( $userId, $note = null )
    {
        $record = Attendance::forUser( $userId )
            ->clockedInOrOnBreak()
            ->first();

        if ( ! $record instanceof Attendance ) {
            throw new NotAllowedException( __( 'You must be clocked in to take a break.' ) );
        }

        if ( $record->status === Attendance::STATUS_ON_BREAK ) {
            throw new NotAllowedException( __( 'You\'re already on break. End your break first.' ) );
        }

        $record->break_start = now();
        $record->status = Attendance::STATUS_ON_BREAK;
        $record->save();

        return [
            'status' => 'success',
            'message' => __( 'Break started.' ),
            'data' => compact( 'record' ),
        ];
    }

    public function breakOut( $userId, $note = null )
    {
        $record = Attendance::forUser( $userId )
            ->onBreak()
            ->first();

        if ( ! $record instanceof Attendance ) {
            throw new NotAllowedException( __( 'You\'re not currently on break.' ) );
        }

        $this->endBreak( $record );

        return [
            'status' => 'success',
            'message' => __( 'Break ended.' ),
            'data' => compact( 'record' ),
        ];
    }

    private function endBreak( Attendance $record )
    {
        $breakStart = Carbon::parse( $record->break_start );
        $breakEnd = now();
        $breakMinutes = $breakStart->diffInMinutes( $breakEnd );
        $existingBreak = (float) $record->break_hours;
        $newBreakHours = $existingBreak + round( $breakMinutes / 60, 2 );

        $record->break_end = $breakEnd;
        $record->break_hours = $newBreakHours;
        $record->status = Attendance::STATUS_CLOCKED_IN;
        $record->save();
    }

    /**
     * Closes a shift that was left open on a previous day,
     * so a new clock-in is not blocked by a forgotten clock-out.
     */
    private function closeStaleShift( Attendance $record )
    {
        if ( $record->status === Attendance::STATUS_ON_BREAK && $record->break_start ) {
            $breakStart = Carbon::parse( $record->break_start );
            $breakEnd = $record->clock_in_at->copy()->endOfDay();
            $breakMinutes = $breakStart->diffInMinutes( $breakEnd );
            $existingBreak = (float) $record->break_hours;

            $record->break_end = $breakEnd;
            $record->break_hours = $existingBreak + round( $breakMinutes / 60, 2 );
            $record->status = Attendance::STATUS_CLOCKED_IN;
        }

        $clockIn = Carbon::parse( $record->clock_in_at );
        $clockOut = $clockIn->copy()->endOfDay();
        $totalMinutes = $clockIn->diffInMinutes( $clockOut );
        $breakMinutes = (float) $record->break_hours * 60;
        $netMinutes = $totalMinutes - $breakMinutes;
        $netHours = round( $netMinutes / 60, 2 );

        $record->clock_out_at = $clockOut;
        $record->clock_out_ip = request()->ip();
        $record->clock_out_note = __( 'Auto-closed (forgot to clock out)' );
        $record->total_hours = round( $totalMinutes / 60, 2 );
        $record->net_hours = $netHours;
        $record->status = Attendance::STATUS_CLOCKED_OUT;
        $record->save();
    }

    public function getCurrentStatus( $userId )
    {
        $todayRecord = Attendance::forUser( $userId )
            ->forToday()
            ->orderBy( 'id', 'desc' )
            ->first();

        return $this->buildCurrentStatusResponse( $todayRecord );
    }

    public function getStaffStatus()
    {
        $staff = User::where( 'active', true )->get();
        $statuses = [];

        foreach ( $staff as $user ) {
            $activeRecord = Attendance::forUser( $user->id )
                ->clockedInOrOnBreak()
                ->first();

            $statuses[] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'is_clocked_in' => $activeRecord instanceof Attendance,
                'is_on_break' => $activeRecord && $activeRecord->status === Attendance::STATUS_ON_BREAK,
                'record' => $activeRecord,
            ];
        }

        return [
            'status' => 'success',
            'data' => $statuses,
        ];
    }

    public function getHistory( $filters = [] )
    {
        $query = Attendance::with( [ 'user', 'author' ] );

        // Non-admin users only see their own history
        $user = Auth::user();
        $isAdmin = $user && $user->hasRoles( [ 'admin', 'nexopos.developer' ] );

        if ( ! $isAdmin ) {
            $query->where( 'user_id', $user ? $user->id : 0 );
        } elseif ( ! empty( $filters[ 'user_id' ] ) ) {
            $query->where( 'user_id', $filters[ 'user_id' ] );
        }

        if ( ! empty( $filters[ 'start_date' ] ) ) {
            $query->whereDate( 'clock_in_at', '>=', $filters[ 'start_date' ] );
        }

        if ( ! empty( $filters[ 'end_date' ] ) ) {
            $query->whereDate( 'clock_in_at', '<=', $filters[ 'end_date' ] );
        }

        if ( ! empty( $filters[ 'status' ] ) ) {
            $query->where( 'status', $filters[ 'status' ] );
        }

        return $query->orderBy( 'id', 'desc' )->paginate( 50 );
    }

    private function buildCurrentStatusResponse( $todayRecord )
    {
        $attendanceDeviceService = app()->make( AttendanceDeviceService::class );

        return [
            'status' => 'success',
            'data' => [
                'is_clocked_in' => $todayRecord instanceof Attendance
                    && in_array( $todayRecord->status, [
                        Attendance::STATUS_CLOCKED_IN, Attendance::STATUS_ON_BREAK,
                    ] ),
                'is_on_break' => $todayRecord instanceof Attendance
                    && $todayRecord->status === Attendance::STATUS_ON_BREAK,
                'record' => $todayRecord,
                'device_verified' => $attendanceDeviceService->canBypassDeviceCheck()
                    ? true
                    : $attendanceDeviceService->requestHasValidDevice(),
                'is_admin' => $attendanceDeviceService->canBypassDeviceCheck(),
            ],
        ];
    }
}
