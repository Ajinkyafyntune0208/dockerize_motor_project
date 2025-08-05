<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class relianceBikeV1Config extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $configs = [
            [
                'key' => 'IC.RELIANCE.V1.BIKE.ENABLE',
                'label' => 'IC.RELIANCE.V1.BIKE.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.TP_USERID',
                'label' => 'IC.RELIANCE.V1.BIKE.TP_USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.USERID',
                'label' => 'IC.RELIANCE.V1.BIKE.USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.TP_SOURCE_SYSTEM_ID',
                'label' => 'IC.RELIANCE.V1.BIKE.TP_SOURCE_SYSTEM_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.SOURCE_SYSTEM_ID',
                'label' => 'IC.RELIANCE.V1.BIKE.SOURCE_SYSTEM_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.TP_AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.BIKE.TP_AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.BIKE.AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.RTI2W',
                'label' => 'IC.RELIANCE.V1.BIKE.RTI2W',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_COVERAGE',
                'label' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_COVERAGE',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/CoverageDetailsForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.OCP_APIM_SUBSCRIPTION_KEY',
                'label' => 'IC.RELIANCE.V1.BIKE.OCP_APIM_SUBSCRIPTION_KEY',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_PREMIUM',
                'label' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_PREMIUM',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/PremiumCalulationForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_PROPOSAL',
                'label' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_PROPOSAL',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/ProposalCreationForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.PAYMENT_GATEWAY_LINK',
                'label' => 'IC.RELIANCE.V1.BIKE.PAYMENT_GATEWAY_LINK',
                'value' => 'https://api.brobotinsurance.com/common/PaymentIntegration/PaymentIntegration',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_PROPOSAL_STATUS',
                'label' => 'IC.RELIANCE.V1.BIKE.END_POINT_URL_PROPOSAL_STATUS',
                'value' => 'https://api.brobotinsurance.com/smartzone- motor1/API/Service/ProposalStatusForEndorsement',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.POLICY_DWLD_LINK_NEW_API_ENABLE',
                'label' => 'IC.RELIANCE.V1.BIKE.POLICY_DWLD_LINK_NEW_API_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.POLICY_DWLD_LINK_NEW',
                'label' => 'IC.RELIANCE.V1.BIKE.POLICY_DWLD_LINK_NEW',
                'value' => 'https://api.brobotinsurance.com/common/api/service/DownloadScheduleLink',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.SECURE_AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.BIKE.SECURE_AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.BIKE.POLICY_DWLD_LINK',
                'label' => 'IC.RELIANCE.V1.BIKE.POLICY_DWLD_LINK',
                'value' => 'https://api.brobotinsurance.com/common/api/service/DownloadScheduleLink',
                'environment' => 'local'
            ]
        ];

        foreach ($configs as $config) {

            $checkConfig = ConfigSetting::where(['key' => $config['key']])->first();
            
            if($checkConfig == null){
                ConfigSetting::updateOrCreate($config);
            }
        }
    }
}
