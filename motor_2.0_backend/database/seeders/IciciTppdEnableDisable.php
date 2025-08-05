<?php

namespace Database\Seeders;

use App\Models\ConfigSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IciciTppdEnableDisable extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $ICICI_LOMBARD_TPPD_ENABLE = ConfigSettings::where('key','constants.ICICI_LOMBARD_TPPD_ENABLE')->first();
        if($ICICI_LOMBARD_TPPD_ENABLE)
        {
            DB::table('config_settings')
                ->where('key','constants.ICICI_LOMBARD_TPPD_ENABLE')
                ->update(
                    ['value' => 'N']
                );
        }else
        {
            DB::table('config_settings')->insert([
                ['label' => 'ICICI_LOMBARD_TPPD_ENABLE', 'key' => 'constants.ICICI_LOMBARD_TPPD_ENABLE','value' => 'N','environment' => 'local']
            ]);  
        }

        $FETCH_ACKO_CKYC_JOB_ENABLE = ConfigSettings::where('key','constants.motor.FETCH_ACKO_CKYC_JOB_ENABLE')->first();
        if($FETCH_ACKO_CKYC_JOB_ENABLE)
        {
            DB::table('config_settings')
                ->where('key','constants.motor.FETCH_ACKO_CKYC_JOB_ENABLE')
                ->update(
                    ['value' => 'N']
                );
        }else
        {
            DB::table('config_settings')->insert([
                ['label' => 'FETCH_ACKO_CKYC_JOB_ENABLE', 'key' => 'constants.motor.FETCH_ACKO_CKYC_JOB_ENABLE','value' => 'N','environment' => 'local']
            ]);  
        }

    }
}
