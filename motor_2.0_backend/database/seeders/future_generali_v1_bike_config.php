<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class future_generali_v1_bike_config extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $config = [

            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.AGENT_CODE',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.AGENT_CODE',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.BRANCH_CODE',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.BRANCH_CODE',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.DISABLE_RSA_CALCULATION',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.DISABLE_RSA_CALCULATION',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_VENDOR_CODE',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_VENDOR_CODE',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_BIKE_FETCH_POLICY_DETAILS',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_BIKE_FETCH_POLICY_DETAILS',

                'value' => '',
                
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_GATEWAY_LINK_MOTOR',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_GATEWAY_LINK_MOTOR',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_TYPE',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_TYPE',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_OPTION',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_OPTION',

                'value' => '',

            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_FAILURE_CALLBACK_URL',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_FAILURE_CALLBACK_URL',

                'value' => '',

            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PDF_USER_ID',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PDF_USER_ID',    

                'value' => '',

            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PDF_PASSWORD',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PDF_PASSWORD',

                'value' => '',

            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PROPOSAL_PDF_URL',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PROPOSAL_PDF_URL',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_VENDOR_CODE',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_VENDOR_CODE',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_CREATE_POLICY_DETAILS',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_CREATE_POLICY_DETAILS',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.PDF_LINK_MOTOR',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.PDF_LINK_MOTOR',

                'value' => '',

            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.MOTOR_CHECK_TRN_STATUS',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.MOTOR_CHECK_TRN_STATUS',

                'value' => '',
            ],
            [
                'key' => 'IC.FUTURE_GENERALI.V1.BIKE.ENABLED',

                'label' => 'IC.FUTURE_GENERALI.V1.BIKE.ENABLED',

                'value' => '',
            ],
        ];
        foreach ($config as  $value) {
            $checkConfig = ConfigSetting::where(['key' => $value['key']])->first();
            if($checkConfig == null){
                ConfigSetting::updateOrCreate($value);
            }
        }

    }
}
