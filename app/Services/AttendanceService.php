<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use App\Exceptions\NotAllowedException;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Clock in a user
     *
     * @param int $userId
     * @param string|null $note
     * @return array
     * @throws NotAllowedException
     */
    public function clockIn( $userId, $note = null )
    {
        $activeRecord = Attendance::forUser( $userId )
            ->clockedIn()
            ->first();

        if ( $activeRecord instanceof Attendance ) {
            throw new NotAllowedException( __( 'You\'re already clocked in. Please clock out first.' ) );
        }

        $attendance = new Attendance;
        $attendance->user_id = $userId;
        $attendance->clock_in_at = now();
        $attendance->clock_in_ip = request()->ip();
        $attendance->clock_in_note = $note;
        $attendance->status = Attendance::STATUS_CLOCKED_IN;
        $attendance->author_id = Auth::id();
        $attendance->save();

        return [
            'status' => 'success',
            'message' => __( 'You\'ve successfully clocked in.' ),
            'data' => [
                'attendance' => $attendance,
            ],
        ];
    }

    /**
     * Clock out a user
     *
     * @param int $userId
     * @param string|null $note
     * @return array
     * @throws NotAllowedException
     */
    public function clockOut( $userId, $note = null )
    {
        $activeRecord = Attendance::forUser( $userId )
            ->clockedIn()
            ->first();

        if ( ! $activeRecord instanceof Attendance ) {
            throw new NotAllowedException( __( 'No active clock-in record found.' ) );
        }

        $clockIn = Carbon::parse( $activeRecord->clock_in_at );
        $clockOut = now();
        $totalHours = $clockIn->diffInMinutes( $clockOut ) / 60;

        $activeRecord->clock_out_at = $clockOut;
        $activeRecord->clock_out_ip = request()->ip();
        $activeRecord->clock_out_note = $note;
        $activeRecord->total_hours = round( $totalHours, 2 );
        $activeRecord->status = Attendance::STATUS_CLOCKED_OUT;
        $activeRecord->save();

        return [
            'status' => 'success',
            'message' => __( 'You\'ve successfully clocked out.' ),
            'data' => [
                'attendance' => $activeRecord,
                'total_hours' => $activeRecord->total_hours,
            ],
        ];
    }

    /**
     * Get the current clock-in status for a user today
     *
     * @param int $userId
     * @return array
     */
    public function getCurrentStatus( $userId )
    {
        $todayRecord = Attendance::forUser( $userId )
            ->forToday()
            ->orderBy( 'id', 'desc' )
            ->first();

        if ( $todayRecord instanceof Attendance ) {
            return [
                'status' => 'success',
                'data' => [
                    'is_clocked_in' => $todayRecord->status === Attendance::STATUS_CLOCKED_IN,
                    'record' => $todayRecord,
                ],
            ];
        }

        return [
            'status' => 'success',
            'data' => [
                'is_clocked_in' => false,
                'record' => null,
            ],
        ];
    }

    /**
     * Get the current clock-in status for all staff
     *
     * @return array
     */
    public function getStaffStatus()
    {
        $staff = User::where( 'active', true )->get();
        $statuses = [];

        foreach ( $staff as $user ) {
            $activeRecord = Attendance::forUser( $user->id )
                ->clockedIn()
                ->first();

            $statuses[] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'is_clocked_in' => $activeRecord instanceof Attendance,
                'record' => $activeRecord,
            ];
        }

        return [
            'status' => 'success',
            'data' => $statuses,
        ];
    }

    /**
     * Get attendance history with optional filters
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getHistory( $filters = [] )
    {
        $query = Attendance::with( [ 'user', 'author' ] );

        if ( ! empty( $filters[ 'user_id' ] ) ) {
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
}
