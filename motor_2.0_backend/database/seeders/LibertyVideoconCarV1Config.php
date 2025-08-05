<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class LibertyVideoconCarV1Config extends Seeder
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
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.ENABLE',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.RTI_DISABLED',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.RTI_DISABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER_POS',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME_POS',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_OD',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_OD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_TP',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_PACKAGE',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_PACKAGE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION',
                'value' => 'https://api-uat.libertyinsurance.in/motor/API/IMDTPService/PostPremiumDetails',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.FETCH_RENEWAL_URL',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.FETCH_RENEWAL_URL',
                'value' => 'https://api-uat.libertyinsurance.in/motor/API/IMDTPService/GetRenewalPolicyDetails',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_USER_ID',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_USER_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_PASSWORD',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_PASSWORD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.EMAIL',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.EMAIL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.AgencyID',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.AgencyID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.BranchID',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.BranchID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.END_POINT_URL_PI_LEAD_ID_CREATE',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.breakin.END_POINT_URL_PI_LEAD_ID_CREATE',
                'value' => 'https://motorpi-uat.libertyinsurance.in/api/MotoVeysClaimLook.asmx',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_GATEWAY_LINK',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_GATEWAY_LINK',
                'value' => 'https://api-uat.libertyinsurance.in/TPPayment/Home/CapturePayment',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_SOURCE',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_SOURCE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.OTP',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.OTP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.CREATE_POLICY',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.CREATE_POLICY',
                'value' => 'https://api-uat.libertyinsurance.in/motor/API/IMDTPService/GetPolicy',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.POLICY_PDF',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.POLICY_PDF',
                'value' => 'https://api-uat.libertyinsurance.in/Motor/API/IMDTPService/GetPolicySchedule',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_STATUS_CHECK_END_POINT_URL',
                'label' => 'IC.LIBERTY_VIDEOCON.V1.CAR.PAYMENT_STATUS_CHECK_END_POINT_URL',
                'value' => 'https://api-uat.libertyinsurance.in/Motor/api/IMDTPService/checkPaymentStatus?TransactionIDByPayU=',
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
