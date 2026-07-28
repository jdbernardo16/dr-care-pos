<?php

use App\Http\Controllers\Dashboard\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::get('/activity-log', [ActivityLogController::class, 'showActivityLog'])
    ->name(ns()->routeName('ns.dashboard.activity-log'));
