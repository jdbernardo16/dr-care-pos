<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        include dirname( __FILE__ ) . '/../permissions/attendance.php';
        include dirname( __FILE__ ) . '/../permissions/payroll.php';

        $this->call( DefaultCategorySeeder::class );
        $this->call( DefaultUnitGroupSeeder::class );
        $this->call( DefaultProviderSeeder::class );
        $this->call( CustomerGroupSeeder::class );
    }
}
