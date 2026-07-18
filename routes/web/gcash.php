<?php

use App\Http\Controllers\Dashboard\GcashController;
use Illuminate\Support\Facades\Route;

Route::get( '/gcash', [ GcashController::class, 'listSessions' ] )->name( ns()->routeName( 'ns.dashboard.gcash' ) );
