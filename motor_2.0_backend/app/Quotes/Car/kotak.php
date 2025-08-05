<?php

use App\Models\SelectedAddons;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }
    $NewDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
	$first_reg_date = new DateTime($NewDate);
	$today = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
	$diff = $today->diff($first_reg_date);
    $car_years = $diff->y;
    $car_months = $diff->m;
    $car_days = $diff->d;
    $car_age = $car_years . '.' . $car_months;
    $current_date = date('Y-m-d');
    $expdate = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
    $vehicle_in_60_days = get_date_diff('day',$current_date,$expdate);
    $premium_type = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
    if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
        $addons = $selected_CPA->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                
            }
        }
    }
    // dd($cpa_tenure);

    if (($car_years >= 20) && ($tp_check == 'true')){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 20 year',
        ];
    }
    if ($requestData->business_type == 'rollover' &&  $vehicle_in_60_days > 60){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Future Policy Expiry date is allowed only upto 60 days',
            'request' => [
                'car_age' => $car_age,
                'date_duration_in_days' => $vehicle_in_60_days,
                'message' => 'Future Policy Expiry date is allowed only upto 60 days',
            ]
        ];
        }
	//     if (($car_years == 4) && ($car_months > 5) && ($productData->zero_dep == 0)) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero dep is not allowed for vehicle age greater than 4.5 years',
    //         'request' => [
    //             'car_age' => $car_age,
    //             'car_months' => $car_months,
    //             'zero_dep' => $productData->zero_dep,
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 4.5 years',
    //         ]
    //     ];
    // } else if (($car_years == 4) && ($car_months == 5) && ($car_days > 0) && ($productData->zero_dep == 0)) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero dep is not allowed for vehicle age greater than 4.5 years',
    //         'request' => [
    //             'car_age' => $car_age,
    //             'car_months' => $car_months,
    //             'car_days' => $car_days,
    //             'zero_dep' => $productData->zero_dep,
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 4.5 years',
    //         ]
    //     ];
    // } 
     if (($car_years > 11) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])) {

        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age greater than 11.10 years',
            'request' => [
                'car_age' => $car_age,
                'zero_dep' => $productData->zero_dep,
                'message' => 'Vehicle age greater than 11.10 years',
            ]
        ];
    } else if (($car_years == 11) && ($car_months > 10) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age greater than 11.10 years',
            'request' => [
                'car_age' => $car_age,
                'car_months' => $car_months,
                'zero_dep' => $productData->zero_dep,
                'message' => 'Vehicle age greater than 11.10 years',
            ]
        ];
    } else if (($car_years == 11) && ($car_months == 10) && ($car_days > 0) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age greater than 11.10 years',
            'request' => [
                'car_age' => $car_age,
                'car_months' => $car_months,
                'car_days' => $car_days,
                'zero_dep' => $productData->zero_dep,
                'message' => 'Vehicle age greater than 11.10 years',
            ]
        ];
    } else if ($requestData->rto_code == 'RJ-14') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'This RTO is not available with the Insurance Company',
            'request' => [
                'rto_code' => $requestData->rto_code,
                'message' => 'This RTO is not available with the Insurance Company',
            ]
        ];
    } else {
        if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
            $is_pos_disabled_renewbuy = 'Y'; 
        }else{
        $is_pos_disabled_renewbuy = config('constants.motorConstant.IS_POS_DISABLED_RENEWBUY');
        }
        $is_pos     = ($is_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_flag = false;
        
        $POS_PAN_NO = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $is_pos_flag = true;
                $POS_PAN_NO = $pos_data->pan_no;
            }
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_KOTAK') == 'Y')
        {
            $POS_PAN_NO    = 'ABGTY8890Z';
        }
    	/* 
    	#this code needs to be take care of in laravel format    4th sep 2021 - Deepak
    	$CI = &get_instance();
        $car_insu_id = $request_data['car_insu_id'];
        $POS_PAN_NO = '';
        if (file_exists(APPPATH . 'logs/car_quotes/' . $request_data['quote'] . '/posp.txt') !== false) {
            $agent_id = file_get_contents(APPPATH . 'logs/car_quotes/' . $request_data['quote'] . '/posp.txt');
            $agent_data = $CI->db->get_where('agents', ['agent_id' => $agent_id])
                ->row_array();
            $POS_PAN_NO = $agent_data['pan_no'];
        } else {
            $POS_PAN_NO = '';
        }
        // */

        // DISABLING POS JOURNEY AND PROCEEDING WITH NON_POS
        if (in_array('kotak', explode(',', config('POS_DISABLED_ICS')))) {
            $is_pos_flag = false;
            $POS_PAN_NO = '';
        }
        
        //Removing age validation for all iC's  #31637 
        // if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin' || $premium_type == 'comprehensive' || $premium_type == 'breakin') {
        //     if ($requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party') {
    
        //         return  [
        //             'premium_amount' => 0,
        //             'status' => false,
        //             'message' => 'Quotes not available when previous policy type is not sure or third party',
        //         ];
        //     }
        // }
       
        //As per RB requirment, Quote block in case of Zero NCB #25457
        if($requestData->business_type == 'rollover' && $requestData->applicable_ncb == 0 && config('constants.motorConstant.QOUTE_BLOCK_FOR_ZERO_NCB_ROLLOVER_RENEWBUY') == 'Y'){
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quotes is not available for Non NCB when business type is Rollover.', 
                'request' => [
                    'message' => 'Quotes is not available for Non NCB when business type is Rollover.',
                ]
            ];
        }

        $mmv = get_mmv_details($productData,$requestData->version_id,'kotak');

        if($mmv['status'] == 1) {
          $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv,
                ]
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv_data' => $mmv_data,
                    'message' => 'Vehicle Not Mapped',
                ]
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv_data' => $mmv_data,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ]
            ];
        } else {
            $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
            if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10) && strlen($reg_no[1]) < 2) {
                $permitAgency = $reg_no[0] . '0' . $reg_no[1];
            } else {
                $permitAgency = $reg_no[0] . '' . $reg_no[1];
            }

            $permitAgency = isBhSeries($permitAgency) ? $requestData->rto_code : $permitAgency;

            $rto_data = DB::table('kotak_rto_location')
                        ->where('NUM_REGISTRATION_CODE', str_replace('-', '', $permitAgency))
                        ->first();
            $rto_data = keysToLower($rto_data);
            if (!empty($rto_data) && strtoupper($rto_data->pvt_uw) == 'ACTIVE') {

                $motor_claims_made = $prev_policy_end_date = $policy_start_date = $policy_end_date =  $no_claim_bonus_percentage = $prev_no_claim_bonus_percentage = $PrevInsurer =  $vPAODTenure = '';

                $nonElectricalCoverSelection = $electricalCoveSelection = $cngCoverSelection = $isIMT28 = $paUnnamedPersonCoverselection =  $isPACoverPaidDriverSelected = $isDepreciationCover = 'false';
                $nonElectricalCoverInsuredAmount = $electricalCoverInsuredAmount = $cngCoverInsuredAmount = $voluntary_deductible_amount = $isPACoverPaidDriverAmount = $paUnnamedPersonCoverinsuredAmount = 0;
                $rsa_selected  = $rti = $EngineProtect = $ConsumableCover = 'false';

                // Addons And Accessories
                $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
                $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
                $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
                $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

                // car age calculation
                $date1 = new DateTime($requestData->vehicle_register_date);
                $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
                $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                $interval = $date1->diff($date2);
                $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
                $car_age = round($age / 12, 2);
               
                //Removing age validation as per Nirmal sir.
                if (trim($productData->product_identifier == "Kotak RSA"))
                {
                    $ConsumableCover = 'false';
                    $rsa_selected = 'true';
                    $rti = 'false';
                    $EngineProtect = 'false';
                    $isDepreciationCover = 'false';
                }
                else if(trim($productData->product_identifier) == 'Kotak CZDR')
                {
                    $ConsumableCover = 'true';
                    $rsa_selected = 'false';
                    $rti = 'false';
                    $EngineProtect = 'false';
                    $isDepreciationCover = 'false';
                } else if (trim($productData->product_identifier) == 'Kotak ECZDR')
                {
                    $ConsumableCover = 'false';
                    $rsa_selected = 'false';
                    $rti = 'false';
                    $EngineProtect = 'true';
                    $isDepreciationCover = 'false';
                } else if (trim($productData->product_identifier) == 'Kotak RTCZDR')
                {
                    $ConsumableCover = 'false';
                    $rsa_selected = 'false';
                    $rti = 'true';
                    $EngineProtect = 'false';
                    $isDepreciationCover = 'false';
                }   
                else if (trim($productData->product_identifier) == 'Kotak RTCZDREP')
                {
                    $ConsumableCover = 'false';
                    $rsa_selected = 'false';
                    $rti = 'true';
                    $EngineProtect = 'true';
                    $isDepreciationCover = 'false';
                }

                $isKeyReplacement           = 'true';
                $KeyReplacementSI           = 25000;

                if(strtoupper(config('constants.motorConstant.kotak.IS_LOPB_ENABLED', 'N')) == 'Y')
                {
                    $isLossPersonalBelongings   = 'true';
                    $LossPersonalBelongingsSI   = 10000;
                }
                else
                {
                    $isLossPersonalBelongings   = 'false';
                    $LossPersonalBelongingsSI   = 0;
                }

                $tyreSecure                 = (($car_age < 1.5) && $tp_check == 'false') ? 'true' :'false';
                $tyreSecureSI               = (($car_age < 1.5) && $tp_check == 'false') ? Carbon::createFromFormat('d-m-Y',$requestData->vehicle_register_date)->format('Y') : ''; // As per IC passing registration year
               
                #need to confirm business type for breakin
                if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {

                    $policy_end_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
                    $prev_policy_end_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
                    $vMarketMovement = ($requestData->business_type == 'breakin') ? '-6.5' : '-10';
                    // $vPAODTenure = '1';
                    $vPAODTenure = isset($cpa_tenure) ? $cpa_tenure : '1';
                    $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
                    $prev_no_claim_bonus_percentage = $NCBEligibilityCriteria == '1' ? "0" : $requestData->previous_ncb;

                    if ($requestData->is_claim == 'N') {
                        $prev_no_claim_bonus_percentage = $requestData->previous_ncb;
                        if ($requestData->previous_policy_type == 'Third-party') {
                            $no_claim_bonus_percentage = '0';
                            $prev_no_claim_bonus_percentage = '0';
                        }
                    } else {
                        $motor_claims_made = '1 OD Claim';
                        $prev_no_claim_bonus_percentage = '0';
                    }

                    $PrevInsurer = [
                        "vPrevInsurerCode" => "OICL",
                        "vPrevPolicyType" =>  $requestData->previous_policy_type == 'Third-party' ? "LiabilityOnlyPolicy" : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? '1+3' : "ComprehensivePolicy"),
                        "vPrevInsurerDescription" => "ORIENTAL INSURANCE",
                    ];

                    $vBusinessType = 'R';
                    $bIsNoPrevInsurance = '0';
                    $vProductTypeODTP = '1011';
                } else if ($requestData->business_type == 'newbusiness') {
                    $prev_no_claim_bonus_percentage = '0';
                    $vMarketMovement = '-10';
                    //$vPAODTenure = '3';
                    // $vPAODTenure = '1'; // By Default CPA will be 1 Year
                    $vPAODTenure = isset($cpa_tenure) ? $cpa_tenure : '3';
                    $vBusinessType = 'N';
                    $bIsNoPrevInsurance = '1';
                    $vProductTypeODTP = '1063';
                }


                foreach ($accessories as $key => $value) {

                    if (in_array('Electrical Accessories', $value)) {
                        $electricalCoveSelection = 'true';
                        $electricalCoverInsuredAmount = $value['sumInsured'];
                    }

                    if (in_array('Non-Electrical Accessories', $value)) {
                        $nonElectricalCoverSelection = 'true';
                        $nonElectricalCoverInsuredAmount = $value['sumInsured'];
                    }

                    if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                        $cngCoverSelection = 'true';
                        $cngCoverInsuredAmount = $value['sumInsured'];
                    }

                }

                foreach($additional_covers as $key => $value) {
                    if (in_array('LL paid driver', $value)) {
                        $isIMT28 = 'true';
                    }

                    if (in_array('PA cover for additional paid driver', $value)) {
                        $isPACoverPaidDriverSelected = 'true';
                        $isPACoverPaidDriverAmount = $value['sumInsured'];
                    }

                    if (in_array('Unnamed Passenger PA Cover', $value)) {
                        $paUnnamedPersonCoverselection = 'true';
                        $paUnnamedPersonCoverinsuredAmount = $value['sumInsured'];
                    }
                }

                foreach ($discounts as $key => $value) {
                    if (in_array('voluntary_insurer_discounts', $value)) {
                        if(isset( $value['sumInsured'] ) ){
                            $voluntary_deductible_amount = $value['sumInsured'];
                        }
                    }
                }
                $tokenData = getKotakTokendetails('motor', $is_pos_flag);
                $token_req_array = [
                    'vLoginEmailId' => $tokenData['vLoginEmailId'],
                    'vPassword' => $tokenData['vPassword'],
                ];

                // $data = cache()->remember('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR', 10, function() use ($token_req_array, $tokenData, $enquiryId, $productData){
                    $data =  getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'), $token_req_array, 'kotak', [
                            'Key' => $tokenData['vRanKey'],
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'kotak',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Token Generation',
                            'transaction_type' => 'quote',
                        ]); 
                    // });
                $user_id = $is_pos_flag ? config('constants.IcConstants.kotak.KOTAK_MOTOR_POS_USERID') : config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID');

                if ($data['response']) {
                    $token_response = json_decode($data['response'], true);
                    if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                        // update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Token Generation Success Success", "Success" );
                        $premium_req_array = [
                            "vIdProof" => "",
                            "vIdProofDetail" => "",
                            "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                            "vIntermediaryName" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE_NAME'),
                            "vManufactureCode" => $mmv_data->manufacturer_code,
                            "vManufactureName" => $mmv_data->manufacturer,
                            "vModelCode" => $mmv_data->num_parent_model_code,
                            "vModelDesc" => $mmv_data->vehicle_model,
                            "vVariantCode" => $mmv_data->variant_code,
                            "vVariantDesc" => $mmv_data->txt_variant,
                            "vModelSegment" => $mmv_data->txt_segment_type,
                            "vSeatingCapacity" => $mmv_data->seating_capacity,
                            "vFuelType" => $mmv_data->txt_fuel,
                            "isLPGCNGChecked" => $cngCoverSelection,
                            "vLPGCNGKitSI" => $cngCoverInsuredAmount,
                            "isElectricalAccessoriesChecked" => $electricalCoveSelection,
                            "vElectricalAccessoriesSI" => $electricalCoverInsuredAmount,
                            "isNonElectricalAccessoriesChecked" => $nonElectricalCoverSelection,
                            "vNonElectricalAccessoriesSI" => $nonElectricalCoverInsuredAmount,
                            "vRegistrationDate" => strtr($requestData->vehicle_register_date, '-', '/'),
                            "vRTOCode" => $rto_data->txt_rto_location_code,
                            "vRTOStateCode" => $rto_data->num_state_code,
                            "vRegistrationCode" => $rto_data->num_registration_code,
                            "vRTOCluster" => $rto_data->txt_rto_cluster,
                            "vRegistrationZone" => $rto_data->txt_registration_zone,
                            "vModelCluster" => $mmv_data->txt_model_cluster,
                            "vCubicCapacity" => $mmv_data->cubic_capacity,
                            "isReturnToInvoice" => $rti,
                            "isRoadSideAssistance" => $rsa_selected,
                            "isEngineProtect" => $EngineProtect,
                            "isDepreciationCover" => $isDepreciationCover,
                            "isKeyReplacement"          => $isKeyReplacement,
                            "KeyReplacementSI"          => $KeyReplacementSI,
                            "isTyreCover" => $tyreSecure,
                            "TyreCoverSI" => $tyreSecureSI,
                            "isLossPersonalBelongings"  => $isLossPersonalBelongings,
                            "LossPersonalBelongingsSI"  => $LossPersonalBelongingsSI,
                            "nVlntryDedctbleFrDprctnCover" => '0',
                            "isConsumableCover" => $ConsumableCover,
                            "isPACoverUnnamed" => $paUnnamedPersonCoverselection,
                            "vPersonUnnamed" => $paUnnamedPersonCoverselection == 'true' ? $mmv_data->seating_capacity: "0",
                            "vUnNamedSI" => $paUnnamedPersonCoverinsuredAmount,
                            "vMarketMovement" => $vMarketMovement,
                            "isPACoverPaidDriver" => $isPACoverPaidDriverSelected,
                            "vPACoverPaidDriver" => $isPACoverPaidDriverSelected == 'true' ? "1" : "0",
                            "vSIPaidDriver" => $isPACoverPaidDriverAmount,
                            "isIMT28" => $isIMT28,
                            "isIMT29" => "false",
                            "vPersonIMT28" => "1",
                            "vPersonIMT29" => "0",
                            "vBusinessType" => $vBusinessType,
                            "vPolicyStartDate" => $policy_start_date,
                            "vPreviousPolicyEndDate" => strtr($prev_policy_end_date, '-', '/'),
                            "vProductType" => in_array($requestData->policy_type, ['third_party_breakin', 'third_party']) ? "LiabilityOnlyPolicy" : "ComprehensivePolicy",
                            "vClaimCount" => $motor_claims_made,
                            "vClaimAmount" => 0,
                            "vNCBRate" => $prev_no_claim_bonus_percentage,
                            "vWorkflowId" => "",
                            "vFinalIDV" => "0",
                            "objCustomerDetails" => [
                                "vCustomerType" => $requestData->vehicle_owner_type == 'I' ? "I" : "C",
                                "vCustomerLoginId" => $user_id,
                                "vCustomerVoluntaryDeductible" => $voluntary_deductible_amount,
                                "vCustomerGender" => "",
                            ],
                            "objPrevInsurer" => $PrevInsurer,
                            "bIsCreditScoreOpted" => "0",
                            "bIsNewCustomer" => "0",
                            "vCSCustomerFirstName" => "",
                            "vPurchaseDate" => date('d/m/Y', strtotime($vehicleDate)),
                            "vCSCustomerLastName" => "",
                            "dCSCustomerDOB" => "",
                            "nCSCustomerGender" => "1",
                            "vCSCustomerPANNumber" => "",
                            "vCSCustomerMobileNo" => "",
                            "vCSCustomerPincode" => "",
                            "vCSCustomerIdentityProofType" => "1",
                            "vCSCustomerIdentityProofNumber" => '',
                            "nOfficeCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_OFFICE_CODE'),
                            "vOfficeName" => config('constants.IcConstants.kotak.KOTAK_MOTOR_OFFICE_NAME'),
                            "bIsNoPrevInsurance" => $bIsNoPrevInsurance,
                            "vPreviousYearNCB" => $prev_no_claim_bonus_percentage,
                            "vRegistrationYear" => date('Y', strtotime($requestData->vehicle_register_date)),
                            "vProductTypeODTP" => $vProductTypeODTP,
                            "vPAODTenure" => $requestData->vehicle_owner_type == 'C' ? '0' : $vPAODTenure,
                            "vPosPanCard" => $POS_PAN_NO,
                            "IsPartnerRequest" => true,
                        ];

                        if ( ! $is_pos_flag) {
                            unset($premium_req_array['vPosPanCard']);
                        }

                        if($premium_type == "own_damage")
                        {
                            $premium_req_array['dPreviousTPPolicyExpiryDate']=date('d/m/Y',strtotime('+2 year +1 day', strtotime($requestData->previous_policy_expiry_date)));;
                            $premium_req_array['dPreviousTPPolicyStartDate']=date('d/m/Y',strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                            $premium_req_array['vPrevTPInsurerCode']='BHARTI AXA';
                            $premium_req_array['vPrevTPInsurerExpiringPolicyNumber']='PHJHyyyHJ12313';
                            $premium_req_array['vPrevTPInsurerName']='BHARTI AXA';
                            $premium_req_array['vProductType']='ODOnly';
                            $premium_req_array['vProductTypeODTP']='';
                            $premium_req_array['nProductCode']="3151";
                            $premium_req_array['vPAODTenure']="0";
                            $premium_req_array['vCustomerPrevPolicyNumber']="OD909908126";
                        }
                        if ($premium_type=='own_damage_breakin') {
                            $premium_req_array['dPreviousTPPolicyExpiryDate']=date('d/m/Y',strtotime('+1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));;
                            $premium_req_array['dPreviousTPPolicyStartDate']=date('d/m/Y',strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                            $premium_req_array['vPrevTPInsurerCode']='BHARTI AXA';
                            $premium_req_array['vPrevTPInsurerExpiringPolicyNumber']='PHJHyyyHJ12313';
                            $premium_req_array['vPrevTPInsurerName']='BHARTI AXA';
                            $premium_req_array['nProductCode']="3151";
                            $premium_req_array['vPAODTenure']="0";
                            $premium_req_array['vCustomerPrevPolicyNumber']="OD909908126";
                        }
                        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin')
                        {
                            if(strtolower($requestData->previous_policy_expiry_date) == "new" && $requestData->business_type == "newbusiness")
                            {
                                $requestData->previous_policy_expiry_date = Carbon::now()->addday();
                            }
                            else
                            {
                                $premium_req_array['dPreviousTPPolicyExpiryDate'] = date('d/m/Y',strtotime(empty($requestData->previous_policy_expiry_date) ? Carbon::now() : $requestData->previous_policy_expiry_date));
                                $premium_req_array['dPreviousTPPolicyStartDate'] = date('d/m/Y',strtotime('-1 year +1 day', strtotime(empty($requestData->previous_policy_expiry_date) ? Carbon::now() : $requestData->previous_policy_expiry_date)));
                            }
                            $premium_req_array['vPrevTPInsurerCode'] = 'BHARTI AXA';
                            $premium_req_array['vPrevTPInsurerExpiringPolicyNumber'] = 'PHJHyyyHJ12313';
                            $premium_req_array['vPrevTPInsurerName'] = 'BHARTI AXA';
                            $premium_req_array['nProductCode'] = "3176";
                            // $premium_req_array['vPAODTenure'] = "1"; 
                            $premium_req_array['vPAODTenure'] = $vPAODTenure; 
                            $premium_req_array['vCustomerPrevPolicyNumber'] = "OD909908126";
                            $premium_req_array['isNonElectricalAccessoriesChecked'] = 'false';
                            $premium_req_array['isElectricalAccessoriesChecked'] = 'false';
                            $premium_req_array['vNonElectricalAccessoriesSI'] = 0;
                            $premium_req_array['vElectricalAccessoriesSI'] = 0;
                            // $premium_req_array['vPAODTenure'] = $requestData->vehicle_owner_type == 'C' ? '0' : '1';
                            $premium_req_array['vPAODTenure'] = $requestData->vehicle_owner_type == 'C' ? '0' : $vPAODTenure;
                            if ($premium_type == 'third_party_breakin') {
                                $premium_req_array['dPreviousTPPolicyExpiryDate'] = "";
                                $premium_req_array['dPreviousTPPolicyStartDate'] = "";
                                $premium_req_array['vPrevTPInsurerCode'] = "";
                                $premium_req_array['vPrevTPInsurerName'] = "";
                                $premium_req_array['vCustomerPrevPolicyNumber'] = "";
                            }
                        }

                        if($requestData->vehicle_owner_type == 'C'){
                            $premium_req_array['isIMT29'] = true;
                            $premium_req_array['vPersonIMT29'] = $mmv_data->seating_capacity - 1;
                        }

                        $temp_data = $premium_req_array;
                        $temp_data['product_identifier'] = $productData->product_identifier;
                        $temp_data['type'] = 'Premium Calculation';
                        $temp_data['zero_dep'] = $productData->zero_dep;
                        $checksum_data = checksum_encrypt($temp_data);
                        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'kotak', $checksum_data, "CAR");
                        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                            $data = $is_data_exist_for_checksum;
                        }else{
                        $data = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PREMIUM') . '/' . $user_id, $premium_req_array, 'kotak', [
                            'token' => $token_response['vTokenCode'],
                            'headers' => [
                                'vTokenCode' => $token_response['vTokenCode']
                            ],
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'kotak',
                            'checksum' =>$checksum_data,
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Premium Calculation',
                            'transaction_type' => 'quote',
                        ]);
                    }
                        if ($data['response']) {
                            $response = json_decode($data['response'], true);
                            if (isset($response['vErrorMsg']) && $response['vErrorMsg'] == 'Success' && $response['vNetPremium'] != '') {
                                $idv = ($response['vFinalIDV']);
                                //CHANGES AS PER GIT https://github.com/Fyntune/motor_2.0_backend/issues/30003
                                // $idv_min = (string) ceil(0.9 * $response['vFinalIDV']);
                                $idv_min = (string) ceil(config('KOTAK_CAR_MIN_IDV_PERCENTAGE', 0.9) * $response['vFinalIDV']);
                                // $idv_max = (string) floor(1.15 * $response['vFinalIDV']);
                                $idv_max = (string) floor(config('KOTAK_CAR_MAX_IDV_PERCENTAGE', 1.15) * $response['vFinalIDV']);
                                $skip_second_call = false;
                                if ($requestData->is_idv_changed == 'Y') {
                                    if ($requestData->edit_idv >= $idv_max) {
                                        $premium_req_array['vFinalIDV'] = $idv_max;
                                        $vehicle_idv = $idv_max;
                                    } elseif ($requestData->edit_idv <= $idv_min) {
                                        $premium_req_array['vFinalIDV'] = $idv_min;
                                        $vehicle_idv = $idv_min;
                                    } else {
                                        $premium_req_array['vFinalIDV'] = $requestData->edit_idv;
                                        $vehicle_idv = $requestData->edit_idv;
                                    }
                                } else {
                                    #$premium_req_array['vFinalIDV'] = $idv_min;
                                    //new idv code
                                    $getIdvSetting = getCommonConfig('idv_settings');
                                    switch ($getIdvSetting) {
                                        case 'default':
                                            $premium_req_array['vFinalIDV'] = $idv;
                                            $vehicle_idv = $idv;
                                            $skip_second_call = true;
                                            break;
                                        case 'min_idv':
                                            $premium_req_array['vFinalIDV'] = $idv_min;
                                            $vehicle_idv = $idv_min;
                                            break;
                                        case 'max_idv':
                                            $premium_req_array['vFinalIDV'] = $idv_max;
                                            $vehicle_idv = $idv_max;
                                            break;
                                        default:
                                        $premium_req_array['vFinalIDV'] = $idv_min;
                                        $vehicle_idv = $idv_min;
                                            break;
                                    }
                                }

                    if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $vehicle_idv>= 5000000)
                    {
                       $premium_req_array ['vPosPanCard'] = '';
                    } elseif(!empty($pos_data))
                    {
                        $premium_req_array ['vPosPanCard'] = $pos_data->pan_no;
                    }
                            if(!$skip_second_call){
                                $temp_data = $premium_req_array;
                                $temp_data['product_identifier'] = $productData->product_identifier;
                                $temp_data['type'] = 'Premium Re-Calculation';
                                $temp_data['zero_dep'] = $productData->zero_dep;
                                $checksum_data = checksum_encrypt($temp_data);
                                $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'kotak', $checksum_data, 'CAR');

                                if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                                    $data = $is_data_exist_for_checksum;
                                }else{
                                    $data = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PREMIUM') . '/' . $user_id, $premium_req_array, 'kotak', [
                                        'token' => $token_response['vTokenCode'],
                                        'headers' => [
                                            'vTokenCode' => $token_response['vTokenCode']
                                        ],
                                        'enquiryId' => $enquiryId,
                                        'requestMethod' =>'post',
                                        'productName'  => $productData->product_name,
                                        'company'  => 'kotak',
                                        'checksum'  => $checksum_data,
                                        'section' => $productData->product_sub_type_code,
                                        'method' =>'Premium Calculation',
                                        'transaction_type' => 'quote',
                                    ]);
                                }
                            }
                                if ($data['response']) {
                                    $response = json_decode($data['response'], true);

                                    if ($response['vErrorMsg'] == 'Success' && $response['vNetPremium'] != '') {
                                        $idv = ($response['vFinalIDV']);
                                    } else {
                                        return [
                                            'webservice_id' => $data['webservice_id'],
                                            'table' => $data['table'],
                                            'premium_amount' => 0,
                                            'status' => false,
                                            'message' => isset($response['vErrorMsg']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $response['vErrorMsg']) : 'Error while processing request',
                                        ];
                                    }
                                } else {
                                    return [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                        'premium_amount' => 0,
                                        'status' => false,
                                        'message' => 'Insurer not reachable',
                                    ];
                                }
                                // }

                                $tp =  $od = $rsa = $zero_dep = $consumable = $eng_protect = $rti = $electrical_accessories = $non_electrical_accessories = $lpg_cng = $lpg_cng_tp = $pa_owner = $llpaiddriver = $pa_unnamed = $paid_driver = $voluntary_deduction_zero_dep = $paid_driver_tp =
                                $NCB = $legal_liability_to_employee = 0;
                                $geog_Extension_OD_Premium = 0;
                                $geog_Extension_TP_Premium = 0;       
                                $LossPersonalBelongingsPremium = $KeyReplacementPremium = $tyreSecurePremium = 0;
                                if (isset($response['vBasicTPPremium'])) {
                                    $tp = ($response['vBasicTPPremium']);
                                }
                                if (isset($response['vPACoverForOwnDriver'])) {
                                    $pa_owner = ($response['vPACoverForOwnDriver']);
                                }
                                if (isset($response['vPAForUnnamedPassengerPremium'])) {
                                    $pa_unnamed = ($response['vPAForUnnamedPassengerPremium']);
                                }
                                if (isset($response['vCngLpgKitPremiumTP'])) {
                                    $lpg_cng_tp = ($response['vCngLpgKitPremiumTP']);
                                }
                                if (isset($response['vPANoOfEmployeeforPaidDriverPremium'])) {
                                    $paid_driver = ($response['vPANoOfEmployeeforPaidDriverPremium']);
                                }
                                if (isset($response['vPaidDriverlegalliability'])) {
                                    $paid_driver_tp = ($response['vPaidDriverlegalliability']);
                                }
                                if (isset($response['vLegalLiabilityPaidDriverNo'])) {
                                    $llpaiddriver = ($response['vLegalLiabilityPaidDriverNo']);
                                }
                                if (isset($response['vDepreciationCover'])) {
                                    $zero_dep = ($response['vDepreciationCover']);
                                }
                                if (isset($response['vRSA'])) {
                                    $rsa = ($response['vRSA']);
                                }
                                if (isset($response['vEngineProtect'])) {
                                    $eng_protect = ($response['vEngineProtect']);
                                }
                                if (isset($response['vConsumableCover'])) {
                                    $consumable = ($response['vConsumableCover']);
                                }
                                if (isset($response['vReturnToInvoice'])) {
                                    $rti = ($response['vReturnToInvoice']);
                                }
                                if (isset($response['vElectronicSI'])) {
                                    $electrical_accessories = ($response['vElectronicSI']);
                                }
                                if (isset($response['vNonElectronicSI'])) {
                                    $non_electrical_accessories = ($response['vNonElectronicSI']);
                                }
                                if (isset($response['vCngLpgKitPremium'])) {
                                    $lpg_cng = ($response['vCngLpgKitPremium']);
                                }
                                if (isset($response['vVoluntaryDeductionDepWaiver'])) {
                                    $voluntary_deduction_zero_dep = ($response['vVoluntaryDeductionDepWaiver']);
                                }
                                if (isset($response['vOwnDamagePremium'])) {
                                    $od = ($response['vOwnDamagePremium']);
                                }
                                if (isset($response['vNCB'])) {
                                    $NCB = ($response['vNCB']);
                                }
                                if (isset($response['vLLEOPDCC'])) {
                                    $legal_liability_to_employee = ($response['vLLEOPDCC']);
                                }
                                if (isset($response['nKeyReplacementPremium'])) {
                                    $KeyReplacementPremium = ($response['nKeyReplacementPremium']);
                                }
                                if (isset($response['nLossPersonalBelongingsPremium'])) {
                                    $LossPersonalBelongingsPremium = ($response['nLossPersonalBelongingsPremium']);
                                }
                                if (isset($response['nTyreCoverPremium'])) {
                                    $tyreSecurePremium = ($response['nTyreCoverPremium']);
                                }

                                $allowed_quote = (($productData->zero_dep == 0) && ($zero_dep == 0)) ? false : true;
                                $applicable_addons = [ ];
                                if ((trim($productData->product_identifier) == "Kotak RSA")) 
                                {
                                    $add_ons_data = [
                                        'in_built' => [
                                            'road_side_assistance' => (float)$rsa,
                                        ],
                                        'additional' => [],
                                        'other' => [],
                                    ];
                                    array_push($applicable_addons,"roadSideAssistance");
                                } 
                                elseif ($productData->zero_dep == 0 && (trim($productData->product_identifier) == "Kotak CZDR")) 
                                {
                                    $add_ons_data = [
                                        'in_built' => [
                                            'zero_depreciation' => (float)$zero_dep,
                                            'road_side_assistance' => (float)$rsa,
                                            'consumables' => (float)$consumable,
                                        ],
                                        'additional' => [],
                                        'other' => [],
                                    ];
                                    array_push($applicable_addons, "zeroDepreciation", "roadSideAssistance", "consumables");
                                } 
                                elseif ($productData->zero_dep == 0 && (trim($productData->product_identifier) == "Kotak ECZDR")) 
                                {
                                    $add_ons_data = [
                                        'in_built' => [
                                            'engine_protector' => (float)$eng_protect,
                                            'zero_depreciation' => (float)$zero_dep,
                                            'road_side_assistance' => (float)$rsa,
                                            'consumables' => (float)$consumable,
                                        ],
                                        'additional' => [],
                                        'other' => [],
                                    ];
                                    array_push($applicable_addons, "engineProtector", "zeroDepreciation", "roadSideAssistance", "consumables");
                                } 
                                elseif ($productData->zero_dep == 0 && (trim($productData->product_identifier) == "Kotak RTCZDR")) 
                                {
                                    $add_ons_data = [
                                        'in_built' => [
                                            'return_to_invoice' => (float)($rti),
                                            'zero_depreciation' => (float)($zero_dep),
                                            'road_side_assistance' => (float)($rsa),
                                            'consumables' => (float)($consumable),
                                        ],
                                        'additional' => [],
                                        'other' => [],
                                    ];
                                    array_push($applicable_addons, "returnToInvoice", "zeroDepreciation", "roadSideAssistance", "consumables");
                                } 
                                elseif ($productData->zero_dep == 0 && (trim($productData->product_identifier) == "Kotak RTCZDREP")) 
                                {
                                    $add_ons_data = [
                                        'in_built' => [
                                            'engine_protector' => (float)$eng_protect,
                                            'return_to_invoice' => (float)($rti),
                                            'zero_depreciation' => (float)($zero_dep),
                                            'road_side_assistance' => (float)($rsa),
                                            'consumables' => (float)($consumable),
                                        ],
                                        'additional' => [],
                                        'other' => [],
                                    ];
                                    array_push($applicable_addons, "engineProtector", "returnToInvoice", "zeroDepreciation", "roadSideAssistance", "consumables");
                                } 
                                else 
                                {
                                    $add_ons_data = [
                                        'in_built' => [],
                                        'additional' => [
                                            'road_side_assistance' => (float)$rsa,
                                        ],
                                        'other' => [],
                                    ];
                                    array_push($applicable_addons, "roadSideAssistance");
                                }
                                $add_ons_data['additional']['keyReplace'] = (float) $KeyReplacementPremium;
                                $add_ons_data['additional']['lopb'] = (float) $LossPersonalBelongingsPremium;
                                $add_ons_data['additional']['tyreSecure'] = (float) $tyreSecurePremium;

                                if($KeyReplacementPremium > 0 && !in_array('keyReplace',$applicable_addons))
                                {
                                    array_push($applicable_addons, "keyReplace");
                                }
                                if($LossPersonalBelongingsPremium > 0 && !in_array('lopb',$applicable_addons))
                                {
                                    array_push($applicable_addons, "lopb");
                                }
                                if($tyreSecurePremium > 0 && !in_array('tyreSecure',$applicable_addons))
                                {
                                    array_push($applicable_addons, "tyreSecure");
                                }

                                if ($allowed_quote) {
                                    $add_ons = [];
                                    $total_premium = ($response['vTotalPremium']);
                                    $base_premium_amount = ($total_premium / (1 + ( 18.0 / 100)));


                                    foreach ($add_ons_data as $add_on_key => $add_on_value) {
                                        if (count($add_on_value) > 0) {
                                            foreach ($add_on_value as $add_on_value_key => $add_on_value_value) {
                                                if (is_numeric($add_on_value_value)) {
                                                    if ($add_on_value_value != '0' && $add_on_value_value != 0) {
                                                        $base_premium_amount -= $add_on_value_value;
                                                        $value = $add_on_value_value;
                                                    } else {
                                                        $value = 0;
                                                    }
                                                } else {
                                                    $value = $add_on_value_value;
                                                }
                                                $add_ons[$add_on_key][$add_on_value_key] = $value;
                                            }
                                        } else {
                                            $add_ons[$add_on_key] = $add_on_value;
                                        }
                                    }
                                    // ($request_data['motor_car_owner_type'] == 'C' ? '0' : ($response['vPACoverForOwnDriver']));

                                    $net_premium = $response['vNetPremium'];
                                    $final_gst_amount = $response['vGSTAmount'];
                                    array_walk_recursive($add_ons, function (&$item) {
                                        if ($item == '' || $item == '0') {
                                            $item = 0;
                                        }
                                    });
                                    $voluntary_deductible =  ($response['vVoluntaryDeduction'] ? $response['vVoluntaryDeduction'] : 0);
                                    $other_discount = ($productData->zero_dep == 0 ? $voluntary_deduction_zero_dep : '0');
                                    
                                    $addon_premium = array_sum($add_ons_data['additional']);
                                    //$od = $od - $addon_premium;
                                    $base_premium_amount = ($base_premium_amount * (1 + ( 18.0 / 100)));
                                    $final_od_premium = $od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
                                    $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver +  $paid_driver + $pa_unnamed + $paid_driver_tp + $legal_liability_to_employee; //$paid_driver_tp is for satp policy only and $paiddriver for comprehensive policy and bundle policy
                                    $final_total_discount =  $NCB + $other_discount + $voluntary_deductible;
                                    $policy_start_date = \Carbon\Carbon::createFromFormat('d/m/Y', $response['vPolicyStartDate'])->format('d-m-Y');
                                    $policy_end_date = \Carbon\Carbon::createFromFormat('d/m/Y', $response['vPolicyEndDate'])->format('d-m-Y');
                                    
                                    $return_data = [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                        'status' => true,
                                        'msg' => 'Found',
                                        'Data' => [
                                            'idv' => ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 0 : (int) $idv,
                                            'min_idv' => ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 0 : (int) $idv_min,
                                            'max_idv' => ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 0 : (int) $idv_max,
                                            'vehicle_idv' => $idv,
                                            'qdata' => null,
                                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                                            'addonCover' => null,
                                            'addon_cover_data_get' => '',
                                            'rto_decline' => null,
                                            'rto_decline_number' => null,
                                            'mmv_decline' => null,
                                            'mmv_decline_name' => null,
                                            'policy_type' =>($premium_type == 'third_party' ||  $premium_type == 'third_party_breakin') ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                                            // 'business_type' => 'Rollover',
                                            'cover_type' => '1YC',
                                            'hypothecation' => '',
                                            'hypothecation_name' => '',
                                            'vehicle_registration_no' => $requestData->rto_code,
                                            'rto_no' => $requestData->rto_code,
                                            'version_id' => $requestData->version_id,
                                            'selected_addon' => [],
                                            'showroom_price' => 0,
                                            'fuel_type' => $requestData->fuel_type,
                                            'ncb_discount' => $requestData->applicable_ncb,
                                            'company_name' => $productData->company_name,
                                            'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                                            'product_name' => $productData->product_sub_type_name.' '.trim($productData->product_identifier),
                                            'mmv_detail' => $mmv_data,
                                            'master_policy_id' => [
                                                'policy_id' => $productData->policy_id,
                                                'policy_no' => $productData->policy_no,
                                                'policy_start_date' => $policy_start_date,
                                                'policy_end_date' => $policy_end_date,
                                                'sum_insured' => $productData->sum_insured,
                                                'corp_client_id' => $productData->corp_client_id,
                                                'product_sub_type_id' => $productData->product_sub_type_id,
                                                'insurance_company_id' => $productData->company_id,
                                                'status' => $productData->status,
                                                'corp_name' => '',
                                                'company_name' => $productData->company_name,
                                                'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                                                'product_sub_type_name' => $productData->product_sub_type_name,
                                                'flat_discount' => $productData->default_discount,
                                                'predefine_series' => '',
                                                'is_premium_online' => $productData->is_premium_online,
                                                'is_proposal_online' => $productData->is_proposal_online,
                                                'is_payment_online' => $productData->is_payment_online,
                                            ],
                                            'motor_manf_date' => $requestData->vehicle_register_date,
                                            'vehicle_register_date' => $requestData->vehicle_register_date,
                                            'vehicleDiscountValues' => [
                                                'master_policy_id' => $productData->policy_id,
                                                'product_sub_type_id' => $productData->product_sub_type_id,
                                                'segment_id' => 0,
                                                'rto_cluster_id' => 0,
                                                'car_age' => $car_age,
                                                'aai_discount' => 0,
                                                'ic_vehicle_discount' => floatval(abs($other_discount)),
                                            ],
                                            'ic_vehicle_discount' => floatval(abs($other_discount)),
                                            'basic_premium' => floatval($od),
                                            'motor_electric_accessories_value' => floatval($electrical_accessories),
                                            'motor_non_electric_accessories_value' => floatval($non_electrical_accessories),
                                            'motor_lpg_cng_kit_value' => floatval($lpg_cng),
                                            'total_accessories_amount(net_od_premium)' => floatval($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                                            'total_own_damage' => floatval($final_od_premium),
                                            'tppd_premium_amount' => floatval($tp),
                                            'compulsory_pa_own_driver' => floatval($pa_owner), // Not added in Total TP Premium
                                            'cover_unnamed_passenger_value' => $pa_unnamed,
                                            'default_paid_driver' => $llpaiddriver,
                                            'motor_additional_paid_driver' => floatval(!empty($paid_driver) ? $paid_driver : $paid_driver_tp ?? 0),
                                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                                            'cng_lpg_tp' => floatval($lpg_cng_tp),
                                            'seating_capacity' => $mmv_data->seating_capacity,
                                            'deduction_of_ncb' => floatval(abs($NCB)),
                                            'antitheft_discount' => 0,
                                            'aai_discount' => 0,
                                            'voluntary_excess' => $voluntary_deductible,
                                            'other_discount' => floatval(abs($other_discount)),
                                            'total_liability_premium' => floatval($final_tp_premium),
                                            'net_premium' => floatval($net_premium),
                                            'service_tax_amount' => floatval($final_gst_amount),
                                            'service_tax' => 18,
                                            'total_discount_od' => 0,
                                            'add_on_premium_total' => 0,
                                            'addon_premium' => 0,
                                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                            'quotation_no' => '',
                                            'premium_amount' => floatval($total_premium),
                                            'service_data_responseerr_msg' => 'success',
                                            'user_id' => $requestData->user_id,
                                            'product_sub_type_id' => $productData->product_sub_type_id,
                                            'user_product_journey_id' => $requestData->user_product_journey_id,
                                            'business_type' =>  $requestData->business_type == 'newbusiness' ?  'New Business' : $requestData->business_type,
                                            'service_err_code' => null,
                                            'service_err_msg' => null,
                                            'policyStartDate' => $policy_start_date,
                                            'policyEndDate' => $policy_end_date,
                                            'ic_of' => $productData->company_id,
                                            'vehicle_in_90_days' => NULL,
                                            'get_policy_expiry_date' => null,
                                            'get_changed_discount_quoteid' => 0,
                                            'vehicle_discount_detail' => [
                                                'discount_id' => null,
                                                'discount_rate' => null,
                                            ],
                                            'is_premium_online' => $productData->is_premium_online,
                                            'is_proposal_online' => $productData->is_proposal_online,
                                            'is_payment_online' => $productData->is_payment_online,
                                            'policy_id' => $productData->policy_id,
                                            'insurane_company_id' => $productData->company_id,
                                            'max_addons_selection' => null,
                                            'add_ons_data'      => $add_ons,
                                            'applicable_addons' => $applicable_addons,
                                            'final_od_premium'  => floatval($final_od_premium),
                                            'final_tp_premium'  => floatval($final_tp_premium),
                                            'final_total_discount' => floatval(abs($final_total_discount)),
                                            'final_net_premium' => floatval($total_premium),
                                            'final_gst_amount'  => floatval($final_gst_amount),
                                            'final_payable_amount' => floatval($total_premium),
                                            'mmv_detail'    => [
                                                'manf_name'     => $mmv_data->manufacturer,
                                                'model_name'    => $mmv_data->vehicle_model,
                                                'version_name'  => $mmv_data->txt_variant,
                                                'fuel_type'     => $mmv_data->txt_fuel,
                                                'seating_capacity' => $mmv_data->seating_capacity,
                                                'carrying_capacity' => $mmv_data->carrying_capacity,
                                                'cubic_capacity' => $mmv_data->cubic_capacity,
                                                'gross_vehicle_weight' => '',
                                                'vehicle_type'  => 'Private Car',
                                            ],
                                            'user_id' => $user_id
                                        ],
                                    ];

                                    $included_additional = [
                                        'included' => []
                                    ];
                                    if (in_array($mmv_data->txt_fuel, ['CNG','LPG'])) {
                                        $return_data['Data']['motor_lpg_cng_kit_value'] = 0;
                                        $included_additional['included'][] = 'motorLpgCngKitValue';
                                    }
                                    $return_data['Data']['included_additional'] = $included_additional;
                                    if (!empty($legal_liability_to_employee)) {
                                        $return_data['Data']['other_covers'] = [
                                            'LegalLiabilityToEmployee' => round($legal_liability_to_employee)
                                        ];
                                        $return_data['Data']['LegalLiabilityToEmployee'] = round($legal_liability_to_employee);
                                    }
                                    if(isset($cpa_tenure)&&$requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
                                    {
                                        $return_data['Data']['multi_Year_Cpa'] = $pa_owner;
                                    }

                                } else {
                                    return [
                                        'webservice_id' => $data['webservice_id'],
                                        'table' => $data['table'],
                                        'premium_amount' => 0,
                                        'status' => false,
                                        'message' => 'Zero dep is not allowed for this vehicle',
                                    ];
                                }

                            } else {
                                return [
                                    'webservice_id' => $data['webservice_id'],
                                    'table' => $data['table'],
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => isset($response['vErrorMsg']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $response['vErrorMsg']) : 'Error while processing request',
                                ];
                            }

                        } else {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Insurer not reachable',
                            ];
                        }
                    } else {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => (isset($token_response['vErrorMsg']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_response['vErrorMsg']) : 'Error while processing request'),
                        ];
                    }

                } else {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => (isset($data['vErrorMsg'])) ? 'Insurer not reachable : ' . $data['vErrorMsg'] : 'Insurer not reachable : Error while processing request',
                    ];
                }

            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => (!empty($rto_data) && strtoupper($rto_data->pvt_uw) == 'DECLINED' ? 'RTO Declined' : 'RTO not available'),
                ];
            }
        }

        return camelCase($return_data);
        
    }
}

?>