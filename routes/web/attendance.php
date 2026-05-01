<?php

use App\Http\Controllers\Dashboard\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::get( '/attendance', [ AttendanceController::class, 'listAttendances' ] )->name( ns()->routeName( 'ns.dashboard.attendance' ) );
Route::get( '/attendance/create', [ AttendanceController::class, 'createAttendance' ] )->name( ns()->routeName( 'ns.dashboard.attendance-create' ) );
Route::get( '/attendance/edit/{attendance}', [ AttendanceController::class, 'editAttendance' ] )->name( ns()->routeName( 'ns.dashboard.attendance-edit' ) );
Route::get( '/attendance/clock', [ AttendanceController::class, 'clockPage' ] )->name( ns()->routeName( 'ns.dashboard.attendance-clock' ) );
