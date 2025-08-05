<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class future_generali_v1_gcv_config extends Seeder
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
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.VENDOR_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.VENDOR_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.VENDOR_CODE_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.VENDOR_CODE_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.AGENT_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.AGENT_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.AGENT_CODE_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.AGENT_CODE_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.BRANCH_CODE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.BRANCH_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.BRANCH_CODE_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.BRANCH_CODE_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.END_POINT_URL',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.END_POINT_URL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.COMPANY_ID_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.COMPANY_ID_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.BRANCH_ID_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.BRANCH_ID_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.APP_ID_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.APP_ID_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PAYMENT_GATEWAY_LINK',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PAYMENT_GATEWAY_LINK',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PAYMENT_TYPE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PAYMENT_TYPE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PAYMENT_OPTION',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PAYMENT_OPTION',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_USER_ID',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_USER_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_PASSWORD',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_PASSWORD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_USER_ID_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_USER_ID_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_PASSWORD_CORPORATE',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_PASSWORD_CORPORATE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_LINK_MOTOR',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PDF_LINK_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.PROPOSAL_PDF_URL',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.PROPOSAL_PDF_URL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.MOTOR_CHECK_TRN_STATUS',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.MOTOR_CHECK_TRN_STATUS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.FUTURE_GENERALI.V1.GCV.ENABLED',
                'key' => 'IC.FUTURE_GENERALI.V1.GCV.ENABLED',
                'value' => '',
                'environment' => 'local'
            ],


        ];
        foreach ($configs as  $value) {
            $checkConfig = ConfigSetting::where(['key' => $value['key']])->first();
            if($checkConfig == null){
                ConfigSetting::updateOrCreate($value);
            }
        }
    }
}
