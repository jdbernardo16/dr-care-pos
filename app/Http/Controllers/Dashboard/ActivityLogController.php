<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends DashboardController
{
    public function showActivityLog()
    {
        return View::make('pages.dashboard.activity-log.list', [
            'title' => __('Activity Log'),
            'description' => __('View all system activities and changes.'),
        ]);
    }

    public function getActivities()
    {
        $perPage = request()->query('per_page', 50);
        $search = request()->query('search');
        $type = request()->query('type');

        $query = Activity::with('causer')->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('log_name', $type);
        }

        return $query->paginate($perPage);
    }

    public function getLogTypes()
    {
        return Activity::select('log_name')
            ->distinct()
            ->pluck('log_name');
    }
}
