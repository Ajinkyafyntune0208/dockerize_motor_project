<?php

namespace App\Http\Controllers\Proposal\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\KotakPremiumDetailController;

class kotakSubmitProposal extends Controller
{
    public static function submit($proposal, $request) {
 
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $quote_log   = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($requestData->business_type != 'newbusiness') {
            $prev_insu_comp_name =  $requestData->previous_insurer;
            $prev_comp_code =  $requestData->previous_insurer_code;
        } 

        $policy_start_date =   $cpa_required =  $vPAODTenure = '';
        $paUnnamedPersonCoverselection  =  'false';
        $paUnnamedPersonCoverinsuredAmount =  '0';

        if ($requestData->vehicle_owner_type == "C") {
            $cpa_required = 'false';
            $vPAODTenure = '0';
        }
        $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;

        if($requestData->business_type == 'newbusiness') {
            $policy_start_date = '';
            $bike_claims_made = '';
            $vMarketMovement = '-15';
            $PolicyTerm = '1';
            $previousInsurerPolicyExpiryDate = '';
            $bIsNoPrevInsurance = '1';
            $prev_no_claim_bonus_percentage = '0';
            $no_claim_bonus_percentage = '0';
            $current_ncb = '0';
            $vPAODTenure = '5';
            $cpa_required = 'true';
            $Policy_Start_Date = date('Y-m-d', strtotime('+1 day', strtotime(date('Y-m-d'))));
            $Policy_End_Date = date('Y-m-d', strtotime('+5 year -1 day', strtotime(date('Y-m-d'))));
        } else if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $previousInsurerPolicyExpiryDate = strtr(date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)), '-', '/');
            $Policy_Start_Date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $Policy_End_Date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($Policy_Start_Date)));
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_diff > 0) {   
                $previousInsurerPolicyExpiryDate = Carbon::now()->addDays(3)->format('d/m/Y');
                $Policy_Start_Date = Carbon::now()->addDays(1)->format('Y-m-d');
                $Policy_End_Date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($Policy_Start_Date)));
            }
            $vMarketMovement = '-35';
            $PolicyTerm = '1';
            if ($requestData->is_claim == 'N') {
                $bike_claims_made = '';
                $prev_no_claim_bonus_percentage = $requestData->previous_ncb;
                $total_claim = 0;
                $one_year_claim =  0;

                if ($date_diff > 90 || $requestData->previous_policy_type == 'Third-party') {
                    $prev_no_claim_bonus_percentage = 0 ;
                }
            } else { 
                $bike_claims_made = '1 OD Claim';
                $prev_no_claim_bonus_percentage = '0';
                $total_claim = 1;
                $one_year_claim =  1;
            }
            $vPAODTenure = '1';
            $cpa_required = 'true';
            
        }

        if($premium_type == 'third_party')
        {
            $prev_no_claim_bonus_percentage = 0;
        }
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $compulsory_personal_accident = $selected_addons->compulsory_personal_accident;
        if (!empty($compulsory_personal_accident)) {
            foreach ($compulsory_personal_accident as $key => $data)  {
                if (isset($data['reason']))  {
                    $vPAODTenure = '0';
                    $cpa_required = 'false';
                }else if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                    $tenure = '1';
                    $vPAODTenure = isset($data['tenure']) ? (string) $data['tenure'] :$tenure;
                }
            }
        }

        foreach($additional_covers as $key => $value) {
            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $paUnnamedPersonCoverselection = 'true';
                $paUnnamedPersonCoverinsuredAmount = $value['sumInsured'];
            }
        }

        $additional_details = json_decode($proposal->additional_details);
        $vehicleDetails = $additional_details->vehicle;

        if ($vehicleDetails->isVehicleFinance == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType;
            $finance_comp_code = $vehicleDetails->nameOfFinancer;
            $Financing_Institution_Name = DB::table('kotak_financier_master')
                        ->where('code', $finance_comp_code)
                        ->pluck('name')
                        ->first();
        }

        $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
        if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10)) {
            $registration_no = $reg_no[0] . '0' . $reg_no[1];
        } else {
            $registration_no = $reg_no[0] . '' . $reg_no[1];
        }
                    
        $veh_reg_no = explode('-', isset($proposal->vehicale_registration_number) && $proposal->vehicale_registration_number != 'NEW'  ? $proposal->vehicale_registration_number : $requestData->rto_code);

        if (isset($veh_reg_no[2]) && $veh_reg_no[2] != '' && isset($veh_reg_no[3]) && $veh_reg_no[3] != '') {
            if (strlen($veh_reg_no[3]) == 3) {
                $veh_reg_no[3] = '0' . $veh_reg_no[3];
            } else if (strlen($veh_reg_no[3]) == 2) {
                $veh_reg_no[3] = '00' . $veh_reg_no[3];
            } else if (strlen($veh_reg_no[3]) == 1) {
                $veh_reg_no[3] = '000' . $veh_reg_no[3];
            }
            $Veh_Regno = $veh_reg_no[0] . '-' . $veh_reg_no[1] . '-' . $veh_reg_no[2] . '-' . $veh_reg_no[3];
            if (strlen($veh_reg_no[1]) == 1) {
                $new_veh_regno = $veh_reg_no[0] . '-0' . $veh_reg_no[1] . '-' . $veh_reg_no[2] . '-' . $veh_reg_no[3];
            }
        } else {
            $Veh_Regno = 'NEW';
        }
        unset($reg_no);

        $rto_data = DB::table('kotak_bike_rto_location')
                    ->where('NUM_REGISTRATION_CODE', str_replace('-', '', $registration_no))
                    ->first();
        $rto_data = keysToLower($rto_data);

        $mmv = get_mmv_details($productData,$requestData->version_id,$productData->company_alias);
        if($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $POS_PAN_NO = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->idv <= 5000000) {
            if($pos_data) {
                $POS_PAN_NO = $pos_data->pan_no;
            }
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_KOTAK') == 'Y' && $quote_log->idv <= 5000000)
        {
            $POS_PAN_NO    = 'ABGTY8890Z';
        }

        if(config('constants.motorConstant.KOTAK_BIKE_MMV_TESTING') == 'Y')
        {
            $mmv_data->num_product_code      = 3191;
            $mmv_data->vehicle_class_code    = 45;
            $mmv_data->manufacturer_code     = '10090';
            $mmv_data->manufacturer          = 'TVS';
            $mmv_data->num_parent_model_code 	= '2116705';
            $mmv_data->vehicle_model 			= 'APACHE';
            $mmv_data->variant_code 			= '2116714';
            $mmv_data->txt_variant 			= 'SELF START';
            $mmv_data->number_of_wheels 		= 2;
            $mmv_data->cubic_capacity 		= '150';
            $mmv_data->gross_vehicle_weight 	= 0;
            $mmv_data->seating_capacity 		= 2 ;
            $mmv_data->carrying_capacity 	= 2 ;
            $mmv_data->tab_row_index 		= 100;
            $mmv_data->body_type_code 		= 0;
            $mmv_data->txt_model_cluster 	= 'CATEGORY 1';
            $mmv_data->txt_fuel 				= 'Petrol';
            $mmv_data->txt_segment_type 		= 'MOTOR CYCLE';
            $mmv_data->num_exshowroom_price 	= 50260;
            $mmv_data->UW_Status 			= 'Active';
            $mmv_data->ic_version_code 		= 2118681;
        }

        $tokenData = getKotakTokendetails('bike');

        $token_req_array = [
                'vLoginEmailId' => $tokenData['vLoginEmailId'],
                'vPassword' => $tokenData['vPassword'],
        ];

        $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'), $token_req_array, 'kotak', [
            'Key' => $tokenData['vRanKey'],
            'headers' => [
                'vRanKey' => $tokenData['vRanKey']
            ],
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'kotak',
            'section' => $productData->product_sub_type_code,
            'method' =>'Token Generation',
            'transaction_type' => 'proposal',
        ]);
        $data = $get_response['response'];

        if ($data) {
            $token_response = json_decode($data, true);
            if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                $premium_req_array = [
                    "vUserLoginId" => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'),
                    "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_BIKE_INTERMEDIARY_CODE'),
                    "bIsRollOver" => ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') ? 'true' : 'false',
                    "nSelectedMakeCode" => $mmv_data->manufacturer_code,
                    "vSelectedMakeDesc" => $mmv_data->manufacturer,
                    "nSelectedModelCode" => $mmv_data->num_parent_model_code,
                    "vSelectedModelDesc" => $mmv_data->vehicle_model,
                    "nSelectedVariantCode" => $mmv_data->variant_code,
                    "vSelectedVariantDesc" => $mmv_data->txt_variant,
                    "nSelectedVariantSeatingCapacity" => $mmv_data->seating_capacity,
                    "vSelectedVariantModelCluster" => $mmv_data->txt_model_cluster,
                    "nSelectedVariantCubicCapacity" => $mmv_data->cubic_capacity,
                    "vSelectedModelSegment" => $mmv_data->txt_segment_type,
                    "vSelectedFuelTypeDescription" => $mmv_data->txt_fuel,
                    "nSelectedRTOCode" => $rto_data->txt_rto_location_code,
                    "vSelectedRegistrationCode" => $rto_data->num_registration_code,
                    "vSelectedRTOCluster" => $rto_data->txt_rto_cluster,
                    "vSelectedRTOAuthorityLocation" => $rto_data->txt_rto_location_desc,
                    "vRTOStateCode" => $rto_data->num_state_code,
                    "vPurchaseDate" => date('d/m/Y', strtotime($vehicleDate)),
                    "dSelectedRegDate" => strtr($requestData->vehicle_register_date, '-', '/'),
                    "dSelectedPreviousPolicyExpiryDate" => $requestData->business_type == 'newbusiness' ? strtr($requestData->vehicle_register_date, '-', '/') : strtr($previousInsurerPolicyExpiryDate, '-', '/'),
                    "bIsNoPrevInsurance" => $requestData->business_type == 'newbusiness' ? 'true' : 'false',
                    "bIsNoPrevInsurance" => $requestData->business_type == 'newbusiness' ? 'true' : 'false',
                    "nTotalClaimCount" => $requestData->business_type == 'newbusiness' ? '0' : $total_claim,
                    "nClaimCount1Year" => $requestData->business_type == 'newbusiness' ? '0' : $one_year_claim,
                    "nClaimCount2Year" => $requestData->business_type == 'newbusiness' ? '0' : '0',
                    "nClaimCount3Year" => $requestData->business_type == 'newbusiness' ? '0' : '0',

                    "nSelectedPreviousPolicyTerm" => $requestData->business_type == 'newbusiness' ? '0' : '1',
                    "nSelectedNCBRate" => $prev_no_claim_bonus_percentage,
                    "vSelectedPrevInsurerCode" => $requestData->business_type == 'newbusiness' ? '' : $prev_comp_code,
                    "vSelectedPrevInsurerDesc" => $requestData->business_type == 'newbusiness' ? '' : $prev_insu_comp_name,
                    "vSelectedPrevPolicyType" => $requestData->business_type == 'newbusiness' ? '' : "Comprehensive",
                    "nSelectedRequiredPolicyTerm" => $PolicyTerm,
                    "bIsNonElectAccessReq" => 'false',
                    "nNonElectAccessSumInsured" => "0",
                    "bIsElectAccessReq" => 'false',
                    "nElectAccessSumInsured" => "0",
                    "bIsSideCar" => 'false',
                    "nSideCarSumInsured" => "0",
                    "bIsPACoverForUnnamed" => $paUnnamedPersonCoverselection,##$acc_cover_unnamed_passenger == "" ? 'false' : 'true',
                    "nPACoverForUnnamedSumInsured" =>  $paUnnamedPersonCoverinsuredAmount,##$acc_cover_unnamed_passenger == "" ? '0' : $acc_cover_unnamed_passenger,
                    "vCustomerType" => $requestData->vehicle_owner_type ? $requestData->vehicle_owner_type : "C",
                    "nRequestIDV" => (string)$quote_log->idv,
                    "nMarketMovement" => $vMarketMovement,
                    "nResponseCreditScore" => "0",
                    "bIsFlaProcessActive" => 'false',
                    "bIsCreditScoreOpted" => 'false',
                    "bIsNewCustomer" => 'false',
                    "vCSCustomerFirstName" => $requestData->vehicle_owner_type == 'I' ? $proposal->first_name : "",
                    "vCSCustomerLastName" => $requestData->vehicle_owner_type == 'I' ? $proposal->last_name : "",
                    "dCSCustomerDOB" => $requestData->vehicle_owner_type == 'I' ? date('d/m/Y', strtotime(strtr($proposal->dob, '-', '/'))) : "",
                    // "nCSCustomerGender" => $requestData->vehicle_owner_type == "I" ? ($proposal->gender == 'F' ? '2' : '1') : "",
                    "vCSCustomerMobileNo" => $requestData->vehicle_owner_type == 'I' ? $proposal->mobile_number : '',
                    "vCSCustomerPincode" => $requestData->vehicle_owner_type == 'I' ? $proposal->pincode : '',
                    "vCSCustomerIdentityProofType" => "1",
                    "vCSCustomerIdentityProofNumber" => $proposal->pan_number ? $proposal->pan_number : '',
                    "vOfficeName" => config('constants.IcConstants.kotak.KOTAK_BIKE_OFFICE_NAME'),
                    "nOfficeCode" => config('constants.IcConstants.kotak.KOTAK_BIKE_OFFICE_CODE'),
                    "vProductTypeODTP" => ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') ? "1022" : "1066",
                    "bIsCompulsoryPAWithOwnerDriver" => $cpa_required,
                    "vPAODTenure" => $requestData->vehicle_owner_type == 'C' ? '0' : $vPAODTenure,
                    "nManufactureYear" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    "bIsLossAccessoriesReq" => 'false',
                    "nLossAccessSumInsured" => "0",
                    "vAPICustomerId" => "",
                    "vPosPanCard" => $POS_PAN_NO,
                    // "IsPartnerRequest" => true,
                ];
                if($requestData->vehicle_owner_type == "I") {
                    $premium_req_array['nCSCustomerGender'] = $proposal->gender == 'F' ? '2' : '1';
                 }
                 if($requestData->business_type == 'rollover' || 'newbusiness')
                 {
                     $premium_req_array["nProductCode"] ='3191';
                     if($premium_type == 'third_party')
                     {
                         $premium_req_array["nProductCode"]='3192';
                     }
                 }

                /* if ($proposal->is_ckyc_verified != 'Y') { */
                    $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_BIKE_PREMIUM'), $premium_req_array, 'kotak', [
                        'token' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,   
                        'company'  => 'kotak',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Premium Calculation',
                        'transaction_type' => 'proposal',
                    ]);
                    $data = $get_response['response'];

                    if ($data) {
                        $res_array = array_change_key_case_recursive(json_decode($data, true));
                        $premium_res_array = $res_array['twowheelerresponsewithcover'];

                        if (!isset($res_array['errormessage']) && $res_array['errormessage'] == '' && $premium_res_array['verrormessage'] == '' && $premium_res_array['nnetpremium'] != '') {
                            $tp = 0;
                            $od = 0;
                            $zero_dep_cover = 0;
                            $pa_owner = 0;
                            $llpaiddriver = 0;
                            $pa_unnamed = 0;
                            $NCB = 0;

                            if (isset($premium_res_array['nbasictppremium'])) {
                                $tp = ($premium_res_array['nbasictppremium']);
                            }

                            if (isset($premium_res_array['npacoverforownerdriverpremium'])) {
                                $pa_owner = ($premium_res_array['npacoverforownerdriverpremium']);
                            }

                            if (isset($premium_res_array['npatounnamedhirerpillionpassngrpremium'])) {
                                $pa_unnamed = ($premium_res_array['npatounnamedhirerpillionpassngrpremium']);
                            }

                            if (isset($premium_res_array['nowndamagepremium'])) {
                                $od = ($premium_res_array['nowndamagepremium']);
                            }

                            if (isset($premium_res_array['nnoclaimbonusdiscount'])) {
                                $NCB = ($premium_res_array['nnoclaimbonusdiscount']);
                            }

                            $addon_premium =  0;                              
                            $final_od_premium = $od ;
                            $final_tp_premium = $tp;
                            $total_discount = $NCB ;
                            $final_net_premium = ($premium_res_array['nnetpremium']);
                            $final_gst_amount = ($premium_res_array['ngstamount']);
                            $final_payable_amount  = $premium_res_array['ntotalpremium'];
                            
                            $vehicleDetails = [
                                'manufacture_name' => $mmv_data->manufacturer,
                                'model_name' => $mmv_data->vehicle_model,
                                'version' => $mmv_data->txt_variant,
                                'fuel_type' => $mmv_data->txt_fuel,
                                'seating_capacity' => $mmv_data->seating_capacity,
                                'carrying_capacity' => $mmv_data->seating_capacity - 1,
                                'cubic_capacity' => $mmv_data->cubic_capacity,
                                'gross_vehicle_weight' => $mmv_data->gross_vehicle_weight,
                                'vehicle_type' => 'Bike'
                            ];

                            UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                ->update([
                                'od_premium' => $final_od_premium,
                                'tp_premium' => $final_tp_premium,
                                'ncb_discount' => $NCB,
                                'total_discount' => $total_discount,
                                'addon_premium' => $addon_premium,
                                'total_premium' => $premium_res_array['ntotalpremium'],
                                'service_tax_amount' => $premium_res_array['ngstamount'],
                                'final_payable_amount' => $final_payable_amount,
                                'cpa_premium' => $pa_owner,
                                'customer_id' => $premium_res_array['vworkflowid'], ## workflowid as customer_id
                                'unique_proposal_id' => $premium_res_array['vquoteid'],
                                'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $Policy_Start_Date))),
                                'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $Policy_End_Date))),
                                'ic_vehicle_details' => $vehicleDetails
                            ]);

                            KotakPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                        
                        } else {
                            $error_msg = isset($res_array['errormessage']) ? $res_array['errormessage'] : 'Error while processing request';
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' =>  $error_msg ,
                            ]);
                        }
                    } else {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Insurer not reachable',
                        ]);
                    }
                /* } */

                $vtotalpremium = $premium_res_array['vtotalpremium'] ?? $proposal->total_premium;
                $vquoteid = $premium_res_array['vquoteid'] ?? $proposal->unique_proposal_id;
                $vworkflowid = $premium_res_array['vworkflowid'] ?? $proposal->customer_id;
                $kyc_url = '';
                $is_kyc_url_present = false;
                $kyc_message = '';
                $kyc_status = true;

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $ckyc_controller = new CkycController;

                    $ckyc_modes = [
                        'ckyc_number' => 'ckyc_number',
                        'pan_card' => 'pan_number_with_dob',
                        'aadhar' => 'aadhar'
                    ];

                    $ckyc_response_data = $ckyc_controller->ckycVerifications(new Request([
                        'companyAlias' => 'kotak',
                        'enquiryId' => $request['enquiryId'],
                        'mode' => $ckyc_modes[$proposal->ckyc_type],
                        'quoteId' => $vquoteid
                    ]));

                    $ckyc_response = $ckyc_response_data->getOriginalContent();

                    if ($ckyc_response['status']) {
                        $kyc_url = '';
                        $is_kyc_url_present = false;
                        $kyc_message = '';
                        $kyc_status = true;

                        $ckyc_response['data']['customer_details'] = [
                            'name' => $ckyc_response['data']['customer_details']['fullName'] ?? null,
                            'mobile' => $ckyc_response['data']['customer_details']['mobileNumber'] ?? null,
                            'dob' => $ckyc_response['data']['customer_details']['dob'] ?? null,
                            'address' => null, // $ckyc_response['data']['customer_details']['address'],
                            'pincode' => null, // $ckyc_response['data']['customer_details']['pincode'],
                            'email' => $ckyc_response['data']['customer_details']['email'] ?? null,
                            'pan_no' => $ckyc_response['data']['customer_details']['panNumber'] ?? null,
                            'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
                        ];

                        $ckyc_response_to_save['response'] = $ckyc_response;

                        $updated_proposal = $ckyc_controller->saveCkycResponseInProposal(new Request([
                            'company_alias' => 'kotak',
                            'trace_id' => $request['enquiryId']
                        ]), $ckyc_response_to_save, $proposal);

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->update($updated_proposal);

                        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                    } else {
                        $kyc_url = $ckyc_response['data']['redirection_url'];
                        $is_kyc_url_present = true;
                        $kyc_message = '';
                        $kyc_status = false;

                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                                'additional_details_data' => $ckyc_response['data']['redirection_url'],
                            ]);
                    }
                }
                        
                if ($kyc_status) {

                    $veh_reg_no[1] = $veh_reg_no[1] ?? '';
                    if(($veh_reg_no[0] == 'DL') && ((intval($veh_reg_no[1]) < 10) && (strlen($veh_reg_no[1]) == 1))) {
                        $veh_reg_no[1] = '0'.$veh_reg_no[1];
                    }
                    $org_type = $requestData->vehicle_owner_type;
                    //kotak bike split api
                    $proposal_req = [
                        "objTwoWheelerSaveProposalRequest" => [
                            "vUserLoginId" => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'),
                            "vWorkFlowID" => $vworkflowid,#in customer_id workflowid is saved 
                            "vQuoteID" => $vquoteid,
                            "objCustomerDetails" => [
                                "vCustomerId" => "",
                                "vCustomerType" => $org_type,
                                "vIDProof" => "0",
                                "vIDProofDetails" => "",
                                "vCustomerFirstName" => ($org_type == 'I' ? $proposal->first_name : ''),
                                "vCustomerMiddleName" => "",
                                "vCustomerLastName" => ($org_type == 'I' ? (!empty($proposal->last_name) ?  $proposal->last_name : '.') : ''),
                                "vCustomerEmail" => ($org_type == 'I' ? $proposal->email : ''),
                                "vCustomerMobile" => ($org_type == 'I' ? $proposal->mobile_number : ''),
                                "vCustomerDOB" => ($org_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : ''),
                                "vCustomerSalutation" => ($org_type == 'I' ? ($proposal->gender == 'F' ? ($proposal->marital_status == 'Single' ? 'MISS' : 'MRS') : 'MR') : ''),
                                "vCustomerGender" => ($org_type == 'I' ? ($proposal->gender == 'F' ? 'FEMALE' : 'MALE') : ''),
                                "vOccupationCode" => "1",
                                "vCustomerPanNumber" => ($org_type == 'I' ? $proposal->pan_number : ''),
                                "vMaritalStatus" => ($org_type == 'I' ? $proposal->marital_status  : ''),
                                "vCustomerPincode" => ($org_type == 'I' ? $proposal->pincode : ''),
                                //"vCustomerPincodeLocality" => "",
                                //"vCustomerStateCd" => "",
                                //"vCustomerStateName" => "",
                                //"vCustomerCityDistrict" => "",
                                //"vCustomerCityDistrictCd" => "",
                                //"vCustomerCity" => "",
                                //"vCustomerCityCd" => "",
                                "vCustomerAddressLine1" => ($org_type == 'I' ?  $proposal->address_line1 : ''),
                                "vCustomerAddressLine2" => ($org_type == 'I' ? $proposal->address_line2 : ''),
                                "vCustomerAddressLine3" => ($org_type == 'I' ? $proposal->address_line3 : ''),
                                "vOrganizationAddressLine2" => ($org_type != 'I' ? $proposal->address_line2 : ''),
                                "vOrganizationAddressLine3" => ($org_type != 'I' ? $proposal->address_line3 : ''),
                                "vCustomerCRNNumber" => "",
                            ],
                            "vNomineeName" => ($org_type == 'I'  ? $proposal->nominee_name : ''),
                            "vNomineeDOB" => ($org_type == 'I'  ? date('d/m/Y', strtotime($proposal->nominee_dob)) : ''),
                            "vNomineeRelationship" => ($org_type == 'I' ? $proposal->nominee_relationship : ''),
                            "vNomineeAppointeeName" => "",
                            "vNomineeAppointeeRelationship" => "",
                            "vRMCode" => "",
                            "vBranchInwardNumber" => "",
                            "dBranchInwardDate" => date('d/m/Y'),
                            "vCustomerCRNNumber" => "",
                            "bIsVehicleFinanced" => $proposal->is_vehicle_finance == '1' ? 'true' : 'false', //false ????????????
                            "vFinancierAddress" => $proposal->is_vehicle_finance == '1' ? $proposal->financer_location : '',
                            "vFinancierAgreementType" => $proposal->is_vehicle_finance == '1' ? 'Hypothecation' : '',
                            "vFinancierCode" => $proposal->is_vehicle_finance == '1' ? $proposal->name_of_financer : '',
                            "vFinancierName" => $proposal->is_vehicle_finance == '1' ? $Financing_Institution_Name : '', # fetch name from code
                            "vRegistrationNumber1" => $veh_reg_no[0] ?? '',
                            "vRegistrationNumber2" => $veh_reg_no[1] ?? '',
                            "vRegistrationNumber3" => $veh_reg_no[2] ?? '',
                            "vRegistrationNumber4" => $veh_reg_no[3] ?? '',
                            "vEngineNumber" => $proposal->engine_number,
                            "vChassisNumber" => $proposal->chassis_number,
                            "vPrevInsurerCode" => $proposal->previous_insurance_company,
                            "vPrevInsurerExpiringPolicyNumber" => $proposal->previous_policy_number,
                            "vPreInspectionNumber" => "",
                        ]
                    ];

                    if ($org_type == 'C') {
                        $proposal_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationName'] = $proposal->first_name;
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationContactName'] = $proposal->first_name;
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationEmail'] = $proposal->email;
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationMobile'] = $proposal->mobile_number;
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationPincode'] = $proposal->pincode;
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationAddressLine1'] = $proposal->address_line1 . '' . $proposal->address_line2 . '' . $proposal->address_line3;
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationTANNumber'] = '';
                        $proposal_payment_req['objTwoWheelerSaveProposalRequest']['objCustomerDetails']['vOrganizationGSTNumber'] = $proposal->gst_number;;
                    }
                                
                    $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_BIKE_PROPOSAL'), $proposal_req, 
                    'kotak', [
                        'token' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'kotak',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'bike Proposal Service',
                        'transaction_type' => 'proposal',
                    ]);

                    $proposal_service_response = $get_response['response'];
                    $proposal_response  = json_decode($proposal_service_response, true);

                    if(isset($proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vErrorMessage']) && $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vErrorMessage'] == 'Success')
                    {
                        $proposalUpdate = [
                            'additional_details_data' => json_encode($proposal_response),
                            'total_premium' => (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vTotalPremium'])),
                            'final_payable_amount' => (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vTotalPremium'])),
                            'proposal_no' => $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vProposalNumber'] ?? $proposal->proposal_no ?? null,
                        ];

                        if (!empty($proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vGSTAmount'])) {
                            $proposalUpdate['service_tax_amount'] = (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vGSTAmount']));
                        } else {
                            $proposalUpdate['service_tax_amount'] = ($proposalUpdate['total_premium'] * (18 / (100 + 18)));
                        }

                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update($proposalUpdate);

                        updateJourneyStage([
                            'user_product_journey_id' => $enquiryId,
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $enquiryId,
                                'proposalNo' => $proposal->proposal_no,
                                'finalPayableAmount' => $vtotalpremium,
                                'is_breakin' => '',
                                'inspection_number' => '',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                                'kyc_status' => $kyc_status,
                            ]
                        ]);
                    }else{
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => (isset($proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vErrorMessage']) && $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vErrorMessage'] !== 'Success') ? $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vErrorMessage'] : (isset($proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vWarningMsg']) ? $proposal_response['Fn_Save_Partner_Two_Wheeler_ProposalResult']['vWarningMsg']:'Error while processing Proposal Service request'),
                        ]);
                    }
                }  else {
                    return response()->json([
                        'status' => true,
                        'msg' => "Proposal Submitted Successfully!",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => [
                            'proposalId' => null,
                            'userProductJourneyId' => $enquiryId,
                            'proposalNo' => null,
                            'finalPayableAmount' => $vtotalpremium,
                            'is_breakin' => '',
                            'inspection_number' => '',
                            'kyc_url' => $kyc_url,
                            'is_kyc_url_present' => $is_kyc_url_present,
                            'kyc_message' => $kyc_message,
                            'kyc_status' => $kyc_status,
                        ]
                    ]);
                }
            } else {
                return response()->json([
                    'premium_amount' => 0,
                    'status'         => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'        => $token_response['vErrorMsg']
                ]);
            }
        } else {
            return response()->json([
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => 'Insurer not reachable'
            ]);
        }
    }
}
