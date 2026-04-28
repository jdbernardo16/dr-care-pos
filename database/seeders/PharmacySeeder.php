<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    /**
     * Run the pharmacy database seeds.
     *
     * Seeds categories, units, and 100+ realistic pharmacy products
     * with stock levels and expiration dates.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            PharmacyCategorySeeder::class,
            PharmacyUnitSeeder::class,
            PharmacyProductSeeder::class,
        ]);
    }
}
