<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class relianceCarV1Config extends Seeder
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
                'key' => 'IC.RELIANCE.V1.CAR.ENABLE',
                'label' => 'IC.RELIANCE.V1.CAR.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.IS_GDD_ENABLED',
                'label' => 'IC.RELIANCE.V1.CAR.IS_GDD_ENABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.TP_USERID',
                'label' => 'IC.RELIANCE.V1.CAR.TP_USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.PAYMENT_GATEWAY_LINK',
                'label' => 'IC.RELIANCE.V1.CAR.PAYMENT_GATEWAY_LINK',
                'value' => 'https://api.brobotinsurance.com/common/PaymentIntegration/PaymentIntegration',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL_STATUS',
                'label' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL_STATUS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW_API_ENABLE',
                'label' => 'IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW_API_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW',
                'label' => 'IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK_NEW',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.SECURE_AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.CAR.SECURE_AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK',
                'label' => 'IC.RELIANCE.V1.CAR.POLICY_DWLD_LINK',
                'value' => 'https://api.brobotinsurance.com/common/api/service/DownloadScheduleLink',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.USERID',
                'label' => 'IC.RELIANCE.V1.CAR.USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.TP_SOURCE_SYSTEM_ID',
                'label' => 'IC.RELIANCE.V1.CAR.TP_SOURCE_SYSTEM_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.SOURCE_SYSTEM_ID',
                'label' => 'IC.RELIANCE.V1.CAR.SOURCE_SYSTEM_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.TP_AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.CAR.TP_AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.CAR.AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.IS_RETURN_TO_INVOICE',
                'label' => 'IC.RELIANCE.V1.CAR.IS_RETURN_TO_INVOICE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_COVERAGE',
                'label' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_COVERAGE',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/CoverageDetailsForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY',
                'label' => 'IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PREMIUM',
                'label' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PREMIUM',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/PremiumCalulationForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.IS_POS_TESTING_MODE_ENABLE',
                'label' => 'IC.RELIANCE.V1.CAR.IS_POS_TESTING_MODE_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_VENDORCODE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_VENDORCODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_VENDORCODEVALUE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_VENDORCODEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_BASCODE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_BASCODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_BASCODEVALUE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_BASCODEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_BASMOBILE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_BASMOBILE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_SMCODE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_SMCODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_SMCODEVALUE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_SMCODEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_SMMOBILENO',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_SMMOBILENO',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_RGICL_OFFICE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_RGICL_OFFICE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_RGICL_OFFICEVALUE',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_RGICL_OFFICEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_LEADCREATEDBYSYSTEM',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_LEADCREATEDBYSYSTEM',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_INTIMATORNAME',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_INTIMATORNAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_INTIMATORMOBILENO',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_INTIMATORMOBILENO',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_USERID',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.LEAD_USERPASSWORD',
                'label' => 'IC.RELIANCE.V1.CAR.LEAD_USERPASSWORD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.CAR_LEAD_CREATION_URL',
                'label' => 'IC.RELIANCE.V1.CAR.CAR_LEAD_CREATION_URL',
                'value' => 'https://api.brobotinsurance.com/RAS_InsertLead',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL',
                'label' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/ProposalCreationForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_FETCH_RENEWAL',
                'label' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_FETCH_RENEWAL',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/PremiumCalulationForMotorRenewal',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL_POST_INSPECTION',
                'label' => 'IC.RELIANCE.V1.CAR.END_POINT_URL_PROPOSAL_POST_INSPECTION',
                'value' => 'https://api.brobotinsurance.com/breakin1/API/Service/ProposalCreationForMotorPostInspection',
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
