<?php

use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function shortTermPremium($enquiryId, $requestData, $productData, $rto_arr, $mmv_data, $city_name)
{

    // $mmv_data = DB::table('iffco_tokio_pcv_short_term_mmv_master AS itstmmv')
    // ->where('itstmmv.make_code', $mmv_data['make_code'])
    // ->first();


    // echo '<pre>'; print_r([$mmv_data, $mmv_data]); echo '</pre';die();

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $car_age = $interval->y;

    $premium_type = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();

    $vehicle_in_90_days = 'N';
    if ($car_age > 90) {
        $vehicle_in_90_days = 'Y';
    }

    $is_zero_dep_product    = (($productData->zero_dep == '0') ? true : false);

    // if ($car_age > 6 && $is_zero_dep_product) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero dep is not allowed for vehicle age greater than 6 years',
    //         'request' => [
    //             'productData' => $productData,
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 6 years',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }

    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $is_electrical = $is_non_electrical = $is_lpg_cng = false;
    $electrical_si = $non_electrical_si = $lpg_cng_si = 0;

    foreach ($accessories as $key => $value)
    {
        if (in_array('Electrical Accessories', $value))
        {
            $is_electrical = true;
            $electrical_si = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value))
        {
            $is_non_electrical = true;
            $non_electrical_si = $value['sumInsured'];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value))
        {
            $is_lpg_cng = true;
            $lpg_cng_si = $value['sumInsured'];
        }
    }

    $is_paid_driver = $is_pa_unnamed = $is_ll_paid = false;
    $paid_driver_si = $pa_unnamed_si = $ll_paid_si = 0;

    foreach ($additional_covers as $key => $value)
    {
        if (in_array('PA cover for additional paid driver', $value))
        {
            $is_paid_driver = true;
            $paid_driver_si = $value['sumInsured'];
        }

        if (in_array('Unnamed Passenger PA Cover', $value))
        {
            $is_pa_unnamed = true;
            $pa_unnamed_si = $value['sumInsured'];
        }

        if (in_array('LL paid driver', $value))
        {
            $is_ll_paid = true;
            $ll_paid_si = $value['sumInsured'];
        }
    }

    $is_pa_unnamed = false;
    $pa_unnamed_si = 0;

    $is_anti_theft = $is_voluntary_access = $is_tppd = false;
    $voluntary_excess_si = 0;

    foreach ($discounts as $key => $data)
    {
        if ($data['name'] == 'anti-theft device')
        {
            $is_anti_theft = true;
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured']))
        {
            $is_voluntary_access = 'Y';
            $voluntary_excess_si = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover')
        {
            $is_tppd = 'Y';
        }
    }
    // End Addons And Accessories

    $rto_code = $requestData->rto_code;
    
    $rto_data = (object)[
        'state_code' => $rto_arr->state_code,
        'city_code' => $rto_arr->rto_city_code,
        'city_name' => $rto_arr->rto_city_name,

        'state_short_code' => explode('-',$rto_code)[0],
    ];

    $rto_city_data = DB::table('iffco_tokio_rto_city_master')
    ->where('rto_city_name', strtoupper($rto_arr->rto_city_code))
    ->first();

    if(!empty($rto_city_data))
    {
        if(empty($rto_city_data->display_name ?? ''))
        {
            return [
                'status' => false,
                'message' => 'RTO City name is not matched',
                'request' => [
                    'message' => 'RTO City name is not matched',
                    'requestData' => ['rto_city_data' => $rto_city_data, 'rto_data' => $rto_data]
                ]
            ];
        }
        $rto_data->city_display_name = $rto_city_data->display_name;
    }
    else
    {
        return [
            'status' => false,
            'message' => 'RTO City name is not matched',
            'request' => [
                'message' => 'RTO City name is not matched',
                'requestData' => ['rto_arr' => $rto_arr]
            ]
        ];
    }

    $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
    $is_new         = (($requestData->business_type == "newbusiness") ? true : false);

    $is_three_months    = (in_array($premium_type, ['short_term_3', 'short_term_3_breakin']) ? true : false);
    $is_six_months      = (in_array($premium_type, ['short_term_6', 'short_term_6_breakin']) ? true : false);    

    $is_breakin = (in_array($premium_type, ['short_term_3_breakin', 'short_term_6_breakin']) ? true : false);

    $is_zero_dep = null;
    $is_consumable = null;

    // if ($is_zero_dep_product) {
    //     $is_zero_dep = 'Y';
    //     $is_consumable = 'Y';
    // }
    $is_zero_dep = (($car_age < 5) ? 'Y' : null);
    // $is_consumable = 'Y';

    $is_breakin_date = false;
    $isBreakInMorethan90days = '';

    $noPreviousPolicyData = ($requestData->previous_policy_type == 'Not sure');

    if(!$is_new)
    {
        if($requestData->previous_policy_type == 'Not sure')
        {
            $is_breakin_date = true;
            $isBreakInMorethan90days = 'Y';
        }
        else if(Carbon::parse($requestData->previous_policy_expiry_date) < Carbon::parse(date('d-m-Y')))
        {
            $is_breakin_date = true;
            if((Carbon::parse($requestData->previous_policy_expiry_date)->diffInDays(Carbon::parse(date('d-m-Y')))) > 90)
            {
                $isBreakInMorethan90days = 'Y';
            }
        }
        else{
            $is_breakin_date = false;
        }
    }
    
    if($requestData->previous_policy_type == 'Not sure')
    {
        $is_breakin_date = true;
        $isBreakInMorethan90days = 'Y';
    }

    if($is_breakin_date)
    {
        $policy_start_date = Carbon::parse(date('d-m-Y'));
    }
    else{
        $policy_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1);
    }
    $policy_end_date = Carbon::parse($policy_start_date)->addMonth($is_three_months ? 3 : 6)->subDay(1);

    $requestData->manufacture_year = '01-' . $requestData->manufacture_year; // Adding date, we get only month and year (01-2020)


    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
        $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
    } else {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }
    $vehicle_register_no = implode('', $vehicle_register_no);

    $partnerCode = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM');
    $partnerPass = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM');

    $is_lpg_cng_internal = false;
    if (in_array(TRIM(STRTOUPPER($mmv_data['fuel_type'])), ['OTHER','OTHERS'])) {
        if (in_array(TRIM(STRTOUPPER($requestData->fuel_type)), ['CNG', 'LPG', 'BIFUEL'])) {
            $is_lpg_cng_internal = true;
        }
    }
    else if(in_array(TRIM(STRTOUPPER($mmv_data['fuel_type'])), ['CNG', 'LPG', 'BIFUEL']))
    {
        $is_lpg_cng_internal = true;
    } 
    if(in_array(TRIM(STRTOUPPER($requestData->fuel_type)), ['CNG', 'LPG', 'BIFUEL']) && config('constants.motorConstant.SMS_FOLDER') == 'ace')
    {
        $is_lpg_cng_internal = true;
    }

    // echo '<pre>'; print_r([$mmv_data, $mmv_data]); echo '</pre';die();
    $quoteServiceRequest = [
        "uniqueReferenceNo" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM') . time() . rand(10, 99), // Unique Number everytime
        "contractType" => "CVI",
        "partnerDetail" => [
            "partnerCode" => $partnerCode,
            "partnerBranch" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_BRANCH_SHORT_TERM'),
            "partnerSubBranch" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_SUB_BRANCH_SHORT_TERM'),
            "responseURL" =>route('cv.payment-confirm',['iffco_tokio']),
        ],
        "commercialVehicle" => [
            "commercialMakeWrapper" => 
            [
                "makeCode" => $mmv_data['make_code'],
                // "makeCode" => $mmv_data['model_code'],
                // "makeCodeName" => $mmv_data['make_code_name'],
                // "vehicleClass" => $mmv_data['class'],
                // "vehicleSubClass" => $mmv_data['sub_class'],
                // "vehicleSubClassName" => $mmv_data['sub_class_name'],
                // "manufacturer" => $mmv_data['manufacturer'],
                // "model" => $mmv_data['model'],
                // "variant" => $mmv_data['variant'],
                // "cc" => round($mmv_data['cc']),
                // "seatingCapacity" => round($mmv_data['seating_capacity']),
                // "fuelType" => $mmv_data['fuel_type'],
            ],

            "policyType" => "CP", // Hardcoded for now [ACT_ONLY, COMPREHENSIVE, COMPREHENSIVE_WITH_ZERO_DEP, CP]
            "contractType" => "CVI",
            "insuranceType" => "PARTNER_RENEWAL",

            "corporateClient" => ($is_individual ? 'N' : 'Y'),
            // "breakInMorethan90days" => $isBreakInMorethan90days,

            "stateCode" => $rto_data->state_code,
            "stateName" => $rto_data->state_code,
            "cityCode" => $rto_data->city_code,
            "cityName" => $rto_data->city_name,
            "cityDisplayName" => $rto_data->city_display_name,

            "dateOfFirstRegistration" => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
            "monthAndYearOfRegistartion" => date('m/Y', strtotime($requestData->vehicle_register_date)),
            "yearOfMake" => date('Y', strtotime($requestData->manufacture_year)),

            "inceptionDate" => $policy_start_date->format('d/m/Y'),
            "expirationDate" => $policy_end_date->format('d/m/Y'),

            "registrationNo" => $vehicle_register_no,

            "noClaimBonus" => (int)$requestData->applicable_ncb,

            "towingAndRelated" => null,
            "paValueAutoInsuredPersons" => null,
            "towingAndRelatedLimit" => null,
            "paValueAutoInsuredPersonsLimit" => null,
            "towing" => null,
            "otherAccessories" => null,
            "otherAccessoriesValue" => null,

            // ADDONS
            "consumable" => $is_consumable,
            "depreciationWaiver" => $is_zero_dep,
            "zeroDep" => null,
            // END ADDONS

            // ELECTRICAL ACCESSORIES
            "electricalAccessories" => ($is_electrical ? 'Y' : 'N'),
            "electricalAccessoriesValue" => $electrical_si,
            // END ELECTRICAL ACCESSORIES

            // LPG CNG
            "cngLpg" => ($is_lpg_cng ? 'Y' : null),
            "cngLpgFitted" => ($is_lpg_cng_internal ? 'Y' : null),
            "cngLpgValue" => $lpg_cng_si,

            "vehicleDrivenByCngLpg" => null,// ($is_lpg_cng ? 'Y' : 'N'),
            "companyFittedCngLpg" => null,// ($is_lpg_cng ? 'Y' : 'N'),
            "valueOfCngLpgKit" => null,// $lpg_cng_si,
            // END LPG CNG

            // CPA
            "paOwnerDriver" => $is_individual ? 'Y' : 'N',
            "paValueAutoOwnerDriver" => null,
            // END CPA

            // PA TO PAID DRIVER                
            // "insurePaidDriver" => ($is_paid_driver ? 'Y' : null),
            'paPaidDriver' => $paid_driver_si,
            // END PA TO PAID DRIVER

            // PA TO PASSENGER
            "passangersUnderPersonnelAccidentCover" => null,
            "passangersUnderPersonnelAccidentCoverLimit" => null,

            "paToPassenger" => ($is_pa_unnamed ? 'Y' : null),
            "paToPassengerTotalMember" => null,
            "paToPassengerSumInsured" => $pa_unnamed_si,

            "imt43TotalPassenger" => null,
            "nonFarePayingPaxTotalPassenger" => null,
            // END PA TO PASSENGER

            // LEGAL LIABILITY
            "llPaidDriverCleanerConductor" => ($is_ll_paid ? 'Y' : null),
            "llPaidDriverCleanerConductorTotalPassenger" => "1",
            // END LEGAL LIABILITY

            "imt23" => null,
            "imt34" => null,
            "imt36" => null,
            "imt42" => null,
            "imt43" => null,
            "imt44" => null,
            "nonFarePayingPax" => null,

            "monthOfRegistration" => date('m', strtotime($requestData->vehicle_register_date)),
            "yearOfRegistration" => date('Y', strtotime($requestData->vehicle_register_date)),

            "engineNo" => "TEST7182724",
            "chasisNo" => "TESTLMG1S00495657",

            "previousPolicyNo" => (($is_new || $noPreviousPolicyData) ? "" : 'DFSOJF234534'),
            "previousInsurer" => (($is_new || $noPreviousPolicyData) ? "" : 'Acko General Insurance'),
            "previousPolicyEndDate" => ($noPreviousPolicyData ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y')),
            "previousPolicyExpiryDate" => ($noPreviousPolicyData ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y')),

            "defaultIDV" => "1",
            // "vehicleName" => "",
        ],
    ];

    if($isBreakInMorethan90days == 'Y')
    {
        $quoteServiceRequest["commercialVehicle"]["breakInMorethan90days"] = $isBreakInMorethan90days;
    }

    if($is_breakin) {
        $quoteServiceRequest["commercialVehicle"]["inspectionAgency"] = 'LiveChek';
        $quoteServiceRequest["commercialVehicle"]["inspectionDate"] = date('d/m/Y');
        $quoteServiceRequest["commercialVehicle"]["inspectionNo"] = 'test' . time() . rand(10, 99);
        $quoteServiceRequest["commercialVehicle"]["inspectionStatus"] = 'APPROVED';
        
        // $quoteServiceRequest["commercialVehicle"]["inspectionDate"] = '15/03/2022';
        // $quoteServiceRequest["commercialVehicle"]["inspectionNo"] = '1659697418';

        // "inspectionNo": "1659697418",
        // "inspectionAgency": "LiveChek",
        // "inspectionStatus": "APPROVED",
        // "inspectionDate": "15/03/2022",
    }

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
            "Content-Type: application/json"
        ],
        'requestMethod' => 'post',
        'requestType' => 'JSON',
        'section' =>  'PCV',
        'method' => 'Quote Calculation - Quote',
        'productName' => $productData->product_name,
        'transaction_type' => 'quote',
    ];

    $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL_SHORT_TERM'), $quoteServiceRequest, 'iffco_tokio', $additional_data);
    $quoteServiceResponse = $get_response['response'];
    if (empty($quoteServiceResponse)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer not Reacheable',
        ];
    }

    $premium_data = json_decode($quoteServiceResponse, true);

    if ($premium_data === null || empty($premium_data)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer not Reacheable',
        ];
    }

    if (isset($premium_data['error']) && !empty($premium_data['error'])) {
        if(!is_array($premium_data['error']))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $premium_data['error'],
            ];
        }
        else if(count($premium_data['error']) > 0)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => implode(', ', array_column($premium_data['error'], 'errorMessage')),
            ];
        }
        else
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not Reacheable',
            ];
        }
    }

    $idv = round($premium_data['basicIDV']);

    $min_idv = round($premium_data['basicIDV']);
    $max_idv = round($idv * 1.3);

    if ($requestData->is_idv_changed == 'Y') {                       	
        if ($requestData->edit_idv >= $max_idv) {
            $quoteServiceRequest["commercialVehicle"]["defaultIDV"] = (string)$max_idv;
        } elseif ($requestData->edit_idv <= $min_idv) {
            $quoteServiceRequest["commercialVehicle"]["defaultIDV"] = (string)$min_idv;
        } else {
            $quoteServiceRequest["commercialVehicle"]["defaultIDV"] = (string)$requestData->edit_idv;
        }
    } else {
        $quoteServiceRequest["commercialVehicle"]["defaultIDV"] = (string)$min_idv;
    }

    $quoteServiceRequest["uniqueReferenceNo"] = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM') . time() . rand(10, 99);

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
            "Content-Type: application/json"
        ],
        'requestMethod' => 'post',
        'requestType' => 'JSON',
        'section' =>  'PCV',
        'method' => 'Quote Calculation - Quote',
        'productName' => $productData->product_name,
        'transaction_type' => 'quote',
    ];

    $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL_SHORT_TERM'), $quoteServiceRequest, 'iffco_tokio', $additional_data);
    $quoteServiceResponse = $get_response['response'];
    if (empty($quoteServiceResponse)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer not Reacheable',
        ];
    }

    $premiumData = json_decode($quoteServiceResponse, true);

    if ($premiumData === null || empty($premiumData)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer not Reacheable',
        ];
    }

    if (isset($premiumData['error']) && !empty($premiumData['error'])) {
        if(!is_array($premiumData['error']))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $premiumData['error'],
            ];
        }
        else if(count($premiumData['error']) > 0)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => implode(', ', array_column($premiumData['error'], 'errorMessage')),
            ];
        }
        else
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not Reacheable',
            ];
        }
    }

    $idv = round($premiumData['basicIDV']);

    $odPremium = $nonElectricalPremium = $electricalPremium = $cngOdPremium = $totalOdPremium = 0;
    $tpPremium = $legalLiabilityToDriver = $paUnnamed = $cngTpPremium = $totalTpPremium = 0;
    $ncbAmount = $aaiDiscount = $antiTheft = $voluntaryDeductible = $otherDiscount = $tppdDiscount = $totalDiscountPremium = 0;

    $odPremium = isset($premiumData['basicOD']) ? round(abs($premiumData['basicOD'])) : 0;
    $electricalPremium = isset($premiumData['electricalOD']) ? round(abs($premiumData['electricalOD'])) : 0;
    $cngOdPremium = isset($premiumData['cngOD']) ? round(abs($premiumData['cngOD'])) : 0;

    $totalOdPremium = $odPremium + $nonElectricalPremium + $electricalPremium + $cngOdPremium;

    $tpPremium = isset($premiumData['basicTP']) ? round(abs($premiumData['basicTP'])) : 0;
    $legalLiabilityToDriver = isset($premiumData['llDriverTP']) ? round(abs($premiumData['llDriverTP'])) : 0;
    $paUnnamed = isset($premiumData['paPassengerTP']) ? round(abs($premiumData['paPassengerTP'])) : 0;
    $cngTpPremium = isset($premiumData['cngTP']) ? round(abs($premiumData['cngTP'])) : 0;

    $totalTpPremium = $tpPremium + $legalLiabilityToDriver + $paUnnamed + $cngTpPremium;

    $paOwnerDriver = isset($premiumData['paOwnerDriverTP']) ? round(abs($premiumData['paOwnerDriverTP'])) : 0;

    $ncbAmount = isset($premiumData['ncb']) ? round(abs($premiumData['ncb'])) : 0;

    $antiTheft = isset($premiumData['antiTheftDisc']) ? round(abs($premiumData['antiTheftDisc'])) : 0;
    $voluntaryDeductible = isset($premiumData['voluntaryExcessDisc']) ? round(abs($premiumData['voluntaryExcessDisc'])) : 0;

    $tppdDiscount = isset($premiumData['tppdDiscount']) ? round(abs($premiumData['tppdDiscount'])) : 0;

    $otherDiscount = isset($premiumData['premiumDiscount']) ? round(abs($premiumData['premiumDiscount'])) : 0;

    $totalDiscountPremium = $ncbAmount + $aaiDiscount + $antiTheft + $voluntaryDeductible + $otherDiscount + $tppdDiscount;

    $totalBasePremium = $totalOdPremium + $totalTpPremium - $totalDiscountPremium;

    $serviceTax = round(abs($totalBasePremium * 18/100));
    $totalPayableAmount = round(abs($totalBasePremium * (1 + (18/100))));


    $zero_dep_amount = isset($premiumData['nilDep']) ? round(abs($premiumData['nilDep'])) : 0;
    $consumable_amount = isset($premiumData['consumablePrem']) ? round(abs($premiumData['consumablePrem'])) : 0;

    // if ($is_zero_dep_product) {
    //     $add_ons_data = [
    //         'in_built' => [
    //             'zero_depreciation' => round($zero_dep_amount),
    //         ],
    //         'additional' => [
    //             // 'consumables' => round($consumable_amount),
    //         ],
    //         'other' => [],
    //     ];
    // } else {
    //     $add_ons_data = [
    //         'in_built' => [],
    //         'additional' => [],
    //         'other' => [],
    //     ];
    // }
    
    $add_ons_data = [
        'in_built' => [
            // 'consumables' => round($consumable_amount)
        ],
        'additional' => [
            'zero_depreciation' => round($zero_dep_amount),
        ],
        'other' => [],
    ];

    $applicable_addons = ['zeroDepreciation', 'consumables'];

    $data_response = [
        'status' => true,
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'product_name' => $productData->product_name,
        'msg' => 'Found',
        'Data' => [
            'idv' => $idv, //$idv,
            'min_idv' => $min_idv, //$min_idv,
            'max_idv' => $max_idv, //$max_idv,
            'vehicle_idv' => 0, //$min_idv,
            'premiumTypeCode' => $premium_type,
            'qdata' => null,
            'pp_enddate' => $requestData->previous_policy_expiry_date,
            'addonCover' => null,
            'addon_cover_data_get' => '',
            'rto_decline' => null,
            'rto_decline_number' => null,
            'mmv_decline' => null,
            'mmv_decline_name' => null,
            'policy_type' => 'Short Term',
            'business_type' => $is_new ? 'New Business' : 'Rollover',
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
            'product_name' => $productData->product_name,
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
                'ic_vehicle_discount' => $otherDiscount,
            ],
            'ic_vehicle_discount' => $otherDiscount,
            'basic_premium' => $odPremium,
            'motor_electric_accessories_value' => $electricalPremium,
            'motor_non_electric_accessories_value' => $nonElectricalPremium,
            'motor_lpg_cng_kit_value' => $cngOdPremium,
            'total_accessories_amount(net_od_premium)' => 0,
            'total_own_damage' => $totalOdPremium,
            'tppd_premium_amount' => $tpPremium,
            'compulsory_pa_own_driver' => $paOwnerDriver, // Not added in Total TP Premium
            'cover_unnamed_passenger_value' => $paUnnamed, //$paUnnamed,
            'default_paid_driver' => $legalLiabilityToDriver,
            'll_paid_driver_premium' => $legalLiabilityToDriver,
            'll_paid_conductor_premium' => 0,
            'll_paid_cleaner_premium' => 0,
            'motor_additional_paid_driver' => 0,
            'cng_lpg_tp' => $cngTpPremium,
            'seating_capacity' => $mmv_data['seating_capacity'],
            'deduction_of_ncb' => $ncbAmount,
            'antitheft_discount' => $antiTheft,
            'aai_discount' =>  $aaiDiscount, //$automobile_association,
            'voluntary_excess' => $voluntaryDeductible, //$voluntary_excess,
            'other_discount' => $otherDiscount,
            'total_liability_premium' => $tpPremium,
            'tppd_discount' => $tppdDiscount,
            'net_premium' => $totalBasePremium,
            'service_tax_amount' => $serviceTax,
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => $totalPayableAmount,
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
            'add_ons_data' => $add_ons_data,
            'applicable_addons' => $applicable_addons,
            'final_od_premium' => $totalOdPremium,
            'final_tp_premium' => $totalTpPremium,
            'final_total_discount' => $totalDiscountPremium,
            'final_net_premium' => $totalBasePremium,
            'final_gst_amount' => $serviceTax,
            'final_payable_amount' => $totalPayableAmount,
            'mmv_detail' => [
                'manf_name' => $mmv_data['manufacturer'],
                'model_name' => $mmv_data['model'],
                'version_name' => $mmv_data['variant'],
                'fuel_type' => $mmv_data['fuel_type'],
                'seating_capacity' => $mmv_data['seating_capacity'],
                'carrying_capacity' => $mmv_data['seating_capacity'],
                'cubic_capacity' => $mmv_data['cc'],
                'gross_vehicle_weight' => '',
                'vehicle_type' => 'Taxi',
            ],
        ],
        'premiumData' => $premiumData
    ];
    return camelCase($data_response);
}
