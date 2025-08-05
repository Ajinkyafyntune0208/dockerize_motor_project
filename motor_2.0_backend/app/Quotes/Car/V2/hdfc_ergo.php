<?php

use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\MasterRto;
use App\Models\HdfcErgoRtoLocation;
use App\Models\MasterPremiumType;
use App\Models\CvAgentMapping;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class hdfc_ergo
{

    function getQuote($enquiryId, $requestData, $productData)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y') {
            return self::getQuoteV2($enquiryId, $requestData, $productData);
        } else {
            return getQuoteV1($enquiryId, $requestData, $productData);
        }
    }
    
    function getQuoteV1($enquiryId, $requestData, $productData)
    {
        try {
            // if (($requestData->ownership_changed ?? '') == 'Y') {
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
                        'mmv' => $mmv
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
                        'mmv' => $mmv,
                        'message' => 'Vehicle Not Mapped',
                    ]
                ];
            } else if ($mmv_data->ic_version_code == 'DNE') {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle code does not exist with Insurance company',
                    'request' => [
                        'mmv' => $mmv,
                        'message' => 'Vehicle code does not exist with Insurance company',
                    ]
                ];
            } else {
                $car_age = 0;
                $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                $date1 = new DateTime($vehicleDate);
                $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
                $requestData->previous_policy_expiry_date = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
                $interval = $date1->diff($date2);
                $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
                $car_age = ceil($age / 12);
                $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
                //            print_r($car_age);
                // zero depriciation validation
    
                $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
                // if (($car_age >= 15) && ($tp_check == 'true')) {
                //     return [
                //         'premium_amount' => 0,
                //         'status' => false,
                //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
                //     ];
                // }
                if ($car_age > 5 && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('CAR_AGE_VALIDASTRION_ALLOWED_IC')))) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                        'request' => [
                            'car_age' => $car_age,
                            'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                        ]
                    ];
                }
                $rto_code = $requestData->rto_code;
                // Re-arrange for Delhi RTO code - start
                $rto_code = explode('-', $rto_code);
                if ((int)$rto_code[1] < 10) {
                    $rto_code[1] = '0' . (int)$rto_code[1];
                }
                switch ($requestData->business_type) {
    
                    case 'rollover':
                        $business_type = 'Roll Over';
                        break;
    
                    case 'newbusiness':
                        $business_type = 'New Business';
                        break;
    
                    default:
                        $business_type = $requestData->business_type;
                        break;
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
                            'rto_code' => $rto_code,
                            'message' => 'RTO code does not exist',
                        ]
                    ];
                }
                $rto_location = HdfcErgoRtoLocation::where('rto_code', 'like', '%' . $rto_data->rto_number . '%')->first();
                if (empty($rto_location)) {
                    return [
                        'status' => false,
                        'premium' => 0,
                        'message' => 'RTO details does not exist with insurance company',
                        'request' => [
                            'rto_code' => $rto_code,
                            'message' => 'RTO details does not exist with insurance company',
                        ]
                    ];
                }
                $premium_type_old = $premium_type;
                if ($premium_type == 'third_party_breakin') {
                    $premium_type = 'third_party';
                }
                if ($premium_type == 'own_damage_breakin') {
                    $premium_type = 'own_damage';
                }
    
                switch ($premium_type) {
                    case 'third_party_breakin':
                        $premium_type = 'third_party';
                        break;
                    case 'own_damage_breakin':
                        $premium_type = 'own_damage';
                        break;
                }
                // if ($car_age > 15) {
                //     return [
                //         'premium_amount' => 0,
                //         'status' => false,
                //         'message' => 'Quotes are not found for vehicle age greater than 15 years',
                //         'request' => [
                //             'car_age' => $car_age,
                //             'message' => 'Quotes are not found for vehicle age greater than 15 years',
                //         ]
                //     ];
                // }
    
                $vehicle_in_90_days = 'N';
                if ($requestData->business_type == 'newbusiness') {
                    $BusinessType = 'New Vehicle';
                    $Registration_Number = 'NEW';
                    $policy_start_date = today();
                    $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y');
    
                    $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousPolicyType = $previous_ncb = '';
                    $break_in = 'NO';
                    $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
                } else {
                    $today_date = date('Y-m-d') . ' 00:00:00';
                    //die;
                    if ($premium_type_old == 'third_party_breakin') {
                        $policy_start_date = today()->addDay(3);
                    } else {
                        $policy_start_date =  Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1);
                    }
                    $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y');
                    $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
    
                    $BusinessType = 'Roll Over';
                    $proposalType = "RENEWAL";
                    $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
                    $PreviousPolicyToDt = Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y');
                    //$PreviousPolicyType = "MOT-PLT-001";
                    $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
                    $previous_ncb = $NCBEligibilityCriteria == '1' ? "" : $requestData->previous_ncb;
    
                    if ($requestData->vehicle_registration_no != '') {
                        $Registration_Number = $requestData->vehicle_registration_no;
                    } else {
                        $Registration_Number = $rto_code . '-AB-1234';
                    }
    
                    $break_in = "No";
                    $soapAction = "GenerateProposal";
                    //$nilDepreciationCover = ($car_age > 5) ? 0 : 1;
                }
    
                $PreviousPolicy_PolicyNo = 'laksj987987';
                if ($requestData->previous_policy_type == 'Not sure') {
                    $PreviousPolicyToDt = NULL;
                    $requestData->previous_ncb = NULL;
                    $PreviousPolicy_PolicyNo = NULL;
                }
                $zero_dep = '0';
                if ($productData->zero_dep == '0') {
                    $NilDepreciationCoverYN = '1';
                    $key_rplc_yn = '1';
                    $consumable = '1';
                    $Eng_Protector = '1';
                    $zero_dep = '1';
                } else {
                    $NilDepreciationCoverYN = '0';
                    $key_rplc_yn = '0';
                    $consumable = '0';
                    $Eng_Protector = '0';
                }
    
                //without addon
                $without_addon = ($productData->product_identifier == 'without_addon') ? true : false;
                // Addons And Accessories
                $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
                $ElectricalaccessSI = $rsacover = $PAforUnnamedPassengerSI = $antitheft = $voluntary_insurer_discountsf = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
                //$addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
                $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
                $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
                $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
                $LLtoPaidDriverYN = $geoExtension = '0';
                $tppd_cover = 0;
                $voluntary_deductible = 0;
                if (isset($requestData->voluntary_excess_value)) {
                    if ($requestData->voluntary_excess_value == 20000 || $requestData->voluntary_excess_value == 25000) {
                        $voluntary_deductible = $requestData->voluntary_excess_value;
                    }
                }
                if (!empty($discounts)) {
                    foreach ($discounts as $key => $data) {
                        if ($data['name'] == 'TPPD Cover') {
                            $tppd_cover = 6000;
                        }
                    }
                }
                foreach ($additional_covers as $key => $value) {
                    if (in_array('LL paid driver', $value)) {
                        $LLtoPaidDriverYN = '1';
                    }
    
                    if (in_array('PA cover for additional paid driver', $value)) {
                        $PAPaidDriverConductorCleaner = 1;
                        $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
                    }
    
                    if (in_array('Unnamed Passenger PA Cover', $value)) {
                        $PAforUnnamedPassenger = $mmv_data->seating_capacity;
                        $PAforUnnamedPassengerSI = $value['sumInsured'];
                    }
                }
                foreach ($accessories as $key => $value) {
                    if (in_array('geoExtension', $value)) {
                        $geoExtension = '1';
                    }
                    if (in_array('Electrical Accessories', $value)) {
                        $Electricalaccess = 1;
                        $ElectricalaccessSI = $value['sumInsured'];
                    }
    
                    if (in_array('Non-Electrical Accessories', $value)) {
                        $NonElectricalaccess = 1;
                        $NonElectricalaccessSI = $value['sumInsured'];
                    }
    
                    if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                        $externalCNGKIT = 'LPG';
                        $externalCNGKITSI = $value['sumInsured'];
                    }
                }
                $ProductCode = '2311';
    
                if ($premium_type == "third_party") {
                    $ProductCode = '2319';
                }
    
    
                $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                $posp_email = '';
                $posp_name = '';
                $posp_unique_number = '';
                $posp_pan_number = '';
                $posp_aadhar_number = '';
                $posp_contact_number = '';
                $is_pos = false;
                $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type', 'P')
                    ->first();
                if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                    if (config('HDFC_CAR_V1_IS_NON_POS') != 'Y') {
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
                        $pos_code = $hdfc_pos_code;
                    }
                } elseif (config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y') {
                    $is_pos = true;
                    $pos_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
                }
    
                $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
                // $transactionid = customEncrypt($enquiryId);
    
                //Headers
    
                $PRODUCT_CODE  = $ProductCode;
                $SOURCE        = config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR');
                $CHANNEL_ID    = config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR');
                $TRANSACTIONID = $transactionid;
                $CREDENTIAL    = config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR');
    
    
                // token Generation
                $additionData = [
                    'type'              => 'gettoken',
                    'method'            => 'tokenGeneration',
                    'section'           => 'car',
                    'productName'       => $productData->product_name . " ($business_type)",
                    'enquiryId'         => $enquiryId,
                    'transaction_type'  => 'quote',
                    'PRODUCT_CODE'      => $PRODUCT_CODE, //$ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                    'SOURCE'            => $SOURCE, //config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                    'CHANNEL_ID'        => $CHANNEL_ID, //config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                    'TRANSACTIONID'     => $TRANSACTIONID, //$transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                    'CREDENTIAL'        => $CREDENTIAL, //config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                ];
                $token = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);
                $token_data = json_decode($token['response'], TRUE);
                if (isset($token_data['Authentication']['Token'])) {
                    $additionData = [
                        'type'          => 'IDVCalculation',
                        'method'        => 'IDVCalculation',
                        'requestMethod' => 'post',
                        'section'       => 'car',
                        'enquiryId'     => $enquiryId,
                        'productName'   => $productData->product_name . " ($business_type)",
                        'TOKEN'         => $token_data['Authentication']['Token'],
                        'transaction_type'  => 'quote',
                        'PRODUCT_CODE'      => $PRODUCT_CODE, //$ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                        'SOURCE'            => $SOURCE, //config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                        'CHANNEL_ID'        => $CHANNEL_ID, //config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                        'TRANSACTIONID'     => $TRANSACTIONID, //$transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                        'CREDENTIAL'        => $CREDENTIAL, //config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                    ];
                    $idv_request_array = [
                        'TransactionID' => $TRANSACTIONID, //$transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),//$enquiryId,
                        'IDV_DETAILS' => [
                            'Policy_Start_Date' => date('d/m/Y', strtotime($policy_start_date)),
                            'ModelCode' => $mmv_data->vehicle_model_code,
                            'Vehicle_Registration_Date' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                            'RTOCode' => $rto_location->rto_location_code,
                        ]
                    ];
    
                    if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                        $getidvdata = getWsData(config('constants.IcConstants.hdfc_ergo.IDV_LINK_URL_HDFC_ERGO_GIC_MOTOR'), $idv_request_array, 'hdfc_ergo', $additionData);
                        if (!$getidvdata['response']) {
                            return [
                                'webservice_id' => $getidvdata['webservice_id'],
                                'table' => $getidvdata['table'],
                                'status' => false,
                                'premium' => 0,
                                'message' => 'Idv Service Issue',
                            ];
                        }
                        $data_idv = json_decode($getidvdata['response'], TRUE);
                    }
                    $skip_second_call = false;
                    if (isset($data_idv['StatusCode']) && $data_idv['StatusCode'] == 200 || in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                        $idv_min = $data_idv['CalculatedIDV']['MIN_IDV_AMOUNT'] ?? 0;
                        $idv_max = $data_idv['CalculatedIDV']['MAX_IDV_AMOUNT'] ?? 0;
    
                        $idv = $data_idv['CalculatedIDV']['IDV_AMOUNT'] ?? 0;
                        if (config('constants.IcConstants.hdfc_ergo.IDV_DEVIATION_ENABLE') == 'Y') {
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
    
                        if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                            $totalAcessoriesIDV = 0;
                            $totalAcessoriesIDV += (int)($requestData->electrical_acessories_value);
                            $totalAcessoriesIDV += (int)($requestData->nonelectrical_acessories_value);
                            $totalAcessoriesIDV += (int)($requestData->bifuel_kit_value);
    
                            if ($totalAcessoriesIDV > ($idv * 0.25)) {
                                return [
                                    'webservice_id' => $getidvdata['webservice_id'],
                                    'table' => $getidvdata['table'],
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
                        $is_loss_of_use_opted = ($car_age == 0) ? 1 : 0;
    
                        if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_NO_LOSS_OF_BELONGINGS') == 'Y') {
                            $is_loss_of_use_opted = 0;
                        }
                        $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
                        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
                            $addons = $selected_CPA->compulsory_personal_accident;
                            foreach ($addons as $value) {
                                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                                        $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                                    
                                }
                            }
                        }
                        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage")
                        {
                            if ($requestData->business_type == 'newbusiness')
                            {
                                $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3';
                            }
                        }
                        $model_config_premium = [
                            'TransactionID' => $TRANSACTIONID, //$transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),//$enquiryId,
                            'Policy_Details' => [
                                'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                                'ProposalDate' => date('d/m/Y'),
                                'BusinessType_Mandatary' => $BusinessType,
                                'VehicleModelCode' => $mmv_data->vehicle_model_code,
                                'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                                'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                                'YearOfManufacture' => date('Y', strtotime($requestData->vehicle_register_date)),
                                'RTOLocationCode' => $rto_location->rto_location_code,
                                'Vehicle_IDV' => round($idv),
                                'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
                                'PreviousPolicy_PolicyEndDate' => $PreviousPolicyToDt,
                                'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                                'PreviousPolicy_PolicyNo' => $PreviousPolicy_PolicyNo,
                                "EngineNumber" => "TREYTREGREGER",
                                "FinancierCode" => "",
                                "ChassisNumber" => "TREYTREGREGERRRRR",
                                "AgreementType" => "",
                                "BranchName" => "",
                                "PreviousPolicy_IsZeroDept_Cover" => $without_addon ? false : true,
                                "PreviousPolicy_IsRTI_Cover" => $without_addon ? false : true,
                            ],
                            'Req_PvtCar' => [
                                'IsLimitedtoOwnPremises' => '0',
                                'ExtensionCountryCode' => $geoExtension,
                                "ExtensionCountryName" => '',
                                'BiFuelType' => ($externalCNGKITSI > 0 ? "CNG" : ""),
                                'BiFuel_Kit_Value' => $externalCNGKITSI,
                                'POLICY_TYPE' => (($premium_type == 'own_damage') ? 'OD Only' : (($premium_type == "third_party") ? '' : 'OD Plus TP')), // as per the IC in case of tp only value for POLICY_TYPE will be null
                                'LLPaiddriver' => $LLtoPaidDriverYN,
                                'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
                                'IsZeroDept_Cover' => $without_addon ? 0 : $zero_dep,
                                'IsNCBProtection_Cover' => ($car_age <= 5 && !$without_addon) ? 1 : 0,
                                'IsRTI_Cover' => ($interval->days <= 1093 && !$without_addon) ? 1 : 0,
                                'IsCOC_Cover' => ($car_age <= 5 && !$without_addon) ? 1 : 0,
                                'IsEngGearBox_Cover' => ($car_age <= 5 && !$without_addon) ? 1 : 0,
                                'IsEA_Cover' => ($car_age <= 5 && !$without_addon) ? 1 : 0,
                                'isBatteryChargerAccessoryCover' => !$without_addon && $requestData->fuel_type == 'ELECTRIC' ? 1 : 0,
                                'IsEAW_Cover' => ($car_age <= 5 && !$without_addon) ? 1 : 0,
                                'IsTyreSecure_Cover' => (!in_array($mmv_data->vehicle_manufacturer, ['HONDA', 'TATA MOTORS LTD']) && ($car_age <= 3) && !$without_addon) ? 1 : 0,
                                'NoofUnnamedPerson' => $PAforUnnamedPassenger,
                                'IsLossofUseDownTimeProt_Cover' => $without_addon ? 0 : $is_loss_of_use_opted,
                                'UnnamedPersonSI' => $PAforUnnamedPassengerSI,
                                'ElecticalAccessoryIDV' => $ElectricalaccessSI,
                                'NonElecticalAccessoryIDV' => $NonElectricalaccessSI,
                                //'CPA_Tenure' => (($premium_type == 'own_damage') ? '0' : ($requestData->business_type == 'newbusiness' ? '3' : '1')),
                                'CPA_Tenure' => (($premium_type == 'own_damage') ? '0' : (isset($cpa_tenure) ? $cpa_tenure : '1')), // By Default CPA will be 1 Year
                                'Effectivedrivinglicense' => (($premium_type == 'own_damage') ? 'true' : 'false'),
                                // 'Voluntary_Excess_Discount' => $voluntary_deductible, // Voluntary Deductible discount is removed.
                                'POLICY_TENURE' => (($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') ? '3' : '1'),
                                // 'TPPDLimit' => $tppd_cover, as per #23856
                                "Owner_Driver_Nominee_Age" => "24",
                                "Owner_Driver_Nominee_Name" => "Subodh",
                                "Owner_Driver_Nominee_Relationship" => "1311",
                                'NumberOfEmployees' => ($requestData->vehicle_owner_type == 'C' ? $mmv_data->seating_capacity  : 0),
                                /* "POSP_CODE" => [], */
                            ],
                            /* 'Req_POSP' => [//need to remove from quote page
                                'EMAILID' => $posp_email,
                                'NAME' => $posp_name,
                                'UNIQUE_CODE' => $posp_unique_number,
                                'STATE' => '',
                                'PAN_CARD' => $posp_pan_number,
                                'ADHAAR_CARD' => $posp_aadhar_number,
                                'NUM_MOBILE_NO' => $posp_contact_number
                            ], */
                        ];
                        if ($is_pos) {
                            $posp_code = ($idv >= 5000000) ? [] : (!empty($pos_code) ? $pos_code : []);
                            $model_config_premium['Req_PvtCar']['POSP_CODE'] = $posp_code;
                        }
                        $additionData = [
                            'type' => 'PremiumCalculation',
                            'method' => 'PremiumCalculation',
                            'requestMethod' => 'post',
                            'section' => 'car',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name . " ($business_type)",
                            'TOKEN' => $token_data['Authentication']['Token'],
                            'transaction_type' => 'quote',
                            'PRODUCT_CODE'  => $PRODUCT_CODE, //$ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                            'SOURCE'        => $SOURCE, //config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                            'CHANNEL_ID'    => $CHANNEL_ID, //config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                            'TRANSACTIONID' => $TRANSACTIONID, //$transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                            'CREDENTIAL'    => $CREDENTIAL, //config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                        ];
                        if ($requestData->previous_policy_type == 'Not sure') {
                            unset($model_config_premium['Policy_Details']['PreviousPolicy_NCBPercentage']);
                            unset($model_config_premium['Policy_Details']['PreviousPolicy_PolicyEndDate']);
                            unset($model_config_premium['Policy_Details']['PreviousPolicy_PolicyNo']);
                            unset($model_config_premium['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                            unset($model_config_premium['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                        }
    
                        if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $idv >= 5000000) {
                            $model_config_premium['Req_POSP'] = [
                                'EMAILID' => '',
                                'NAME' => '',
                                'UNIQUE_CODE' => '',
                                'STATE' => '',
                                'PAN_CARD' => '',
                                'ADHAAR_CARD' => '',
                                'NUM_MOBILE_NO' => ''
                            ];
                        } elseif (!empty($pos_data)) {
                            $model_config_premium['Req_POSP'] = [
                                'EMAILID' => $pos_data->agent_email,
                                'NAME' => $pos_data->agent_name,
                                'UNIQUE_CODE' => $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '',
                                'STATE' => '',
                                'PAN_CARD' => $pos_data->pan_no,
                                'ADHAAR_CARD' => $pos_data->aadhar_no,
                                'NUM_MOBILE_NO' => $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : ''
                            ];
                        }
                        if (!$skip_second_call) {
                            $checksum_data = checksum_encrypt($model_config_premium);
                            $additionData['checksum'] = $checksum_data;
                            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'hdfc_ergo', $checksum_data, 'CAR');
    
                            if ($is_data_exits_for_checksum['found'] && $refer_webservice) {
                                $getpremium = $is_data_exits_for_checksum;
                            } else {
                                $getpremium = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_PREMIUM'), $model_config_premium, 'hdfc_ergo', $additionData);
                            }
                        }
    
                        if (!$getpremium['response']) {
                            return [
                                'webservice_id' => $getpremium['webservice_id'],
                                'table' => $getpremium['table'],
                                'status' => false,
                                'premium' => 0,
                                'message' => 'Premium Service issue',
                            ];
                        }
    
                        $arr_premium = json_decode($getpremium['response'], TRUE);
                        //    print_r(json_encode([$model_config_premium,$arr_premium]));
                        //    die;
                        if (($arr_premium['StatusCode'] == '200')) {
                            $premium_data = $arr_premium['Resp_PvtCar'];
                            $idv = ($premium_type != 'third_party') ? (string)round($premium_data['IDV']) : '0';
                            $igst = $anti_theft = $other_discount = $rsapremium = $pa_paid_driver = $zero_dep_amount
                                = $ncb_discount = $tppd = $final_tp_premium = $final_od_premium =
                                $final_net_premium = $final_payable_amount = $basic_od =
                                $electrical_accessories = $lpg_cng_tp = $lpg_cng =
                                $non_electrical_accessories = $pa_owner = $voluntary_excess =
                                $pa_unnamed = $key_rplc = $tppd_discount = $ll_paid_driver =
                                $personal_belonging = $engine_protection = $consumables_cover =
                                $rti = $tyre_secure = $ncb_protection = $GeogExtension_od = $GeogExtension_tp = $OwnPremises_OD = $OwnPremises_TP = 0;
                            $geog_Extension_OD_Premium = 0;
                            $geog_Extension_TP_Premium = 0;
                            $legal_liability_to_employee = 0;
                            $batteryProtect = 0;
                            if (!empty($premium_data['PAOwnerDriver_Premium'])) {
                                $pa_owner = round($premium_data['PAOwnerDriver_Premium']);
                            }
                            if (!empty($premium_data['GeogExtension_ODPremium'])) {
                                $GeogExtension_od = round($premium_data['GeogExtension_ODPremium']);
                            }
                            if (!empty($premium_data['GeogExtension_TPPremium'])) {
                                $GeogExtension_tp = round($premium_data['GeogExtension_TPPremium']);
                            }
    
                            if (!empty($premium_data['LimitedtoOwnPremises_OD_Premium'])) {
                                $OwnPremises_OD = round($premium_data['LimitedtoOwnPremises_OD_Premium']);
                            }
                            if (!empty($premium_data['LimitedtoOwnPremises_TP_Premium'])) {
                                $OwnPremises_TP = round($premium_data['LimitedtoOwnPremises_TP_Premium']);
                            }
                            if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                                $zero_dep_amount = round($premium_data['Vehicle_Base_ZD_Premium']);
                            }
                            if (!empty($premium_data['EA_premium'])) {
                                $rsapremium = round($premium_data['EA_premium']);
                            }
                            if (!empty($premium_data['Loss_of_Use_Premium'])) {
                                $personal_belonging = round($premium_data['Loss_of_Use_Premium']);
                            }
                            if (!empty($premium_data['Vehicle_Base_NCB_Premium'])) {
                                $ncb_protection = round($premium_data['Vehicle_Base_NCB_Premium']);
                            }
                            if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                                $ncb_discount = round($premium_data['NCBBonusDisc_Premium']);
                            }
                            if (!empty($premium_data['Vehicle_Base_ENG_Premium'])) {
                                $engine_protection = round($premium_data['Vehicle_Base_ENG_Premium']);
                            }
                            if (!empty($premium_data['Vehicle_Base_COC_Premium'])) {
                                $consumables_cover = round($premium_data['Vehicle_Base_COC_Premium']);
                            }
                            if (!empty($premium_data['Vehicle_Base_RTI_Premium'])) {
                                $rti = round($premium_data['Vehicle_Base_RTI_Premium']);
                            }
                            if (!empty($premium_data['EAW_premium'])) {
                                $key_rplc = round($premium_data['EAW_premium']);
                            }
                            if (!empty($premium_data['UnnamedPerson_premium'])) {
                                $pa_unnamed = round($premium_data['UnnamedPerson_premium']);
                            }
                            if (!empty($premium_data['Electical_Acc_Premium'])) {
                                $electrical_accessories = round($premium_data['Electical_Acc_Premium']);
                            }
                            if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                                $non_electrical_accessories = round($premium_data['NonElectical_Acc_Premium']);
                            }
                            if (!empty($premium_data['BiFuel_Kit_OD_Premium'])) {
                                $lpg_cng = round($premium_data['BiFuel_Kit_OD_Premium']);
                            }
                            if (!empty($premium_data['BiFuel_Kit_TP_Premium'])) {
                                $lpg_cng_tp = round($premium_data['BiFuel_Kit_TP_Premium']);
                            }
                            if (!empty($premium_data['PAPaidDriver_Premium'])) {
                                $pa_paid_driver = round($premium_data['PAPaidDriver_Premium']);
                            }
                            if (!empty($premium_data['PaidDriver_Premium'])) {
                                $ll_paid_driver = round($premium_data['PaidDriver_Premium']);
                            }
                            if (!empty($premium_data['VoluntartDisc_premium'])) {
                                $voluntary_excess = round($premium_data['VoluntartDisc_premium']);
                            }
                            if (!empty($premium_data['Vehicle_Base_TySec_Premium'])) {
                                $tyre_secure = round($premium_data['Vehicle_Base_TySec_Premium']);
                            }
                            if (!empty($premium_data['AntiTheftDisc_Premium'])) {
                                $anti_theft = round($premium_data['AntiTheftDisc_Premium']);
                            }
                            if (!empty($premium_data['Net_Premium'])) {
                                $final_net_premium = round($premium_data['Net_Premium']);
                            }
                            if (!empty($premium_data['Total_Premium'])) {
                                $final_payable_amount = round($premium_data['Total_Premium']);
                            }
                            if (!empty($premium_data['InBuilt_BiFuel_Kit_Premium'])) {
                                $lpg_cng_tp = round($premium_data['InBuilt_BiFuel_Kit_Premium']);
                            }
                            if (!empty($premium_data['NumberOfEmployees_Premium'])) {
                                $legal_liability_to_employee = round($premium_data['NumberOfEmployees_Premium']);
                            }
                            if(!empty($premium_data['BatteryChargerAccessory_Premium'])) {
                                $batteryProtect = $premium_data['BatteryChargerAccessory_Premium'];
                            }
    
                            $final_tp_premium = round($premium_data['Basic_TP_Premium']) + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $ll_paid_driver + $GeogExtension_tp + $OwnPremises_TP + $legal_liability_to_employee;
                            $final_total_discount = $anti_theft + $ncb_discount + $voluntary_excess + $premium_data['TPPD_premium'];
    
                            if ($electrical_accessories > 0) {
                                $zero_dep_amount += (int)$premium_data['Elec_ZD_Premium'];
                                $engine_protection += (int)$premium_data['Elec_ENG_Premium'];
                                $ncb_protection += (int)$premium_data['Elec_NCB_Premium'];
                                $consumables_cover += (int)$premium_data['Elec_COC_Premium'];
                                $rti += (int)$premium_data['Elec_RTI_Premium'];
    
                                // THIS BELOW BLOCK IS COMMENTED BECAUSE IT WAS GIVING ERROR OF PREMIUM MISMATCH
                                // $electrical_accessories += (int)$premium_data['Elec_ZD_Premium'];
                                // $electrical_accessories += (int)$premium_data['Elec_ENG_Premium'];
                                // $electrical_accessories += (int)$premium_data['Elec_NCB_Premium'];
                                // $electrical_accessories += (int)$premium_data['Elec_COC_Premium'];
                                // $electrical_accessories += (int)$premium_data['Elec_RTI_Premium'];
                            }
    
                            if ($non_electrical_accessories > 0) {
                                $zero_dep_amount += (int)$premium_data['NonElec_ZD_Premium'];
                                $engine_protection += (int)$premium_data['NonElec_ENG_Premium'];
                                $ncb_protection += (int)$premium_data['NonElec_NCB_Premium'];
                                $consumables_cover += (int)$premium_data['NonElec_COC_Premium'];
                                $rti += (int)$premium_data['NonElec_RTI_Premium'];
    
                                // THIS BELOW BLOCK IS COMMENTED BECAUSE IT WAS GIVING ERROR OF PREMIUM MISMATCH
                                // $non_electrical_accessories += (int)$premium_data['NonElec_ZD_Premium'];
                                // $non_electrical_accessories += (int)$premium_data['NonElec_ENG_Premium'];
                                // $non_electrical_accessories += (int)$premium_data['NonElec_NCB_Premium'];
                                // $non_electrical_accessories += (int)$premium_data['NonElec_COC_Premium'];
                                // $non_electrical_accessories += (int)$premium_data['NonElec_RTI_Premium'];
                            }
    
                            if ($lpg_cng > 0) {
                                $zero_dep_amount += (int)$premium_data['Bifuel_ZD_Premium'];
                                $engine_protection += (int)$premium_data['Bifuel_ENG_Premium'];
                                $ncb_protection += (int)$premium_data['Bifuel_NCB_Premium'];
                                $consumables_cover += (int)$premium_data['Bifuel_COC_Premium'];
                                $rti += (int)$premium_data['Bifuel_RTI_Premium'];
    
                                // THIS BELOW BLOCK IS COMMENTED BECAUSE IT WAS GIVING ERROR OF PREMIUM MISMATCH
                                // $lpg_cng += (int)$premium_data['Bifuel_ZD_Premium'];
                                // $lpg_cng += (int)$premium_data['Bifuel_ENG_Premium'];
                                // $lpg_cng += (int)$premium_data['Bifuel_NCB_Premium'];
                                // $lpg_cng += (int)$premium_data['Bifuel_COC_Premium'];
                                // $lpg_cng += (int)$premium_data['Bifuel_RTI_Premium'];
                            }
    
                            $final_od_premium = $premium_data['Basic_OD_Premium'] + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;
    
                            $add_ons = [];
                            $applicable_addons = [];
                            if ($car_age <= 3) {
                                array_push($applicable_addons, "returnToInvoice");
                            }
                            if ($car_age == 0 && (int)$personal_belonging > 0) {
                                array_push($applicable_addons, "lopb");
                            }
                            if ($car_age > 5) {
                                $add_ons = [
                                    'in_built' => [],
                                    'additional' => [
                                        'zeroDepreciation' => 0,
                                        'roadSideAssistance' => 0,
                                        'engineProtector' => 0,
                                        'ncbProtection' => 0,
                                        'keyReplace' => 0,
                                        'consumables' => 0,
                                        'tyreSecure' => 0,
                                        'returnToInvoice' => 0,
                                        'lopb' => 0,
                                        'batteryProtect' => round($batteryProtect)
                                    ],
                                    'other' => []
                                ];
                            } elseif ($zero_dep = '1') {
                                $add_ons = [
                                    'in_built' => [],
                                    'additional' => [
                                        'zeroDepreciation' => round($zero_dep_amount),
                                        'roadSideAssistance' => round($rsapremium),
                                        'engineProtector' => round($engine_protection),
                                        'ncbProtection' => round($ncb_protection),
                                        'keyReplace' => round($key_rplc),
                                        'consumables' => round($consumables_cover),
                                        'tyreSecure' => round($tyre_secure),
                                        'returnToInvoice' => round($rti),
                                        'lopb' => round($personal_belonging),
                                        'batteryProtect' => round($batteryProtect)
                                    ],
                                    'other' => []
                                ];
                                array_push($applicable_addons, "roadSideAssistance", "ncbProtection", "tyreSecure", "zeroDepreciation", "engineProtector", "consumables", "keyReplace");
                            } else {
                                $add_ons = [
                                    'in_built' => [],
                                    'additional' => [
                                        'zero_depreciation' => 0,
                                        'roadSideAssistance' => round($rsapremium),
                                        'engineProtector' => round($engine_protection),
                                        'ncbProtection' => round($ncb_protection),
                                        'keyReplace' => round($key_rplc),
                                        'consumables' => round($consumables_cover),
                                        'tyreSecure' => round($tyre_secure),
                                        'returnToInvoice' => round($rti),
                                        'lopb' => round($personal_belonging),
                                    ],
                                    'other' => []
                                ];
                                array_push($applicable_addons, "roadSideAssistance", "ncbProtection", "tyreSecure", "zeroDepreciation", "engineProtector", "consumables", "keyReplace");
                            }

                            if (!empty($batteryProtect)) {
                                array_push($applicable_addons, 'batteryProtect');
                            }
                            
                            foreach ($add_ons['additional'] as $k => $v) {
                                if (empty($v)) {
                                    unset($add_ons['additional'][$k]);
                                }
                            }
                            if ((int)$tyre_secure == 0 && ($key = array_search('tyreSecure', $applicable_addons)) !== false) {
                                array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                            }
                            $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
                            $add_ons['additional_premium'] = array_sum($add_ons['additional']);
                            $add_ons['other_premium'] = array_sum($add_ons['other']);
                            $final_payable_amount = $final_od_premium + $final_tp_premium - $final_total_discount + $add_ons['additional_premium'];
                            $final_payable_amount = $final_payable_amount * (1 + (18.0 / 100));
                            $data_response = [
                                'webservice_id' => $getpremium['webservice_id'],
                                'table' => $getpremium['table'],
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
                                    'policy_type' => ($premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive')),
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
                                        'ic_vehicle_discount' => 0,
                                    ],
                                    'ic_vehicle_discount' => 0,
                                    'basic_premium' => ($premium_type != 'third_party') ? (string)round($premium_data['Basic_OD_Premium']) : '0',
                                    'motor_electric_accessories_value' => round($electrical_accessories),
                                    'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                                    'motor_lpg_cng_kit_value' => round($lpg_cng),
                                    // 'GeogExtension_ODPremium' => round($GeogExtension_od),
                                    // 'GeogExtension_TPPremium' => round($GeogExtension_tp),
                                    // 'LimitedtoOwnPremises_TP'=>round($OwnPremises_TP),
                                    // 'LimitedtoOwnPremises_OD'=>round($OwnPremises_OD),
                                    'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                                    'total_own_damage' => round($final_od_premium),
                                    'tppd_premium_amount' => (string)round($premium_data['Basic_TP_Premium']),
                                    'tppd_discount' => round($premium_data['TPPD_premium']),
                                    'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                                    'cover_unnamed_passenger_value' => (int)$pa_unnamed,
                                    'default_paid_driver' => (int)$ll_paid_driver,
                                    'motor_additional_paid_driver' => round($pa_paid_driver),
                                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                                    'cng_lpg_tp' => round($lpg_cng_tp),
                                    'seating_capacity' => $mmv_data->seating_capacity,
                                    'deduction_of_ncb' => round(abs($ncb_discount)),
                                    'antitheft_discount' => round(abs($anti_theft)),
                                    'aai_discount' => '', //$automobile_association,
                                    'voluntary_excess' => $voluntary_excess,
                                    'other_discount' => 0,
                                    'total_liability_premium' => round($final_tp_premium),
                                    'net_premium' => round($final_payable_amount),
                                    'service_tax_amount' => round($final_payable_amount - $final_net_premium),
                                    'service_tax' => 18,
                                    'total_discount_od' => 0,
                                    'add_on_premium_total' => 0,
                                    'addon_premium' => 0,
                                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                    'quotation_no' => '',
                                    'premium_amount' => round($final_payable_amount),
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
                                    'other_covers' => [
                                        'LegalLiabilityToEmployee' => $legal_liability_to_employee ?? 0
                                    ],
                                    'premium_data' => $premium_data,
                                    'is_premium_online' => $productData->is_premium_online,
                                    'is_proposal_online' => $productData->is_proposal_online,
                                    'is_payment_online' => $productData->is_payment_online,
                                    'policy_id' => $productData->policy_id,
                                    'insurane_company_id' => $productData->company_id,
                                    'max_addons_selection' => null,
                                    'add_ons_data' => $add_ons,
                                    'applicable_addons' => $applicable_addons,
                                    'final_od_premium' => round($final_od_premium),
                                    'final_tp_premium' => round($final_tp_premium),
                                    'final_total_discount' => round(abs($final_total_discount)),
                                    'final_net_premium' => round($final_payable_amount),
                                    // 'final_gst_amount' => round($final_payable_amount - $final_net_premium),
                                    'final_payable_amount' => round($final_payable_amount),
                                    'mmv_detail' => [
                                        'manf_name' => $mmv_data->vehicle_manufacturer,
                                        'model_name' => $mmv_data->vehicle_model_name,
                                        'version_name' => $mmv_data->variant,
                                        'fuel_type' => $mmv_data->fuel,
                                        'seating_capacity' => $mmv_data->seating_capacity,
                                        'carrying_capacity' => $mmv_data->carrying_capacity,
                                        'cubic_capacity' => $mmv_data->cubic_capacity,
                                        'gross_vehicle_weight' => '',
                                        'vehicle_type' => 'Private Car',
                                    ],
                                ]
                            ];
                            if (!empty($legal_liability_to_employee)) {
    
                                $data_response['Data']['other_covers'] = [
                                    'LegalLiabilityToEmployee' => $legal_liability_to_employee ?? 0
                                ];
                                $data_response['Data']['LegalLiabilityToEmployee'] = $legal_liability_to_employee ?? 0;
                            }
                            if ($data_response['Data']['cng_lpg_tp'] == 0) {
                                unset($data_response['Data']['cng_lpg_tp']);
                            }
                            if ($data_response['Data']['motor_lpg_cng_kit_value'] == 0) {
                                unset($data_response['Data']['motor_lpg_cng_kit_value']);
                            }
                            if(isset($cpa_tenure))
                            {
                            if($requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
                            {
                                $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
                            }
                            }
    
                            $return_data = camelCase($data_response);
                            // return camelCase($data_response);
                        } else {
                            $return_data = [
                                'webservice_id' => $getpremium['webservice_id'],
                                'table' => $getpremium['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => $arr_premium['Error'],
                            ];
                        }
                    } else {
                        $return_data = [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => $data_idv['Error']
                        ];
                    }
                } else {
                    $return_data = [
                        'webservice_id' => $token['webservice_id'],
                        'table' => $token['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => $token_data
                    ];
                }
            }
        } catch (\Exception $e) {
            $return_data = [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Premium Service Issue' . $e->getMessage(),
    
            ];
        }
        return $return_data;
    }
    
    public static function getQuoteV2($enquiryId, $requestData, $productData)
    {
        $refer_webservice = $productData->db_config['quote_db_cache'];
    
        // if (($requestData->ownership_changed ?? '') == 'Y') {
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
    
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle Not Mapped',
                ]
            ];
        } elseif ($mmv_data->ic_version_code == 'DNE') {
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
        $rto_data = HdfcErgoRtoLocation::where('rto_code', $rto_code)->first();
        if (!$rto_data) {
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
        $od_only = in_array($premium_type, ['own_damage', 'own_damage_breakin']);
    
        if ($requestData->business_type == 'rollover') {
            if (isset($requestData->ownership_changed) && $requestData->ownership_changed == 'Y') {
                $business_type = 'USED';
            } else {
                $business_type = 'ROLLOVER';
            }
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        } elseif ($requestData->business_type == 'newbusiness') {
            $business_type = 'NEW';
            $policy_start_date = $tp_only ? date('d-m-Y', strtotime('tomorrow')) : date('d-m-Y', time());
        } elseif ($requestData->business_type == 'breakin') {
            $business_type = 'ROLLOVER';
    
            if (($requestData->policy_type == 'own_damage' && $requestData->previous_policy_type == 'Third-party') || $requestData->previous_policy_type == 'Not sure') {
                $business_type = 'USED';
            }
    
            $policy_start_date = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date));
        }
    
        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))));
    
        $car_age = 0;
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);
    
        if ($interval->y >= 15 && $productData->zero_dep == 0) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero depreciation is not available for vehicles having age more than 15 years'
            ];
        }
        $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        // if (($interval->y >= 15) && ($tp_check == 'true')) {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
        //     ];
        // }
    
        $policy_type = '';
        if ($requestData->policy_type == 'comprehensive') {
            $policy_type = 'Comprehensive';
        } elseif ($requestData->policy_type == 'own_damage') {
            $policy_type = 'ODOnly';
        }
    
        $is_cng = in_array($mmv_data->fuel, ['LPG', 'CNG']);
        $electrical_accessories_sa = 0;
        $non_electrical_accessories_sa = 0;
        $lp_cng_kit_sa = 0;
        $is_lpg_cng_kit = 'NO';
        $motor_cnglpg_type = '';
        $pa_paid_driver = 'NO';
        $pa_paid_driver_sa = 0;
        $is_pa_unnamed_passenger = 'NO';
        $pa_unnamed_passenger_sa = 0;
        $is_ll_paid_driver = 'NO';
        $is_tppd_discount = 'NO';
    
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)
            ->first();
    
        if ($selected_addons) {
            if ($selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '') {
                foreach ($selected_addons['accessories'] as $accessory) {
                    if ($accessory['name'] == 'Electrical Accessories') {
                        $electrical_accessories_sa = $accessory['sumInsured'];
                    } elseif ($accessory['name'] == 'Non-Electrical Accessories') {
                        $non_electrical_accessories_sa = $accessory['sumInsured'];
                    } elseif ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG' && !$is_cng) {
                        $is_lpg_cng_kit = 'YES';
                        $lp_cng_kit_sa = $accessory['sumInsured'];
                        $motor_cnglpg_type = 'CNG';
                    }
                }
            }
    
            if ($selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '') {
                foreach ($selected_addons['additional_covers'] as $additional_cover) {
                    if ($additional_cover['name'] == 'PA cover for additional paid driver') {
                        $pa_paid_driver = 'YES';
                        $pa_paid_driver_sa = $additional_cover['sumInsured'];
                    }
    
                    if ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                        $is_pa_unnamed_passenger = 'YES';
                        $pa_unnamed_passenger_sa = $additional_cover['sumInsured'];
                    }
    
                    if ($additional_cover['name'] == 'LL paid driver') {
                        $is_ll_paid_driver = 'YES';
                    }
                }
            }
            $iszerodep = 'NO';
            $isLOPB = 'NO';
            $isEmergency_assistance_wider = 'NO';
            $Emergency_assistance_cover = 'NO';
            $Engine_and_Gear_box_protector = 'NO';
            if (($productData->zero_dep == 0) && ($interval->y < 5)) {
                $iszerodep = 'YES';
            } else {
                $iszerodep = 'NO';
            }
            if($interval->y > 5){
                $iszerodep = 'NO';
                $isLOPB = 'NO';
                $isEmergency_assistance_wider = 'NO';
                $Emergency_assistance_cover = 'NO';
                $Engine_and_Gear_box_protector = 'NO';
            }
            //implement Addon's Bundle Logic
            switch ($productData->product_identifier) {
                case 'Essential ZD':
                    if ($interval->y >= 5 && $interval->y < 10) {
                        $iszerodep = 'YES';
                        $isLOPB = 'YES';
                        $isEmergency_assistance_wider = 'YES';
                        $Emergency_assistance_cover = 'YES';
                    }
                    break;
                case 'Essential_EGP':
                    if ($interval->y >= 5 && $interval->y < 10) {
                        $iszerodep = 'YES';
                        $isLOPB = 'YES';
                        $isEmergency_assistance_wider = 'YES';
                        $Emergency_assistance_cover = 'YES';
                        $Engine_and_Gear_box_protector = 'YES';
                    }
                    break;
            }
            if ($selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '') {
                foreach ($selected_addons['discounts'] as $discount) {
                    if ($discount['name'] == 'TPPD Cover') {
                        $is_tppd_discount = $tp_only ? 'YES' : 'NO';
                    }
                }
            }
        }
        $posp_code = '';
        $is_pos = false;
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type', 'P')
                    ->first();
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            if (config('HDFC_CAR_V2_IS_NON_POS') != 'Y')
            {
                $posp_code = config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_TEST_POSP_CODE') : $pos_data->pan_no;
            }
            $is_pos = true;
        }
        elseif (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
        {
            $posp_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_TEST_POSP_CODE');
        }

        $premium_request = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE'),
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
            'IsPreviousClaim' => $requestData->is_claim == 'Y' || $requestData->previous_policy_type == 'Third-party' ? 'YES' : 'NO',
            'PreviousPolicyEndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
            'PreviousNCBDiscountPercentage' => $requestData->previous_policy_type == 'Third-party' ? 0 : $requestData->previous_ncb,
            'PreviousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? 'ThirdParty' : 'Comprehensive',
            'PreviousInsurerId' => 9,
            'PospCode' => $posp_code,
            'CORDiscount' => 0,
            'AddOnCovers' => [
                'IsZeroDepTakenLastYear' => $productData->zero_dep == 0 ? 1 : 0,
                'IsZeroDepCover' => $iszerodep,
                'IsLossOfUse' => $isLOPB, //$interval->y < 1 ? "YES" : "NO",
                //'IsEmergencyAssistanceCover' => $interval->y < 15 ? "YES" : "NO",
                'IsEmergencyAssistanceCover' => $Emergency_assistance_cover,
                'IsNoClaimBonusProtection' => $interval->y < 5 ? "YES" : "NO",
                'IsEngineAndGearboxProtectorCover' => $Engine_and_Gear_box_protector,
                'IsCostOfConsumable' => $interval->y < 5 ? "YES" : "NO",
                'IsReturntoInvoice' => $interval->y < 3 ? "YES" : "NO",
                "ReturntoInvoicePlanType"=>$interval->y < 3 ? "A_PURCHASE_INVOICE_VALUE" : "",
                'IsEmergencyAssistanceWiderCover' => $isEmergency_assistance_wider,
                'IsTyreSecureCover' => $interval->y < 3 ? "YES" : "NO",
                'NonelectricalAccessoriesIdv' => $non_electrical_accessories_sa,
                'ElectricalAccessoriesIdv' => $electrical_accessories_sa,
                'LpgCngKitIdv' => $lp_cng_kit_sa,
                'SelectedFuelType' => $motor_cnglpg_type,
                'IsPAPaidDriver' => $pa_paid_driver,
                'PAPaidDriverSumInsured' => $pa_paid_driver_sa,
                'IsPAUnnamedPassenger' => $is_pa_unnamed_passenger,
                'PAUnnamedPassengerNo' => $is_pa_unnamed_passenger == 'YES' ? $mmv_data->carrying_capacity : 0,
                'PAPerUnnamedPassengerSumInsured' => $pa_unnamed_passenger_sa,
                'IsLegalLiabilityDriver' => $is_ll_paid_driver,
                'LLPaidDriverNo' => $is_ll_paid_driver == 'YES' ? 1 : 0,
                'IsTPPDDiscount' => $is_tppd_discount,
                'IsExLpgCngKit' => $is_cng ? 'NO' : $is_lpg_cng_kit,
                'IsLLEmployee' => ($requestData->vehicle_owner_type == 'C' && !$od_only ? 'YES'  : 'NO'),
                'LLEmployeeNo' => ($requestData->vehicle_owner_type == 'C'  && !$od_only ? $mmv_data->seating_capacity  : 0),
                'CpaYear' => $requestData->vehicle_owner_type == 'I' ? ($requestData->business_type == 'newbusiness' ? 3 : 1) : 0,
            ],
        ];
        if ($productData->product_identifier == 'Essential ZD') {
            $premium_request['IsEssentialZD'] = 'true';
        }
        if ($productData->product_identifier == 'Essential_EGP') {
            $premium_request['Isessentialegp'] = 'true';
        }
        if (in_array($premium_type, ['own_damage', 'own_damage_breakin'])) {
            $premium_request['TPExistingEndDate'] = date('Y-m-d', strtotime('+2 year', strtotime($requestData->previous_policy_expiry_date)));
            unset($premium_request['AddOnCovers']['IsPAUnnamedPassenger']);
            unset($premium_request['AddOnCovers']['PAUnnamedPassengerNo']); //PA Passenger is not allowed for ODOnly policy type
            unset($premium_request['AddOnCovers']['IsLegalLiabilityDriver']);
            unset($premium_request['AddOnCovers']['LLPaidDriverNo']); // Legal liability driver is not allowed for ODOnly policy type
            unset($premium_request['AddOnCovers']['CpaYear']); // CpaYear is not allowed for ODOnly policy type
        }
    
        if ($od_only) {
            unset($premium_request['AddOnCovers']['LLEmployeeNo']);
            unset($premium_request['AddOnCovers']['IsLLEmployee']);
        }
        $checksum_data = checksum_encrypt($premium_request);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'hdfc_ergo', $checksum_data, 'CAR');
    
        if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
            $get_response = $is_data_exits_for_checksum;
        } else {
    
            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
                'section' => $productData->product_sub_type_code,
                'method' => 'Premium Calculation',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'checksum' => $checksum_data,
                'productName' => $productData->product_name,
                'transaction_type' => 'quote',
                'headers' => [
                    'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                    'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                    'Content-Type' => 'application/json',
                    'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                    'Accept-Language' => 'en-US,en;q=0.5'
                ]
            ]);
        }
        $premium_response = $get_response['response'];
        if ($premium_response) {
            $premium_response = json_decode($premium_response, TRUE);
            $skip_second_call = false;
    
            if (isset($premium_response['Status']) && $premium_response['Status'] == 200) {
                if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $vehicle_idv = $premium_response['Data'][0]['VehicleIdv'];
                    $min_idv = $premium_response['Data'][0]['VehicleIdvMin'];
                    $max_idv = $premium_response['Data'][0]['VehicleIdvMax'];
                    $default_idv = $premium_response['Data'][0]['VehicleIdv'];
    
                    if (config('constants.IcConstants.hdfc_ergo.IDV_DEVIATION_ENABLE_V2') !== 'N') {
                        if ($requestData->is_idv_changed == 'Y') {
                            if ($requestData->edit_idv >= $max_idv) {
                                $premium_request['RequiredIDV'] = $max_idv;
                            } elseif ($requestData->edit_idv <= $min_idv) {
                                $premium_request['RequiredIDV'] = $min_idv;
                            } else {
                                $premium_request['RequiredIDV'] = $requestData->edit_idv;
                            }
                        } else {
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
                    } else {
                        $premium_request['RequiredIDV'] = $default_idv;
                    }
                    if ($is_pos) {
                        $posp_code = ($idv >= 5000000) ? '' : (!empty($pos_code) ? $pos_code : '');
                        $premium_request['PospCode'] = $posp_code;
                    }
                    if (!$skip_second_call) {
                        $checksum_data = checksum_encrypt($premium_request);
                        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'hdfc_ergo', $checksum_data, 'CAR');
    
                        if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
                            $get_response = $is_data_exits_for_checksum;
                        } else {
                            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
                                'section' => $productData->product_sub_type_code,
                                'method' => 'Premium Re-calculation',
                                'requestMethod' => 'post',
                                'enquiryId' => $enquiryId,
                                'checksum' => $checksum_data,
                                'productName' => $productData->product_name,
                                'transaction_type' => 'quote',
                                'headers' => [
                                    'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                                    'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                                    'Content-Type' => 'application/json',
                                    'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                                    'Accept-Language' => 'en-US,en;q=0.5'
                                ]
                            ]);
                        }
                    }
                    $premium_response = $get_response['response'];
                    if ($premium_response) {
                        $premium_response = json_decode($premium_response, TRUE);
    
                        if (isset($premium_response['Status'])) {
                            if ($premium_response['Status'] != 200) {
                                return camelCase([
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'status' => false,
                                    'premium_amount' => 0,
                                    'message' => (isset($premium_response['Message']) && !empty($premium_response['Message'])) ? createErrorMessage($premium_response['Message']) : 'An error occured while recalculating premium'
                                ]);
                            }
                        } else {
                            return camelCase([
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'premium_amount' => 0,
                                'message' => 'An error occured while recalculating premium'
                            ]);
                        }
                    } else {
                        return camelCase([
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'Insurer not reachable'
                        ]);
                    }
                }
    
                $basic_od = $premium_response['Data'][0]['BasicODPremium'] ?? 0;
                $basic_tp = $premium_response['Data'][0]['BasicTPPremium'] ?? 0;
                $electrical_accessories = 0;
                $non_electrical_accessories = 0;
                $lpg_cng_kit_od = 0;
                $cpa = 0;
                $unnamed_passenger = 0;
                $ll_paid_driver = 0;
                $legal_liability_to_employee = 0;
                $pa_paid_driver = 0;
                $zero_depreciation = 0;
                $road_side_assistance = 0;
                $ncb_protection = 0;
                $engine_protection = 0;
                $consumable = 0;
                $key_replacement = 0;
                $tyre_secure = 0;
                $return_to_invoice = 0;
                $loss_of_personal_belongings = 0;
                $lpg_cng_kit_tp = $premium_response['Data'][0]['LpgCngKitTPPremium'] ?? 0;
                $ncb_discount = 0;
                $tppd_discount = $premium_response['Data'][0]['TppdDiscountAmount'] ?? 0;
                $geog_Extension_OD_Premium = 0;
                $geog_Extension_TP_Premium = 0;
    
                if (isset($premium_response['Data'][0]['BuiltInLpgCngKitPremium']) && $premium_response['Data'][0]['BuiltInLpgCngKitPremium'] != 0.0) {
                    $lpg_cng_kit_tp = $premium_response['Data'][0]['BuiltInLpgCngKitPremium'] ?? 0;
                }
    
                if (isset($premium_response['Data'][0]['AddOnCovers'])) {
                    foreach ($premium_response['Data'][0]['AddOnCovers'] as $addon_cover) {
                        switch ($addon_cover['CoverName']) {
                            case 'ElectricalAccessoriesIdv':
                                $electrical_accessories = $addon_cover['CoverPremium'];
                                break;
    
                            case 'NonelectricalAccessoriesIdv':
                                $non_electrical_accessories = $addon_cover['CoverPremium'];
                                break;
    
                            case 'LpgCngKitIdvOD':
                                $lpg_cng_kit_od = $addon_cover['CoverPremium'];
                                break;
    
                            case 'LpgCngKitIdvTP':
                                $lpg_cng_kit_tp = $addon_cover['CoverPremium'];
                                break;
    
                            case 'PACoverOwnerDriver':
                                $cpa = $addon_cover['CoverPremium'];
                                break;
    
                            case 'PACoverOwnerDriver3Year':
                                if ($requestData->business_type == 'newbusiness' && $requestData->policy_type == 'comprehensive') {
                                    $cpa = $addon_cover['CoverPremium'];
                                }
                                break;
    
                            case 'UnnamedPassenger':
                                $unnamed_passenger = $addon_cover['CoverPremium'];
                                break;
    
                            case 'LLPaidDriver':
                                $ll_paid_driver = $addon_cover['CoverPremium'];
                                break;
    
                            case 'LLEmployee':
                                $legal_liability_to_employee = $addon_cover['CoverPremium'];
                                break;
    
                            case 'PAPaidDriver':
                                $pa_paid_driver = $addon_cover['CoverPremium'];
                                break;
    
                            case 'ZERODEP':
                                $zero_depreciation = $addon_cover['CoverPremium'];
                                break;
    
                            case 'EMERGASSIST':
                                // $road_side_assistance = $interval->y < 15 && $requestData->business_type!="breakin" ? $addon_cover['CoverPremium'] : 0;
    
                                if (($requestData->business_type == "breakin" && $interval->y < 5) || ($requestData->business_type != "breakin" && $interval->y < 15)) {
                                    $road_side_assistance = $addon_cover['CoverPremium'];
                                }
                                break;
    
                            case 'NCBPROT':
                                $ncb_protection = $interval->y < 5 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                            case 'ENGEBOX':
                                $engine_protection = $interval->y < 10 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                            case 'COSTCONS':
                                $consumable = $interval->y < 5 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                            case 'EMERGASSISTWIDER':
                                $key_replacement = $interval->y < 10 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                            case 'TYRESECURE':
                                $tyre_secure = $interval->y < 5 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                            case 'RTI':
                                $return_to_invoice = $interval->y < 3 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                                // case 'LOSSUSEDOWN':
                            case 'LOPB':
                                $loss_of_personal_belongings = $interval->y < 10 ? $addon_cover['CoverPremium'] : 0;
                                break;
    
                            default:
                                break;
                        }
                    }
                }
    
                $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od;
                $final_tp_premium = $basic_tp + $unnamed_passenger + $pa_paid_driver + $ll_paid_driver + $lpg_cng_kit_tp + $legal_liability_to_employee;
                $ncb_discount = $premium_response['Data'][0]['NewNcbDiscountAmount'] ?? 0; //$final_od_premium * ($requestData->applicable_ncb / 100);
                $final_total_discount = $ncb_discount + $tppd_discount;
                $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
                $final_gst_amount = round($final_net_premium * 0.18);
                $final_payable_amount = $final_net_premium + $final_gst_amount;
                $applicable_addons = [];
                $addons_data = [
                    'in_built' => [],
                    'additional' => [],
                    'other_premium' => []
                ];
    
                if ($premium_type != 'third_party') {
                    switch ($productData->product_identifier) {
                        case 'Essential ZD':
                            $addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => $zero_depreciation,
                                    'lopb' => $loss_of_personal_belongings,
                                    'key_replace' => $key_replacement,
                                    'road_side_assistance' => $road_side_assistance,
                                ],
                                'additional' => [],
                                'other_premium' => []
                            ];
                            break;
    
                        case 'Essential_EGP':
                            $addons_data = [
                                'in_built'   => [
                                    'zero_depreciation' => $zero_depreciation,
                                    'lopb' => $loss_of_personal_belongings,
                                    'engine_protector' => $engine_protection,
                                    'key_replace' => $key_replacement,
                                    'road_side_assistance' => $road_side_assistance,
                                ],
                                'additional' => [],
                                'other_premium' => []
                            ];
                            break;
                        default:
                            if ($interval->y < 5) {
                                $loss_of_personal_belongings = 0;
                                $key_replacement = 0;
                                $engine_protection = 0;
                            }
                            if ($interval->y <= 5) {
                            $addons_data = [
                                'in_built' => [],
                                'additional' => [
                                    'zero_depreciation' => $zero_depreciation,
                                    'road_side_assistance' => $road_side_assistance,
                                    'ncb_protection' => $ncb_protection,
                                    'engine_protector' => $engine_protection,
                                    'key_replace' => $key_replacement,
                                    'consumables' => $consumable,
                                    'tyre_secure' => $tyre_secure,
                                    'return_to_invoice' => $return_to_invoice,
                                    'lopb' => $loss_of_personal_belongings,
                                ],
                                'other_premium' => []
                            ];
                        }
                            break;
                    }
    
                    $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                    $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                    $addons_data['other_premium'] = 0;
    
                    $applicable_addons = ['zeroDepreciation', 'roadSideAssistance', 'consumables', 'keyReplace', 'engineProtector', 'ncbProtection', 'tyreSecure', 'returnToInvoice', 'lopb'];
                    if ($interval->y >= 10) {
                        array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    }
                    //     if ($addons_data['additional']['return_to_invoice'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['key_replace'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['engine_protector'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['ncb_protection'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['road_side_assistance'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['lopb'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['consumables'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                    //     }
    
                    //     if ($addons_data['additional']['tyre_secure'] == 0)
                    //     {
                    //         array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                    //     }
                }
                // foreach($addons_data['additional'] as $k=>$v){
                //     if(empty($v)){
                //         unset($addons_data['additional'][$k]);
                //     }
                // }
                $business_types = [
                    'rollover' => 'Rollover',
                    'newbusiness' => 'New Business',
                    'breakin' => 'Breakin'
                ];
                $return_data = [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => true,
                    'msg' => 'Found',
                    'Data' => [
                        'idv' => !$tp_only ? round($premium_request['RequiredIDV']) : 0,
                        'min_idv' => !$tp_only ? ceil($min_idv) : 0,
                        'max_idv' => !$tp_only ? floor($max_idv) : 0,
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
                        'policy_type' => $tp_only ? 'Third Party' : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? 'Own Damage' : 'Comprehensive'),
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => !$tp_only ? $vehicle_idv : 0,
                        'vehicle_registration_no' => $requestData->rto_code,
                        'version_id' => $mmv_data->ic_version_code,
                        'selected_addon' => [],
                        'showroom_price' => !$tp_only ? $vehicle_idv : 0,
                        'fuel_type' => $requestData->fuel_type,
                        'vehicle_idv' => !$tp_only ? $vehicle_idv : 0,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
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
                            'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => "",
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online
                        ],
                        'motor_manf_date' => $requestData->vehicle_register_date,
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'ic_vehicle_discount' => 0, //$other_discount,
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => '0',
                            'rto_cluster_id' => '0',
                            'car_age' => $car_age,
                            'ic_vehicle_discount' => 0, //$other_discount
                        ],
                        'basic_premium' => round($basic_od),
                        'deduction_of_ncb' => round($ncb_discount),
                        'tppd_premium_amount' => round($basic_tp),
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'total_accessories_amount(net_od_premium)' => '',
                        'total_own_damage' => '',
                        'total_liability_premium' => '',
                        'net_premium' => round($final_net_premium),
                        'service_tax_amount' => round($final_gst_amount),
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => '',
                        'quotation_no' => '',
                        'premium_amount' => round($final_payable_amount),
                        'final_od_premium' => round($final_od_premium),
                        'final_tp_premium' => round($final_tp_premium),
                        'final_total_discount' => round($final_total_discount),
                        'final_net_premium' => round($final_net_premium),
                        'final_gst_amount' => round($final_gst_amount),
                        'final_payable_amount' =>  round($final_payable_amount),
                        'service_data_responseerr_msg' => '',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : (($requestData->business_type == "breakin" || ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party')) ? 'Breakin' : 'Roll over'),
                        'policyStartDate' => $policy_start_date,
                        'policyEndDate' => $policy_end_date,
                        'ic_of' => $productData->company_id,
                        'vehicle_in_90_days' => 0, //$vehicle_in_90_days,
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
                        "max_addons_selection" => NULL,
                        'add_ons_data' => $addons_data,
                        'applicable_addons' => $applicable_addons,
                        'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                        'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    ]
                ];
    
                if (round($electrical_accessories) > 0) {
                    $return_data['Data']['motor_electric_accessories_value'] = round($electrical_accessories);
                }
    
                if (round($non_electrical_accessories) > 0) {
                    $return_data['Data']['motor_non_electric_accessories_value'] = round($non_electrical_accessories);
                }
    
                if (round($lpg_cng_kit_od) > 0) {
                    $return_data['Data']['motor_lpg_cng_kit_value'] = round($lpg_cng_kit_od);
                }
    
                if (round($unnamed_passenger) > 0) {
                    $return_data['Data']['cover_unnamed_passenger_value'] = round($unnamed_passenger);
                }
    
                if (round($ll_paid_driver) > 0) {
                    $return_data['Data']['default_paid_driver'] = round($ll_paid_driver);
                }
    
                if (!empty($legal_liability_to_employee)) {
                    $return_data['Data']['other_covers'] = [
                        'LegalLiabilityToEmployee' => round($legal_liability_to_employee)
                    ];
                    $return_data['Data']['LegalLiabilityToEmployee'] = round($legal_liability_to_employee);
                }
    
                if (round($cpa) > 0) {
                    $return_data['Data']['compulsory_pa_own_driver'] = round($cpa);
                }
    
                if (round($lpg_cng_kit_tp) > 0) {
                    $return_data['Data']['cng_lpg_tp'] = round($lpg_cng_kit_tp);
                }
    
                if (round($pa_paid_driver) > 0) {
                    $return_data['Data']['motor_additional_paid_driver'] = round($pa_paid_driver);
                }
    
                if (round($tppd_discount) > 0) {
                    $return_data['Data']['tppd_discount'] = round($tppd_discount);
                }
    
                return camelCase($return_data);
            } else {
                $message = !empty(createErrorMessage($premium_response['Message'] ?? '')) ?  createErrorMessage($premium_response['Message']) : 'An error occured while calculating premium';
                return camelCase([
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => $message
                ]);
            }
        } else {
            return camelCase([
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Insurer not reachable'
            ]);
        }
    }
    
    function createErrorMessage($message)
    {
        if (is_array($message)) {
            return implode('. ', $message);
        }
    
        return $message;
    }
}