<?php

namespace App\Http\Controllers\Paytm;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequestResponse;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use paytm\paytmchecksum\PaytmChecksum;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class PaymentGatewayController extends Controller
{
    public $merchantKey;
    public $enquiryId;
    public $companyAlias;
    public $mid;
    public $website;
    public $section;

    public function __construct($companyAlias, $section, $enquiryId = null)
    {
        $this->enquiryId = $enquiryId;
        $this->companyAlias = $companyAlias;
        $this->section = $section;

        $creds = self::getCredentials($companyAlias);
        $this->merchantKey = $creds['merchantKey'];
        $this->mid = $creds['mid'];
        $this->website = $creds['website'];
    }

    public static function getCredentials($companyAlias)
    {
        $configType = getCommonConfig('paymentGateway.paytm.configType', null);

        if (!empty($configType)) {
            if ($configType == 'global') {

                //global config
                $merchantKeyName = 'paymentGateway.paytm.merchantKey';
                $midKeyName = 'paymentGateway.paytm.mid';
                $websiteKeyName = 'paymentGateway.paytm.websiteName';
            } else {

                //ic wise config
                $merchantKeyName = 'paymentGateway.paytm.' . $companyAlias . 'merchantKey';
                $midKeyName = 'paymentGateway.paytm.' . $companyAlias . 'mid';
                $websiteKeyName = 'paymentGateway.paytm.' . $companyAlias . 'websiteName';
            }

            $merchantKey = getCommonConfig($merchantKeyName, null);
            $mid = getCommonConfig($midKeyName, null);
            $website = getCommonConfig($websiteKeyName, null);
        } else {
            $merchantKey = config('PAYMENT_GATEWAY.PAYTM.MERCHANT_KEY');
            $mid = config('PAYMENT_GATEWAY.PAYTM.MID');
            $website = config('PAYMENT_GATEWAY.PAYTM.WEBSITE_NAME');
        }

        return [
            'merchantKey' => $merchantKey,
            'mid' => $mid,
            'website' => $website,
        ];
    }

    public function initiateTransaction(Request $request)
    {
        $proposal = UserProposal::where('user_product_journey_id', $this->enquiryId)
        ->first();

        $quoteLog = QuoteLog::where('user_product_journey_id', $this->enquiryId)
        ->first();

        $oderId = config('PAYMENT_GATEWAY.PAYTM.ORDER_ID_PREFIX').'_' . random_int(111, 99999) . time();
        $callBackUrl = route(
            $this->section.'.payment-confirm',
            [
                $this->companyAlias,
                'user_proposal_id'      => $proposal->user_proposal_id,
                'policy_id'             => $request->policyId
            ]
        );

        $paytmParams = [
            'body' => [
                "requestType"   => "Payment",
                "mid"           => $this->mid,
                "websiteName"   => $this->website,
                "orderId"       => $oderId,
                "callbackUrl"   => $callBackUrl,
                'txnAmount' => [
                    "value"     => $proposal->final_payable_amount,
                    "currency"  => "INR",
                ],
                "userInfo"      => [
                    "custId"    => "CUST_" . $proposal->user_proposal_id,
                    "firstName" => $proposal->first_name,
                    "lastName" => $proposal->last_name,
                ],
            ]
        ];

        $checksum = PaytmChecksum::generateSignature(
            json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES),
            $this->merchantKey
        );

        $paytmParams['head'] = [
            'signature' => $checksum
        ];

        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        $url = config('PAYMENT_GATEWAY.PAYTM.INITIATE_TRANSACTION_URL');
        $url .= "?mid=".$this->mid."&orderId=".$paytmParams['body']['orderId'];

        $additionalData = [
            'enquiryId' => $this->enquiryId,
            'headers' => [
                'Content-Type: application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'company_alias' => $this->companyAlias,
            'section' => $this->section,
            'productName' => 'Paytm Payment Gateway',
            'method' => 'Initiate Transaction',
            'transaction_type' => 'proposal'
        ];

        $getResponse = getWsData(
            $url,
            $post_data,
            'paytm',
            $additionalData
        );

        $transactionResponse = $getResponse['response'];
        if (empty($transactionResponse)) {
            return response()->json([
                'status' => false,
                'msg'    => "Error in Token Generation",
                'data'   => $transactionResponse
            ]);
        }

        $transactionResponse = json_decode($transactionResponse, true);
        if (
            !empty($transactionResponse['body']['resultInfo']['resultStatus']) &&
            $transactionResponse['body']['resultInfo']['resultStatus'] == 'S'
        ) {
            $paytmParams['txnToken'] = $transactionResponse['body']['txnToken'];

            $paytmParams['form_data'] = [
                'form_action' => config('PAYMENT_GATEWAY.PAYTM.SHOW_PAYMENT_PAGE_URL').'?mid=' . $this->mid . '&orderId=' . $paytmParams['body']['orderId'],
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' =>
                [
                    'mid'       => $paytmParams['body']['mid'],
                    'orderId'   => $paytmParams['body']['orderId'],
                    'txnToken'  => $transactionResponse['body']['txnToken']
                ]
            ];

            PaymentRequestResponse::where([
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'user_proposal_id' => $proposal->user_proposal_id,
            ])
            ->update([
                'active' => 0
            ]);

            PaymentRequestResponse::create([
                'quote_id' => $quoteLog->quote_id,
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'user_proposal_id' => $proposal->user_proposal_id,
                'ic_id' => $proposal->ic_id,
                'order_id' => $paytmParams['body']['orderId'],
                'amount' => $proposal->final_payable_amount,
                'payment_url' => $paytmParams['form_data']['form_action'],
                'return_url' => $paytmParams["body"]["callbackUrl"],
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'xml_data' => json_encode($paytmParams)
            ]);

            $data = [
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'ic_id' => $proposal->ic_id,
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ];
            
            updateJourneyStage($data);

            return response()->json([
                'status'    => true,
                'msg'       => "Payment Redirection",
                'data'      => $paytmParams['form_data'],
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg'    => "Error in Token Generation",
                'data'   => $transactionResponse
            ]);
        }
    }

