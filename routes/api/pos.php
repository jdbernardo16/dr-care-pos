<?php

use App\Http\Controllers\Dashboard\PosController;
use Illuminate\Support\Facades\Route;

Route::get( 'pos/heartbeat', [ PosController::class, 'heartbeat' ] );
