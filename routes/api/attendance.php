<?php

use App\Http\Controllers\Dashboard\AttendanceController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::post( 'attendance/clock-in', [ AttendanceController::class, 'clockIn' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ) );

Route::post( 'attendance/clock-out', [ AttendanceController::class, 'clockOut' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ) );

Route::post( 'attendance/break-in', [ AttendanceController::class, 'breakIn' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ) );

Route::post( 'attendance/break-out', [ AttendanceController::class, 'breakOut' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ) );

Route::middleware( NsRestrictMiddleware::arguments( 'attendance.read' ) )->group( function () {
    Route::get( 'attendance/current-status', [ AttendanceController::class, 'getCurrentStatus' ] );
    Route::get( 'attendance/staff-status', [ AttendanceController::class, 'getStaffStatus' ] );
    Route::get( 'attendance/history', [ AttendanceController::class, 'getHistory' ] );
} );
