<?php

use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use App\Models\RahejaRtoLocation;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

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
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $car_age = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);
    if (($car_age > 8) && ($productData->zero_dep == 0) && in_array($productData->company_alias, explode(',', config('CAR_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero dep is not allowed for vehicle age greater than 8 years',
            'request' => [
                'message' => 'Zero dep is not allowed for vehicle age greater than 8 years',
                'car_age' => $car_age
            ]
        ];
    }
    $mmv_data = get_mmv_details($productData, $requestData->version_id, 'raheja');
    $mmv_data = (object)array_change_key_case((array)$mmv_data, CASE_LOWER);
    if ($mmv_data->status == false) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Does Not Exists',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv_data
            ]
        ];
    }
    $mmv_data = $mmv_data->data;
    if (empty($mmv_data['ic_version_code'] || $mmv_data['ic_version_code'] == '')) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv_data
            ]
        ];
    } else if ($mmv_data['ic_version_code'] == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv_data
            ]
        ];

    } else if (count($mmv_data) > 0) {
        $reg_no = explode('-', $requestData->rto_code);

        $rto_code = $reg_no[0] . '' . $reg_no[1];

        $rto_data =RahejaRtoLocation::where('rto_code', $rto_code)
            ->select('*')->first();

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $BusinessTypeID = '25';
        $PHNumericFeild1 = '1';;
        if ($premium_type == 'comprehensive' && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $ProductCode = '2311';
            $CoverType = '1471';
            $Tennure = '102';

            $ProductName = 'MOTOR - PRIVATE CAR PACKAGE POLICY(2311)';
            if ($premium_type == "third_party") {
                $ProductCode = '2325';
                $ProductName = 'TWO WHEELER LIABILITY POLICY(2325)';
                $CoverType = '1694';
                $Tennure = '155';
            }
        } elseif ($premium_type == 'comprehensive' && $requestData->business_type == 'newbusiness') {
            $BusinessTypeID = '24';
            $ProductCode = '2367';
            $CoverType = '1473';
            $Tennure = '101';
            $PHNumericFeild1 = '5';
            $ProductName = 'MOTOR PRIVATE CAR BUNDLED POLICY(2367)';
            if ($premium_type == "third_party") {
                $ProductCode = '2326';
                $ProductName = 'TWO WHEELER LONG TERM LIABILITY POLICY(2326)';
                $CoverType = '1695';
                $Tennure = '157';
            }
        } else {
            $ProductCode = '2323';
            $CoverType = '1668';
            $Tennure = '151';
            $ProductName = 'MOTOR - PRIVATE CAR STANDALONE OD(2323)';
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

        if (!($requestData->business_type == 'newbusiness') && !($premium_type == "third_party") && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure') || $requestData->ownership_changed == 'Y') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Break-In Quotes Not Allowed',
                'request' => [
                    'message' => 'Break-In Quotes Not Allowed',
                    'previous_policy_typ' => $requestData->previous_policy_type
                ]
            ];
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
        if (isset($rto_data->rto_code) && $rto_data->rto_code !== null) {
            if ($requestData->business_type == 'rollover') {
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                $pre_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                $TPPolicyStartDate = date('Y-m-d');
                $TPPolicyEndDate = date('Y-m-d', strtotime('+3 year -1 day', strtotime($TPPolicyStartDate)));
                if ($requestData->applicable_ncb == 0) {
                    $no_claim_bonus_val = $requestData->applicable_ncb;
                    $no_claim_bonus = ['ZERO', 'TWENTY', 'TWENTY_FIVE', 'THIRTY_FIVE', 'FORTY_FIVE', 'FIFTY', 'FIFTY_FIVE', 'SIXTY_FIVE'];
                    $previousNoClaimBonus = $no_claim_bonus[$no_claim_bonus_val];
                } else {
                    $motor_claims_made = 'true';
                    $previousNoClaimBonus = '0';
                }
                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            } else {
                $policy_start_date = date('Y-m-d');
                $motor_claims_made = 'false';
                $previousNoClaimBonus = 'ZERO';
                $isVehicleNew = 'true';
                $policy_end_date = date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            }
            if ($premium_type == "third_party") {
                $zero_dep = "false";
                $engine_protector = "false";
                $consumable = "false";
                $return_to_invoice = "false";
                $tyreProtectCover = "false";
                $breakdownAssistanceCover = "false";
                $theftCover = "false";
                $nonElectricalCoverSelection = "false";
                $nonElectricalCoverInsuredAmount = 0;
                $electricalCoveSelection = "false";
                $electricalCoverInsuredAmount = 0;
                $cngCoverSelection = "false";
                $cngCoverInsuredAmount = 0;
            } else {
                $applicable_addons = [
                    'zeroDepreciation','tyreSecure', 'consumables', 'keyReplace', 'lopb', 'engineProtector', 'returnToInvoice','ncbProtection'
                ];
                /*engine protector*/
                if ($car_age > 8) {
                    $engine_protector = "false";
                    $consumable = "false";
                    array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                    array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                    // $zero_dep = "false";
                } else {
                    $engine_protector = "true";
                    $consumable = "true";
                    // $zero_dep = "true";
                }
                if ($car_age > 8) {
                    $zero_dep = "false";
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                } else {
                    $zero_dep = "true";
                }
                /*engine protector*/

                /*return_to_invoice*/
                if ($car_age > 4) {
                    $return_to_invoice = "false";
                    array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                } else {
                    $return_to_invoice = "true";
                }
                /*return_to_invoice*/
                /*non-electric additional cover*/
                $motor_lpg_cng_kit = 0;
                $motor_non_electric_accessories = 0;
                $motor_electric_accessories = 0;
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                            $motor_lpg_cng_kit = $data['sumInsured'];
                            if ($motor_lpg_cng_kit != 0 && !($motor_lpg_cng_kit > 1000 && $motor_lpg_cng_kit < 60000)) {
                                return [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'lpg cng kit amoount should be greater than 1000 and  less than 60000',
                                    'request' => [
                                        'message' => 'lpg cng kit y amoount should be greater than 1000 and less than 60000',
                                        'value' => $motor_lpg_cng_kit
                                    ]
                                ];
                            }
                        }

                        if ($data['name'] == 'Non-Electrical Accessories') {
                            $motor_non_electric_accessories = $data['sumInsured'];
                            if ($motor_non_electric_accessories >= 200000 && $motor_non_electric_accessories != 0) {
                                return [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'non electric accessoriesny amoount should be less than 200000',
                                    'request' => [
                                        'message' => 'non electric accessoriesny amoount should be less than 200000',
                                        'value' => $motor_non_electric_accessories
                                    ]
                                ];
                            }
                        }

                        if ($data['name'] == 'Electrical Accessories') {
                            $motor_electric_accessories = $data['sumInsured'];
                            if ($motor_electric_accessories >= 200000 && $motor_electric_accessories != 0) {
                                return [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => ' electric accessoriesny amoount should be less than 200000',
                                    'request' => [
                                        'message' => ' electric accessoriesny amoount should be less than 200000',
                                        'value' => $motor_electric_accessories
                                    ]
                                ];
                            }
                        }
                    }
                }

                if ($motor_non_electric_accessories != '' && $motor_non_electric_accessories < 200000 && $motor_non_electric_accessories != 0) {
                    $nonElectricalCoverSelection = "true";
                    $nonElectricalCoverInsuredAmount = $motor_non_electric_accessories;
                } else {
                    $nonElectricalCoverSelection = "false";
                    $nonElectricalCoverInsuredAmount = 0;
                }
                /*non-electric additional cover*/
                /*Electric additional cover*/
                if ($motor_electric_accessories != '' && $motor_electric_accessories < 200000 && $motor_electric_accessories != 0) {
                    $electricalCoveSelection = "true";
                    $electricalCoverInsuredAmount = $motor_electric_accessories;
                } else {
                    $electricalCoveSelection = "false";
                    $electricalCoverInsuredAmount = 0;
                }
                /*Electric additional cover*/
                /*LPG-CNG additional cover*/
                if (($motor_lpg_cng_kit != '') && ($motor_lpg_cng_kit > 1000) && ($motor_lpg_cng_kit < 60000)) {
                    $cngCoverSelection = "true";
                    $cngCoverInsuredAmount = $motor_lpg_cng_kit;
                } else {
                    $cngCoverSelection = "false";
                    $cngCoverInsuredAmount = 0;
                }
                /*LPG-CNG additional cover*/
            }
            /*unnamed-passenger addon*/
            $motor_acc_cover_unnamed_passenger = 0;
            $isPACoverPaidDriverAmount = 0;
            $legal_liability = 'N';
            $is_geo_ext = false;
            $srilanka = false;
            $pak = false;
            $bang = false;
            $bhutan = false;
            $nepal = false;
            $maldive = false;
            $rsa_cover  = true;
            foreach ($additional_covers as $key => $value) {
                if (in_array('LL paid driver', $value)) {
                    $legal_liability = 'Y';
                }

                if (in_array('PA cover for additional paid driver', $value)) {
                    $isPACoverPaidDriverSelected = 'true';
                    $isPACoverPaidDriverAmount = $value['sumInsured'];
                }

                if (in_array('Unnamed Passenger PA Cover', $value)) {
                    $motor_acc_cover_unnamed_passenger = $value['sumInsured'];
                }
                if (in_array('Geographical Extension', $value)) {
                    $is_geo_ext = true;
                    $countries = $value['countries'];
                    if(in_array('Sri Lanka',$countries))
                    {
                        $srilanka = true;
                    }
                    if(in_array('Bangladesh',$countries))
                    {
                        $bang = true; 
                    }
                    if(in_array('Bhutan',$countries))
                    {
                        $bhutan = true; 
                    }
                    if(in_array('Nepal',$countries))
                    {
                        $nepal = true; 
                    }
                    if(in_array('Pakistan',$countries))
                    {
                        $pak = true; 
                    }
                    if(in_array('Maldives',$countries))
                    {
                        $maldive = true; 
                    }
                }
            }
            if ($motor_acc_cover_unnamed_passenger != 0) {
                $paUnnamedPersonCoverselection = "true";
                $paUnnamedPersonCoverinsuredAmount = $motor_acc_cover_unnamed_passenger;
            } else {
                $paUnnamedPersonCoverselection = "false";
                $paUnnamedPersonCoverinsuredAmount = 0;
            }
            /*unnamed-passenger addon*/

            /*paid_driver addon*/
            if ($isPACoverPaidDriverAmount != 0) {
                $paid_driver_selection = "true";
                $paid_driver_amount = $isPACoverPaidDriverAmount;
            } else {
                $paid_driver_selection = "false";
                $paid_driver_amount = 0;
            }
            /*paid_driver addon*/
            $isPOSP = false;
            $pospName = null;
            $pospUniqueNumber = null;
            $pospLocation = null;
            $pospPanNumber = null;
            $pospAadhaarNumber = null;
            $pospContactNumber = null;

            if ($premium_type == "comprehensive" || $premium_type == "own_damage") {
                $Prev_policy_code = "1";
                $Prev_policy_name = "COMPREHENSIVE";
            } else {
                $Prev_policy_code = "2";
                $Prev_policy_name = "LIABILITY ONLY";
            }
            if ($premium_type == "third_party") {
                $ncb_discount_rate = 0;
            } else {
                $ncb_discount_rate = $requestData->applicable_ncb;
            }

            /*ncb_protection*/
            if ($requestData->previous_ncb > 0) {
                $ncb_protection = 'true';
            } else {
                $ncb_protection = 'false';
            }
            /*ncb_protection*/
            /*voluntary_deductible*/
            if (isset($requestData->voluntary_excess_value)) {
                $voluntary_deductible = $requestData->voluntary_excess_value;
            } else {
                $voluntary_deductible = '0';
            }

            if ($voluntary_deductible != '0') {
                $voluntary_deductible_flag = 'true';
            } else {
                $voluntary_deductible_flag = 'false';
            }
            $zero_dep = (($productData->zero_dep == '0') ? true : false);
            $tppd_cover = false;
            $is_antitheft = false;
            if (!empty($additional['discounts'])) {
                foreach ($additional['discounts'] as $key => $data) {
                    if ($data['name'] == 'TPPD Cover') {
                        $tppd_cover = true;
                    }
                    if ($data['name'] == 'anti-theft device') {
                        $is_antitheft = true;
                    }
                }
            }
            /*voluntary_deductible*/
            $year = explode('-', $requestData->manufacture_year);
            $yearOfManufacture = trim(end($year));
            /*trace-id generation*/
            $trace_id = array();

            $trace_id_response = getWsData(
                config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_MOTOR_TRACE_ID'),
                [],
                'raheja',
                [
                    'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                    'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR'),
                    'request_method' => 'get',
                    'request_data' => [
                        'section' => 'car',
                        'method' => 'traceid',
                        'proposal_id' => '0',
                    ],
                    'section' => 'car',
                    'company' => $productData->company_name,
                    'productName' => $productData->product_sub_type_name,
                    'enquiryId' => $enquiryId,
                    'method' => 'Trace Id Generation',
                    'transaction_type' => 'quote',
                ]
            );
            //$mmv_data['cc'] = "1298";
            $idv_api = [
                'objVehicleDetails' => [              //IDV API
                    "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant'] . "|" . $mmv_data['cc'] . "CC",
                    "RtoLocation" => $rto_data->rto_code,
                    "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    "ManufacturingYear" => $yearOfManufacture,
                    "ManufacturingMonth" => date('m', strtotime($requestData->vehicle_register_date))
                ],
                'objPolicy' => [
                    "UserName" => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                    "ProductCode" => $ProductCode,
                    "TraceID" => str_replace('"', '', $trace_id_response['response']),// "TAPI240620018939",
                    "SessionID" => "",
                    "TPSourceName" => config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_MOTOR'),
                    "BusinessTypeID" => $BusinessTypeID,
                    "PolicyStartDate" => $policy_start_date
                ],
            ];
            $data = getWsData(config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_MOTOR_IDV'), $idv_api, 'raheja',
                [
                    'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                    'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR'),
                    'request_data' => [
                        'section' => 'car',
                        'method' => 'IDV calculations',
                        'proposal_id' => '0',
                    ],
                    'section' => 'car',
                    'method' => 'IDV calculations',
                    'request_method' => 'post',
                    'requestMethod' => 'post',
                    'company' => $productData->company_name,
                    'productName' => $productData->product_sub_type_name,
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'quote',
                ]
            );
           if(!$data['response']){
               return [
                   'webservice_id' => $data['webservice_id'],
                   'table' => $data['table'],
                   'premium_amount' => 0,
                   'status' => false,
                   'message' => 'Idv Service Issue',

               ];
           }
            $vehicle_reg_num = explode('-', $requestData->rto_code);
            $veh_reg_date = explode('-', $requestData->vehicle_register_date);
            $response = array_change_key_case_recursive(json_decode($data['response'], true));
            if (!isset($response['objvehicledetails'])) {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status' => false,
                    'message' => 'Idv Service Issue',
                ];
            }
            $idv = $response['objvehicledetails']['modifiedidv'];
            $idv_min = ceil($idv - ($idv * 0.15));#Minimum IDV is calculated as -15% of actual IDV
            $idv_max = $idv + floor($idv * 0.15);#Minimum IDV is calculated as +15% of actual IDV
            #$idv = $idv_min;

            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $skip_second_call = true;
                    $idv = $idv;
                    break;
                case 'min_idv':
                    $idv = $idv_min;
                    break;
                case 'max_idv':
                    $idv = $idv_max;
                    break;
                default:
                    $idv = $idv_min;
                    break;
            }
            /*objPreviousInsurance new business code*/
            if (($requestData->business_type == 'rollover')) {
                $objPreviousInsurance = [
                    "PrevPolicyType" => $Prev_policy_code,
                    "PrevPolicyStartDate" => $pre_policy_start_date,
                    "PrevPolicyEndDate" => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
                    "ProductCode" => $ProductCode,
                    "PrevInsuranceCompanyID" => "18",
                    "PrevNCB" => ($requestData->previous_ncb == "0") ? "0" : $requestData->previous_ncb,
                    "IsClaimedLastYear" => ($requestData->applicable_ncb == "0") ? "1" : "2",
                    "NatureOfLoss" => ($requestData->applicable_ncb == '0') ? "1" : "",
                    "prevPolicyCoverType" => $Prev_policy_name,
                    "CurrentNCBHidden" => ($requestData->applicable_ncb == '20') ? "20" : $ncb_discount_rate
                ];
            } else {
                $objPreviousInsurance = '';
            }
            /*objPreviousInsurance new business code*/
            if ($premium_type == 'own_damage') { //od addons
                $objCovers = [
                    [
                        "CoverID" => "9",
                        "CoverName" => "Basic - OD",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "objCoverDetails" => null,
                        "IsChecked" => "true"
                    ],
                    [
                        "CoverID" => "70",
                        "CoverName" => "Non Electrical Accessories",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "IsChecked" => $nonElectricalCoverSelection,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $nonElectricalCoverInsuredAmount,
                            "PHVarcharFeild1" => "Test",
                            "PHNumericFeild2" => "",
                            "PHVarcharFeild2" => ""
                        ]
                    ],
                    [
                        "CoverID" => "33",
                        "CoverName" => "Electrical or electronic accessories",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "IsChecked" => $electricalCoveSelection,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $electricalCoverInsuredAmount,
                            "PHVarcharFeild1" => "Test",
                            "PHNumericFeild2" => "",
                            "PHVarcharFeild2" => ""
                        ]
                    ],
                    [
                        "CoverID" => "20",
                        "CoverName" => "CNG Kit - OD",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "IsChecked" => $cngCoverSelection,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $cngCoverInsuredAmount
                        ]
                    ],
                    [
                        "CoverID" => "37",
                        "CoverName" => "Engine Protect",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => $engine_protector,
                        "objCoverDetails" => [
                            "PHIntField1" => 0,
                            "PHVarcharFeild1" => "",
                            "PHVarcharFeild2" => "Geared",
                            "PHIntField2" => 0,
                            "PHNumericFeild1" => 0
                        ]
                    ],
                    [
                        "CoverID" => "24",
                        "CoverName" => "Consumable Expenses",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => $consumable
                    ],
                    [
                        "CoverID" => "80",
                        "CoverName" => "Return To Invoice",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => $return_to_invoice
                    ],
                    [
                        "CoverID" => "97",
                        "CoverName" => "Zero Depreciation",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => $zero_dep,
                        "objCoverDetails" => [
                            "PHIntField1" => 0,
                            "PHVarcharFeild1" => ($requestData->business_type == 'newbusiness') ? "2" : ">2",
                            "PHVarcharFeild2" => ($requestData->business_type == 'newbusiness') ? "" : "yes",
                            "PHNumericFeild1" => 0,
                            "PHIntField2" => 0
                        ]
                    ],
                    [
                        "CoverID" => "99",
                        "CoverName" => "NCB Retention",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => ($requestData->is_claim == 'Y') ? 'false' : $ncb_protection,
                        "objCoverDetails" => null
                    ],
                    [
                        "CoverID" => "100",
                        "CoverName" => "Key Protect",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => "true"
                    ],
                    [
                        "CoverID" => "104",
                        "CoverName" => "Tyre And Rim Protector",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => "true",
                    ],
                    [
                        "CoverID" => "101",
                        "CoverName" => "Loss of Personal Belongings",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => "true",
                        "objCoverDetails" => [
                            "PHIntField1" => 0,
                            "PHVarcharFeild1" => "25000",
                            "PHVarcharFeild2" => "",
                            "PHNumericFeild1" => 0,
                            "PHIntField2" => 0
                        ]
                    ],
                    [
                        "CoverID" => "91",
                        "CoverName" => "Voluntary Deductibles",
                        "CoverType" => "Discount",
                        "PackageName" => "Discount",
                        "IsChecked" => (isset($requestData->voluntary_excess_value) == '') ? 'false' : $voluntary_deductible_flag,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $voluntary_deductible,
                        ]
                    ],
                    [
                        "CoverID" => "110",
                        "CoverName" => "Installation of Anti-Theft Device",
                        "CoverType" => "Discount",
                        "PackageName" => "Discount",
                        "IsChecked" => $is_antitheft,
                        "objCoverDetails" => null
                    ],
                    [
                        "CoverID"  =>  "41",
                        "CoverName"  =>  "Geographical Extension - OD",
                        "CoverType"  =>  "ODPackage",
                        "IsChecked"  => $is_geo_ext,
                        "objCoverDetails"  =>  null,
                        "PackageName"  =>  "ODPackage"
                    ],
                    /* [
                        "CoverID" => "42",
                        "CoverName" => "Geographical Extension - TP",
                        "CoverType" => "LiabilityPackage",
                        "IsChecked" => $is_geo_ext,
                        "objCoverDetails" => null,
                        "PackageName" => "LiabilityPackage"
                    ], */
                    [
                        "CoverID" => "115",
                        "CoverName" => "Road Side Assistance",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => $rsa_cover,
                        "objCoverDetails" => null
                    ],
                ];
            } else {
                $objCovers = [      //new and rollover addons
                    [
                        "CoverID" => "9",
                        "CoverName" => "Basic - OD",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "objCoverDetails" => null,
                        "IsChecked" => "true"
                    ],
                    [
                        "CoverID" => "10",
                        "CoverName" => "Basic - TP",
                        "CoverType" => "LiabilityPackage",
                        "PackageName" => "LiabilityPackage",
                        "objCoverDetails" => null,
                        "IsChecked" => "true"
                    ],
                    [
                        "CoverID" => "94",
                        "CoverName" => "PA - Unnamed Person",
                        "CoverType" => "LiabilityPackage",
                        "PackageName" => "LiabilityPackage",
                        "IsChecked" => $paUnnamedPersonCoverselection,
                        "objCoverDetails" => [
                            "PHNumericFeild2" => ($paUnnamedPersonCoverinsuredAmount == '25000') ? "20000" : $paUnnamedPersonCoverinsuredAmount,
                            "PHNumericFeild1" => isset($mmv_data['Seating_Capacity']) ? $mmv_data['Seating_Capacity'] : "5"
                        ]
                    ],
                     [
                         "CoverID" => "76",
                         "CoverName" => "Paid Driver",
                         "CoverType" => "LiabilityPackage",
                         "PackageName" => "LiabilityPackage",
                         "IsChecked" => $paid_driver_selection,
                         "objCoverDetails" => [
                           "PHVarcharFeild1" => $paid_driver_amount,
                           "PHNumericFeild1" => "1",//isset($mmv_data['Seating_Capacity']) ? $mmv_data['Seating_Capacity'] : "5" as per observation this code commented git id 11965
                         ]
                     ],
                    [
                        "CoverID" => "70",
                        "CoverName" => "Non Electrical Accessories",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "IsChecked" => $nonElectricalCoverSelection,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $nonElectricalCoverInsuredAmount,
                            "PHVarcharFeild1" => "Test",
                            "PHNumericFeild2" => "",
                            "PHVarcharFeild2" => ""
                        ]
                    ],
                    [
                        "CoverID" => "33",
                        "CoverName" => "Electrical or electronic accessories",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "IsChecked" => $electricalCoveSelection,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $electricalCoverInsuredAmount,
                            "PHVarcharFeild1" => "Test",
                            "PHNumericFeild2" => "",
                            "PHVarcharFeild2" => ""
                        ]
                    ],
                    [
                        "CoverID" => "21",
                        "CoverName" => "CNG Kit - TP",
                        "CoverType" => "LiabilityPackage",
                        "PackageName" => "LiabilityPackage",
                        "IsChecked" => $cngCoverSelection
                    ],
                    [
                        "CoverID" => "20",
                        "CoverName" => "CNG Kit - OD",
                        "CoverType" => "ODPackage",
                        "PackageName" => "ODPackage",
                        "IsChecked" => $cngCoverSelection,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $cngCoverInsuredAmount
                        ]
                    ],
                    [
                        "CoverID" => "49",
                        "CoverName" => "Legal Liability to Paid Driver",
                        "CoverType" => "LiabilityPackage",
                        "PackageName" => "LiabilityPackage",
                        "objCoverDetails" => null,
                        "IsChecked" => ($legal_liability == 'N') ? "false" : "true"
                    ],
                    [
                        "CoverID" => "73",
                        "CoverName" => "PA - Owner",
                        "CoverType" => "LiabilityPackage",
                        "PackageName" => "LiabilityPackage",
                        "IsChecked" => ($requestData->vehicle_owner_type == 'C') ? "false" : "true",
                        "objCoverDetails" => [
                            "PHintFeild1" => ($requestData->vehicle_owner_type == 'C') ? "" : "20",
                            "PHVarcharFeild1" => ($requestData->vehicle_owner_type == 'C') ? "" : "DFDGH",
                            "PHVarcharFeild2" => ($requestData->vehicle_owner_type == 'C') ? "" : "1527",
                            "PHNumericFeild2" => ($requestData->vehicle_owner_type == 'C') ? "" : 1500000,
                            //"PHNumericFeild1" => ($requestData->business_type == 'newbusiness') ? "3" : "0",
                            "PHNumericFeild1" => ($requestData->vehicle_owner_type == 'C') ? "0" : "1", //By Default CPA will be 1 year
                            "PHVarcharFeild4" => "",
                            "PHVarcharFeild5" => "",
                        ]
                    ],
                    [
                        "CoverID" => "37",
                        "CoverName" => "Engine Protect",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => $engine_protector,
                        "objCoverDetails" => [
                            "PHIntField1" => 0,
                            "PHVarcharFeild1" => "",
                            "PHVarcharFeild2" => "Geared",
                            "PHIntField2" => 0,
                            "PHNumericFeild1" => 0
                        ]
                    ],
                    [
                        "CoverID" => "24",
                        "CoverName" => "Consumable Expenses",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => $consumable
                    ],
                    [
                        "CoverID" => "80",
                        "CoverName" => "Return To Invoice",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => $return_to_invoice
                    ],
                    [
                        "CoverID" => "97",
                        "CoverName" => "Zero Depreciation",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => $zero_dep,
                        "objCoverDetails" => [
                            "PHIntField1" => 0,
                            "PHVarcharFeild1" => ($requestData->business_type == 'newbusiness') ? "2" : ">2",
                            "PHVarcharFeild2" => ($requestData->business_type == 'newbusiness') ? "" : "yes",
                            "PHNumericFeild1" => 0,
                            "PHIntField2" => 0
                        ]
                    ],
                    [
                        "CoverID" => "99",
                        "CoverName" => "NCB Retention",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => ($requestData->is_claim == 'Y') ? 'false' : $ncb_protection,
                        "objCoverDetails" => null
                    ],
                    [
                        "CoverID" => "100",
                        "CoverName" => "Key Protect",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => "true"
                    ],
                    [
                        "CoverID" => "104",
                        "CoverName" => "Tyre And Rim Protector",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "objCoverDetails" => null,
                        "IsChecked" => "true",
                    ],
                    [
                        "CoverID" => "101",
                        "CoverName" => "Loss of Personal Belongings",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => "true",
                        "objCoverDetails" => [
                            "PHIntField1" => 0,
                            "PHVarcharFeild1" => "25000",
                            "PHVarcharFeild2" => "",
                            "PHNumericFeild1" => 0,
                            "PHIntField2" => 0
                        ]
                    ],
                    [
                        "CoverID" => "91",
                        "CoverName" => "Voluntary Deductibles",
                        "CoverType" => "Discount",
                        "PackageName" => "Discount",
                        "IsChecked" => (isset($requestData->voluntary_excess_value) == '') ? 'false' : $voluntary_deductible_flag,
                        "objCoverDetails" => [
                            "PHNumericFeild1" => $voluntary_deductible,
                        ]
                    ],
                    [
                        "CoverID" => "87",
                        "CoverName" => "TPPD",
                        "CoverType" => "LiabilityPackage",
                        "PackageName" => "LiabilityPackage",
                        "IsChecked" => $tppd_cover,
                        "objCoverDetails" => null,

                    ],
                    [
                        "CoverID"  =>  "41",
                        "CoverName"  =>  "Geographical Extension - OD",
                        "CoverType"  =>  "ODPackage",
                        "IsChecked"  => $is_geo_ext,
                        "objCoverDetails"  =>  null,
                        "PackageName"  =>  "ODPackage"
                    ],
                    [
                        "CoverID" => "42",
                        "CoverName" => "Geographical Extension - TP",
                        "CoverType" => "LiabilityPackage",
                        "IsChecked" => $is_geo_ext,
                        "objCoverDetails" => null,
                        "PackageName" => "LiabilityPackage"
                    ],
                    [
                        "CoverID" => "115",
                        "CoverName" => "Road Side Assistance",
                        "CoverType" => "AddOnCovers",
                        "PackageName" => "AddOnCovers",
                        "IsChecked" => $rsa_cover,
                        "objCoverDetails" => null
                    ],
                    [
                        "CoverID" => "110",
                        "CoverName" => "Installation of Anti-Theft Device",
                        "CoverType" => "Discount",
                        "PackageName" => "Discount",
                        "IsChecked" => $is_antitheft,
                        "objCoverDetails" => null
                    ],
                ];
            }

            $ListGeoExtCountryList = [
                        [
                            "ChkId" => 33,
                            "ChkName" => "PAKISTAN",
                            "SelectedValue" => $pak
                        ],
                        [
                            "ChkId" => 34,
                            "ChkName" => "BHUTAN",
                            "SelectedValue" => $bhutan
                        ],
                        [
                            "ChkId" => 35,
                            "ChkName" => "NEPAL",
                            "SelectedValue" => $nepal
                        ],
                        [
                            "ChkId" => 36,
                            "ChkName" => "MALDIVES",
                            "SelectedValue" => $maldive
                        ],
                        [
                            "ChkId" => 37,
                            "ChkName" => "SRILANKA",
                            "SelectedValue" => $srilanka
                        ],
                        [
                            "ChkId" => 38,
                            "ChkName" => "BANGLADESH",
                            "SelectedValue" => $bang
                        ]
            ];
            /*Quick quote service*/
            $requestData->user_email = "abc@gmail.com";
            $requestData->user_mobile = "8898621511";
            $premium_api = [
                "Loading" => "",
                "Discount" => "",
                "objClientDetails" => [
                    "MobileNumber" => $requestData->user_mobile,
                    "ClientType" => ($requestData->vehicle_owner_type == 'I') ? "0" : "1",
                    "EmailId" => $requestData->user_email
                ],
                "objVehicleDetails" => [
                    "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant'] . "|" . $mmv_data['cc'] . "CC",
                    "RtoLocation" => trim($rto_data->rto_loc_name . '|' . $rto_data->rto_code),
                    "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    "Registration_Number1" => $vehicle_reg_num[0],
                    "Registration_Number2" => $vehicle_reg_num[1],
                    "Registration_Number3" => "NM",
                    "Registration_Number4" => "1221",
                    "ManufacturingYear" => $veh_reg_date[2],
                    "ManufacturingMonth" => $veh_reg_date[1],
                    "FuelType" => $mmv_data['Fuel_Type'],
                    "IsForeignEmbassy" => "2",
                    "ModifiedIDV" => $idv
                ],
                "objPolicy" => [
                    "TraceID" => str_replace('"', '', $trace_id_response['response']),
                    "UserName" => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                    "TPSourceName" => config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_MOTOR'),
                    "SessionID" => "",
                    "ProductCode" => $ProductCode,
                    "ProductName" => $ProductName,
                    "PolicyStartDate" => $policy_start_date,
                    "PolicyEndDate" => $policy_end_date,
                    "BusinessTypeID" => $BusinessTypeID,
                    "CoverType" => $CoverType,
                    "Tennure" => $Tennure,
                    "TPPolicyStartDate" => ($premium_type == 'own_damage') ? $TPPolicyStartDate : "",
                    "TPPolicyEndDate" => ($premium_type == 'own_damage') ? $TPPolicyEndDate : "",
                ],
                "objPreviousInsurance" => $objPreviousInsurance,
                "objCovers" => $objCovers,
            ];
            if ($requestData->business_type == 'newbusiness') {
                unset($premium_api['objPreviousInsurance']);
            }
            if($is_geo_ext)
            {
                $premium_api['ListGeoExtCountryList'] = $ListGeoExtCountryList;  
            }
