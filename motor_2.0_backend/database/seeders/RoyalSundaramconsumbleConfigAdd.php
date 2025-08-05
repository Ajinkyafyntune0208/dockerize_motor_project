<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoyalSundaramconsumbleConfigAdd extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $value = \App\Models\ConfigSettings::where('key','ROYAL_SUNDARAM_ENABLE_CONSUMABLE_AS_BUILT_IN')
        ->first();
    if($value === null)
    {

        DB::table('config_settings')->insert([
            ['label' => 'ROYAL_SUNDARAM_ENABLE_CONSUMABLE_AS_BUILT_IN', 'key' => 'ROYAL_SUNDARAM_ENABLE_CONSUMABLE_AS_BUILT_IN','value' => 'N','environment' => 'local']
        ]);
    }else
    {
        DB::table('config_settings')
        ->where('key', 'ROYAL_SUNDARAM_ENABLE_CONSUMABLE_AS_BUILT_IN')
        ->update([
            'value'     => 'N'
        ]);
    }
    }
}
