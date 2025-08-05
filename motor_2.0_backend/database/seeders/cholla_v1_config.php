<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class cholla_v1_config extends Seeder
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
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.ENABLED',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.ENABLED',
                'value' => '',
                'environment' => 'local'
            ],
            // for quote
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_TP',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_TP',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_OD',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_OD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.IMDSHORTCODE_DEV',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.IMDSHORTCODE_DEV',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.USER_CODE',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.USER_CODE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.IS_TP_IMDSHORTCODE_DEV',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.IS_TP_IMDSHORTCODE_DEV',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.TP_IMDSHORTCODE_DEV',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.TP_IMDSHORTCODE_DEV',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE',
                'value' => 'https://developer.cholainsurance.com/endpoint/integration-services-comprehensive/v1.0.0/QuoteWrapper',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_TP',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_TP',
                'value' => 'https://developer.cholainsurance.com/endpoint/integration-services-liability/v1.0.0/QuoteWrapper',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_OD',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_OD',
                'value' => 'https://developer.cholainsurance.com/endpoint/integration-services-saod/v1.0.0/QuoteWrapper',
                'environment' => 'local'
            ],
            //proposal
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_PROPOSAL',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_PROPOSAL',
                'value' => 'https://developer.cholainsurance.com/endpoint/integration-services-comprehensive/v1.0.0/ProposalWrapper',
                'environment' => 'local'
            ],
            //pg
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM',
                'value' => 'https://uat.billdesk.com/pgidsk/PGIMerchantPayment',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.BROKER_NAME',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.BROKER_NAME',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_MERCHANT_ID_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_MERCHANT_ID_CHOLLA_MANDALAM',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_SECURITY_ID_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_SECURITY_ID_CHOLLA_MANDALAM',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_CHECKSUM_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.PAYMENT_CHECKSUM_CHOLLA_MANDALAM',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.QUERY_API_URL_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.QUERY_API_URL_CHOLLA_MANDALAM',
                'value' => 'https://uat.billdesk.com/pgidsk/PGIQueryController',
                'environment' => 'local'
            ],
            [
                'label' => 'IC.CHOLLA_MANDALAM.V1.CAR.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM',
                'key' => 'IC.CHOLLA_MANDALAM.V1.CAR.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM',
                'value' => '1.00',
                'environment' => 'local'
            ]
            
        ];
        foreach ($configs as  $value) {
            $checkConfig = ConfigSetting::where(['key' => $value['key']])->first();
            if($checkConfig == null){
                ConfigSetting::updateOrCreate($value);
            }
        }
    }
}
