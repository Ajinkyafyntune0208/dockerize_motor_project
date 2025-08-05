<?php

namespace App\Http\Controllers\Payment\Services\Bike\V1;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\CorporateVehiclesQuotesRequest;


include_once app_path() . '/Helpers/BikeWebServiceHelper.php';



class FutureGeneraliPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('quote_id')->first();

        $return_data = [
            'form_action' => config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_GATEWAY_LINK_MOTOR'),
            'form_method' => 'POST',
            'payment_type' => config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_TYPE', 0),
            'form_data' => [
                'PaymentOption' => config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_OPTION', 3),
                'TransactionID' => $proposal->unique_proposal_id,
                'ResponseURL' => route('bike.payment-confirm', ['future_generali', 'enquiry_id' => $enquiryId]),
                'ProposalNumber' => $proposal->proposal_no,
                'PremiumAmount' => ($proposal->final_payable_amount),
                'UserIdentifier' => 'NA',
                'UserId' => 'NA',
                'FirstName' => $proposal->first_name,
                'LastName' => !empty($proposal->last_name) ? $proposal->last_name :'.',
                'Mobile' => $proposal->mobile_number,
                'Email' => $proposal->email,
            ]
        ];
       
        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
              ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $proposal->user_proposal_id,
            'amount'                    => ($proposal->final_payable_amount),
            'ic_id'                     => '28',
            'payment_url'               => config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_GATEWAY_LINK_MOTOR'),
            'proposal_no'               => $proposal->proposal_no,
            'order_id'                  => $proposal->proposal_no,
            'return_url'                => route('bike.payment-confirm', ['future_generali', 'enquiry_id' => $enquiryId]),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($return_data)
        ]);

        $data['user_product_journey_id'] = $proposal->user_product_journey_id;
        $data['ic_id'] = $proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function confirm($request)
    {
        
        $WS_P_ID = $request->WS_P_ID;
        $TID = $request->TID;
        $PGID = $request->PGID;
        $Premium = $request->Premium;
        $Response = $request->Response;
        $enqId = $request->enquiry_id;
        if(($request->Response ?? '') == 'Success' && !empty($enqId))
        {
            updateJourneyStage([
                'user_product_journey_id' => $enqId,
                'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
        }else{
            $return_url =  config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_FAILURE_CALLBACK_URL');
            try{
                $return_url = paymentSuccessFailureCallbackUrl($enqId,'BIKE','FAILURE');
                //$return_url = config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enqId)]);
            }catch(\Exception $e)
            {
                \Illuminate\Support\Facades\Log::error("Future generali return url issue ".\Illuminate\Support\Facades\URL::current());
            }
            return redirect($return_url);
        }

        if((!isset($request->TID) || empty($request->TID)))
        {
            return redirect(paymentSuccessFailureCallbackUrl($enqId,'BIKE','SUCCESS'));
            //return redirect(config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_SUCCESS_CALLBACK_URL'). '?' . http_build_query(['enquiry_id' => customEncrypt($enqId)]));
        }
        $proposal = UserProposal::where('user_product_journey_id', $enqId)->first();
        $master_policy_id = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);
        if ($Response == 'UserCancelled') 
        {
            return redirect(paymentSuccessFailureCallbackUrl($enqId,'BIKE','FAILURE'));
            //return redirect(config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enqId)]));
        }

        $pg_response = [
                    'WS_P_ID' => $WS_P_ID,
                    'TID' => $TID,
                    'PGID' => $PGID,
                    'Premium' => $Premium,
                    'Response' => $Response,
                ];
        PaymentRequestResponse::where('proposal_no', $TID)
            ->where('user_product_journey_id', $proposal->user_product_journey_id)
            ->update([
                "status" => STAGE_NAMES['PAYMENT_SUCCESS'],
                "response" => json_encode([
                    'ws_p_id' => $WS_P_ID,
                    'TID' => $TID,
                    'PGID' => $PGID,
                    'premium' => $Premium,
                    'response' => $Response,
                ])
            ]);
        if ($Response == 'Success' && $Premium !== 0) 
        {
                
            $policy_response = self:: generatePolicyNumber($pg_response, $proposal->user_product_journey_id);
            $policy_response = (array)$policy_response;
            updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
            if($policy_response['status'] == 'true' && $policy_response['policy_no'] != '' && $policy_response['policy_no'] != 'NULL')
            {
                $policy_no = $policy_response['policy_no'];
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                $pdf_array = [
                                'tem:PolicyNumber' => $policy_no,
                                'tem:UserID'       => config('IC.FUTURE_GENERALI.V1.BIKE.PDF_USER_ID'), //'webagg02',
                                'tem:Password'     => config('IC.FUTURE_GENERALI.V1.BIKE.PDF_PASSWORD') //'webagg02@123',
                            ];
                $additional_data = 
                [
                'requestMethod' => 'post',
                'enquiryId' => $proposal->user_product_journey_id,
                'soap_action'  => 'GetPDF',
                'container'    => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'method' => 'Generate Policy',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name
                ];

                $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.Pdf_LINK_MOTOR'), $pdf_array, 'future_generali', $additional_data);
                $data = $get_response['response'];

                if ($data) 
                {
                    $pdf_output = preg_replace('/<\\?xml .*\\?>/i', '', $data);
                    libxml_use_internal_errors(TRUE);
                    $pdf_output =  XmlToArray::convert(remove_xml_namespace($pdf_output));
                    
                    if (isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['0']))
                    {
                         foreach ($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF'] as $key => $value) 
                         {
                            $pdf_data = array_search_key('PDFBytes',$value);
                         }
                    }

                    elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['PDFBytes']))
                    {
                        $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['PDFBytes'];
                    }
                    elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['TCS_x0020_Document']['PDFBytes']))
                    {
                        $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['TCS_x0020_Document']['PDFBytes'];
                    }
                    /* elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['XMLFile']))
                    {
                        $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['XMLFile'];
                    } */
                    if (isset($pdf_data)) 
                    {
                        updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);
                        $pdf_name = config('IC.FUTURE_GENERALI.V1.BIKE.PROPOSAL_PDF_URL') . 'future_generali/' . $proposal->user_product_journey_id . '.pdf';
                        Storage::put($pdf_name, base64_decode($pdf_data));
                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'pdf_url' => $pdf_name
                            ]);
                    }
                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'));
                    //return redirect(config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                } 
                else 
                {
                    return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'));
                    //return redirect(config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }

            }
            else
            {
                $policy_response['message_desc'] = 'Payment is successful but some issue occurred in policy generation';
                
                //return $policy_response;
                return redirect(paymentSuccessFailureCallbackUrl($proposal->user_product_journey_id,'BIKE','SUCCESS'));
                //return redirect(config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));

            }
        
        }
        else
        {

            PaymentRequestResponse::where('user_product_journey_id',$proposal->user_product_journey_id)
                        ->update([
                            "status" => STAGE_NAMES['PAYMENT_FAILED'],
                            "response" => $request->All()
                        ]);
            updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['PAYMENT_FAILED']]
            );
            return redirect(paymentSuccessFailureCallbackUrl($request->enquiry_id,'BIKE','FAILURE'));
            //return redirect(config('IC.FUTURE_GENERALI.V1.BIKE.PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($request->enquiry_id)]));

        }

            
    }
    
    static public  function generatePolicyNumber($pg_response, $user_product_journey_id)
    {
        $WS_P_ID = $pg_response['WS_P_ID'];
        $TID = $pg_response['TID'];
        $PGID = $pg_response['PGID'];
        $Premium = $pg_response['Premium'];
        $Response = $pg_response['Response'];

        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" : "F";
        $requestData = getQuotation($proposal->user_product_journey_id);
        $master_policy_id = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);
        $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $enquiryId = $proposal->user_product_journey_id;
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }

        $IsPos = 'N';
        $is_FG_pos_disabled = config('constants.motorConstant.IS_FG_POS_DISABLED');
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
        $PanCardNo = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code

        $rto_data = DB::table('future_generali_rto_master')
                ->where('rta_code', strtr($rto_code, ['-' => '']))
                ->first();

        if ($Response == 'Success' && $Premium !== 0) 
        {
            if ($requestData->is_renewal == 'Y' && $requestData->rollover_renewal != 'Y' && app('env') == 'local') {
                $TransDate =DB::table('payment_request_response')->select(DB::raw('DATE_FORMAT(created_at,"%d/%m/%Y") AS TransactionDate'))->where('user_product_journey_id', $enquiryId)->get();
                $renewal_creat_req = [
                    "PolicyNo" => $proposal->previous_policy_number,
                    "VendorCode" => config('IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_VENDOR_CODE'),
                    "ExpiryDate" => str_replace('-', '/', $proposal->prev_policy_expiry_date), //"01/03/2023",
                    "RegistrationNo" => "",
                    "QuotationNo" => $proposal->proposal_no, //"Q000002906",
                    "CKYCNo" => $proposal->ckyc_number,
                    "CKYCRefNo" => $proposal->ckyc_reference_id, //"PR_EK3CN1NDMI",
                    "Receipt" => [
                        "UniqueTranKey" => $WS_P_ID, //"ABCD1692313220",
                        "CheckType" => "",
                        "BSBCode" => "",
                        "TransactionDate" => $TransDate[0]->TransactionDate,
                        "ReceiptType" => "IVR",
                        "Amount" => ($proposal->final_payable_amount),
                        "TCSAmount" => "",
                        "TranRefNo" => $PGID, //"33423223234363",
                        "TranRefNoDate" => date('d/m/Y'),
                    ],
                ];
                $url = config('IC.FUTURE_GENERALI.V1.BIKE.RENEWAL_CREATE_POLICY_DETAILS');
                $get_response = getWsData($url, $renewal_creat_req, 'future_generali', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Renewal Create Policy Details',
                    'requestMethod' => 'post',
                    'enquiryId' => $proposal->user_product_journey_id,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ]
                ]);
                $policy_data_response = $get_response['response'];
                if ($policy_data_response) {
                    $result = XmlToArray::convert($policy_data_response);
                    if (isset($result['Policy']['Status'])) {
                        if ($result['Policy']['Status'] == 'Success') {
                            $client_id = isset($result['Client']) ? $result['Client'] : '0';
                            $policy_no = isset($result['PolicyNumber']) ? $result['PolicyNumber'] : '';

                            $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'policy_no' => $policy_no
                                ]);

                            PolicyDetails::updateOrCreate(
                                [
                                    'proposal_id' => $proposal->user_proposal_id,
                                ],
                                [
                                    'policy_number' => $policy_no,
                                    'idv' => '',
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'ncb' => null,
                                    'premium' => ($proposal->final_payable_amount),

                                ]
                            );
                            return [
                                'status' => 'true',
                                'message' => 'successful',
                                'policy_no' => $policy_no
                            ];
                        } else {
                            $client_id = isset($result['Client']) ? $result['Client'] : '0';
                            $policy_no = '';

                            $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'policy_no' => $policy_no,
                                    'unique_quote' => $client_id
                                ]);

                            return [
                                'status' => 'false',
                                'message' => 'Issue in policy generation service',
                                'policy_no' => $policy_no
                            ];
                        }
                    } else {
                        return
                            [
                                'status' => 'false',
                                'message' => 'Something went wrong',
                                'policy_no' => ''
                            ];
                    }
                }
            }
            $mmv = get_mmv_details($productData,$requestData->version_id,'future_generali');

            if($mmv['status'] == 1)
            {
            $mmv = $mmv['data'];
            }
            else
            {
                return  [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }

            $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

            // bike age calculation
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
            $bike_age = ceil($age / 12);
            $usedCar = 'N'; 

            
            if ($requestData->business_type == 'newbusiness')
            {
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
                $claimMadeinPreviousPolicy = 'N';
                $ncb_declaration = 'N';
                $NewBike = 'Y';
                $rollover = 'N';
                $policy_start_date = date('d/m/Y');
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                $PolicyNo = $insurer  = $previous_insurer_name = $prev_ic_address1 = $prev_ic_address2 = $prev_ic_pincode = $PreviousPolExpDt = $prev_policy_number = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date = '';
                $contract_type = 'F15';
                $risk_type = 'F15';
                $reg_no = '';
                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                {
                    if($pos_data)
                    {
                        $IsPos = 'Y';
                        $PanCardNo = $pos_data->pan_no;
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }

                    if($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $PanCardNo = 'ABGTY8890Z';
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }

                    if(config('FUTURE_GENERALI_IS_NON_POS') == 'Y')
                    {
                        $IsPos = 'N';
                        $PanCardNo  = '';
                        $contract_type = 'F15';
                        $risk_type = 'F15';
                    }


                }
                elseif($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }

            }
            else
            {
                if($requestData->previous_policy_type == 'Not sure')
                {
                    $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                    
                }
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
                $claimMadeinPreviousPolicy = $requestData->is_claim;
                $ncb_declaration = 'N';
                $NewBike = 'N';
                $rollover = 'Y';

                if($requestData->previous_policy_type != 'Not sure')
                {
                    $previous_insure_name = DB::table('future_generali_prev_insurer')
                                        ->where('insurer_id', $proposal->previous_insurance_company)->first();
                    $previous_insurer_name = $previous_insure_name->name;
                    $ClientCode = $proposal->previous_insurance_company;
                    $PreviousPolExpDt = date('d/m/Y', strtotime($corporate_vehicle_quotes_request->previous_policy_expiry_date));
                    $prev_policy_number = $proposal->previous_policy_number;

                    $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
                    $insurer = keysToLower($insurer);
                    $prev_ic_address1 = $insurer->address_line_1;
                    $prev_ic_address2 = $insurer->address_line_2;
                    $prev_ic_pincode = $insurer->pin;
                }
            
                $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';

                $registration_number = $reg_no;
                $registration_number = explode('-', $registration_number);

                if ($registration_number[0] == 'DL') {
                    $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true); 
                    $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3];
                } else {
                    $registration_number = $reg_no;
                }

                $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

                if($date_diff > 90)
                {
                    $bike_expired_more_than_90_days = 'Y';
                }
                else
                {
                    $bike_expired_more_than_90_days = 'N';
                 
                }

                if ($claimMadeinPreviousPolicy == 'N' && $bike_expired_more_than_90_days == 'N' && $premium_type != 'third_party') 
                {
                    $ncb_declaration = 'Y';
                    $motor_no_claim_bonus = $requestData->previous_ncb;
                    $motor_applicable_ncb = $requestData->applicable_ncb;
                }
                else
                {
                    $ncb_declaration = 'N';
                    $motor_no_claim_bonus = '0';
                    $motor_applicable_ncb = '0';
                }

                if($claimMadeinPreviousPolicy == 'Y' && $premium_type != 'third_party') {
                    $motor_no_claim_bonus = $requestData->previous_ncb;
                }

                $today_date =date('Y-m-d'); 
                if(new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
                {
                    $policy_start_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                }
                else if(new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date))
                {
                   $policy_start_date = date('d/m/Y', strtotime("+2 day")); 
                }
                else
                {
                    $policy_start_date = date('d/m/Y', strtotime("+1 day")); 
                }

                if($requestData->previous_policy_type == 'Not sure')
                {
                    $policy_start_date = date('d/m/Y', strtotime("+2 day"));
                    $usedCar = 'Y'; 
                    $rollover = 'N';       
                }

                $policy_end_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));

                if($requestData->previous_policy_type == 'Third-party')
                {
                    $ncb_declaration = 'N';
                    $motor_no_claim_bonus = '0';
                    $motor_applicable_ncb = '0';
                }

                

                if($premium_type== "own_damage")
                {
                   $contract_type = 'TWO';
                   $risk_type = 'FVO';

                }
                else
                {   
                    $contract_type = 'FTW';
                    $risk_type = 'FTW';
                }

                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('FUTURE_GENERALI_IS_NON_POS') != 'Y')
                {
                    if($pos_data)
                    {
                        $IsPos = 'Y';
                        $PanCardNo = $pos_data->pan_no;
                        if($premium_type== "own_damage")
                        {
                           $contract_type = 'PWO';
                           $risk_type = 'TWO';

                        }
                        else
                        {   
                            $contract_type = 'PTW';
                            $risk_type = 'FTW';
                        }
                        if($requestData->business_type == 'newbusiness')
                        {
                            $contract_type = 'P15';
                            $risk_type = 'F15';
                        }
                    }

                    if($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $PanCardNo = 'ABGTY8890Z';
                        if($premium_type== "own_damage")
                        {
                           $contract_type = 'PWO';
                           $risk_type = 'TWO';

                        }
                        else
                        {   
                            $contract_type = 'PTW';
                            $risk_type = 'FTW';
                        }
                    }

                }
                elseif($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                }
              
            }
            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('FUTURE_GENERALI_IS_NON_POS') != 'Y')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }
                }
            }

            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

            $IsElectricalItemFitted = 'false';
            $ElectricalItemsTotalSI = 0;
            $IsNonElectricalItemFitted = 'false';
            $NonElectricalItemsTotalSI = 0;
            $bifuel = 'false';

            if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
                $accessories = ($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if ($value['name'] == 'Electrical Accessories') {
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value['sumInsured'];
                    } else if ($value['name'] == 'Non-Electrical Accessories') {
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value['sumInsured'];
                    } else if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $type_of_fuel = '5';
                        $bifuel = 'true';
                        $Fueltype = 'CNG';
                        $BiFuelKitSi = $value['sumInsured'];
                    }
                }
            }

            //PA for un named passenger
            $IsPAToUnnamedPassengerCovered = 'false';
            $PAToUnNamedPassenger_IsChecked = '';
            $PAToUnNamedPassenger_NoOfItems = '';
            $PAToUnNamedPassengerSI = 0;
            $IsLLPaidDriver = '0';

            if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
                $additional_covers = $selected_addons->additional_covers;
                foreach ($additional_covers as $value) {
                    if ($value['name'] == 'Unnamed Passenger PA Cover') {
                        $IsPAToUnnamedPassengerCovered = 'true';
                        $PAToUnNamedPassenger_IsChecked = 'true';
                        $PAToUnNamedPassenger_NoOfItems = '1';
                        $PAToUnNamedPassengerSI = $value['sumInsured'];
                    }
                    if ($value['name'] == 'LL paid driver') {
                        $IsLLPaidDriver = '1';
                    }
                }
            }

            $IsAntiTheftDiscount = 'false';

            if ($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '') {
                $discount = $selected_addons->discount;
                foreach ($discount as $value) {
                    if ($value['name'] == 'anti-theft device') {
                        $IsAntiTheftDiscount = 'true';
                    }
                }
            }

            if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
            {
                $accessories = ($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if($value['name'] == 'Electrical Accessories')
                    {
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'Non-Electrical Accessories')
                    {
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value['sumInsured'];
                    }
            
                }
            }

            
           
            

               
            /*$addon_req = 'N';
          
            $addon = [];
            if(!empty($selected_addons['addons']))
            {
                foreach ($selected_addons['addons'] as $key => $data) {
                    if ($data['name'] == 'Zero Depreciation' && ($bike_age <= 5)) {
                        $addon_req = 'Y';
                        $addon[] = [
                            'CoverCode' => 'ZODEP'
                        ];
                    }
                    if ($data['name'] == 'Road Side Assistance' && ($bike_age <= 3) ) {
                        $addon_req = 'Y';
                        $addon[] = [
                            'CoverCode' => 'RODSA'
                        ];
                    }
                    if ($data['name'] == 'Consumable' && ($bike_age <= 3) ) {
                        $addon_req = 'Y';
                        $addon[] = [
                            'CoverCode' => 'CONSM'
                        ];
                    }
                }
            }*/
        
            $cpa_selected = false;
            $cpa_year = '';
            if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
                $addons = $selected_addons->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if(isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident'))
                    {
                        $cpa_selected = true;
                        if($requestData->business_type == 'newbusiness')
                        {
                            $cpa_year = isset($value['tenure'])? (string) $value['tenure'] :'1';
                        }
                    }
                 }
            }

            if($requestData->vehicle_owner_type == "I") 
            {
                if ($proposal->gender == "M" || strtolower($proposal->gender) == "male") 
                {
                    $salutation = 'MR';
                }
                else
                {
                    $salutation = 'MS';
                }
            }
            else
            {
                $salutation = '';
            }


            if ($requestData->vehicle_owner_type == 'I' && $cpa_selected == true && $premium_type != "own_damage" && $premium_type != "own_damage_breakin") 
            {
                $CPAReq = 'Y';
                $cpa_nom_name = $proposal->nominee_name;
                $cpa_nom_age = $proposal->nominee_age;
                $cpa_nom_age_det = 'Y';
                $cpa_nom_perc = '100';
                $cpa_relation = $proposal->nominee_relationship;
                $cpa_appointee_name = '';
                $cpa_appointe_rel = '';
                /* if ($requestData->business_type == 'newbusiness')
                {
                    $cpa_year = '5';
                }
                else
                {
                    $cpa_year = '';
                } */
            } else {
                $CPAReq = 'N';
                $cpa_nom_name = '';
                $cpa_nom_age = '';
                $cpa_nom_age_det = '';
                $cpa_nom_perc = '';
                $cpa_relation = '';
                $cpa_appointee_name = '';
                $cpa_appointe_rel = '';
                $cpa_year = '';
            }

            $previous_tp_insurer_code = '';
            $additional_data = $proposal->additonal_data;
            switch($premium_type)
            {
                case "comprehensive":
                    $cover_type = "CO";
                    break;
                case "own_damage":
                    $cover_type = "OD";
                    $previous_tp_insurer_code = DB::table('future_generali_previous_tp_insurer_master')
                    ->select('tp_insurer_code')
                    ->where('client_code', $proposal->tp_insurance_company)->first()->tp_insurer_code;
                    break;

                case "third_party":
                    $cover_type = "LO";
                    break;
            }

            $covercodes = [];
            $add_on_details = json_decode($proposal->product_code);
            $ads = json_decode($add_on_details->Addon);
            
            foreach ($ads as $key => $value) 
            {  
                if(!empty($value))
                {
                   array_push($covercodes,(array)$value); 
                }
                else
                {
                    $covercodes['CoverCode'] = '';
                }
            }

            $proposal->address_line1 = str_replace([', ', '. '], [',', '.'], $proposal->address_line1);
            $proposal->address_line1 = str_replace([',', '.'], [', ', '. '], $proposal->address_line1);
            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 30,
                'address_2_limit'   => 30,
                'address_3_limit'   => 30,
                'address_4_limit'   => 30
            ];
            $getAddress = getAddress($address_data);

            $fuelTypes = [
                'petrol' => 'P',
                'diesel' => 'D',
                'cng' => 'C',
                'lpg' => 'L',
                'electric' => 'E',
            ];

            $fuelType = $fuelTypes[strtolower($requestData->fuel_type)] ?? '';
            $mmv = get_mmv_details($productData, $requestData->version_id, 'future_generali');
            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
                $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
                if (isset($fuelTypes[strtolower($mmv_data->fuel_code)])) {
                    $fuelType = $fuelTypes[strtolower($mmv_data->fuel_code)];
                }
            }
          
            
            $proposal_array = json_decode($proposal->additional_details_data, true);
            $TransDate =DB::table('payment_request_response')->select(DB::raw('DATE_FORMAT(created_at,"%d/%m/%Y") AS TransactionDate'))->where('user_product_journey_id', $enquiryId)->get();
            $quote_array = [
                '@attributes'  => [
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ],
                'Uid'          => time(),
                'VendorCode'   => config('IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE'),
                'VendorUserId' =>  config('IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE'),
                'PolicyHeader' => [
                    'PolicyStartDate' => $proposal_array["PolicyHeader"]["PolicyStartDate"],
                    'PolicyEndDate'   => $proposal_array["PolicyHeader"]["PolicyEndDate"],
                    'AgentCode'       => config('IC.FUTURE_GENERALI.V1.BIKE.AGENT_CODE'),
                    'BranchCode'      => ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.BIKE.BRANCH_CODE'),
                    'MajorClass'      => 'MOT',
                    'ContractType'    => $contract_type,
                    'METHOD'          => 'CRT',
                    'PolicyIssueType' => 'I',
                    'PolicyNo'        => '',
                    'ClientID'        => '',
                    'ReceiptNo'       => '',
                ],
                'POS_MISP'     => [
                    'Type'  => ($IsPos == 'Y') ? 'P' : '',
                    'PanNo' => ($IsPos == 'Y') ? $PanCardNo : '',
                ],
                'Client'       => [
                    'ClientType'    => $requestData->vehicle_owner_type,
                    'CreationType'  => 'C',
                    'Salutation'    => $salutation,
                    'FirstName'     => $proposal->first_name,
                    'LastName'      => $proposal->last_name,
                    'DOB'           => date('d/m/Y', strtotime($proposal->dob)),
                    'Gender'        => $proposal->gender,
                    'MaritalStatus' => $proposal->marital_status == 'Single' ? 'S' : ($proposal->marital_status == 'Married' ? 'M' : ''),
                    'Occupation'    => $requestData->vehicle_owner_type == 'C' ? 'OTHR' : $proposal->occupation,
                    'PANNo'         => isset($proposal->pan_number) ? $proposal->pan_number : '',
                    'GSTIN'         => isset($proposal->gst_number) ? $proposal->gst_number : '',
                    'AadharNo'      => '',
                    'EIANo'         => '',
                    'CKYCNo'        => $proposal->ckyc_number,
                    'CKYCRefNo'     => $proposal->ckyc_reference_id, //CKYCRefNo will be passed after payment
                    
                    'Address1'      => [
                        'AddrLine1'   => trim($proposal_array["Client"]["Address1"]["AddrLine1"]),
                        'AddrLine2'   => trim($proposal_array["Client"]["Address1"]["AddrLine2"]) != '' ? trim($proposal_array["Client"]["Address1"]["AddrLine2"]) : '..',
                        'AddrLine3'   => trim($proposal_array["Client"]["Address1"]["AddrLine3"]),
                        'Landmark'    => trim($proposal_array["Client"]["Address1"]["Landmark"]),
                        'Pincode'     => $proposal->pincode,
                        'City'        => $proposal->city,
                        'State'       => $proposal->state,
                        'Country'     => 'IND',
                        'AddressType' => 'R',
                        'HomeTelNo'   => '',
                        'OfficeTelNo' => '',
                        'FAXNO'       => '',
                        'MobileNo'    => $proposal->mobile_number,
                        'EmailAddr'   => $proposal->email,
                    ],
                    'Address2'      => [
                        'AddrLine1'   => '',
                        'AddrLine2'   => '',
                        'AddrLine3'   => '',
                        'Landmark'    => '',
                        'Pincode'     => '',
                        'City'        => '',
                        'State'       => '',
                        'Country'     => '',
                        'AddressType' => '',
                        'HomeTelNo'   => '',
                        'OfficeTelNo' => '',
                        'FAXNO'       => '',
                        'MobileNo'    => $proposal->mobile_number,
                        'EmailAddr'   => $proposal->email,
                    ],
                ],
                'Receipt'      => [
                    'UniqueTranKey'   => $WS_P_ID,
                    'CheckType'       => '',
                    'BSBCode'         => '',
                    'TransactionDate' => date('d/m/Y'),
                    'ReceiptType'     => 'IVR',
                    'Amount'          => ($proposal->final_payable_amount),
                    'TCSAmount'       => '',
                    'TranRefNo'       => $PGID,
                    'TranRefNoDate'   => $TransDate[0]->TransactionDate,
                ],
                'Risk'         => [
                    'RiskType'          => $risk_type,
                    'Zone'              => $rto_data->zone,
                    'Cover'             => $cover_type,
                    'Vehicle'           => [
                        'TypeOfVehicle'           => '',
                        'VehicleClass'            => '',
                        'RTOCode'                 => str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code, true)),
                        'Make'                    => $mmv_data->make,
                        'ModelCode'               => $mmv_data->vehicle_model_code,
                        'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : str_replace('-', '', $registration_number),
                        'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                        'FuelType'                => $fuelType,
                        'CNGOrLPG'                => [
                            'InbuiltKit'    =>  in_array($fuelType, ['C', 'L']) ? 'Y' : 'N',
                            'IVDOfCNGOrLPG' =>  $bifuel == 'true' ? $BiFuelKitSi : '',
                        ],
                        'BodyType'                => '',
                        'EngineNo'                => isset($proposal->engine_number) ? $proposal->engine_number : '',
                        'ChassiNo'                => isset($proposal->chassis_number) ? $proposal->chassis_number : '',
                        'CubicCapacity'           => $mmv_data->cc,
                        'SeatingCapacity'         => $mmv_data->seating_capacity,
                        'IDV'                     => $master_policy_id->idv,
                        'GrossWeigh'              => '',
                        'CarriageCapacityFlag'    => '',
                        'ValidPUC'                => 'Y',
                        'TrailerTowedBy'          => '',
                        'TrailerRegNo'            => '',
                        'NoOfTrailer'             => '',
                        'TrailerValLimPaxIDVDays' => '',
                    ],
                    'InterestParty'     => [
                        'Code'     => $proposal->is_vehicle_finance == '1' ? 'HY' : '',
                        'BankName' => $proposal->is_vehicle_finance == '1' ? strtoupper($proposal->name_of_financer) : '',
                    ],
                    'AdditionalBenefit' => [
                        'Discount'                                 => $proposal->discount_percent,
                        'ElectricalAccessoriesValues'              => $IsElectricalItemFitted == 'true' ? $ElectricalItemsTotalSI : '',
                        'NonElectricalAccessoriesValues'           => $IsNonElectricalItemFitted == 'true' ? $NonElectricalItemsTotalSI : '',
                        'FibreGlassTank'                           => '',
                        'GeographicalArea'                         => '',
                        'PACoverForUnnamedPassengers'              => $IsPAToUnnamedPassengerCovered == 'true' ? $PAToUnNamedPassengerSI : '',
                        'LegalLiabilitytoPaidDriver'               => $IsLLPaidDriver,
                        'LegalLiabilityForOtherEmployees'          => '',
                        'LegalLiabilityForNonFarePayingPassengers' => '',
                        'UseForHandicap'                           => '',
                        'AntiThiefDevice'                          => '',
                        'NCB'                                      => $motor_applicable_ncb,
                        'RestrictedTPPD'                           => '',
                        'PrivateCommercialUsage'                   => '',
                        'CPAYear' => $cpa_year,
                        'IMT23'                                    => '',
                        'CPAReq'                                   => $CPAReq,
                        'CPA'                                      => [
                            'CPANomName'       => $cpa_nom_name,
                            'CPANomAge'        => $cpa_nom_age,
                            'CPANomAgeDet'     => $cpa_nom_age_det,
                            'CPANomPerc'       => $cpa_nom_perc,
                            'CPARelation'      => $cpa_relation,
                            'CPAAppointeeName' => $cpa_appointee_name,
                            'CPAAppointeRel'   => $cpa_appointe_rel
                        ],
                        'NPAReq'              => 'N',
                        'NPA'                 => [
                            'NPAName'         => '',
                            'NPALimit'        => '',
                            'NPANomName'      => '',
                            'NPANomAge'       => '',
                            'NPANomAgeDet'    => '',
                            'NPARel'          => '',
                            'NPAAppinteeName' => '',
                            'NPAAppinteeRel'  => '',
                        ],
                    ],
                    'AddonReq'          => $add_on_details->AddonReq,
                    
                    'Addon'             => $covercodes,
                    'PreviousTPInsDtls' => [
                    'PreviousInsurer' => ($premium_type == 'own_damage') ? $previous_tp_insurer_code: '',
                    'TPPolicyNumber' => ($premium_type == 'own_damage') ? $proposal->tp_insurance_number: '',
                    'TPPolicyEffdate' => ($premium_type == 'own_damage') ? date('d/m/Y', strtotime($proposal->tp_start_date)) : '',
                    'TPPolicyExpiryDate' => ($premium_type == 'own_damage') ? date('d/m/Y', strtotime($proposal->tp_end_date)) : ''

                     ],

                    'PreviousInsDtls'   => [
                        'UsedCar'        => $usedCar,
                        'UsedCarList'    => [
                            'PurchaseDate'    => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '',
                            'InspectionRptNo' => '',
                            'InspectionDt'    => '',
                        ],
                        'RollOver'       => $rollover,
                        'RollOverList'   => [
                            'PolicyNo'              => ($rollover == 'N') ? '' :$prev_policy_number,
                            'InsuredName'           => ($rollover == 'N') ? '' :$previous_insurer_name,
                            'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                            'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                            'Address1'              => ($rollover == 'N') ? '' :$prev_ic_address1,
                            'Address2'              => ($rollover == 'N') ? '' :$prev_ic_address2,
                            'Address3'              => '',
                            'Address4'              => '',
                            'Address5'              => '',
                            'PinCode'               => ($rollover == 'N') ? '' :$prev_ic_pincode,
                            'InspectionRptNo'       => '',
                            'InspectionDt'          => '',
                            'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                            'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                            'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$motor_no_claim_bonus,
                        ],
                        'NewVehicle'     => $NewBike,
                        'NewVehicleList' => [
                            'InspectionRptNo' => '',
                            'InspectionDt'    => '',
                        ],
                    ],
                ],
            ];

            if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                $quote_array['Risk']['PreviousTPInsDtls']['PreviousInsurer'] = '';
                $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyNumber'] = '';
                $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyEffdate'] = '';
                $quote_array['Risk']['PreviousTPInsDtls']['TPPolicyExpiryDate'] = '';
            }
            
            $additional_data = [
                'requestMethod' => 'post',
                'enquiryId' => $proposal->user_product_journey_id,
                'soap_action' => 'CreatePolicy',
                'container' => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
                'method' => 'Proposal Generation',
                'section' => 'bike',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name
            ];
            
         
           
            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
            $data = $get_response['response'];
      
            
            if ($data) 
            {
                $quote_output = html_entity_decode($data);
                /*$doc = @simplexml_load_string($quote_output);
                if (!$doc) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Future Generali is unable to process your request at this moment. Please contact support.'
                    ]);
                } */ //under obs for IC return data validation in XML
                $quote_output = @XmlToArray::convert($quote_output);

                if(!$quote_output)
                {
                    return [
                        'status' => false,
                        'msg' => 'Future Generali is unable to process your request at this moment. Please contact support.',
                        'message' => 'Future Generali is unable to process your request at this moment. Please contact support.',
                        'policy_no' => ''
                    ];
                }
                $result = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root'] ?? '';

                if(empty($result))
                {
                    return [
                        'status' => false,
                        'msg' => 'Future Generali is unable to process your request at this moment. Please contact support.',
                        'message' => 'Future Generali is unable to process your request at this moment. Please contact support.',
                        'policy_no' => ''
                    ];
                }

                if (isset($result['Status']) && strtolower($result['Status']) == 'fail') 
                {
                    if (isset($result['Error'])) 
                    {
                        $contains = Str::contains($result['Error'], [
                            'has been already created with the same Chassis Number in our system.',
                            'has been already created with the same Chassis Number Expiring on'
                        ]);
                        if ($contains) {
                            $policy_number = explode("'", $result['Error']);

                            UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                    ->where('user_proposal_id',$proposal->user_proposal_id)
                                    ->update([
                                        'policy_no'             => $policy_number[1]
                                    ]);

                            PolicyDetails::updateOrCreate(
                                [
                                    'proposal_id' => $proposal->user_proposal_id,
                                ],
                                [
                                    'policy_number' => $policy_number[1],
                                    'idv' => '',
                                    'policy_start_date' => $proposal->policy_start_date,
                                    'ncb' => null,
                                    'premium' => ($proposal->final_payable_amount),

                                ]
                            );
                            
                            return [
                                'status' => 'true',
                                'message' => 'successful',
                                'policy_no' => $policy_number[1]
                            ];

                        }
                    }
                    elseif(isset($result['Root']['Policy']['ErrorMessage']))
                    {
                        return [
                            'status' => 'false',
                            'message' => $result['Root']['Policy']['ErrorMessage'],
                            'policy_no' => ''
                        ];
                    }
                }

                if (isset($result['Policy']['Status'])) 
                {
                
                    if(strtolower($result['Policy']['Status']) == 'successful')
                    {
                        $client_id = isset($result['client']['clientid']) ? $result['client']['clientid'] : '0';
                        $policy_no = isset($result['Policy']['PolicyNo']) ? $result['Policy']['PolicyNo'] : '';
                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';

                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                        ->where('user_proposal_id',$proposal->user_proposal_id)
                                        ->update([
                                            'policy_no'             => $policy_no
                           
                                        ]);

                        PolicyDetails::updateOrCreate(
                            [
                                'proposal_id' => $proposal->user_proposal_id,
                            ],
                            [
                                'policy_number' => $policy_no,
                                'idv' => '',
                                'policy_start_date' => $proposal->policy_start_date,
                                'ncb' => null,
                                'premium' => ($proposal->final_payable_amount),

                            ]
                            );

                        return [
                                'status' => 'true',
                                'message' => 'successful',
                                'policy_no' => $policy_no
                            ];
                    }
                    else
                    {
                        $client_id = isset($result['client']['clientid']) ? $result['client']['clientid'] : '0';
                        $policy_no = isset($result['Policy']['PolicyNo']) ? $result['Policy']['PolicyNo'] : '';
                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';
                    if(!empty($policy_no))
                    {
                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id',$proposal->user_proposal_id)
                            ->update([
                                'policy_no'             => $policy_no,
                                'unique_quote'          => $client_id,
                                'pol_sys_id'    => $receipt_no
                            ]);

                            PolicyDetails::updateOrCreate(
                                [
                                    'proposal_id' => $proposal->user_proposal_id,
                                ],
                                [
                                    'policy_number' => $policy_no,

                                ]
                               );
                    }
                        $bank_error_txt = 'SR289-BANKDESC03 :00001 E048Duplicate found';
                        $bank_error = '';
                        $client_error = '';
                        $error_txt = '';


                        if(isset($result['Client']['Status']) && strtolower($result['Client']['Status']) == 'fail')
                        {
                            if(isset($result['Client']['ErrorMessage']))
                            {
                                $client_error = $result['Client']['ErrorMessage'];
                            }
                            elseif(isset($result['Client']['Message']))
                            {
                                $client_error = $result['Client']['Message'];
                            }
                        }
                        elseif(isset($result['Receipt']['Status']) && strtolower($result['Receipt']['Status']) == 'fail')
                        {
                            if(isset($result['Receipt']['ErrorMessage']))
                            {
                                $bank_error = $result['Receipt']['ErrorMessage'];
                            }
                            elseif(isset($result['Receipt']['Message']))
                            {
                                $bank_error = $result['Receipt']['Message'];
                            }
                        }
                        else
                        {
                            if(isset($result['Error']))
                            {
                                $error_txt = $result['Error'];
                            }
                            if(isset($result['ErrorMessage']))
                            {
                                $error_txt = $result['ErrorMessage'];
                            }
                            if(isset($result['Policy']['ErrorMessage']))
                            {
                                $error_txt = $result['Policy']['ErrorMessage'];
                            }
                            elseif(isset($result['Policy']['Message']))
                            {
                                $error_txt = $result['Policy']['Message'];
                            }

                        }
                        
                        
                        
                        $error_msg_found = strpos($bank_error, $bank_error_txt);
                        
                        if($error_msg_found == true)
                        {
                            $quote_array['Receipt']['UniqueTranKey'] = $PGID;
                            $quote_array['Receipt']['TranRefNo'] = $WS_P_ID;
                            $quote_array['PolicyHeader']['ClientID'] =  $client_id;
                            $quote_array['PolicyHeader']['ReceiptNo'] = $receipt_no;

                            $additional_data = [
                                'requestMethod' => 'post',
                                'enquiryId' => $proposal->user_product_journey_id,
                                'soap_action' => 'CreatePolicy',
                                'container' => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
                                'method' => 'Proposal Generation2',
                                'section' => 'bike',
                                'transaction_type' => 'proposal',
                            ];
                            
                           
                            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
                            $data = $get_response['response'];
                            if ($data) 
                            {
                                $quote_output = html_entity_decode($data);
                                $quote_output = XmlToArray::convert($quote_output);

                                $result = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root'];

                                if (isset($result['Policy']['Status'])) 
                                {

                                    if(strtolower($result['Policy']['Status']) == 'successful')
                                    {

                                        $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                                        $policy_no = isset($result['Policy']['PolicyNo']) ? $result['Policy']['PolicyNo'] : '';
                                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';

                                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                                        ->where('user_proposal_id',$proposal->user_proposal_id)
                                                        ->update([
                                                            'policy_no'             => $policy_no
                                                        ]);

                                        PolicyDetails::updateOrCreate(
                                        [
                                            'proposal_id' => $proposal->user_proposal_id,
                                        ],
                                        [
                                            'policy_number' => $policy_no,
                                            'idv' => '',
                                            'policy_start_date' => $proposal->policy_start_date,
                                            'ncb' => null,
                                            'premium' => ($proposal->final_payable_amount),

                                        ]
                                       );

                                        return [
                                            'status' => 'true',
                                            'message' => 'successful',
                                            'policy_no' => $policy_no
                                        ];

                                    }
                                    else
                                    {
                                        $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                                        $policy_no = isset($result['Policy']['PolicyNo']) ? $result['Policy']['PolicyNo'] : '';
                                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';

                                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                                        ->where('user_proposal_id',$proposal->user_proposal_id)
                                                        ->update([
                                                            'policy_no'             => $policy_no,
                                                            'unique_quote'          => $client_id,
                                                            'unique_proposal_id'    => $receipt_no
                                                        ]);
                                        PolicyDetails::updateOrCreate(
                                            [
                                                'proposal_id' => $proposal->user_proposal_id,
                                            ],
                                            [
                                                'policy_number' => $policy_no
    
                                            ]
                                            );

                                        return [
                                            'status' => 'false',
                                            'message' => 'Issue in policy generation service',
                                            'policy_no' => $policy_no
                                        ];

                                    }
                                }
                                else
                                {
                                    return [
                                    'status' => 'false',
                                    'message' => 'Something went wrong',
                                    'policy_no' => ''
                                   ];
                                }
                            }
                            else
                            {
                                
                                return [
                                    'status' => 'false',
                                    'message' => 'Something went wrong',
                                    'policy_no' => ''
                                ];
                            }
                            
                        }
                        else
                        {
                            $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                            $policy_no = '';
                            $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';
                            $error_txt = $result['ErrorMessage'] ?? '';
                            return [
                                'status' => 'false',
                                'message' => $error_txt,
                                'policy_no' => ''
                            ];
                        }


                    }

                }
                else
                {
                    return [
                        'status' => 'false',
                        'message' => 'Something went wrong',
                        'policy_no' => ''
                   ];
                }
                
            } 
            else 
            {
                return [
                    'status' => 'false',
                    'message' => 'Something went wrong',
                    'policy_no' => ''
                ];
            }
        }
        else
        {
            return [
                    'status' => 'false',
                    'message' => 'Something went wrong',
                    'policy_no' => ''
                ];

        }
        
        
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        if ((env('APP_ENV') != 'local')) {
            $payment_status_check = self::payment_status_check($user_product_journey_id);
            if (!$payment_status_check['status']) {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'Payment is pending'
                ];
                return response()->json($pdf_response_data);
            }
        }
        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            ->where(['prr.active' => 1, 'prr.status' => STAGE_NAMES['PAYMENT_SUCCESS']])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','prr.order_id','prr.response'
            )
            ->first();

        if($policy_details == null)
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'No Data Found'
            ];
            return response()->json($pdf_response_data);
        }
        
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if(empty($policy_details->policy_number) || $policy_details->policy_number == 'NULL')
        {
            $payment_response = json_decode($policy_details->response);
            $payment_response = (object) array_change_key_case(json_decode($policy_details->response,1));
            $pg_response = [
                'WS_P_ID' =>  $payment_response->ws_p_id,
                'TID' => $payment_response->tid,
                'PGID' => $payment_response->pgid,
                'Premium' =>$payment_response->premium,
                'Response' =>$payment_response->response,
            ];
            $policy_response = self::generatePolicyNumber($pg_response, $proposal->user_product_journey_id);
            $policy_response = (array)$policy_response;
            if(!empty($policy_response['policy_no']) && $policy_response['policy_no'] != 'NULL')
            {
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                $policy_details->policy_number = $policy_response['policy_no'];
            } else {
                return response()->json([
                    'status' => false,
                    'msg'    => 'Policy Number Generation API : ' . ($policy_response['message'] ?? 'Something went wrong.')
                ]);
            }
        }

        if(empty($policy_details->pdf_url) && !empty($policy_details->policy_number))
        {
            $pdf_array = [
                'tem:PolicyNumber' => $policy_details->policy_number,
                'tem:UserID'       => config('IC.FUTURE_GENERALI.V1.BIKE.PDF_USER_ID'), //'webagg02',
                'tem:Password'     => config('IC.FUTURE_GENERALI.V1.BIKE.PDF_PASSWORD') //'webagg02@123',
            ];
            
            $additional_data = 
            [
            'requestMethod' => 'post',
            'enquiryId' => $proposal->user_product_journey_id,
            'soap_action'  => 'GetPDF',
            'container'    => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
            'method' => 'Generate Policy',
            'section' => 'bike',
            'transaction_type' => 'proposal',
            ];

            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.PDF_LINK_MOTOR'), $pdf_array, 'future_generali', $additional_data);
            $data = $get_response['response'];
           

            if ($data) 
            {
                $pdf_output = preg_replace('/<\\?xml .*\\?>/i', '', $data);
                libxml_use_internal_errors(TRUE);
                $pdf_output =  XmlToArray::convert(remove_xml_namespace($pdf_output));
                
                if (isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['0']))
                {
                     foreach ($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF'] as $key => $value) 
                     {
                        $pdf_data = array_search_key('PDFBytes',$value);
                     }
                }

                elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['PDFBytes']))
                {
                    $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['PDFBytes'];
                }
                
                /* elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['XMLFile']))
                {
                    $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['XMLFile'];
                } */
               
                if (isset($pdf_data)) 
                {
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);

                    $pdf_name = config('IC.FUTURE_GENERALI.V1.BIKE.PROPOSAL_PDF_URL') . 'future_generali/' . $proposal->user_product_journey_id . '.pdf';
                    Storage::put($pdf_name, base64_decode($pdf_data));
                    PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'pdf_url' => $pdf_name
                        ]);

                        $pdf_response_data = [
                        'status' => true,
                        'msg' => 'success',
                        'data' => [
                            'policy_number' => $policy_details->policy_number,
                            'pdf_link'      => file_url($pdf_name)
                        ]
                    ];
                }
                else
                {
                   $pdf_response_data = [
                    'status' => false,
                    'dev'    => 'Error Occured',
                    'msg'    => isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['Msg']) ? $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['Msg'] : 'Issue in PDF Generation service'
                    ];

                }
                
            } 
            else 
            {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'No response received from PDF Generation service'
                    ];
            }
        
        } 
        else 
        {
            $pdf_response_data = [
                'status' => true,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link' => file_url(config('IC.FUTURE_GENERALI.V1.BIKE.PROPOSAL_PDF_URL') . 'future_generali/'. $proposal->user_product_journey_id . '.pdf'),
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

    static public function ReconService($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $incomplete_transactions = DB::table('payment_request_response as prr')
            //->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where(array('prr.user_product_journey_id'=>$user_product_journey_id,'prr.active'=>1))
            ->whereIn(strtolower('prr.status'),[ STAGE_NAMES['PAYMENT_INITIATED'],'Failed','Fail','UserCancelled',''])
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'prr.order_id','prr.user_product_journey_id'
            )
            ->first();
        if($incomplete_transactions)
        {
            $proposal = UserProposal::where('user_product_journey_id', $incomplete_transactions->user_product_journey_id)->first();
            $quote_array = [
                    'transactionId' =>$incomplete_transactions->proposal_no,//'1628145500',
                    'source' => 'WebAggregator'
                ];
                            

            $additional_data = [
                'requestMethod' => 'get',
                'enquiryId' => $incomplete_transactions->user_product_journey_id,
                'soap_action' => '',
                'container' => '',
                'method' => 'Recon_Fetch_Trns',
                'section' => 'bike',
                'transaction_type' => 'proposal',
            ];
            
           
            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.MOTOR_CHECK_TRN_STATUS'), $quote_array, 'future_generali', $additional_data);
            $status_data = $get_response['response'];
     
            if($status_data)
            {
                $status_data = array_change_key_case_recursive(XmlToArray::convert($status_data));
                if(isset($status_data['listquickpayfields']['quickpayfield']))
                {

                    $status_data = $status_data['listquickpayfields']['quickpayfield'];
                
                    if(isset($status_data[0]))
                    {
                        
                        foreach ($status_data as $key => $value) 
                        {
                            if($value['transactionstatus'] != '')
                            {
                                $proper_status_data = $status_data[$key];
                            }
                        }
                        $status_data = $proper_status_data;
                    }

                    PaymentRequestResponse::where('proposal_no',$incomplete_transactions->proposal_no)
                        ->where('user_product_journey_id', $incomplete_transactions->user_product_journey_id)
                        ->update([
                            "status" => $status_data['transactionstatus'],
                            "response" => json_encode([
                                'ws_p_id' => $status_data['fg_transaction_id'],
                                'TID' => $status_data['transactionid'],
                                'PGID' => $status_data['pgtransactionid'],
                                'premium' => $status_data['paymentamount'],
                                'response' => $status_data['transactionstatus'],
                            ])
                        ]);
                    
                    $pg_response = [
                            'WS_P_ID' =>  $status_data['fg_transaction_id'],
                            'TID' => $status_data['transactionid'],
                            'PGID' => $status_data['pgtransactionid'],
                            'Premium' =>$status_data['paymentamount'],
                            'Response' =>$status_data['transactionstatus'],
                        ];

                    $policy_response = self:: generatePolicyNumber($pg_response, $proposal->user_product_journey_id);
                    $policy_response = (array)$policy_response;

                    if($policy_response['status'] == 'true' && $policy_response['policy_no'] != '' && $policy_response['policy_no'] != 'NULL')
                    {
                        $policy_no = $policy_response['policy_no'];
                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);

                        $pdf_array = [
                                        'tem:PolicyNumber' => $policy_no,
                                        'tem:UserID'       => config('IC.FUTURE_GENERALI.V1.BIKE.PDF_USER_ID'), //'webagg02',
                                        'tem:Password'     => config('IC.FUTURE_GENERALI.V1.BIKE.PDF_PASSWORD') //'webagg02@123',
                                    ];
                        $additional_data = 
                        [
                        'requestMethod' => 'post',
                        'enquiryId' => $proposal->user_product_journey_id,
                        'soap_action'  => 'GetPDF',
                        'container'    => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                        'method' => 'Generate Policy',
                        'section' => 'bike',
                        'transaction_type' => 'proposal',
                        ];

                        $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.PDF_LINK_MOTOR'), $pdf_array, 'future_generali', $additional_data);
                        $data = $get_response['response'];

                        if ($data) 
                        {
                            $pdf_output = preg_replace('/<\\?xml .*\\?>/i', '', $data);
                            libxml_use_internal_errors(TRUE);
                            $pdf_output =  XmlToArray::convert(remove_xml_namespace($pdf_output));
                            
                            if (isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['0']))
                            {
                                 foreach ($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF'] as $key => $value) 
                                 {
                                    $pdf_data = array_search_key('PDFBytes',$value);
                                 }
                            }

                            elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['PDFBytes']))
                            {
                                $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['PDFBytes'];
                            }

                            elseif(isset($pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['XMLFile']))
                            {
                                $pdf_data = $pdf_output['Body']['GetPDFResponse']['GetPDFResult']['diffgram']['DocumentElement']['PDF']['XMLFile'];
                            }
                            
                            if (isset($pdf_data)) 
                            {
                                updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);
                                $pdf_name = config('IC.FUTURE_GENERALI.V1.BIKE.PROPOSAL_PDF_URL') . 'future_generali/' . $proposal->user_product_journey_id . '.pdf';
                                Storage::put($pdf_name, base64_decode($pdf_data));
                                PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'pdf_url' => $pdf_name
                                    ]);
                                return [
                                            'status' => 'true',
                                            'message' => 'Policy Issued successfully',
                                            'policy_no' => $policy_no
                                        ];
                            }
                            else
                            {
                                return [
                                            'status' => 'true',
                                            'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                            'policy_no' => $policy_no
                                        ];

                            }
                            
                        } 
                        else 
                        {
                            return [
                                        'status' => 'true',
                                        'message' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                        'policy_no' => $policy_no
                                    ];
                            
                        }

                    }
                    else
                    {
                        return $policy_response;
                        
                    }

                }
                else
                {
                    return
                    [
                        'status' => 'false',
                        'message' => 'Issue in recon API or invalid proposal no.'
                    ];
                }
            }
            else
            {
                return
                    [
                        'status' => 'false',
                        'message' => 'Issue in recon API'
                    ];

            }

        }
        else
        {
            return [
                'status' => 'false',
                'message' => 'No incomplete transaction found'
            ];
        }       

    }
    static public function payment_status_check($enquiry_id)
    {
        $get_payment_details = PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
            ->select('order_id', 'id', 'user_proposal_id')
            ->get();
        if ($get_payment_details->isEmpty()) {
            return [
                'status' => false
            ];
        }
        foreach ($get_payment_details as $value) {
            if (empty($value->order_id)) {
                continue;
            }

            $request_array = [
                "soap:Body" => [
                    "FetchTRNDetails" => [
                        "_attributes" => [
                            'xmlns' => 'http://tempuri.org/'
                        ],
                        "transactionId" => $value->order_id,
                        "source" => "webaggregator" #it's for payu
                    ]
                ]
            ];
            $root = [
                'rootElementName' => 'soap:Envelope',
                '_attributes' => [
                    "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                    "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
                    "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/"
                ],
            ];

            $additional_data = [
                'requestMethod' => 'post',
                'enquiryId' => $enquiry_id,
                'soap_action'  => '',
                'headers' =>  [
                    'SOAPAction' => 'http://tempuri.org/FetchTRNDetails',
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ],
                'method' => 'fg_Recorn_service',
                'section' => 'bike',
                'transaction_type' => 'proposal',
            ];
            #config('IC.FUTURE_GENERALI.V1.BIKE.IC.FUTURE_GENERALI.V1.BIKE.MOTOR_CHECK_TRN_STATUS')
            $recorn_payment_status_check = ArrayToXml::convert($request_array, $root, false, 'utf-8');
            $get_response = getWsData(config(' IC.FUTURE_GENERALI.V1.BIKE.MOTOR_CHECK_TRN_STATUS'), $recorn_payment_status_check, 'future_generali', $additional_data);
            $payment_data = $get_response['response'];
            if ($payment_data) {
                $status_data = XmlToArray::convert($payment_data);
                if (isset($status_data['soap:Body']['FetchTRNDetailsResponse']['FetchTRNDetailsResult']['listQuickPayFields']['QuickPayField'])) {

                    $payment_status_data = $status_data['soap:Body']['FetchTRNDetailsResponse']['FetchTRNDetailsResult']['listQuickPayFields']['QuickPayField'];
                    if (isset($payment_status_data[0])) {
                        foreach ($payment_status_data as $key => $row) {
                            if (isset($row['TransactionStatus']) &&  $row['TransactionStatus'] == 'Success') {

                                $pg_response = [
                                    'WS_P_ID' =>  $row['FG_Transaction_ID'],
                                    'TID'     =>  $row['TransactionId'],
                                    'PGID'    =>  $row['PGTransactionID'],
                                    'Premium' =>  $row['PaymentAmount'],
                                    'Response' => $row['TransactionStatus']
                                ];
                                PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                                    ->update([
                                        'active'  => 0
                                    ]);

                                PaymentRequestResponse::where('id', $value->id)
                                    ->update([
                                        'response'      => json_encode($pg_response),
                                        'updated_at'    => date('Y-m-d H:i:s'),
                                        'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                                        'active'        => 1
                                    ]);

                                $data['user_product_journey_id']    = $enquiry_id;
                                $data['proposal_id']                = $value->user_proposal_id;
                                $data['ic_id']                      = '28';
                                $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                                updateJourneyStage($data);
                                return [
                                    'status' => true,
                                    'msg' => 'success'
                                ];
                            }
                        }
                    } else {
                        if (isset($payment_status_data['TransactionStatus']) &&  $payment_status_data['TransactionStatus'] == 'Success') {

                            $pg_response = [
                                'WS_P_ID' =>  $payment_status_data['FG_Transaction_ID'],
                                'TID'     =>  $payment_status_data['TransactionId'],
                                'PGID'    =>  $payment_status_data['PGTransactionID'],
                                'Premium' =>  $payment_status_data['PaymentAmount'],
                                'Response' =>  $payment_status_data['TransactionStatus']
                            ];

                            PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                                ->update([
                                    'active'  => 0
                                ]);

                            PaymentRequestResponse::where('id', $value->id)
                                ->update([
                                    'response'      => json_encode($pg_response),
                                    'updated_at'    => date('Y-m-d H:i:s'),
                                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                                    'active'        => 1
                                ]);

                            $data['user_product_journey_id']    = $enquiry_id;
                            $data['proposal_id']                = $value->user_proposal_id;
                            $data['ic_id']                      = '28';
                            $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                            updateJourneyStage($data);
                            return [
                                'status' => true,
                                'msg' => 'success'
                            ];
                        }
                    }
                }
            }
        }
        return [
            'status' => false
        ];
    }
}
