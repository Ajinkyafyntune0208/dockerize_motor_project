<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class HdfcBikeV1ConfigSeeder extends Seeder
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
                'key' => 'IC.HDFC_ERGO.V1.BIKE.ENABLE',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.ENABLE',
                'value' => 'N',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.SOURCE_ID',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.SOURCE_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.CREDENTIAL',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.CREDENTIAL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.CALCULATE_IDV',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.CALCULATE_IDV',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/GetCalculateIDV',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.CALCULATE_PREMIUM',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.CALCULATE_PREMIUM',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/CalculatePremium',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.GIC_BIKE_CREATE_PROPOSAL',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.GIC_BIKE_CREATE_PROPOSAL',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/CreateProposal',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.TRANSACTION_NO_SERIES_GIC',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.TRANSACTION_NO_SERIES_GIC',
                'value' => '1022',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.APP_ID',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.APP_ID',
                'value' => '10178',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.SUB_ID',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.SUB_ID',
                'value' => 'S000000226',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.GENERATE_CHECKSUM',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.GENERATE_CHECKSUM',
                'value' => 'https://hepgw.hdfcergo.com/PaymentUtilitiesService/PaymentUtilities.asmx/GenerateRequestChecksum',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.PAYMENT_URL',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.PAYMENT_URL',
                'value' => 'https://heapp21.hdfcergo.com/UAT/OnlineProducts/CCPGISUBSCRIBER/MakePayment.aspx',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.GENERATE_PDF',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.GENERATE_PDF',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/GetPolicyDocument',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.PROPOSAL_PDF_URL',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.PROPOSAL_PDF_URL',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.GENERATE_POLICY',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.GENERATE_POLICY',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/GeneratePolicy',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.GET_TOKEN',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.GET_TOKEN',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/Authenticate',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.SUBMIT_PAYMENT_DETAILS',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.SUBMIT_PAYMENT_DETAILS',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/SubmitPaymentDetails',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.BIKE.PRODUCT_CODE_GIC',
                'label' => 'IC.HDFC_ERGO.V1.BIKE.PRODUCT_CODE_GIC',
                'value' => '2311',
                'environment' => 'local'
            ],
        ];


        foreach ($configs as $config) {

            $checkConfig = ConfigSetting::where(['key' => $config['key']])->first();

            if ($checkConfig == null) {
                ConfigSetting::updateOrCreate($config);
            }
        }
    }
}
