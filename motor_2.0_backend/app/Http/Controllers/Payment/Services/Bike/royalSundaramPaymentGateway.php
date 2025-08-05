<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use App\Http\Controllers\Payment\Services\Car\royalSundaramPaymentGateway as ROYAL_CAR_PAYMENT_GATEWAY;
use App\Models\CorporateVehiclesQuotesRequest;

class royalSundaramPaymentGateway {

    public static function make($request) {
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->pluck('quote_id')
                ->first();
        $icId = $user_proposal->ic_id;
        $productData = getProductDataByIc($request->policyId);
        DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->update(['active' => 0]);

        $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->get()
        ->first();

        if($CorporateVehiclesQuotesRequest->previous_insurer_code != 'royal_sundaram' || $CorporateVehiclesQuotesRequest->is_renewal !== 'Y')
        {
            // NORMAL FLOW
            $proposal_array = [
                'authenticationDetails' => [
                    'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
                    'apiKey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE'),
                ],
                'premium' => $user_proposal->final_payable_amount,
                'quoteId' => $user_proposal->unique_proposal_id,
                'emailId' => $user_proposal->email,
                'isOTPVerified'=> 'Yes',
                'reqType' => 'xml'
            ];
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $proposal_array['uniqueId'] = $user_proposal->unique_proposal_id;
                $proposal_array['ckycNo'] = $user_proposal->ckyc_number;
            }
    
