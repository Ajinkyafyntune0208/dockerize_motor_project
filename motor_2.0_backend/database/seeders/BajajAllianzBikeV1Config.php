<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class BajajAllianzBikeV1Config extends Seeder
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
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE_NEW_BUSINESS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE_NEW_BUSINESS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE_OD',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE_OD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PRODUCT_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.AUTH_NAME',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.AUTH_NAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.NEW_TP_URL_ENABLE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.NEW_TP_URL_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.USERNAME_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.USERNAME_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.USERNAME_POS_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.USERNAME_POS_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.AUTH_NAME_POS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.AUTH_NAME_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PASSWORD_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PASSWORD_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.AUTH_PASS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.AUTH_PASS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BRANCH_OFFICE_CODE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BRANCH_OFFICE_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.QUOTE_TP_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.QUOTE_TP_URL',
                'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL',
                'value' => 'https://webservicesint.bajajallianz.com/WebServicePolicy/WebServicePolicyPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.IS_RSA_ENABLED',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.IS_RSA_ENABLED',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.CAR_USERNAME_POS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.CAR_USERNAME_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PROPOSAL_TP_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PROPOSAL_TP_URL',
                'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PROPOSAL_END_POINT_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PROPOSAL_END_POINT_URL',
                'value' => 'https://webservicesint.bajajallianz.com/WebServicePolicy/WebServicePolicyPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.POLICY_ISSUE_TP_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.POLICY_ISSUE_TP_URL',
                'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL_ISSUE_POLICY',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL_ISSUE_POLICY',
                'value' => 'https://webservicesint.bajajallianz.com/WebServicePolicy/WebServicePolicyPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PAYMENT_GATEWAY_SOURCE_NAME',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PAYMENT_GATEWAY_SOURCE_NAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PAYMENT_GATEWAY_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.PAYMENT_GATEWAY_URL',
                'value' => 'https://webservicesint.bajajallianz.com/Insurance/WS/new_cc_payment.jsp',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BAJAJ_ALLIANZ_CAR_USERNAME',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BAJAJ_ALLIANZ_CAR_USERNAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BAJAJ_ALLIANZ_CAR_USERNAME_POS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BAJAJ_ALLIANZ_CAR_USERNAME_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BAJAJ_ALLIANZ_RENEWAL_PASSWORD',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.BAJAJ_ALLIANZ_RENEWAL_PASSWORD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.FETCH_RENEWAL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.FETCH_RENEWAL',
                'value' => 'https://webservicesint.bajajallianz.com/BjazMotorWebservice/getrnwdata',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.ISSUE_POLICY_URL_RENEWAL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.ISSUE_POLICY_URL_RENEWAL',
                'value' => 'https://webservicesint.bajajallianz.com/BjazMotorWebservice/issuepolicy',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL_POLICY_PDF_DOWNLOAD_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL_POLICY_PDF_DOWNLOAD_TP',
                'value' => 'https://api.bagicpt.bajajallianz.com/ext/common/commoncs/BjazDownloadPDFWs/policypdfdownload',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL_POLICY_PDF_DOWNLOAD',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.END_POINT_URL_POLICY_PDF_DOWNLOAD',
                'value' => 'https://webservicesint.bajajallianz.com/BjazDownloadPDFWs/policypdfdownload',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.CHECK_PG_TRANS_STATUS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.BIKE.CHECK_PG_TRANS_STATUS',
                'value' => 'https://webservicesint.bajajallianz.com/BagicHealthWebservice/checkpgtransstatus',
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