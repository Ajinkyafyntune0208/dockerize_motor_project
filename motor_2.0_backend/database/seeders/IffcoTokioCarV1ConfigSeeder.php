<?php

namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class IffcoTokioCarV1ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $configs = [
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.ENABLE',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.ENABLE',
                'value' => '', //Y
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_PREMIUM_VA',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_PREMIUM_VA',
                'value' => 'https://staging.iffcotokio.co.in/portaltest/services/MotorPremiumWebserviceVA',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_PREMIUM_NB_VA',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_PREMIUM_NB_VA',
                'value' => 'https://staging.iffcotokio.co.in/portaltest/services/NewVehiclePremiumWebserviceVA',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_IDV',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_IDV',
                'value' => 'https://www.online.iffcotokio.co.in/ptnrportal/services/IDVWebService',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.CONTRACT_TYPE_CAR',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.CONTRACT_TYPE_CAR',
                'value' => '', //PCP
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.ZCOVER_BIKE_TP',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.ZCOVER_BIKE_TP',
                'value' => '', //AC
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.ZCOVER_BIKE_CO',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.ZCOVER_BIKE_CO',
                'value' => '', //CO
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.PARTNER_BRANCH_CAR',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.PARTNER_BRANCH_CAR',
                'value' => '', //BAJAJ_CAPITAL
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.PARTNER_CODE_CAR',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.PARTNER_CODE_CAR',
                'value' => '', //ITGIMOT104
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.PARTNER_SUB_BRANCH_CAR',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.PARTNER_SUB_BRANCH_CAR',
                'value' => '', //BAJAJ_CAPITAL
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.PAYMENT_GATEWAY_LINK',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.PAYMENT_GATEWAY_LINK',
                'value' => 'https://staging.iffcotokio.co.in/portaltest/MotorServiceReq',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_PDF',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.END_POINT_URL_PDF',
                'value' => 'https://staging.iffcotokio.co.in/partner-services/policy/download', //
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.AUTH_PASSWORD_CAR',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.AUTH_PASSWORD_CAR',
                'value' => '', //partner@2020
                'environment' => 'local'
            ],
            [
                'key' => 'IC.IFFCO_TOKIO.V1.CAR.ENDPOINT_PAYMENT_STATUS_CHECK',
                'label' => 'IC.IFFCO_TOKIO.V1.CAR.ENDPOINT_PAYMENT_STATUS_CHECK',
                'value' => 'https://staging.iffcotokio.co.in/portaltest/services/CheckPolicyStatus',
                'environment' => 'local'
            ],
        ];
        foreach ($configs as $config) {

            $checkConfig = ConfigSetting::where(['key' => $config['key']])->first();

            if($checkConfig == null){
                ConfigSetting::updateOrCreate($config);
            }
        }
    }
}
