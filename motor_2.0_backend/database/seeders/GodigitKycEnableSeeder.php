<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GodigitKycEnableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
            DB::table('config_settings')->insert([
                ['label' => 'constants.IS_CKYC_ENABLED_GODIGIT', 'key' => 'constants.IS_CKYC_ENABLED_GODIGIT','value' => 'Y','environment' => 'local']
            ]);

    }
}
