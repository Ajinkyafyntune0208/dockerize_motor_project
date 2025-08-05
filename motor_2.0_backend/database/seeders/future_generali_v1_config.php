<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class future_generali_v1_config extends Seeder
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
            // for quote
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_POS_DISABLED',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_POS_DISABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_FG_POS_DISABLED',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_FG_POS_DISABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_POS_ENABLED',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_POS_ENABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_POS_TESTING_MODE_ENABLE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_POS_TESTING_MODE_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_ENABLED_AFFINITY',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_ENABLED_AFFINITY',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_ADDON_ENABLE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_ADDON_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.END_POINT_URL',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.END_POINT_URL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.COMPANY_ID_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.COMPANY_ID_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.BRANCH_ID_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.BRANCH_ID_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.APP_ID_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.APP_ID_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.BREAKIN_CHECK_URL',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.BREAKIN_CHECK_URL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_ENABLED_FOR_CAR',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_ENABLED_FOR_CAR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_FETCH_POLICY_DETAILS',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_FETCH_POLICY_DETAILS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_VENDOR_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_VENDOR_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.ENABLED',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.ENABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_NON_POS',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_NON_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.IS_NON_POS',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.IS_NON_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_GATEWAY_LINK_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_GATEWAY_LINK_MOTOR',
                'value' => 'https://fgnluat.fggeneral.in/Ecom_UAT/WEBAPPLN/UI/Common/WebAggPay.aspx',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_TYPE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_TYPE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_OPTION',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_OPTION',
                'value' => '3',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_FAILURE_CALLBACK_URL',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PAYMENT_FAILURE_CALLBACK_URL',
                'value' => 'https://uatcar.rbstaging.in/payment-failure',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID',
                'value' => 'Webnew',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD',
                'value' => 'Webnew@123',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_LINK_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PDF_LINK_MOTOR',
                'value' => 'http://fgnluat.fggeneral.in/PDFDownload/PDF.asmx',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.PROPOSAL_PDF_URL',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.PROPOSAL_PDF_URL',
                'value' => 'policyDocs/Car/',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_CREATE_POLICY_DETAILS',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.RENEWAL_CREATE_POLICY_DETAILS',
                'value' => 'https://fgnluat.fggeneral.in/TCS-Renewal/API/MotorRenewal/CreatePolicy',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.CAR.MOTOR_CHECK_TRN_STATUS',
                'key' => 'IC.FUTURE_GENERALI.V1.CAR.MOTOR_CHECK_TRN_STATUS',
                'value' => 'Y',
                'environment' => 'local'
            ],
        ];
        foreach ($configs as  $value) {
            $checkConfig = ConfigSetting::where(['key' => $value['key']])->first();
            if($checkConfig == null){
                ConfigSetting::updateOrCreate($value);
            }
        }
        // DB::table('config_settings')->insert();

    }
}
