<?php

namespace App\Http\Controllers\Payment\Services\Car;

use App\Http\Controllers\SyncPremiumDetail\Car\ChollaMandalamPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPolicy;
use App\Models\MasterPremiumType;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use App\Models\QuoteLog;
use App\Models\chollamandalammodel;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Str;
use Config;
use Illuminate\Http\Request;
use App\Models\CvBreakinStatus;


include_once app_path() . '/Helpers/CarWebServiceHelper.php';


class chollaMandalamPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {


        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();


        $enquiryId = customDecrypt($request->enquiryId);

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();
        $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)
        ->first();
        $quote_log_id = $quoteLog->quote_id ?? null;
;
        $productData = getProductDataByIc($request['policyId']);
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $requestData = getQuotation($enquiryId);
        
        $isInspectionWaivedOff = false;
        $isBreakinCase = $proposal->is_breakin_case == 'Y' || ($quoteLog['premium_json']['isInspectionWaivedOff'] ?? false);
        $isBreakinCase = $isBreakinCase && in_array($premium_type, ['breakin', 'own_damage_breakin']);

        if (
            $isBreakinCase &&
            !empty($requestData->previous_policy_expiry_date) &&
            strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
            config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_INSPECTION_WAIVED_OFF') == 'Y'
        ) {
            $date1 = new DateTime($requestData->previous_policy_expiry_date);
            $date2 = new DateTime();
            $interval = $date1->diff($date2);

            //inspection is not required for breakin within 10 days
            if ($interval->days <= 10) {
                $isInspectionWaivedOff = true;
            }
        }
        
        if($isBreakinCase && !$isInspectionWaivedOff)
        {   
            $breakinDetails = CvBreakinStatus::where('user_proposal_id', trim($proposal['user_proposal_id']))->first();

            if (empty($breakinDetails)) {
                return [
                    'status'   => false,
                    'message'  => 'Inspection is Required',
                ];
            }

            $policy_start_date = date('d-m-Y', time());
            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $diff_ped = Carbon::now();
            if($diff_ped->diffInDays($proposal->prev_policy_expiry_date) > 30){
                $policy_start_date = Carbon::createFromFormat('d-m-Y', $policy_start_date)->addDays(3)->format('d-m-Y');
                $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            }
            $cholla_model= new chollamandalammodel();
            $request_data = [
                'enquiryId' => $enquiryId,
                'productName' => $request->product_name,
                'section'   => 'car',
            ];
            $token_response = $cholla_model->token_generation($request_data);
            if ($token_response['status'] == false) {
                return $token_response;
            }
            $token = $token_response['token'];
            $proposal_array = json_decode($proposal->additional_details_data, TRUE);
            $proposal_array['breakin_insp_date'] = date('d-m-Y', strtotime($breakinDetails->inspection_date));
            $proposal_array['breakin_insp_time'] = date('H:i', strtotime($breakinDetails->updated_at));
            $proposal_array['breakin_insp_ref_number'] = $breakinDetails->breakin_number;
            $proposal_array['breakin_insp_agency'] = 'Cholla';
            $proposal_array['breakin_insp_place'] = $proposal->city;

            $additional_data_proposal = [
                'requestMethod' => 'post',
                'Authorization' => $token,
                'proposal_id' => '0',
                'enquiryId' => $enquiryId,
                'method' => 'Proposal Submition - Proposal',
                'section' => 'car',
                'type' => 'request',
                'transaction_type' => 'proposal',
            ];

            $get_response = getWsData(
                config('constants.IcConstants.cholla_madalam.END_POINT_URL_CHOLLA_MANDALAM_MOTOR_PROPOSAL'),
                $proposal_array,
                'cholla_mandalam',
                $additional_data_proposal
            );
            $proposaldata = $get_response['response'];
            if ($proposaldata) {
                $requestData = getQuotation($enquiryId);
                $proposal_response = json_decode($proposaldata, true);
                if($proposal_response['Status'] == 'ExpectationFailed'){
                    return [
                        'status'   => false,
                        'message'  => 'Error in Proposal Service of Break-in.',
                        'error'		=> 'Invalid response in Proposal submit of Break-in.'
                    ];
                }
                $error_message = $proposal_response['Message'];
                $proposal_response = array_change_key_case_recursive($proposal_response);
                $proposal_response_data = $proposal_response['data'];
                $total_premium 		= $proposal_response_data['total_premium'];
                $service_tax_total 	= $proposal_response_data['gst'];
                $base_premium 		= $proposal_response_data['net_premium'];

                $base_cover['od'] = $proposal_response_data['basic_own_damage_cng_elec_non_elec'];
                $base_cover['tp'] = $proposal_response_data['basic_third_party_premium'];
                $base_cover['electrical'] = $proposal_response_data['electrical_accessory_prem'];
                $base_cover['non_electrical'] = $proposal_response_data['non_electrical_accessory_prem'];
                $base_cover['lpg_cng_od'] = $proposal_response_data['cng_lpg_own_damage'];
                $base_cover['pa_owner'] = $proposal_response_data['personal_accident'];
                $base_cover['unnamed'] = $proposal_response_data['unnamed_passenger_cover'];
                $base_cover['paid_driver'] = '0';
                $base_cover['legal_liability'] = $proposal_response_data['legal_liability_to_paid_driver'];
                $base_cover['lpg_cng_tp'] = $proposal_response_data['cng_lpg_tp'];

                $base_cover['ncb'] = $proposal_response_data['no_claim_bonus'];
                $base_cover['automobile_association'] = '0';
                $base_cover['anti_theft'] = '0';
                $base_cover['other_discount'] = $proposal_response_data['dtd_discounts'] + $proposal_response_data['gst_discounts'];
                $base_cover['zero_dep'] = $proposal_response_data['zero_depreciation'];
                $base_cover['key_replacement'] = $proposal_response_data['key_replacement_cover'];
                $base_cover['consumable'] = $proposal_response_data['consumables_cover'];
                $base_cover['loss_of_belongings'] = $proposal_response_data['personal_belonging_cover'];
                $base_cover['rsa'] = $proposal_response_data['rsa_cover'];
                $base_cover['engine_protect']  =  $proposal_response_data['hydrostatic_lock_cover'];
                $base_cover['tyre_secure'] = 'NA';
                $base_cover['return_to_invoice'] = 'NA';
                $base_cover['ncb_protect'] = 'NA';

                $addon_sum = (is_integer($base_cover['zero_dep']) ? $base_cover['zero_dep'] : 0)
                    + (is_integer($base_cover['key_replacement']) ? $base_cover['key_replacement'] : 0)
                    + (is_integer($base_cover['consumable']) ? $base_cover['consumable'] : 0)
                    + (is_integer($base_cover['loss_of_belongings']) ? $base_cover['loss_of_belongings'] : 0)
                    + (is_integer($base_cover['rsa']) ? $base_cover['rsa'] : 0)
                    + (is_integer($base_cover['engine_protect']) ? $base_cover['engine_protect'] : 0)
                    + (is_integer($base_cover['tyre_secure']) ? $base_cover['tyre_secure'] : 0)
                    + (is_integer($base_cover['return_to_invoice']) ? $base_cover['return_to_invoice'] : 0)
                    + (is_integer($base_cover['ncb_protect']) ? $base_cover['ncb_protect'] : 0);

                $total_od = $base_cover['od'] + $base_cover['electrical'] + $base_cover['non_electrical'] + $base_cover['lpg_cng_od'];
                $total_tp = $base_cover['tp'] + $base_cover['legal_liability'] + $base_cover['unnamed'] + $base_cover['lpg_cng_tp']+$base_cover['pa_owner'];
                $total_discount = $base_cover['other_discount'] + $base_cover['automobile_association'] + $base_cover['anti_theft'] + $base_cover['ncb'];

                $basePremium = $total_od + $total_tp - $total_discount + $addon_sum;
                $totalTax = $basePremium * 0.18;
                $final_premium = $basePremium + $totalTax;
                $no_claim_bonus = $requestData->previous_ncb;
                if (!$proposal_response) {
                    return $error_message;
                } else {
                    UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_start_date' =>  str_replace('00:00:00', '', $policy_start_date),
                        'policy_end_date' =>  str_replace('00:00:00', '', $policy_end_date),
                        'proposal_no' => $proposal_response_data['payment_id'],
                        'final_payable_amount' => $proposal_response_data['total_premium'],
                        'od_premium' => round($total_od),
                        'tp_premium' => round($total_tp),
                        'total_premium' => round($basePremium),
                        'addon_premium' => round($addon_sum),
                        'cpa_premium' => $base_cover['pa_owner'],
                        'service_tax_amount' => round($totalTax),
                        'total_discount' => round($total_discount),
                        'final_payable_amount' => $proposal_response_data['total_premium'],#round($final_premium),
                        'ic_vehicle_details' => '',
                        'discount_percent' => $no_claim_bonus.'%',
                        'vehicale_registration_number'=>$proposal->vehicale_registration_number,
                        'engine_no'=>$proposal->engine_number,
                        'chassis_no'=>$proposal->chassis_number,
                        'final_premium'=>env('APP_ENV') == 'local' ? config('constants.IcConstants.cholla_madalam.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM') : $final_premium,
                        'product_code'=>$proposal->product_code,
                        'ncb_discount'=>$base_cover['ncb'],
                        'dob'=>($proposal->dob!=null ? date("Y-m-d", strtotime($proposal->dob)):''),
                        'nominee_dob'=>($proposal->nominee_dob!=null ? date("Y-m-d", strtotime($proposal->nominee_dob)):''),
                        'cpa_policy_fm_dt'=>($proposal->cpa_policy_fm_dt!=null ? date("Y-m-d", strtotime($proposal->cpa_policy_fm_dt)): ''),
                        'cpa_policy_to_dt'=>($proposal->cpa_policy_to_dt!=null ? date("Y-m-d", strtotime($proposal->cpa_policy_to_dt)):''),
                        'cpa_policy_no'=>$proposal->cpa_policy_no,
                        'cpa_sum_insured'=>$proposal->cpa_sum_insured,
                        'car_ownership'=>$proposal->car_ownership,
                        'electrical_accessories'=>$proposal->electrical_accessories,
                        'non_electrical_accessories'=>$proposal->non_electrical_accessories,
                        'version_no'=>$proposal->version_no,
                        'vehicle_category'=>$proposal->vehicle_category,
                        'vehicle_usage_type'=>$proposal->vehicle_usage_type
                    ]);

                    ChollaMandalamPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                }
            }
        }
        $checksum=chollaMandalamPaymentGateway::create_checksum($enquiryId ,$request);

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $return_data = [
            'form_action' => config('constants.IcConstants.cholla_madalam.PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM'),
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'msg' => $checksum['msg'],
            ]
        ];
        $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $data['ic_id'] = $user_proposal->ic_id;
        $data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data);

        $checksum=explode('|',$checksum['msg']);

        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
            ->update(['active' => 0]);

        PaymentRequestResponse::insert([
            'quote_id' => $quote_log_id,
            'user_product_journey_id' => $enquiryId,
            'user_proposal_id' => $proposal->user_proposal_id,
            'ic_id' => $icId,
            'order_id' => $checksum[1],
            'amount' => $proposal->final_payable_amount,
            'payment_url' => config('constants.IcConstants.cholla_madalam.PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM'),
            'return_url' => route('car.payment-confirm', ['cholla_mandalam', 'user_proposal_id' => $proposal->user_proposal_id, 'policy_id' => $request->policyId]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'active' => 1,
            'xml_data' => json_encode($return_data)
        ]);


        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);


    }

    public static  function  create_checksum($enquiryId ,$request)
    {

        $policy_id=$request['policyId'];
        DB::enableQueryLog();
        $data = UserProposal::where('user_product_journey_id', $enquiryId)
            ->first();


        // $new_pg_transaction_id = strtoupper(config('constants.IcConstants.cholla_madalam.BROKER_NAME')).customEncrypt($enquiryId) . date('His', strtotime(now()));
        $new_pg_transaction_id = strtoupper(config('constants.IcConstants.cholla_madalam.BROKER_NAME')). substr(strtoupper(md5(mt_rand())), 0, 7) . date('His', strtotime(now()));

        $str_arr = [
            config('constants.IcConstants.cholla_madalam.PAYMENT_MERCHANT_ID_CHOLLA_MANDALAM'),
            $new_pg_transaction_id,
            'NA',
            (env('APP_ENV') == 'local') ? config('constants.IcConstants.cholla_madalam.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM') :$data->final_payable_amount,
            'NA',
            'NA',
            'NA',
            'INR',
            'NA',
            'R',
            config('constants.IcConstants.cholla_madalam.PAYMENT_SECURITY_ID_CHOLLA_MANDALAM'),
            'NA',
            'NA',
            'F',
            $data->proposal_no,
            $data->vehicale_registration_number,
            $data->mobile_number,
            $data->email,
            $data->chassis_number,
            $data->first_name.' '.$data->last_name,
            'NA',
            route('car.payment-confirm', ['cholla_mandalam','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),

        ];

        $msg_desc = implode('|', $str_arr);
        $checksum = strtoupper(hash_hmac('sha256', $msg_desc, config('constants.IcConstants.cholla_madalam.PAYMENT_CHECKSUM_CHOLLA_MANDALAM')));

        $new_string = $msg_desc.'|'.$checksum;


        $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
            ->where('user_proposal_id', $data->user_proposal_id)
            ->update([
                'unique_proposal_id'                 => $new_pg_transaction_id,
            ]);


        $quries = DB::getQueryLog();

        return [
            'status' => 'true',
            'msg' => $new_string,
            'transaction_id' => $new_pg_transaction_id
        ];
    }

    public static function confirm($request)
    {

        $cholla_model = new chollamandalammodel();
        $request_data = $request->all();

        $proposal = UserProposal::where('user_product_journey_id', $request_data['enquiry_id'])->first();
        if(empty($proposal))
        {
            return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        if($request_data!=null && isset($_REQUEST['msg'])) {
           $response = $_REQUEST['msg'];
            $response = explode('|', $response);
            if ($response[14] == '0300') {

                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update(
                        [
                            'response' => implode(' ', $request_data),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                        ]
                    );


                $additional_details=json_decode($proposal->additional_details);
                $additional_details->billdesk_txn_date=$response[13];
                $additional_details->billdesk_txn_ref_no=$response[2];
                $additional_details=json_encode($additional_details);


                $updateProposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'additional_details' => $additional_details,

                    ]);


        $pdf_data = $cholla_model->motor_retry_pdf(base64_encode($response[1]), $request_data['policy_id'], $proposal->user_product_journey_id);

                      if ($pdf_data['status'] || $pdf_data['status']== true) {
                    $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                    $data['ic_id'] = $proposal->ic_id;
                    $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                    updateJourneyStage($data);
                    PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                        'policy_number' => $pdf_data['policy_no'],
                        'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                        'policy_start_date' => $proposal->policy_start_date,
                        'status' =>  'Success'

                    ]);
                    $enquiryId = $proposal->user_product_journey_id;
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));
                } else {
                    
                    $enquiryId = $request_data['enquiry_id'];
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));
                }

            } else {

                PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('active', 1)
                    ->update(
                        [
                            'response' => implode(' ', $request_data),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'status' => STAGE_NAMES['PAYMENT_FAILED']
                        ]
                    );
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
                updateJourneyStage($data);
                $enquiryId = $proposal->user_product_journey_id;
                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
                //return redirect(Config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));
            }


        } else {
            PaymentRequestResponse::where('user_product_journey_id', $proposal->user_product_journey_id)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->where('active', 1)
                ->update(
                    [
                        'response' => 'response',
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => STAGE_NAMES['PAYMENT_FAILED']
                    ]
                );
            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            $enquiryId = $enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
            //return redirect(Config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($request_data['enquiry_id'])]));

        }
    }


    static public function generatePdf($request){
           $user_product_journey_id = customDecrypt($request->enquiryId);
        $cholla_model = new chollamandalammodel();
        $policy_details = PaymentRequestResponse::leftjoin('policy_details as pd','pd.proposal_id','=','payment_request_response.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','payment_request_response.user_product_journey_id')
            ->where('payment_request_response.user_product_journey_id',$user_product_journey_id)
            ->where(array('payment_request_response.active'=>1))
            ->select(
                'up.user_proposal_id', 'up.user_proposal_id','up.proposal_no','up.unique_proposal_id',
                'pd.policy_number','pd.pdf_url','pd.ic_pdf_url','payment_request_response.order_id'
            )
            ->first();

        if($policy_details == null){
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Data Not Found'
            ];
            return response()->json($pdf_response_data);
        }
        $payment_check = self::check_payment_status($user_product_journey_id, $request->product_name);
        if(!$payment_check['status'])
        {
            $pdf_response_data = [
                'status' => false,
                'msg'    => 'Payment is Pending'
            ];
            return response()->json($pdf_response_data);
        }
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        if($policy_details->ic_pdf_url == '')
        {
        $pdf_data = $cholla_model->motor_retry_pdf(base64_encode($policy_details->order_id),$request->master_policy_id, $proposal->user_product_journey_id);

            if ($pdf_data['status'] || $pdf_data['status']==true) {
                $data['user_product_journey_id'] = $proposal->user_product_journey_id;
                $data['ic_id'] = $proposal->ic_id;
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                updateJourneyStage($data);
                PolicyDetails::where(['proposal_id' => $proposal->user_proposal_id])->update([

                    'policy_number' => $pdf_data['policy_no'],
                    'pdf_url' => config('constants.motorConstant.CAR_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf',
                    'policy_start_date' => $proposal->policy_start_date,
                    'status' =>  'Success'

                ]);
                $pdf_response_data = [
                    'status' => true,
                    'msg' => 'sucess',
                    'data' => [
                        'policy_number' => $pdf_data['policy_no'],
                        'pdf_link'      => file_url(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL').'cholla_mandalam/'.md5($proposal->user_proposal_id).'.pdf')
                    ]
                ];

            } else {
                $pdf_response_data = [
                    'status' => false,
                    'msg'    => 'Error Occured',
                    'dev'    => $pdf_data['message']
                ];
            }



        } else {
            $pdf_response_data = [
                'status' => true,
                'msg'    => STAGE_NAMES['POLICY_PDF_GENERATED'],
                'data' => [
                    'policy_number' => $policy_details->policy_number,
                    'pdf_link'      => file_url($policy_details->pdf_url)
                ]
            ];
        }

        return response()->json($pdf_response_data);
    }

  static public function ReconService(Request $request){
        $cholla_model = new chollamandalammodel();
     $response = $cholla_model->update_transaction_summary((object)$request->all(),'Car');
        return response()->json($response);
    }
 static public function check_payment_status($enquiry_id, $section)
    {
        $transactiondata = PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
            ->select('order_id', 'id', 'user_proposal_id', 'user_product_journey_id')
            ->get();
        if (empty($transactiondata)) {
            return [
                'status' => false
            ];
        }
        $api_merchant_id = config('constants.IcConstants.cholla_madalam.QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM');
        $payment_checksum = config('constants.IcConstants.cholla_madalam.PAYMENT_CHECKSUM_CHOLLA_MANDALAM');
        foreach ($transactiondata as $value) {
            if (empty($value->order_id)) {
                continue;
            }
            $query_api_array = [
                '0122',
                $api_merchant_id,
                $value->order_id,
                date('Ymdhis'),
            ];

            $query_api = implode('|', $query_api_array);
            $checksum = strtoupper(hash_hmac('sha256', $query_api, $payment_checksum));

            $new_string = $query_api . '|' . $checksum;
            $query_api_request = [
                'msg' => $new_string
            ];

            $additional_payment_data = [
                'requestMethod' => 'post',
                'Authorization' => '',
                'proposal_id'   => $value->user_proposal_id,
                'enquiryId' => $value->user_product_journey_id,
                'section' => $section,
                'method'        => 'Query API - Payment Status',
                'type'          => 'Query API',
                'transaction_type' => 'proposal'
            ];

            $get_response = getWsData(
                Config('constants.IcConstants.cholla_madalam.QUERY_API_URL_CHOLLA_MANDALAM'),
                $query_api_request,
                'cholla_mandalam',
                $additional_payment_data
            );
            $query_api_data = $get_response['response'];

            if (!empty($query_api_data) && $query_api_data != '{}') {
                $query_api_response = explode('|', $query_api_data);

                if ($query_api_response[15] == '0300') {

                    PaymentRequestResponse::where('user_product_journey_id', $enquiry_id)
                        ->update([
                            'active'  => 0
                        ]);

                    PaymentRequestResponse::where('id', $value->id)
                        ->update([
                            'response'      => implode('|', $query_api_response),
                            'updated_at'    => date('Y-m-d H:i:s'),
                            'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                            'active'        => 1
                        ]);
                    $proposal = UserProposal::where('user_product_journey_id', $value->user_product_journey_id)
                        ->first();
                    $additional_details = json_decode($proposal->additional_details);
                    $additional_details->billdesk_txn_date   = $query_api_response[14];
                    $additional_details->billdesk_txn_ref_no = $query_api_response[3];
                    $additional_details = json_encode($additional_details);
                    UserProposal::where('user_product_journey_id', $value->user_product_journey_id)
                        ->where('user_proposal_id', $value->user_proposal_id)
                        ->update([
                            'additional_details' => $additional_details,
                            'unique_proposal_id' =>$value->order_id
                        ]);
                    $data['user_product_journey_id']    = $enquiry_id;
                    $data['proposal_id']                = $value->user_proposal_id;
                    $data['ic_id']                      = '30';
                    $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                    updateJourneyStage($data);
                    return [
                        'status'    => true
                    ];
                }
            }
        }
        return [
            'status'    => false,
            'message'   => 'No response Form Query API service'
        ];
    }

}
