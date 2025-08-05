<?php

namespace App\Http\Controllers\Payment\Services\Car\V1;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\CorporateVehiclesQuotesRequest;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';


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
            'form_action' => config('IC.FUTURE_GENERALI.V1.CAR.PAYMENT_GATEWAY_LINK_MOTOR'),
            'form_method' => 'POST',
            'payment_type' => config('IC.FUTURE_GENERALI.V1.CAR.PAYMENT_TYPE', 0),
            'form_data' => [
                'PaymentOption' => config('IC.FUTURE_GENERALI.V1.CAR.PAYMENT_OPTION', 3),
                'TransactionID' => $proposal->unique_proposal_id,
                'ResponseURL' => route('car.payment-confirm', ['future_generali','enquiry_id' => $enquiryId]),
                'ProposalNumber' => $proposal->proposal_no,
                'PremiumAmount' => (int) round($proposal->final_payable_amount),
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
            'amount'                    => (int) round($proposal->final_payable_amount),
            'ic_id'                     => '28',
            'payment_url'               => config('IC.FUTURE_GENERALI.V1.CAR.PAYMENT_GATEWAY_LINK_MOTOR'),
            'proposal_no'               => $proposal->proposal_no,
            'order_id'                  => $proposal->proposal_no,
            'return_url'                => route('car.payment-confirm', ['future_generali', 'enquiry_id' => $enquiryId]),
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
            $return_url =  config('IC.FUTURE_GENERALI.V1.CAR.PAYMENT_FAILURE_CALLBACK_URL');
            try{
                $enquiryId = $enqId;
                $return_url = paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE');
                //$return_url = config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enqId)]);
            }catch(\Exception $e)
            {
                \Illuminate\Support\Facades\Log::error("Future generali return url issue ".\Illuminate\Support\Facades\URL::current());
            }
            return redirect($return_url);
        }

        if(!isset($request->TID) || empty($request->TID))
        {
            $enquiryId = $enqId;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
            //return redirect(config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_SUCCESS_CALLBACK_URL'). '?' . http_build_query(['enquiry_id' => customEncrypt($enqId)]));
        }
        $proposal = UserProposal::where('user_product_journey_id', $enqId)->first();
        $master_policy_id = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)
            ->first();
        $productData = getProductDataByIc($master_policy_id->master_policy_id);
        if ($Response == 'UserCancelled')
        {
            $enquiryId = $enqId;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
            //return redirect(config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enqId)]));
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
                    'stage' => STAGE_NAMES['PAYMENT_SUCCESS'],
                ]);

            if($policy_response['status'] == 'true' && $policy_response['policy_no'] != '' && $policy_response['policy_no'] != 'NULL')
            {
                $policy_no = $policy_response['policy_no'];
                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);

                $UserProductJourney = UserProductJourney::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
                $corporate_service ='N';
        
                if(!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IC.FUTURE_GENERALI.V1.CAR.IS_ENABLED_AFFINITY') == 'Y')
                {
                        $corporate_service = 'Y';
                }

                if($corporate_service !== 'Y')
                {
                    $UserID = config('IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID');
                    $Password = config('IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD');
                }else
                {
                    $UserID = config('IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID_CORPORATE');
                    $Password = config('IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD_CORPORATE');
                }

                $pdf_array = [
                                'tem:PolicyNumber' => $policy_no,
                                'tem:UserID'       => $UserID, //'webagg02',
                                'tem:Password'     => $Password //'webagg02@123',
                            ];
                $additional_data =
                [
                'requestMethod' => 'post',
                'enquiryId' => $proposal->user_product_journey_id,
                'soap_action'  => 'GetPDF',
                'container'    => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'method' => 'Generate Policy',
                'section' => 'car',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name
                ];

                $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.PDF_LINK_MOTOR'), $pdf_array, 'future_generali', $additional_data);
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

                    if (isset($pdf_data))
                    {
                        updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);
                        $pdf_name = config('IC.FUTURE_GENERALI.V1.CAR.PROPOSAL_PDF_URL') . 'future_generali/' . $proposal->user_product_journey_id . '.pdf';
                        Storage::put($pdf_name, base64_decode($pdf_data));
                        PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'pdf_url' => $pdf_name
                            ]);
                    }
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                    //return redirect(config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }
                else
                {
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                    //return redirect(config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
                }

            }
            else
            {
                $policy_response['message_desc'] = 'Payment is successful but some issue occurred in policy generation';
                Log::error('Future Generali Policy Generation Error for journey ID : ' . $proposal->user_product_journey_id . ' and error message is as follows : ' . json_encode($policy_response));
                //return $policy_response;
                $enquiryId = $proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                //return redirect(config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($proposal->user_product_journey_id)]));
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
            $enquiryId = customDecrypt($request->enquiry_id);
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
            //return redirect(config('IC.FUTURE_GENERALI.V1.CAR.CAR_PAYMENT_FAILURE_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($request->enquiry_id)]));

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
        $premium_type_code = $premium_type;
        $IsPos = 'N';
        $is_FG_pos_disabled = config('IC.FUTURE_GENERALI.V1.CAR.IS_FG_POS_DISABLED');
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('IC.FUTURE_GENERALI.V1.CAR.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('IC.FUTURE_GENERALI.V1.CAR.IS_POS_TESTING_MODE_ENABLE');
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

        $is_breakin = 'N';
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
            $is_breakin = 'Y';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
            $is_breakin = 'N';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
            $is_breakin = 'Y';
        }
        
        if($requestData->ownership_changed == 'Y')
        {
            if(!in_array($premium_type,['third_party','third_party_breakin']))
            {
               $is_breakin = 'Y';
            }
        }

        if($is_breakin == 'Y')
        {
            $breakinDetails = DB::table('cv_breakin_status')
                        ->where('cv_breakin_status.user_proposal_id', '=', trim($proposal->user_proposal_id))
                        ->first();
            
            if (empty($breakinDetails)) {
                return  [
                    'status' => false,
                    'message' => 'Breakin details not found'
                ];
            }

        }

        if ($Response == 'Success' && $Premium !== 0)
        {
             if ($requestData->is_renewal == 'Y' && $requestData->rollover_renewal != 'Y') {
                $TransDate =DB::table('payment_request_response')->select(DB::raw('DATE_FORMAT(created_at,"%d/%m/%Y") AS TransactionDate'))->where('user_product_journey_id', $enquiryId)->get();
                $renewal_creat_req = [
                    "PolicyNo" => $proposal->previous_policy_number,
                    "VendorCode" =>config('IC.FUTURE_GENERALI.V1.CAR.RENEWAL_VENDOR_CODE'),
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
                        "Amount" => (int) round($proposal->final_payable_amount),
                        "TCSAmount" => "",
                        "TranRefNo" => $PGID, //"33423223234363",
                        "TranRefNoDate" => date('d/m/Y'),
                    ],
                ];
                $url = config('IC.FUTURE_GENERALI.V1.CAR.RENEWAL_CREATE_POLICY_DETAILS');
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
                        if (config('IC.FUTURE_GENERALI.V1.CAR.RENEWAL_ENABLED_FOR_CAR') == 'Y') {
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
                                        'premium' => (int) round($proposal->final_payable_amount),

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
                        } elseif (strtolower($result['Policy']['Status']) == 'successful') {

                            $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                            $policy_no = isset($result['Policy']['PolicyNo']) ? $result['Policy']['PolicyNo'] : '';
                            $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';
                    
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
                                    'premium' => (int) round($proposal->final_payable_amount),
                    
                                ]
                            );
                    
                            return [
                                'status' => 'true',
                                'message' => 'successful',
                                'policy_no' => $policy_no
                            ];
                        } else {
                            $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                            $policy_no = '';
                            $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';
                    
                            $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                ->where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'policy_no' => $policy_no,
                                    'unique_quote' => $client_id,
                                    'unique_proposal_id' => $receipt_no
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

            // car age calculation
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
            $car_age = $age / 12;
            $usedCar = 'N';

            $addon = [];

            if ($requestData->business_type == 'newbusiness')
            {
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
                $claimMadeinPreviousPolicy = 'N';
                $ncb_declaration = 'N';
                $NewCar = 'Y';
                $rollover = 'N';
                $policy_start_date = date('d/m/Y');
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                $PolicyNo = $insurer  = $previous_insurer_name = $prev_ic_address1 = $prev_ic_address2 = $prev_ic_pincode = $PreviousPolExpDt = $prev_policy_number = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date = '';
                $contract_type = 'F13';
                $risk_type = 'F13';
                $reg_no = '';
                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                {
                    if($pos_data)
                    {
                        $IsPos = 'Y';
                        $PanCardNo = $pos_data->pan_no;
                        $contract_type = 'P13';
                        $risk_type = 'F13';
                    }

                    if($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $PanCardNo = 'ABGTY8890Z';
                        $contract_type = 'P13';
                        $risk_type = 'F13';
                    }
                    if(config('IC.FUTURE_GENERALI.V1.CAR.IS_NON_POS') == 'Y')
                    {
                        $IsPos = 'N';
                        $PanCardNo = '';
                        $contract_type = 'F13';
                        $risk_type = 'F13';
                    }

                }
                elseif($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    $contract_type = 'P13';
                    $risk_type = 'F13';
                }

            }
            else
            {
                if($requestData->previous_policy_type == 'Not sure')
                {
                    $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));

                }
                $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

                if($date_diff > 90)
                {
                   $motor_expired_more_than_90_days = 'Y';
                }
                else
                {
                    $motor_expired_more_than_90_days = 'N';

                }
                $motor_no_claim_bonus = '0';
                $motor_applicable_ncb = '0';
                $claimMadeinPreviousPolicy = $requestData->is_claim;
                $ncb_declaration = 'N';
                $NewCar = 'N';
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

                if ($claimMadeinPreviousPolicy == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party')
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
                    $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                    if(in_array($premium_type_code, ['third_party_breakin'])) {
                        $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                    }
                }
                else
                {
                    $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                    if(in_array($premium_type_code, ['third_party_breakin'])) {
                        $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                    }
                }

                if($requestData->previous_policy_type == 'Not sure')
                {
                    $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                    if(in_array($premium_type_code, ['third_party_breakin'])) {
                        $policy_start_date = date('d/m/Y', strtotime("+3 day"));
                    }
                    $usedCar = 'Y';
                    $rollover = 'N';
                }
                
                if($requestData->ownership_changed == 'Y')
                {
                    if(!in_array($premium_type,['third_party','third_party_breakin']))
                    {
                       $is_breakin = 'Y';
                       $ncb_declaration = 'N';
                       $policy_start_date = date('d/m/Y', strtotime("+1 day"));                   
                       $motor_no_claim_bonus = $requestData->previous_ncb = 0;
                       $motor_applicable_ncb = $requestData->applicable_ncb = 0;
                       $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                       $corporate_vehicle_quotes_request->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                       $PreviousPolExpDt = date('d/m/Y', strtotime($corporate_vehicle_quotes_request->previous_policy_expiry_date));
                    }
                }
            
                if($requestData->business_type != 'newbusiness')
                {
                $policy_end_date = date('d/m/Y', strtotime(date('Y/m/d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
                }

                if($requestData->previous_policy_type == 'Third-party')
                {
                    $ncb_declaration = 'N';
                    $motor_no_claim_bonus = '0';
                    $motor_applicable_ncb = '0';
                }


                if($premium_type== "own_damage")
                {
                 
                   $contract_type = 'FVO';
                   $risk_type = 'FVO';

                }
                else
                {
                    $contract_type = 'FPV';
                    $risk_type = 'FPV';
                }

                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && config('IC.FUTURE_GENERALI.V1.CAR.IS_NON_POS') != 'Y')
                {
                    if($pos_data)
                    {
                        $IsPos = 'Y';
                        $PanCardNo = $pos_data->pan_no;
                        if($premium_type== "own_damage")
                        {
                           $contract_type = 'PVO';
                           $risk_type = 'FVO';

                        }
                        else
                        {
                            $contract_type = 'PPV';
                            $risk_type = 'FPV';
                        }
                        
                        if($requestData->business_type == 'newbusiness')
                        {
                            $contract_type = 'P13';
                            $risk_type = 'F13';
                        }
                    }

                    if($pos_testing_mode === 'Y')
                    {
                        $IsPos = 'Y';
                        $PanCardNo = 'ABGTY8890Z';
                        if($premium_type== "own_damage")
                        {
                           $contract_type = 'PVO';
                           $risk_type = 'FVO';

                        }
                        else
                        {
                            $contract_type = 'PPV';
                            $risk_type = 'FPV';
                        }
                    }

                }
                elseif($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PVO';
                       $risk_type = 'FVO';

                    }
                    else
                    {
                        $contract_type = 'PPV';
                        $risk_type = 'FPV';
                    }
                }


            }
            if($is_pos_enabled == 'Y')
                {
                    if($pos_data)
                    {
                        $IsPos = 'Y';
                        $PanCardNo = $pos_data->pan_no;
                        if($premium_type== "own_damage")
                        {
                           $contract_type = 'PVO';
                           $risk_type = 'FVO';

                        }
                        else
                        {
                            $contract_type = 'PPV';
                            $risk_type = 'FPV';
                        }
                        
                        if($requestData->business_type == 'newbusiness')
                        {
                            $contract_type = 'P13';
                            $risk_type = 'F13';
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

                /*$addon_req = 'Y';
                if($selected_addons && $selected_addons->addons != NULL && $selected_addons->addons != '')
                {
                    $addons = $selected_addons->addons;

                    foreach ($addons as $value) {
                        if (in_array('Road Side Assistance', $value) &&  $productData->product_identifier == 'Basic_with_Addons')
                        {
                            $addon[] = [
                                'CoverCode' => 'STRSA',
                            ];
                        }
                        if (in_array('NCB Protection', $value) &&  $productData->product_identifier == 'Basic_with_Addons')
                        {
                            $addon[] = [
                                'CoverCode' => 'STNCB',
                            ];
                        }

                    }
                }*/

                /*if ($productData->zero_dep == '0') {

                    //zero dep  7 year
                    if ($car_age <=7 && $productData->product_identifier=='zero_dep') {
                      $addon[] = [
                      'CoverCode' => 'STZDP',
                      ];
                  }

                          //zero dep plus consumable 7 year
                          if ($car_age <=7 && $productData->product_identifier=='zero dep plus consumable') {
                          $addon[] = [
                          'CoverCode' => 'ZDCNS',
                          ];
                      }
                           //zero dep plus consumable plus engine 7 year
                           if ($car_age <=7 && $productData->product_identifier=='zero dep plus consumable plus engine') {
                           $addon[] = [
                              'CoverCode' => 'ZDCNE',
                          ];
                      }
                           //zero dep plus consumable plus tyre 5 year
                           if ($car_age <=5 && $productData->product_identifier=='zero dep plus consumable plus tyre') {
                           $addon[] = [
                              'CoverCode' => 'ZDCNT',
                          ];
                      }
                           //zero dep plus consumable plus engine plus tyre 5 year
                           if ($car_age <=5 && $productData->product_identifier=='zero dep plus consumable plus engine plus tyre') {
                           $addon[] = [
                              'CoverCode' => 'ZDCET',
                          ];
                      }

                           //zero dep plus consumable plus engine plus tyre plus RTI 3 year
                           if ($car_age <=3 && $productData->product_identifier=='zero dep plus consumable plus engine plus tyre plus RTI') {
                           $addon[] = [
                              'CoverCode' => 'ZCETR',
                          ];
                      }

                          } else{
                            //standlone rsa  7 year
                        //rsa plus personal belonging plus key loss 7 year
                        if ($car_age <=7 && $productData->product_identifier=='rsa plus personal belonging plus key loss') {
                       $addon[] = [
                           'CoverCode' => 'RSPBK',
                       ];
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
                            $cpa_year = isset($value['tenure'])? (string) $value['tenure'] : '1';
                        }
                    }
                 }
            }
            if ((env('APP_ENV') == 'local') && $requestData->business_type != 'newbusiness' && !in_array($premium_type,['third_party','third_party_breakin'])) {
                //applicable addon for addon declaration logic
                $rsa = $engine_protector = $tyre_protection = $return_to_invoice = $Consumable = $personal_belonging = $key_and_lock_protect = $nilDepreciationCover = $ncb_protction = false;
                if ($selected_addons && !empty($selected_addons->applicable_addons)) {
                    $addons = $selected_addons->applicable_addons;

                    foreach ($addons as $value) {
                        if (in_array('Road Side Assistance', $value)) {
                            $rsa = true;
                        }
                        if (in_array('Engine Protector', $value)) {
                            $engine_protector = true;
                        }
                        if (in_array('Tyre Secure', $value)) {
                            $tyre_protection = true;
                        }
                        if (in_array('Return To Invoice', $value)) {
                            $return_to_invoice = true;
                        }
                        if (in_array('Consumable', $value)) {
                            $Consumable = true;
                        }
                        if (in_array('Loss of Personal Belongings', $value)) {
                            $personal_belonging = true;
                        }
                        if (in_array('Key Replacement', $value)) {
                            $key_and_lock_protect = true;
                        }
                        if (in_array('Zero Depreciation', $value)) {
                            $nilDepreciationCover = true;
                        }
                        if (in_array('NCB Protection', $value)) {
                            $ncb_protction = true;
                        }
                    }
                }

                //checking last addons
                $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsConsumable_Cover = $PreviousPolicy_IsTyre_Cover = $PreviousPolicy_IsEngine_Cover = $PreviousPolicy_IsLpgCng_Cover  = $PreviousPolicy_IsRsa_Cover = $PreviousPolicy_IskeyReplace_Cover = $PreviousPolicy_IsncbProtection_Cover = $PreviousPolicy_IsreturnToInvoice_Cover = $PreviousPolicy_Islopb_Cover = $PreviousPolicy_electricleKit_Cover = $PreviousPolicy_nonElectricleKit_Cover = false;
                if (!empty($proposal->previous_policy_addons_list)) {
                    $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                    foreach ($previous_policy_addons_list as $key => $value) {
                        if ($key == 'zeroDepreciation' && $value) {
                            $PreviousPolicy_IsZeroDept_Cover = true;
                        } else if ($key == 'consumables' && $value) {
                            $PreviousPolicy_IsConsumable_Cover = true;
                        } else if ($key == 'tyreSecure' && $value) {
                            $PreviousPolicy_IsTyre_Cover = true;
                        } else if ($key == 'engineProtector' && $value) {
                            $PreviousPolicy_IsEngine_Cover = true;
                        } else if ($key == 'roadSideAssistance' && $value) {
                            $PreviousPolicy_IsRsa_Cover = true;
                        } else if ($key == 'keyReplace' && $value) {
                            $PreviousPolicy_IskeyReplace_Cover = true;
                        } else if ($key == 'ncbProtection' && $value) {
                            $PreviousPolicy_IsncbProtection_Cover = true;
                        } else if ($key == 'returnToInvoice' && $value) {
                            $PreviousPolicy_IsreturnToInvoice_Cover = true;
                        } else if ($key == 'lopb' && $value) {
                            $PreviousPolicy_Islopb_Cover = true;
                        } else if ($key == 'externalBiKit' && $value) {
                            $PreviousPolicy_IsLpgCng_Cover = true;
                        }else if ($key == 'electricleKit' && $value) {
                            $PreviousPolicy_electricleKit_Cover = true;
                        }
                         else if ($key == 'nonElectricleKit' && $value) {
                            $PreviousPolicy_nonElectricleKit_Cover = true;
                        }
                    }
                }

                #addon declaration logic start 
                if ($nilDepreciationCover && !$PreviousPolicy_IsZeroDept_Cover) {
                    $is_breakin = 'Y';
                }
                if ($rsa && !$PreviousPolicy_IsRsa_Cover) {
                    $is_breakin = 'Y';
                }
                if ($Consumable && !$PreviousPolicy_IsConsumable_Cover) {
                    $is_breakin = 'Y';
                }
                if ($key_and_lock_protect && !$PreviousPolicy_IskeyReplace_Cover) {
                    $is_breakin = 'Y';
                }
                if ($engine_protector && !$PreviousPolicy_IsEngine_Cover) {
                    $is_breakin = 'Y';
                }
                if ($ncb_protction && !$PreviousPolicy_IsncbProtection_Cover) {
                    $is_breakin = 'Y';
                }
                if ($tyre_protection && !$PreviousPolicy_IsTyre_Cover) {
                    $is_breakin = 'Y';
                }
                if ($return_to_invoice && !$PreviousPolicy_IsreturnToInvoice_Cover) {
                    $is_breakin = 'Y';
                }
                if ($personal_belonging && !$PreviousPolicy_Islopb_Cover) {
                    $is_breakin = 'Y';
                }
                if ($bifuel == 'true' && !$PreviousPolicy_IsLpgCng_Cover) {
                    $is_breakin = 'Y';
                }if ($IsElectricalItemFitted == 'true' && !$PreviousPolicy_electricleKit_Cover) {
                    $is_breakin = 'Y';
                }
                if ($IsNonElectricalItemFitted == 'true' && !$PreviousPolicy_nonElectricleKit_Cover) {
                    $is_breakin = 'Y';
                }
                //end addon declaration
            }
            if($requestData->vehicle_owner_type == "I")
            {
                if ($proposal->gender == "M")
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


            if ($requestData->vehicle_owner_type == 'I' && $cpa_selected == true && $premium_type != "own_damage")
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
                    $cpa_year = '3';
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

            $UserProductJourney = UserProductJourney::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            
            $corporate_service ='N';
            if(!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IC.FUTURE_GENERAL.V1.CAR.IS_ENABLED_AFFINITY') == 'Y')
            {
                    $corporate_service = 'Y';
            }

            if($corporate_service !== 'Y')
            {
                $VendorCode = config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE');
                $VendorUserId =config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE') ;
                $AgentCode = config('IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE') ;
                $BranchCode = ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE');
            }else
            {
                $VendorCode = config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE_CORPORATE');
                $VendorUserId =config('IC.FUTURE_GENERALI.V1.CAR.VENDOR_CODE_CORPORATE') ;
                $AgentCode = config('IC.FUTURE_GENERALI.V1.CAR.AGENT_CODE_CORPORATE') ;
                $BranchCode = ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.CAR.BRANCH_CODE_CORPORATE');
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
            $TransDate =DB::table('payment_request_response')->select(DB::raw('DATE_FORMAT(created_at,"%d/%m/%Y") AS TransactionDate'))->where('user_product_journey_id', $enquiryId)->get();
           
            $policy_start_date = !empty($proposal->policy_start_date) ? date('d/m/Y', strtotime($proposal->policy_start_date)) : $policy_start_date;
            $policy_end_date = !empty($proposal->policy_end_date) ? date('d/m/Y', strtotime($proposal->policy_end_date)) : $policy_end_date;
            $quote_array = [
                '@attributes'  => [
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ],
                'Uid'          => time(),#$TID
                'VendorCode'   => $VendorCode,
                'VendorUserId' =>  $VendorUserId,
                'PolicyHeader' => [
                    'PolicyStartDate' => $policy_start_date,
                    'PolicyEndDate'   => $policy_end_date,
                    'AgentCode'       => $AgentCode,
                    'BranchCode'      => $BranchCode,
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
                        'AddrLine1'   => trim($getAddress['address_1']),
                        'AddrLine2'   => trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : '..',
                        'AddrLine3'   => trim($getAddress['address_3']),
                        'Landmark'    => trim($getAddress['address_4']),
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
                    'Amount'          => (int) round($proposal->final_payable_amount),
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
                        'ModelCode'               => $mmv_data->vehicle_code,
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
                    'AddonReq' =>$add_on_details->AddonReq,
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
                            'InspectionRptNo' => ($usedCar == 'Y' && in_array($premium_type_code, ['breakin','own_damage_breakin'])) ?  $breakinDetails->breakin_id : '',
                            'InspectionDt'    => ($usedCar == 'Y' && in_array($premium_type_code, ['breakin','own_damage_breakin'])) ?  date('d/m/Y', strtotime(str_replace('-', '/', $breakinDetails->inspection_date)))  : '',
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
                            'InspectionRptNo'       => ($is_breakin == 'Y' && $usedCar == 'N') ? $breakinDetails->breakin_id : '',#$breakinDetails->breakin_number
                            'InspectionDt'          => ($is_breakin == 'Y' && $usedCar == 'N') ? date('d/m/Y', strtotime(str_replace('-', '/', $breakinDetails->inspection_date))) : '',
                            'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                            'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                            'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$motor_no_claim_bonus,
                        ],
                        'NewVehicle'     => $NewCar,
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
                'section' => 'car',
                'transaction_type' => 'proposal',
                'productName'  => $productData->product_name
            ];
            
            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
            $data = $get_response['response'];
           
            if ($data)
            {
                $quote_output = html_entity_decode($data);
                $quote_output = @XmlToArray::convert($quote_output);
                
                if(!$quote_output)
                {
                    return [
                        'status' => false,
                        'msg' => 'Future Generali is unable to process your request at this moment. Please contact support.',
                        'policy_no' => ''
                    ];
                }

                if(isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Status']) && $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Status'] == 'Fail') {
                    return [
                        'status' => 'false',
                        'message' => $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['ErrorMessage'],
                        'policy_no' => ''
                    ];
                }
                $result = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root'] ?? '';

                if(empty($result))
                {
                    return [
                        'status' => 'false',
                        'msg' => 'Future Generali is unable to process your request at this moment. Please contact support.',
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

                            $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
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
                                    'premium' => (int) round($proposal->final_payable_amount),

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
                        $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                        $policy_no = isset($result['Policy']['PolicyNo']) ? $result['Policy']['PolicyNo'] : '';
                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';

                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                        ->where('user_proposal_id',$proposal->user_proposal_id)
                                        ->update([
                                            'policy_no'             => $policy_no
                                        ]);

                        PolicyDetails::create([
                            'proposal_id' => $proposal->user_proposal_id,
                            'policy_number' => $policy_no,
                            'idv' => '',
                            'policy_start_date' => $proposal->policy_start_date,
                            'ncb' => null,
                            'premium' => (int) round($proposal->final_payable_amount),
                        ]);

                        return [
                                'status' => 'true',
                                'message' => 'successful',
                                'policy_no' => $policy_no
                            ];
                    }
                    else
                    {
                        $client_id = isset($result['Client']['ClientId']) ? $result['Client']['ClientId'] : '0';
                        $policy_no = '';
                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';

                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id',$proposal->user_proposal_id)
                            ->update([
                                'policy_no'             => $policy_no,
                                'unique_quote'          => $client_id,
                                'pol_sys_id'    => $receipt_no
                            ]);



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
                            $quote_array['Uid'] = time();
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
                                'section' => 'car',
                                'transaction_type' => 'proposal',
                                'productName'  => $productData->product_name
                            ];


                            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
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
                                            'premium' => (int) round($proposal->final_payable_amount),

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
                                        $policy_no = '';
                                        $receipt_no = isset($result['Receipt']['ReceiptNo']) ? $result['Receipt']['ReceiptNo'] : '0';

                                        $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                                                        ->where('user_proposal_id',$proposal->user_proposal_id)
                                                        ->update([
                                                            'policy_no'             => $policy_no,
                                                            'unique_quote'          => $client_id,
                                                            'unique_proposal_id'    => $receipt_no
                                                        ]);

                                        return [
                                            'status' => 'false',
                                            'message' => 'Issue in policy generation service',
                                            'policy_no' => $policy_no
                                        ];

                                    }
                                }
                                else
                                {
                                    return
                                    [
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
                            
                            if (empty($error_txt)) {
                                $error_txt = $client_error ?? '';
                            }
                            return [
                                'status' => 'false',
                                'message' => $error_txt ,
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
            $payment_response = (object) array_change_key_case(json_decode($policy_details->response,true));
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
                    'msg'    => 'Policy Number Generation API : ' . ($policy_response['message'] ?? $policy_response['msg'] ?? 'Something went wrong.')
                ]);
            }
        }

        if($policy_details->pdf_url == '' && $policy_details->policy_number != '')
        {
            $UserProductJourney = UserProductJourney::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            $corporate_service ='N';
    
            if(!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IC.FUTURE_GENERALI.V1.CAR.IS_ENABLED_AFFINITY') == 'Y')
            {
                $corporate_service = 'Y';
            }

            if($corporate_service !== 'Y')
            {
                $UserID = config('IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID');
                $Password = config('IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD');
            }else
            {
                $UserID = config('IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID_CORPORATE');
                $Password = config('IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD_CORPORATE');
            }

            $pdf_array = [
                'tem:PolicyNumber' => $policy_details->policy_number,
                'tem:UserID'       => $UserID, //'webagg02',
                'tem:Password'     => $Password //'webagg02@123',
            ];
            $additional_data =
            [
            'requestMethod' => 'post',
            'enquiryId' => $proposal->user_product_journey_id,
            'soap_action'  => 'GetPDF',
            'container'    => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
            'method' => 'Generate Policy',
            'section' => 'car',
            'transaction_type' => 'proposal',
            ];

            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.PDF_LINK_MOTOR'), $pdf_array, 'future_generali', $additional_data);
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

                if (isset($pdf_data))
                {
                    updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                    ]);

                    $pdf_name = config('IC.FUTURE_GENERALI.V1.CAR.PROPOSAL_PDF_URL') . 'future_generali/' . $proposal->user_product_journey_id . '.pdf';
                    Storage::put($pdf_name, base64_decode($pdf_data));
                    PolicyDetails::where('proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'pdf_url' => $pdf_name
                        ]);

                        $pdf_response_data = [
                        'status' => true,
                        'msg' => 'sucess',
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
                    'msg'    => 'No response received from PDF generation service'
                    ];
            }

        }
        else
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data'   => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      =>  file_url($policy_details->pdf_url)
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
            $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
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
                'section' => 'car',
                'transaction_type' => 'proposal',
            ];


            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.MOTOR_CHECK_TRN_STATUS'), $quote_array, 'future_generali', $additional_data);
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
                            "status" => STAGE_NAMES['PAYMENT_SUCCESS'],
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

                        $UserProductJourney = UserProductJourney::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
                        $corporate_service ='N';
                
                        if(!empty($UserProductJourney->corporate_id) && !empty($UserProductJourney->domain_id) && config('IC.FUTURE_GENERALI.V1.CAR.IS_ENABLED_AFFINITY') == 'Y')
                        {
                                $corporate_service = 'Y';
                        }
        
                        if($corporate_service !== 'Y')
                        {
                            $UserID = config('IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID');
                            $Password = config('IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD');
                        }else
                        {
                            $UserID = config('IC.FUTURE_GENERALI.V1.CAR.PDF_USER_ID_CORPORATE');
                            $Password = config('IC.FUTURE_GENERALI.V1.CAR.PDF_PASSWORD_CORPORATE');
                        }
                        

                        $pdf_array = [
                                        'tem:PolicyNumber' => $policy_no,
                                        'tem:UserID'       => $UserID, //'webagg02',
                                        'tem:Password'     => $Password //'webagg02@123',
                                    ];
                        $additional_data =
                        [
                        'requestMethod' => 'post',
                        'enquiryId' => $proposal->user_product_journey_id,
                        'soap_action'  => 'GetPDF',
                        'container'    => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                        'method' => 'Generate Policy',
                        'section' => 'car',
                        'transaction_type' => 'proposal',
                        ];

                        $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.PDF_LINK_MOTOR'), $pdf_array, 'future_generali', $additional_data);
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

                            if (isset($pdf_data))
                            {
                                updateJourneyStage([
                                        'user_product_journey_id' => $proposal->user_product_journey_id,
                                        'stage' => STAGE_NAMES['POLICY_ISSUED']
                                ]);
                                $pdf_name = config('IC.FUTURE_GENERALI.V1.CAR.PROPOSAL_PDF_URL') . 'future_generali/' . $proposal->user_product_journey_id . '.pdf';
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
                'section' => 'car',
                'transaction_type' => 'proposal',
            ];
            #config('IC.FUTURE_GENERALI.V1.CAR.MOTOR_CHECK_TRN_STATUS')
            $recorn_payment_status_check = ArrayToXml::convert($request_array, $root, false, 'utf-8');
            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.CAR.MOTOR_CHECK_TRN_STATUS'), $recorn_payment_status_check, 'future_generali', $additional_data);
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
