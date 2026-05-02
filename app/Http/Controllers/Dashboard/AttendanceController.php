<?php

/**
 * NexoPOS Controller
 *
 * @since  1.0
 **/

namespace App\Http\Controllers\Dashboard;

use App\Crud\AttendanceCrud;
use App\Http\Controllers\DashboardController;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends DashboardController
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    public function clockIn( Request $request )
    {
        return $this->attendanceService->clockIn(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function clockOut( Request $request )
    {
        return $this->attendanceService->clockOut(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function breakIn( Request $request )
    {
        return $this->attendanceService->breakIn(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function breakOut( Request $request )
    {
        return $this->attendanceService->breakOut(
            Auth::id(),
            $request->input( 'note' )
        );
    }

    public function getCurrentStatus()
    {
        return $this->attendanceService->getCurrentStatus( Auth::id() );
    }

    public function clockPage()
    {
        return view( 'pages.dashboard.attendance.clock', [
            'title' => __( 'Attendance Clock' ),
        ] );
    }

    public function getStaffStatus()
    {
        return $this->attendanceService->getStaffStatus();
    }

    public function getHistory( Request $request )
    {
        return $this->attendanceService->getHistory( $request->only( [
            'user_id', 'start_date', 'end_date', 'status',
        ] ) );
    }

    public function listAttendances()
    {
        return AttendanceCrud::table();
    }

    public function createAttendance()
    {
        return AttendanceCrud::form();
    }

    public function editAttendance( Attendance $attendance )
    {
        return AttendanceCrud::form( $attendance );
    }
}
