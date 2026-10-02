<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Services\HolidayService;
use Illuminate\Http\Request;

class HolidayController extends DashboardController
{
    public function __construct(
        protected HolidayService $holidayService
    ) {}

    public function create( Request $request )
    {
        return $this->holidayService->createHoliday( $request->only( [
            'name', 'date', 'type', 'multiplier', 'is_recurring', 'description',
        ] ) );
    }

    public function update( $id, Request $request )
    {
        return $this->holidayService->updateHoliday( $id, $request->only( [
            'name', 'date', 'type', 'multiplier', 'is_recurring', 'description',
        ] ) );
    }

    public function delete( $id )
    {
        return $this->holidayService->deleteHoliday( $id );
    }

    public function get( Request $request )
    {
        return $this->holidayService->getHolidays( $request->only( [
            'year', 'month', 'type',
        ] ) );
    }

    public function listHolidays()
    {
        return view( 'pages.dashboard.holidays.list', [
            'title' => __( 'Holidays' ),
        ] );
    }
}
