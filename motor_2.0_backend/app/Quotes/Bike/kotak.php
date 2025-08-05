<?php
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
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
    $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $first_reg_date = new DateTime($NewDate);
 	$today = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
	$diff = $today->diff($first_reg_date);
    $bike_years = $diff->y;
    $bike_months = $diff->m;
    $bike_days = $diff->d;
    $bike_age = $bike_years . '.' . $bike_months;
    $return_data = [];
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    if (($bike_years >= 20) && ($tp_check == 'true')){
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 20 year',
            ];
    }
    if($premium_type == 'third_party_breakin')
    {
        $premium_type = 'third_party';
    }
    if (($bike_years > 9) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age greater than 9.9 years',
            'request'=> [
                'bike_years' => $bike_years
            ]
        ];
    } else if (($bike_years == 9) && ($bike_months > 10) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age greater than 9.9 years',
            'request'=> [
                'bike_years' => $bike_years
            ]
        ];
    } else if (($bike_years == 9) && ($bike_months == 10) && ($bike_days > 0) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age greater than 9.9 years',
            'request'=> [
                'bike_years' => $bike_years
            ]
        ];
    } else if ($productData->zero_dep == 0) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero Dept cover not available for thie vehicle',
            'request'=> [
                'bike_years' => $bike_years,
                'product_data' =>$productData->zero_dep
            ]
        ];
    } else {
        /* 
    	#this code needs to be take care of in laravel format    22nd sep 2021
        $POS_PAN_NO = '';
        if (file_exists(APPPATH . 'logs/bike_quotes/' . $request_data['quote'] . '/posp.txt') !== false) {
            $agent_id = file_get_contents(APPPATH . 'logs/bike_quotes/' . $request_data['quote'] . '/posp.txt');
            $agent_data = $CI->db->get_where('agents', ['agent_id' => $agent_id])
                ->row_array();
            $POS_PAN_NO = $agent_data['pan_no'];
        } else {
            $POS_PAN_NO = '';
        }
        // */

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $POS_PAN_NO = '';

        /* $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $POS_PAN_NO = $pos_data->pan_no;
            }
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_KOTAK') == 'Y')
        {
            $POS_PAN_NO    = 'ABGTY8890Z';
        } */


        $mmv = get_mmv_details($productData,$requestData->version_id,$productData->company_alias);
  
        if($mmv['status'] == 1) {
            $mmv_data = $mmv['data'];
            //kotak maintain different mmv uat & prod
           if(config('constants.motorConstant.KOTAK_BIKE_MMV_TESTING') == 'Y')
           {
                $mmv_data = 
                [
                    'num_product_code' => 3191,
                    'vehicle_class_code' => 45,
                    'manufacturer_code' => '10090',
                    'manufacturer' => 'TVS',
                    'num_parent_model_code' => '2116705',
                    'vehicle_model' => 'APACHE',
                    'variant_code' => '2116714',
                    'txt_variant' => 'SELF START',
                    'number_of_wheels' => 2,
                    'cubic_capacity' => '150',
                    'gross_vehicle_weight' => 0,
                    'seating_capacity' => 2 ,
                    'carrying_capacity' => 2 ,
                    'tab_row_index' => 100,
                    'body_type_code' => 0,
                    'txt_model_cluster' => 'CATEGORY 1',
                    'txt_fuel' => 'Petrol',
                    'txt_segment_type' => 'MOTOR CYCLE',
                    'num_exshowroom_price' => 50260,
                    'UW_Status' => 'Active',
                    'ic_version_code' => 2118681,
                ];
           }
            
          } else {
              return  [
                  'premium_amount' => 0,
                  'status' => false,
                  'message' => $mmv['message'],
                  'request'=>[
                    'mmv'=> $mmv,
                    'version_id'=>$requestData->version_id
                 ]
              ];
          }
        if (trim($mmv_data['ic_version_code']) == '' || $mmv_data['manufacturer_code'] == '' || !isset($mmv_data) || $mmv_data == '' || count($mmv_data) < 0) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle not mapped',
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id'=>$requestData->version_id
                 ]
            ];
        } elseif (trim($mmv_data['ic_version_code']) == 'DNE' || (isset($mmv_data['UW_Status']) && $mmv_data['UW_Status'] == 'Declined')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle is Declined or does not exist with insurance company',
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id'=>$requestData->version_id
                 ]
            ];
        } else {

            if ($mmv_data['cubic_capacity'] > 150) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle cubic capacity cannot be greater than 150',
                    'request'=>[
                        'mmv'=> $mmv_data,
                        'version_id'=>$requestData->version_id
                     ]
                ];
            }

            $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
            if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10)) {
                $permitAgency = $reg_no[0] . '0' . $reg_no[1];
            } else {
                $permitAgency = $reg_no[0] . '' . $reg_no[1];
            }

            if ($reg_no[0] == 'GJ' && $mmv_data['txt_segment_type'] == 'MOTOR CYCLE') {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Motorcycle Segment blocked for (Gujarat - GJ)',
                    'request'=> [
                        'rto_code' => $reg_no[0],
                        'segment_type'=>$mmv_data['txt_segment_type']
                    ]
                ];
            }

            $rto_data = DB::table('kotak_bike_rto_location')
                        ->where('NUM_REGISTRATION_CODE', str_replace('-', '', $permitAgency))
                        ->first();
            $rto_data = keysToLower($rto_data);
            $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
            if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
                $addons = $selected_CPA->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                            $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : 1;
                        
                    }
                }
            }
            if (!empty($rto_data) &&  strtoupper($rto_data->pvt_uw) == 'ACTIVE') {
                $bike_claims_made = $policy_end_date = $no_claim_bonus_percentage =  $current_ncb =  $prev_no_claim_bonus_percentage =  $vPAODTenure = '';
                $paUnnamedPersonCoverselection = 'false';
                $paUnnamedPersonCoverinsuredAmount = '0';

                if($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                                     
                    if(Carbon::parse($requestData->vehicle_register_date)->greaterThan(Carbon::parse('31-08-2018'))) {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Rollover scenario is not availble if registration date is greater than 31-08-2018',
                            'request'=> [
                                'registration_date' => $requestData->vehicle_register_date
                            ]
                        ];    
                    }
                    // $vPAODTenure = 1;
                    $vPAODTenure = isset($cpa_tenure) ? $cpa_tenure : 1;
                    $vMarketMovement = '-35';
                    $policy_end_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
                    if ($date_diff > 0) {
                        if ($reg_no[0] == 'DL') {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Delhi RTO not allowed for Break-in case',
                                'request'=> [
                                    'rto_code' => $reg_no[0]
                                ]
                            ]; //Delhi NCR blocked
                        }
                        $policy_end_date = Carbon::now()->addDays(3)->format('d/m/Y');
                        $policy_start_date = Carbon::now()->addDays(1)->format('Y-m-d');
                    }
                    if ($requestData->is_claim == 'N') {
                        $prev_no_claim_bonus_percentage = $requestData->previous_ncb;
                        if ($date_diff > 90 || $requestData->previous_policy_type == 'Third-party') {
                            $prev_no_claim_bonus_percentage = 0 ;
                        }
                        $total_claim = 0;
                        $one_year_claim = 0;
                    } elseif ($requestData->is_claim  == 'Y') {
                        $prev_no_claim_bonus_percentage = 0;
                        $no_claim_bonus_percentage = 0;
                        $current_ncb = 0;
                        $total_claim = 1;
                        $one_year_claim = 1;
                    }
                } else if ($requestData->business_type == 'newbusiness') {
                    $vMarketMovement = '-15';
                    // $vPAODTenure = 5;
                    $vPAODTenure = isset($cpa_tenure) ? $cpa_tenure : 5;
                    $prev_no_claim_bonus_percentage = 0;
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime(date('Y-m-d'))));
                    $policy_end_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime(date('Y-m-d'))));
                }

                if($premium_type == 'third_party')
                {
                    $prev_no_claim_bonus_percentage = 0;
                }
                $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
                $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

                foreach($additional_covers as $key => $value) {
                    if (in_array('Unnamed Passenger PA Cover', $value)) {
                        $paUnnamedPersonCoverselection = 'true';
                        $paUnnamedPersonCoverinsuredAmount = $value['sumInsured'];
                    }
                }

                $tokenData = getKotakTokendetails('bike');

                $token_req_array = [
                        'vLoginEmailId' => $tokenData['vLoginEmailId'],
                        'vPassword' => $tokenData['vPassword'],
                ];

                $get_response = cache()->remember('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE', 10 , function() use ($token_req_array, $tokenData, $enquiryId, $productData) {
                    return getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'), $token_req_array, 'kotak', [
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
                        'transaction_type' => 'quote',
                    ]);    
                });

                $data = $get_response['response'];
                

                if ($data) {
                    try {
                        $token_response = json_decode($data, true);
                        if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                            $voluntary_deductible_amount = isset($requestData->voluntary_excess_value) ? $requestData->voluntary_excess_value : 0;

                            $premium_req_array = [
                                "vUserLoginId" => config('constants.IcConstants.kotak.KOTAK_BIKE_USERID'), 
                                "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_BIKE_INTERMEDIARY_CODE'),
                                "bIsRollOver" => ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') ? 'true' : 'false',
                                "nSelectedMakeCode" => $mmv_data['manufacturer_code'],
                                "vSelectedMakeDesc" => $mmv_data['manufacturer'],
                                "nSelectedModelCode" => $mmv_data['num_parent_model_code'],
                                "vSelectedModelDesc" => $mmv_data['vehicle_model'],
                                "nSelectedVariantCode" => $mmv_data['variant_code'],
                                "vSelectedVariantDesc" => $mmv_data['txt_variant'],
                                "nSelectedVariantSeatingCapacity" => $mmv_data['seating_capacity'],
                                "vSelectedVariantModelCluster" => $mmv_data['txt_model_cluster'],
                                "nSelectedVariantCubicCapacity" => $mmv_data['cubic_capacity'],
                                "vSelectedModelSegment" => $mmv_data['txt_segment_type'],
                                "vSelectedFuelTypeDescription" => $mmv_data['txt_fuel'],
                                "nSelectedRTOCode" => $rto_data->txt_rto_location_code,
                                "vSelectedRegistrationCode" => $rto_data->num_registration_code,
                                "vSelectedRTOCluster" => $rto_data->txt_rto_cluster,
                                "vSelectedRTOAuthorityLocation" => $rto_data->txt_rto_location_desc,
                                "vRTOStateCode" => $rto_data->num_state_code,
                                "dSelectedRegDate" => strtr($requestData->vehicle_register_date, '-', '/'),
                                "dSelectedPreviousPolicyExpiryDate" => $requestData->business_type == 'newbusiness' ? '' : strtr($policy_end_date, '-', '/'),
                                "bIsNoPrevInsurance" => $requestData->business_type == 'newbusiness' ? 'true' : 'false',
                                // "nTotalClaimCount" => $requestData->business_type == 'newbusiness' ? '' : $total_claim,
                                // "nClaimCount1Year" => $requestData->business_type == 'newbusiness' ? '' : $one_year_claim,
                                // "nClaimCount2Year" => $requestData->business_type == 'newbusiness' ? '' : '0',
                                // "nClaimCount3Year" => $requestData->business_type == 'newbusiness' ? '' : '0',
                                // "nSelectedPreviousPolicyTerm" => $requestData->business_type == 'newbusiness' ? '' : '1', 
                                // "nSelectedNCBRate" => $prev_no_claim_bonus_percentage,
                                // "vSelectedPrevInsurerCode" => $requestData->business_type == 'newbusiness' ? '' : "OICL",
                                // "vSelectedPrevInsurerDesc" => $requestData->business_type == 'newbusiness' ? '' : "ORIENTAL INSURANCE",
                                // "vSelectedPrevPolicyType" => $requestData->business_type == 'newbusiness' ? '' : "Comprehensive",
                                "nSelectedRequiredPolicyTerm" => $requestData->business_type == 'newbusiness' ? '1' : '1',
                                "bIsNonElectAccessReq" => 'false',
                                "bIsElectAccessReq" => 'false',
                                "bIsSideCar" => 'false',
                                "bIsPACoverForUnnamed" => $paUnnamedPersonCoverselection,##$request_data['bike_acc_cover_unnamed_passenger'] != '' ? 'true' : 'false',
                                "nNonElectAccessSumInsured" => "0",
                                "nElectAccessSumInsured" => "0",
                                "nSideCarSumInsured" => "0",
                                "nPACoverForUnnamedSumInsured" => $paUnnamedPersonCoverinsuredAmount,##$request_data['bike_acc_cover_unnamed_passenger'] != '' ? $request_data['bike_acc_cover_unnamed_passenger'] : '0',
                                "vCustomerType" => $requestData->vehicle_owner_type == 'I' ? "I" : "C",
                                "vCustomerVoluntaryDeductible" => "0",
                                "nRequestIDV" => "0",
                                "nMarketMovement" => $vMarketMovement,
                                "nResponseCreditScore" => "0",
                                "vPurchaseDate" => date('d/m/Y', strtotime($vehicleDate)),
                                "bIsFlaProcessActive" => 'false',
                                "bIsCreditScoreOpted" => 'false',
                                "bIsNewCustomer" => 'false',
                                "vCSCustomerFirstName" => "",
                                "vCSCustomerLastName" => "",
                                "dCSCustomerDOB" => "",
                                "vCSCustomerPANNumber" => "",
                                "vCSCustomerMobileNo" => "",
                                "vCSCustomerPincode" => "",
                                "vCSCustomerIdentityProofType" => "1",
                                "vCSCustomerIdentityProofNumber" => "", //ABCDE1234Q
                                "vOfficeName" => "MUMBAI-KALINA",
                                "nOfficeCode" => config('constants.IcConstants.kotak.KOTAK_BIKE_OFFICE_CODE'),
                                "vProductTypeODTP" => ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') ? "1022" : "1066",
                                "vPAODTenure" => $requestData->vehicle_owner_type == 'C' ? '0' : $vPAODTenure,
                                "nManufactureYear" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                                "nLossAccessSumInsured" => "0",
                                "bIsCompulsoryPAWithOwnerDriver" => 'true', //CPA
                                "bIsLossAccessoriesReq" => 'false',
                                "vAPICustomerId" => "",
                                "vPosPanCard" => $POS_PAN_NO,
                                // "IsPartnerRequest" => true,
                            ];
                            if($requestData->vehicle_owner_type == 'I') {
                                $premium_req_array["nCSCustomerGender"] = "1";
                            }
                            if($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin'){
                                $premium_req_array["nClaimCount1Year"] = $requestData->business_type == 'newbusiness' ? '' : $one_year_claim;
                                $premium_req_array["nClaimCount2Year"] = $requestData->business_type == 'newbusiness' ? '' : '0';
                                $premium_req_array["nClaimCount3Year"] = $requestData->business_type == 'newbusiness' ? '' : '0';
                                $premium_req_array["nSelectedNCBRate"] = $prev_no_claim_bonus_percentage;
                                $premium_req_array["nSelectedPreviousPolicyTerm"] = $requestData->business_type == 'newbusiness' ? '' : '1';
                                $premium_req_array["nTotalClaimCount"] = $requestData->business_type == 'newbusiness' ? '' : $total_claim;
                                $premium_req_array["vSelectedPrevInsurerCode"] = $requestData->business_type == 'newbusiness' ? '' : "OICL";
                                $premium_req_array["vSelectedPrevInsurerDesc"] = $requestData->business_type == 'newbusiness' ? '' : "ORIENTAL INSURANCE";
                                $premium_req_array["vSelectedPrevPolicyType"] = $requestData->business_type == 'newbusiness' ? '' : "Comprehensive";
                            }
                            if($requestData->business_type == 'rollover' || 'newbusiness')
                            {
                                $premium_req_array["nProductCode"] ='3191';
                                if($premium_type == 'third_party')
                                {
                                    $premium_req_array["nProductCode"]='3192';
                                }
                            }
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
                                'transaction_type' => 'quote',
                            ]);
                            $data=$get_response['response'];
                            if ($data) {
                                $data = json_decode($data, true);
                                $response = $data['TwoWheelerResponseWithCover'];

                                if (!isset($data['ErrorMessage']) && $data['ErrorMessage'] == '' && $response['vErrorMessage'] == '' && $response['nNetPremium'] != '') { 
                                    if ($response['nFinalIDV'] > 125000) {
                                        return [
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'premium_amount' => 0,
                                            'status' => false,
                                            'message' => 'IDV greater than 1.25 Lacs',
                                        ];
                                    }
                                    $idv = ($response['nFinalIDV']);
                                    ////CHANGES AS PER GIT https://github.com/Fyntune/motor_2.0_backend/issues/30003
                                    // $idv_min = (string) ceil(0.9 * $response['nFinalIDV']);
                                    $idv_min = (string) (config('KOTAK_BIKE_MIN_IDV_PERCENTAGE', 0.9) * $response['nFinalIDV']);
                                    // $idv_max = (string) floor(1.10 * $response['nFinalIDV']);
                                    $idv_max = (string) floor(config('KOTAK_BIKE_MAX_IDV_PERCENTAGE', 1.10) * $response['nFinalIDV']);
                                    $skip_second_call = false;
                                    if ($requestData->is_idv_changed == 'Y') {
                                        if ($requestData->edit_idv >= $idv_max) {
                                            $premium_req_array['nRequestIDV'] = $idv_max;
                                        } elseif ($requestData->edit_idv <= $idv_min) {
                                            $premium_req_array['nRequestIDV'] = $idv_min;
                                        } else {
                                            $premium_req_array['nRequestIDV'] = $requestData->edit_idv;
                                        }
                                    } else {
                                        /* $premium_req_array['nRequestIDV'] = $idv_min; */
                                        $getIdvSetting = getCommonConfig('idv_settings');
                                        switch ($getIdvSetting) {
                                            case 'default':
                                                $premium_req_array['nRequestIDV'] = $idv;
                                                $skip_second_call = true;
                                                break;
                                            case 'min_idv':
                                                $premium_req_array['nRequestIDV'] = $idv_min;
                                                break;
                                            case 'max_idv':
                                                $premium_req_array['nRequestIDV'] = $idv_max;
                                                break;
                                            default:
                                            $premium_req_array['nRequestIDV'] = $idv_min;
                                                break;
                                        }
                                    }
                                    if(!$skip_second_call){
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
                                        'transaction_type' => 'quote',
                                    ]);
                                }
                                    $data = $get_response['response'];
                                    if ($data) {
                                        $data = json_decode($data, true);
                                        $response = $data['TwoWheelerResponseWithCover'];

                                        if ($data['ErrorMessage'] == '' && $response['vErrorMessage'] == '' && $response['nNetPremium'] != '') {
                                            $idv = ($response['nFinalIDV']);
                                        } else {
                                            $error_msg = isset($data['ErrorMessage']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $data['ErrorMessage']) : 'Error while processing request';
                                            return [
                                                'webservice_id' => $get_response['webservice_id'],
                                                'table' => $get_response['table'],
                                                'premium_amount' => 0,
                                                'status' => false,
                                                'message' => isset($response['vErrorMessage']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $response['vErrorMessage']) : $error_msg ,
                                            ];
                                        }

                                    } else {
                                        return [
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'premium_amount' => 0,
                                            'status' => false,
                                            'message' => 'Insurer not reachable',
                                        ];
                                    }
                                    // }
                                    $tp = $od = $zero_dep = $pa_owner = $llpaiddriver = $pa_unnamed = $NCB = $paid_driver = $lpg_cng_tp = $other_discount = 0;

                                    if (isset($response['nBasicTPPremium'])) {
                                        $tp = ($response['nBasicTPPremium']);
                                    }
                                    if (isset($response['nPACoverForOwnerDriverPremium'])) {
                                        $pa_owner = ($response['nPACoverForOwnerDriverPremium']);
                                    }
                                    if (isset($response['nPAtoUnnamedHirerPillionPassngrPremium'])) {
                                        $pa_unnamed = ($response['nPAtoUnnamedHirerPillionPassngrPremium']);
                                    }
                                    if (isset($response['nOwnDamagePremium'])) {
                                        $od = ($response['nOwnDamagePremium']);
                                    }
                                    if (isset($response['nNoClaimBonusDiscount'])) {
                                        $NCB = ($response['nNoClaimBonusDiscount']);
                                    }

                                    if (isset($response['nNetPremium'])) {
                                        $net_premium = ($response['nNetPremium']);
                                    }

                                    $add_ons_data = [
                                        'in_built' => [],
                                        'additional' => [
                                            'zero_depreciation' => 'NA',
                                            'road_side_assistance' => 'NA',
                                        ],
                                        'other' => [],
                                    ];
                                    $applicable_addons = [];
                                    $add_ons = [];
                                    $total_premium = $base_premium_amount = ($response['nTotalPremium']);
                                    #array hardcoded for time being need to pull from DB 
                                    $bike_data['addon_on'] = [
                                        'zero_depreciation' => '0',
                                        'road_side_assistance' => '0'
                                    ];

                                    foreach ($bike_data['addon_on'] as $key => $addon) { ## bike_data[addon_on]
                                        $add_ons[$key] = 'NA';
                                        if ($add_ons_data['additional'][$key] == 'In-Built') {
                                            $add_ons[$key] = 'In-Built';
                                        } elseif (($add_ons_data['additional'][$key] !== '') && ($bike_age <= $addon) && ($addon != 0) && (intval($add_ons_data['additional'][$key]) !== 0)) {
                                            $add_ons[$key] =  ($add_ons_data['additional'][$key] * (1 + (18.0 / 100)));
                                            $base_premium_amount -= $add_ons[$key];
                                        }
                                    }

                                    $pa_owner = ($requestData->vehicle_owner_type == 'C' ? '0' : ($response['nPACoverForOwnerDriverPremium']));


                                    array_walk_recursive($add_ons, function (&$item) {
                                        if ($item == '' || $item == '0') {
                                            $item = 'NA';
                                        }
                                    });

                                    if ($requestData->business_type != 'newbusiness') {
                                        $add_ons_data['additional']['cpa_cover'] = (string) ($pa_owner);
                                        $base_premium_amount = ($base_premium_amount - (($pa_owner * (1 + (18.0  / 100)))));
                                    }
                                    $voluntary_deductible = '0';##voluntary_deductible_calculation($od,$voluntary_deductible_amount,'bike');
                                    $OD = $od;
                                    // $tp = $tp - ($pa_owner + $pa_unnamed +$llpaiddriver);
                                    $totalTPPremium = $tp + $llpaiddriver + $pa_unnamed;
                                    $totalDiscount = $NCB + $voluntary_deductible;
                                    $base_premium_amount =  $OD + $totalTPPremium - $totalDiscount;
                                    $final_total_discount =  $NCB + $other_discount + $voluntary_deductible;
                                    $return_data = [
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'status' => true,
                                        'msg' => 'Found',
                                        'Data' => [
                                            'idv' => (int) $idv,
                                            'min_idv' => (int) $idv_min,
                                            'max_idv' => (int) $idv_max,
                                            'vehicle_idv' => $idv,
                                            'qdata' => null,
                                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                                            'addonCover' => null,
                                            'addon_cover_data_get' => '',
                                            'rto_decline' => null,
                                            'rto_decline_number' => null,
                                            'mmv_decline' => null,
                                            'mmv_decline_name' => null,
                                            'policy_type' => $premium_type == 'third_party' ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                                            'business_type' => 'Rollover',
                                            'cover_type' => '1YC', #1YC
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
                                            'product_name' => $productData->product_sub_type_name,
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
                                                'car_age' => $bike_age,
                                                'aai_discount' => 0,
                                                'ic_vehicle_discount' => 0,#(abs($other_discount)),
                                            ],
                                            'basic_premium' => ($od),
                                            'motor_electric_accessories_value' => 0,
                                            'motor_non_electric_accessories_value' => 0,
                                            'motor_lpg_cng_kit_value' => 0,
                                            'total_accessories_amount(net_od_premium)' => 0,
                                            'total_own_damage' => ($od),
                                            'tppd_premium_amount' => ($tp),
                                            'compulsory_pa_own_driver' => ($pa_owner), // Not added in Total TP Premium
                                            'cover_unnamed_passenger_value' => $pa_unnamed,
                                            'default_paid_driver' => $llpaiddriver,
                                            'motor_additional_paid_driver' => ($paid_driver),
                                            'cng_lpg_tp' => ($lpg_cng_tp),
                                            'seating_capacity' => $mmv_data['seating_capacity'],
                                            'deduction_of_ncb' => (abs($NCB)),
                                            'antitheft_discount' => 0,
                                            'aai_discount' => 0,
                                            'voluntary_excess' => $voluntary_deductible,
                                            'other_discount' => (abs($other_discount)),
                                            'total_liability_premium' => ($totalTPPremium),#($tp),
                                            'net_premium' => ($net_premium),
                                            'service_tax_amount' => ($response['nGSTAmount']),
                                            'service_tax' => 18,
                                            'total_discount_od' => 0,
                                            'add_on_premium_total' => 0,
                                            'addon_premium' => 0,
                                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                            'quotation_no' => '',
                                            'premium_amount' => ($total_premium),
                                            'service_data_responseerr_msg' => 'success',
                                            'user_id' => $requestData->user_id,
                                            'product_sub_type_id' => $productData->product_sub_type_id,
                                            'user_product_journey_id' => $requestData->user_product_journey_id,
                                            'business_type' => $requestData->business_type,
                                            'service_err_code' => null,
                                            'service_err_msg' => null,
                                            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                                            'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
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
                                            'add_ons_data'      => $add_ons_data,
                                            'applicable_addons' => $applicable_addons,
                                            'final_od_premium'  => ($od),
                                            'final_tp_premium'  => ($totalTPPremium),#($tp),
                                            'final_total_discount' => (abs($final_total_discount)),
                                            'final_net_premium' => ($total_premium),
                                            'final_gst_amount'  => ($response['nGSTAmount']),
                                            'final_payable_amount' => ($total_premium),
                                            'mmv_detail'    => [
                                                'manf_name'     => $mmv_data['manufacturer'],
                                                'model_name'    => $mmv_data['vehicle_model'],
                                                'version_name'  => $mmv_data['txt_variant'],
                                                'fuel_type'     => $mmv_data['txt_fuel'],
                                                'seating_capacity' => $mmv_data['seating_capacity'],
                                                'carrying_capacity' => $mmv_data['carrying_capacity'],
                                                'cubic_capacity' => $mmv_data['cubic_capacity'],
                                                'gross_vehicle_weight' => '',
                                                'vehicle_type'  => 'Private Car',
                                            ],
                                        ],
                                    ];
                                    if(isset($cpa_tenure)&&$requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
                                    {
                                        $return_data['Data']['multi_Year_Cpa'] = $pa_owner;
                                    }
                                  

                                } else {
                                    $error_msg = isset($data['ErrorMessage']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $data['ErrorMessage']) : 'Error while processing request';
                                    return [
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'premium_amount' => 0,
                                        'status' => false,
                                        'message' => isset($response['vErrorMessage']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $response['vErrorMessage']) : $error_msg,
                                    ];
                                }

                            } else {
                                return [
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'Insurer not reachable',
                                ];
                            }
                        } else {
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => (isset($token_response['vErrorMsg']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_response['vErrorMsg']) : 'Error while processing request'),
                            ];
                        }
                    } catch (Exception $e) {
                        return [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Insurer not reachable : ' . $e->getMessage() ,
                        ];
                    }
                } else {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable : ',
                    ];
                }
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => (!empty($rto_data) && strtoupper($rto_data['Pvt_UW']) == 'DECLINED' ? 'RTO Declined' : 'RTO not available'),
                ];
            }
        }
    }
    return camelCase($return_data);
}