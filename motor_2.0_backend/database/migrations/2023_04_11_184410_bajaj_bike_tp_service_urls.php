<?php

use App\Models\ConfigSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BajajBikeTpServiceUrls extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //

        if(env('APP_ENV') == 'local')
        {
            $BAJAJ_ALLIANZ_BIKE_USERNAME_TP = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_TP');
            $BAJAJ_ALLIANZ_BIKE_PASSWORD_TP = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PASSWORD_TP');
            $BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP');
            $BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL');
            $BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL');
            $BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL');
            $END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP = config('constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_BIKE_TP');
            
            if ($BAJAJ_ALLIANZ_BIKE_USERNAME_TP == null || empty($BAJAJ_ALLIANZ_BIKE_USERNAME_TP)) {
                ConfigSettings::insert([
                    'label' => 'BAJAJ_ALLIANZ_BIKE_USERNAME_TP',
                    'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_TP',
                    'value' => '',
                    'environment' => 'local'
                ]);
            }

            if ($BAJAJ_ALLIANZ_BIKE_PASSWORD_TP == null || empty($BAJAJ_ALLIANZ_BIKE_PASSWORD_TP)) {
                ConfigSettings::insert([
                    'label' => 'BAJAJ_ALLIANZ_BIKE_PASSWORD_TP',
                    'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PASSWORD_TP',
                    'value' => '',
                    'environment' => 'local'
                ]);
            }

            if ($BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP == null || empty($BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP)) {
                ConfigSettings::insert([
                    'label' => 'BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP',
                    'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP',
                    'value' => '',
                    'environment' => 'local'
                ]);
            }

            if ($BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL == null || empty($BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL)) {
                ConfigSettings::insert([
                    'label' => 'BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL',
                    'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL',
                    'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                    'environment' => 'local'
                ]);
            }

            if ($BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL == null || empty($BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL)) {
                ConfigSettings::insert([
                    'label' => 'BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL',
                    'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL',
                    'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                    'environment' => 'local'
                ]);
            }

            if ($BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL == null || empty($BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL)) {
                ConfigSettings::insert([
                    'label' => 'BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL',
                    'key' => 'constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL',
                    'value' => 'https://soapapi.bagicpt.bajajallianz.com/ext/motor/aggregatorsrvc/soap/WebServicePolicy/WebServicePolicyPort.wsdl',
                    'environment' => 'local'
                ]);
            }

            if ($END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP == null || empty($END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP)) {
                ConfigSettings::insert([
                    'label' => 'END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP',
                    'key' => 'constants.motor.bajaj_allianz.END_POINT_URL_POLICY_PDF_DOWNLOAD_BAJAJ_ALLIANZ_MOTOR_TP',
                    'value' => 'https://api.bagicpt.bajajallianz.com/ext/common/commoncs/BjazDownloadPDFWs/policypdfdownload',
                    'environment' => 'local'
                ]);
            }            
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
