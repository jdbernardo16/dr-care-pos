<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PayrollAttendanceSeeder extends Seeder
{
    /**
     * Seed 5 employees with attendance for April 2026 (weekdays, 7am-5pm).
     */
    public function run()
    {
        $employees = [
            [ 'username' => 'john.smith',   'email' => 'john@drcare.test',  'hourly_rate' => 45.00 ],
            [ 'username' => 'jane.doe',     'email' => 'jane@drcare.test',  'hourly_rate' => 55.00 ],
            [ 'username' => 'carlos.garcia', 'email' => 'carlos@drcare.test', 'hourly_rate' => 50.00 ],
            [ 'username' => 'maria.santos',  'email' => 'maria@drcare.test', 'hourly_rate' => 60.00 ],
            [ 'username' => 'ahmed.khan',   'email' => 'ahmed@drcare.test',  'hourly_rate' => 48.00 ],
        ];

        $admin = User::where( 'username', 'admin' )->first();

        if ( ! $admin ) {
            $this->command->error( 'Admin user not found. Run the default seeder first.' );
            return;
        }

        foreach ( $employees as $data ) {
            $user = User::where( 'username', $data['username'] )->first();

            if ( ! $user ) {
                $user = new User;
                $user->username = $data['username'];
                $user->email = $data['email'];
                $user->password = Hash::make( 'password' );
                $user->hourly_rate = $data['hourly_rate'];
                $user->active = true;
                $user->author_id = $admin->id;
                $user->save();
                $user->assignRole( 'nexopos.store.cashier' );

                $this->command->info( "Created user: {$user->username}" );
            } else {
                $user->hourly_rate = $data['hourly_rate'];
                $user->save();
                $this->command->info( "Updated hourly rate for: {$user->username}" );
            }

            $this->createAttendanceForApril( $user, $admin->id );
        }

        $this->command->info( '✅ 5 employees seeded with April 2026 attendance (weekdays 7am-5pm).' );
    }

    private function createAttendanceForApril( User $user, $authorId )
    {
        $start = Carbon::parse( '2026-04-01' );
        $end = Carbon::parse( '2026-04-30' );
        $created = 0;

        for ( $date = $start->copy(); $date->lte( $end ); $date->addDay() ) {
            // Skip weekends (Saturday=6, Sunday=0)
            if ( $date->isWeekend() ) {
                continue;
            }

            $clockIn = (clone $date)->setTime( 7, 0, 0 );   // 7:00 AM
            $clockOut = (clone $date)->setTime( 17, 0, 0 ); // 5:00 PM
            $totalHours = $clockIn->diffInMinutes( $clockOut ) / 60; // 10 hours

            // Check if attendance already exists for this user+date
            $exists = Attendance::forUser( $user->id )
                ->whereDate( 'clock_in_at', $clockIn->toDateString() )
                ->exists();

            if ( $exists ) {
                continue;
            }

            $attendance = new Attendance;
            $attendance->user_id = $user->id;
            $attendance->clock_in_at = $clockIn;
            $attendance->clock_out_at = $clockOut;
            $attendance->total_hours = $totalHours;
            $attendance->status = Attendance::STATUS_CLOCKED_OUT;
            $attendance->author_id = $authorId;
            $attendance->save();

            $created++;
        }

        $this->command->info( "  {$user->username}: {$created} attendance records created." );
    }
}
