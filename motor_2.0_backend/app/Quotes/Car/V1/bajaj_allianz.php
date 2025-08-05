<?php

namespace App\Quotes\Car\V1;

use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Str;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class bajaj_allianz {
    /*
        1. BASIC (without Addons)
        2. DRIVE_ASSURE_PACK_PLUS ("zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb")
        3. DRIVE_ASSURE_PACK ("zeroDepreciation", "engineProtector", "roadSideAssistance")
        4. TELEMATICS_PREMIUM (zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb", "Accident_shield")
        5. TELEMATICS_PRESTIGE ("zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb", "consumables", "Accident_shield", "Conveyance_Benefit")
        6. TELEMATICS_CLASSIC ("keyReplace", "roadSideAssistance", "lopb", "Accident_shield")
        7. Prime ("keyReplace", "roadSideAssistance")
        8. BASIC_ADDON ("keyReplace", "roadSideAssistance", "engineProtector", "lopb", "consumables", "Accident_shield", "Conveyance_Benefit")
    */
public static function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
    //Removing age validation for all iC's  #31637 
    // if ($requestData->previous_policy_type == 'Not sure')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not available at insurer if previous policy details are not sure'
    //     ];
    // }
    
    // if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
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

    $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');
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
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv_data
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv_data
            ]
        ];
    }
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
    if (empty($masterProduct)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Master Product mapping not found',
            'request' => [
                'message' => 'Master Product mapping not found',
                'policy_id' => $productData->policy_id
            ]
        ];
    } else if ($productData->premium_type_id == '2') {
        $masterProduct = new \stdClass();
        $masterProduct->product_identifier = '';
    }
    $date1 = new \DateTime($requestData->vehicle_register_date);
    $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $car_age = ceil($age / 12);
    // zero depriciation validation
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    // if (($interval->y >= 19) && ($tp_check == 'true')) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 19 years',
    //     ];
    // }
    // if ($car_age > 5 && $productData->zero_dep == '0') {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //         'request' => [
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    // if (($car_age > 15) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin'])){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Car age should not be greater than 15 year',
    //         'request' => [
    //             'message' => 'Car age should not be greater than 15 year',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }
    if ($requestData->business_type == 'newbusiness') {
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        if($date_difference > 0) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Backe Date not allowed for New Business policy',
                'request' => [
                    'message' => 'Backe Date not allowed for New Business policy',
                    'vehicle_register_date' => $requestData->vehicle_register_date
                ]
            ];
        }
    }
    $rto_code = $requestData->rto_code;
    // Re-arrange for Delhi RTO code - start
    $rto_code = explode('-', $rto_code);
    if ((int) $rto_code[1] < 10) {
        $rto_code[1] = '0' . (int) $rto_code[1];
    }
    $rto_code = implode('-', $rto_code);
    // Re-arrange for Delhi RTO code - End
    if ($rto_code == 'HP-40') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Blocked RTO - HP-40',
            'request' => [
                'message' => 'Blocked RTO - HP-40',
                'rto_code' => $rto_code
            ]
        ];
    }
        
    $tp_only = (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? 'true' : 'false';

    if(($tp_only != 'true') && ($requestData->previous_policy_type == 'Third-party'))
    {
        return
        [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes Not Available For Previous Policy Type Third-Party.',
            'request' => [
                'message' => 'Quotes Not Available For Previous Policy Type Third-Party.',
                'previous_policy_type' => $requestData->previous_policy_type
            ]
        ];
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
    $vehicle_in_90_days = 'N';
    $break_in = 'NO';
    if ($requestData->vehicle_owner_type == 'I') {
        // $extCol24 = '1';
        $extCol24 = isset($cpa_tenure) ? $cpa_tenure : '1';
        $cpa = 'MCPA';
    } else {
        $extCol24 = '0';
        $cpa = '';
    }
    if ($requestData->business_type == 'newbusiness') {
        $BusinessType = '1';
        $polType = '1';
        $policy_start_date = today();
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(3)->subDay(1)->format('d-m-Y');
        $car_age = 0;
        if($requestData->vehicle_owner_type == 'I') {
            // $extCol24 = '3';
        $extCol24 = isset($cpa_tenure) ? $cpa_tenure : '3';
        }
    } else {
        $prev_policy_expiry_date = ($requestData->previous_policy_expiry_date == 'New') ? date('d-m-Y') : $requestData->previous_policy_expiry_date;
        $policy_start_date = (Carbon::parse($prev_policy_expiry_date)->format('d-m-Y') >= now()->format('d-m-Y')) ? Carbon::parse($prev_policy_expiry_date)->addDay(1) : today();
        $date_diff = get_date_diff('day', $prev_policy_expiry_date);
        if ($date_diff > 0) {
            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $policy_start_date = Carbon::today()->addDay(2)->format('d-M-Y');
            } else if($requestData->business_type == 'breakin') {
                $policy_start_date = Carbon::today()->addDay(1)->format('d-M-Y');
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'No quotes available',
                    'request' => [
                        'message' => 'No quotes available',
                        'business_type' => $requestData->business_type,
                        'premium_type' => $premium_type
                    ]
                ];
            }
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
        $BusinessType = '2';
        $polType = '3';
        $date1 = new \DateTime($requestData->vehicle_register_date);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
    }

    if ($requestData->business_type == 'breakin') {

        //RID for breakin is T+1
        $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));

        if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
            //RID for TP breakin is T+3
            $policy_start_date = date('d-m-Y', strtotime('+3 day', time()));
        }

        $policy_end_date = date('d-M-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }

    $externalCNGKITSI = $NonElectricalaccessSI = $NonElectricalaccessSI = $ElectricalaccessSI = $LLtoPaidDriverYN = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = 0;
    $cover_data = [];
    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

    foreach ($additional_covers as $key => $value) {
        if (in_array('LL paid driver', $value)) {
            $LLtoPaidDriverYN = 1;
        }

        /* if (in_array('PA cover for additional paid driver', $value)) {
        $PAPaidDriverConductorCleaner = 1;
        $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        } */

        if (in_array('Unnamed Passenger PA Cover', $value)) {
            $PAforUnnamedPassenger = 1;
            $PAforUnnamedPassengerSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'PA',
                'typ:paramRef' => 'PA',
            ];
        }
    }
    $is_lpg_cng = false;
    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value) && $value['sumInsured'] != '0') {
            $ElectricalaccessSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'ELECACC',
                'typ:paramRef' => 'ELECACC',
            ];
            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $ElectricalaccessSI = 0;
            }
        }

        if (in_array('Non-Electrical Accessories', $value) && $value['sumInsured'] != '0') {
            $NonElectricalaccessSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'NELECACC',
                'typ:paramRef' => 'NELECACC',
            ];
            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $NonElectricalaccessSI = 0;
            }
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value) && $value['sumInsured'] != '0') {
            $is_lpg_cng = true;
            $externalCNGKITSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'CNG',
                'typ:paramRef' => 'CNG',
            ];
            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $externalCNGKITSI = 0;
            }
        }
    }
    if (!empty($externalCNGKITSI)) {
        if ($externalCNGKITSI < 1000) {
            return [
                'status' => false,
                'message' => 'CNGsumInsured Value should be more then 1000',
            ];
        }
    }

    $voluntary_insurer_discounts = 0;
    $is_tppd_discount = false;
    $is_anti_theft = false;

    foreach ($discounts as $key => $value) {
        if (($tp_only != 'true') && in_array('voluntary_insurer_discounts', $value)) {
            $voluntary_insurer_discounts = isset($value['sumInsured']) ? $value['sumInsured'] : 0;
        }
        if (in_array('TPPD Cover', $value)) {
            $is_tppd_discount = true;
        }
        if (($tp_only != 'true') && in_array('anti-theft device', $value)) {
            $is_anti_theft = true;
        }
    }
    if ($voluntary_insurer_discounts != 0) {
        $cover_data[] = [
            'typ:paramDesc' => 'VOLEX',
            'typ:paramRef' => 'VOLEX',
        ];
    }

    if ($is_tppd_discount) {
         $cover_data[] = [
            'typ:paramDesc' => 'TPPD_RES',
            'typ:paramRef' => 'TPPD_RES',
        ];
    }

    if ($is_anti_theft) {
         $cover_data[] = [
            'typ:paramDesc' => 'ATHEFT',
            'typ:paramRef' => 'ATHEFT',
        ];
    }

    $rto_code = $requestData->rto_code;  
    $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);
    $rto_details = DB::table('bajaj_allianz_master_rto')->where('registration_code', str_replace('-', '', $rto_code))->first();
    if(empty($rto_details))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Not Available',
            'request' => [
                'message' => 'RTO Not Available',
                'rto_code' => $requestData->rto_code,
            ],
        ]; 
    }
    $zone_A = ['AHMEDABAD', 'BANGALORE', 'CHENNAI', 'HYDERABAD', 'KOLKATA', 'MUMBAI', 'NEW DELHI', 'PUNE', 'DELHI'];
    $zone = ((in_array(strtoupper($rto_details->city_name), $zone_A)) ? 'A' : 'B');

    if ($LLtoPaidDriverYN == 1) {
        $cover_data[] = [
            'typ:paramDesc' => 'LLO',
            'typ:paramRef' => 'LLO',
        ];
    }
    
    $extCol38 = $masterProduct->product_identifier == 'DRIVE_ASSURE_WELCOME' ? '1000' : '0';
    if (in_array($premium_type, ['third_party', 'third_party_breakin'])) { // Only TP
        $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_TP");
    } else if ($BusinessType == '1') { // New Business
        $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_NEW");
    } else if (in_array($premium_type, ['own_damage', 'own_damage_breakin'])) { // Stand Alone OD
        $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_OD");
    } else { // Comprehensive Rollover
        $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE");
    }

    $vehicale_reg_no = str_replace('-', '', !empty($requestData->vehicle_registration_no) ? $requestData->vehicle_registration_no : implode('-',array_merge(explode('-', $rto_code), ['MK', rand(1111, 9999)])));//($rto_code.'-')); // there was premium missmatch if we do not add complete reg no in rollover

    $bajajPosDataFetched = bajajPosDataFetched($requestData, $tp_only);
    
    $is_pos_disable_in_quote = $bajajPosDataFetched->is_pos_disable_in_quote;
    $is_pos_enabled = $bajajPosDataFetched->is_pos_enabled;
    $extCol40 = $bajajPosDataFetched->extCol40;
    $pUserId = $bajajPosDataFetched->pUserId;
    $nonPospUserId = $bajajPosDataFetched->nonPospUserId;
    $bajaj_new_tp_url = $bajajPosDataFetched->bajaj_new_tp_url;
    $pos_data = $bajajPosDataFetched->pos_data;
    // checking for multiple names in rto master
    $check_rto_name = !Str::is(Str::lower($requestData->rto_city),Str::lower($rto_details->city_name)) ? true : false;
    $chk_rei_name = Str::contains($rto_details->city_name, '/');
    if($chk_rei_name && $check_rto_name)
    {
        $sep_city_name = explode('/',$rto_details->city_name);
        foreach($sep_city_name as $val)
        {
            $city_to_lowercase = Str::lower($val);
            $inp_rto = Str::lower($requestData->rto_city);
            if(Str::is($inp_rto, $city_to_lowercase))
            {
                $rto_details->city_name = $val;
                break;
            }
            else

            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'RTO Name Not Available',
                    'request' => [
                        'message' => 'RTO Name Not Available',
                        'rto_code' => $requestData->rto_code,
                    ],
                ]; 
            }
        }
    }

    $data = [
        'soapenv:Header' => [],
        'soapenv:Body' => [
            'web:calculateMotorPremiumSig' => [
                'pUserId' => $pUserId,//config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME"),
                'pPassword' => ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD"),
                'pVehicleCode' => $mmv_data->vehicle_code,
                'pCity' => strtoupper($rto_details->city_name),
                'pWeoMotPolicyIn_inout' => [
                    'typ:contractId' => '0',
                    'typ:polType' => $polType,
                    'typ:product4digitCode' => $product4digitCode,
                    'typ:deptCode' => '18',
                    'typ:branchCode' => config("IC.BAJAJ_ALLIANZ.V1.CAR.BRANCH_OFFICE_CODE"),
                    'typ:termStartDate' => Carbon::parse($policy_start_date)->format('d-M-Y'),
                    'typ:termEndDate' => date('d-M-Y', strtotime($policy_end_date)),
                    'typ:tpFinType' => '',
                    'typ:hypo' => '',
                    'typ:vehicleTypeCode' => '22',
                    'typ:vehicleType' => $mmv_data->vehicle_type,
                    'typ:miscVehType' => '0',
                    'typ:vehicleMakeCode' => $mmv_data->vehicle_make_code,
                    'typ:vehicleMake' => $mmv_data->vehicle_make,
                    'typ:vehicleModelCode' => $mmv_data->vehicle_model_code,
                    'typ:vehicleModel' => $mmv_data->vehicle_model,
                    'typ:vehicleSubtypeCode' => $mmv_data->vehicle_subtype_code,
                    'typ:vehicleSubtype' => $mmv_data->vehicle_subtype,
                    'typ:fuel' => $mmv_data->fuel,
                    'typ:zone' => $zone,
                    'typ:engineNo' => '123123123',
                    'typ:chassisNo' => '12345678910113692',
                    'typ:registrationNo' => $BusinessType == '1' ? "NEW" : $vehicale_reg_no,
                    'typ:registrationDate' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                    'typ:registrationLocation' => $rto_details->city_name,
                    'typ:regiLocOther' => $rto_details->city_name,
                    'typ:carryingCapacity' => $mmv_data->carrying_capacity,
                    'typ:cubicCapacity' => $mmv_data->cubic_capacity,
                    'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                    'typ:color' => 'WHITE',
                    'typ:vehicleIdv' => '',
                    'typ:ncb' => $requestData->applicable_ncb,
                    'typ:addLoading' => '0',
                    'typ:addLoadingOn' => '0',
                    'typ:spDiscRate' => '0',
                    'typ:elecAccTotal' => $ElectricalaccessSI,
                    'typ:nonElecAccTotal' => $NonElectricalaccessSI,
                    'typ:prvPolicyRef' => '',
                    'typ:prvExpiryDate' => (($BusinessType == '1') ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                    'typ:prvInsCompany' => '',
                    'typ:prvNcb' => $requestData->previous_ncb,
                    'typ:prvClaimStatus' => (($requestData->is_claim == 'Y') ? '1' : '0'),
                    'typ:autoMembership' => '0',
                    'typ:partnerType' => (($requestData->vehicle_owner_type == 'I') ? 'P' : 'I'),
                ],
                'accessoriesList_inout' => [
                    'typ:WeoMotAccessoriesUser' => [
                        'typ:contractId' => '',
                        'typ:accCategoryCode' => '',
                        'typ:accTypeCode' => '',
                        'typ:accMake' => '',
                        'typ:accModel' => '',
                        'typ:accIev' => '',
                        'typ:accCount' => '',
                    ],
                ],
                'paddoncoverList_inout' => [
                    'typ:WeoMotGenParamUser' => $cover_data,
                ],
                'motExtraCover' => [
                    'typ:geogExtn' => '',
                    'typ:noOfPersonsPa' => $PAforUnnamedPassenger ? $mmv_data->carrying_capacity : '',
                    'typ:sumInsuredPa' => $PAforUnnamedPassenger ? $PAforUnnamedPassengerSI : '',
                    'typ:sumInsuredTotalNamedPa' => '',
                    'typ:cngValue' => $externalCNGKITSI,
                    'typ:noOfEmployeesLle' => '',
                    'typ:noOfPersonsLlo' => $LLtoPaidDriverYN,
                    'typ:fibreGlassValue' => '',
                    'typ:sideCarValue' => '',
                    'typ:noOfTrailers' => '',
                    'typ:totalTrailerValue' => '',
                    'typ:voluntaryExcess' => $voluntary_insurer_discounts,
                    'typ:covernoteNo' => '',
                    'typ:covernoteDate' => '',
                    'typ:subImdcode' => '',
                    'typ:extraField1' => '400002', // Hardcoded
                    'typ:extraField2' => '',
                    'typ:extraField3' => '',
                ],
                'pQuestList_inout' => [
                    'typ:WeoBjazMotQuestionaryUser' => [
                        'typ:questionRef' => '',
                        'typ:contractId' => '',
                        'typ:questionVal' => '',
                    ],
                ],
                'pDetariffObj_inout' => [
                    'typ:vehPurchaseType' => '',
                    'typ:vehPurchaseDate' => !empty($requestData->vehicle_invoice_date) ? date('d-M-Y', strtotime($requestData->vehicle_invoice_date)) : "",
                    'typ:monthOfMfg' => '',
                    'typ:bodyType' => '',
                    'typ:goodsTransType' => '',
                    'typ:natureOfGoods' => '',
                    'typ:otherGoodsFrequency' => '',
                    'typ:permitType' => '',
                    'typ:roadType' => '',
                    'typ:vehDrivenBy' => '',
                    'typ:driverExperience' => '',
                    'typ:clmHistCode' => '',
                    'typ:incurredClmExpCode' => '',
                    'typ:driverQualificationCode' => '',
                    'typ:tacMakeCode' => '',
                    'typ:registrationAuth' => '',
                    'typ:extCol1' => '',
                    'typ:extCol2' => '',
                    'typ:extCol3' => '',
                    'typ:extCol4' => '',
                    'typ:extCol5' => '',
                    'typ:extCol6' => '',
                    'typ:extCol7' => '',
                    'typ:extCol8' => $cpa,
                    'typ:extCol9' => '',
                    'typ:extCol10' => $masterProduct->product_identifier,
                    'typ:extCol11' => '',
                    'typ:extCol12' => '',
                    'typ:extCol13' => '',
                    'typ:extCol14' => '',
                    'typ:extCol15' => '',
                    'typ:extCol16' => '',
                    'typ:extCol17' => '',
                    'typ:extCol18' => 'CPA',
                    'typ:extCol19' => '',
                    'typ:extCol21' => '',
                    'typ:extCol20' => '',
                    'typ:extCol22' => '',
                    'typ:extCol23' => '',
                    'typ:extCol24' => $extCol24, //CPA cover
                    'typ:extCol25' => '',
                    'typ:extCol26' => '',
                    'typ:extCol29' => '',
                    'typ:extCol27' => '',
                    'typ:extCol28' => '',
                    'typ:extCol30' => '',
                    'typ:extCol31' => '',
                    'typ:extCol32' => '',
                    'typ:extCol33' => '',
                    'typ:extCol34' => '',
                    'typ:extCol35' => '',
                    'typ:extCol36' => '',
                    'typ:extCol37' => '',
                    'typ:extCol38' => $extCol38,
                    'typ:extCol39' => '',
                    'typ:extCol40' => '', //$POS_code,
                ],
                'premiumDetailsOut_out' => [
                    'typ:serviceTax' => '',
                    'typ:collPremium' => '',
                    'typ:totalActPremium' => '',
                    'typ:netPremium' => '',
                    'typ:totalIev' => '',
                    'typ:addLoadPrem' => '',
                    'typ:totalNetPremium' => '',
                    'typ:imtOut' => '',
                    'typ:totalPremium' => '',
                    'typ:ncbAmt' => '',
                    'typ:stampDuty' => '',
                    'typ:totalOdPremium' => '',
                    'typ:spDisc' => '',
                    'typ:finalPremium' => '',
                ],
                'premiumSummeryList_out' => [
                    'typ:WeoMotPremiumSummaryUser' => [
                        'typ:od' => '',
                        'typ:paramDesc' => '',
                        'typ:paramRef' => '',
                        'typ:net' => '',
                        'typ:act' => '',
                        'typ:paramType' => '',
                    ],
                ],
                'pError_out' => [
                    'typ:WeoTygeErrorMessageUser' => [
                        'typ:errNumber' => '',
                        'typ:parName' => '',
                        'typ:property' => '',
                        'typ:errText' => '',
                        'typ:parIndex' => '',
                        'typ:errLevel' => '',
                    ],
                ],
                'pErrorCode_out' => '',
                'pTransactionId_inout' => '',
                'pTransactionType' => '',
                'pContactNo' => '',
            ],
        ],
    ];
    

    if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y'){
        $is_renewbuy = true; 
    }else{
    $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
    }
    if($is_renewbuy)
    {
        $data['soapenv:Body']['web:calculateMotorPremiumSig']['pUserId'] = $nonPospUserId;
        $data['soapenv:Body']['web:calculateMotorPremiumSig']['pDetariffObj_inout']['typ:extCol40'] = '';
    }

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            'Content-Type' => 'text/xml; charset="utf-8"',
        ],
        'requestMethod' => 'post',
        'requestType' => 'xml',
        'section' => 'Car',
        'method' => 'Premium Calculation',
        //'productName' => $masterProduct->product_identifier,
        'productName' => $productData->product_identifier,
        'product' => 'Private Car',
        'transaction_type' => 'quote',
    ];
    $root = [
        'rootElementName' => 'soapenv:Envelope',
        '_attributes' => [
            "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
            "xmlns:web" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl",
            "xmlns:typ" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl/types/",
        ],
    ];
    $temp_data = $data;
    unset($temp_data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:registrationNo']);
    $checksum_data = checksum_encrypt($temp_data);
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'bajaj_allianz',$checksum_data,"CAR");
    $additional_data['checksum'] = $checksum_data;
    $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');

    if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
            $url = config('IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_TP_URL');
    } else {
            $url = config('IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_URL');
    }

    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_response = $is_data_exist_for_checksum;
    }else{
        $get_response = getWsData($url, $input_array, 'bajaj_allianz', $additional_data);
    }
    
    if (empty($get_response['response'])) {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer not reachable',
        ];
    }
    $response = XmlToArray::convert($get_response['response']);
    // echo "<pre>";print_r([$response, $data]);echo "</pre>";die();

    if(isset($response['env:Body']['env:Fault']['faultstring']) && $response['env:Body']['env:Fault']['faultstring'] != '')
    {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'status' => false,
            'msg' => $response['env:Body']['env:Fault']['faultstring'],
            'message' => $response['env:Body']['env:Fault']['faultstring'],
        ];
    }

    if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
        $service_response = $response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'];
    } else {
        $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
    }
    
    if ($service_response['pErrorCode_out'] != '0') {
        $error_msg = 'Insurer not reachable';
        if (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'])) {
            $error_msg = $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
        } elseif (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) && is_array($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) && count($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) > 0) {
            $error_msg = implode(', ', array_column($service_response['pError_out']['typ:WeoTygeErrorMessageUser'], 'typ:errText'));
        }
        else if(isset($service_response['pError_out']) && !is_array($service_response['pError_out']))
        {
           $error_msg = $service_response['pError_out']; 
        }
        
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => $error_msg,
        ];
    }
    update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], 'Premium Calculation - ' . $masterProduct->product_identifier, "Success" );
    if ($tp_only == 'true') {
        $idv = $idv_min = $idv_max = 0;
    } else {
        $idv = $service_response['pWeoMotPolicyIn_inout']['typ:vehicleIdv'];
        $idv_min = (string) $idv;
        $idv_max = (string) (1.20 * $idv);
        if (config('constants.motorConstant.SMS_FOLDER') == 'spa') {
            $idv_min = (string) ($idv - ($idv * 0.10));
            $idv_max = (string) (1.10 * $idv);
        }
    }
    if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $idv >= 5000000) {
        $is_renewbuy = true;
    }
    if ($is_renewbuy) {
        if ($idv >= 5000000) {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pUserId'] = $nonPospUserId;
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pDetariffObj_inout']['typ:extCol40'] = '';
        } else {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pUserId'] = $pUserId;
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pDetariffObj_inout']['typ:extCol40'] = $extCol40;
        }
    }

    if ($requestData->is_idv_changed == 'Y' && $tp_only == 'false') {
        if ($requestData->edit_idv >= ($idv_max)) {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = ($idv_max);
            $idvFor50Lac = ($idv_max);
        } elseif ($requestData->edit_idv <= ($idv_min)) {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = ($idv_min);
            $idvFor50Lac = ($idv_min);
        } else {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = $requestData->edit_idv;
            $idvFor50Lac = $requestData->edit_idv;
        }
        $data['soapenv:Body']['web:calculateMotorPremiumSig']['pTransactionId_inout'] = $service_response['pTransactionId_inout'];

        if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $idvFor50Lac >= 5000000){
            $is_renewbuy = true; 
        }
        if ($is_renewbuy) {
            if ($idvFor50Lac >= 5000000) {
                $data['soapenv:Body']['web:calculateMotorPremiumSig']['pUserId'] = $nonPospUserId;
                $data['soapenv:Body']['web:calculateMotorPremiumSig']['pDetariffObj_inout']['typ:extCol40'] = '';
            } else {
                $data['soapenv:Body']['web:calculateMotorPremiumSig']['pUserId'] = $pUserId;
                $data['soapenv:Body']['web:calculateMotorPremiumSig']['pDetariffObj_inout']['typ:extCol40'] = $extCol40;
            }
        }

        $temp_data = $data;
        unset($temp_data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:registrationNo']);
        $checksum_data = checksum_encrypt($temp_data);
        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'bajaj_allianz',$checksum_data,"CAR");
        $additional_data['checksum'] = $checksum_data;

        $additional_data['method'] = 'Premium Re-Calculation';
        $additional_data['productName'] = $productData->product_identifier;
        $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');

        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
            $get_response = $is_data_exist_for_checksum;
        }else{
            $get_response = getWsData(config('IC.BAJAJ_ALLIANZ.V1.CAR.QUOTE_URL'), $input_array, 'bajaj_allianz', $additional_data);
        }
        if (empty($get_response['response'])) {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurer not reachable',
            ];
        }
        $response = XmlToArray::convert($get_response['response']);

        if(isset($response['env:Body']['env:Fault']['faultstring']) && $response['env:Body']['env:Fault']['faultstring'] != '')
        {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'msg' => $response['env:Body']['env:Fault']['faultstring'],
                'message' => $response['env:Body']['env:Fault']['faultstring'],
            ];
        }
        if (!isset($response['env:Body']['m:calculateMotorPremiumSigResponse'])) {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurer not reachable',
            ];
        }
        $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
        if ($service_response['pErrorCode_out'] != '0') {
            $message = 'Insurer not reachable';
            if (!empty($service_response['pError_out']['WeoTygeErrorMessageUser']['errText'])) {
                $message = $service_response['pError_out']['WeoTygeErrorMessageUser']['errText'];
            } else if (!empty($service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText'])) {
                $message = $service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText'];
            } else if (!empty($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'])) {
                $message = $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
            }
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => $message,
                // 'message' => $service_response['pError_out']['WeoTygeErrorMessageUser']['errText'] ?? $service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText'] ?? 'Insurer not reachable',
            ];
        }
    }
    $transaction_id = $service_response['pTransactionId_inout'] ?? '';
    $idv = (int)$service_response['pWeoMotPolicyIn_inout']['typ:vehicleIdv'];
    $zero_dep_amount = $key_replacement = $basic_od = $tppd = $pa_owner = $non_electrical_accessories = $electrical_accessories = $pa_unnamed = $ll_paid_driver = $lpg_cng = $lpg_cng_tp = $other_discount = $ncb_discount = $engine_protector = $lossbaggage = $rsa = $accident_shield = $conynBenef = $consExps = $voluntary_deductible = 0;
    $restricted_tppd = 0;
    $antitheft_discount_amount = 0;
    $geog_Extension_OD_Premium = 0;
    $geog_Extension_TP_Premium = 0;
    $uw_loading_amount = 0;

    $finalPremium = $service_response['premiumDetailsOut_out']['typ:finalPremium'];
    $base_premium_amount = ($finalPremium / (1.18));
    if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
        $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];

        if ($premium_type == 'own_damage' && $requestData->is_claim == 'Y' && $productData->zero_dep == '1' && isset($covers['od'])) {
            $basic_od = ($covers['od']);
        } else if (!isset($covers[0])) {
            if ($covers['typ:paramDesc'] == 'Basic Third Party Liability') {
                $tppd = $covers['typ:act'];
            }
        }
        else {
            foreach ($covers as $key => $cover) {
                if(!isset($cover['typ:paramDesc'])) {
                    continue;
                }
                if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                    $zero_dep_amount = ($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                    $key_replacement = ($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                    $engine_protector = ($cover['typ:od']);
                }
                // elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') 
                elseif (in_array($cover['typ:paramDesc'], ['Basic Own Damage','Basic Own Damage 1']))
                {
                    $basic_od = ($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                    $tppd = ($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                    $pa_owner = ($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                    $non_electrical_accessories = ($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                    $electrical_accessories = ($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                    $pa_unnamed = ($cover['typ:act']);
                } elseif (in_array($cover['typ:paramDesc'], ['LL To Person For Operation/Maintenance(IMT.28/39)', '19LL To Person For Operation/Maintenance(IMT.28/39)'])) {
                    $ll_paid_driver = ($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                    $lpg_cng = ($cover['typ:od']);
                    $lpg_cng_tp = ($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'Commercial Discount') {
                    $other_discount = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                    $ncb_discount = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Personal Baggage Cover') {
                    $lossbaggage = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                    $rsa = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Accident Sheild') {
                    $accident_shield = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Conveyance Benefit') {
                    $conynBenef = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                    $consExps = (abs($cover['typ:od']));
                } elseif (in_array($cover['typ:paramDesc'], ['Voluntary Excess (IMT.22 A)', '6Voluntary Excess (IMT.22 A)'])) {
                    $voluntary_deductible = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                    $restricted_tppd = ($cover['typ:act']);
                }
                elseif(in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)', 'Anti-Theft Device (IMT.10)']))
                {
                    $antitheft_discount_amount = (abs($cover['typ:od']));

                }
                elseif(in_array($cover['typ:paramDesc'], ['CHDH Additional Discount/Loading', 'CHDH Additional Discount/Loading '])) 
                {
                    if ($cover['typ:od'] > 0) 
                    {
                        $uw_loading_amount = (abs($cover['typ:od']));
                    }
                    else
                    {
                        $other_discount += (abs($cover['typ:od']));
                    }
                }
            }
        }
    }
    $applicable_addons = $add_ons_data = [];
    $all_addons = [
        'zero_depreciation' => $zero_dep_amount,
        'road_side_assistance' => $rsa,
        'engine_protector' => $engine_protector,
        'ncb_protection' => 0, // Bajaj doesn't provide NCB
        'key_replace' => $key_replacement,
        'consumables' => $consExps,
        'tyre_secure' => 0, // Bajaj doesn't provide Tyre Secure
        'return_to_invoice' => 0, // Bajaj doesn't provide RTI
        'lopb' => $lossbaggage,
        'Accident_shield' => $accident_shield,
        'Conveyance_Benefit' => $conynBenef,
    ];
    switch ($masterProduct->product_identifier) {
        case 'DRIVE_ASSURE_PACK_PLUS':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'key_replace' => $key_replacement,
                    'road_side_assistance' => $rsa,
                    'lopb' => $lossbaggage,
                ],
                'additional' => [
                    // 'zero_depreciation' => 0,
                    // 'road_side_assistance' => 0,
                    // 'engine_protector' => 0,
                    // 'ncb_protection' => 0,
                    // 'key_replace' => 0,
                    // 'consumables' => 0,
                    // 'tyre_secure' => 0,
                    // 'return_to_invoice' => 0,
                    // 'lopb' => 0,
                ],
                'other' => [],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb");
            break;

        case 'DRIVE_ASSURE_PACK':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'road_side_assistance' => $rsa,
                ],
                'additional' => [
                    // 'zero_depreciation' => 0,
                    // 'road_side_assistance' => 0,
                    // 'engine_protector' => 0,
                    // 'ncb_protection' => 0,
                    // 'key_replace' => 0,
                    // 'consumables' => 0,
                    // 'tyre_secure' => 0,
                    // 'return_to_invoice' => 0,
                    // 'lopb' => 0,
                ],
                'other' => [],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "roadSideAssistance");
            break;
        case 'TELEMATICS_PREMIUM':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                    'lopb' => $lossbaggage,
                ],
                'additional' => [
                    // 'zero_depreciation' => 0,
                    // 'road_side_assistance' => 0,
                    // 'engine_protector' => 0,
                    // 'ncb_protection' => 0,
                    // 'key_replace' => 0,
                    // 'consumables' => 0,
                    // 'tyre_secure' => 0,
                    // 'return_to_invoice' => 0,
                    // 'lopb' => 0,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                ],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb");
            break;
        case 'TELEMATICS_PRESTIGE':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                    'lopb' => $lossbaggage,
                    'consumables' => $consExps,
                ],
                'additional' => [
                    // 'zero_depreciation' => 0,
                    // 'road_side_assistance' => 0,
                    // 'engine_protector' => 0,
                    // 'ncb_protection' => 0,
                    // 'key_replace' => 0,
                    // 'consumables' => 0,
                    // 'tyre_secure' => 0,
                    // 'return_to_invoice' => 0,
                    // 'lopb' => 0,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                    'Conveyance_Benefit' => $conynBenef,
                ],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb", "consumables");
            break;
        case 'TELEMATICS_CLASSIC':
            $add_ons_data = [
                'in_built' => [
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                    'lopb' => $lossbaggage,
                ],
                'additional' => [
                    // 'zero_depreciation' => 0,
                    // 'road_side_assistance' => 0,
                    // 'engine_protector' => 0,
                    // 'ncb_protection' => 0,
                    // 'key_replace' => 0,
                    // 'consumables' => 0,
                    // 'tyre_secure' => 0,
                    // 'return_to_invoice' => 0,
                    // 'lopb' => 0,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                ],
            ];
            array_push($applicable_addons, "keyReplace", "roadSideAssistance", "lopb");
            break;

        case 'Prime':
            $add_ons_data = [
                'in_built' => [
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                ],
                'additional' => [
                    // 'zero_depreciation' => 0,
                    // 'road_side_assistance' => 0,
                    // 'engine_protector' => 0,
                    // 'ncb_protection' => 0,
                    // 'key_replace' => 0,
                    // 'consumables' => 0,
                    // 'tyre_secure' => 0,
                    // 'return_to_invoice' => 0,
                    // 'lopb' => 0,
                ],
                'other' => [],
            ];
                array_push($applicable_addons, "keyReplace", "roadSideAssistance");
            break;

        case 'BASIC_ADDON':
            $add_ons_data = [
                'in_built' => [],
                'additional' => [
                    'road_side_assistance' => $rsa,
                    'engine_protector' => $engine_protector,
                    'ncb_protection' => 0, // Bajaj doesn't provide NCB
                    'key_replace' => $key_replacement,
                    'consumables' => $consExps,
                    'tyre_secure' => 0, // Bajaj doesn't provide Tyre Secure
                    'return_to_invoice' => 0, // Bajaj doesn't provide RTI
                    'lopb' => $lossbaggage,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                    'Conveyance_Benefit' => $conynBenef,
                ],
            ];
            array_push($applicable_addons, "keyReplace", "roadSideAssistance", "engineProtector", "lopb", "consumables");
            break;
        default:
            $add_ons_data['in_built'] = $add_ons_data['additional'] = [];
    }

    // if(config('constants.brokerName') != 'TMIBASL')
    // {
    //     if (in_array(0, array_values($add_ons_data['in_built']))) {
    //         return  [
    //             'premium_amount' => 0,
    //             'status' => false,
    //             'message' => "Some Addon package value amount is zero",
    //             'request' => [
    //                 'addons_data' => $add_ons_data,
    //             ]
    //         ];
    //     }
    // }

    // package addons which amount is zero will be shown as in-built

    // $applicable_addons = [
    //     "zeroDepreciation",
    //     "roadSideAssistance",
    //     "engineProtector",
    //     "consumables",
    //     "lopb",
    //     "keyReplace"
    // ];
    // if ($car_age > 5)
    // {
    //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
    //     array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
    //     array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
    // }
    // if ($car_age > 6)
    // {
    //     array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
    // }
    // if ($car_age > 9)
    // {
    //     array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
    //     array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
    // }

    // $applicable_addons

    /* foreach ($all_addons as $key => $value) {
        if($value > 0){
            $applicable_addons[] = Str::camel($key);
        }
    } */
    
    $add_ons_data['in_built_premium'] = !empty($add_ons_data['in_built']) ? array_sum($add_ons_data['in_built']) : 0;
    $add_ons_data['additional_premium'] = !empty($add_ons_data['in_built']) ? array_sum($add_ons_data['additional']) : 0;
    $add_ons_data['other_premium'] = !empty($add_ons_data['in_built']) ? array_sum($add_ons_data['other']) : 0;
    $ExtraPremiumForRejectedRTO = is_array($service_response['pDetariffObj_inout']['typ:extCol22']) ? 0 : $service_response['pDetariffObj_inout']['typ:extCol22'];

    $tppd = $tppd + $restricted_tppd;
    $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng; //A
    $uw_loading_amount += $ExtraPremiumForRejectedRTO;
    $totalTP = $tppd + $pa_unnamed + $ll_paid_driver + $lpg_cng_tp; //B
    $totalDiscount = $ncb_discount + $other_discount + $voluntary_deductible + $restricted_tppd + $antitheft_discount_amount; //C
    $totalBasePremium = $final_od_premium + $totalTP - $totalDiscount; //A + B - C
    $final_gst_amount = $totalBasePremium * 0.18;
    $final_payable_amount = $totalBasePremium * (1.18);
    $data_response = [
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'status' => true,
        'msg' => 'Found',
        'premium_type' => $premium_type,
        'Data' => [
            'idv' => (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? 0: ($idv),
            'min_idv' => (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? 0: ($idv_min),
            'max_idv' => (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? 0: ($idv_max),
            'vehicle_idv' => (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? 0: ($idv),
            'qdata' => null,
            'pp_enddate' => $requestData->previous_policy_expiry_date,
            'addonCover' => null,
            'addon_cover_data_get' => '',
            'rto_decline' => null,
            'rto_decline_number' => null,
            'mmv_decline' => null,
            'mmv_decline_name' => null,
            'policy_type' => in_array($premium_type, ['third_party', 'third_party_breakin']) ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),//$tp_only == 'true' ? 'Third Party' : 'Comprehensive',
            'cover_type' => '1YC',
            'hypothecation' => '',
            'hypothecation_name' => '',
            'vehicle_registration_no' => $rto_code,
            'rto_no' => $rto_code,
            'version_id' => $requestData->version_id,
            'selected_addon' => [],
            'showroom_price' => 0,
            'fuel_type' => $requestData->fuel_type,
            'ncb_discount' => $requestData->applicable_ncb,
            'company_name' => $productData->company_name,
            'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
            'product_name' => $productData->product_sub_type_name . ' - ' . ucwords(str_replace('_', ' ', strtolower($masterProduct->product_identifier))),
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
                'ic_vehicle_discount' => (abs($other_discount)),
            ],
            'ic_vehicle_discount' => (abs($other_discount)),
            'basic_premium' => ($basic_od),
            'motor_electric_accessories_value' => ($electrical_accessories),
            'motor_non_electric_accessories_value' => ($non_electrical_accessories),
            // 'motor_lpg_cng_kit_value' => ($lpg_cng),
            'total_accessories_amount(net_od_premium)' => ($electrical_accessories + $non_electrical_accessories + $lpg_cng),
            'total_own_damage' => ($basic_od),
            'tppd_premium_amount' => ($tppd),
            'compulsory_pa_own_driver' => ($pa_owner), // Not added in Total TP Premium
            'cover_unnamed_passenger_value' => $pa_unnamed,
            'default_paid_driver' => $ll_paid_driver,
            'motor_additional_paid_driver' => '', //($pa_paid_driver),
            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
            // 'cng_lpg_tp' => ($lpg_cng_tp),
            'seating_capacity' => $mmv_data->carrying_capacity,
            'deduction_of_ncb' => (abs($ncb_discount)),
            'antitheft_discount' => $antitheft_discount_amount, //(abs($anti_theft)),
            'aai_discount' => '', //$automobile_association,
            'voluntary_excess' => $voluntary_deductible,
            'other_discount' => (abs($other_discount)),
            'total_liability_premium' => ($totalTP),
            'net_premium' => ($totalBasePremium),
            'service_tax_amount' => ($final_gst_amount),
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            // 'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => ($final_payable_amount),
            'service_data_responseerr_msg' => 'success',
            'user_id' => $requestData->user_id,
            'product_sub_type_id' => $productData->product_sub_type_id,
            'user_product_journey_id' => $requestData->user_product_journey_id,
            'business_type' => $requestData->business_type == 'newbusiness' ?  'New Business' : $requestData->business_type,
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
            'add_ons_data' => $add_ons_data,
            /* 'add_ons_data' => [
            'in_built' => [],
            'additional' => [
            'zero_depreciation' => ($zero_dep_amount),
            'road_side_assistance' => ($rsapremium),
            'imt23' => ($imt_23),
            ],
            'in_built_premium' => 0,
            'additional_premium' => array_sum([$zero_dep_amount, $rsapremium, $imt_23]),
            'other_premium' => 0,
            ], */
            'applicable_addons' => $applicable_addons,
            'final_od_premium' => ($final_od_premium),
            'final_tp_premium' => ($totalTP),
            'final_total_discount' => (abs($totalDiscount)),
            'final_net_premium' => ($totalBasePremium),
            'final_gst_amount' => ($final_gst_amount),
            'final_payable_amount' => ($final_payable_amount),
            'underwriting_loading_amount'=> $uw_loading_amount,
            'mmv_detail' => [
                'manf_name' => $mmv_data->vehicle_make,
                'model_name' => $mmv_data->vehicle_model,
                'version_name' => $mmv_data->vehicle_subtype,
                'fuel_type' => $mmv_data->fuel,
                'seating_capacity' => $mmv_data->carrying_capacity,
                'carrying_capacity' => $mmv_data->carrying_capacity,
                'cubic_capacity' => $mmv_data->cubic_capacity,
                'gross_vehicle_weight' => '',
                'vehicle_type' => $mmv_data->vehicle_type,
            ],
            'product_identifier' => $masterProduct->product_identifier,
            'transaction_id' => $transaction_id
        ],
    ];
    if($is_lpg_cng)
    {
        $data_response['Data']['cng_lpg_tp'] = ($lpg_cng_tp);
        $data_response['Data']['motor_lpg_cng_kit_value'] = ($lpg_cng);
        $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
    }
    if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
    {
        // unset($data_response['Data']['compulsory_pa_own_driver']);
        $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
    }
    if(!empty($lpg_cng_tp))
    {
        $data_response['Data']['cng_lpg_tp'] = ($lpg_cng_tp);
    }
    if(!empty($lpg_cng))
    {
        $data_response['Data']['motor_lpg_cng_kit_value'] = ($lpg_cng);
    }
    if($is_tppd_discount)
    {
        $data_response['Data']['tppd_discount'] = ($restricted_tppd);
    }
    if(isset($cpa_tenure)&&$requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
    {
        $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
    }               
    return camelCase($data_response);
}



function bajajPosDataFetched($requestData, $tp_only)

{
       
    $is_pos_disable_in_quote = config('constants.motorConstant.IS_BAJAJ_POS_DISABLED_QOUTE');
        
    // $is_pos_disable_in_quote = config('constants.motorConstant.IS_BAJAJ_POS_DISABLED_QOUTE');
    $is_pos_enabled = (($is_pos_disable_in_quote == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED'));
    $extCol40 = '';

    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
    ->where('seller_type', 'P')
    ->first();


    $pUserId = config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME");
    $bajaj_new_tp_url = config("IC.BAJAJ_ALLIANZ.V1.CAR.NEW_TP_URL_ENABLE");

    if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
        $pUserId = config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_TP");
    }
    $nonPospUserId = $pUserId;

    if ($is_pos_enabled == 'Y') {
        if (isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            $extCol40 = $pos_data->pan_no;
            $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS");
        }
        if (config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
            $extCol40 = 'DNPPS5548E';
            $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS");
        }
    }

    return (object)[
            'is_pos_disable_in_quote' => $is_pos_disable_in_quote,
            'is_pos_enabled' => $is_pos_enabled,
            'extCol40' => $extCol40,
            'pUserId' => $pUserId,
            'bajaj_new_tp_url' => $bajaj_new_tp_url,
            'pos_data' => $pos_data,
            'nonPospUserId' => $pUserId,
        ];
}
}