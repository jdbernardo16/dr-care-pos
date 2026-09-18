<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;

class PosController extends DashboardController
{
    public function heartbeat()
    {
        $now = ns()->date->getNow();

        return [
            'status' => 'success',
            'time' => $now->toDateTimeString(),
            'timestamp' => $now->getTimestamp() * 1000,
        ];
    }
}