            $get_response = getWsData(config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_BIKE_PROPOSAL'), $proposal_array, 'royal_sundaram', [
                'enquiryId' => $user_proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'productName' => $productData->product_name,
                'company' => 'royal_sundaram',
                'section' => $productData->product_sub_type_code,
                'method' =>'Proposal Generation',
                'transaction_type' => 'proposal',
                'root_tag' => 'GPROPOSALREQUEST',
            ]);
            $data = $get_response['response'];
        }else{
            //RENEWAL FLOW
            $data = true;
        }

        if ($data) {
            if($CorporateVehiclesQuotesRequest->previous_insurer_code != 'royal_sundaram' || $CorporateVehiclesQuotesRequest->is_renewal !== 'Y')
            {
                //NORMAL FLOW
                $proposal_response = json_decode($data, TRUE);

                if (isset($proposal_response['PREMIUMDETAILS']['Status']['StatusCode']) && $proposal_response['PREMIUMDETAILS']['Status']['StatusCode'] == 'S-0005') {
                    updateJourneyStage([
                        'user_product_journey_id' => customDecrypt($request->userProductJourneyId),
                        'ic_id' => $icId,
                        'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
                        'proposal_id' => $user_proposal->user_proposal_id,
                    ]);
    
                    $return_data = [
                        'form_action' => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_BIKE'),
                        'form_method' => 'POST',
                        'payment_type' => 0,
                        'form_data' => [
                            'reqType' => 'JSON',
                            'process' => 'paymentOption',
                            'apikey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE'),
                            'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
                            'premium' => $user_proposal->final_payable_amount,
                            'quoteId' => ($CorporateVehiclesQuotesRequest->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest->rollover_renewal != 'Y') ? $user_proposal->previous_policy_number : $user_proposal->unique_proposal_id,
                            'version_no' => $user_proposal->version_no,
                            'strFirstName' => $user_proposal->first_name,
                            'strEmail' => $user_proposal->email,
                            'strMobileNo' => $user_proposal->mobile_number,
                            'isQuickRenew' => 'No',
                            /* 'crossSellProduct' => '',
                            'crossSellQuoteid' => '', */
                            'returnUrl' => route('bike.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                            'vehicleSubLine' => 'privatePassengerCar',#'motorCycle',
                            'elc_value' => '',
                            'nonelc_value' => '',
                            'paymentType' => (env('APP_ENV') == 'local') ? 'RazorPay' : 'PAYTM',
                            #'BusinessType' => '',
                        ]
                    ];
    
                    DB::table('payment_request_response')->insert([
                        'quote_id' => $quote_log_id,
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'user_proposal_id' => $user_proposal->user_proposal_id,
                        'ic_id' => $icId,
                        'order_id' => $user_proposal->proposal_no,
                        'amount' => $user_proposal->final_payable_amount,
                        'payment_url' => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_BIKE'),
                        'return_url' => route('bike.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                        'xml_data' => json_encode($return_data),
                        'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                        'active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    if (isset($proposal_response['PREMIUMDETAILS']['Status'])) {
                        return [
                            'status' => false,
                            'message' => $proposal_response['PREMIUMDETAILS']['Status']['Message']
                        ];
                    } else {
                        return [
                            'status' => false,
                            'message' => 'Insurer not reachable'
                        ];
                    }
                }
            }else
            {
                //RENEWAL FLOW

                    updateJourneyStage([
                        'user_product_journey_id' => customDecrypt($request->userProductJourneyId),
                        'ic_id' => $icId,
                        'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
                        'proposal_id' => $user_proposal->user_proposal_id,
                    ]);
    
                    $return_data = [
                        'form_action' => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_BIKE'),
                        'form_method' => 'POST',
                        'payment_type' => 0,
                        'form_data' => [
                            'reqType' => 'JSON',
                            'process' => 'paymentOption',
                            'apikey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE'),
                            'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
                            'premium' => $user_proposal->final_payable_amount,
                            'quoteId' => $user_proposal->unique_proposal_id,
                            'version_no' => ( $CorporateVehiclesQuotesRequest->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest->previous_insurer_code == 'royal_sundaram') ? '12345' :  $user_proposal->version_no,
                            'strFirstName' => $user_proposal->first_name,
                            'strEmail' => $user_proposal->email,
                            'strMobileNo' => $user_proposal->mobile_number,
                            'isQuickRenew' => ( $CorporateVehiclesQuotesRequest->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest->previous_insurer_code == 'royal_sundaram') ? 'Yes' : 'No',
                            /* 'crossSellProduct' => '',
                            'crossSellQuoteid' => '', */
                            'returnUrl' => route('bike.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                            'vehicleSubLine' => 'privatePassengerCar',#'motorCycle',
                            'elc_value' => '',
                            'nonelc_value' => '',
                            'paymentType' => (env('APP_ENV') == 'local') ? 'RazorPay' : (( $CorporateVehiclesQuotesRequest->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest->previous_insurer_code == 'royal_sundaram') ? 'billdesk' : 'PAYTM'),
                            #'BusinessType' => '',
                        ]
                    ];
    
                    DB::table('payment_request_response')->insert([
                        'quote_id' => $quote_log_id,
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'user_proposal_id' => $user_proposal->user_proposal_id,
                        'ic_id' => $icId,
                        'order_id' => $user_proposal->proposal_no,
                        'amount' => $user_proposal->final_payable_amount,
                        'payment_url' => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_BIKE'),
                        'return_url' => route('bike.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                        'xml_data' => json_encode($return_data),
                        'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                        'active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        } else {
            $return_data = [
                'status' => 'false',
                'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
            ];
        }

        return response()->json([
            'status' => true,
            'msg' => "Payment Redirection",
            'data' => $return_data,
        ]);
    }

    public static function confirm($request) {
        $response = $request->All();
        $user_proposal = UserProposal::find($response['user_proposal_id']);
        $quote_log = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->select('quote_id', 'master_policy_id')
            ->first();
        #$return_url = config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL');
        $return_url = paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'FAILURE');
        $status = false;
        $message = STAGE_NAMES['PAYMENT_FAILED'];
        $policy_no = NULL;
        PaymentRequestResponse::updateOrCreate(['quote_id' => $quote_log->quote_id, 'active' => 1], [
            'response' => json_encode($response),
            'status' => ((isset($response['status']) && $response['status'] == 'success') ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED']),
            'proposal_no' => ((isset($response['policyNO']) && $response['policyNO'] != '') ? $response['policyNO'] : ''),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        if ($response['status'] == 'success' || $response['status'] === 'true') {
            $policy_no = $response['policyNO'];
            if(empty($policy_no))
            {
                $enquiryId = $user_proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'BIKE','SUCCESS'));
            }
            UserProposal::where('user_proposal_id' , $response['user_proposal_id'])->update([
                'policy_no' => $policy_no
            ]);
            $user_proposal->policy_no = $policy_no;
            $generate_pdf = self::generate_pdf($user_proposal)->getOriginalContent();
            $status = $generate_pdf['status'];
            $message = $generate_pdf['msg'];
            #$return_url = config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL');
            $return_url = paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'BIKE', 'SUCCESS');
        }

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'ic_id' => $user_proposal->ic_id,
            'stage' => $message
        ]);

        return redirect($return_url);
    }

    public static function generate_pdf($proposal) {
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        $status = false;
        $data = [];
        try {
            
        $pdf_generate_url = config('constants.IcConstants.royal_sundaram.POLICY_PDF_ROYAL_SUNDARAM_BIKE') . "?quoteId=" . $proposal->unique_proposal_id . "&type=PurchasedPdf&businessType=NB&force=true&proposerDob=" . $proposal->dob."&expiryDate=".$proposal->policy_end_date;
        PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id ], [
            'policy_number' => $proposal->policy_no,
            'policy_start_date' => $proposal->policy_start_date,
            'ic_pdf_url' => $pdf_generate_url,
            'ncb' => $proposal->ncb_discount,
            'premium' => $proposal->final_payable_amount,
            'idv' => $proposal->idv,
            'status' => 'SUCCESS'
        ]);

        if (config('ENABLE_ROYAL_SUNDARAM_BASE64PDF_SERVICE') == 'Y') {
            return self::pdfGenerationService($proposal);
        }
        
        $context_options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        // $pdf_binary_data = file_get_contents($pdf_generate_url, false, stream_context_create($context_options));

        $pdf_binary_data = httpRequestNormal($pdf_generate_url, 'GET', [], [], [], $context_options, false)['response'];

        if (!empty($pdf_binary_data)) {
            $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'royal_sundaram/' . md5($proposal->user_proposal_id) . '.pdf';

            try {
                Storage::put($pdf_name, $pdf_binary_data);
                PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id ], [
                    'pdf_url' => $pdf_name,
                ]);
                $status = true;
                $message = STAGE_NAMES['POLICY_ISSUED'];
                $data = [
                    'policy_number' => $proposal->policy_no,
                    'pdf_link' => file_url($pdf_name)
                ];
                return response()->json([
                    'status' => $status,
                    'msg' => $message,
                    'data' => $data
                ]);
            } catch (\Throwable $th) {
                // $message = 'Policy Issued, but pdf not generated. Reason : '.$th->getMessage();
            }
        }
        } catch (\Throwable $th) {
            // $message = 'Policy Issued, but pdf not generated. Reason : '.$th->getMessage();
        }

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'data' => $data
        ]);
    }

    public static function pdfGenerationService($proposal)
    {
        $corporateVehiclesQuotesRequest = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $proposal->user_product_journey_id)
        ->first();
        $isRenewal = ($corporateVehiclesQuotesRequest->is_renewal ?? '') == 'Y' && ($corporateVehiclesQuotesRequest->rollover_renewal ?? '') != 'Y';
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        $status = false;
        $data = [];
        $pdfRequest = [
            'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
            'apiKey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE'),
            'businessType' => $isRenewal ? 'RN' : 'NB',
            'expiryDate' => !empty($proposal->policy_end_date ?? null) ? date('d/m/Y', strtotime($proposal->policy_end_date)) : null,
            'force' => true,
            'pdfType' => "purchasedPDF",
            'policyNumber' => $proposal->policy_no,
            'tinyURL' => config('constants.IcConstants.royal_sundaram.pdf.isTinyUrl') == 'Y' ? 'Yes' : 'No',
        ];
        $pdfResponse = getWsData(config('constants.IcConstants.royal_sundaram.GENERATE_BASE64PDF_SERVICE_URL'), $pdfRequest, 'royal_sundaram', [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' => 'post',
            'productName' => '',
            'company' => 'royal_sundaram',
            'section' => 'BIKE',
            'method' => 'Generate Base64 Pdf',
            'transaction_type' => 'proposal'
        ]);
        $pdfResponse = $pdfResponse['response'];
        if (!empty($pdfResponse)) {
            $data = json_decode($pdfResponse, true);
            if (!empty($data['data'] ?? null)) {
                if ($pdfRequest['tinyURL'] == 'Yes') {
                    $pdfData = $data['data']['tinyUrlMotor']['shorturl'] ?? null;
                    if (empty($pdfData)) {
                        return response()->json([
                            'status' => false,
                            'msg' => $message,
                        ]);
                    }
                    $pdfBinaryData = httpRequestNormal($pdfData, 'GET', [], [], [] , [], false)['response'];
                } else {
                    $pdfData = $data['data']['base64File'] ?? null;
                    if (empty($pdfData)) {
                        return response()->json([
                            'status' => false,
                            'msg' => $message,
                        ]);
                    }
                    $pdfBinaryData = base64_decode($pdfData);
                }
                try {
                    $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'royal_sundaram/' . md5($proposal->user_proposal_id) . '.pdf';
                    if (!checkValidPDFData($pdfBinaryData)) {
                        return response()->json([
                            'status' => false,
                            'msg' => 'IC\'s PDF data content is not a valid PDF data.',
                        ]);
                    }
                    Storage::put($pdf_name, $pdfBinaryData);

                    $policyData = [
                        'pdf_url' => $pdf_name
                    ];

                    if ($pdfRequest['tinyURL'] == 'Yes' && isset($pdfData)) {
                        $policyData['ic_pdf_url'] = $pdfData;
                    }

                    PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id], $policyData);

                    $data = [
                        'policy_number' => $proposal->policy_no,
                        'pdf_link' => file_url($pdf_name)
                    ];
                    $status = true;
                    $message = STAGE_NAMES['POLICY_ISSUED'];
                } catch (\Throwable $th) {
                    info($th);
                }

            }
        }
        return response()->json([
            'status' => $status,
            'msg' => $message,
            'data' => $data
        ]);
    }

    public static function recon($request) {
        $status = false;
        $message = 'No data found';
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $quoteId = $proposal->unique_proposal_id;
        $quote_log = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->select('quote_id', 'master_policy_id')
            ->first();
        $productData = getProductDataByIc($quote_log->master_policy_id);
        $transaction_status_array = [
            'agent_id' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_BIKE'),
            'api_key' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_BIKE'),
            'quote_id' => $quoteId
        ];

        $message = 'No response from Policy Transaction Status API';
        $get_response = getWsData(config('constants.IcConstants.royal_sundaram.CHECK_TRANSACTION_STATUS_ROYAL_SUNDARAM_BIKE'), $transaction_status_array, 'royal_sundaram', [
            'enquiryId' => $proposal->user_product_journey_id,
            'requestMethod' =>'post',
            'productName' => $productData->product_name,
            'company' => 'royal_sundaram',
            'section' => 'BIKE',
            'method' =>'Check Transaction Status',
            'transaction_type' => 'payment',
            'root_tag' => 'TransactionCheckRequest',
        ]);
        $data = $get_response['response'];

        if ($data) {
            $transaction_status_response = json_decode($data, true);
            $message = 'Policy Transaction Status is false';

            if(isset($transaction_status_response['code']) && $transaction_status_response['code'] == 'S-1701' && isset($transaction_status_response['data']) && isset($transaction_status_response['data']['policyConverted']) && $transaction_status_response['data']['policyConverted'] == 'Yes') {
                $generate_pdf = self::generate_pdf($proposal)->getOriginalContent();
                $status = $generate_pdf['status'];
                $message = $generate_pdf['msg'];
            }
        }

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'ic_id' => $proposal->ic_id,
            'stage' => $message
        ]);

        return response()->json([
            'status' => $status,
            'msg' => $message
        ]);
    }

    public static function generatePdf($request) {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payment_status = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id,
            //'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
            //'active' => 1
        ])->get();
        //->first();
        if (empty($payment_status)) 
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment Details Not Found'
            ]);
        }
        else
        {
            
            $break_loop = false;
            foreach ($payment_status as $key => $value) 
            { 
                $payment_status_data = [
                    'enquiryId'                     => $user_product_journey_id,
                    'payment_request_response_id'   => $value->id,
                    'order_id'                      => $value->order_id,
                    'section'                       => 'Bike'
                ];
                $payment_response = ROYAL_CAR_PAYMENT_GATEWAY::payment_status((object) $payment_status_data);
                //$payment_response = self::payment_status((object) $payment_status_data);
                
//                $payment_response = '{
//                                    "data": {
//                                        "quoteId": "BA502965VPC12046426",
//                                        "premium": "6587.94",
//                                        "policyNumber": "VPRB044585000100",
//                                        "policyDownloadLink": "http://10.46.194.192/Services/Mailer/DownloadPdf?quoteId=BA502965VPC12046426&type=PurchasedPdf&expiryDate=01/07/2023&proposerDob=05/11/1984",
//                                        "policyConverted": "Yes",
//                                        "transactionNumber": "WHMP1230604835"
//                                    },
//                                    "code": "S-1701",
//                                    "message": "Transaction Check Status Fetched Successfully"
//                                }';
//                print_r($payment_response);
//                die;
                $payment_response  = json_decode($payment_response,True);
                if(isset($payment_response['data']) && $payment_response['data']['policyConverted'] == 'Yes')
                {
                    $break_loop = true;
                    $payment_status = STAGE_NAMES['PAYMENT_SUCCESS'];                         
                    $updatePaymentResponse = [
                        'status'  => $payment_status
                    ];
                    PaymentRequestResponse::where('id', $value->id)
                        ->update($updatePaymentResponse);

                    $policyNumber = $payment_response['data']['policyNumber'] ?? NULL;
                    if($policyNumber != NULL)
                    {
                        UserProposal::where('user_proposal_id' , $value->user_proposal_id)->update([
                            'policy_no' => $policyNumber
                        ]);
                        
                        $PolicyDetails = PolicyDetails::where('proposal_id','=',$value->user_proposal_id)->get()->first();
                        
                        if(empty($PolicyDetails))
                        {
                            $policy_data = [
                                    'proposal_id'   => $value->user_proposal_id,
                                    'policy_number' => $policyNumber,
                                    'status'        => 'SUCCESS',
                                ];
                            PolicyDetails::insert($policy_data);
                        }
                        else
                        {
                            if($PolicyDetails->policy_number == '')
                            {
                                $policy_data = [
                                    'policy_number' => $policyNumber,
                                ];
                                PolicyDetails::where('proposal_id','=',$value->user_proposal_id)->update($policy_data);
                            }
                        }
                    }                    
                }
                
                if($break_loop == true)
                {
                    break;
                }                
            }
        }
        $PolicyDetails = PolicyDetails::where('proposal_id','=',$value->user_proposal_id)->get()->first();
        if(empty($PolicyDetails))
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Policy Number Not Found'
            ]);            
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $generate_pdf = self::generate_pdf($proposal)->getOriginalContent();
        $status = $generate_pdf['status'];
        $message = $generate_pdf['msg'];

        updateJourneyStage([
            'user_product_journey_id' => $proposal->user_product_journey_id,
            'stage' => $message
        ]);

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'data' => $generate_pdf['data'] ?? NULL
        ]);
    }
}