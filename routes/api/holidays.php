<?php

use App\Http\Controllers\Dashboard\HolidayController;
use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware( NsRestrictMiddleware::arguments( 'holiday.read' ) )->group( function () {
    Route::get( 'holidays', [ HolidayController::class, 'get' ] );
} );

Route::post( 'holidays', [ HolidayController::class, 'create' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'holiday.create' ) );

Route::put( 'holidays/{id}', [ HolidayController::class, 'update' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'holiday.update' ) );

Route::delete( 'holidays/{id}', [ HolidayController::class, 'delete' ] )
    ->middleware( NsRestrictMiddleware::arguments( 'holiday.delete' ) );
