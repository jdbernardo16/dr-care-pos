<?php

use App\Http\Controllers\Dashboard\AttendanceController;
use App\Http\Middleware\EnsureEnrolledDevice;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::post( 'attendance/clock-in', [ AttendanceController::class, 'clockIn' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::post( 'attendance/clock-out', [ AttendanceController::class, 'clockOut' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::post( 'attendance/break-in', [ AttendanceController::class, 'breakIn' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::post( 'attendance/break-out', [ AttendanceController::class, 'breakOut' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), EnsureEnrolledDevice::class );

Route::middleware( NsRestrictMiddleware::arguments( 'attendance.read' ) )->group( function () {
    Route::get( 'attendance/current-status', [ AttendanceController::class, 'getCurrentStatus' ] );
    Route::get( 'attendance/staff-status', [ AttendanceController::class, 'getStaffStatus' ] );
    Route::get( 'attendance/history', [ AttendanceController::class, 'getHistory' ] );
} );

Route::post( 'attendance/enroll-device', [ AttendanceController::class, 'enrollDevice' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), 'throttle:10,1' );

Route::post( 'attendance/generate-enrollment-code', [ AttendanceController::class, 'generateEnrollmentCode' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'attendance.clock' ), 'throttle:30,1' );

Route::middleware( NsRestrictMiddleware::arguments( 'attendance.read' ) )->group( function () {
    Route::get( 'attendance/devices', [ AttendanceController::class, 'listDevices' ] );
    Route::post( 'attendance/devices/{device}/toggle', [ AttendanceController::class, 'toggleDevice' ] );
    Route::delete( 'attendance/devices/{device}', [ AttendanceController::class, 'deleteDevice' ] );
} );
