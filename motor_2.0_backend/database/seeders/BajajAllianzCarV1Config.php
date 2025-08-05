<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class BajajAllianzCarV1Config extends Seeder
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
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.ENABLE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_NEW',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_NEW',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_OD',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_OD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.BRANCH_OFFICE_CODE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.BRANCH_OFFICE_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_TP_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_TP_URL',
                'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_URL',
                'value' => 'http://webservicesint.bajajallianz.com/WebServicePolicy/WebServicePolicyPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.NEW_TP_URL_ENABLE',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.NEW_TP_URL_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PROPOSAL_TP_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PROPOSAL_TP_URL',
                'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PROPOSAL_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PROPOSAL_URL',
                'value' => 'http://webservicesint.bajajallianz.com/WebServicePolicy/WebServicePolicyPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_PIN_GENERATION',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_PIN_GENERATION',
                'value' => 'http://webservicesint.bajajallianz.com:80/BagicMotorWS/BagicMotorWSPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.POLICY_ISSUE_TP_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.POLICY_ISSUE_TP_URL',
                'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.POLICY_ISSUE_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.POLICY_ISSUE_URL',
                'value' => 'http://webservicesint.bajajallianz.com/WebServicePolicy/WebServicePolicyPort',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PAYMENT_GATEWAY_SOURCE_NAME',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PAYMENT_GATEWAY_SOURCE_NAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PAYMENT_GATEWAY_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.PAYMENT_GATEWAY_URL',
                'value' => 'http://webservicesint.bajajallianz.com/Insurance/WS/new_cc_payment.jsp',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_POLICY_PDF_DOWNLOAD_TP',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_POLICY_PDF_DOWNLOAD_TP',
                'value' => 'https://api.bagicpt.bajajallianz.com/ext/common/commoncs/BjazDownloadPDFWs/policypdfdownload',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_POLICY_PDF_DOWNLOAD',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_POLICY_PDF_DOWNLOAD',
                'value' => 'http://webservicesint.bajajallianz.com/BjazDownloadPDFWs/policypdfdownload',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.CHECK_PG_TRANS_STATUS',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.CHECK_PG_TRANS_STATUS',
                'value' => 'http://webservicesint.bajajallianz.com/BagicHealthWebservice/checkpgtransstatus',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.BAJAJ_ALLIANZ.V1.CAR.BREAKIN_POLICY_ISSUE_URL',
                'label' => 'IC.BAJAJ_ALLIANZ.V1.CAR.BREAKIN_POLICY_ISSUE_URL',
                'value' => 'http://webservicesint.bajajallianz.com:80/WebServicePolicy/WebServicePolicyPort',
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
