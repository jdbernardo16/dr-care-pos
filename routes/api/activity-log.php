<?php

use App\Http\Controllers\Dashboard\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::get('activity-log', [ActivityLogController::class, 'getActivities']);
Route::get('activity-log/types', [ActivityLogController::class, 'getLogTypes']);
