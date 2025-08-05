<?php

namespace App\Http\Controllers\Payment\Services;
include_once app_path() . '/Helpers/CvWebServiceHelper.php';

use App\Http\Controllers\Controller;

use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestResponse;
use App\Models\CorporateVehiclesQuotesRequest;
use DateTime;

class royalSundaramPaymentGateway extends Controller
{
   /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request) 
    {
        $user_proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->pluck('quote_id')
            ->first();
        $icId = $user_proposal->ic_id;
        $productData = getProductDataByIc($request->policyId);
        DB::table('payment_request_response')
            ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->update(['active' => 0]);

        if ($user_proposal->is_breakin_case == 'Y') 
        {
            $breakin_status = DB::table('cv_breakin_status')->where('user_proposal_id', $user_proposal->user_proposal_id)->select('*')->first();

            $return_data = [
                'status' => false,
                'msg' => 'Proposal is not recommended'
            ];

            if (!empty($breakin_status) && $breakin_status->breakin_status_final == STAGE_NAMES['INSPECTION_APPROVED']) 
            {
                $return_data = self::proposal_creation($user_proposal, $productData, $quote_log_id);
            }
        } 
        else 
        {
            PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->update(['active' => 0]);
            
            $json_data = 
            [
                'form_action' => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_CV_MOTOR'),
                //'form_action' => 'https://www.royalsundaram.net/web/test/paymentgateway',
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'reqType'       => 'JSON',
                    'process'       => 'paymentOption',
                    "apikey"        => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                    "agentId"       => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                    'premium'       => $user_proposal->final_payable_amount,
                    'quoteId'       => $user_proposal->unique_proposal_id,
                    'version_no'    => $user_proposal->version_no,
                    'strFirstName'  => $user_proposal->first_name,
                    'strMobileNo'   => $user_proposal->mobile_number,
                    'strEmail'      => $user_proposal->email,
                    //'version_no' => "12345",
                    //'strFirstName' => $user_proposal->first_name,
                    //'strEmail' => "website.support@royalsundaram.in",
                    'isQuickRenew'  => 'No',
                    'crossSellProduct' => '',
                    'crossSellQuoteid' => '',
                    'returnUrl' => route('cv.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                    //'returnUrl'         => "https://cv-test.uat.assurecore.io/payment/ROYALSGI/policyCreate/PQ-FOW-0044-2210-ASSUREKIT_HEALTH/ASSUREKIT_HEALTH/",
                    // 'returnUrl' => "https://localhost:8000/car/payment-confirm/royal_sundaram?user_proposal_id=3571",
                    'vehicleSubLine'    => 'privatePassengerCar',
                    'elc_value'         => (!empty($user_proposal->electrical_accessories) && $user_proposal->electrical_accessories != '0') ? $user_proposal->electrical_accessories : '',
                    'nonelc_value'      => (!empty($user_proposal->non_electrical_accessories) && $user_proposal->non_electrical_accessories != '0') ? $user_proposal->non_electrical_accessories : '',
                    'paymentType'       => (env('APP_ENV') == 'local') ? 'RazorPay' : 'Billdesk',
                    //'BusinessType'      => '',
                ]
            ];
            
            PaymentRequestResponse::insert([
                'quote_id'                  => $quote_log_id,
                'user_product_journey_id'   => $user_proposal->user_product_journey_id,
                'user_proposal_id'          => $user_proposal->user_proposal_id,
                'ic_id'                     =>  $user_proposal->ic_id,
                'order_id'                  => $user_proposal->proposal_no,
                'proposal_no'               => $user_proposal->proposal_no,
                'amount'                    => $user_proposal->final_payable_amount,
                'payment_url'               => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_CV_MOTOR'),
                'return_url'                => route('cv.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                'xml_data'                  => json_encode($json_data),
                'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
                'active'                    => 1,
                'created_at'                => date('Y-m-d H:i:s'),
            ]);
            
            updateJourneyStage([
                'user_product_journey_id'   => $user_proposal->user_product_journey_id,
                'ic_id'                     => 35,
                'stage'                     => STAGE_NAMES['PAYMENT_INITIATED']
            ]);
            $return_data = [
                'status' => true,
                'msg' => 'Payment Redirection',
                'data' => $json_data
            ];
        }
        return response()->json($return_data);
    }

    public static function proposal_creation($user_proposal, $productData, $quote_log_id) 
    {
        $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', $user_proposal->user_product_journey_id)
        ->get()
        ->first();
        $isInspectionWaivedOff = false;
        $waiverExpiry = null;
        if (
            $user_proposal->business_type == 'breakin' &&
            !empty($user_proposal->previous_policy_expiry_date) &&
            strtoupper($user_proposal->previous_policy_expiry_date) != 'NEW' &&
            config('ROYAL_SUNDARAM_INSPECTION_WAIVED_OFF_CV') == 'Y'
        ) {
                $isInspectionWaivedOff = true;
                $waiverExpiry = date('Y-m-d', strtotime($user_proposal->previous_policy_expiry_date . ' +90 days'));
        }

        if ($user_proposal->business_type == 'newbusiness') 
        {
            $businessType = 'New Business';
        } 
        else if ($user_proposal->business_type == 'rollover') 
        {
            $businessType = 'Roll Over';
        } 
        else if ($user_proposal->business_type == 'breakin') 
        {
            $businessType = 'Break-In';
        }

        if (get_parent_code($productData->product_sub_type_id) == 'PCV')
        {
            $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_PCV_G_PROPOSAL';
        }
        elseif (get_parent_code($productData->product_sub_type_id) == 'GCV') 
        {
            $requestUrl = 'constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_GCV_G_PROPOSAL';
        }

        if($user_proposal->business_type !== 'breakin' && $CorporateVehiclesQuotesRequest->is_renewal !== 'Y' || $isInspectionWaivedOff == true)
        {
            $proposal_array = [
                "authenticationDetails" => [
                    "apikey" => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                    "agentId" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                    "partner" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR')
                ],
                 "quoteId"          =>  $user_proposal->unique_proposal_id,
                 "premium"          =>  $user_proposal->final_payable_amount,
                 "strEmail"         =>  $user_proposal->email,
                 "reqType"          =>  "XML",
                 "isOTPVerified"    =>  "Yes"
            ];
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $proposal_array['uniqueId'] = $user_proposal->unique_proposal_id;
                $proposal_array['ckycNo'] = $user_proposal->ckyc_number;
            }
            $get_response = getWsData(config($requestUrl), $proposal_array, 'royal_sundaram', [
            //$data = getWsData('https://dtcdocstag.royalsundaram.in/Services/Product/PassengerCarryingVehicle/GProposalService', $proposal_array, 'royal_sundaram', [
                'enquiryId' => $user_proposal->user_product_journey_id,
                'requestMethod' =>'post',
                'productName' => $productData->product_name. " ($businessType)",
                'company' => 'royal_sundaram',
                'section' => $productData->product_sub_type_code,
                'method' =>'Proposal Generation',
                'transaction_type' => 'proposal',
                'root_tag' => 'GPROPOSALREQUEST',
            ]);
            $data = $get_response['response'];
        }
        else
        {
            $data = true;
        }

        if ($data) {

            if($user_proposal->business_type !== 'breakin' && $CorporateVehiclesQuotesRequest->is_renewal !== 'Y' || $isInspectionWaivedOff == true)
            {
                $proposal_response = json_decode($data, TRUE);
                if (isset($proposal_response['PREMIUMDETAILS']['Status'])) 
                {
                    updateJourneyStage([
                        'user_product_journey_id'   => $user_proposal->user_product_journey_id,
                        'ic_id'                     => $user_proposal->ic_id,
                        'stage'                     => STAGE_NAMES['PAYMENT_INITIATED'],
                        'proposal_id'               => $user_proposal->user_proposal_id,
                    ]);
    
                    $json_data = [
                        'form_action' => config('constants.IcConstants.royal_sundaram.PAYMENT_GATEWAY_LINK_ROYAL_SUNDARAM_CV_MOTOR'),
                        //'form_action' => 'https://www.royalsundaram.net/web/test/paymentgateway',
                        'form_method' => 'POST',
                        'payment_type' => 0, // form-submit
                        'form_data' => [
                            'reqType' => 'JSON',
                            'process' => 'paymentOption',
                            "apikey" => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                            "agentId" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                             'premium' => $user_proposal->final_payable_amount,
                             'quoteId' => $user_proposal->unique_proposal_id,
                             'version_no' => $user_proposal->version_no,
                             'strFirstName' => $user_proposal->first_name,
                             'strEmail' => $user_proposal->email,
                            //'version_no' => "12345",
                            //'strFirstName' => $user_proposal->first_name,
                            //'strEmail' => "website.support@royalsundaram.in",
                            'isQuickRenew' => 'No',
                            'crossSellProduct' => '',
                            'crossSellQuoteid' => '',
                            // 'returnUrl' => route('car.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                            'returnUrl' => "https://cv-test.uat.assurecore.io/payment/ROYALSGI/policyCreate/PQ-FOW-0044-2210-ASSUREKIT_HEALTH/ASSUREKIT_HEALTH/",
                            // 'returnUrl' => "https://localhost:8000/car/payment-confirm/royal_sundaram?user_proposal_id=3571",
                            'vehicleSubLine' => 'privatePassengerCar',
                            'elc_value' => (!empty($user_proposal->electrical_accessories) && $user_proposal->electrical_accessories != '0') ? $user_proposal->electrical_accessories : '',
                            'nonelc_value' => (!empty($user_proposal->non_electrical_accessories) && $user_proposal->non_electrical_accessories != '0') ? $user_proposal->non_electrical_accessories : '',
                            'paymentType' => (env('APP_ENV') == 'local') ? 'RazorPay' : 'Billdesk',
                            'BusinessType' => '',
                            'strMobileNo' => $user_proposal->mobile_number
                        ]
                    ];
                    PaymentRequestResponse::insert([
                                    'quote_id' => $quote_log_id,
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'user_proposal_id' => $user_proposal->user_proposal_id,
                                    'ic_id' =>  $user_proposal->ic_id,
                                    'order_id' => $user_proposal->proposal_no,
                                    'amount' => $user_proposal->final_payable_amount,
                                    'payment_url' => 'https://www.royalsundaram.net/web/test/paymentgateway',
                                    'return_url' => route('car.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                                    'xml_data' => json_encode($json_data),
                                    'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                                    'active' => 1,
                                    'created_at' => date('Y-m-d H:i:s'),
                            ]);
    
                    return [
                        'status' => true,
                        'msg' => 'Payment Redirection',
                        'data' => $json_data
                    ];
                } else {
                    if (isset($proposal_response['PREMIUMDETAILS']['Status'])) {
                        return [
                            'status' => false,
                            'msg' => $proposal_response['PREMIUMDETAILS']['Status']['Message']
                        ];
                    } else {
                        return [
                            'status' => false,
                            'msg' => 'Insurer not reachable'
                        ];
                    }
                }

            }
            else
            {
                    updateJourneyStage([
                        'user_product_journey_id' => $user_proposal->user_product_journey_id,
                        'ic_id' => $user_proposal->ic_id,
                        'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
                        'proposal_id' => $user_proposal->user_proposal_id,
                    ]);
    
                    $json_data = [
                        'form_action' => 'https://www.royalsundaram.net/web/test/paymentgateway',
                        'form_method' => 'POST',
                        'payment_type' => 0, // form-submit
                        'form_data' => [
                            'reqType' => 'JSON',
                            'process' => 'paymentOption',
                            "apikey" => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
                            "agentId" => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
                            'premium' => $user_proposal->final_payable_amount,
                            'quoteId' => $user_proposal->unique_proposal_id,
                            'version_no' => ( $CorporateVehiclesQuotesRequest->is_renewal == 'Y' ) ? '12345' :  $user_proposal->version_no,
                            'strFirstName' => $user_proposal->first_name,
                            'strEmail' => $user_proposal->email,
                            'isQuickRenew' => ( $CorporateVehiclesQuotesRequest->is_renewal == 'Y' ) ? 'Yes' : '',
                            'crossSellProduct' => '',
                            'crossSellQuoteid' => '',
                            'returnUrl' => route('car.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                            'vehicleSubLine' => 'privatePassengerCar',
                            'elc_value' => (!empty($user_proposal->electrical_accessories) && $user_proposal->electrical_accessories != '0') ? $user_proposal->electrical_accessories : '',
                            'nonelc_value' => (!empty($user_proposal->non_electrical_accessories) && $user_proposal->non_electrical_accessories != '0') ? $user_proposal->non_electrical_accessories : '',
                            'paymentType' => (env('APP_ENV') == 'local') ? 'RazorPay' : ($CorporateVehiclesQuotesRequest->is_renewal == 'Y' ? 'billdesk' : 'PAYTM'),
                            'BusinessType' => ( $CorporateVehiclesQuotesRequest->is_renewal == 'Y' ) ? 'RN' : '',
                            'strMobileNo' => $user_proposal->mobile_number
                        ]
                    ];

                    PaymentRequestResponse::insert([
                                                    'quote_id' => $quote_log_id,
                                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                                    'user_proposal_id' => $user_proposal->user_proposal_id,
                                                    'ic_id' =>  $user_proposal->ic_id,
                                                    'order_id' => $user_proposal->proposal_no,
                                                    'amount' => $user_proposal->final_payable_amount,
                                                    'payment_url' => 'https://www.royalsundaram.net/web/test/paymentgateway',
                                                    'return_url' => route('car.payment-confirm', ['royal_sundaram', 'user_proposal_id' => $user_proposal->user_proposal_id]),
                                                    'xml_data' => json_encode($json_data),
                                                    'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                                                    'active' => 1,
                                                    'created_at' => date('Y-m-d H:i:s'),
                                                 ]);
                    $premium_type = DB::table('master_premium_type')
                    ->where('id', $productData->premium_type_id)
                    ->pluck('premium_type_code')
                    ->first();
                    $tp_only_breakin = in_array($premium_type, ['third_party_breakin']);

                    if($user_proposal->business_type == 'breakin' && !$tp_only_breakin && $isInspectionWaivedOff == false)
                    {
                        $policy_start_date = date('Y-m-d');
                        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                        UserProposal::where('user_product_journey_id', trim($user_proposal->user_product_journey_id))
                        ->update([
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                        ]);

                    }
    
                    return [
                        'status' => true,
                        'msg' => 'Payment Redirection',
                        'data' => $json_data
                    ];
                } 
            }
         else {
            return [
                'status' => false,
                'msg' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
            ];
        }
    }
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request) 
    {
        $response = $request->All();
        $user_proposal = UserProposal::find($response['user_proposal_id']);
        $quote_log = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->select('quote_id', 'master_policy_id')
            ->first();
        #$return_url = config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL');
        $return_url = paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE');
        $status = false;
        $message = STAGE_NAMES['PAYMENT_FAILED'];
        $policy_no = NULL;
        PaymentRequestResponse::updateOrCreate(['quote_id' => $quote_log->quote_id, 'active' => 1], [
            'response' => json_encode($response),
            'status' => ((isset($response['status']) && $response['status'] == 'success') ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED']),
            //'proposal_no' => ((isset($response['policyNO']) && $response['policyNO'] != '') ? $response['policyNO'] : ''),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        if ($response['status'] == 'success') 
        {
            $policy_no = $response['policyNO'];
            UserProposal::where('user_proposal_id' , $response['user_proposal_id'])->update([
                'policy_no' => $policy_no
            ]);
            $user_proposal->policy_no = $policy_no;
            $generate_pdf = self::generate_pdf($user_proposal)->getOriginalContent();
            $status = $generate_pdf['status'];
            $message = $generate_pdf['msg'];
            #$return_url = config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL');
            $return_url = paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS');
        }

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'ic_id' => $user_proposal->ic_id,
            'stage' => $message
        ]);

        return redirect($return_url);
    }

    public static function generate_pdf($proposal) 
    {       
        $pdf_generate_url = config('constants.IcConstants.royal_sundaram.POLICY_PDF_ROYAL_SUNDARAM_MOTOR') . "?quoteId=" . $proposal->unique_proposal_id . "&type=PurchasedPdf&businessType=NB&force=true&proposerDob=" . $proposal->dob."&expiryDate=".$proposal->policy_end_date;
        $policyNumber = $proposal->policy_no;
        if (empty($policyNumber) || strpos($policyNumber, 'XX') !== false ) {
            $result = self::transactionHistory($proposal->user_product_journey_id, $proposal->unique_proposal_id);
            if ($result['status'] ?? false) {
                $policyNumber = $result['policyNumber'] ?? $policyNumber;
            }
        }
        PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id ], [
            'policy_number'     => $policyNumber,
            //'policy_start_date' => $proposal->policy_start_date,
            'ic_pdf_url'        => $pdf_generate_url,
            //'ncb'               => $proposal->ncb_discount,
            //'premium'           => $proposal->final_payable_amount,
            //'idv'               => $proposal->idv,
            'status'            => 'SUCCESS'
        ]);

        $proposal->policy_no = $policyNumber;
        $proposal->save();

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
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        $status = false;
        $data = [];

        if (!empty($pdf_binary_data)) 
        {
            $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'royal_sundaram/' . md5($proposal->user_proposal_id) . '.pdf';

            try 
            {
                Storage::put($pdf_name, $pdf_binary_data);
                PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id ], [
                    'pdf_url' => $pdf_name,
                ]);
                $data = [
                    'policy_number' => $proposal->policy_no,
                    'pdf_link' => file_url($pdf_name)
                ];
                $status = true;
                $message = STAGE_NAMES['POLICY_ISSUED'];
            } 
            catch (\Throwable $th) 
            {
                // $message = 'Policy Issued, but pdf not generated. Reason : '.$th->getMessage();
            }
        }

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'data' => $data
        ]);
    }

    public static function generatePdf($request) 
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payment_status = PaymentRequestResponse::where([
            'user_product_journey_id' => $user_product_journey_id,
//            'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
//            'active' => 1
        ])->get();
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
                    'section'                       => 'CV'
                ];
                
                $payment_response = self::payment_status((object) $payment_status_data);
                
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
    
    //Passing request as object
    public static function payment_status($request) {
       
        $user_product_journey_id = $request->enquiryId;
        //$proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $quoteId = $request->order_id;
        $quote_log = QuoteLog::where('user_product_journey_id', $user_product_journey_id)
            ->select('quote_id', 'master_policy_id')
            ->first();
        $productData = getProductDataByIc($quote_log->master_policy_id);
        $transaction_status_array = [
            'api_key' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
            'agent_id' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
            'quote_id' => $quoteId
        ];
        $section = (isset($request->section) && $request->section != null) ? $request->section : 'Car';
        $get_response = getWsData(config('constants.IcConstants.royal_sundaram.CHECK_TRANSACTION_STATUS_ROYAL_SUNDARAM_MOTOR'), $transaction_status_array, 'royal_sundaram', [
            'enquiryId'         => $user_product_journey_id,
            'requestMethod'     => 'post',
            'productName'       => $productData->product_name,
            'company'           => 'royal_sundaram',
            'section'           => $section,
            'method'            => 'Check Transaction Status',
            'transaction_type'  => 'proposal',
            'root_tag'          => 'TransactionCheckRequest',
        ]);
        $data = $get_response['response'];
        return $data;
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
            'agentId' => config('constants.IcConstants.royal_sundaram.AGENTID_ROYAL_SUNDARAM_CV_MOTOR'),
            'apiKey' => config('constants.IcConstants.royal_sundaram.APIKEY_ROYAL_SUNDARAM_CV_MOTOR'),
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
            'section' => 'CV',
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
                    $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'royal_sundaram/' . md5($proposal->user_proposal_id) . '.pdf';
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

    public static function transactionHistory($enquiryId, $quoteId)
    {
        try {
            $url = config('constants.IcConstants.royal_sundaram.TRANSACTION_HISTORY_URL').$quoteId;
            $getResponse = getWsData($url, '', 'royal_sundaram', [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'get',
                'productName'       => '',
                'company'           => 'royal_sundaram',
                'section'           => 'cv',
                'method'            => 'Check Transaction Status',
                'transaction_type'  => 'proposal',
                'type'              => 'transactionHistoryByQuote'
            ]);
            $data = $getResponse['response'];
            if (empty($data)) {
                return [
                    'status' => false,
                    'msg' => 'Issue in transaction history service',
                ];
            }
            $data = json_decode($data, true);
            if (!empty($data['data']['policyNumber'] ?? '')) {
                return [
                    'status' => true,
                    'policyNumber' => $data['data']['policyNumber']
                ];
            }
            return [
                'status' => false,
                'msg' => 'Policy number not found',
            ];
        } catch (\Throwable $th) {
            return [
                'status' => false,
                'msg' => $th->getMessage(),
            ];
        }
    }
}
