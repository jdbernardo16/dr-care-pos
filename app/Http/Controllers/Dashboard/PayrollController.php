<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends DashboardController
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public function createDraft( Request $request )
    {
        return $this->payrollService->createDraft(
            $request->input( 'user_id' ),
            $request->input( 'period_start' ),
            $request->input( 'period_end' ),
            $request->input( 'period_type', 'bi-weekly' )
        );
    }

    public function postRun( $id )
    {
        return $this->payrollService->postRun( $id );
    }

    public function voidRun( $id )
    {
        return $this->payrollService->voidRun( $id );
    }

    public function deleteDraft( $id )
    {
        return $this->payrollService->deleteDraft( $id );
    }

    public function getRuns( Request $request )
    {
        return $this->payrollService->getRuns( $request->only( [
            'user_id', 'status', 'period_start', 'period_end',
        ] ) );
    }

    public function getRun( $id )
    {
        return $this->payrollService->getRunWithItems( $id );
    }

    public function getPayslip( $id )
    {
        return $this->payrollService->getPayslip( $id );
    }

    public function getEligibleUsers()
    {
        return $this->payrollService->getEligibleUsers();
    }

    public function getUnpaidAttendance( Request $request )
    {
        return $this->payrollService->getUnpaidAttendance(
            $request->input( 'user_id' ),
            $request->input( 'period_start' ),
            $request->input( 'period_end' )
        );
    }

    public function getPeriodBoundaries( Request $request )
    {
        return $this->payrollService->getPeriodBoundaries(
            $request->input( 'period_type', 'bi-weekly' ),
            $request->input( 'reference_date' )
        );
    }

    public function recalculateRun( $id )
    {
        return $this->payrollService->recalculateRun( $id );
    }

    public function getSummary( Request $request )
    {
        return $this->payrollService->getPayrollSummary(
            $request->input( 'start_date' ),
            $request->input( 'end_date' )
        );
    }

    public function listRuns()
    {
        return view( 'pages.dashboard.payroll.list', [
            'title' => __( 'Payroll Runs' ),
        ] );
    }

    public function createRun()
    {
        return view( 'pages.dashboard.payroll.create', [
            'title' => __( 'Create Pay Run' ),
        ] );
    }

    public function showRun( $id )
    {
        return view( 'pages.dashboard.payroll.detail', [
            'title' => __( 'Payroll Run Detail' ),
            'run' => PayrollRun::with( 'user' )->findOrFail( $id ),
        ] );
    }
}
