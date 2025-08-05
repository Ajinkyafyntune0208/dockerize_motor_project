<?php

use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\MasterRto;
use App\Models\HdfcErgoBikeRtoLocation;
use App\Models\MasterPremiumType;
use App\Models\CvAgentMapping;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y')
    {
        return getQuoteV2($enquiryId, $requestData, $productData);
    }
    else if (config('IC.HDFC_ERGO.V1.BIKE.ENABLE') == 'Y')
    {
        return App\Quotes\Bike\V1\hdfc_ergo::getQuoteV1($enquiryId, $requestData, $productData);
    }
    else 
    {
        return getQuoteV1($enquiryId, $requestData, $productData);
    }
}

function getQuoteV1($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    // try {
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
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv,
                    'version_id' => $requestData->version_id
                ]
            ];
        }
        $mmv_data = (object)array_change_key_case((array)$mmv, CASE_LOWER);
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv_data,
                    'version_id' => $requestData->version_id
                ]
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv_data,
                    'version_id' => $requestData->version_id
                ]
            ];
        } else {
            $bike_age = 0;
            $prev_policy_end_date = $previous_policy_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));

            $current_date = date('Y-m-d');
            if(strtotime($prev_policy_end_date)  < strtotime($current_date))
            {
                $previous_policy_date = date('Y-m-d', strtotime('+1 day'));
            }

            $prev_policy_end_datefortp = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('d/m/Y') : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new DateTime($vehicleDate);
            $date2 = new DateTime($previous_policy_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0'); // same is used in car
            $bike_age = ceil($age / 12);
            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

             $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
            // if (( $bike_age >= 15) && ($tp_check == 'true')){
            //     return [
            //         'premium_amount' => 0,
            //         'status' => false,
            //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
            //     ];
            // }
            if ($bike_age > 3 && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC')))) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Zero dep is not allowed for vehicle age greater than 3 years',
                    'request' => [
                        'bike_Age' => $bike_age,
                        'productData' => $productData->zero_dep
                    ]
                ];
            }

            $rto_code = $requestData->rto_code;
            // Re-arrange for Delhi RTO code - start
            $rto_code = explode('-', $rto_code);
            if ((int)$rto_code[1] < 10) {
                $rto_code[1] = '0' . (int)$rto_code[1];
            }
            $rto_code = implode('-', $rto_code);
            // Re-arrange for Delhi RTO code - End
            $rto_data = MasterRto::where('rto_code', $rto_code)->where('status', 'Active')->first();
            if (empty($rto_data)) {
                return [
                    'status' => false,
                    'premium' => 0,
                    'message' => 'RTO code does not exist',
                    'request' => [
                        'rto_code' => $rto_code
                    ]
                ];
            }
            $rto_location = HdfcErgoBikeRtoLocation::where('rto_code', 'like', '%' . $rto_data->rto_number . '%')
                ->first();
            if (empty($rto_location)) {
                return [
                    'status' => false,
                    'premium' => 0,
                    'message' => 'RTO details does not exist with insurance company',
                    'request' => [
                        'rto_code' => $rto_code,
                        'rto_number' => $rto_data->rto_number,
                        'rto_location' => $rto_location
                    ]
                ];
            }
            $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
            $od_only = ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false;
            $is_previous_claim = $requestData->is_claim == 'Y' ? true : false;
            $applicable_ncb = $is_previous_claim ? 0 : $requestData->applicable_ncb;
            $previous_ncb = $is_previous_claim ? 0 : $requestData->previous_ncb;
            $ProductCode = '2312';
            $type_of_cover = 'OD Plus TP';
            $policyType = 'Comprehensive';
            $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $cpa_tenure = '1';  
            if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
                $addons = $selected_CPA->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                            $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                        
                    }
                }
            }

            // if ($bike_age > 15) {
            //     return [
            //         'premium_amount' => 0,
            //         'status' => false,
            //         'message' => 'Quotes are not found for vehicle age greater than 15 year',
            //         'request' => [
            //             'car_age' => $bike_age,
            //             'message' => 'Quotes are not found for vehicle age greater than 15 year',
            //         ]
            //     ];
            // }

            $vehicle_in_90_days = 'N';
            if ($requestData->business_type == 'newbusiness') {
                $business_type = 'New Business';
                $BusinessType = 'New Vehicle';
                $policy_start_date = date('Y-m-d');
                $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '5';
            } else if ($requestData->business_type == 'rollover') {
                $business_type = 'Roll Over';
                $BusinessType = 'Roll Over';
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
            } else if ($requestData->business_type == 'breakin') {
                $business_type = 'Break-In';
                $BusinessType = 'Roll Over';
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }

            if ($tp_only) {
                $ProductCode = '2320';
                $policyType = 'Third Party';
                $type_of_cover = '';
                $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
            } else if ($od_only){
                $policyType = 'Own Damage';
                $type_of_cover = 'OD Only';
            }
            if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                $ProductCode = '2367';
            }

            $productName = $productData->product_name. " ($business_type)";
            if ($requestData->business_type != 'newbusiness' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
                $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
                if ($date_difference > 0) {
                    $policy_start_date = date('Y-m-d', strtotime('+1 day'));
                }
                if($date_difference > 90){
                    $applicable_ncb = 0;
                }
            }
            if ($requestData->business_type != 'newbusiness' && in_array($requestData->previous_policy_type, ['Not sure'])) {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
                $prev_policy_end_date = date('Y-m-d', strtotime('-120 days'));
                $prev_policy_end_datefortp = NULL;
                $applicable_ncb = 0;
            }
            if(in_array($premium_type, ['breakin', 'own_damage_breakin','third_party_breakin']))
            {
                $policy_start_date = Carbon::parse(date('d-m-Y'))->addDay(1)->format('Y-m-d');
            }

            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('Y-m-d');

            $zero_dep =  ($productData->zero_dep == '0') ? '1' : '0';

            // Addons And Accessories
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $ElectricalaccessSI = $NonElectricalaccessSI = $PAforUnnamedPassengerSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = 0;
            //$addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
            $LLtoPaidDriverYN  = $LLtoPaidDriverSI = 0;
            $geoExtension = '0';
            if (!$od_only) {
                foreach ($additional_covers as $key => $value) {
                    if (in_array('LL paid driver', $value) && !$od_only) {
                        $LLtoPaidDriverYN = 1;
                        $LLtoPaidDriverSI = $value['sumInsured'];
                    }
                    if (in_array('PA cover for additional paid driver', $value) && !$od_only) {
                        $PAPaidDriverConductorCleaner = 1;
                        $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
                    }

                    if (in_array('Unnamed Passenger PA Cover', $value)) {
                        $PAforUnnamedPassenger = 1;
                        $PAforUnnamedPassengerSI = $value['sumInsured'];
                    }
                    if (in_array('Geographical Extension', $value) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                        $geoExtension = '7';
                    }
                }
            }

            if($requestData->business_type == 'newbusiness' && $premium_type == 'third_party'){
                $PAforUnnamedPassenger = 0;
                $PAforUnnamedPassengerSI = 0;
            }

            foreach ($accessories as $key => $value) {
                if (in_array('geoExtension', $value) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $geoExtension = '7';
                }
                if (in_array('Electrical Accessories', $value) && !$tp_only) {
                    $ElectricalaccessSI = $value['sumInsured'];
                }

                if (in_array('Non-Electrical Accessories', $value) && !$tp_only) {
                    $NonElectricalaccessSI = $value['sumInsured'];
                }
            }

            $bike_anti_theft = 'false';
            $voluntary_insurer_discounts =0;
            $tppd_cover = 0;#for TW product tppd limit is 6000
            if (!empty($discounts)) {
                foreach ($discounts as $key => $value) {
                    if ($value['name'] == 'anti-theft device') {
                        $bike_anti_theft = 'true';
                    }
                    if (!empty($value['name']) && !empty($value['sumInsured']) && $value['name'] == 'voluntary_insurer_discounts') {
                        $voluntary_insurer_discounts = $value['sumInsured'];
                    }
                    if ($value['name'] == 'TPPD Cover') {
                        $tppd_cover = 6000;
                    }
                }
            }
            #voluntary deductible applicable only vehicle age less than 5 years
            if($bike_age > 5 && $voluntary_insurer_discounts > 0)
            {
                $voluntary_insurer_discounts = 0;
            }
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos = false;
            $posp_email = '';
            $posp_name = '';
            $posp_unique_number = '';
            $posp_pan_number = '';
            $posp_aadhar_number = '';
            $posp_contact_number = '';
            $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('seller_type', 'P')
                ->first();

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                if (config('HDFC_BIKE_V1_IS_NON_POS') != 'Y') {
                    $hdfc_pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                        ->pluck('hdfc_ergo_code')
                        ->first();
                    if ((empty($hdfc_pos_code) || is_null($hdfc_pos_code))) {
                        return [
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'HDFC POS Code Not Available'
                        ];
                    }
                    $is_pos = true;
                    $pos_code = $hdfc_pos_code;#config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
                }
            }
            elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y'){
                $is_pos = true;
                $pos_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
            }
            // token Generation
            // $transactionid = customEncrypt($enquiryId);
            $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
            $token = hdfcErgoGetToken($enquiryId, $transactionid, $productName, $ProductCode, 'quote');

            if ($token['status']) {
                $additionData = [
                    'type' => 'withToken',
                    'method' => 'IDV Calculation',
                    'requestMethod' => 'post',
                    'section' => 'bike',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name. " ($business_type)",
                    'TOKEN' => $token['message'],
                    'transaction_type' => 'quote',
                    'PRODUCT_CODE' => $ProductCode,
                    'TRANSACTIONID' => $transactionid,
                    'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
                    'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
                    'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
                ];
                $idv_request_array = [
                    'TransactionID' => $transactionid,
                    'IDV_DETAILS' => [
                        'Policy_Start_Date' => date('d/m/Y', strtotime($policy_start_date)),
                        'ModelCode' => $mmv_data->vehicle_model_code,
                        'Vehicle_Registration_Date' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'RTOCode' => $rto_location->rto_location_code,
                    ]
                ];
                if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $get_response = getWsData(config('HDFC_ERGO_GIC_BIKE_CALCULATE_IDV'), $idv_request_array, 'hdfc_ergo', $additionData);
                    $getidvdata=$get_response['response'];
                    if (!$getidvdata) {
                        return [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'status' => false,
                            'premium' => 0,
                            'message' => 'IDV Service Issue',
                        ];
                    }
                    $data_idv = json_decode($getidvdata, TRUE);
                }
                $skip_second_call = false;
                if (isset($data_idv['StatusCode']) && $data_idv['StatusCode'] == 200 || in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $idv = $idv_max = $idv_min = 0;

                    if (!$tp_only) {
                        $idv_min = $data_idv['CalculatedIDV']['MIN_IDV_AMOUNT'];
                        $idv_max = $data_idv['CalculatedIDV']['MAX_IDV_AMOUNT'];
                        $idv = $data_idv['CalculatedIDV']['IDV_AMOUNT'];

                        if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                            if ($requestData->edit_idv >= $idv_max) {
                                $idv = floor($idv_max);
                            } elseif ($requestData->edit_idv <= $idv_min) {
                                $idv = floor($idv_min);
                            } else {
                                $idv = floor($requestData->edit_idv);
                            }
                        } else {
                            $getIdvSetting = getCommonConfig('idv_settings');
                            switch ($getIdvSetting) {
                                case 'default':
                                    $data_idv['CalculatedIDV']['IDV_AMOUNT'] = $idv;
                                    $skip_second_call = true;
                                    $idv =  $idv;
                                    break;
                                case 'min_idv':
                                    $data_idv['CalculatedIDV']['MIN_IDV_AMOUNT'] = $idv_min;
                                    $idv =  $idv_min;
                                    break;
                                case 'max_idv':
                                    $data_idv['CalculatedIDV']['MAX_IDV_AMOUNT'] = $idv_max;
                                    $idv =  $idv_max;
                                    break;
                                default:
                                $idv = $data_idv['CalculatedIDV']['IDV_AMOUNT'] = $idv;
                                    $idv =  $idv_min;
                                    break;
                            }
                            // $idv = $idv_min;
                        }
                    }

                    if($premium_type != 'third_party' && $premium_type != 'third_party_breakin'){
                        $totalAcessoriesIDV = 0;
                        $totalAcessoriesIDV += (int)($requestData->electrical_acessories_value);
                        $totalAcessoriesIDV += (int)($requestData->nonelectrical_acessories_value);
                        $totalAcessoriesIDV += (int)($requestData->bifuel_kit_value);

                        if($totalAcessoriesIDV > ($idv * 0.25)) {
                            return [
                                'webservice_id'=>$get_response['webservice_id'],
                                'table'=>$get_response['table'],
                                'status' => false,
                                'message' => 'Total of Accessories (Electrical, Non Electrical, LPG-CNG KIT) can not be greater than 25% of the vehicle IDV',
                                'request' => [
                                    'message' => 'Total of Accessories (Electrical, Non Electrical, LPG-CNG KIT) can not be greater than 25% of the vehicle IDV',
                                    'totalAcessoriesIDV' => $totalAcessoriesIDV,
                                    'idv_amount' => $idv
                                ]
                            ];
                        }
                        
                    }

                    // $vehicleRegNo = $rto_code . '-MJ-6631';
                    $vehicleRegNo = null;

                    if (!empty($requestData->vehicle_registration_no) && !in_array(strtoupper($requestData->vehicle_registration_no), ['NEW', 'NONE'])) {
                        $vehicleRegNo = $requestData->vehicle_registration_no;
                    }

                    $model_config_premium = [
                        'TransactionID' => $transactionid,
                        'Customer_Details' => null,
                        'Policy_Details' => [
                            'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                            'ProposalDate' => date('d/m/Y'),
                            'BusinessType_Mandatary' => $BusinessType,
                            'VehicleModelCode' => $mmv_data->vehicle_model_code,
                            'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                            'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                            'YearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'RTOLocationCode' => $rto_location->rto_location_code,
                            'Vehicle_IDV' => $idv,
                            'PreviousPolicy_CorporateCustomerId_Mandatary' => "",
                            'PreviousPolicy_NCBPercentage' => $previous_ncb,
                            'PreviousPolicy_PolicyEndDate' => $prev_policy_end_datefortp,
                            'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                            'PreviousPolicy_PolicyNo' => '',
                            'PreviousPolicy_PreviousPolicyType' =>(($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage' ) ? 'Comprehensive Package' : 'TP'),
                            'Registration_No' => $vehicleRegNo,
                            'EngineNumber' => 'dwdwad34343',
                            'ChassisNumber' => 'grgrgrg444',
                            'AgreementType' => "",
                            'FinancierCode' => "",
                            'BranchName' => "",
                        ],
                        /* 'Req_POSP' => [
                            'EMAILID' => $posp_email,
                            'NAME' => $posp_name,
                            'UNIQUE_CODE' => $posp_unique_number,
                            'STATE' => "",
                            'PAN_CARD' => $posp_pan_number,
                            'ADHAAR_CARD' => $posp_aadhar_number,
                            'NUM_MOBILE_NO' => $posp_contact_number
                        ], */
                    ];
                    if($requestData->business_type != 'newbusiness')
                    {
                        $model_config_premium['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = !in_array($requestData->previous_policy_type, ['Not sure']) ? 'BHARTIAXA' : '';
                        $model_config_premium['Policy_Details']['PreviousPolicy_PolicyNo'] = !in_array($requestData->previous_policy_type, ['Not sure']) ?'78657866986' : '';
                    }
                    if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                        $model_config_premium['Req_TW_Multiyear'] = [
                            "VehicleClass" => $mmv_data->vehicle_class_code,
                            "DateOfRegistration" => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                            'IsLimitedtoOwnPremises' => '0',
                            'ExtensionCountryCode' => $geoExtension,
                            'POSP_CODE' => [],
                            // 'LLPaiddriver' => 'Yes',
                            'ExtensionCountryName' => '',#($geoExtension == 0) ? null : '',
                            'Effectivedrivinglicense' => ($od_only || $requestData->vehicle_owner_type != 'I') ? 'true' : 'false',
                            'Owner_Driver_Nominee_Name' => "0",
                            'Owner_Driver_Nominee_Age' => "0",
                            'Owner_Driver_Nominee_Relationship' => "0",
                            'IsZeroDept_Cover' => $zero_dep,
                            'IsEA_Cover' => (!$tp_only && $bike_age <= 3) ? 1 : 0,
                            'IsRTI_Cover' => (!$tp_only && $interval->days <= 1093) ? (($previous_ncb == 0 && $requestData->business_type == 'breakin') ? 0 : 1) : 0,
                            'CPA_Tenure' => $cpa_tenure ,
                            'Paiddriver' => $LLtoPaidDriverYN,
                            'PAPaiddriverSI' => $LLtoPaidDriverSI,
                            /* 'Paiddriver' => $PAPaidDriverConductorCleaner,
                            'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI, */
                            'NoofUnnamedPerson' => $PAforUnnamedPassenger,
                            'UnnamedPersonSI' => $PAforUnnamedPassengerSI,
                            'ElecticalAccessoryIDV' => $ElectricalaccessSI,
                            'NonElecticalAccessoryIDV' => $NonElectricalaccessSI,
                            // 'Voluntary_Excess_Discount' => $voluntary_insurer_discounts,
                            // 'TPPDLimit' => $tppd_cover,as per #23856
                            'AntiTheftDiscFlag' => false,
                            'POLICY_TENURE' => 5,
                            'POLICY_TYPE' => $type_of_cover,
                        ];
                    } else {
                        $model_config_premium['Req_TW'] = [
                            'IsLimitedtoOwnPremises' => '0',
                            'ExtensionCountryCode' => $geoExtension,
                            'POSP_CODE' => [],
                            // 'LLPaiddriver' => 'Yes',
                            'ExtensionCountryName' => '',#($geoExtension == 0) ? 0 : '',
                            'Effectivedrivinglicense' => ($od_only || $requestData->vehicle_owner_type != 'I') ? 'true' : 'false',
                            'Owner_Driver_Nominee_Name' => "0",
                            'Owner_Driver_Nominee_Age' => "0",
                            'Owner_Driver_Nominee_Relationship' => "0",
                            'IsZeroDept_Cover' => $zero_dep,
                            'IsEA_Cover' => (!$tp_only && $interval->days <= 1093) ? 1 : 0,
                            'IsRTI_Cover' => (!$tp_only && $interval->days <= 1093) ? (($previous_ncb == 0 && $requestData->business_type == 'breakin') ? 0 : 1) : 0,
                            'CPA_Tenure' => $cpa_tenure,
                            'Paiddriver' => $LLtoPaidDriverYN,
                            'PAPaiddriverSI' => $LLtoPaidDriverYN,
                            'NoofUnnamedPerson' => $PAforUnnamedPassenger,
                            'UnnamedPersonSI' => $PAforUnnamedPassengerSI,
                            'ElecticalAccessoryIDV' => $ElectricalaccessSI,
                            'NonElecticalAccessoryIDV' => $NonElectricalaccessSI,
                            // 'Voluntary_Excess_Discount' => $voluntary_insurer_discounts, // Voluntary Deductible discount is removed.
                            // 'TPPDLimit' => $tppd_cover, as per #23856
                            'AntiTheftDiscFlag' => false,
                            'POLICY_TENURE' => 1,
                            'POLICY_TYPE' => $type_of_cover,
                        ];
                    }
                    if (!$is_pos) {
                        unset($model_config_premium['Req_TW']['POSP_CODE']);
                        if(isset($model_config_premium['Req_TW_Multiyear']['POSP_CODE']))
                        {
                            unset($model_config_premium['Req_TW_Multiyear']['POSP_CODE']);
                        }
                    } else {
                        $posp_code = ($idv >= 5000000) ? [] : (!empty($pos_code) ? $pos_code : []);
                        $model_config_premium['Req_TW']['POSP_CODE'] = $posp_code;
                        if (isset($model_config_premium['Req_TW_Multiyear']['POSP_CODE'])) {
                            $model_config_premium['Req_TW_Multiyear']['POSP_CODE'] = $posp_code;
                        }
                    }             
                    $additionData = [
                        'type' => 'withToken',
                        'method' => 'Premium Calculation',
                        'requestMethod' => 'post',
                        'section' => 'bike',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_name. " ($business_type)",
                        'TOKEN' => $token['message'],
                        'transaction_type' => 'quote',
                        'PRODUCT_CODE' => $ProductCode,
                        'TRANSACTIONID' => $transactionid,
                        'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
                        'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
                        'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
                    ];

                    // if(!$skip_second_call) {
                        $checksum_data = checksum_encrypt($model_config_premium);
                        $additionData['checksum'] = $checksum_data;
                        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'hdfc_ergo', $checksum_data, 'BIKE');
                        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                            $get_response = $is_data_exist_for_checksum;
                        }else{
                            $get_response = getWsData(config('HDFC_ERGO_GIC_BIKE_CALCULATE_PREMIUM'), $model_config_premium, 'hdfc_ergo', $additionData);
                        }
                    // }
                    $getpremium = $get_response['response'];
                    if (!$getpremium) {
                        return [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'status' => false,
                            'premium' => 0,
                            'message' => 'Premium Service issue',
                        ];
                    }

                    $arr_premium = json_decode($getpremium, TRUE);
                    if ((isset($arr_premium['StatusCode']) && $arr_premium['StatusCode'] == '200')) {
                        $premium_data = $arr_premium['Resp_TW'];
                        $idv = (!$tp_only) ? $premium_data['IDV'] : '0';
                        $igst = $anti_theft = $other_discount = $rsapremium = $pa_paid_driver = $zero_dep_amount = $ncb_discount = $tppd = $final_tp_premium = $final_od_premium = $final_net_premium = $final_payable_amount = $basic_od = $electrical_accessories = $lpg_cng_tp = $lpg_cng = $non_electrical_accessories = $pa_owner = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount = $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $rti = $tyre_secure = $ncb_protection=$GeogExtension_od= $GeogExtension_tp=$OwnPremises_OD= $OwnPremises_TP = 0;
                        if (!empty($premium_data['PAOwnerDriver_Premium'])) {
                            $pa_owner = ($premium_data['PAOwnerDriver_Premium']);
                        }
                        if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                            $zero_dep_amount += ($premium_data['Vehicle_Base_ZD_Premium']);
                        }
                        if (!empty($premium_data['EA_premium'])) {
                            $rsapremium = ($premium_data['EA_premium']);
                        }
                        if (!empty($premium_data['Vehicle_Base_RTI_Premium'])) {
                            $rti += ($premium_data['Vehicle_Base_RTI_Premium']);
                        }
                        if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                            $ncb_discount = ($premium_data['NCBBonusDisc_Premium']);
                        }

                        if (!empty($premium_data['GeogExtension_ODPremium'])) {
                            $GeogExtension_od = ($premium_data['GeogExtension_ODPremium']);
                        }
                        if (!empty($premium_data['GeogExtension_TPPremium'])) {
                            $GeogExtension_tp= ($premium_data['GeogExtension_TPPremium']);
                        }

                        if (!empty($premium_data['LimitedtoOwnPremises_OD_Premium'])) {
                            $OwnPremises_OD = ($premium_data['LimitedtoOwnPremises_OD_Premium']);
                        }
                        if (!empty($premium_data['LimitedtoOwnPremises_TP_Premium'])) {
                            $OwnPremises_TP = ($premium_data['LimitedtoOwnPremises_TP_Premium']);
                        }

                        if (!empty($premium_data['UnnamedPerson_premium'])) {
                            $pa_unnamed = ($premium_data['UnnamedPerson_premium']);
                        }
                        if (!empty($premium_data['PAPaidDriver_Premium'])) {
                            $pa_paid_driver = ($premium_data['PAPaidDriver_Premium']);
                        }
                        if (!empty($premium_data['Net_Premium'])) {
                            $final_net_premium = ($premium_data['Net_Premium']);
                        }
                        if (!empty($premium_data['PaidDriver_Premium'])) {
                            $ll_paid_driver = ($premium_data['PaidDriver_Premium']);
                        }
                        if (!empty($premium_data['Total_Premium'])) {
                            $final_payable_amount = ($premium_data['Total_Premium']);
                        }
                        if (!empty($premium_data['AntiTheftDisc_Premium'])) {
                            $anti_theft = ($premium_data['AntiTheftDisc_Premium']);
                        }
                        if (!empty($premium_data['VoluntartDisc_premium'])) {
                            $voluntary_excess = ($premium_data['VoluntartDisc_premium']);
                        }
                        if (!empty($premium_data['TPPD_premium'])) {
                            $tppd_discount = ($premium_data['TPPD_premium']);
                        }
                        if (!empty($premium_data['Electical_Acc_Premium'])) {
                            $electrical_accessories = ($premium_data['Electical_Acc_Premium']);
                        }
                        if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                            $non_electrical_accessories = ($premium_data['NonElectical_Acc_Premium']);
                        }
                        if ($zero_dep == '1' && !empty($premium_data['Elec_ZD_Premium'])) {
                            $zero_dep_amount += ($premium_data['Elec_ZD_Premium']);
                        }
                        if ($zero_dep == '1' && !empty($premium_data['NonElec_ZD_Premium'])) {
                            $zero_dep_amount += ($premium_data['NonElec_ZD_Premium']);
                        }
                        if (!empty($premium_data['Elec_RTI_Premium'])) {
                            $rti += ($premium_data['Elec_RTI_Premium']);
                        }
                        if (!empty($premium_data['NonElec_RTI_Premium'])) {
                            $rti += ($premium_data['NonElec_RTI_Premium']);
                        }

                        $final_tp_premium = ($premium_data['Basic_TP_Premium']) + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $ll_paid_driver+$GeogExtension_tp+$OwnPremises_TP;
                        $final_total_discount = $anti_theft + $ncb_discount + $voluntary_excess + $tppd_discount;
                        $final_od_premium = $premium_data['Basic_OD_Premium'] + $non_electrical_accessories + $electrical_accessories + $lpg_cng+$GeogExtension_od+$OwnPremises_OD;
                        $add_ons = [
                            'in_built' => [],
                            'additional' => [],
                            'other' => []
                        ];
                        $applicable_addons = [];
                        if ($bike_age <= 5) {
                            $add_ons['additional']['road_side_assistance'] = ($rsapremium);
                            array_push($applicable_addons, "roadSideAssistance");
                            if ($bike_age <= 3) {
                                $add_ons['additional']['zero_depreciation'] = $zero_dep == '1' ? ($zero_dep_amount) : 0;
                                $add_ons['additional']['return_to_invoice'] = ($rti);
                                array_push($applicable_addons, "zeroDepreciation");
                                array_push($applicable_addons, "returnToInvoice");
                            }
                        }
                        $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
                        $add_ons['additional_premium'] = array_sum($add_ons['additional']);
                        $add_ons['other_premium'] = array_sum($add_ons['other']);

                        $data_response = [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'status' => true,
                            'msg' => 'Found',
                            'Data' => [
                                'idv' => (int)$idv,
                                'min_idv' => (int)$idv_min,
                                'max_idv' => (int)$idv_max,
                                'vehicle_idv' => $idv,
                                'qdata' => null,
                                'pp_enddate' => $requestData->previous_policy_expiry_date,
                                'addonCover' => null,
                                'addon_cover_data_get' => '',
                                'rto_decline' => null,
                                'rto_decline_number' => null,
                                'mmv_decline' => null,
                                'mmv_decline_name' => null,
                                'policy_type' => $policyType,
                                'business_type' => $business_type,
                                'cover_type' => '1YC',
                                'hypothecation' => '',
                                'hypothecation_name' => '',
                                'vehicle_registration_no' => $requestData->rto_code,
                                'rto_no' => $rto_code,
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
                                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
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
                                    'segment_id' => '0',
                                    'rto_cluster_id' => '0',
                                    'car_age' => $bike_age,
                                    // 'aai_discount' => 0,
                                    'ic_vehicle_discount' => 0,
                                ],
                                'ic_vehicle_discount' => 0,
                                'basic_premium' => ($premium_type != 'third_party') ? (string)($premium_data['Basic_OD_Premium']) : '0',
                                'motor_electric_accessories_value' => ($electrical_accessories),
                                'motor_non_electric_accessories_value' => ($non_electrical_accessories),
                                'motor_lpg_cng_kit_value' => ($lpg_cng),
                                'GeogExtension_ODPremium' => ($GeogExtension_od),
                                'GeogExtension_TPPremium' => ($GeogExtension_tp),
                                //                            'LimitedtoOwnPremises_TP'=>($OwnPremises_TP),
                                //                            'LimitedtoOwnPremises_OD'=>($OwnPremises_OD),
                                'total_accessories_amount(net_od_premium)' => ($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                                'total_own_damage' => ($final_od_premium),
                                'tppd_premium_amount' => (string)($premium_data['Basic_TP_Premium']),
                                'tppd_discount' => $tppd_discount,
                                'compulsory_pa_own_driver' => ($pa_owner), // Not added in Total TP Premium
                                'cover_unnamed_passenger_value' => $pa_unnamed,
                                'default_paid_driver' => $ll_paid_driver,
                                'motor_additional_paid_driver' => ($pa_paid_driver),
                                'cng_lpg_tp' => ($lpg_cng_tp),
                                'seating_capacity' => $mmv_data->seating_capacity,
                                'deduction_of_ncb' => (abs($ncb_discount)),
                                'antitheft_discount' => (abs($anti_theft)),
                                // 'aai_discount' => '', //$automobile_association,
                                'voluntary_excess' => $voluntary_excess,
                                'other_discount' => 0,
                                'total_liability_premium' => ($final_tp_premium),
                                'net_premium' => ($final_net_premium),
                                'service_tax_amount' => ($final_payable_amount - $final_net_premium),
                                'service_tax' => 18,
                                'total_discount_od' => 0,
                                'add_on_premium_total' => 0,
                                'addon_premium' => 0,
                                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                'quotation_no' => '',
                                'premium_amount' => ($final_payable_amount),
                                'service_data_responseerr_msg' => 'success',
                                'user_id' => $requestData->user_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'user_product_journey_id' => $requestData->user_product_journey_id,
                                'service_err_code' => null,
                                'service_err_msg' => null,
                                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                                'ic_of' => $productData->company_id,
                                'vehicle_in_90_days' => $vehicle_in_90_days,
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
                                'add_ons_data' => $add_ons,
                                'applicable_addons' => $applicable_addons,
                                'final_od_premium' => ($final_od_premium),
                                'final_tp_premium' => ($final_tp_premium),
                                'final_total_discount' => (abs($final_total_discount)),
                                'final_net_premium' => ($final_net_premium),
                                'final_gst_amount' => ($final_payable_amount - $final_net_premium),
                                'final_payable_amount' => ($final_payable_amount),
                                'mmv_detail' => [
                                    'manf_name' => $mmv_data->vehicle_manufacturer,
                                    'model_name' => $mmv_data->vehicle_model_name,
                                    'version_name' => $mmv_data->variant,
                                    'fuel_type' => $mmv_data->fuel,
                                    'seating_capacity' => $mmv_data->seating_capacity,
                                    'carrying_capacity' => $mmv_data->carrying_capacity,
                                    'cubic_capacity' => $mmv_data->cubic_capacity,
                                    'gross_vehicle_weight' => '',
                                    'vehicle_type' => 'Two Wheeler',
                                ],
                            ],
                        ];
                        if(isset($cpa_tenure))
                        {
                        if($requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
                        {
                           
                            $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
                        } 
                        }
                        $return_data=camelCase($data_response);
                    } else {
                        $return_data = [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => $arr_premium['Error'] ?? "Premium Service issue",
                        ];
                    }
                } else {
                    $return_data = [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => $data_idv['Error'] ?? 'IDV Service Issue'
                    ];
                }
            } else {
                $return_data = [
                    'webservice_id'=>$token['webservice_id'],
                    'table'=>$token['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $token['message']
                ];
            }
        }
    // } catch (\Exception $e) {
    //     $return_data = [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Premium Service Issue ' . $e->getMessage(),

    //     ];
    // }

    return $return_data;
}

