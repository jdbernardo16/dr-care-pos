<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class HolidayService
{
    public function createHoliday( $data )
    {
        $data['author_id'] = Auth::id();
        $holiday = Holiday::create( $data );

        return [
            'status' => 'success',
            'message' => __( 'Holiday created.' ),
            'data' => compact( 'holiday' ),
        ];
    }

    public function updateHoliday( $id, $data )
    {
        $holiday = Holiday::findOrFail( $id );
        $holiday->fill( $data );
        $holiday->save();

        return [
            'status' => 'success',
            'message' => __( 'Holiday updated.' ),
            'data' => compact( 'holiday' ),
        ];
    }

    public function deleteHoliday( $id )
    {
        $holiday = Holiday::findOrFail( $id );
        $holiday->delete();

        return [
            'status' => 'success',
            'message' => __( 'Holiday deleted.' ),
        ];
    }

    public function getHolidays( $filters = [] )
    {
        $query = Holiday::query();

        if ( ! empty( $filters['year'] ) ) {
            $query->whereYear( 'date', $filters['year'] );
        }

        if ( ! empty( $filters['month'] ) ) {
            $query->whereMonth( 'date', $filters['month'] );
        }

        if ( ! empty( $filters['type'] ) ) {
            $query->where( 'type', $filters['type'] );
        }

        return $query->orderBy( 'date' )->get();
    }

    /**
     * Get holiday info for a specific date.
     * Returns null if not a holiday.
     */
    public function getHolidayForDate( $date )
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        $holiday = Holiday::whereDate( 'date', $dateStr )->first();

        // Check recurring holidays by month+day
        if ( ! $holiday ) {
            $parsed = Carbon::parse( $dateStr );
            $holiday = Holiday::where( 'is_recurring', true )
                ->whereMonth( 'date', $parsed->month )
                ->whereDay( 'date', $parsed->day )
                ->first();
        }

        return $holiday;
    }

    /**
     * Get the pay multiplier for a given date.
     * Returns 1.0 (regular rate) if not a holiday.
     */
    public function getMultiplierForDate( $date )
    {
        $holiday = $this->getHolidayForDate( $date );

        if ( ! $holiday ) {
            return 1.0;
        }

        return (float) $holiday->multiplier;
    }

    /**
     * Check if a date is a Sunday (rest day).
     */
    public function isRestDay( $date )
    {
        $parsed = $date instanceof Carbon ? $date : Carbon::parse( $date );
        return $parsed->dayOfWeek === Carbon::SUNDAY;
    }

    /**
     * Get the applicable overtime rate multiplier.
     *
     * Philippine law:
     * - Regular day OT (beyond 8hrs): +25% → 1.25
     * - Rest day / Special non-working OT: +30% → 1.30
     * - Regular holiday OT: +100% → 2.00
     * - Night differential (10pm-6am): +10% per hour → 1.10
     */
    public function getOvertimeRateMultiplier( $date )
    {
        $holiday = $this->getHolidayForDate( $date );

        if ( $holiday && $holiday->type === Holiday::TYPE_REGULAR ) {
            return 2.00; // Regular holiday: double pay
        }

        if ( $holiday || $this->isRestDay( $date ) ) {
            return 1.30; // Special non-working or rest day: +30%
        }

        return 1.25; // Regular day OT: +25%
    }

    /**
     * Check if a time falls within night differential hours (10pm - 6am).
     */
    public static function isNightDifferential( Carbon $time )
    {
        $hour = (int) $time->format( 'G' );
        return $hour >= 22 || $hour < 6;
    }
}
