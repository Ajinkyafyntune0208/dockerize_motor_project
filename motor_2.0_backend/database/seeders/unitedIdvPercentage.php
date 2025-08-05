<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class unitedIdvPercentage extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \Illuminate\Support\Facades\DB::table('united_idv_percentages')->insert([
            ['age_interval' => '1-2', 'percentage' => '20'],
            ['age_interval' => '2-3', 'percentage' => '30'],
            ['age_interval' => '3-4', 'percentage' => '40'],
            ['age_interval' => '4-5', 'percentage' => '50'],
            ['age_interval' => '5-6', 'percentage' => '55'],
            ['age_interval' => '6-7', 'percentage' => '60'],
            ['age_interval' => '7-8', 'percentage' => '65'],
            ['age_interval' => '8-9', 'percentage' => '70'],
            ['age_interval' => '9-10', 'percentage' => '75']
        ]);
    }
}
