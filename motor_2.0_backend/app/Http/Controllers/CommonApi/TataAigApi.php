<?php

namespace App\Http\Controllers\CommonApi;
use Illuminate\Http\Request;

class TataAigApi
{
    function CheckPaymentStatus($request)
    {        
        $verifyPaymentRequest = [
            'payment_id' => $request['payment_data']['order_id']
        ];

        $additional_data = [
            'enquiryId'         => $request['enquiryId'],
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$request['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $request['section'],
            'method'            => 'Payment status before payment',
            'transaction_type'  => 'proposal',
            'productName'       => $request['productData']['product_name'],
            'token'             => $request['token'],
        ];
        $verifyPaymentRequstUrl = config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_PAYMENT_VERIFY') .'?'.
            http_build_query([
                'product' => 'motor'
        ]);

        return $get_response = getWsData(
            $verifyPaymentRequstUrl,
            $verifyPaymentRequest,
            'tata_aig_v2',
            $additional_data
        );
    }
}