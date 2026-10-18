<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Models\OvertimeRequest;
use App\Services\OvertimeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OvertimeRequestController extends DashboardController
{
    public function __construct(
        protected OvertimeRequestService $overtimeRequestService
    ) {}

    public function file( Request $request )
    {
        return $this->overtimeRequestService->fileRequest(
            Auth::id(),
            $request->input( 'date' ),
            $request->input( 'start_time' ),
            $request->input( 'end_time' ),
            $request->input( 'reason' )
        );
    }

    public function approve( $id, Request $request )
    {
        return $this->overtimeRequestService->approve(
            $id,
            $request->input( 'admin_note' )
        );
    }

    public function reject( $id, Request $request )
    {
        return $this->overtimeRequestService->reject(
            $id,
            $request->input( 'admin_note' )
        );
    }

    public function getRequests( Request $request )
    {
        return $this->overtimeRequestService->getRequests( $request->only( [
            'user_id', 'status', 'start_date', 'end_date',
        ] ) );
    }

    public function getMyRequests( Request $request )
    {
        return $this->overtimeRequestService->getMyRequests(
            Auth::id(),
            $request->only( [ 'status' ] )
        );
    }

    public function listRequests()
    {
        return view( 'pages.dashboard.overtime.list', [
            'title' => __( 'Overtime Requests' ),
        ] );
    }

    public function filePage()
    {
        return view( 'pages.dashboard.overtime.file', [
            'title' => __( 'File Overtime' ),
        ] );
    }
}
