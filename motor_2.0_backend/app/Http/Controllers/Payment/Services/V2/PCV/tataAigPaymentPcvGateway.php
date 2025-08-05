<?php

namespace App\Http\Controllers\Payment\Services;
namespace App\Http\Controllers\Payment\Services\V2\PCV;


use Exception;
use Config;
use stdClass;
use Carbon\Carbon;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\MasterPolicy;
use App\Models\MasterProduct;
use App\Models\QuoteLog;
use TataAigV2Helper;
use App\Exceptions\Handler;
use App\Http\Controllers\Finsall\FinsallController;
use App\Http\Controllers\Finsall\TataAigV2FinsallController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Proposal\Services\V2\PCV\tataAigSubmitProposals as TATA_AIG;
use App\Models\PaymentRequestResponse;
use App\Http\Controllers\CommonApi\TataAigApi;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/IcHelpers/TataAigV2Helper.php';

class tataAigPaymentPcvGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $enquiryId      = customDecrypt($request->enquiryId);
        $proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $proposal_additional_details_data = json_decode($proposal->additional_details_data);
        $quote_log_id   = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();
        $masterProduct = MasterProduct::where('master_policy_id', $request['policyId'])->first();
        $generatePaymentResponse = self::generatePaymentService(
            $enquiryId,
            $proposal,
            $masterProduct
        );

        if(!$generatePaymentResponse['status'])
        {
            return $generatePaymentResponse;
        }

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->where('user_proposal_id', $proposal->user_proposal_id)
        ->update([
            'active'        => 0,
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        $return_data = [
            'form_action' => $generatePaymentResponse['url'] ?? $generatePaymentResponse['paymentLink_web'],
            'form_method' => 'post',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'pgiRequest' => $generatePaymentResponse['pgiRequest'] ?? $generatePaymentResponse['paymentLink_web']
            ]
        ];

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'ic_id'                     => $masterProduct->ic_id,
            'proposal_no'               => $proposal->proposal_no,
            'order_id'                  => $proposal_additional_details_data->tata_aig_v2->payment_id,//$proposal->proposal_no,
            'amount'                    => $proposal->final_payable_amount,
            'payment_url'               =>  $generatePaymentResponse['url'] ?? $generatePaymentResponse['paymentLink_web'],
            'xml_data'                  => json_encode($return_data),
            'return_url'                => route(
                'cv.payment-confirm',
                [
                    'tata_aig_v2',
                    'user_proposal_id'      => $proposal->user_proposal_id,
                    'policy_id'             => $request['policyId']
                ]
            ),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1
        ]);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }
    public static function generatePaymentService($enquiryId, $proposal, $masterProduct)
    {
        $productData = getProductDataByIc($masterProduct->master_policy_id);
        if (in_array($productData->premium_type_code, ["short_term_3","short_term_6","short_term_3_breakin","short_term_6_breakin","comprehensive","third_party","third_party_breakin","breakin"])) 
        {
            $token_response = TataAigV2Helper::getToken($enquiryId, $productData);
            if (!$token_response['status']) 
            {
                return $token_response;
            }
        } 
        else 
        {
            $token_response = TATA_AIG::getToken($enquiryId, $productData);          
        }
        $proposal_additional_details_data = json_decode($proposal->additional_details_data);
        $payment_details =  PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                                ->orderBy('id','desc')
                                ->get();
        $tata_aig_api = new TataAigApi();
        if(!empty($payment_details))
        {
            foreach($payment_details as $payment)
            {
                $payment_details_data = [
                    'order_id' => $payment->order_id
                ];
                $product_data = [
                    'product_name' => $productData->product_name
                ];
                $payment_request_data = [
                    'payment_data' => $payment_details_data,
                    'enquiryId'    => $enquiryId,
                    'productData'  => $product_data,
                    'section'      => 'CAR',
                    'token'        => $token_response['token']
                ];
                $payment_response = $tata_aig_api->CheckPaymentStatus($payment_request_data);
                $payment_response = json_decode($payment_response['response'],true);
                $is_payment_success = $payment_response['status'] == 200 && $payment_response['data']['payment_status'] == 'Success';
                if($is_payment_success)
                {
                    return [
                        'status' => false,
                        'message' => 'Payment already initiated. Kindly wait some time for confirmation.'
                    ];
                }
            }
        }

        $first_name = preg_replace('/[^A-Za-z0-9\-]/','',$proposal->first_name);
        $last_name = preg_replace('/[^A-Za-z0-9\-]/','',$proposal->last_name);
        $paymentRequest = [
            'deposit_in'            => 'Bank',
            'email'                 => strtolower($proposal->email),
            'online_payment_mode'   => 'UPI',
            'payer_type'            => 'Customer',
            'payment_mode'          => 'onlinePayment',
            'payment_id' => [
                // $proposal_additional_details_data->tata_aig_v2->payment_id,
                $proposal_additional_details_data->tata_aig_v2->payment_id,
            ],

            'payer_id'              => '',
            'payer_pan_no'          => '',
            'payer_name'            => $first_name .' '. $last_name,
            'payer_relationship'    => '',
            'office_location_code'  => 90101,
            'pan_no'                => $proposal->pan_number,
            'mobile no'             => $proposal->mobile_number,

            'returnurl'             => route(
                'cv.payment-confirm',
                [
                    'tata_aig_v2'
                ]
            ),
        ];

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('IC.TATA_AIG.V2.PCV.XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Payment Request Creation',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        $paymentRequstUrl = config('IC.TATA_AIG.V2.PCV.END_POINT_URL_PAYMENT') .'?'.
        http_build_query([
            'product' => 'motor'
        ]);
        $get_response = getWsData(
            $paymentRequstUrl,
            $paymentRequest,
            'tata_aig_v2',
            $additional_data
        );

        $paymentResponse = $get_response['response'];
        $paymentResponse = TATA_AIG::validaterequest($paymentResponse);

        if (!$paymentResponse['status']) {
            return $paymentResponse;
        }
      
        $paymentResponse = json_decode($paymentResponse['data'], true);
        $paymentResponse['status'] = true;
        return $paymentResponse;       
    }


    public static function confirm($request)
    {

        $proposal = UserProposal::where('proposal_no', $request->proposal_no)->first();


           if(empty($proposal))
        {
            return redirect(config('IC.TATA_AIG.V2.PCV.PAYMENT_FAILURE_CALLBACK_URL'));
        }



         $proposal_additional_details_data = json_decode($proposal->additional_details_data);
      
        $productData = getProductDataByIc($proposal_additional_details_data->tata_aig_v2->master_policy_id);


        $masterProduct = MasterProduct::where('master_policy_id', $proposal_additional_details_data->tata_aig_v2->master_policy_id)->first();

        $enquiryId = $proposal->user_product_journey_id;

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->where('user_proposal_id', $proposal->user_proposal_id)
        ->where('active', 1)
        ->update([
            'response'      => json_encode($request->all()),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $data['user_product_journey_id']    = $proposal->user_product_journey_id;
        $data['ic_id']                      = $proposal->ic_id;
        $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
        updateJourneyStage($data);

        if (in_array($productData->premium_type_code, ["short_term_3","short_term_6","short_term_3_breakin","comprehensive","third_party","third_party_breakin","breakin"])) {
            $token_response = TataAigV2Helper::getToken($enquiryId, $productData);

            if (!$token_response['status']) {
                return $token_response;
            }
        } else {
            $token_response = TATA_AIG::getToken($enquiryId, $productData);
           
        }

        $verifyPaymentServiceResponse = self::verifyPaymentService($enquiryId, $proposal, $productData, $token_response);


        if(!$verifyPaymentServiceResponse['status'])
        {
            DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->where('active', 1)
            ->update([
                'status'        => STAGE_NAMES['PAYMENT_FAILED']
            ]);

            $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CV','FAILURE'));
        }

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->where('user_proposal_id', $proposal->user_proposal_id)
        ->where('active', 1)
        ->update([
            'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
        ]);

        $policyDownloadServiceResponse = self::policyDownloadService($enquiryId, $proposal, $productData, $verifyPaymentServiceResponse, $token_response);
        $enquiryId = $proposal->user_product_journey_id;
        return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CV','SUCCESS'));
    }

    public static function verifyPaymentService($enquiryId, $proposal, $productData, $token_response)
    {
        if(!$token_response['status'])
        {
            return $token_response;
        }
        $proposal_additional_details_data = json_decode($proposal->additional_details_data);
        $order_id_data = PaymentRequestResponse::where('user_product_journey_id', $enquiryId)->distinct()
        ->pluck('order_id')->toArray();
        array_push($order_id_data,$proposal_additional_details_data->tata_aig_v2->payment_id);
        $order_id_data = array_unique($order_id_data);
        foreach ($order_id_data as $payment_id) {
            $verifyPaymentRequest = [
                'payment_id' => $payment_id
            ];

            $additional_data = [
                'enquiryId'         => $enquiryId,
                'headers'           => [
                    'Content-Type'  => 'application/JSON',
                    'Authorization'  => 'Bearer '.$token_response['token'],
                    'x-api-key'  	=> config('IC.TATA_AIG.V2.PCV.XAPI_KEY')
                ],
                'requestMethod'     => 'post',
                'requestType'       => 'json',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Payment Verify',
                'transaction_type'  => 'proposal',
                'productName'       => $productData->product_name,
                'token'             => $token_response['token'],
            ];

            $verifyPaymentRequstUrl = config('IC.TATA_AIG.V2.PCV.END_POINT_URL_PAYMENT_VERIFY') .'?'.
            http_build_query([
                'product' => 'motor'
            ]);

            $get_response = getWsData(
                $verifyPaymentRequstUrl,
                $verifyPaymentRequest,
                'tata_aig_v2',
                $additional_data
            );

            $verifyPaymentResponse = $get_response['response'];
            $verifyPaymentResponse = TATA_AIG::validaterequest($verifyPaymentResponse);
            if ($verifyPaymentResponse['status']) {
                break;
            }
        }

        if(!$verifyPaymentResponse['status'])
        {
            $data['user_product_journey_id']    = $proposal->user_product_journey_id;
            $data['ic_id']                      = $proposal->ic_id;
            $data['stage']                      = STAGE_NAMES['PAYMENT_INITIATED'];
            updateJourneyStage($data);

            return $verifyPaymentResponse;
        }

        $proposal_additional_details_data->tata_aig_v2->policy_no = $verifyPaymentResponse['data']['policy_no'];
        $proposal_additional_details_data->tata_aig_v2->policy_id = $verifyPaymentResponse['data']['encrypted_policy_id'];
        $proposal->policy_no = $verifyPaymentResponse['data']['policy_no'];
        $proposal->additional_details_data = json_encode($proposal_additional_details_data);
        $proposal->save();

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        updateJourneyStage($data);

        PaymentRequestResponse::where([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'active' => 1
        ])->update([
            'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
        ]);

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'policy_number' => $verifyPaymentResponse['data']['policy_no']
            ]
        );

        return $verifyPaymentResponse;
    }

    static public function policyDownloadService($enquiryId, $proposal, $productData, $verifyPaymentServiceResponse, $token_response)
    {

        $proposal_additional_details_data = json_decode($proposal->additional_details_data, 1);


        $ic_pdf_url = config(
            'IC.TATA_AIG.V2.PCV.END_POINT_URL_POLICY_DOWNLOAD')
        . $verifyPaymentServiceResponse['data']['encrypted_policy_id'];

        $pdf_url = config('IC.TATA_AIG.V2.PCV.PROPOSAL_PDF_URL') . 'tata_aig_v2/'. md5($proposal->user_proposal_id).'.pdf';

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('IC.TATA_AIG.V2.PCV.XAPI_KEY')
            ],
            'requestMethod'     => 'get',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Policy Download',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
            'type'              => 'policyPdfGeneration'
        ];

          $verifyPaymentRequstUrl = config('IC.TATA_AIG.V2.PCV.END_POINT_URL_PAYMENT_VERIFY') .'?'.
        http_build_query([
            'product' => 'motor'
        ]);

        $get_response = getWsData(
            $ic_pdf_url,
            [],
            'tata_aig_v2',
            $additional_data
        );

        $policyDownloadResponse = $get_response['response'];

        $policyDownloadResponse = json_decode($policyDownloadResponse);

        try
        {
            Storage::put($pdf_url, base64_decode($policyDownloadResponse->byteStream));
        }
        catch(Exception $e)
        {
            return [
                'status' => false,
                'msg'    => $e->getMessage(),
            ];
        }

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED']
        ]);

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $proposal->user_proposal_id],
            [
                'policy_number' => $proposal_additional_details_data['tata_aig_v2']['policy_no'],
                'ic_pdf_url' => $ic_pdf_url,
                'pdf_url' => $pdf_url,
                'status' => 'SUCCESS'
            ]
   
        );

        return [
            'status' => true,
            'msg' => 'sucess',
            'data' => [
                'policy_number' => $proposal_additional_details_data['tata_aig_v2']['policy_no'],
                'pdf_link'      => file_url($pdf_url)
            ]
        ];
  
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        //NEW code payment verify service changes start
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        if(empty($proposal->additional_details_data))
        {            
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'additional_details_data is empty'
            ];
            return response()->json($pdf_response_data);
        }

        $proposal_additional_details_data = json_decode($proposal->additional_details_data);

        $productData = getProductDataByIc($proposal_additional_details_data->tata_aig_v2->master_policy_id);

        $enquiryId = $proposal->user_product_journey_id;

         if (in_array($productData->premium_type_code, ["short_term_3","short_term_6","short_term_3_breakin","short_term_6_breakin","comprehensive","third_party","third_party_breakin","breakin"])) {
            $token_response = TataAigV2Helper::getToken($enquiryId, $productData);

            if (!$token_response['status']) {
                return $token_response;
            }
        } else {
            $token_response = TATA_AIG::getToken($enquiryId, $productData);
           
        }

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin(
                'policy_details as pd',
                'pd.proposal_id','=','prr.user_proposal_id'
            )
            ->join(
                'user_proposal as up',
                'up.user_product_journey_id','=','prr.user_product_journey_id'
            )
            ->where([
                'prr.user_product_journey_id'   => $user_product_journey_id,
                'prr.active'                    => 1,
                // 'prr.status'                    => STAGE_NAMES['PAYMENT_SUCCESS']
            ])->whereIn('prr.status',[ STAGE_NAMES['PAYMENT_SUCCESS'],STAGE_NAMES['PAYMENT_INITIATED']])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id', 'up.proposal_no','up.unique_proposal_id', 'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id', 'prr.lead_source'
            )
            ->first();


        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found',
                'data'   => []
            ];

            return response()->json($pdf_response_data);
        }

        $masterProduct = MasterProduct::where('master_policy_id', $proposal_additional_details_data->tata_aig_v2->master_policy_id)->first();


        if($policy_details->pdf_url == '')
        {
            $generatePolicy['status'] = false;
            if ($policy_details->lead_source == 'finsall') {
                return self::finsallRehitService($user_product_journey_id, $policy_details->proposal_no);
            } else {
                $generatePolicy = self::generatePolicy($user_product_journey_id, $proposal, $productData, $token_response);
            }


            if($generatePolicy['status'])
            {
                $pdf_response_data = self::policyDownloadService(
                    $user_product_journey_id,
                    $proposal,
                    $productData,
                    $generatePolicy,
                    $token_response
                );
            }
            else{
                $pdf_response_data = $generatePolicy;
            }
        }
        else
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'ic_pdf_url' => $policy_details->ic_pdf_url,
                    'pdf_url' => $policy_details->pdf_url,
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link' => file_url($policy_details->pdf_url)
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

    static public function generatePolicy($enquiryId, $proposal, $productData, $token_response)
    {

        $is_package     = (($proposal->product_type == 'comprehensive') ? true : false);
        $is_liability   = (($proposal->product_type == 'third_party') ? true : false);
        $is_od          = (($proposal->product_type == 'own_damage') ? true : false);

        $additional_details_data     = json_decode($proposal->additional_details_data);
        $tata_aig_v2_data      = $additional_details_data->tata_aig_v2;


        $payment_service = self::verifyPaymentService($enquiryId, $proposal, $productData, $token_response);

        if ($payment_service['status'])
        {
            return $payment_service;
        }
        else
        {
            return [
                'status' => false,
                'msg'    => $payment_service['msg'],
                'data'   => []
            ];
        }
    }

    public static function finsallRehitService($user_product_journey_id, $proposal_no)
    {
        $response = [
            'status' => false,
            'msg' => STAGE_NAMES['PAYMENT_FAILED']
        ];
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if (!empty($proposal) && !empty($proposal_no)) {
            $paymentStatusService = FinsallController::paymentStatus($proposal, $proposal_no);

            if ($paymentStatusService['status']) {
                PaymentRequestResponse::where('order_id', $proposal_no)
                    ->where('active', 1)
                    ->update([
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);

                $request = (object)[];
                $request->txnRefNo = $paymentStatusService['txnRefNo'];
                $request->txnDateTime = $paymentStatusService['txnDateTime'];
                $finsall = new TataAigV2FinsallController();
                $response = $finsall->paymentCheck($request, $proposal);
            }
        }
        return $response;
    }

}