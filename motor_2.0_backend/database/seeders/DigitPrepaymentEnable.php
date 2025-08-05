<?php

namespace Database\Seeders;

use App\Models\ConfigSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DigitPrepaymentEnable extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $value = \App\Models\ConfigSettings::where('key','GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE')
            ->first();
        if($value === null)
        {

            DB::table('config_settings')->insert([
                ['label' => 'GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE', 'key' => 'GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE','value' => 'Y','environment' => 'local']
            ]);
        }else
        {
            DB::table('config_settings')
            ->where('key', 'GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE')
            ->update([
                'value'     => 'Y'
            ]);
        }
    }
}
