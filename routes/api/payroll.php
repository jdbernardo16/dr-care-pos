<?php

use App\Http\Controllers\Dashboard\PayrollController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware( NsRestrictMiddleware::arguments( 'payroll.read' ) )->group( function () {
    Route::get( 'payroll/runs', [ PayrollController::class, 'getRuns' ] );
    Route::get( 'payroll/runs/{id}', [ PayrollController::class, 'getRun' ] );
    Route::get( 'payroll/runs/{id}/payslip', [ PayrollController::class, 'getPayslip' ] );
    Route::get( 'payroll/eligible-users', [ PayrollController::class, 'getEligibleUsers' ] );
    Route::get( 'payroll/unpaid-attendance', [ PayrollController::class, 'getUnpaidAttendance' ] );
    Route::get( 'payroll/period-boundaries', [ PayrollController::class, 'getPeriodBoundaries' ] );
    Route::get( 'payroll/summary', [ PayrollController::class, 'getSummary' ] );
} );

Route::post( 'payroll/runs', [ PayrollController::class, 'createDraft' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'payroll.create' ) );

Route::post( 'payroll/runs/{id}/post', [ PayrollController::class, 'postRun' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'payroll.post' ) );

Route::post( 'payroll/runs/{id}/recalculate', [ PayrollController::class, 'recalculateRun' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'payroll.update' ) );

Route::post( 'payroll/runs/{id}/void', [ PayrollController::class, 'voidRun' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'payroll.void' ) );

Route::delete( 'payroll/runs/{id}', [ PayrollController::class, 'deleteDraft' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'payroll.delete' ) );
