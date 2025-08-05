<?php

namespace App\Http\Controllers\RenewalService\Car\V1;

use App\Models\SelectedAddons;
use app\Helpers\CvWebServiceHelper;
use app\Helpers\CarWebServiceHelper;
require_once app_path().'/Helpers/CarWebServiceHelper.php';
class hdfc_ergo
{
    protected $section;
    public function __construct()
    {
        $this->section = 'CAR';
    }

    public function IcServicefetchData($renwalData)
    {
        $requestData = getQuotation($renwalData['user_product_journey_id']);
        $enquiryId   = $renwalData['user_product_journey_id'];
        $productData = getProductDataByIc($renwalData['previous_policy_number']);
        $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);

        $SOURCE        = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.SOURCE');
        $CHANNEL_ID    = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.CHANNELID');
        $CREDENTIAL    = config('IC.HDFC_ERGO.V1.CAR.RENEWAL.CREDENTIAL');
        $ProductCode = '2311';

        $data_token = [
            'enquiryId' => $enquiryId,
            'productData' => $productData,
            'requestData' => $requestData,
            'transactionid' => $transactionid,
            'SOURCE'        => $SOURCE,
            'CHANNEL_ID'    => $CHANNEL_ID,
            'CREDENTIAL'    => $CREDENTIAL,
            'ProductCode'   => $ProductCode,
        ];
        $PRODUCT_CODE  = $ProductCode;
        $TRANSACTIONID = $transactionid;
        $additionData = [
            'type'              => 'gettoken',
            'method'            => 'tokenGeneration',
            'section'           => 'car',
            'productName'       => 'renewal fetch',
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'CREDENTIAL'        => $CREDENTIAL,
        ];
        $token_gen = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.TOKEN_GENERATION_URL'), '', 'hdfc_ergo', $additionData);
        $token_data = json_decode($token_gen['response'], TRUE);
        $auth_token = !empty($token_data['Authentication']['Token']) ? $token_data['Authentication']['Token'] : false;
        //fetch    
        $additionData = [
            'type'              => 'getrenewal',
            'method'            => 'Renewal Fetch Policy',
            'section'           => 'car',
            'requestMethod'     => 'post',
            'productName'       => 'renewal fetch service',
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'proposal',
            'PRODUCT_CODE'      => $PRODUCT_CODE,
            'SOURCE'            => $SOURCE,
            'CHANNEL_ID'        => $CHANNEL_ID,
            'CREDENTIAL'        => $CREDENTIAL,
            'TRANSACTIONID'     => $TRANSACTIONID,
            'TOKEN'             => $auth_token,
        ];
        $renewal_fetch_data_array = [
            'TransactionID'     => $TRANSACTIONID,
            'Req_Renewal' => [
                "Policy_no" => $renwalData['previous_policy_number'],
            ]
        ];
        $renewal_fetch_data = getWsData(config('IC.HDFC_ERGO.V1.CAR.RENEWAL.FETCH_GENERATION_URL'), $renewal_fetch_data_array, 'hdfc_ergo', $additionData);
        if (!$renewal_fetch_data['response']) {
            return [
                'status' => false,
                'premium' => 0,
                'message' => 'Renewal Service Issue',
            ];
        }
        // dd(json_decode($renewal_fetch_data['response']));
        return json_decode($renewal_fetch_data['response']);
    }
}