function hdfcErgoGetToken($enquiryId, $transaction_id, $productName, $productCode, $stage) {
    $additionData = [
        'type' => 'getToken',
        'method' => 'Token Generation',
        'section' => 'bike',
        'enquiryId' => $enquiryId,
        'transaction_type' => $stage,
        'productName' => $productName,
        'PRODUCT_CODE' => $productCode,
        'TRANSACTIONID' => $transaction_id,
        'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
        'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
        'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
    ];
    //$token = cache()->remember('HDFC_ERGO_GIC_BIKE_GET_TOKEN', 60 * 15, function () use ($additionData) {
        $get_response = getWsData(config('HDFC_ERGO_GIC_BIKE_GET_TOKEN'), '', 'hdfc_ergo', $additionData);
    //});

    $token = $get_response['response'];
    if (!empty($token)) {
        $token_data = json_decode($token, TRUE);
        if (isset($token_data['Authentication']['Token'])) {
            return [
                'status' => true,
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'message' => $token_data['Authentication']['Token']
            ];
        } else if (isset($token_data['Error'])) {
            return [
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'status' => false,
                'message' => $token_data['Error']
            ];
        }
    }

    return [
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'status' => false,
        'message' => 'Token Generation Service not reachable'
    ];
}

function getQuoteV2($enquiryId, $requestData, $productData)
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
    // dd($requestData);
    $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');

    if ($mmv['status'] == 1)
    {
        $mmv = $mmv['data'];
    }
    else
    {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];          
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    // dd($mmv_data);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    }
    elseif ($mmv_data->ic_version_code == 'DNE')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }

    $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code, true);
    $rto_data = HdfcErgoBikeRtoLocation::where('rto_code', $rto_code)->first();
    if ( ! $rto_data)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO does not exists',
            'request' => [
                'mmv' => $mmv,
                'message' => 'RTO does not exists',
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);

    if ($requestData->business_type == 'rollover')
    {
        $business_type = 'ROLLOVER';
        $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
    }
    elseif ($requestData->business_type == 'newbusiness')
    {
        $business_type = 'NEW';
        $policy_start_date = $tp_only ? date('d-m-Y', strtotime('tomorrow')) : date('d-m-Y', time());
    }
    elseif ($requestData->business_type == 'breakin')
    {
        $business_type = 'ROLLOVER';
        $policy_start_date = $tp_only ? date('d-m-Y', strtotime('+3 day', time())) : date('d-m-Y', strtotime('tomorrow')); //Policy start date will be T+1 for breakin
    }

    if ($requestData->ownership_changed == 'Y' || in_array($requestData->previous_policy_type, ['Not sure']))
    {
        $business_type = 'USED';
        $policy_start_date = $tp_only ? date('d-m-Y', strtotime('+3 day', time())) : date('d-m-Y', strtotime('tomorrow'));
    }

    $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))));

    $car_age = 0;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    // if (($car_age >= 15) && ($tp_check == 'true')){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
    //     ];
    // }
    
    if ($interval->y >= 3 && $productData->zero_dep == 0)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero depreciation is not available for vehicles having age more than 3 years'
        ];
    }

    if ($requestData->policy_type == 'comprehensive')
    {
        $policy_type = 'Comprehensive';
    }
    elseif ($requestData->policy_type == 'own_damage')
    {
        $policy_type = 'OwnDamage';
    }

    $electrical_accessories_sa = 0;
    $non_electrical_accessories_sa = 0;
    $is_pa_unnamed_passenger = 'NO';
    $pa_unnamed_passenger_sa = 0;
    $is_ll_paid_driver = 'NO';
    $is_tppd_discount = 'NO';

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)
        ->first();
    // dd($selected_addons);
    if ($selected_addons)
    {
        if ($selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '')
        {
            foreach ($selected_addons['additional_covers'] as $additional_cover)
            {
                if ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                {
                    $is_pa_unnamed_passenger = 'YES';
                    $pa_unnamed_passenger_sa = $additional_cover['sumInsured'];
                }

                if ($additional_cover['name'] == 'LL paid driver')
                {
                    $is_ll_paid_driver = 'YES';
                }
            }
        }

        if ($selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '')
        {
            foreach ($selected_addons['discounts'] as $discount)
            {
                if ($discount['name'] == 'TPPD Cover')
                {
                    $is_tppd_discount = 'YES';
                }
            }
        }
    }

    $is_tppd_discount = 'NO'; // UW Criteria from Base Service : Restricted Cover of TPPD is not allowed
    $RegistrationNo = $requestData->vehicle_registration_no;
    if ((empty($RegistrationNo))||($RegistrationNo == "NEW")) { 
        $RegistrationNo = $requestData->rto_code;
    } 
    $premium_request = [
        'ConfigurationParam' => [
            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE'),
        ],
        'TypeOfBusiness' => $business_type,
        'VehicleMakeCode' => $mmv_data->manufacturer_code,
        'VehicleModelCode' => $mmv_data->vehicle_model_code,
        'RtoLocationCode' => $rto_data->rto_location_code,
        'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "INDIVIDUAL" : "CORPORATE",
        'PolicyType' => $tp_only ? 'ThirdParty' : $policy_type,
        'CustomerStateCode' => $rto_data->state_code,
        'PurchaseRegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
        'RequiredIDV' => 0,
        'IsPreviousClaim' => ($requestData->is_claim == 'Y') || ($requestData->previous_policy_type == 'Third-party') ? 'YES' : 'NO',
        'PreviousPolicyEndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
        'PreviousNCBDiscountPercentage' => $requestData->previous_ncb,
        'PospCode' => '',
        'RegistrationNo' => $RegistrationNo,
        'CORDiscount' => 0,
        'AddOnCovers' => [
            'IsZeroDepCover' => $productData->zero_dep == 0 && $interval->y < 3 ? "YES" : "NO",
            'IsEmergencyAssistanceCover' => "NO",//$interval->y < 10 ? "YES" : "NO", // RSA SELECTED NO BECAUSE OF THIS PREMIUM MISMATCH ISSUE TILL THEN THIS ADDON IS OFF ( NEED TO DISCUSS THIS WITH IC)
            'planType' => $interval->y < 3 ? "ENGEBOX" : "",
            'UnnamedPassengerSumInsured' => $pa_unnamed_passenger_sa,
            'IsLegalLiabilityDriver' => $is_ll_paid_driver,
            'IsTPPDDiscount' => $is_tppd_discount,
            'CpaYear' => $requestData->vehicle_owner_type == 'I' ? ($requestData->business_type == 'newbusiness' ? 5 : 1) : 0,
        ],
    ];

    if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure')
    {
        unset($premium_request['IsPreviousClaim']);
        unset($premium_request['PreviousPolicyEndDate']);
        unset($premium_request['PreviousNCBDiscountPercentage']);
    }

    if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')
    {
        $premium_request['TPEndDate'] = date('Y-m-d', strtotime('+2 year', strtotime($requestData->previous_policy_expiry_date)));
        unset($premium_request['AddOnCovers']['UnnamedPassengerSumInsured']); //PA Passenger is not allowed for ODOnly policy type
        unset($premium_request['AddOnCovers']['IsLegalLiabilityDriver']); // Legal liability driver is not allowed for ODOnly policy type
        unset($premium_request['AddOnCovers']['IsTPPDDiscount']); // IsTPPDDiscount is not allowed for ODOnly policy type
        unset($premium_request['AddOnCovers']['CpaYear']); // CpaYear is not allowed for ODOnly policy type
    }

    $checksum_data = checksum_encrypt($premium_request);
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'hdfc_ergo', $checksum_data, 'BIKE');
    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_response = $is_data_exist_for_checksum;
    }else{
        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'checksum'  => $checksum_data,
            'transaction_type' => 'quote',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),
                'Content-Type' => 'application/json',
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                'Accept-Language' => 'en-US,en;q=0.5'
            ]
        ]);
    }

    $premium_response = $get_response['response'];
    if ($premium_response)
    {
        $premium_response = json_decode($premium_response, TRUE);
        $skip_second_call = false;
        if (isset($premium_response['Status']) && $premium_response['Status'] == 200)
        {
            foreach ($premium_response['Data'] as $premium)
            {
                if ($premium['PremiumYear'] == 5)
                {
                    $premium_data = $premium;
                }
                elseif ($premium['PremiumYear'] == 1)
                {
                    $premium_data = $premium;
                }
            }

            if ( ! in_array($premium_type, ['third_party', 'third_party_breakin']))
            {
                // dd($premium_data);
                $vehicle_idv = $premium_data['VehicleIdv'];
                $min_idv = $premium_data['VehicleIdvMin'];
                $max_idv = $premium_data['VehicleIdvMax'];
                $default_idv = $premium_data['VehicleIdv'];
            
                if ($requestData->is_idv_changed == 'Y')
                {                       	
                    if ($requestData->edit_idv >= $max_idv)
                    {
                        $premium_request['RequiredIDV'] = $max_idv;
                    }
                    elseif ($requestData->edit_idv <= $min_idv)
                    {
                        $premium_request['RequiredIDV'] = $min_idv;
                    }
                    else
                    {
                        $premium_request['RequiredIDV'] = $requestData->edit_idv;
                    }
                }
                else
                {
                    $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $premium_request['RequiredIDV'] = $default_idv;
                                $skip_second_call = true;
                                $idv = $default_idv;
                                break;
                            case 'min_idv':
                                $premium_request['RequiredIDV'] = $min_idv;
                                $idv = $min_idv;
                                break;
                            case 'max_idv':
                                $premium_request['RequiredIDV'] = $max_idv;
                                $idv = $max_idv;
                                break;
                            default:
                            $premium_request['RequiredIDV'] = $min_idv;
                                $idv = $min_idv;
                                break;
                        }
                        //$premium_request['RequiredIDV'] = $min_idv;
                }

                if(!$skip_second_call) {
                    $checksum_data = checksum_encrypt($premium_request);
                    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'hdfc_ergo', $checksum_data, 'BIKE');
                    if($is_data_exist_for_checksum['found'] && $refer_webservice){
                        $get_response = $is_data_exist_for_checksum;
                    }else{
                        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Premium Re-calculation',
                            'requestMethod' => 'post',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'quote',
                            'checksum'    => $checksum_data,
                            'headers' => [
                                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),
                                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),
                                'Content-Type' => 'application/json',
                                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                                'Accept-Language' => 'en-US,en;q=0.5'
                            ]
                        ]);
                    }
                }
                
                $premium_response = $get_response['response'];
                if ($premium_response)
                {
                    $premium_response = json_decode($premium_response, TRUE);

                    if (isset($premium_response['Status']))
                    {
                        if ($premium_response['Status'] != 200)
                        {
                            return camelCase([
                                'webservice_id'=>$get_response['webservice_id'],
                                'table'=>$get_response['table'],
                                'status' => false,
                                'premium_amount' => 0,
                                'message' => (isset($premium_response['Message']) && !empty($premium_response['Message'])) ? createErrorMessage($premium_response['Message']): 'An error occured while recalculating premium'
                            ]);
                        }
                        else
                        {
                            foreach ($premium_response['Data'] as $premium)
                            {
                                if ($premium['PremiumYear'] == 5)
                                {
                                    $premium_data = $premium;
                                }
                                elseif ($premium['PremiumYear'] == 1)
                                {
                                    $premium_data = $premium;
                                }
                            }
                        }
                    }
                    else
                    {
                        return camelCase([
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'An error occured while recalculating premium'
                        ]);
                    }
                }
                else
                {
                    return camelCase([
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'Insurer not reachable'
                    ]);
                }
            }
            // dd($premium_data);
            $basic_od = $premium_data['BasicODPremium'] ?? 0;
            $basic_tp = $premium_data['BasicTPPremium'] ?? 0;
            $cpa = 0;
            $unnamed_passenger = 0;
            $ll_paid_driver = 0;
            $zero_depreciation = 0;
            $road_side_assistance = 0;
            $engine_protection = 0;
            $ncb_discount = 0;
            $tppd_discount = $premium_data['TppdDiscountAmount'] ?? 0;
                    
            if (isset($premium_data['AddOnCovers']))
            {
                foreach ($premium_data['AddOnCovers'] as $addon_cover)
                {
                    switch($addon_cover['CoverName'])
                    {
                        case 'PACoverOwnerDriver':
                            $cpa = $addon_cover['CoverPremium'];
                            break;

                        case 'PACoverOwnerDriver5Year':
                            if ($requestData->business_type == 'newbusiness' && $requestData->policy_type == 'comprehensive')
                            {
                                $cpa = $addon_cover['CoverPremium'];
                            }
                            break;

                        case 'UnnamedPassenger':
                            $unnamed_passenger = $addon_cover['CoverPremium'];
                            break;

                        case 'LLPaidDriver':
                            $ll_paid_driver = $addon_cover['CoverPremium'];
                            break;

                        case 'ZeroDepreciation':
                            $zero_depreciation = $productData->zero_dep == 0 && $interval->y < 3 ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'EmergencyAssistance':
                            $road_side_assistance = $interval->y < 10 ? $addon_cover['CoverPremium'] : 0;
                            break;

                        case 'EngineProtection':
                            $engine_protection = $interval->y < 3 ? $addon_cover['CoverPremium'] : 0;
                            break;

                        default:
                            break;
                    }
                }
            }

            $final_od_premium = $basic_od;
            $final_tp_premium = $basic_tp + $unnamed_passenger + $ll_paid_driver;
            $ncb_discount = $premium_data['NewNcbDiscountAmount'] ?? 0;//$final_od_premium * ($requestData->applicable_ncb / 100);
            $final_total_discount = $ncb_discount + $tppd_discount;
            $final_net_premium = ($final_od_premium + $final_tp_premium - $final_total_discount);
            $final_gst_amount = ($final_net_premium * 0.18);
            $final_payable_amount = $final_net_premium + $final_gst_amount;
            $applicable_addons = [];
            $addons_data = [
                'in_built' => [],
                'additional' => [],
                'other_premium' => []
            ];

            if ($premium_type != 'third_party')
            {
                $addons_data = [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation' => $zero_depreciation,
                        'road_side_assistance' => $road_side_assistance,
                        'engine_protector' => $engine_protection,
                    ],
                    'other' => [],
                ];
            if ($productData->zero_dep == 0){
                $addons_data = [
                    'in_built' => [
                        'zero_depreciation' => $zero_depreciation,
                    ],
                    'additional' => [
                        // 'zero_depreciation' => $zero_depreciation,
                        'road_side_assistance' => $road_side_assistance,
                        'engine_protector' => $engine_protection,
                    ],
                    'other' => [],
                ];
            }
                $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                $addons_data['other_premium'] = 0;

                $applicable_addons = ['zeroDepreciation', 'roadSideAssistance', 'engineProtector'];

                if ($interval->y >= 3)
                {
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                }

                if ($addons_data['additional']['engine_protector'] == 0)
                {
                    array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                }

                if ($addons_data['additional']['road_side_assistance'] == 0)
                {
                    array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                }
            }
            foreach($addons_data['additional'] as $k=>$v){
                if($v == 0){
                    unset($addons_data['additional'][$k]);
                }
            }
            $business_types = [
                'rollover' => 'Rollover',
                'newbusiness' => 'New Business',
                'breakin' => 'Breakin'
            ];

            $return_data = [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => ! $tp_only ? ($premium_request['RequiredIDV']) : 0,
                    'min_idv' => ! $tp_only ? ceil($min_idv) : 0,
                    'max_idv' => ! $tp_only ? floor($max_idv) : 0,
                    'exshowroomprice' => '',
                    'qdata' => NULL,
                    'pp_enddate' => date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'voluntary_excess' => 0,
                    'policy_type' => $tp_only ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => ! $tp_only ? $vehicle_idv : 0,
                    'vehicle_registration_no' => $requestData->rto_code,
                    'tppd_discount' => $tppd_discount,
                    'version_id' => $mmv_data->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => ! $tp_only ? $vehicle_idv : 0,
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => ! $tp_only ? $vehicle_idv : 0,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => [
                        'manf_name' => $mmv_data->vehicle_manufacturer,
                        'model_name' => $mmv_data->vehicle_model_name,
                        'version_name' => $mmv_data->variant,
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'cubic_capacity' => $mmv_data->cubic_capacity,
                        'fuel_type' => $mmv_data->fuel
                    ],
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
                        'logo' => env('APP_URL').config('constants.motorConstant.logos').$productData->logo,
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $requestData->vehicle_register_date,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $car_age,
                        'ic_vehicle_discount' => 0,//$other_discount
                    ],
                    'basic_premium' => ($basic_od),
                    'deduction_of_ncb' => ($ncb_discount),
                    'tppd_premium_amount' => ($basic_tp),
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'total_accessories_amount(net_od_premium)' => '',
                    'total_own_damage' => '',
                    'total_liability_premium' => '',
                    'net_premium' => ($final_net_premium),
                    'service_tax_amount' => ($final_gst_amount),
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => '',
                    'quotation_no' => '',
                    'premium_amount' => ($final_payable_amount),
                    'final_od_premium' => ($final_od_premium),
                    'final_tp_premium' => ($final_tp_premium),
                    'final_total_discount' => ($final_total_discount),
                    'final_net_premium' => ($final_net_premium),
                    'final_gst_amount' => ($final_gst_amount),
                    'final_payable_amount' =>  ($final_payable_amount),
                    'service_data_responseerr_msg' => '',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => $business_types[$requestData->business_type],
                    'policyStartDate' => $policy_start_date,
                    'policyEndDate' => $policy_end_date,
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => 0,//$vehicle_in_90_days,
                    'get_policy_expiry_date' => NULL,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => NULL,
                        'discount_rate' => NULL
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    "max_addons_selection"=> NULL,
                    'add_ons_data' => $addons_data,
                    'applicable_addons' => $applicable_addons
                ]
            ];

            if (($unnamed_passenger) > 0)
            {
                $return_data['Data']['cover_unnamed_passenger_value'] = ($unnamed_passenger);
            }

            if (($ll_paid_driver) > 0)
            {
                $return_data['Data']['default_paid_driver'] = ($ll_paid_driver);
            }

            if (($cpa) > 0)
            {
                $return_data['Data']['compulsory_pa_own_driver'] = ($cpa);
            }

            if (($tppd_discount) > 0)
            {
                $return_data['Data']['tppd_discount'] = ($tppd_discount);
            }

            return camelCase($return_data);
        }
        else
        {
            return camelCase([
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'status' => false,
                'premium_amount' => 0,
                'message' => (isset($premium_response['Message']) && !empty($premium_response['Message']))? createErrorMessage($premium_response['Message']) : 'An error occured while calculating premium'
            ]);
        }
    }
    else
    {
        return camelCase([
            'webservice_id'=>$get_response['webservice_id'],
            'table'=>$get_response['table'],
            'status' => false,
            'premium_amount' => 0,
            'message' => 'Insurer not reachable'
        ]);
    }
}

function createErrorMessage($message)
{
    if (is_array($message))
    {
        return implode('. ', $message);
    }
    
    return $message;
}