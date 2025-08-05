<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class relianceCvV1Config extends Seeder
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
                'key' => 'IC.RELIANCE.V1.CV.ENABLE',
                'label' => 'IC.RELIANCE.V1.CV.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.BRANCH_CODE',
                'label' => 'IC.RELIANCE.V1.CV.BRANCH_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.USERID',
                'label' => 'IC.RELIANCE.V1.CV.USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.SOURCE_SYSTEM_ID',
                'label' => 'IC.RELIANCE.V1.CV.SOURCE_SYSTEM_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.CV.AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.3W_TP_USERID',
                'label' => 'IC.RELIANCE.V1.CV.3W_TP_USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.3W_TP_SOURCE_SYSTEM_ID',
                'label' => 'IC.RELIANCE.V1.CV.3W_TP_SOURCE_SYSTEM_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.3W_TP_AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.CV.3W_TP_AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.END_POINT_URL_COVERAGE',
                'label' => 'IC.RELIANCE.V1.CV.END_POINT_URL_COVERAGE',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/CoverageDetailsForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.OCP_APIM_SUBSCRIPTION_KEY',
                'label' => 'IC.RELIANCE.V1.CV.OCP_APIM_SUBSCRIPTION_KEY',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PREMIUM',
                'label' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PREMIUM',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/PremiumCalulationForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_VENDORCODE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_VENDORCODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_VENDORCODEVALUE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_VENDORCODEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_BASCODE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_BASCODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_BASCODEVALUE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_BASCODEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_BASMOBILE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_BASMOBILE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_SMCODE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_SMCODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_SMCODEVALUE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_SMCODEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_SMMOBILENO',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_SMMOBILENO',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_RGICL_OFFICE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_RGICL_OFFICE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_RGICL_OFFICEVALUE',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_RGICL_OFFICEVALUE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_LEADCREATEDBYSYSTEM',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_LEADCREATEDBYSYSTEM',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_INTIMATORNAME',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_INTIMATORNAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_INTIMATORMOBILENO',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_INTIMATORMOBILENO',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_USERID',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_USERID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.LEAD_USERPASSWORD',
                'label' => 'IC.RELIANCE.V1.CV.LEAD_USERPASSWORD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.END_POINT_URL_LEAD',
                'label' => 'IC.RELIANCE.V1.CV.END_POINT_URL_LEAD',
                'value' => 'https://api.brobotinsurance.com/RAS_InsertLead',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PROPOSAL',
                'label' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PROPOSAL',
                'value' => 'https://api.brobotinsurance.com/smartzone-motor1/API/Service/ProposalCreationForMotor',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PROPOSAL_POST_INSPECTION',
                'label' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PROPOSAL_POST_INSPECTION',
                'value' => 'https://api.brobotinsurance.com/breakin1/API/Service/ProposalCreationForMotorPostInspection',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.PAYMENT_GATEWAY_LINK',
                'label' => 'IC.RELIANCE.V1.CV.PAYMENT_GATEWAY_LINK',
                'value' => 'https://api.brobotinsurance.com/common/PaymentIntegration/PaymentIntegration',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.POLICY_DWLD_LINK_NEW_API_ENABLE',
                'label' => 'IC.RELIANCE.V1.CV.POLICY_DWLD_LINK_NEW_API_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.POLICY_DWLD_LINK_NEW',
                'label' => 'IC.RELIANCE.V1.CV.POLICY_DWLD_LINK_NEW',
                'value' => 'https://api.brobotinsurance.com/common/api/service/DownloadScheduleLink',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.SECURE_AUTH_TOKEN',
                'label' => 'IC.RELIANCE.V1.CV.SECURE_AUTH_TOKEN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.POLICY_DWLD_LINK',
                'label' => 'IC.RELIANCE.V1.CV.POLICY_DWLD_LINK',
                'value' => 'https://rzonews.reliancegeneral.co.in/API/Service/GeneratePolicyschedule',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PROPOSAL_STATUS',
                'label' => 'IC.RELIANCE.V1.CV.END_POINT_URL_PROPOSAL_STATUS',
                'value' => 'https://api.brobotinsurance.com/common/API/Service/ProposalStatusForEndorsement',
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
