<?php

namespace App\Http\Controllers\Payment\Services;

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Http\Controllers\PaymentGateway\BillDesk;
use App\Models\WebServiceRequestResponse;
use Illuminate\Support\Carbon;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class UnitedIndiaPaymentGatewayBillDesk
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {

        $enquiryId      = customDecrypt($request->enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $icId           = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        $quote_log_id   = QuoteLog::where('user_product_journey_id', $enquiryId)
            ->pluck('quote_id')
            ->first();

        $user_proposal  = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $url = config('IC.CV.BILL_DESK_API.V2.CREATE_ORDER_URL');

        $order_id = config('constants.motorConstant.SMS_FOLDER') . time() . rand(111, 9999);

        $sharedSecretKey = config('IC.CV.BILL_DESK_API.V2.SHARED_SECRET_KEY');

        $merchant_id = config('IC.CV.BILL_DESK_API.V2.MERCHANT_ID');

        $return_url = route('cv.payment-confirm', ['united_india']);

        $get_order_id = DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->select('order_id')
            ->get();

        if (!empty($get_order_id)) {

            foreach ($get_order_id as $value) {

                $get_order_id = $value->order_id;

                $status_url = config('IC.UNITED_INIDA.CV.PAYMENT.CHECK_BILL_DESK_API.V2_STATUS_URL');

                $resp_data = BillDesk::checkPaymentStaus($get_order_id, $merchant_id, $sharedSecretKey, $status_url, $enquiryId, $productData);
                $responseContent = json_decode($resp_data->getContent(), true);

                if ($responseContent['status'] === false && isset($responseContent['msg'])) {

                    return response()->json([
                        'status' => false,
                        'message' => $responseContent['msg']
                    ]);
                }
            }
        }

        $generate_order_id = BillDesk::CreateOrder($order_id, $request, $user_proposal, $url, $sharedSecretKey, $merchant_id , $return_url);
        $decoded_data = json_decode($generate_order_id->getContent(), true);


        if (isset($decoded_data['data']) && !empty($decoded_data['data'])) {

            $create_order_response = $decoded_data['data'];

            list($headerBase64Url, $payloadBase64Url, $receivedSignatureBase64Url) = explode('.', $decoded_data['data']);

            $dataToSign = $headerBase64Url . '.' . $payloadBase64Url;
            $computedSignature = hash_hmac('sha256', $dataToSign, $sharedSecretKey, true);
            $computedSignatureBase64Url = trim(strtr(base64_encode($computedSignature), '+/', '-_'), '=');

            if ($computedSignatureBase64Url === $receivedSignatureBase64Url) {

                try {

                    $decoded = BillDesk::jwtDecode($create_order_response, $sharedSecretKey);
                    $decoded = json_decode(json_encode($decoded), true);

                    $startTime = new DateTime(date('Y-m-d H:i:s'));
                    $endTime = new DateTime(date('Y-m-d H:i:s'));

                    $store_data_encrypt = [
                        'enquiry_id'        => $enquiryId,
                        'product'           => $productData->product_name,
                        'section'           => 'CV',
                        'method_name'       => 'OrderId Creation - Step-3 (Decrypted Response)',
                        'company'           => 'united_india',
                        'method'            => 'post',
                        'transaction_type'  => 'proposal',
                        'request'           => 'JSON LOADED',
                        'response'          => is_array($decoded) ? json_encode($decoded) : $decoded,
                        'endpoint_url'      => $url,
                        'ip_address'        => request()->ip(),
                        'start_time'        => $startTime->format('Y-m-d H:i:s'),
                        'end_time'          => $endTime->format('Y-m-d H:i:s'),
                        'response_time'        => $endTime->getTimestamp() - $startTime->getTimestamp(),
                        'created_at'        => Carbon::now(),
                        'headers'           => NULL
                    ];

                    WebServiceRequestResponse::create($store_data_encrypt);

                    $from_url = trim($decoded['links'][1]['href']);
                    $parameters = $decoded['links'][1]['parameters'];



                    $data['user_product_journey_id']    = $user_proposal->user_product_journey_id;
                    $data['ic_id']                      = $user_proposal->ic_id;
                    $data['stage']                      = STAGE_NAMES['PAYMENT_INITIATED'];
                    updateJourneyStage($data);





                    DB::table('payment_request_response')->insert([
                        'quote_id'                  => $quote_log_id,
                        'user_product_journey_id'   => $enquiryId,
                        'user_proposal_id'          => $user_proposal->user_proposal_id,
                        'ic_id'                     => $icId,
                        'order_id'                  => $decoded['orderid'],
                        'amount'                    => $user_proposal->final_payable_amount,
                        'payment_url'               => config('constants.IcConstants.united_india.CV.END_POINT_URL_PAYMENT_GATEWAY'),
                        'return_url'                => route(
                            'cv.payment-confirm',
                            [
                                'united_india',
                                'user_proposal_id'      => $user_proposal->user_proposal_id,
                                'policy_id'             => $request->policyId
                            ]
                        ),
                        'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                        'active'                    => 1
                    ]);

                    UserProposal::updateOrInsert(
                        ['user_product_journey_id' => $enquiryId],
                        ['unique_proposal_id' => $decoded['orderid']]
                    );


                    $return_data = [
                        'form_action'       => $from_url,
                        'form_method'       => 'POST',
                        'payment_type'      => 0,
                        'form_data'         => [
                            'bdorderid'     => trim($parameters['bdorderid']),
                            'merchantid'    => trim($parameters['mercid']),
                            'rdata'         => trim($parameters['rdata'])
                        ]
                    ];


                    return response()->json([
                        'status' => true,
                        'data' => $return_data
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Error decoding payment response: ' . $e->getMessage(),
                        'data' => null,
                    ]);
                }
            }
        } else {

            return response()->json([
                'status' => false,
                'message' => 'Invalid signature received in response.',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => "Error in generating order ID",
            'data' => null,
        ]);
    }

    public static function confirm($request)
    {
        if (isset($request->transaction_response) && !empty($request->transaction_response)) {
            $sharedSecretKey = config('IC.CV.BILL_DESK_API.V2.SHARED_SECRET_KEY');

            $transaction_response = $request->transaction_response;
            $response = BillDesk::jwtDecode($transaction_response, $sharedSecretKey);
            $decoded = json_decode(json_encode($response), true);

            $order_id = $decoded['orderid'];
            $proposal = UserProposal::where('unique_proposal_id', $order_id)->first();
            $enquiry_id = $proposal->user_product_journey_id;

            if ($decoded['auth_status'] == "0300" || $decoded['auth_status'] == "0002") {

                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->where('order_id', $order_id)
                    ->update([
                        'response'      => json_encode($decoded),
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'status'        => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];

                updateJourneyStage($data);

                $additional_details     = json_decode($proposal->additional_details);
                $additional_details_data = json_decode($proposal->additional_details_data);

                if (isset($additional_details->united_india)) {
                    $united_india_data = $additional_details->united_india;
                } else {
                    $united_india_data = $additional_details_data->united_india;
                }


                $proposal_data = [
                    'premium_amount'            => $proposal->final_payable_amount,
                    'num_reference_number'      => $united_india_data->reference_number,
                    'transaction_id'            => $united_india_data->transaction_id,
                    'pg_transaction_id'         => $order_id,
                    'utr_number'         =>        $decoded['transactionid'],
                ];

                $payment_service = unitedIndiaPaymentGateway::payment_info_service($proposal_data, $proposal);

                if (!$payment_service['status']) {
                    return redirect(paymentSuccessFailureCallbackUrl($enquiry_id, 'CV', 'SUCCESS'));
                }

                unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $payment_service['policy_no'],
                        'pdf_schedule' => $payment_service['pdf_schedule']
                    ]
                );

                return redirect(paymentSuccessFailureCallbackUrl($enquiry_id, 'CV', 'SUCCESS'));
            } else {
                DB::table('payment_request_response')
                    ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'response'      => json_encode($decoded), //$_REQUEST['msg'],
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'status'        => STAGE_NAMES['PAYMENT_FAILED']
                    ]);
                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                return redirect(paymentSuccessFailureCallbackUrl($enquiry_id, 'CV', 'FAILURE'));
            }
            return redirect(paymentSuccessFailureCallbackUrl($enquiry_id, 'CV', 'FAILURE'));
        }
        return redirect(Config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
    }


   

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
       
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin(
                'policy_details as pd',
                'pd.proposal_id',
                '=',
                'prr.user_proposal_id'
            )
            ->join(
                'user_proposal as up',
                'up.user_product_journey_id',
                '=',
                'prr.user_product_journey_id'
            )
            ->where([
                'prr.user_product_journey_id'   => $user_product_journey_id,
                'prr.active'                    => 1,
                'prr.status'                    => STAGE_NAMES['PAYMENT_SUCCESS']
            ])
            ->select(
                'up.user_proposal_id',
                'up.user_proposal_id',
                'up.proposal_no',
                'up.unique_proposal_id',
                'pd.policy_number',
                'pd.pdf_url',
                'pd.ic_pdf_url',
                'prr.order_id'
            )
            ->first();

        if ($policy_details == null) {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found',
                'data'   => []
            ];

            return response()->json($pdf_response_data);
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();


        if ($policy_details->pdf_url == '') {
            $generatePolicy['status'] = false;

            if (is_null($policy_details->policy_number) || $policy_details->policy_number == '') {
                $generatePolicy = unitedIndiaPaymentGateway::generatePolicy($proposal, $request->all());
            }


            if ($generatePolicy['status']) {
                $pdf_response_data = unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $generatePolicy['data']['policy_no'],
                        'pdf_schedule' => $generatePolicy['data']['pdf_schedule']
                    ]
                );
            } else if ($policy_details->policy_number != '') {
                $pdf_response_data = unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $policy_details->policy_number,
                        'pdf_schedule' => $policy_details->ic_pdf_url
                    ]
                );
            } else {
                $pdf_response_data = $generatePolicy;
            }
        } else {
            $pdf_response_data = [
                'status' => false,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'ic_pdf_url' => $policy_details->ic_pdf_url,
                    'pdf_url' => $policy_details->pdf_url,
                    'pdf_link'      => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'united_india/' . md5($proposal->user_proposal_id) . '.pdf'),
                    'policy_number' => $policy_details->policy_number
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }


   
   
  
}
