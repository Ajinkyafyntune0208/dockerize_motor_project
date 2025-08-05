<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GodigitKycUrlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        if(env('APP_ENV') == 'local')
        {
            DB::table('config_settings')->insert([
                ['label' => 'GODIGIT_KYC_VERIFICATION_API', 'key' => 'constants.IcConstants.godigit.GODIGIT_KYC_VERIFICATION_API','value' => 'https://preprod-qnb.godigit.com/digit/base/services/v1/kyc/status','environment' => 'local']
            ]);
        }else
        {
            DB::table('config_settings')->insert([
                ['label' => 'GODIGIT_KYC_VERIFICATION_API', 'key' => 'constants.IcConstants.godigit.GODIGIT_KYC_VERIFICATION_API','value' => 'https://prod-qnb.godigit.com/digit/base/services/v1/kyc/status','environment' => 'local']
            ]);
        }
    }
}
