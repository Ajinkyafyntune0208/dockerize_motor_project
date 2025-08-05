<?php

use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use App\Models\RahejaRtoLocation;
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
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $bike_age = floor($age / 12);
        $requestData->previous_policy_expiry_date=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $applicable_addons=[];
    $bike_age = car_age($requestData->vehicle_register_date,$requestData->previous_policy_expiry_date);
    if (($bike_age > 2) && ($productData->zero_dep == '0') && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC'))) )
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Zero dep is not allowed for vehicle age greater than 2 years',
            'request'=> [
                'bike_age'=> $bike_age,
                'productData'=>$productData->zero_dep
            ]
        ];
    }
    else
    {
        $mmv_data = get_mmv_details($productData,$requestData->version_id,'raheja');
        $mmv_data = (object) array_change_key_case((array) $mmv_data,CASE_LOWER);
        if($mmv_data->status == false) {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Does Not Exists',
                'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
            ];
        }
        $mmv_data  = $mmv_data->data;
        if(empty($mmv_data['ic_version_code'] || $mmv_data['ic_version_code'] == ''))
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
            ];
        }
        else if($mmv_data['ic_version_code'] == 'DNE')
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
            ];

        }
        else if (count($mmv_data) > 0)
        {
            $reg_no = explode('-', $requestData->rto_code);

                $rto_code = $reg_no[0] . '' . $reg_no[1];
            $rto_data = RahejaRtoLocation::where('rto_code',$rto_code)
                        ->select('*')->first();
            $premium_type = MasterPremiumType::where('id',$productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
            if($premium_type =='third_party_breakin'){
                $premium_type ='third_party';
            }
            if($premium_type =='own_damage_breakin'){
                $premium_type ='own_damage';
            }
            $BusinessTypeID='25';
            $PHNumericFeild1='1';
                if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                 $ProductCode='2312';
                $CoverType='1476';
                $Tennure='118';

                 $ProductName='TWO WHEELER PACKAGE POLICY(2312)';
                if($premium_type == "third_party"){
                    $ProductCode='2325';
                    $ProductName='TWO WHEELER LIABILITY POLICY(2325)';
                    $CoverType='1694';
                    $Tennure='155';
                }
          } elseif ($requestData->business_type == 'newbusiness'){
                $BusinessTypeID='24';
                $ProductCode='2369';
                $CoverType='1480';
                $Tennure='111';
                $PHNumericFeild1='5';
                $ProductName='TWO WHEELER BUNDLED POLICY(2369)';
                if($premium_type == "third_party"){
                    $ProductCode='2326';
                    $ProductName='TWO WHEELER LONG TERM LIABILITY POLICY(2326)';
                    $CoverType='1695';
                    $Tennure='157';
                }
            } else {
                $ProductCode='2324';
                $CoverType='1686';
                $Tennure='153';
                $ProductName='TWO WHEELER STANDALONE OD(2324)';
            }
            switch ($requestData->business_type) {

                case 'rollover':
                    $business_type='Roll Over';
                    break;

                case 'newbusiness':
                    $business_type='New Business';
                    break;

                default:
                    $business_type =$requestData->business_type;
                    break;

            }
          $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
            $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

            $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                ->first();
            if (isset($rto_data->rto_code) && $rto_data->rto_code !== null)
            {
                if($requestData->business_type == 'rollover'){
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    $pre_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                    $TPPolicyStartDate = date('Y-m-d');
                    $TPPolicyEndDate = date('Y-m-d', strtotime('+5 year -1 day', strtotime($TPPolicyStartDate)));
                    if ($requestData->applicable_ncb == 0)
                    {
                        $no_claim_bonus_val = $requestData->applicable_ncb;
                        $no_claim_bonus = ['ZERO', 'TWENTY', 'TWENTY_FIVE', 'THIRTY_FIVE', 'FORTY_FIVE', 'FIFTY', 'FIFTY_FIVE', 'SIXTY_FIVE'];
                        $previousNoClaimBonus = $no_claim_bonus[$no_claim_bonus_val];
                    }
                    else
                    {
                        $motor_claims_made = 'true';
                        $previousNoClaimBonus = '0';
                    }
                    $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
                }
                else
                {
                    $policy_start_date = date('Y-m-d');
                    $motor_claims_made = 'false';
                    $previousNoClaimBonus = 'ZERO';
                    $isVehicleNew = 'true';
                    $policy_end_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
                }
                if($requestData->business_type == 'breakin'){
                    $policy_start_date = date('Y-m-d');
                    $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime($policy_start_date)));
                    $policy_end_date=date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                    $pre_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                    $TPPolicyStartDate = $policy_start_date;
                    $TPPolicyEndDate = date('Y-m-d', strtotime('+5 year -1 day', strtotime($TPPolicyStartDate)));

                }
                if($premium_type == "third_party")
                {
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
                }
                else{
                    $applicable_addons = [];
                    if ($bike_age > 8)
                    {
                        $engine_protector = "false";
                        $consumable = "false";
//                        array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
//                        array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                        // $zero_dep = "false";
                    }
                    else
                    {
                        $engine_protector = "true";
                        $consumable = "true";
                        // $zero_dep = "true";
                    }
                    $ep_check=false;
                    $ce_check=false;
                    $RTI=false;
                    if ($bike_age > 2)
                    {
                        $zero_dep = "false";
//                        array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    }
                    else
                    {
                        $ce_check=true;
                        $ep_check=true;
                        $RTI=true;
                        $zero_dep = "true";
                    }
                    /*engine protector*/

                    /*return_to_invoice*/
                    if ($bike_age > 4)
                    {
                        $return_to_invoice = "false";
//                        array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                    }
                    else
                    {
                        $return_to_invoice = "true";
                    }
                    /*return_to_invoice*/
                    /*non-electric additional cover*/
                    $motor_lpg_cng_kit=0;$motor_non_electric_accessories=0;$motor_electric_accessories=0;
                    if (!empty($additional['accessories'])) {
                        foreach ($additional['accessories'] as $key => $data) {
                            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                                $motor_lpg_cng_kit = $data['sumInsured'];
                            }

                            if ($data['name'] == 'Non-Electrical Accessories') {
                                $motor_non_electric_accessories = $data['sumInsured'];
                            }

                            if ($data['name'] == 'Electrical Accessories') {
                                $motor_electric_accessories = $data['sumInsured'];
                            }
                        }
                    }

                    if ($motor_non_electric_accessories != '' && $motor_non_electric_accessories < 200000 && $motor_non_electric_accessories != 0)
                    {
                        $nonElectricalCoverSelection = "true";
                        $nonElectricalCoverInsuredAmount = $motor_non_electric_accessories;
                    }
                    else
                    {
                        $nonElectricalCoverSelection = "false";
                        $nonElectricalCoverInsuredAmount = 0;
                    }
                    /*non-electric additional cover*/
                    /*Electric additional cover*/
                    if ($motor_electric_accessories != '' && $motor_electric_accessories < 200000 && $motor_electric_accessories != 0)
                    {
                        $electricalCoveSelection = "true";
                        $electricalCoverInsuredAmount = $motor_electric_accessories;
                    }
                    else
                    {
                        $electricalCoveSelection = "false";
                        $electricalCoverInsuredAmount = 0;
                    }
                    /*Electric additional cover*/
                    /*LPG-CNG additional cover*/
                    if (($motor_lpg_cng_kit != '') && ($motor_lpg_cng_kit > 1000) && ($motor_lpg_cng_kit < 60000))
                    {
                        $cngCoverSelection = "true";
                        $cngCoverInsuredAmount = $motor_lpg_cng_kit;
                    }
                    else
                    {
                        $cngCoverSelection = "false";
                        $cngCoverInsuredAmount = 0;
                    }
                    /*LPG-CNG additional cover*/
                }
                /*unnamed-passenger addon*/
                $motor_acc_cover_unnamed_passenger=0;$isPACoverPaidDriverAmount=0;$legal_liability='N';
                foreach($additional_covers as $key => $value) {
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
                }
                if ($motor_acc_cover_unnamed_passenger != 0)
                {
                    $paUnnamedPersonCoverselection = "true";
                    $paUnnamedPersonCoverinsuredAmount = $motor_acc_cover_unnamed_passenger;
                }
                else
                {
                    $paUnnamedPersonCoverselection = "false";
                    $paUnnamedPersonCoverinsuredAmount = 0;
                }
                /*unnamed-passenger addon*/

                /*paid_driver addon*/
                if ( $isPACoverPaidDriverAmount != 0)
                {
                    $paid_driver_selection = "true";
                    $paid_driver_amount = $isPACoverPaidDriverAmount;
                }
                else
                {
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
                $pospContactNumber =null;
                if($premium_type == "comprehensive" || $premium_type == "own_damage" || $premium_type == "third_party" || $premium_type == "breakin")
                {
                    $Prev_policy_code = "1";
                    $Prev_policy_name = "COMPREHENSIVE";
                }
                else
                {
                    $Prev_policy_code = "2";
                    $Prev_policy_name = "LIABILITY ONLY";
                }
                if ($premium_type == "third_party")
                {
                    $ncb_discount_rate = 0;
                    $requestData->previous_ncb="0";
                        $requestData->applicable_ncb="0";
                }
                else
                {
                    $ncb_discount_rate = $requestData->applicable_ncb;
                }
                /*ncb_protection*/
                if ($requestData->previous_ncb > 20)
                {
                    $ncb_protection = 'true';
                }
                else
                {
                    $ncb_protection = 'false';
                }
                /*ncb_protection*/
                /*voluntary_deductible*/

                if(isset($requestData->voluntary_excess_value) && !empty($requestData->voluntary_excess_value))
                {
                    $voluntary_deductible = $requestData->voluntary_excess_value;
                }
                else
                {
                    $voluntary_deductible = '0';
                }
                if($voluntary_deductible != '0')
                {
                    $voluntary_deductible_flag = 'true';
                }
                else
                {
                    $voluntary_deductible_flag = 'false';
                }
                $zero_dep = (($productData->zero_dep == '0') ? true : false);
                $tppd_cover = false;
                if (!empty($additional['discounts'])) {
                    foreach ($additional['discounts'] as $key => $data) {
                        if ($data['name'] == 'TPPD Cover') {
                            $tppd_cover = true;
                        }
                    }
                }
                /*voluntary_deductible*/
                $year = explode('-', $requestData->manufacture_year);
                $yearOfManufacture = trim(end($year));
                /*trace-id generation*/
                if ($bike_age <=2) {
                    array_push($applicable_addons,'zeroDepreciation');
                    array_push($applicable_addons,'engineProtector');
                    array_push($applicable_addons,'consumables');
                    array_push($applicable_addons,'returnToInvoice');
                }
                $trace_id= array();
                $PrevNCB=($requestData->previous_ncb == "0") ? "0" : $requestData->previous_ncb;
                $IsClaimedLastYear=($requestData->applicable_ncb == "0") ? "1" : "2";
                $NatureOfLoss=($requestData->applicable_ncb == '0') ? "1" : "";
                $CurrentNCBHidden=($requestData->applicable_ncb == '20') ? "20" : $ncb_discount_rate;
                if ($requestData->business_type == 'breakin') {
                    $vehicle_in_90_days = $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
                    if ($date_difference > 90) {
                        $PrevNCB = "0";
                        $IsClaimedLastYear = "1";
                        $NatureOfLoss = "1";
                        $CurrentNCBHidden = "0";
                    }

                }
                $get_response = getWsData(
                    config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_TRACE_ID'),
                    [],
                    'raheja',
                    [
                        'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE') ,
                        'password' =>  config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                        'request_method'       => 'get',
                        'request_data' => [
                            'section'     => 'bike',
                            'method' => 'traceid',
                            'proposal_id' => '0',
                        ],
                        'section'     => 'bike',
                        'company'     => $productData->company_name,
                        'productName'     => $productData->product_sub_type_name,
                        'enquiryId'     => $enquiryId,
                        'method'       => 'Trace Id Generation',
                        'transaction_type' => 'quote',
                    ]
                );
                $trace_id_response = $get_response['response'];
                //$mmv_data['cc'] = "1298";
                $idv_api = [
                    'objVehicleDetails' => [              //IDV API
                    "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant']. "|" . $mmv_data['cc'] . "CC",
                    "RtoLocation" => $rto_data->rto_code,
                    "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                    "ManufacturingYear" => $yearOfManufacture,
                    "ManufacturingMonth" => date('m', strtotime($requestData->vehicle_register_date))
                ],
                'objPolicy' => [
                        "UserName" => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                         "ProductCode" =>$ProductCode,
                        "TraceID" => str_replace('"','',$trace_id_response),// "TAPI240620018939",
                        "SessionID" => "",
                        "TPSourceName" => config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                        "BusinessTypeID" => $BusinessTypeID,
                        "PolicyStartDate" => $policy_start_date
                    ],
                ];
                $get_response = getWsData(config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_IDV'), $idv_api, 'raheja',
                    [
                        'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                        'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                        'request_data' => [
                            'section'     => 'bike',
                            'method'      => 'IDV calculations',
                            'proposal_id' => '0',
                        ],
                        'section'     => 'bike',
                        'method'      => 'IDV calculations',
                        'request_method'       => 'post',
                        'requestMethod'  => 'post',
                        'company'     => $productData->company_name,
                        'productName'     => $productData->product_sub_type_name,
                        'enquiryId'     => $enquiryId,
                        'transaction_type' => 'quote',
                    ]
                );

                $data=$get_response['response'];
                if(!$data){
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Idv Service Issue',

                    ];
                }
                $vehicle_reg_num = explode('-', $requestData->rto_code);
                $veh_reg_date = explode('-', $requestData->vehicle_register_date);
                $response = array_change_key_case_recursive(json_decode($data, true));
                $idv = $response['objvehicledetails']['modifiedidv'];
                $idv_min = $idv - ceil($idv * 0.1);
                $idv_max = $idv + floor($idv * 0.15);
                // $idv=$idv_min;

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
                if (($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')) {
                    $objPreviousInsurance = [
                        "PrevPolicyType" => $Prev_policy_code,
                        "PrevPolicyStartDate" => $pre_policy_start_date,
                        "PrevPolicyEndDate" => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
                        "ProductCode" =>$ProductCode,
                        "PrevInsuranceCompanyID" => "18",
                        "PrevNCB" =>$PrevNCB,
                        "IsClaimedLastYear" =>$IsClaimedLastYear,
                        "NatureOfLoss" =>$NatureOfLoss,
                        "prevPolicyCoverType" => $Prev_policy_name,
                        "CurrentNCBHidden" =>$CurrentNCBHidden,
                    ];
                } else {
                    $objPreviousInsurance ='';
                }
                /*objPreviousInsurance new business code*/
                if (($premium_type == 'own_damage')) { //od addons
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
                            "CoverID" => "97",
                            "CoverName" => "Zero Depreciation",
                            "CoverType" => "AddOnCovers",
                            "PackageName" => "AddOnCovers",
                            "IsChecked" => $zero_dep,
                            "objCoverDetails" => [
                                "PHIntField1" => 0,
                                "PHVarcharFeild1" => ($premium_type == "third_party") ? "2" : ">2",
                                "PHVarcharFeild2" => ($premium_type == "third_party") ? "" : "yes",
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
                                "CoverID"=>"37",
                                "CoverName"=>"Engine Protect",
                                "CoverType"=>"AddOnCovers",
                                "PackageName"=>"AddOnCovers",
                                "IsChecked"=>$ep_check,
                                "objCoverDetails"=>[
                                    "PHVarcharFeild2"=>"Geared"

                                ]

                            ],
                            [
                                "CoverID"=>"24",
                                "CoverName"=>"Consumable Expenses",
                                "CoverType"=>"AddOnCovers",
                                "PackageName"=>"AddOnCovers",
                                "IsChecked"=>$ce_check,
                                "objCoverDetails"=>null

                            ],
                            [
                                "CoverID"=>"80",
                                "CoverName"=>"Return To Invoice",
                                "CoverType"=>"AddOnCovers",
                                "PackageName"=>"AddOnCovers",
                                "IsChecked"=>$RTI,
                                "objCoverDetails"=>null

                            ],

                    ];
                }
                elseif($premium_type == 'third_party')
                {
                    $objCovers = [
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
                            "PHNumericFeild1" => isset($mmv_data['Seating_Capacity']) ? $mmv_data['Seating_Capacity'] : "2"
                            ]
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
                                "PHNumericFeild1" =>$PHNumericFeild1,
                                "PHVarcharFeild4" => "",
                                "PHVarcharFeild5" => "" ,
                            ]
                        ],
                        [
                            "CoverID"=> "87",
                            "CoverName"=> "TPPD",
                            "CoverType"=> "LiabilityPackage",
                            "PackageName"=> "LiabilityPackage",
                            "IsChecked"=> $tppd_cover,
                            "objCoverDetails"=> null,

                        ],
                    ];
                }
                else //new and rollover addons
                {
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
                            "PHNumericFeild1" => isset($mmv_data['Seating_Capacity']) ? $mmv_data['Seating_Capacity'] : "2"
                            ]
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
                                  "PHNumericFeild1" =>$PHNumericFeild1,
                                "PHVarcharFeild4" => "",
                                "PHVarcharFeild5" => "" ,
                            ]
                        ],
                        [
                            "CoverID" => "97",
                            "CoverName" => "Zero Depreciation",
                            "CoverType" => "AddOnCovers",
                            "PackageName" => "AddOnCovers",
                            "IsChecked" => $zero_dep,
                            "objCoverDetails" => [
                                "PHIntField1" => 0,
                                "PHVarcharFeild1" => ($premium_type == 'third_party') ? "2" : ">2",
                                "PHVarcharFeild2" => ($premium_type == 'third_party') ? "" : "",
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
                            "CoverID"=> "87",
                            "CoverName"=> "TPPD",
                            "CoverType"=> "LiabilityPackage",
                            "PackageName"=> "LiabilityPackage",
                            "IsChecked"=> $tppd_cover,
                            "objCoverDetails"=> null,

                        ],
                            [
                                "CoverID"=>"37",
         "CoverName"=>"Engine Protect",
         "CoverType"=>"AddOnCovers",
         "PackageName"=>"AddOnCovers",
         "IsChecked"=>$ep_check,
         "objCoverDetails"=>[
                                "PHVarcharFeild2"=>"Geared"

]

],
      [
          "CoverID"=>"24",
         "CoverName"=>"Consumable Expenses",
         "CoverType"=>"AddOnCovers",
         "PackageName"=>"AddOnCovers",
         "IsChecked"=>$ce_check,
         "objCoverDetails"=>null

],
      [
          "CoverID"=>"80",
         "CoverName"=>"Return To Invoice",
         "CoverType"=>"AddOnCovers",
         "PackageName"=>"AddOnCovers",
         "IsChecked"=>$RTI,
         "objCoverDetails"=>null

],
                    ];
                }
                /*Quick quote service*/
                $premium_api = [
                        "Loading" => "",
                        "Discount" => "",
                        "objClientDetails" => [
                        "MobileNumber" => "8898621511",
                        "ClientType" => ($requestData->vehicle_owner_type == 'I') ? "0" : "1",
                        "EmailId" => "abc@gmail.com"
                    ],
                    "objVehicleDetails" => [
                        "MakeModelVarient" => $mmv_data['make_desc'] . "|" . $mmv_data['model_desc'] . "|" . $mmv_data['variant']. "|" . $mmv_data['cc'] . "CC",
                        "RtoLocation" => trim($rto_data->rto_loc_name . '|' . $rto_data->rto_code),
                        "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                        "Registration_Number1" => $vehicle_reg_num[0],
                        "Registration_Number2" => $vehicle_reg_num[1],
                        "Registration_Number3" => "NM",
                        "Registration_Number4" => "1221",
                        "ManufacturingYear" => $veh_reg_date[2],
                        "ManufacturingMonth" => $veh_reg_date[1],
                        "FuelType" => $mmv_data['Fuel_Type'],
                        "BodyType" => "scooter",
                        "TypeofModification" => "",
                        "ModifiedIDV" => $idv
                    ],
                    "objPolicy" => [
                        "TraceID" => str_replace('"','',$trace_id_response),
                        "UserName" => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                        "TPSourceName" => config('constants.IcConstants.raheja.TP_SOURCE_NAME_RAHEJA_BIKE'),
                        "SessionID" => "",
                        "ProductCode" =>$ProductCode,
                        "ProductName" =>$ProductName,
                        "PolicyStartDate" => $policy_start_date,
                        "PolicyEndDate" => $policy_end_date,
                        "BusinessTypeID" => $BusinessTypeID,
                        "CoverType" =>$CoverType,
                        "Tennure" => $Tennure,
                        "TPPolicyStartDate" => ($premium_type == 'own_damage' || $requestData->business_type == 'breakin') ? $TPPolicyStartDate : "",
                        "TPPolicyEndDate" => ($premium_type == 'own_damage' || $requestData->business_type == 'breakin') ? $TPPolicyEndDate : "",
                        ],
                    "objPreviousInsurance"=>$objPreviousInsurance,
                    "objCovers" => $objCovers,
                ];

                 if ($requestData->business_type == 'newbusiness')
                 {
                     unset($premium_api['objPreviousInsurance']);
                 }