    public function paymentStatusCheck()
    {
        $proposal = UserProposal::where('user_product_journey_id', $this->enquiryId)
            ->first();

        $paymentLog = PaymentRequestResponse::where([
            'user_product_journey_id' => $this->enquiryId,
            'ic_id' => $proposal->ic_id
        ])
        ->select('xml_data', 'id', 'user_proposal_id')
        ->get();

        if (empty($paymentLog)) {
            return [
                'status' => false
            ];
        }
        foreach ($paymentLog as $value) {
            $transactionStatus = self::transactionStatus($proposal, $value);
            if ($transactionStatus['status'] ?? false) {
                return $transactionStatus;
            }
        }
        return [
            'status' => false
        ];
    }
    public function transactionStatus($proposal, $paymentData)
    {
        $xmlData = json_decode($paymentData->xml_data, true);
        $xmlData = $xmlData['form_data']['form_data'] ?? [];

        $paytmParams["body"] =  [
            "mid" => $xmlData['mid'],
            "orderId" => $xmlData['orderId'],
        ];
        $checksum = PaytmChecksum::generateSignature(
            json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES),
            $this->merchantKey
        );

        $paytmParams["head"] = [
            "signature"	=> $checksum
        ];

        $url = config('PAYMENT_GATEWAY.PAYTM.ORDER_STATUS_URL');

        $additionalData = [
            'enquiryId' => $this->enquiryId,
            'headers' => [
                'Content-Type: application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'company_alias' => $this->companyAlias,
            'section' => $this->section,
            'productName' => 'Paytm Payment Gateway',
            'method' => 'Transaction Status',
            'transaction_type' => 'proposal'
        ];

        $getResponse = getWsData($url , $paytmParams, 'paytm', $additionalData);
        $transactionResponse = $getResponse['response'];
        
        if (empty($transactionResponse)) {
            return response()->json([
                'status' => false,
                'msg'    => "Service Error",
                'data'   => $transactionResponse
            ]);
        }

        $transactionStatusResponse = json_decode($transactionResponse, true);
        if (
            !empty($transactionStatusResponse['body']['resultInfo']['resultStatus']) &&
            $transactionStatusResponse['body']['resultInfo']['resultStatus'] == 'TXN_SUCCESS'
        ) {
            PaymentRequestResponse::where('user_product_journey_id', $this->enquiryId)
            ->update([
                'active'  => 0
            ]);

            PaymentRequestResponse::where('id', $paymentData->id)
            ->update([
                'response' => $transactionStatusResponse,
                'updated_at' => date('Y-m-d H:i:s'),
                'status'  => STAGE_NAMES['PAYMENT_SUCCESS'],
                'active'  => 1
            ]);

            $data = [
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'ic_id' => $proposal->ic_id,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ];
            
            updateJourneyStage($data);

            return [
                'status' => true,
                'msg' => 'success'
            ];
        }

        return [
            'status' => false,
            'msg' => 'Failure'
        ];
    }

    public function paymentConfirm($request)
    {
        $requestData = $request->all();

        if (empty($requestData['user_proposal_id'])) {
            return [
                'status' => false
            ];
        }

        $proposal = UserProposal::where('user_proposal_id', $requestData['user_proposal_id'])->first();

        $this->enquiryId = $proposal->user_product_journey_id;

        $paytmParams["body"] =  [
            "mid" => $requestData['MID'],
            "orderId" => $requestData['ORDERID'],
        ];

        $checksum = PaytmChecksum::generateSignature(
            json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES),
            $this->merchantKey
        );

        $paytmParams["head"] = [
            "signature"	=> $checksum
        ];
        
        $url = config('PAYMENT_GATEWAY.PAYTM.ORDER_STATUS_URL');

        $additionalData = [
            'enquiryId' => $this->enquiryId,
            'headers' => [
                'Content-Type: application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'company_alias' => $this->companyAlias,
            'section' => $this->section,
            'productName' => 'Paytm Payment Gateway',
            'method' => 'Transaction Status',
            'transaction_type' => 'proposal'
        ];

        $getResponse = getWsData($url , $paytmParams, 'paytm', $additionalData);
        $transactionStatusResponse = json_decode($getResponse['response'], true);

        if (
            !empty($transactionStatusResponse) &&
            !empty($transactionStatusResponse['body']['resultInfo']['resultStatus']) &&
            $transactionStatusResponse['body']['resultInfo']['resultStatus'] == 'TXN_SUCCESS'
        ) {

            PaymentRequestResponse::where([
                'user_product_journey_id' => $this->enquiryId,
                'user_proposal_id' => $proposal->user_proposal_id,
                'active' => 1
            ])
            ->update([
                'response' => json_encode($request->all()),
                'updated_at' => date('Y-m-d H:i:s'),
                'status'  => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);

            $data = [
                'user_product_journey_id' => $proposal->user_product_journey_id,
                'ic_id' => $proposal->ic_id,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ];

            updateJourneyStage($data);

            return [
                'status' => true,
                'data' => [
                    'orderId' => $transactionStatusResponse['body']['orderId'],
                    'proposal' => $proposal
                ]
            ];
        }

        return [
            'status' => false
        ];
    }
}
