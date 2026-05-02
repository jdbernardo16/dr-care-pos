<?php

use App\Http\Controllers\Dashboard\OvertimeRequestController;
use Illuminate\Support\Facades\Route;

Route::get( '/overtime', [ OvertimeRequestController::class, 'listRequests' ] )->name( ns()->routeName( 'ns.dashboard.overtime' ) );
Route::get( '/overtime/file', [ OvertimeRequestController::class, 'filePage' ] )->name( ns()->routeName( 'ns.dashboard.overtime-file' ) );
