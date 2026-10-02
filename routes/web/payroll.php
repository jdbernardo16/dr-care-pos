<?php

use App\Http\Controllers\Dashboard\PayrollController;
use Illuminate\Support\Facades\Route;

Route::get( '/payroll', [ PayrollController::class, 'listRuns' ] )->name( ns()->routeName( 'ns.dashboard.payroll' ) );
Route::get( '/payroll/create', [ PayrollController::class, 'createRun' ] )->name( ns()->routeName( 'ns.dashboard.payroll-create' ) );
Route::get( '/payroll/{id}', [ PayrollController::class, 'showRun' ] )->name( ns()->routeName( 'ns.dashboard.payroll-detail' ) );
