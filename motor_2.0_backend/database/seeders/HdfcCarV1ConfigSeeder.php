<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class HdfcCarV1ConfigSeeder extends Seeder
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
                'key' => 'IC.HDFC_ERGO.V1.CAR.ENABLE',
                'label' => 'IC.HDFC_ERGO.V1.CAR.ENABLE',
                'value' => 'N',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.SOURCE_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.SOURCE_GIC',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/Authenticate',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.IDV_LINK_URL_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.IDV_LINK_URL_GIC',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/GetCalculateIDV',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.IDV_DEVIATION_ENABLE',
                'label' => 'IC.HDFC_ERGO.V1.CAR.IDV_DEVIATION_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.NO_LOSS_OF_BELONGINGS',
                'label' => 'IC.HDFC_ERGO.V1.CAR.NO_LOSS_OF_BELONGINGS',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.GIC_PREMIUM',
                'label' => 'IC.HDFC_ERGO.V1.CAR.GIC_PREMIUM',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/CalculatePremium',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.GIC_CREATE_PROPOSAL',
                'label' => 'IC.HDFC_ERGO.V1.CAR.GIC_CREATE_PROPOSAL',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/CreateProposal',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.TRANSACTION_NO_SERIES_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.TRANSACTION_NO_SERIES_GIC',
                'value' => '1022',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.APPID_PAYMENT_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.APPID_PAYMENT_GIC',
                'value' => '10178',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.SubscriptionID_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.SubscriptionID_GIC',
                'value' => 'S000000226',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.PAYMENT_CHECKSUM_LINK_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.PAYMENT_CHECKSUM_LINK_GIC',
                'value' => 'https://hepgw.hdfcergo.com/PaymentUtilitiesService/PaymentUtilities.asmx/GenerateRequestChecksum',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.PAYMENT_GATEWAY_LINK_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.PAYMENT_GATEWAY_LINK_GIC',
                'value' => 'https://heapp21.hdfcergo.com/UAT/OnlineProducts/CCPGISUBSCRIBER/MakePayment.aspx',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC',
                'label' => 'IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_GIC',
                'value' => '2314',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.GIC_SUBMIT_PAYMENT_DETAILS',
                'label' => 'IC.HDFC_ERGO.V1.CAR.GIC_SUBMIT_PAYMENT_DETAILS',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/SubmitPaymentDetails',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.HDFC_ERGO.V1.CAR.GIC_POLICY_DOCUMENT_DOWNLOAD',
                'label' => 'IC.HDFC_ERGO.V1.CAR.GIC_POLICY_DOCUMENT_DOWNLOAD',
                'value' => 'https://accessuat.hdfcergo.com/cp/Integration/HEIIntegrationService/Integration/GetPolicyDocument',
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