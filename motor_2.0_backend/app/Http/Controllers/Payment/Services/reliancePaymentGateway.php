<?php
namespace App\Http\Controllers\Payment\Services;

use Exception;
use Config;
use App\Models\UserProposal;
use App\Models\PaymentRequestResponse;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Proposal\Services\relianceSubmitProposal;
use App\Http\Controllers\Payment\Services\relianceMIscDPaymentGateway;

use App\Http\Controllers\Finsall\FinsallController;
use App\Http\Controllers\Finsall\RelianceFinsallController;
use App\Http\Controllers\SyncPremiumDetail\Services\ReliancePremiumDetailController;
use DateTime;
use Carbon\Carbon;
use App\Models\WebServiceRequestResponse;
use App\Models\WebserviceRequestResponseDataOptionList;

include_once app_path().'/Helpers/CvWebServiceHelper.php';
class reliancePaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();
        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->first();
        $quote_log_data->bodyIDV = $quote_log_data->premium_json['bodyIDV'];
        $quote_log_data->chassisIDV = $quote_log_data->premium_json['chassisIDV'];

        $productData = getProductDataByIc($quote_log_data->master_policy_id);
        $requestData = getQuotation($enquiryId);
        $parent_id = get_parent_code($productData->product_sub_type_id);
        if (get_parent_code($productData->product_sub_type_id) == 'MISC') {
            return relianceMIscDPaymentGateway::make($request);
        } else {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'reliance', $parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL);

            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
      
        // $rto_code = $requestData->rto_code;
        // $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); 
        
        // $rto_data = DB::table('reliance_rto_master as rm')
        //     ->where('rm.region_code',$rto_code)
        //     ->select('rm.*')
        //     ->first();

        $rto_code = $requestData->rto_code;
        $registration_number = $user_proposal->vehicale_registration_number;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness'
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }
        
        $rto_data = $rcDetails['rtoData'];

        $breakinDetails = DB::table('cv_breakin_status')
            ->where('cv_breakin_status.user_proposal_id', '=', $user_proposal->user_proposal_id)
            ->first();

        $is_breakincase = $user_proposal->is_breakin_case == 'Y';

        if (
            $is_breakincase &&
            isset($quote_log_data['premium_json']['isInspectionWaivedOff'], $quote_log_data['premium_json']['waiverExpiry']) &&
            $quote_log_data['premium_json']['isInspectionWaivedOff'] &&
            (date('Y-m-d') > date('Y-m-d', strtotime(($quote_log_data['premium_json']['waiverExpiry']))))
        ) {
            return [
                'status'   => false,
                'message'  => 'Proposal Expired',
            ];
        }

        $premium_req_data = relianceSubmitProposal::getPremiumRequestData([
            'enquiryId' => $enquiryId,
            'requestData' => $requestData,
            'productData' => $productData,
            'quote_log_data' => $quote_log_data,
            'mmv_data' => $mmv_data,
            'rto_data' => $rto_data,
            'proposal' => $user_proposal,
            'breakin_details' => $breakinDetails
        ]);

        extract($premium_req_data);

        if (isset($status) && ! $status)
        {
            return [
                'status' => false,
                'message' => $message
            ];
        }

        if ($requestData->vehicle_owner_type == "I") {
            if ($user_proposal->gender == "M") {
                $Salutation = 'Mr.';
            } else {
                if ($user_proposal->gender == "F" && $user_proposal->marital_status == "Single") {
                    $Salutation = 'Ms.';
                } else {
                    $Salutation = 'Ms.';
                }
            }
        } else {
            $Salutation = 'M/S';
        }

        $corres_address_data = DB::table('reliance_pincode_state_city_master')
            ->where('pincode',$user_proposal->pincode)
            ->select('*')
            ->first();
        
        $address_data = [
                    'address' => $user_proposal->address_line1,
                    'address_1_limit'   => 250,
                    'address_2_limit'   => 250            
                ];
        $getAddress = getAddress($address_data);

        $ClientDetails = [
            'ClientType' => $ClientType,
            'Salutation' => $Salutation,
            'ForeName' => $ForeName,
            'LastName' => $LastName,
            'CorporateName' => $CorporateName,
            'MidName' => '',
            'OccupationID' => $OccupationID,
            'DOB' => $DOB,
            'Gender' => $Gender,
            'PhoneNo' => '',
            'MobileNo' => $user_proposal->mobile_number,
            'RegisteredUnderGST' => trim($user_proposal->gst_number) == '' ? '0' : '1',
            'RelatedParty' => '0',
            'GSTIN' => $user_proposal->gst_number,
            'GroupCorpID' => '',
            'ClientAddress' => [
                'CommunicationAddress' => [
                    'AddressType' => '0',
                    'Address1'        => trim($getAddress['address_1']),
                    'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                    'Address3'        => trim($getAddress['address_3']),
                    'CityID' => $corres_address_data->city_or_village_id_pk,
                    'DistrictID' => $corres_address_data->district_id_pk,
                    'StateID' => $corres_address_data->state_id_pk,
                    'Pincode' => $user_proposal->pincode,
                    'Country' => '1',
                    'NearestLandmark' => '',
                ],
                'RegistrationAddress'  => [
                    'AddressType' => '0',
                    'Address1'        => trim($getAddress['address_1']),
                    'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                    'Address3'        => trim($getAddress['address_3']),
                    'CityID' => $corres_address_data->city_or_village_id_pk,
                    'DistrictID' => $corres_address_data->district_id_pk,
                    'StateID' => $corres_address_data->state_id_pk,
                    'Pincode' => $user_proposal->pincode,
                    'Country' => '1',
                    'NearestLandmark' => '',
                ],
                'PermanentAddress' => [
                    'AddressType' => '0',
                    'Address1'        => trim($getAddress['address_1']),
                    'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                    'Address3'        => trim($getAddress['address_3']),
                    'CityID' => $corres_address_data->city_or_village_id_pk,
                    'DistrictID' => $corres_address_data->district_id_pk,
                    'StateID' => $corres_address_data->state_id_pk,
                    'Pincode' => $user_proposal->pincode,
                    'Country' => '1',
                    'NearestLandmark' => '',
                ],
            ],
            'EmailID' => $user_proposal->email,
            'MaritalStatus' => $MaritalStatus,
            'Nationality' => '1949'
        ];

        if (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']) && $user_proposal->is_inspection_done == 'Y') {
            $premium_req_array['Risk']['IsInspectionAddressSameasCommAddress'] = 'true';

            $ClientDetails['ClientAddress']['InspectionAddress'] = [
                'AddressType' => '0',
                'Address1' => trim($getAddress['address_1']),
                'Address2' => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                'Address3' => trim($getAddress['address_3']),
                'CityID' => $corres_address_data->city_or_village_id_pk,
                'DistrictID' => $corres_address_data->district_id_pk,
                'StateID' => $corres_address_data->state_id_pk,
                'Pincode' => $user_proposal->pincode,
                'Country' => '1',
                'NearestLandmark' => ''
            ];
        }
        if(!empty($mmv_data->mfg_buildin) &&$mmv_data->mfg_buildin == 'No' )
        {
            $premium_req_array['Risk']['IDV'] = $premium_req_array['Vehicle']['BodyIDV'] + $premium_req_array['Vehicle']['ChassisIDV'];
        }

        if ($user_proposal->is_car_registration_address_same == 0) {
            $reg_address_data = DB::table('reliance_pincode_state_city_master')
                ->where('pincode',$user_proposal->car_registration_pincode)
                ->select('*')
                ->first();

            $premium_req_array['Risk']['IsRegAddressSameasCommAddress'] = 'false';

            $ClientDetails['ClientAddress']['RegistrationAddress'] = [
                'AddressType' => '0',
                'Address1' => $user_proposal->car_registration_address1,
                'Address2' => $user_proposal->car_registration_address2,
                'Address3' => $user_proposal->car_registration_address3,
                'CityID' => $reg_address_data->city_or_village_id_pk,
                'DistrictID' => $reg_address_data->district_id_pk,
                'StateID' => $reg_address_data->state_id_pk,
                'Pincode' => $user_proposal->car_registration_pincode,
                'Country' => '1',
                'NearestLandmark' => '',
            ];
        }

        unset($premium_req_array['ClientDetails']);

        $client['ClientDetails'] = $ClientDetails;

        $premium_req_array = array_merge($client,$premium_req_array);

        if ($requestData->business_type == 'breakin')
        {
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d', time());

            if($parent_id == 'GCV' && $requestData->business_type == 'breakin'){
                if ($mmv_data->gross_weight < 3500) {
                    $policy_start_date = date('Y-m-d', strtotime('+2 day'));
                }else{
                    $policy_start_date = date('Y-m-d', strtotime('+1 day'));
                }
            }

            if ( in_array($premium_type, ['short_term_3', 'short_term_3_breakin']))
            {
                $policy_end_date = date('Y-m-d', strtotime('-1 days + 3 months', strtotime($policy_start_date)));
            }
            elseif ( in_array($premium_type, ['short_term_6', 'short_term_6_breakin']))
            {
                $policy_end_date = date('Y-m-d', strtotime('-1 days + 6 months', strtotime($policy_start_date)));
            }
            else
            {
                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            }

            $premium_req_array['Policy']['Cover_From'] = $policy_start_date;
            $premium_req_array['Policy']['Cover_To'] = $policy_end_date;

            $premium_req_array['Vehicle']['InspectionNo'] = $breakinDetails->breakin_number ?? NULL;
        }

        $proposal_submit_url = config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL');

        if (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']) && $user_proposal->is_inspection_done == 'Y') {
            $proposal_submit_url = config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL_POST_INSPECTION');
        }

        $get_response = getWsData($proposal_submit_url, $premium_req_array, 'reliance',
            [
                'root_tag' => 'PolicyDetails',
                'section' => $productData->product_sub_type_code,
                'method' => 'Proposal Creation',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY'),
                    'Content-type' => 'text/xml'
                ]
            ]
        );
        $proposal_res_data = $get_response['response'];
        $response = $proposal_resp = json_decode($proposal_res_data)?->MotorPolicy;

        if (empty($response)) {
            return response()->json([
                'status' => false,
                'msg' => 'Error in proposal service'
            ]);
        }

        if (isset($proposal_resp->ErrorMessages) && trim($proposal_resp->ErrorMessages) != '') {
            return response()->json([
                'status' => false,
                'msg' => trim($proposal_resp->ErrorMessages)
            ]);
        }
        
        $od = 0;
        $tp_liability = 0;
        $ll_paid_driver = 0;
        $ll_paid_cleaner = 0;
        $electrical_accessories = 0;
        $non_electrical_accessories = 0;
        $external_lpg_cng = 0;
        $external_lpg_cng_tp = 0;
        $pa_to_paid_driver = 0;
        $cpa_premium = 0;
        $ncb_discount = 0;
        $automobile_association = 0;
        $anti_theft = 0;
        $tppd_discount_amt = 0;
        $ic_vehicle_discount = 0;
        $voluntary_excess = 0;
        $other_discount = 0;
        $imt_23 = 0;
        $nil_depreciation = 0;
        $GeogExtension_od = 0;
        $GeogExtension_tp = 0;
        $total_od_addon = 0;

        if (is_array($response->lstPricingResponse)) {
            foreach ($response->lstPricingResponse as $single) {
                if (isset($single->CoverageName) && $single->CoverageName == 'PA to Owner Driver') {
                    $cpa_premium = round($single->Premium);
                } elseif (isset($single->CoverageName) && $single->CoverageName == 'NCB') {
                    $ncb_discount = round(abs($single->Premium));
                } elseif ($single->CoverageName == 'Automobile Association Membership') {
                    $automobile_association = round(abs($single->Premium));
                } elseif ($single->CoverageName == 'Anti-Theft Device') {
                    $anti_theft = round(abs($single->Premium));
                } elseif ($single->CoverageName == 'TPPD') {
                    $tppd_discount_amt = round(abs($single->Premium));
                }
                elseif ($single->CoverageName == 'Basic OD')
                {
                    $od = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Basic Liability')
                {
                    $tp_liability = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Liability to Paid Driver')
                {
                    $ll_paid_driver = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Liability to Cleaner')
                {
                    $ll_paid_cleaner = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Electrical Accessories')
                {
                    $electrical_accessories = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Non Electrical Accessories')
                {
                    $non_electrical_accessories = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Bifuel Kit')
                {
                    $external_lpg_cng = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Bifuel Kit TP')
                {
                    $external_lpg_cng_tp = round($single->Premium);
                }
                elseif ($single->CoverageName == 'PA to Paid Driver')
                {
                    $pa_to_paid_driver = round($single->Premium);
                }
                elseif ($single->CoverageName == 'IMT 23(Lamp/ tyre tube/ Headlight etc )')
                {
                    $imt_23 = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Nil Depreciation')
                {
                    $nil_depreciation = round($single->Premium);
                }
                elseif (($single->CoverageName == 'Geographical Extension' || $single->CoverageName == 'Geo Extension')&& $single->CoverID == '5')
                {
                    $GeogExtension_od = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Geographical Extension' && $single->CoverID == '6')
                {
                    $GeogExtension_tp = round($single->Premium);
                }
                elseif ($single->CoverageName == 'Total OD and Addon')
                {
                    $total_od_addon = abs( (int) $single->Premium);
                }
            }
        } else {
            $tp_liability = $response->lstPricingResponse->Premium;
        }

        $NetPremium = $response->NetPremium;
        $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;
        $total_od_amount = ($od + $GeogExtension_od) - $final_total_discount + $tppd_discount_amt;
        $total_tp_amount = $tp_liability + $ll_paid_driver + $ll_paid_cleaner + $external_lpg_cng_tp + $pa_to_paid_driver + $cpa_premium - $tppd_discount_amt + $GeogExtension_tp;
        $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $external_lpg_cng + $imt_23 + $nil_depreciation;
        $final_payable_amount = $response->FinalPremium;

        if ($proposal_resp->status == '1') {
            $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'proposal_no' => $proposal_resp->ProposalNo,
                    'od_premium' => $total_od_amount,
                    'tp_premium' => round($total_tp_amount),
                    'ncb_discount' => round($ncb_discount), 
                    'addon_premium' => round($total_addon_amount),
                    'cpa_premium' => round($cpa_premium),
                    'total_discount' => round($final_total_discount),
                    'total_premium' => round($NetPremium),
                    'service_tax_amount' => round($final_payable_amount - $NetPremium),
                    'final_payable_amount' => round($final_payable_amount)
                ]);
                $user_proposal = $user_proposal->refresh();

            ReliancePremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            $params = [
                'ProposalNo'     => $proposal_resp->ProposalNo,
                'userID'         => config('constants.IcConstants.cv.reliance.USERID_RELIANCE'),
                'ProposalAmount' => $user_proposal->final_payable_amount,
                'PaymentType'    => '1',
                'Responseurl'    => route('cv.payment-confirm', ['reliance']),
            ];
           $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
           $carrying_capacity = $mmv_data->carrying_capacity ?? '';
            if($productData->product_sub_type_code == 'TAXI')
            {
                if($carrying_capacity <= 6)
                {
                    $productCode = $tp_only == 'true' ? '2353' : '2338';
                }
                else
                {
                    $productCode = $tp_only == 'true' ? '2355' : '2340';
                }
            } elseif ($productData->product_sub_type_code == 'AUTO-RICKSHAW' || $productData->product_sub_type_code == 'ELECTRIC-RICKSHAW') {
                $productCode = $tp_only == 'true' ? '2354' : '2339';
            }
            elseif ($productData->product_sub_type_code == 'MISCELLANEOUS-CLASS')
            {
                $productCode = $tp_only == 'true' ? '2358' : '2343';
            }
            else
            {
                if ($requestData->gcv_carrier_type == 'PUBLIC') {
                    if ($mmv_data->wheels > 3) {
                        $productCode = $tp_only == 'true' ? '2349' : '2334';
                    } elseif ($mmv_data->wheels == 3) {
                        $productCode = $tp_only == 'true' ? '2351' : '2336';
                    }
                } else {
                    if ($mmv_data->wheels > 3) {
                        $productCode = $tp_only == 'true' ? '2350' : '2335';
                    } elseif ($mmv_data->wheels == 3) {
                        $productCode = $tp_only == 'true' ? '2352' : '2337';
                    }
                }
            }
            if(in_array($productCode, ['2350','2349']) && $tp_only == 'true')
            {
                if ($mmv_data->wheels == 3) {
                $params['userID'] = config('constants.IcConstants.cv.reliance.3W_TP_USERID_RELIANCE');
                }
            }
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                if(empty($user_proposal->ckyc_number))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'CKYC number is not available, Please complete CKYC process'
                    ]);
                }
                $kyc_param = [
                    'CKYC' => $user_proposal->ckyc_number,
                    'IsDocumentUpload' => 'false',
                    'PanNo' => $user_proposal->ckyc_type_value,
                    'IsForm60' => 'false'
                ];
                $params = array_merge($params, $kyc_param);
            }

            $params['subscription-key'] = config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY');
    
            $payment_url = config('constants.IcConstants.reliance.PAYMENT_GATEWAY_LINK_RELIANCE'). '?' . http_build_query($params);
    
            DB::table('payment_request_response')
                  ->where('user_product_journey_id', $enquiryId)
                  ->update(['active' => 0]);
    
            DB::table('payment_request_response')->insert([
                'quote_id' => $quote_log_data->quote_id,
                'user_product_journey_id' => $enquiryId,
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $proposal_resp->ProposalNo,
                'proposal_no' => $proposal_resp->ProposalNo,
                'amount' => $user_proposal->final_payable_amount,
                'payment_url' => $payment_url,
                'return_url' => route('cv.payment-confirm', ['reliance']),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'active' => 1,
                'xml_data' => json_encode($params),
            ]);

            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ]); 

            return response()->json([
                'status' => true,
                'data' => [
                    'payment_type' => 1,
                    'paymentUrl' => trim($payment_url)
                ]
            ]);
        } elseif (trim($proposal_resp->ErrorMessages) != '') {
            return response()->json([
                'status' => false,
                'msg' => trim($proposal_resp->ErrorMessages)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Error in proposal submit service'
            ]);
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request)
    {
        //return $request->All();
        $Output = $request->Output;
        $response_array = explode('|', $Output);
        $bool_status = $response_array[0];
        $policy_no = $response_array[1];
        $proposal_no = $response_array[5];
        $status = $response_array[6];

        if(empty($proposal_no))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        $PaymentRequestResponse = PaymentRequestResponse::where('order_id', $proposal_no)
            ->select('*')
            ->first();

        if(empty($PaymentRequestResponse))
        {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }

        $user_proposal = UserProposal::where('user_proposal_id', $PaymentRequestResponse->user_proposal_id)
            ->orderBy('user_proposal_id', 'desc')
            ->select('*')
            ->first();

        $result = QuoteLog::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();

        $productData = getProductDataByIc($result->master_policy_id);

        if (get_parent_code($productData->product_sub_type_id) == 'MISC') {
            return relianceMIscDPaymentGateway::confirm($request);
        }
        PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
            ->update(['active' => 0]);

        PaymentRequestResponse::where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->where('order_id', $proposal_no)
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->update([
                    'response' => $request->All(),
                    'status'   => $status == 'Success' ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'],
                    'active' => 1,
                    'updated_at' => date("Y-m-d H:i:s")
                ]);


        if($bool_status)
        {
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => !empty($policy_no) ? STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] : STAGE_NAMES['PAYMENT_SUCCESS']
            ]);

            if (config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE') == 'Y') {
                $pdf_url =  config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW');
                $pdf_Request = [
                    'PolicyNumber' => $policy_no,
                    'SourceSystemID' => config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE'),
                    'SecureAuthToken' => config('constants.IcConstants.reliance.SECURE_AUTH_TOKEN_RELIANCE'),
                    'EndorsementNo' => '',
                ];
                $result = UserProposal::join('quote_log', 'user_proposal.user_product_journey_id', '=', 'quote_log.user_product_journey_id')
                ->where('user_proposal.user_product_journey_id', $user_proposal->user_product_journey_id)
                    ->select('user_proposal.*', 'quote_log.quote_id', 'quote_log.master_policy_id')
                    ->first();
                $productData = getProductDataByIc($result->master_policy_id);
                $productName = $productData->product_name;
                $productName = str_replace('-', '', $productName);
            } else {
                $pdf_url = config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE') . '?PolicyNo=' . $policy_no . '&ProductCode=' . $user_proposal->product_code;
            }

            if(!empty($policy_no))
            {
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal->user_proposal_id],
                [
                    'policy_number' => $policy_no,
                    'ic_pdf_url'    => $pdf_url,
                    'status'        => $status
                ]
            );

            if (config('constants.motorConstant.RELIANCE_GENERATE_POLICY_PDF') == 'Y') {

                sleep(5);// DELAY OF 5 SECONDS POLICY WAS NOT GENERATING

                if (config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE') == 'Y') {      
                $pdf_data = getWsData(
                    $pdf_url,
                    $pdf_Request,
                    'reliance',
                    [
                        'root_tag' => 'GenerateScheduleRequest',
                        'section' => 'Cv',
                        'method' => 'Download policy PDF',
                        'enquiryId' => $user_proposal->user_product_journey_id,
                        'productName' => $productName.' ('.ucwords($result->business_type).')',
                        'requestMethod' => 'post',
                        'transaction_type' => 'proposal',
                        'headers' => [
                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY'),
                            'Content-type' => 'text/xml'
                        ]
                    ]
                );
                $pdf_data = json_decode($pdf_data['response'], true);

                    if(empty($pdf_data['DownloadLink'])){
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                    }

                if (!empty($pdf_data['DownloadLink'])) {
                    try {
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $user_proposal->user_proposal_id],
                            [
                                'policy_number' => $policy_no,
                                'ic_pdf_url'    => $pdf_data['DownloadLink'],
                                'status'        => $status
                            ]
                        );


                        $path = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/' . md5($user_proposal->user_proposal_id) . '.pdf';

                        $pdfData = httpRequestNormal($pdf_data['DownloadLink'], 'GET', [], [], [], [], false); //file_get_contents($pdf_data['DownloadLink']);

                        if(!checkValidPDFData($pdfData['response'])){
                            updateJourneyStage([
                                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                            ]);
                            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                        }
                        if (isset($pdfData['status']) && $pdfData['status'] == 200) {
                            Storage::put($path, $pdfData['response']);

                        }
                           
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $user_proposal->user_proposal_id],
                            [
                                'pdf_url' => $path
                            ]
                        );
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED']
                        ]);
                    } catch (Exception $a) {
                        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                    }
                }
                }else{
                $pdf_data = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false);

                 if(!checkValidPDFData($pdf_data['response'])){
                        updateJourneyStage([
                            'user_product_journey_id' => $user_proposal->user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);
                        return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
                    }
                    if (isset($pdf_data['status']) && $pdf_data['status'] == 200) {
                    Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/'.md5($user_proposal->user_proposal_id).'.pdf', $pdf_data['response']);
                }
                }

               

                PolicyDetails::where('proposal_id', $user_proposal->user_proposal_id)
                    ->update([
                        'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL').'reliance/' .  md5($user_proposal->user_proposal_id). '.pdf'
                    ]);
                
                sleep(2);// DELAY OF 2 SECONDS POLICY WAS NOT GENERATING
                updateJourneyStage([
                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED']
                ]);
            }
            }

            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'SUCCESS'));
        }
        else
        {
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED']
            ]);
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id, 'CV', 'FAILURE'));
        }
    }

    static public function generatePdf($request)
    {
        $user_product_journey_id = customDecrypt($request->enquiryId);

        $policy_details = DB::table('payment_request_response as prr')
            ->leftjoin('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
            ->join('user_proposal as up','up.user_product_journey_id','=','prr.user_product_journey_id')
            ->where('prr.user_product_journey_id',$user_product_journey_id)
            // ->where('prr.active',1)
            ->select('prr.id', 'prr.user_product_journey_id', 'up.user_proposal_id', 'up.user_proposal_id', 'prr.proposal_no', 'up.unique_proposal_id', 'up.product_code', 'pd.policy_number', 'pd.pdf_url', 'pd.ic_pdf_url', 'prr.lead_source'
            )
            ->get();

        $userProposal = UserProposal::select('user_product_journey_id', 'user_proposal_id', 'proposal_no', 'additional_details')
                ->where('user_product_journey_id', $user_product_journey_id)->first();
        if(empty($policy_details))
        {
            return response()->json([
                'status' => false,
                'msg'    => 'Payment is pending'
            ]);
        }

        $return_data = [
            'status' => false,
            'msg'    => 'Payment is pending'
        ];

        foreach ($policy_details as $policy_detail) {
            if(empty($policy_detail) || empty($policy_detail->policy_number))
            {
                if($policy_detail->lead_source == 'finsall')
                {
                    $finsall_data = self::finsallRehitService($user_product_journey_id,$policy_detail->proposal_no);
                    if($finsall_data['status'])
                    {
                        return  $finsall_data;  
                    }
                }
                $proposalStatusService = self::proposalStatusService($user_product_journey_id, $policy_detail);
                if($proposalStatusService['status']){
                    return self::createPDF($proposalStatusService['data']['policy_number'], $policy_detail);
                }
            }
            else
            {
                if(!empty($policy_detail->pdf_url))
                {
                    if(self::iscurrentPDFDataValid($policy_detail, $userProposal))
                    {
                        return self::createPDF($policy_detail->policy_number, $policy_detail);
                    }
                    return response()->json([
                        'status' => true,
                        'msg' => 'success',
                        'data' => [
                            'policy_number' => $policy_detail->policy_number,
                            'pdf_link' => file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf'),//$policy_detail->pdf_url,
                            'ic_pdf_url' => $policy_detail->ic_pdf_url,
                        ]
                    ]);
                }
                return self::createPDF($policy_detail->policy_number, $policy_detail); 
            }
        }
        return $return_data;
    }

    public static function proposalStatusService($enquiryId, $policy_detail)
    {
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $proposalStatusRequest = [
            'ValidateFlag' => 'false',
            'Policy' => [
                'ProposalNo' => $policy_detail->proposal_no,
                'PolicyNumber' => '',
            ],
            'ErrorMessages' => '',
            'UserID' => config('constants.IcConstants.cv.reliance.USERID_RELIANCE'),
            'SourceSystemID' => config('constants.IcConstants.cv.reliance.SOURCE_SYSTEM_ID_RELIANCE'),
            'AuthToken' => config('constants.IcConstants.cv.reliance.AUTH_TOKEN_RELIANCE'),
            'Authentication' => [
                'PolicyNumber' => '',
                'EngineNo' => '',
                'ChassisNo' => '',
                'RegistrationNo' => '',
                'PolicyEndDate' => '',
                'ProposerDOB' => '',
            ],
        ];
        $policyid = QuoteLog::where('user_product_journey_id', $enquiryId)->pluck('master_policy_id')->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($policyid);
        $parent_id = get_parent_code($productData->product_sub_type_id);
        $mmv = get_mmv_details($productData,$requestData->version_id, 'reliance', $parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL);

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $carrying_capacity = $mmv_data->carrying_capacity ?? '';
        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        if ($productData->product_sub_type_code == 'TAXI') {
            if ($carrying_capacity <= 6) {
                $productCode = $tp_only == 'true' ? '2353' : '2338';
            } else {
                $productCode = $tp_only == 'true' ? '2355' : '2340';
            }
        } elseif ($productData->product_sub_type_code == 'AUTO-RICKSHAW' || $productData->product_sub_type_code == 'ELECTRIC-RICKSHAW') {
            $productCode = $tp_only == 'true' ? '2354' : '2339';
        } elseif ($productData->product_sub_type_code == 'MISCELLANEOUS-CLASS') {
            $productCode = $tp_only == 'true' ? '2358' : '2343';
        } else {
            if ($requestData->gcv_carrier_type == 'PUBLIC') {
                if ($mmv_data->wheels > 3) {
                    $productCode = $tp_only == 'true' ? '2349' : '2334';
                } elseif ($mmv_data->wheels == 3) {
                    $productCode = $tp_only == 'true' ? '2351' : '2336';
                }
            } else {
                if ($mmv_data->wheels > 3) {
                    $productCode = $tp_only == 'true' ? '2350' : '2335';
                } elseif ($mmv_data->wheels == 3) {
                    $productCode = $tp_only == 'true' ? '2352' : '2337';
                }
            }
        }
        if (in_array($productCode, ['2350', '2349']) && $tp_only == 'true') {
            if ($mmv_data->wheels == 3) {
                $premium_req_array['UserID'] = config('constants.IcConstants.cv.reliance.3W_TP_USERID_RELIANCE');
                $premium_req_array['SourceSystemID'] = config('constants.IcConstants.cv.reliance.3W_TP_SOURCE_SYSTEM_ID_RELIANCE');
                $premium_req_array['AuthToken'] = config('constants.IcConstants.cv.reliance.3W_TP_AUTH_TOKEN_RELIANCE');
            }
        }
        $get_response = getWsData(
            config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL_STATUS'),
            $proposalStatusRequest,
            'reliance',
            [
                'root_tag' => 'ProposalDetails',
                'section' => 'Cv',
                'method' => 'Proposal Status - Payment',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $proposal->business_type.' '.$proposal->product_type,
                'transaction_type' => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY'),
                    'Content-type' => 'text/xml'
                ]
            ]
        );
        $proposalStatusResponse = $get_response['response'];

        if(empty($proposalStatusResponse))
        {
            return [
                'status' => false,
                'message' => 'Insurer Not Reachable - Proposal Status'
            ];
        }

        $proposalStatusResponse = json_decode($proposalStatusResponse, true);

        if(empty($proposalStatusResponse))
        {
            return [
                'status' => false,
                'message' => 'Insurer Not Reachable - Proposal Status'
            ];
        }

        if(isset($proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']) && !empty($proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']))
        {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('proposal_no', $policy_detail->proposal_no)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);

                PolicyDetails::updateOrCreate(
                [
                    'proposal_id' => $proposal->user_proposal_id
                ],
                [
                    'policy_number' => $proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']
                ]
            );

            return [
                'status' => true,
                'message' => 'success',
                'data' => [
                    'policy_number' => $proposalStatusResponse['ProposalDetails']['Proposal']['PolicyNumber']
                ]
            ];
        }
        else{

            if(isset($proposalStatusResponse['ProposalDetails']['ErrorMessages']) && !empty($proposalStatusResponse['ProposalDetails']['ErrorMessages']))
            {
                return [
                    'status' => false,
                    'message' => $proposalStatusResponse['ProposalDetails']['ErrorMessages']
                ];
            }
            else
            {
                return [
                    'status' => false,
                    'message' => 'Unable To Fetch PolicyNumber - Proposal Status'
                ];
            }

        }
    }

    public static function createPDF($policy_number, $policy_detail)
    {            
        $user_product_journey_id = $policy_detail->user_product_journey_id;
        if (config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE') == 'Y') {
            $ic_pdf_url =  config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW');
            $pdf_Request = [
                'PolicyNumber' => $policy_number,
                'SourceSystemID' => config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE'),
                'SecureAuthToken' => config('constants.IcConstants.reliance.SECURE_AUTH_TOKEN_RELIANCE'),
                'EndorsementNo' => '',
            ];
            $result = UserProposal::join('quote_log', 'user_proposal.user_product_journey_id', '=', 'quote_log.user_product_journey_id')
                ->where('user_proposal.user_product_journey_id', $user_product_journey_id)
                ->select('user_proposal.*', 'quote_log.quote_id', 'quote_log.master_policy_id')
                ->first();
            $productData = getProductDataByIc($result->master_policy_id);
            $productName = $productData->product_name;
            $productName = str_replace('-', '', $productName);
        } else {
            $ic_pdf_url =  config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE') . '?PolicyNo=' . $policy_number . '&ProductCode=' . $policy_detail->product_code;
        }
        if (config('constants.motorConstant.RELIANCE_GENERATE_POLICY_PDF') == 'Y') {
            try{                
                $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf';
                if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($pdf_name))
                {
                   Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($pdf_name);
                }
                if (config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE_NEW_API_ENABLE') == 'Y') {
                    $pdf_data = getWsData(
                        $ic_pdf_url,
                        $pdf_Request,
                        'reliance',
                        [
                            'root_tag' => 'GenerateScheduleRequest',
                            'section' => 'Cv',
                            'method' => 'Download policy PDF',
                            'enquiryId' => $user_product_journey_id,
                            'productName' => $productName.' ('.ucwords($result->business_type).')',
                            'requestMethod' => 'post',
                            'requestMethod' => 'post',
                            'transaction_type' => 'proposal',
                            'headers' => [
                                'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY'),
                                'Content-type' => 'text/xml'
                            ]
                        ]
                    );
                    $pdf_data = json_decode($pdf_data['response'], true);
                    if (empty($pdf_data['DownloadLink'])) {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);

                        $pdf_response_data = [
                            'status' => true,
                            'msg' => 'success',
                            'data' => [
                                'policy_number' => $policy_number,
                                'ic_pdf_url' => $ic_pdf_url,
                            ]
                        ];

                        return response()->json($pdf_response_data);
                    }
                    if (!empty($pdf_data['DownloadLink'])) {
                        $pdfGenerationResponse = httpRequestNormal($pdf_data['DownloadLink'], 'GET', [], [], [], [], false)['response'];
                        if (!checkValidPDFData($pdfGenerationResponse)) {
                            return response()->json([
                                'status' => false,
                                'msg' => 'Invalid pdf content',
                                'data' => [
                                    'policy_number' => $policy_number,
                                    'ic_pdf_url' => $pdf_data['DownloadLink'],
                                ]
                            ]);
                        }
                        Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/' . md5($policy_detail->user_proposal_id) . '.pdf', $pdfGenerationResponse);
                    }
                } else {
                    $pdf_data = httpRequestNormal($ic_pdf_url, 'GET', [], [], [], [], false);
                    if (!checkValidPDFData($pdf_data['response'])) {
                        updateJourneyStage([
                            'user_product_journey_id' => $user_product_journey_id,
                            'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                        ]);

                        $pdf_response_data = [
                            'status' => true,
                            'msg' => 'success',
                            'data' => [
                                'policy_number' => $policy_number,
                                'ic_pdf_url' => $ic_pdf_url,
                            ]
                        ];

                        return response()->json($pdf_response_data);
                    }

                    if (isset($pdf_data['status']) && $pdf_data['status'] == 200) {
                        Storage::put(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/' . md5($policy_detail->user_proposal_id) . '.pdf', $pdf_data['response']);
                    }
                }
               
                sleep(1); // instantly reading PDF will cause to blank pdf thats why 1 sec delay

                PaymentRequestResponse::where('user_proposal_id', $policy_detail->user_proposal_id)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);
            }
            catch(Exception $e)
            {
                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey_id,
                    'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ]);
                
                $pdf_response_data = [
                    'status' => true,
                    'msg' => 'success',
                    'data' => [
                        'policy_number' => $policy_number,
                        'ic_pdf_url' => $ic_pdf_url,
                    ]
                ];
            }
        }

        PolicyDetails::updateOrCreate(
            ['proposal_id' => $policy_detail->user_proposal_id],
            [
                'policy_number' => $policy_number,
                'ic_pdf_url' => $ic_pdf_url,
                'pdf_url' => config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf',
                'status' => 'SUCCESS'
            ]
        );

        $pdf_url = file_url(config('constants.motorConstant.CV_PROPOSAL_PDF_URL') . 'reliance/'. md5($policy_detail->user_proposal_id). '.pdf');

        $pdf_response_data = [
            'status' => true,
            'msg' => 'success',
            'data' => [
                'policy_number' => $policy_number,
                'pdf_link' => $pdf_url,
                'ic_pdf_url' => $ic_pdf_url,
            ]
        ];

        updateJourneyStage([
            'user_product_journey_id' => $user_product_journey_id,
            'stage' => STAGE_NAMES['POLICY_ISSUED']
        ]);

        return response()->json($pdf_response_data);
    }

    public static function finsallRehitService($user_product_journey_id, $proposal_no){

        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();

        if(empty($proposal) || empty($proposal_no))
        {
            $response = [
                'status' => false,
                'message' => STAGE_NAMES['PAYMENT_FAILED']
            ];
        }else{
            $paymentStatusService = FinsallController::paymentStatus($proposal, $proposal_no);
    
            if($paymentStatusService['status'])
            {
                PaymentRequestResponse::where('order_id', $proposal_no)
                ->where('active', 1)
                ->update([
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                ]);

                $request = (object)[];
                $request->txnRefNo = $paymentStatusService['txnRefNo'];
    
                $response = RelianceFinsallController::reliancePaymentCheck($request, $proposal);
            }
            else
            {
                $response = [
                    'status' => false,
                    'message' => STAGE_NAMES['PAYMENT_FAILED']
                ];
            }
        }
        return $response;
    }

    public static function iscurrentPDFDataValid($policy_detail, $userProposal)
    {

        $current_policy_url = Storage::url($policy_detail->pdf_url);
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $current_policy_data = httpRequestNormal($current_policy_url, 'GET', [], [], [], [], false)['response'];

        $endTime = new DateTime(date('Y-m-d H:i:s'));
        $responseTime = $startTime->diff($endTime);

        $wsLogdata = [
            'enquiry_id'     => $userProposal->user_product_journey_id,
            'product'       => '',
            'section'       => 'Cv',
            'method_name'   => 'current PDF Service - PDF',
            'company'       => 'reliance',
            'method'        => 'get',
            'transaction_type' => 'proposal',
            'request'       => $ic_pdf_url ?? '',
            'response'      => ((is_string($current_policy_data)) ? base64_encode($current_policy_data) : base64_encode(json_encode($current_policy_data))),
            'endpoint_url'  => $current_policy_url,
            'ip_address'    => request()->ip(),
            'start_time'    => $startTime->format('Y-m-d H:i:s'),
            'end_time'      => $endTime->format('Y-m-d H:i:s'),
            // 'response_time'	=> $responseTime->format('%H:%i:%s'),
            'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'    => Carbon::now(),
            'headers'       => null
        ];

        WebServiceRequestResponse::create($wsLogdata);

        WebserviceRequestResponseDataOptionList::firstOrCreate([
            'company' => 'reliance',
            'section' => 'Cv',
            'method_name' => 'current PDF Service - PDF',
        ]);
        
        return (!(is_string($current_policy_data)) || !checkValidPDFData($current_policy_data));
    }
}