//                 print_r($premium_type);
//                print_r(json_encode($premium_api));
//                die;
                $get_response = getWsData(
                    config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_PREMIUM'), $premium_api, 'raheja',
                    [
                        'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                        'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                        'request_data' => [
                            'quote'       => $enquiryId,
                            'proposal_id' => '0',
                            'method'      => 'Quote Premium Calculation',
                            'section'     => 'bike',

                        ],
                        'section'     => 'bike',
                        'request_method' => 'post',
                        'company'     => $productData->company_name,
                        'productName'     => $productData->product_sub_type_name,
                        'enquiryId'     => $enquiryId,
                        'requestMethod'  => 'post',
                        'method'      => 'Quote Premium Calculation',
                        'transaction_type' => 'quote',
                    ]
                );
                $data = $get_response['response'];
//                 print_r(json_encode($data));
//                 die;
                if($data){
                    $response = array_change_key_case_recursive(json_decode($data, true));
                    if($response['objfault']['errormessage'] == ''){
                        if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y')
                        {
                            if ($requestData->edit_idv >= $idv_max)
                            {
                                $idv = floor($idv_max);
                                $idv = floor($idv_max);
                            }
                            elseif ($requestData->edit_idv <= $idv_min)
                            {
                                $idv  = floor($idv_min);
                                $idv  = floor($idv_min);
                            }
                            else
                            {
                                $idv  = floor($requestData->edit_idv);
                                $idv  = floor($requestData->edit_idv);
                            }
                        }else{
                            $idv  = floor($idv_min);
                        }
                        $premium_api['objVehicleDetails']['ModifiedIDV'] = $idv;
                        $get_response = getWsData(
                            config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_TRACE_ID'),
                            [],
                            'raheja',
                            [
                                'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE') ,
                                'password' =>  config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                                'request_method'       => 'get',
                                'request_data' => [
                                    'section'     => 'bike',
                                    'method' => 'traceid',
                                    'proposal_id' => '0',
                                ],
                                'section'     => 'bike',
                                'company'     => $productData->company_name,
                                'productName'     => $productData->product_sub_type_name,
                                'enquiryId'     => $enquiryId,
                                'method'       => 'Trace Id Generation',
                                'transaction_type' => 'quote',
                            ]
                        );
                        $trace_id_response=$get_response['response'];
                        $premium_api['objPolicy']['TraceID'] =  str_replace('"','',$trace_id_response);
                        $get_response = getWsData(
                            config('constants.IcConstants.raheja.END_POINT_URL_RAHEJA_BIKE_PREMIUM'), $premium_api, 'raheja',
                            [
                                'webUserId' => config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_BIKE'),
                                'password' => config('constants.IcConstants.raheja.PASSWORD_RAHEJA_BIKE'),
                                'request_data' => [
                                    'quote'       => $enquiryId,
                                    'proposal_id' => '0',
                                    'method'      => 'Quote Premium Calculation',
                                    'section'     => 'bike',

                                ],
                                'section'     => 'bike',
                                'request_method' => 'post',
                                'company'     => $productData->company_name,
                                'productName'     => $productData->product_sub_type_name,
                                'enquiryId'     => $enquiryId,
                                'requestMethod'  => 'post',
                                'method'      => 'Quote Premium Calculation',
                                'transaction_type' => 'quote',
                            ]
                        );
                        $data_idv = $get_response['response'];
                        if ($data_idv)
                        {
                           $response = array_change_key_case_recursive(json_decode($data_idv, true));
                        }
                       /*idv range calculation*/
                       $tppd= 0; $rsa = 0; $tyre_protect = 0; $zero_dep = 0; $ncb_protection = 0; $consumable = 0; $eng_protect = 0; $rti = 0; $od = 0;
                       $electrical_accessories = 0; $non_electrical_accessories = 0; $lpg_cng = 0; $lpg_cng_tp = 0; $pa_owner = 0; $llpaiddriver = 0;
                       $pa_unnamed = 0; $paid_driver = 0; $key_replacement = 0; $loss_of_personal_belongings = 0; $voluntary_deductible = 0;$ic_vehicle_discount=0;
                       $tppd_discount=0;
                        foreach ($response['lstcoverresponce'] as $key => $value) {
                            if ($value['covername'] == 'Basic - OD')
                            {
                                $od = $value['coverpremium'];

                            } elseif ($value['covername'] == 'Basic - TP') {

                                $tppd = $value['coverpremium'];

                            } elseif ($value['covername'] == 'PA - Owner') {

                                $pa_owner = $value['coverpremium'];

                            } elseif ($value['covername'] == 'Zero Depreciation') {

                                $zero_dep = $value['coverpremium'];

                            } elseif ($value['covername'] == 'PA - Unnamed Person') {

                                $pa_unnamed = $value['coverpremium'];

                            } elseif ($value['covername'] == 'Paid Driver') {

                                $paid_driver = $value['coverpremium'];

                            } elseif ($value['covername'] == 'Voluntary Deductibles') {

                                $voluntary_deductible = $value['coverpremium'];

                            } elseif ($value['covername'] == 'TPPD') {

                                $tppd_discount = $value['coverpremium'];

                            } elseif ($value['covername'] == 'Engine Protect'){

                                 $eng_protect = $value['coverpremium'];

                        } elseif ($value['covername'] == 'Consumable Expenses'){

                        $consumable = $value['coverpremium'];
                        }
                            elseif ($value['covername'] == 'Return To Invoice'){

                                $rti = $value['coverpremium'];
                            }

                        }
                        $final_od_premium = $od;
                        $final_tp_premium = $response['totalliabilitypremium'] + $tppd_discount;
                        $final_tp_premium = $final_tp_premium - $pa_owner;
                        $totalTax = $response['totaltax'];
                        $total_premium_amount = round($response['finalpremium']);
                        //$base_premium_amount = $final_od_premium + $final_tp_premium - $final_total_discount;
                        $base_premium_amount = $total_premium_amount / (1 + (18 / 100));
                        if($requestData->business_type == 'newbusiness')
                        {
                            $add_ons_data = [
                                'in_built'   => [],
                                'additional' => [
                                    'zeroDepreciation'            => round($zero_dep),
                                    'engineProtector' => round($eng_protect),
                                    'consumables' => round($consumable),
                                    'returnToInvoice' => round($rti),
                                ],
                                'other'      => [],
                            ];
                        }
                        else
                        {
                            $add_ons_data = [
                                'in_built'   => [],
                                'additional' => [
                                    'zeroDepreciation'            => round($zero_dep),
                                    'cpa_cover'                   => round($pa_owner),
                                    'engineProtector' => round($eng_protect),
                                    'consumables' => round($consumable),
                                    'returnToInvoice' => round($rti),
                                ],
                                'other'      => [],
                            ];
                        }
                        if(isset($response['ncbpremium']))
                        {
                            $ncb_discount = str_replace("INR ", "", $response['ncbpremium']);
                        }
                        else
                        {
                            $ncb_discount = 0;
                        }
                        foreach ($add_ons_data as $add_on_key => $add_on_value)
                        {
                            if (count($add_on_value) > 0)
                            {
                                foreach ($add_on_value as $add_on_value_key => $add_on_value_value)
                                {
                                    if (is_numeric($add_on_value_value))
                                    {
                                        $value = (string)$add_on_value_value;
                                        $base_premium_amount -= $value;
                                    }
                                    else
                                    {
                                        $value = $add_on_value_value;
                                    }
                                    $add_ons[$add_on_key][$add_on_value_key] = $value;
                                }
                            }
                            else
                            {
                                $add_ons[$add_on_key] = $add_on_value;
                            }
                        }
                        array_walk_recursive($add_ons, function (&$item, $key) {
                            if ($item == '' || $item == '0')
                            {
                                $item = 'NA';
                            }
                        });
                       $base_premium_amount = round($base_premium_amount) * (1 + (18 / 100));
                       $final_total_discount = round($ncb_discount) + round($voluntary_deductible) + $tppd_discount;
                        $add_ons_data['additional']=array_filter($add_ons_data['additional'], function ($number) {
                            return $number != 0;
                        });
                       $data_response = [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'status' => true,
                            'msg' => 'Found',
                            'Data' => [
                                'idv' => $premium_type == 'third_party' ? 0 : round($idv),
                                'min_idv' => round($idv_min),
                                'max_idv' => round($idv_max),
                                //'default_idv' => $default_idv,
                                'vehicle_idv' => $idv,
                                'qdata' => null,
                                'pp_enddate' => $requestData->previous_policy_expiry_date,
                                'addonCover' => null,
                                'addon_cover_data_get' => '',
                                'rto_decline' => null,
                                'rto_decline_number' => null,
                                'mmv_decline' => null,
                                'mmv_decline_name' => null,
                                'policy_type' =>($premium_type=='third_party' ? 'Third Party' : ($premium_type=='own_damage' ?'Own Damage':'Comprehensive')),
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
                                'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
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
                                    'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                                    'product_sub_type_name' => $productData->product_sub_type_name,
                                    'flat_discount' => $productData->default_discount,
                                    'predefine_series' => "",
                                    'is_premium_online' => $productData->is_premium_online,
                                    'is_proposal_online' => $productData->is_proposal_online,
                                    'is_payment_online' => $productData->is_payment_online
                                ],
                                'motor_manf_date' =>  $requestData->vehicle_register_date,
                                'vehicleDiscountValues' => [
                                    'master_policy_id' => $productData->policy_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'segment_id' => 0,
                                    'rto_cluster_id' => 0,
                                    'car_age' => $bike_age,
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
                                'total_own_damage' => ($premium_type == 'third_party')? 0 :$final_od_premium,
                                'cng_lpg_tp' => $lpg_cng_tp,
                                'total_liability_premium' => $tppd,
                                'net_premium' => round($base_premium_amount),
                                'service_tax_amount' => round($totalTax),
                                'service_tax' => 18,
                                'total_discount_od' => 0,
                                'add_on_premium_total' => 0,
                                'addon_premium' => 0,
                                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                'quotation_no' => '',
                                'premium_amount'  => $total_premium_amount,
                                'antitheft_discount' => 0,
                                'final_od_premium' =>($premium_type == 'third_party')? 0 : $final_od_premium,
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
                                "max_addons_selection"=> NULL,
                                "applicable_addons"=> $applicable_addons,
                                'add_ons_data' =>$add_ons_data
                            ],
                        ];
                        return camelCase($data_response);
                    }
                    else {
                        return [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium_amount' => 0,
                            'status'         => 'false',
                            'message'        => $response['objfault']['errormessage']
                        ];
                    }
                }
            }
            else {
                return [
                    'premium_amount' => 0,
                    'status'         => 'false',
                    'message'        => 'RTO not available'
                ];
            }
        }
    }
}

