<?php

use App\Http\Controllers\Dashboard\HolidayController;
use Illuminate\Support\Facades\Route;

Route::get( '/holidays', [ HolidayController::class, 'listHolidays' ] )->name( ns()->routeName( 'ns.dashboard.holidays' ) );