//print_r(json_encode($premium_api));
            $data = getWsData(
                config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_MOTOR_PREMIUM'), $premium_api, 'raheja',
                [
                    'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                    'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR'),
                    'request_data' => [
                        'quote' => $enquiryId,
                        'proposal_id' => '0',
                        'method' => 'Quote Premium Calculation',
                        'section' => 'car',
                    ],
                    'section' => 'car',
                    'request_method' => 'post',
                    'company' => $productData->company_name,
                    'productName' => $productData->product_sub_type_name,
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'method' => 'Quote Premium Calculation',
                    'transaction_type' => 'quote',
                ]
            );
//                print_r(json_decode($data));die;
            if ($data['response']) {
                $response = array_change_key_case_recursive(json_decode($data['response'], true));
                if ($response['objfault']['errormessage'] == '') {
                    /*idv range calculation*/
//                        $idv_min = $idv - ceil($idv * 0.1);
//                        $idv_max = $idv + floor($idv * 0.15);
                    if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                        if ($requestData->edit_idv >= $idv_max) {
                            $idv = floor($idv_max);
                        } elseif ($requestData->edit_idv <= $idv_min) {
                            $idv = floor($idv_min);
                        } else {
                            $idv = floor($requestData->edit_idv);
                        }
                        $premium_api['objVehicleDetails']['ModifiedIDV'] = $idv;
                        $trace_id_response = getWsData(
                            config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_MOTOR_TRACE_ID'),
                            [],
                            'raheja',
                            [
                                'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                                'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR'),
                                'request_method' => 'get',
                                'request_data' => [
                                    'section' => 'car',
                                    'method' => 'traceid',
                                    'proposal_id' => '0',
                                ],
                                'section' => 'car',
                                'company' => $productData->company_name,
                                'productName' => $productData->product_sub_type_name,
                                'enquiryId' => $enquiryId,
                                'method' => 'Trace Id Generation',
                                'transaction_type' => 'quote',
                            ]
                        );
                        $premium_api['objPolicy']['TraceID'] = str_replace('"', '', $trace_id_response['response']);
                        $data = getWsData(
                            config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_MOTOR_PREMIUM'), $premium_api, 'raheja',
                            [
                                'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR'),
                                'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR'),
                                'request_data' => [
                                    'quote' => $enquiryId,
                                    'proposal_id' => '0',
                                    'method' => 'Quote Premium Calculation',
                                    'section' => 'car',
                                ],
                                'section' => 'car',
                                'request_method' => 'post',
                                'company' => $productData->company_name,
                                'productName' => $productData->product_sub_type_name,
                                'enquiryId' => $enquiryId,
                                'requestMethod' => 'post',
                                'method' => 'Quote Premium Calculation',
                                'transaction_type' => 'quote',
                            ]
                        );
                        if ($data['response']) {
                            $response = array_change_key_case_recursive(json_decode($data['response'], true));
                        }
                    }

                    /*idv range calculation*/
                    $tppd = 0;
                    $rsa = 0;
                    $tyre_protect = 0;
                    $zero_dep = 0;
                    $ncb_protection = 0;
                    $consumable = 0;
                    $eng_protect = 0;
                    $rti = 0;
                    $od = 0;
                    $electrical_accessories = 0;
                    $non_electrical_accessories = 0;
                    $lpg_cng = 0;
                    $lpg_cng_tp = 0;
                    $pa_owner = 0;
                    $llpaiddriver = 0;
                    $pa_unnamed = 0;
                    $paid_driver = 0;
                    $key_replacement = 0;
                    $loss_of_personal_belongings = 0;
                    $voluntary_deductible = 0;
                    $ic_vehicle_discount = 0;
                    $tppd_discount = $antitheft_discount = 0;
                    $GeogExtension_tp = $GeogExtension_od = 0;
                    foreach ($response['lstcoverresponce'] as $key => $value) {
                        if ($value['covername'] == 'Basic - OD') {
                            $od = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Basic - TP') {

                            $tppd = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Legal Liability to Paid Driver') {

                            $llpaiddriver = $value['coverpremium'];

                        } elseif ($value['covername'] == 'PA - Owner') {

                            $pa_owner = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Zero Depreciation') {

                            $zero_dep = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Consumable Expenses') {

                            $consumable = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Return To Invoice') {

                            $rti = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Engine Protect') {

                            $eng_protect = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Key Protect') {

                            $key_replacement = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Tyre And Rim Protector') {

                            $tyre_protect = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Loss of Personal Belongings') {

                            $loss_of_personal_belongings = $value['coverpremium'];

                        } elseif ($value['covername'] == 'PA - Unnamed Person') {

                            $pa_unnamed = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Paid Driver') {

                            $paid_driver = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Non Electrical Accessories') {

                            $non_electrical_accessories = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Electrical or electronic accessories') {

                            $electrical_accessories = $value['coverpremium'];

                        } elseif ($value['covername'] == 'CNG Kit - OD') {

                            $lpg_cng = $value['coverpremium'];

                        } elseif ($value['covername'] == 'CNG Kit - TP') {

                            $lpg_cng_tp = $value['coverpremium'];

                        } elseif ($value['covername'] == 'NCB Retention') {

                            $ncb_protection = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Voluntary Deductibles') {

                            $voluntary_deductible = $value['coverpremium'];

                        } elseif ($value['covername'] == 'TPPD') {

                            $tppd_discount = $value['coverpremium'];
                        }

                        else if ($value['covername'] == 'Geographical Extension - OD') {
                            $GeogExtension_od = $value['coverpremium'];
                        }

                        else if ($value['covername'] == 'Geographical Extension - TP') {
                            $GeogExtension_tp = $value['coverpremium'];
                        }

                        else if ($value['covername'] == 'Road Side Assistance') {
                            $rsa = $value['coverpremium'];
                        }
                        else if ($value['covername'] == 'Installation of Anti-Theft Device') {
                            $antitheft_discount = $value['coverpremium'];
                        }
                        else if ($value['covername'] == 'LPG kit - OD') {
                            $lpg_cng = $value['coverpremium'];
                        }
                        else if ($value['covername'] == 'LPG kit - TP') {
                            $lpg_cng_tp = $value['coverpremium'];
                        }
                    }
                    $final_od_premium = $od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od;
                    $final_tp_premium = $tppd + $llpaiddriver + $pa_unnamed + $paid_driver + $lpg_cng_tp + $GeogExtension_tp;
                    $totalTax = $response['totaltax'];
                    $total_premium_amount = round($response['finalpremium']);
                    //$base_premium_amount = $final_od_premium + $final_tp_premium - $final_total_discount;
                    $base_premium_amount = $total_premium_amount / (1 + (18 / 100));
                    if ($requestData->business_type == 'newbusiness') {
                        $add_ons_data = [
                            'in_built' => [],
                            'additional' => [
                                'zeroDepreciation' => round($zero_dep),
                                //'cpa_cover'                   => $pa_owner,
                                'road_side_assistance' => round($rsa),
                                'engineProtector' => round($eng_protect),
                                'ncbProtection' => round($ncb_protection),
                                'keyReplace' => round($key_replacement),
                                'consumables' => round($consumable),
                                'tyreSecure' => round($tyre_protect),
                                'returnToInvoice' => round($rti),
                                'lopb' => round($loss_of_personal_belongings),
                            ],
                            'other' => [],
                        ];
                    } else {
                        $add_ons_data = [
                            'in_built' => [],
                            'additional' => [
                                'zeroDepreciation' => round($zero_dep),
                                'cpa_cover' => round($pa_owner),
                                'road_side_assistance' => round($rsa),
                                'engineProtector' => round($eng_protect),
                                'ncbProtection' => round($ncb_protection),
                                'keyReplace' => round($key_replacement),
                                'consumables' => round($consumable),
                                'tyreSecure' => round($tyre_protect),
                                'returnToInvoice' => round($rti),
                                'lopb' => round($loss_of_personal_belongings),
                            ],
                            'other' => [],
                        ];
                    }
                    if (isset($response['ncbpremium'])) {
                        $ncb_discount = str_replace("INR ", "", $response['ncbpremium']);
                    } else {
                        $ncb_discount = 0;
                    }
                    foreach ($add_ons_data as $add_on_key => $add_on_value) {

                        if (count($add_on_value) > 0) {
                            foreach ($add_on_value as $add_on_value_key => $add_on_value_value) {
                                if (is_numeric($add_on_value_value)) {
                                    $value = (string)$add_on_value_value;
                                    $base_premium_amount -= $value;
                                } else {
                                    $value = $add_on_value_value;
                                }
                                $add_ons[$add_on_key][$add_on_value_key] = $value;
                            }
                        } else {
                            $add_ons[$add_on_key] = $add_on_value;
                        }
                    }

                    array_walk_recursive($add_ons, function (&$item, $key) {
                        if ($item == '' || $item == '0') {
                            $item = 'NA';
                        }
                    });

                    $base_premium_amount = round($base_premium_amount) * (1 + (18 / 100));
                    $final_total_discount = round($ncb_discount) + round($voluntary_deductible) + $tppd_discount + $antitheft_discount;
                    $data_response = [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv' => $idv,
                            'min_idv' => round($idv_min),
                            'max_idv' => round($idv_max),
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
                            'voluntary_excess' => $voluntary_deductible,
                            'version_id' => $requestData->version_id,
                            'selected_addon' => [],
                            'showroom_price' => $idv,
                            'fuel_type' => $mmv_data['Fuel_Type'],
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_name' => $productData->product_name,
                            'mmv_detail' => [
                                'manf_name' => $mmv_data['make_desc'],
                                'model_name' => $mmv_data['model_desc'],
                                'version_name' => $mmv_data['variant'],
                                'fuel_type' => $mmv_data['Fuel_Type'],
                                'seating_capacity' => $mmv_data['Seating_Capacity'],
                                'carrying_capacity' => $mmv_data['Seating_Capacity'],
                                'cubic_capacity' => $mmv_data['cc'],
                                'gross_vehicle_weight' => '',
                                'vehicle_type' => '4W',
                            ],
                            'vehicle_register_date' => $requestData->vehicle_register_date,
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
                                'corp_name' => "Ola Cab",
                                'company_name' => $productData->company_name,
                                'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                                'product_sub_type_name' => $productData->product_sub_type_name,
                                'flat_discount' => $productData->default_discount,
                                'predefine_series' => "",
                                'is_premium_online' => $productData->is_premium_online,
                                'is_proposal_online' => $productData->is_proposal_online,
                                'is_payment_online' => $productData->is_payment_online
                            ],
                            'motor_manf_date' => $requestData->vehicle_register_date,
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
                                'car_age' => $car_age,
                                'ic_vehicle_discount' => $ic_vehicle_discount,
                            ],
                            'basic_premium' => $od,
                            'deduction_of_ncb' => $ncb_discount,
                            //'tppd_premium_amount' => $tppd + $tppd_discount,
                            'tppd_discount' => $tppd_discount,
                            'motor_electric_accessories_value' => $electrical_accessories,
                            'motor_non_electric_accessories_value' => $non_electrical_accessories,
                            'motor_lpg_cng_kit_value' => $lpg_cng,
                            'cover_unnamed_passenger_value' => $pa_unnamed,
                            'seating_capacity' => $mmv_data['Seating_Capacity'],
                            'default_paid_driver' => $llpaiddriver,
                            'motor_additional_paid_driver' => $paid_driver,
                            'compulsory_pa_own_driver' => $pa_owner,
                            'tppd_premium_amount' => round($tppd),
                            'total_accessories_amount(net_od_premium)' => 0,
                            'GeogExtension_ODPremium' => $GeogExtension_od,
                            'GeogExtension_TPPremium' => $GeogExtension_tp,
                            'total_own_damage' => ($premium_type == 'third_party') ? 0 : $final_od_premium,
                            'cng_lpg_tp' => $lpg_cng_tp,
                            'total_liability_premium' => round($tppd),
                            'net_premium' => round($base_premium_amount),
                            'service_tax_amount' => round($totalTax),
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                            'quotation_no' => '',
                            'premium_amount' => $total_premium_amount,
                            'antitheft_discount' => $antitheft_discount,
                            'final_od_premium' => ($premium_type == 'third_party') ? 0 : $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' => round($final_total_discount),
                            'final_net_premium' => $total_premium_amount,
                            'final_gst_amount' => $totalTax,
                            'final_payable_amount' => $total_premium_amount,
                            'service_data_responseerr_msg' => 'success',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'service_err_code' => NULL,
                            'service_err_msg' => NULL,
                            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                            'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                            'ic_of' => $productData->company_id,
                            'vehicle_in_90_days' => NULL,
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
                            "applicable_addons" => $applicable_addons,
                            'add_ons_data' => $add_ons_data

                        ],
                    ];
                    return camelCase($data_response);
                } else {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => $response['objfault']['errormessage']
                    ];
                }

            }

        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request' => [
                    'message' => 'RTO not available',
                    'rto_data' => $rto_data
                ]
            ];
        }


    }


}

