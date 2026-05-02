<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class Ph2026HolidaySeeder extends Seeder
{
    public function run()
    {
        $admin = User::where( 'username', 'admin' )->first();
        if ( ! $admin ) return;

        $holidays = [
            // Regular holidays (double pay — multiplier 2.0)
            [ 'name' => 'New Year\'s Day',              'date' => '2026-01-01', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'Araw ng Kagitingan',           'date' => '2026-04-09', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'Labor Day',                    'date' => '2026-05-01', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'Independence Day',             'date' => '2026-06-12', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'National Heroes Day',           'date' => '2026-08-31', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'Bonifacio Day',                'date' => '2026-11-30', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'Christmas Day',                'date' => '2026-12-25', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],
            [ 'name' => 'Rizal Day',                    'date' => '2026-12-30', 'type' => Holiday::TYPE_REGULAR, 'multiplier' => 2.00, 'is_recurring' => true ],

            // Special non-working holidays (+30% — multiplier 1.3)
            [ 'name' => 'EDSA Revolution Anniversary',   'date' => '2026-02-25', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => true ],
            [ 'name' => 'Black Saturday',                'date' => '2026-04-04', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => false ],
            [ 'name' => 'Ninoy Aquino Day',              'date' => '2026-08-21', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => true ],
            [ 'name' => 'All Saints\' Day',              'date' => '2026-11-01', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => true ],
            [ 'name' => 'All Souls\' Day',               'date' => '2026-11-02', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => true ],
            [ 'name' => 'Christmas Eve',                'date' => '2026-12-24', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => true ],
            [ 'name' => 'New Year\'s Eve',              'date' => '2026-12-31', 'type' => Holiday::TYPE_SPECIAL, 'multiplier' => 1.30, 'is_recurring' => true ],
        ];

        foreach ( $holidays as $data ) {
            $existing = Holiday::whereDate( 'date', $data['date'] )
                ->where( 'type', $data['type'] )
                ->first();

            if ( ! $existing ) {
                $holiday = new Holiday( $data );
                $holiday->author_id = $admin->id;
                $holiday->save();
            }
        }

        $this->command->info( '✅ Philippine 2026 holidays seeded.' );
    }
}
