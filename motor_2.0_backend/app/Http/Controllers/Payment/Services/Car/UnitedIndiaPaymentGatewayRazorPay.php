<?php

namespace App\Http\Controllers\Payment\Services\Car;

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Http\Controllers\PaymentGateway\RazorPay;
use App\Models\ThirdPartyPaymentReqResponse;
use App\Models\WebServiceRequestResponse;
use Illuminate\Support\Carbon;
use Exception;


include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class UnitedIndiaPaymentGatewayRazorPay
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

        $get_order_id = DB::table('payment_request_response')
            ->where('user_product_journey_id', $enquiryId)
            ->select('order_id')
            ->get();

        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        if (!empty($get_order_id)) {

            foreach ($get_order_id as $value) {

                $get_order_id = $value->order_id;

                $request_params = [
                    'order_id' => $get_order_id,
                    'enquiryId' => $enquiryId,
                    'Razorpay_key_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_KEY_ID'),
                    'Razorpay_secret_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_SECREAT_KEY'),
                ];

                $resp_data = RazorPay::fetchPaymentStaus($request_params);
                $responseContent = json_decode($resp_data->getContent(), true);

                ThirdPartyPaymentReqResponse::insert(                                             #storing request
                    [
                        'enquiry_id' => $enquiryId,
                        'request' => json_encode($request_params),
                        'response' => json_encode($responseContent)
                    ]
                );

                $store_data_encrypt = [
                    'enquiry_id'        => $enquiryId,
                    'product'           => $productData->product_name,
                    'section'           => 'CAR',
                    'method_name'       => 'Fetch Payment Status',
                    'company'           => 'united_india',
                    'method'            => 'post',
                    'transaction_type'  => 'proposal',
                    'request'           => json_encode($request_params),
                    'response'          => json_encode($responseContent),
                    'endpoint_url'      => 'https://api.razorpay.com/v1/orders/' . $get_order_id . '/payments',
                    'ip_address'        => request()->ip(),
                    'start_time'        => $startTime->format('Y-m-d H:i:s'),
                    'end_time'          => $endTime->format('Y-m-d H:i:s'),
                    'response_time'        => $endTime->getTimestamp() - $startTime->getTimestamp(),
                    'created_at'        => Carbon::now(),
                    'headers'           => NULL
                ];

                WebServiceRequestResponse::create($store_data_encrypt);

                if ($responseContent['status'] && !empty($responseContent['data'])) {

                    $response = $responseContent['data'];

                    if (isset($response['items']) && isset($response['items']['status']) && $response['items']['status'] == 'captured') {

                        return response()->json([
                            'status' => false,
                            'message' => 'Payment is already captured',
                        ]);
                    }
                }
            }
        }

        $request_params = [
            'final_payable_amount' => $user_proposal->final_payable_amount,
            'enquiryId' => $enquiryId,
            'Razorpay_key_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_KEY_ID'),
            'Razorpay_secret_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_SECREAT_KEY'),
        ];

        $generate_order_id = RazorPay::CreateOrderId($request_params);                   #order id creation
        $decoded_data = json_decode($generate_order_id->getContent(), true);

        ThirdPartyPaymentReqResponse::insert(                                             #storing request
            [
                'enquiry_id' => $enquiryId,
                'request' => json_encode($request_params),
                'response' => json_encode($decoded_data)
            ]
        );

        $store_data_encrypt = [
            'enquiry_id'        => $enquiryId,
            'product'           => $productData->product_name,
            'section'           => 'CAR',
            'method_name'       => 'Order Id Creation',
            'company'           => 'united_india',
            'method'            => 'post',
            'transaction_type'  => 'proposal',
            'request'           => json_encode($request_params),
            'response'          => json_encode($decoded_data),
            'endpoint_url'      => 'https://api.razorpay.com/v1/orders',
            'ip_address'        => request()->ip(),
            'start_time'        => $startTime->format('Y-m-d H:i:s'),
            'end_time'          => $endTime->format('Y-m-d H:i:s'),
            'response_time'     => $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'        => Carbon::now(),
            'headers'           => NULL
        ];

        WebServiceRequestResponse::create($store_data_encrypt);

        if ((isset($decoded_data['status']) && $decoded_data['status'])) {     #successful part

            $create_order_response = $decoded_data['data'];

            $order_id = $decoded_data['order_id'];

            try {

                // $startTime = new DateTime(date('Y-m-d H:i:s'));
                // $endTime = new DateTime(date('Y-m-d H:i:s'));

                // $store_data_encrypt = [
                //     'enquiry_id'        => $enquiryId,
                //     'product'           => $productData->product_name,
                //     'section'           => 'CAR',
                //     'method_name'       => 'OrderId Creation',
                //     'company'           => 'united_india',
                //     'method'            => 'post',
                //     'transaction_type'  => 'proposal',
                //     'request'           => json_encode($request_params),
                //     'response'          => is_array($create_order_response) ? json_encode($create_order_response) : $create_order_response,
                //     'endpoint_url'      => 'https://api.razorpay.com/v1/orders',
                //     'ip_address'        => request()->ip(),
                //     'start_time'        => $startTime->format('Y-m-d H:i:s'),
                //     'end_time'          => $endTime->format('Y-m-d H:i:s'),
                //     'response_time'        => $endTime->getTimestamp() - $startTime->getTimestamp(),
                //     'created_at'        => Carbon::now(),
                //     'headers'           => NULL
                // ];

                // WebServiceRequestResponse::create($store_data_encrypt);

                $data['user_product_journey_id']    = $user_proposal->user_product_journey_id;
                $data['ic_id']                      = $user_proposal->ic_id;
                $data['stage']                      = 'Payment Initiated';
                updateJourneyStage($data);


                DB::table('payment_request_response')->insert([
                    'quote_id'                  => $quote_log_id,
                    'user_product_journey_id'   => $enquiryId,
                    'user_proposal_id'          => $user_proposal->user_proposal_id,
                    'ic_id'                     => $icId,
                    'order_id'                  => $order_id,
                    'amount'                    => $user_proposal->final_payable_amount,
                    'payment_url'               => config('constants.IcConstants.united_india.car.END_POINT_URL_PAYMENT_GATEWAY'),
                    'return_url'                => route(
                        'car.payment-confirm',
                        [
                            'united_india',
                            'user_proposal_id'      => $user_proposal->user_proposal_id,
                            'policy_id'             => $request->policyId
                        ]
                    ),
                    'status'                    => 'Payment Initiated',
                    'active'                    => 1
                ]);

                UserProposal::updateOrInsert(
                    ['user_product_journey_id' => $enquiryId],
                    ['unique_proposal_id' => $order_id]
                );

                $return_data = [
                    'paymentGateway' => 'razorpay',
                    'clientKey' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_KEY_ID'),
                    'orderId' => $order_id,
                    "amount" => $create_order_response['amount_due'],
                    'returnUrl' =>  route('car.payment-confirm', ['united_india'])
                ];


                return response()->json([
                    'status' => true,
                    'msg' => "Payment Reidrectional",
                    'data' => $return_data,
                ]);
            } catch (\Exception $e) {

                return response()->json([
                    'status' => false,
                    'message' => 'Error decoding payment response: ' . $e->getMessage(),
                    'data' => null,
                ]);
            }
        } else {

            return response()->json([
                'status' => false,
                'message' => 'Invalid Response in Order ID Creation Service',
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
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $order_id = $request->razorpay_order_id;
        $razorpay_signature = $request->razorpay_signature;

        $razorpay_payment_id = $request->razorpay_payment_id;
        $enquiry_id = customDecrypt($request->enquiryId);

        $request_params = [
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature,
            'order_id' => $order_id,

            'Razorpay_key_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_KEY_ID'),
            'Razorpay_secret_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_SECREAT_KEY'),
            'enquiry_id' => $enquiry_id,
        ];

        $transaction_info = RazorPay::checkPaymentStaus($request_params);  #check the transaction status
        $decoded_data = json_decode($transaction_info->getContent(), true);

        ThirdPartyPaymentReqResponse::insert(                               #storing request
            [
                'enquiry_id' => $enquiry_id,
                'request' => $razorpay_payment_id,
                'response' => json_encode($decoded_data)
            ]
        );

        $store_data_encrypt = [
            'enquiry_id'        => $enquiry_id,
            'product'           => 'Car Insurance',
            'section'           => 'CAR',
            'method_name'       => 'Check Payment Status', 
            'company'           => 'united_india',
            'method'            => 'post',
            'transaction_type'  => 'proposal',
            'request'           => json_encode($request_params),
            'response'          => json_encode($decoded_data),
            'endpoint_url'      => 'https://api.razorpay.com/v1/payments/' . $razorpay_payment_id,
            'ip_address'        => request()->ip(),
            'start_time'        => $startTime->format('Y-m-d H:i:s'),
            'end_time'          => $endTime->format('Y-m-d H:i:s'),
            'response_time'     => $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'        => Carbon::now(),
            'headers'           => NULL
        ];

        WebServiceRequestResponse::create($store_data_encrypt);

        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();

        if ($decoded_data['status'] && !empty($decoded_data['data'])) {                        #checking tranasaction stage

            if ($decoded_data['data']['status'] == 'authorized') {

                try {
                    
                    $request_params = [
                        'Razorpay_key_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_KEY_ID'),
                        'Razorpay_secret_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_SECREAT_KEY'),
                        'razorpay_payment_id' => $razorpay_payment_id,
                        'amount' => $decoded_data['data']['amount'],
                        'enquiry_id' => $enquiry_id,
                    ];


                    $confirm_data = RazorPay::confirmPaymentStaus($request_params);       #verify transaction
                    $decode_data = json_decode($confirm_data->getContent(), true);


                    ThirdPartyPaymentReqResponse::insert(                                   #storing transaction
                        [
                            'enquiry_id' => $enquiry_id,
                            'request' => $razorpay_payment_id,
                            'response' => json_encode($decode_data)
                        ]
                    );

                    $store_data_encrypt = [
                        'enquiry_id'        => $enquiry_id,
                        'product'           => 'Car Insurance',
                        'section'           => 'CAR',
                        'method_name'       => 'Confirm Payment Status', 
                        'company'           => 'united_india',
                        'method'            => 'post',
                        'transaction_type'  => 'proposal',
                        'request'           => json_encode($request_params),
                        'response'          => json_encode($decoded_data),
                        'endpoint_url'      => 'https://api.razorpay.com/v1/payments/' . $razorpay_payment_id,
                        'ip_address'        => request()->ip(),
                        'start_time'        => $startTime->format('Y-m-d H:i:s'),
                        'end_time'          => $endTime->format('Y-m-d H:i:s'),
                        'response_time'     => $endTime->getTimestamp() - $startTime->getTimestamp(),
                        'created_at'        => Carbon::now(),
                        'headers'           => NULL
                    ];
            
                    WebServiceRequestResponse::create($store_data_encrypt);

                } catch (Exception $e) {

                    if ($e->getMessage() != 'This payment has already been captured') {
                        return response()->json([
                            'status' => false
                        ]);
                    }
                }
            }

            if ($decoded_data['data']['error_code'] == null && $decoded_data['data']['status'] == 'captured' && $decoded_data['data']['captured'] == true) {

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->where('order_id', $order_id)
                    ->update([
                        'response'      => json_encode($decoded_data['data']),
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'status'        => 'Payment Success'
                    ]);

                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = 'Payment Success';
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
                    'utr_number'         =>        $decoded_data['data']['id'],                     #transaction id
                ];

                $payment_service = unitedIndiaPaymentGateway::payment_info_service($proposal_data, $proposal);

                if (!$payment_service['status']) {
                    return redirect(paymentSuccessFailureCallbackUrl($enquiry_id, 'CAR', 'SUCCESS'));
                }

                unitedIndiaPaymentGateway::create_pdf(
                    $proposal,
                    [
                        'policy_number' => $payment_service['policy_no'],
                        'pdf_schedule' => $payment_service['pdf_schedule']
                    ]
                );

                return response()->json([
                    'status' => true,
                    'redirectUrl' => paymentSuccessFailureCallbackUrl($enquiry_id,'CAR','SUCCESS'),
                ]);
                
            } else {

                DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update([
                        'response'      => json_encode($decoded_data['data']),
                        'updated_at'    => date('Y-m-d H:i:s'),
                        'status'        => 'Payment Failed'
                    ]);

                $data['user_product_journey_id']    = $proposal->user_product_journey_id;
                $data['ic_id']                      = $proposal->ic_id;
                $data['stage']                      = 'Payment Failed';
                updateJourneyStage($data);

                return response()->json([
                    'status' => false,
                    'redirectUrl' => paymentSuccessFailureCallbackUrl($enquiry_id,'CAR','FAILURE'),
                ]);
            }
        }

        return response()->json([
            'status' => false,
            'redirectUrl' => paymentSuccessFailureCallbackUrl($enquiry_id,'CAR','FAILURE'),
        ]);
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
                // 'prr.status'                    => 'Payment Success'
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

        // echo "<pre>";print_r([$proposal->getAttributes(), $policy_details]);echo "</pre>";die(); 1804003121P100557502

        if ($policy_details->pdf_url == '') {
            $generatePolicy['status'] = false;

            if (is_null($policy_details->policy_number) || $policy_details->policy_number == '') {
                
                $get_order_id = DB::table('payment_request_response')  //for checking order id
                    ->where('user_product_journey_id', $user_product_journey_id)
                    ->select('order_id')
                    ->get();

                if (!empty($get_order_id)) {

                    foreach ($get_order_id as $value) {

                        $get_order_id = $value->order_id;

                        $request_params = [
                            'order_id' => $get_order_id,
                            'enquiryId' => $user_product_journey_id,
                            'Razorpay_key_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_KEY_ID'),
                            'Razorpay_secret_id' => config('constants.IcConstants.UNITED_INDIA_CAR_ROZER_PAY_SECREAT_KEY'),
                        ];
        
                        $resp_data = RazorPay::fetchPaymentStaus($request_params);
                        $responseContent = json_decode($resp_data->getContent(), true);
        
                        ThirdPartyPaymentReqResponse::insert(                                             #storing request
                            [
                                'enquiry_id' => $user_product_journey_id,
                                'request' => json_encode($request_params),
                                'response' => json_encode($responseContent)
                            ]
                        );

                        $startTime = new DateTime(date('Y-m-d H:i:s'));
                        $endTime = new DateTime(date('Y-m-d H:i:s'));

                        $store_data_encrypt = [
                            'enquiry_id'        => $user_product_journey_id,
                            'product'           => 'Private Car Insurance',
                            'section'           => 'CAR',
                            'method_name'       => 'Fetch Payment Status',
                            'company'           => 'united_india',
                            'method'            => 'post',
                            'transaction_type'  => 'proposal',
                            'request'           => json_encode($request_params),
                            'response'          => json_encode($responseContent),
                            'endpoint_url'      => 'https://api.razorpay.com/v1/orders/' . $get_order_id . '/payments',
                            'ip_address'        => request()->ip(),
                            'start_time'        => $startTime->format('Y-m-d H:i:s'),
                            'end_time'          => $endTime->format('Y-m-d H:i:s'),
                            'response_time'     => $endTime->getTimestamp() - $startTime->getTimestamp(),
                            'created_at'        => Carbon::now(),
                            'headers'           => NULL
                        ];
        
                        WebServiceRequestResponse::create($store_data_encrypt);
        
                        if ($responseContent['status'] && !empty($responseContent['data'])) {
        
                            $response = $responseContent['data'];
        
                            if (isset($response['items']) && isset($response['items'][0]['status']) && $response['items'][0]['status'] == 'captured') {

                                $order_id = $response['items'][0]['order_id'] ?? null;
        
                                DB::table('payment_request_response')->where('order_id', $order_id)
                                ->update([
                                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                    'response' => json_encode($responseContent)
                                ]);
                            }
                        }
                    }
                }

                $generatePolicy = unitedIndiaPaymentGateway::generatePolicy($proposal, $request->all());
            }

            // echo "<pre>";print_r([$generatePolicy, $policy_details]);echo "</pre>";die();

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
                'msg'    => 'Pdf already generated',
                'data'   => [
                    'ic_pdf_url' => $policy_details->ic_pdf_url,
                    'pdf_url' => $policy_details->pdf_url,
                    'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'united_india/' . md5($proposal->user_proposal_id) . '.pdf'),
                    'policy_number' => $policy_details->policy_number
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }
}
