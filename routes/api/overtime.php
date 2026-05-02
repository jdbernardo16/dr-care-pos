<?php

use App\Http\Controllers\Dashboard\OvertimeRequestController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware( NsRestrictMiddleware::arguments( 'overtime.read' ) )->group( function () {
    Route::get( 'overtime/requests', [ OvertimeRequestController::class, 'getRequests' ] );
} );

Route::post( 'overtime/requests', [ OvertimeRequestController::class, 'file' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'overtime.create' ) );

Route::get( 'overtime/my-requests', [ OvertimeRequestController::class, 'getMyRequests' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'overtime.create' ) );

Route::post( 'overtime/requests/{id}/approve', [ OvertimeRequestController::class, 'approve' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'overtime.approve' ) );

Route::post( 'overtime/requests/{id}/reject', [ OvertimeRequestController::class, 'reject' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'overtime.approve' ) );
