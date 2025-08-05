<?php

namespace Database\Seeders;

use App\Models\ConfigSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BajajTpServiceUrlsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        
        if(env('APP_ENV') == 'local')
        {
            $bajajNewTpUrl = [
                ['label' => 'BAJAJ_ALLIANZ_CAR_QUOTE_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_QUOTE_TP_URL','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_PROPOSAL_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_PROPOSAL_TP_URL','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_POLICY_ISSUE_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_POLICY_ISSUE_TP_URL','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'END_POINT_URL_BAJAJ_ALLIANZ_BIKE', 'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE','value' => 'http://webservicesint.bajajallianz.com:80/WebServicePolicy/WebServicePolicyPort','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CV_PROPOSAL_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PROPOSAL_TP_URL','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CV_QUOTE_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_QUOTE_TP_URL','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE_TP', 'key' => 'constants.motor.bajaj_allianz.PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE_TP','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY_TP', 'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY_TP','value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl','environment' => 'local'],
                ['label' => 'END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP', 'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP','value' => 'https://api.bagicpt.bajajallianz.com/ext/common/commoncs/BjazDownloadPDFWs/policypdfdownload','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_USERNAME_TP', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_TP','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_PASSWORD_TP', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_PASSWORD_TP','value' => 'test','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_USERNAME_POS_TP', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_POS_TP','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE','value' => 'N','environment' => 'local']
            ];

            ConfigSettings::insert($bajajNewTpUrl);
            
        }else
        {
            $bajajNewTpUrl = [
                ['label' => 'BAJAJ_ALLIANZ_CAR_QUOTE_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_QUOTE_TP_URL','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_PROPOSAL_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_PROPOSAL_TP_URL','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_POLICY_ISSUE_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_POLICY_ISSUE_TP_URL','value' => '','environment' => 'local'],
                ['label' => 'END_POINT_URL_BAJAJ_ALLIANZ_BIKE', 'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CV_PROPOSAL_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PROPOSAL_TP_URL','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CV_QUOTE_TP_URL', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_QUOTE_TP_URL','value' => '','environment' => 'local'],
                ['label' => 'PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE_TP', 'key' => 'constants.motor.bajaj_allianz.PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE_TP','value' => '','environment' => 'local'],
                ['label' => 'END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY_TP', 'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY_TP','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_USERNAME_TP', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_TP','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_PASSWORD_TP', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_PASSWORD_TP','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_CAR_USERNAME_POS_TP', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_POS_TP','value' => '','environment' => 'local'],
                ['label' => 'BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE', 'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE','value' => 'N','environment' => 'local'],
            ];

            ConfigSettings::insert($bajajNewTpUrl);
        }
        
    }
}
