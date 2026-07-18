<?php

use App\Http\Controllers\Dashboard\GcashController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware( NsRestrictMiddleware::arguments( 'nexopos.use.registers' ) )->group( function () {
    Route::get( 'gcash/active-session/{register}', [ GcashController::class, 'getSessionForRegister' ] );
    Route::post( 'gcash/open/{register}', [ GcashController::class, 'openSession' ] );
    Route::post( 'gcash/close/{session}', [ GcashController::class, 'closeSession' ] );
    Route::post( 'gcash/cash-in/{session}', [ GcashController::class, 'processCashIn' ] );
    Route::post( 'gcash/cash-out/{session}', [ GcashController::class, 'processCashOut' ] );
    Route::get( 'gcash/transactions/{session}', [ GcashController::class, 'getTransactions' ] );
    Route::get( 'gcash/session/{session}', [ GcashController::class, 'getSession' ] );
    Route::get( 'gcash/history/{register}', [ GcashController::class, 'getSessionHistory' ] );
} );
