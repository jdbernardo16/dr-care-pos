<?php

use App\Http\Controllers\Dashboard\PosController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::get( 'pos/heartbeat', [ PosController::class, 'heartbeat' ] );
Route::get( 'pos/offline-snapshot', [ PosController::class, 'offlineSnapshot' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'nexopos.read.products' ) );
