<?php

use App\Models\MasterRto;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\Loss_of_personal_belonging_si_values;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData) {
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
    $mmv = get_mmv_details($productData, $requestData->version_id, 'oriental');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return [
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
                'mmv' => $mmv_data,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv_data,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    } else {
        $premium_type = DB::table('master_premium_type')
                ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }

        $is_liability   = (($premium_type == 'third_party') ? true : false);
        $is_od          = (($premium_type == 'own_damage') ? true : false);

        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
        $is_zero_dep        = ($productData->zero_dep  == 0) ? true : false;
        
        /* $mmv = DB::table('oriental_vehicle_master')
                ->where('vehicle_code', $mmv_data->vehicle_code)
                ->first();

        if ($mmv === null || empty($mmv))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv_data,
                    'version_id' => $requestData->version_id
                ]
            ];
        } */
        //$refer_webservice = config('ENABLE_TO_GET_DATA_FROM_WEBSERVICE_ORIENTAL_CAR') == 'Y';
        $refer_webservice = $productData->db_config['quote_db_cache'];
        $car_age = 0;
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = $interval->y;
        if ($age > 132) {
            $idv = $mmv_data->upto_12_year;
        } elseif ($age > 120) {
            $idv = $mmv_data->upto_11_year;
        } elseif ($age > 108) {
            $idv = $mmv_data->upto_10_year;
        } elseif ($age > 96) {
            $idv = $mmv_data->upto_9_year;
        } elseif ($age > 84) {
            $idv = $mmv_data->upto_8_year;
        } elseif ($age > 72) {
            $idv = $mmv_data->upto_7_year;
        } elseif ($age > 60) {
            $idv = $mmv_data->upto_6_year;
        } elseif ($age > 48) {
            $idv = $mmv_data->upto_5_year;
        } elseif ($age > 36) {
            $idv = $mmv_data->upto_4_year;
        } elseif ($age > 24) {
            $idv = $mmv_data->upto_3_year;
        } elseif ($age > 12) {
            $idv = $mmv_data->upto_2_year;
        } elseif ($age > 6) {
            $idv = $mmv_data->upto_1_year;
        } else {
            $idv = $mmv_data->upto_6_months;
        }
        // zero depriciation validation
        if ($car_age >= 6 && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('CAR_AGE_VALIDASTRION_ALLOWED_IC')))) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed for vehicle age greater than 6 years',
                'request' => [
                    'car_age' => $car_age,
                    'message' => 'Zero dep is not allowed for vehicle age greater than 6 years',
                ]
            ];
        }
        if (
            (
                ($interval->y == 4 && $interval->m >= 6) ||
                ($interval->y > 4 && $interval->y < 6) ||
                ($interval->y == 6 && $interval->m <= 0 && $interval->d == 0)
            ) &&
            $requestData->applicable_ncb < 35 &&
            $productData->zero_dep == '0' &&
            $is_new == false
        ) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed for vehicle age between 4.6 and 6 years with NCB less than 35%',
                'request' => [
                    'car_age' => $car_age,
                    'message' => 'Zero dep is not allowed for vehicle age between 4.6 and 6 years with NCB less than 35%',
                ]
            ];
        }
        $rto_code = $requestData->rto_code;
        // Re-arrange for Delhi RTO code - start 
        $rto_code = explode('-', $rto_code);
        if ((int) $rto_code[1] < 10) {
            $rto_code[1] = '0' . (int) $rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        // Re-arrange for Delhi RTO code - End
        $rto_data = (object)[];
        $rto_data->rto_number = $rto_code;
        $rto_location = DB::table('oriental_rto_master')
                ->where('rto_code', 'like', '%' . $rto_data->rto_number . '%')
                ->first();
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
        $mmv_data->zone = $rto_location->rto_zone;
        // $mmv_data->manf_code = $mmv->veh_manf_code;
        $vehicle_in_90_days = 'N';
        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = 'New Vehicle';
            $Registration_Number = 'NEW';
            $policy_start_date = date('d-M-Y');
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');

            $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousPolicyType = $previous_ncb = '';
            $break_in = 'NO';
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
            $soapAction = "GetQuoteMotor";
            $engine_number = config('CAR_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('CAR_QUOTE_CHASSIS_NUMBER');
        } else {
            if($requestData->previous_policy_type == 'Not sure' || strtoupper($requestData->previous_policy_expiry_date) == 'NEW')
            {
                $policy_start_date = date('d-M-Y');
            }
            else
            {
                $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= Carbon::parse(date('d-m-Y'))) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1)->format('d-M-Y') : date('d-M-Y');
            }
            if($is_od && !(Carbon::parse($requestData->previous_policy_expiry_date) >= Carbon::parse(date('d-m-Y'))))
            {
                $policy_start_date = Carbon::parse(date('d-M-Y'))->addDay(1)->format('d-M-Y');
            }

            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);

            $BusinessType = 'Roll Over';
            if($requestData->previous_policy_type == 'Not sure' || strtoupper($requestData->previous_policy_expiry_date) == 'NEW')
            {
                $PreviousPolicyFromDt = '';
                $PreviousPolicyToDt = '';
            }
            else
            {
                $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                $PreviousPolicyToDt = Carbon::parse($requestData->previous_policy_expiry_date)->format('d-M-Y');             
            }
            
            $PreviousPolicyType = "MOT-PLT-001";
            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
            $previous_ncb = $NCBEligibilityCriteria == '1' ? "" : $requestData->previous_ncb;
            #for quote page engine & chassis no. fastlan validation required
            if ($requestData->vehicle_registration_no != '') {
                $Registration_Number = $requestData->vehicle_registration_no;
                $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                if(!empty($proposal->chassis_number) && !empty($proposal->engine_number))
                {
                    $engine_number = $proposal->engine_number;
                    $chassis_number = $proposal->chassis_number;
                }else{
                    $Registration_Number = config('CAR_QUOTE_REGISTRATION_NUMBER');
                    $engine_number = config('CAR_QUOTE_ENGINE_NUMBER');
                    $chassis_number = config('CAR_QUOTE_CHASSIS_NUMBER');
                }
                
            } else {
                $Registration_Number = config('CAR_QUOTE_REGISTRATION_NUMBER');
                $engine_number = config('CAR_QUOTE_ENGINE_NUMBER');
                $chassis_number = config('CAR_QUOTE_CHASSIS_NUMBER');
            }

            if (
                strlen(str_replace('-', '', $Registration_Number)) > 10 &&
                str_starts_with(strtoupper($Registration_Number), 'DL-0')
            ) {
                $Registration_Number = explode('-', $Registration_Number);
                $Registration_Number[1] = ((int) $Registration_Number[1] * 1);
                $Registration_Number = implode('-', $Registration_Number);
            }

            $break_in = "No";
            $soapAction = "GetQuoteMotor";
            //$nilDepreciationCover = ($car_age > 5) ? 0 : 1; 
        }

        $ProductCodeArray = [
            'pos' => [
                'newbusiness' => [
                    'comprehensive' => 'MOT-POS-012',
                    'third_party' => 'MOT-POS-012',

                ],
                'rollover' => [
                    'comprehensive' => 'MOT-POS-001',
                    'third_party' => 'MOT-POS-001',
                    'own_damage' => 'MOT-POS-015',
                ],
            ],
            'non_pos' => [
                'newbusiness' => [
                    'comprehensive' => 'MOT-PRD-012',
                    'third_party' => 'MOT-PRD-012',
                ],
                'rollover' => [
                    'comprehensive' => 'MOT-PRD-001',
                    'third_party' => 'MOT-PRD-001',
                    'own_damage' => 'MOT-PRD-015',
                ],
            ],
        ];

        switch ($premium_type) {
            case 'comprehensive':
                $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-012" : "MOT-PRD-001";
                $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-012" : "MOT-PLT-001";
                $policy__type = 'Comprehensive';
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? (($requestData->business_type == 'newbusiness') ? '' : 'MOT-PLT-001') : "MOT-PRD-002";
                break;
            case 'third_party':
                $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-012" : "MOT-PRD-001";
                $policy_type = 'MOT-PLT-002';
                $policy__type = 'Third Party';
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? (($requestData->business_type == 'newbusiness') ? '' : 'MOT-PLT-002') : 'MOT-PLT-002';
               break;
            case 'own_damage':
                $ProdCode = "MOT-PRD-015";
                $policy_type = 'MOT-PLT-001';
                $policy__type = 'Own Damage';
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-001';
                $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                $PreviousPolicyToDt = Carbon::parse($PreviousPolicyFromDt)->addYear(3)->subDay(1)->format('d-M-Y');
                break;
        }

        if(!$is_new && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Break-In Quotes Not Allowed'
                ];
            }
        $zero_dep = ($productData->zero_dep == '0') ? '1': '0';

        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $rsacover = $PAforUnnamedPassengerSI = $antitheft = $voluntary_insurer_discountsf = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = $LimitedTPPDYN = 0;
        //$addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $voluntary_insurer_discounts = 'PCVE1'; // Discount of 0
        $voluntary_discounts = [
            '0' => 'PCVE1', // Discount of 0
            '2500' => 'PCVE2', // Discount of 750
            '5000' => 'PCVE3', // Discount of 1500
            '7500' => 'PCVE4', // Discount of 2000
            '15000' => 'PCVE5'  // Discount of 2500
        ];
        foreach ($discounts as $key => $value) {
            if (in_array('anti-theft device', $value)) {
                $antitheft = '1';
            }
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)){
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = '1';
            }
        }
        $LLtoPaidDriverYN = '0';
        $geoExtensionCode = '';

        foreach ($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = '1';
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }

            if (in_array('Geographical Extension', $value)) {

                $countryCodes = array(
                    ['name' => 'Bangladesh', 'code' => 1],
                    ['name' => 'Bhutan', 'code' => 2],
                    ['name' => 'Nepal', 'code' => 3],
                    ['name' => 'Pakistan', 'code' => 4],
                    ['name' => 'Sri Lanka', 'code' => 5],
                    ['name' => 'Maldives', 'code' => 6],
                );

                foreach ($countryCodes as $key => $country) {
                    if (in_array($country['name'], $value['countries'])) { 
                        $geoExtensionCode = 'GEO-EXT-COD' . $country['code'];
                    }
                };
            }
        }
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = '1';
                $externalCNGKITSI = $value['sumInsured'];
            }
        }

        if($requestData->fuel_type == 'CNG' || $requestData->fuel_type == 'LPG')
        {
            $mmv_data->fuel_type = $requestData->fuel_type;
        }

        $fuelType = [
            'PETROL' => 'MFT1',
            'DIESEL' => 'MFT2',
            'CNG' => 'MFT3',
            'OCTANE' => 'MFT4',
            'LPG' => 'MFT5',
            'BATTERY POWERED - ELECTRICAL' => 'MFT6',
            'OTHERS' => 'MFT99',
            'ELECTRIC' => 'MFT6'
        ];
        $internalCNG = '';
        if(strtoupper($mmv_data->fuel_type) == 'CNG' || strtoupper($mmv_data->fuel_type) == 'LPG')
        {
            $internalCNG = '1';
        }
        $state = explode("-", $rto_data->rto_number);
        $is_pos_enabled = 'N';#config('constants.motorConstant.IS_POS_ENABLED');
        $posp_code = '';
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
    
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('oriental_code')
                    ->first();
            if(empty($pos_code) || is_null($pos_code))
            {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'POS details Not Available'
                ];
            }
            else
            {
                $posp_code = $pos_code;
            }
            $is_pos = true;
        }
        else
        {
            $is_pos = false;
        }

        $is_no_ncb = (($requestData->is_claim == 'Y') || $is_liability || in_array($requestData->previous_policy_type, ['Not sure', 'Third-party'])) ? true : false;

        $discount_grid = DB::table('oriental_non_maruti_discount_grid')
            ->where('VEH_MODEL', $mmv_data->vehicle_code)
            ->first();
        $discount_grid = keysToLower($discount_grid);
        $discount_percentage = '';
        $discount_per_array = json_decode(config("NEW_DISCOUNT_PERCENTAGE_VALUES"), 1) ?? [];
        if($mmv_data->manf_name == 'MARUTI' && $premium_type != 'third_party')
        {
            if($car_age <= 5)
            {
                 $discount_percentage = '80';
            }
            else if($car_age <= 10)
            {
                $discount_percentage = '75';
            }
            else if($car_age <= 15)
            {
                $discount_percentage = '75';
            }
        } else if (config("NEW_DISCOUNT_PERCENTAGE_CHANGES") == "Y" && !empty($discount_per_array) && $premium_type != 'third_party') {
            if ($is_new) {
                $discount_percentage = $discount_per_array["oriental"]["car"]["newbusiness"];
            } else if ($is_od) {
                $discount_percentage = $discount_per_array["oriental"]["car"]["own_damage"];
            } else {
                foreach ($discount_per_array["oriental"]["car"]['other'] as $val) {
                    if ($val['from'] < $age && $val['to'] >= $age) {
                        $discount_percentage = $val['percentage'];
                        break;
                    }
                }
            }
        }
        else if(!empty($discount_grid) && $premium_type != 'third_party')
        {
            if($car_age <= 5)
            {
                $discount_percentage = $discount_grid->disc_upto_5yrs;
            }
            else
            {
                $discount_percentage = $discount_grid->disc_5_to_10yrs;
            }
        }

        $agentDiscountSelected = false;
        $userSelectedPercentage = $discount_percentage;
        if ($premium_type != 'third_party') {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'oriental', 'car');
            if ($agentDiscount['status'] ?? false) {
                $discount_percentage = $discount_percentage >= $agentDiscount['discount'] ? $agentDiscount['discount'] : $discount_percentage;
                $agentDiscountSelected = true;
                $userSelectedPercentage = $agentDiscount['discount'];
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }

        $is_cpa = $requestData->vehicle_owner_type == 'C' ? false : true;

        $flex_22 = '';
        if (!empty($selected_addons['compulsory_personal_accident'])) {
            foreach ($selected_addons['compulsory_personal_accident'] as $key => $cpaArr)  {
                if (isset($cpaArr['name']) && $cpaArr['name'] == 'Compulsory Personal Accident')  {
                    $cpa_selected = '1';
                    $tenure = 1;
                    $tenure = isset($cpaArr['tenure'])? $cpaArr['tenure'] :$tenure;
                    if($tenure === 3 || $tenure === '3')
                    {
                        $flex_22 = '0';
                    }
                    else
                    {
                        $flex_22 = '1';
                    }
                }
            }
        }

        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
        {
            if($requestData->business_type == 'newbusiness')
            {
                $flex_22 = isset($flex_22) ? $flex_22 : '0'; 
            }
            else{
                $flex_22 = isset($flex_22) ? $flex_22 : '1';
            }
        }

        $engine_secure_age_available = true;

        $engine_secure_age_limit = strtotime(!$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 11 months - 9 year')) : date('d-m-Y'));
        if (!$is_new && $engine_secure_age_limit >= strtotime($requestData->vehicle_register_date))
        {
            $engine_secure_age_available = false;
        }
        $keyreplacement = 'N~N';
        if (!(($interval->y > 5) || ($interval->y == 5 && $interval->m > 0) || ($interval->y == 5 && $interval->m == 0 && $interval->d > 0) || ($interval->y == 5))) {
            $keyreplacement = 'Y~Y';
        }

        $idv_min = ($premium_type == 'third_party' ? 0 : ((float)$idv * 0.85));
        $idv_max = ($premium_type == 'third_party' ? 0 : ((float)$idv * 1.15));

        if($premium_type != 'third_party')
        {
            if ($requestData->is_idv_changed == 'Y') 
            {
                if ($requestData->edit_idv >= ($idv_max)) 
                {
                    $idv = ($idv_max);
                } 
                elseif ($requestData->edit_idv <= ($idv_min)) 
                {
                    $idv = ($idv_min);
                } 
                else 
                {
                    $idv = $requestData->edit_idv;
                }
            }
            else
            {
                $idv = $idv_min; 
            }
        }

        if ($mmv_data->cc <= 1000 && $idv < 15000){
            $idv = $idv_min = 15000;
        } elseif ($mmv_data->cc > 1000 && $mmv_data->cc <= 1500 && $idv < 20000)
        {
            $idv = $idv_min = 20000;
        } elseif ($mmv_data->cc > 1500 && $idv < 30000)
        {
            $idv = $idv_min = 30000;
        }
        
        $vehIdv = '';
        if ($premium_type != 'third_party') {
            $vehIdv = $idv; // + $ElectricalaccessSI + $NonElectricalaccessSI + $externalCNGKITSI;
        }
        
        $model_config_premium = [
            'soap:Body' => 
                  [
                    $soapAction => 
                    [
                      'objGetQuoteMotorETT' => 
                      [
                          'LOGIN_ID' => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_MOTOR'),//LOGIN_ID, //'POLBZR_2',
                          'DLR_INV_NO' => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_MOTOR'). '-' .customEncrypt($enquiryId),
                          'DLR_INV_DT' => $policy_start_date,
                          'PRODUCT_CODE' => $ProductCodeArray[($is_pos) ? 'pos' : 'non_pos'][$is_new ? 'newbusiness' : 'rollover'][$premium_type], //'MOT-PRD-001',
                          'POLICY_TYPE' => $policy_type,
                          'START_DATE' => $policy_start_date,
                          'END_DATE' => $policy_end_date,
                          'INSURED_NAME' => 'Test',
                          'ADDR_01' => 'ADDRESS1',
                          'ADDR_02' => 'ADDRESS2',
                          'ADDR_03' => 'ADDRESS3',
                          'CITY' => 'NASHIK', // '3810',
                          'STATE' => $state[0],
                          'PINCODE' => '422001',
                          'COUNTRY' => 'IND',
                          'EMAIL_ID' => 'test@gmail.com',
                          'MOBILE_NO' => '9999999999',
                          'TEL_NO' => '',
                          'FAX_NO' => '',
                          'ROAD_TRANSPORT_YN' => '0', //addon rsa
                          'INSURED_KYC_VERIFIED' => '',
                          'MOU_ORG_MEM_ID' => '',
                          'MOU_ORG_MEM_VALI' => '',
                          'MANUF_VEHICLE_CODE' => $mmv_data->manf_code,
                          'VEHICLE_CODE' => $mmv_data->vehicle_code, //'VEH_MAK_5267'
                          'VEHICLE_TYPE_CODE' => ($requestData->business_type== 'newbusiness') ? 'W' : 'P',
                          'VEHICLE_CLASS_CODE' => 'CLASS_2', //for nonpos PC class_2
                          'MANUF_CODE' => $mmv_data->manf_code, //need to discuss'VEH_MANF_044'
                          'VEHICLE_MODEL_CODE' => $mmv_data->vehicle_code,
                          'TYPE_OF_BODY_CODE' => 'SALOON', //for the time include in table
                          'VEHICLE_COLOR' => 'DAJJHHH',
                          'VEHICLE_REG_NUMBER' => ($requestData->business_type == 'newbusiness') ? 'NEW-1234' :$Registration_Number,
                          'FIRST_REG_DATE' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                          'ENGINE_NUMBER' => $engine_number,#'ENGINE12345',
                          'CHASSIS_NUMBER' => $chassis_number,#'CHASSIS1234567891',
                        //   'VEH_IDV' => $premium_type == 'third_party' ? '' : $idv, //isset($idv) ? $idv : ($requestData->business_type== 'newbusiness') ? $mmv_data['UPTO_3_YEAR'] : $mmv_data['UPTO_1_YEAR'],
                          'VEH_IDV' => $vehIdv, //isset($idv) ? $idv : ($requestData->business_type== 'newbusiness') ? $mmv_data['UPTO_3_YEAR'] : $mmv_data['UPTO_1_YEAR'],
                          'CUBIC_CAPACITY' => $mmv_data->cc,
                          'THREEWHEELER_YN' => 'N',
                          'SEATING_CAPACITY' => $mmv_data->seating_capacity,
                          'VEHICLE_GVW' => '',
                          'NO_OF_DRIVERS' => '1',
                          'FUEL_TYPE_CODE' => $fuelType[strtoupper($mmv_data->fuel_type)], //$mmv_data['fuel_type'],
                          'RTO_CODE' => $rto_code,
                          'ZONE_CODE' => $mmv_data->zone,
                          'GEO_EXT_CODE' => $geoExtensionCode,
                          'VOLUNTARY_EXCESS' => $voluntary_insurer_discounts,
                          'MEMBER_OF_AAI' => '0',
                          'ANTITHEFT_DEVICE_DESC' => $antitheft,
                          'NON_ELEC_ACCESS_DESC' => $NonElectricalaccess,
                          'NON_ELEC_ACCESS_VALUE' => $NonElectricalaccessSI,
                          'ELEC_ACCESS_DESC' =>  $Electricalaccess,
                          'ELEC_ACCESS_VALUE' => $ElectricalaccessSI,
                          'SIDE_CAR_ACCESS_DESC' => '',
                          'SIDE_CARS_VALUE' => '',
                          'TRAILER_DESC' => '',
                          'TRAILER_VALUE' => '',
                          'ARTI_TRAILER_DESC' => '',
                          'ARTI_TRAILER_VALUE' => '',
                          'PREV_YR_ICR' => '',
                          'NCB_DECL_SUBMIT_YN' => ($requestData->is_claim == 'Y') ? 'N': 'Y',
                          'LIMITED_TPPD_YN' => (($premium_type == 'own_damage') ? '0' : $LimitedTPPDYN),
                          'RALLY_COVER_YN' => '',
                          'RALLY_DAYS' => '',
                          'NIL_DEP_YN' => $zero_dep, //Zero DEP
                          'FIBRE_TANK_VALUE' => '0',
                          'ALT_CAR_BENEFIT' => '',
                          'PERS_EFF_COVER' => 'LPE_02', //Loss of personal belongings
                          'NO_OF_PA_OWNER_DRIVER' => $PAPaidDriverConductorCleaner, // Pa cover to owner driver
                          'NO_OF_PA_NAMED_PERSONS' => '',
                          'PA_NAMED_PERSONS_SI' => '',
                          'NO_OF_PA_UNNAMED_PERSONS' => (($PAforUnnamedPassenger === 1) ? $mmv_data->seating_capacity : ''),
                          'PA_UNNAMED_PERSONS_SI' => $PAforUnnamedPassengerSI,
                          'NO_OF_PA_UNNAMED_HIRER' => '',
                          'NO_OF_LL_EMPLOYEES' => '',
                          'NO_OF_LL_PAID_DRIVER' => $LLtoPaidDriverYN, //ll to paid driver
                          'NO_OF_LL_SOLDIERS' => '',
                          'OTH_SINGLE_FUEL_CVR' => '',
                          'IMP_CAR_WO_CUSTOMS_CVR' => '',
                          'DRIVING_TUITION_EXT_CVR' => '',
                          'NO_OF_COOLIES' => '',
                          'NO_OF_CONDUCTORS' => '',
                          'NO_OF_CLEANERS' => '',
                          'TOWING_TYPE' => '',
                          'NO_OF_TRAILERS_TOWED' => '',
                          'NO_OF_NFPP_EMPL' => '',
                          'NO_OF_NFPP_OTH_THAN_EMPL' => '',
                          'DLR_PA_NOMINEE_NAME' => 'NomineeName',
                          'DLR_PA_NOMINEE_DOB' => '01-JAN-1992',
                          'DLR_PA_NOMINEE_RELATION' => 'Brother',
                          'RETN_TO_INVOICE' => (($car_age > 2) || ($interval->y == 2 && ($interval->m > 0 || $interval->d > 0))) ? '0' : '1', //Return to invoice
                          'HYPO_TYPE' => '',
                          'HYPO_COMP_NAME' => '',
                          'HYPO_COMP_ADDR_01' => '',
                          'HYPO_COMP_ADDR_02' => '',
                          'HYPO_COMP_ADDR_03' => '',
                          'HYPO_COMP_CITY' => '',
                          'HYPO_COMP_STATE' => '',
                          'HYPO_COMP_PINCODE' => '',
                          'PAYMENT_TYPE' => 'OT',
                          'NCB_PERCENTAGE' => ($is_no_ncb ? '' : $requestData->previous_ncb),     //previous policy ncb
                          'PREV_INSU_COMPANY' => ($requestData->business_type== 'newbusiness') ? '' : 'Bajaj Allianz General Insurance Company Limited',
                          'PREV_POL_NUMBER' => ($requestData->business_type== 'newbusiness') ? '' : '2112003120186734',
                          'PREV_POL_START_DATE' => $PreviousPolicyFromDt,
                          'PREV_POL_END_DATE' => $PreviousPolicyToDt,
                          'EXIS_POL_FM_OTHER_INSR' => '0',
                          'IP_ADDRESS' => '',
                          'MAC_ADDRESS' => '',
                          'WIN_USER_ID' => '',
                          'WIN_MACHINE_ID' => '',
                          'DISCOUNT_PERC' => trim($discount_percentage),//$requestData->applicable_ncb,
                        //   'P_FLEX_19' => 'abcvdg435345',
                          'LPE_01' => '10000', //Loss of personal belongings
                          'FLEX_01' => $PAPaidDriverConductorCleanerSI,
                          'FLEX_02' => '',
                          'FLEX_03' => date('Y', strtotime('01-'.$requestData->manufacture_year)), //manf year
                          'FLEX_05' => '',
                          'FLEX_06' => '',
                          'FLEX_07' => '',
                          'FLEX_08' => '', //GSTNO
                          'FLEX_09' => '', //towing
                          'FLEX_10' => '',
                          'FLEX_19' => $posp_code,
                          'FLEX_20' => ($is_cpa ? 'N' : 'Y'),
                          'FLEX_12' => (($zero_dep == '1' && $car_age < 5) ? '20' : ''),
                        // 'FLEX_21' => (!$engine_secure_age_available) ? 'N' : 'Y', //engine protector
                          'FLEX_22' => $flex_22,
                          'FLEX_24' => (($premium_type == 'own_damage') ? ('Bajaj Allianz General Insurance Company Limited' . '~' . 2112003120186734 . '~' . $PreviousPolicyFromDt . '~' . $PreviousPolicyToDt) : '' ),
                          'FLEX_25' => $keyreplacement, //Key replacement & consumables
                          'FLEX_29' => (env('APP_ENV') == 'local') ? '1~12~~' : '',
                      ],
                    '_attributes' => [
                        "xmlns" => "http://MotorService/"
                    ],
                    ],
                ],
        ];
        if((in_array($productData->product_identifier, ['engine_protector', 'zero_dep'])) && $engine_secure_age_available)
        {
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_21'] = 'Y';
        }else{
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_21'] = 'N';
        }
                if($productData->product_identifier == 'without_addon')
        {
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_12'] = 'N';
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_21'] = 'N';
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_25'] = 'N~N';
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_29'] = (env('APP_ENV') == 'local') ? '1~12~~' : '';
        }
        if (($externalCNGKITSI != '0')) {
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['CNG_KIT_VALUE'] = $externalCNGKITSI;
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_04'] = 'Y';
        }

        if (($internalCNG == '1'))
        {
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_04'] = 'Y';
        }

        if (config('constants.motor.oriental.NO_LOPB') == 'Y')
        {
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['PERS_EFF_COVER'] = 'LPE_00';
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['LPE_01'] = '0';
        }
        //Tyre and Rim
        $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_32'] =  (($premium_type != 'third_party') && !(($interval->y > 5) || ($interval->y == 5 && $interval->m > 0) || ($interval->y == 5 && $interval->m == 0 && $interval->d > 0) || ($interval->y == 5))) ? 'MOT-SMI-018~' . $idv : '';
            $root = [
                'rootElementName' => 'soap:Envelope',
                '_attributes' => [
                    "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
                ]
            ];

            $input_array = ArrayToXml::convert($model_config_premium,$root, false,'utf-8');
            $checksum_data = checksum_encrypt($input_array);

            $additional_data = [
                'enquiryId' => $enquiryId,
                'headers' => [
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ],
                'requestMethod' => 'post',
                'requestType' => 'xml',
                'section' => 'Car',
                'method' => 'Premium Calculation',
                'product' => 'Private Car',
                'transaction_type' => 'quote',
                'productName'       => $productData->product_name,
                'checksum' => $checksum_data,
                'policy_id' => $productData->policy_id
            ];            
            $service_rehit = true;
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'oriental',$checksum_data,'CAR');
            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
            {
                $get_response = $is_data_exits_for_checksum;
            }
            else
            {
                if($is_data_exits_for_checksum['found'] && !$is_data_exits_for_checksum['status'])
                {
                    $error_codes = explode(',',trim(config('ERROR_CODE.ORIENTAL')));
                    foreach($error_codes as $code)
                    {
                        if(str_contains($is_data_exits_for_checksum['message'],$code))
                        {
                            $service_rehit = false;
                            $get_response = $is_data_exits_for_checksum;
                            break;
                        }
                    }
                }
                if($service_rehit)
                {
                    $get_response = getWsData(config('constants.motor.oriental.QUOTE_URL'), $input_array, 'oriental', $additional_data);
                }
            }
            //$get_response = getWsData(config('constants.motor.oriental.QUOTE_URL'), $input_array, 'oriental', $additional_data);

            if($get_response['response'] == '')
            {
                return  [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => 'Car Insurer Not found',
                ];
            }

           $response = XmlToArray::convert($get_response['response']);

           $quote_res_array = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];
        if (($quote_res_array['ERROR_CODE'] == '0 0')) 
        {
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success");

            // $checksum_data = checksum_encrypt($input_array);

            // $additional_data['checksum'] = $checksum_data;
            // $additional_data['method'] = 'Premium Re Calculation';
            // $input_array = ArrayToXml::convert($model_config_premium,$root, false,'utf-8');
            
            // $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'oriental',$checksum_data,'quote');
            // if($is_data_exits_for_checksum['found'] && $refer_webservice)
            // {
            //     $get_response = $is_data_exits_for_checksum;
            // }
            // else
            // {
            //     $get_response = getWsData(config('constants.motor.oriental.QUOTE_URL'), $input_array, 'oriental', $additional_data);               
            // }

            //$get_response = getWsData(config('constants.motor.oriental.QUOTE_URL'), $input_array, 'oriental', $additional_data);
            //$response = XmlToArray::convert($get_response['response']);
            $quote_res_array = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];
            if ($quote_res_array['ERROR_CODE'] != '0 0') {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'msg' => $quote_res_array['ERROR_CODE'],
                ];
            }
            $final_tp_premium = $final_od_premium = $final_net_premium = $final_payable_amount = $final_total_discount=0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
             $res_array = [
                'ANNUAL_PREMIUM' => $quote_res_array['ANNUAL_PREMIUM'],
                'NCB_AMOUNT' => $quote_res_array['NCB_AMOUNT'],
                'SERVICE_TAX' => $quote_res_array['SERVICE_TAX'],
                'ZERO_DEP' => '0',
                'ZERO_DEP_DISC' => '0',
                'PA_OWNER' => '0',
                'ELEC' => '0',
                'LL_PAID_DRIVER' => '0',
                'CNG' => '0',
                'CNG_TP' => '0',
                'VOL_ACC_DIS' => '0',
                'RTI' => '0',
                'ENG_PRCT' => '0',
                'DISC' => '0',
                'FIB_TANK' => '0',
                'NCB_DIS' => '0',
                'ANTI_THEFT' => '0',
                'AUTOMOBILE_ASSO' => '0',
                'UNNAMED_PASSENGER' => '0',
                'KEYREPLACEMENT' => '0',
                'CONSUMABLES' => '0',
                'LOSSPER_BELONG' => '0',
                'NO_CLAIM_BONUS' => '0',
                'OTHER_FUEL1' => '0',
                'OTHER_FUEL2' => '0',
                'IDV' => '0',
                'PA_PAID_DRIVER' => '0',
                'OD_PREMIUM' =>'0',
                'TPPD' =>'0',
                'TYRE_SECURE' =>'0',
            ];
            $flex_01 =  (!empty($quote_res_array['FLEX_02_OUT'])) ?  ($quote_res_array['FLEX_01_OUT'] . $quote_res_array['FLEX_02_OUT']) : $quote_res_array['FLEX_01_OUT'];
            $flex = explode(",", $flex_01);
            foreach ($flex as $val) {
                $cover = explode("~", $val);
                if ($cover[0] == "MOT-CVR-149") {

                    $res_array['ZERO_DEP'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-010") {

                    $res_array['PA_OWNER'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-002") {

                    $res_array['ELEC'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-015") {

                    $res_array['LL_PAID_DRIVER'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-003") {

                    $res_array['CNG'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-008") {

                    $res_array['CNG_TP'] = $cover[1];
                }if ($cover[0] == "MOT-DIS-004") {

                    $res_array['VOL_ACC_DIS'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-070") {

                    $res_array['RTI'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-EPC") {

                    $res_array['ENG_PRCT'] = $cover[1];
                }if ($cover[0] == "MOT-DLR-IMT") {

                    $res_array['DISC'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-005") {

                    $res_array['FIB_TANK'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-001") {

                    $res_array['OD_PREMIUM'] = $cover[1];
                    $res_array['IDV'] = $cover[2];
                }if ($cover[0] == "MOT-CVR-007") {

                    $res_array['TP_PREMIUM'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-012") {

                    $res_array['UNNAMED_PASSENGER'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-154") {

                    $res_array['KEYREPLACEMENT'] = $cover[1];
                } if ($cover[0] == "MOT-CVR-155") {

                    $res_array['CONSUMABLES'] = $cover[1];
                }if ($cover[0] == "MOT-DIS-013" && $cover[1] !== '0') {

                    $res_array['NCB_DIS'] = $cover[1];
                      
                }else if ($cover[0] == "MOT-DIS-310") {

                    $res_array['NCB_DIS'] = $cover[1];
                }if ($cover[0] == "MOT-DIS-002") {

                    $res_array['ANTI_THEFT'] = $cover[1];
                }if ($cover[0] == "MOT-DIS-005") {

                    $res_array['AUTOMOBILE_ASSO'] = $cover[1];
                } if ($cover[0] == "MOT-DIS-ACN") {

                    $res_array['ZERO_DEP_DISC'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-152") {

                    $res_array['LOSSPER_BELONG'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-053") {

                    $res_array['OTHER_FUEL1'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-058") {

                    $res_array['OTHER_FUEL2'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-013") {

                    $res_array['PA_PAID_DRIVER'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-019") {

                    $res_array['TPPD'] = $cover[1];
                }if ($cover[0] == "MOT-CVR-158"){
                    
                    $res_array['TYRE_SECURE'] = $cover[1];
                }
                if ($cover[0] == "MOT-CVR-006") {
                    $geog_Extension_OD_Premium = $cover[1];
                }

                if($cover[0] == "MOT-CVR-051"){
                    $geog_Extension_TP_Premium = $cover[1];
                }
            }

            $final_payable_amount = (string) ($res_array['ANNUAL_PREMIUM']);
            $final_net_premium = ($final_payable_amount / (1 + (18.0 / 100)));
           
            $zerp_dep_discount = 0;
            if($res_array['ZERO_DEP'] > 0){
               $zerp_dep_discount = ($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']);
            }
            // $res_array['NCB_DIS'] = $res_array['NCB_DIS'] - (($zerp_dep_discount + $res_array['ENG_PRCT'] + $res_array['RTI'] + $res_array['LOSSPER_BELONG']) * $requestData->applicable_ncb/100);

            $final_tp_premium = ($premium_type == 'own_damage')  ? '0' : ((string) ($res_array['TP_PREMIUM']) + (string) ($res_array['LL_PAID_DRIVER']) + (string) ($res_array['PA_PAID_DRIVER'])+(string) ($res_array['UNNAMED_PASSENGER'])+(string) ($res_array['CNG_TP'])) + $res_array['OTHER_FUEL2'] + $geog_Extension_TP_Premium;

            $final_total_discount = $res_array['ANTI_THEFT'] + $res_array['NCB_DIS'] + $res_array['VOL_ACC_DIS'] + $res_array['DISC'] + $res_array['TPPD'];

            $final_od_premium = ($premium_type == 'Third Party')  ? '0' :($res_array['OD_PREMIUM'] + $res_array['ELEC'] + $res_array['CNG']) + $res_array['OTHER_FUEL1'] + $geog_Extension_OD_Premium;

            $ribbonMessage = null;
            if ($agentDiscountSelected  && $res_array['DISC'] > 0) {
                $agentDiscountPercentage = (($res_array['DISC'] / $final_od_premium) * 100);
                if ($userSelectedPercentage != $agentDiscountPercentage) {
                    $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount').' '.$agentDiscountPercentage.'%';
                }
            }
            
            $add_ons = [];
       //     $other_fueladdon = [$res_array['OTHER_FUEL1'] + $res_array['OTHER_FUEL2']];
            $applicable_addons = [];
            if ($car_age > 5) {
                $add_ons = [
                    'in_built' => [
                        'road_side_assistance' => 0,
                    ],
                    'additional' => [
                        'zero_depreciation' => 0,
                        // 'road_side_assistance' => 0,
                        'engine_protector' => ($res_array['ENG_PRCT']), //$engine_protection,
                        'ncb_protection' => 0,
                        'key_replace' => ($res_array['KEYREPLACEMENT']),
                        'consumables' => 0, //$consumables,
                        'tyre_secure' => 0,
                        'return_to_invoice' => 0,
                        'lopb' => ($res_array['LOSSPER_BELONG']),
                    ],
                    'other' => [
                       // 'other_fuel' => $res_array['OTHER_FUEL1'] + $res_array['OTHER_FUEL2'],
                    ]
                ];
            } elseif ($zero_dep = '1' && $productData->zero_dep == '0') {
                $add_ons = [
                    'in_built' => [
                        'road_side_assistance' => 0,
                        'zero_depreciation' => ($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']),
                    ],
                    'additional' => [
                        // 'road_side_assistance' => 0,
                        'engine_protector' => ($res_array['ENG_PRCT']),
                        'ncb_protection' => 0,
                        'key_replace' => ($res_array['KEYREPLACEMENT']),
                        'consumables' => ($res_array['CONSUMABLES']),
                        'tyre_secure' => ($res_array['TYRE_SECURE']),
                        'return_to_invoice' => ($res_array['RTI']),
                        'lopb' => ($res_array['LOSSPER_BELONG']),
                    ],
                    'other' => [
                        //'other_fuel' => [$res_array['OTHER_FUEL1'] + $res_array['OTHER_FUEL2']],
                    ]
                ];
            } else {
                $add_ons = [
                    'in_built' => [
                        'road_side_assistance' => 0,
                    ],
                    'additional' => [
                        'zero_depreciation' => ($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']),
                        // 'road_side_assistance' => 0,
                        'engine_protector' => ($res_array['ENG_PRCT']),
                        'ncb_protection' => 0,
                        'key_replace' => ($res_array['KEYREPLACEMENT']),
                        'consumables' => ($res_array['CONSUMABLES']),
                        'tyre_secure' => ($res_array['TYRE_SECURE']),
                        'return_to_invoice' => ($res_array['RTI']),
                        'lopb' => ($res_array['LOSSPER_BELONG']),
                    ],
                    'other' => []
                ];
            }
            if($premium_type == "third_party")
            {
                $add_ons = [
                    'in_built' => [],
                    'additional' => [],
                    'other' => []
                ];
            }

            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "consumables", "keyReplace","returnToInvoice", "lopb", "roadSideAssistance", "tyreSecure");

            if($car_age >= 5)
            {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }
            if(!($res_array['ENG_PRCT']) > 0)
            {
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            }
            if(!($res_array['KEYREPLACEMENT']) > 0)
            {
                array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
            }
            if(!($res_array['CONSUMABLES']) > 0)
            {
                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
            }
            if(!($res_array['RTI']) > 0)
            {
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }
            if(!($res_array['LOSSPER_BELONG']) > 0)
            {
                array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
            }
            if(!($res_array['TYRE_SECURE']) > 0)
            {
                array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
            }
            foreach($add_ons['additional'] as $k=>$v){
                if(empty($v)){
                    unset($add_ons['additional'][$k]);
                }
            }
            $lopb_data = [];
            $lopb_data_applicable = 'false';
            $lopb_selected_option = 0;
            if(($res_array['LOSSPER_BELONG']) > 0)
            {
                $lopb_selected_option = 10000;
                $lopb_data_applicable = 'true';
                $lopb_data = Loss_of_personal_belonging_si_values::select('option')->where('ic_alias', 'oriental')->where('is_applicable', 'Y')->get()->toArray();
            }

            $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
            $add_ons['additional_premium'] = array_sum($add_ons['additional']);
            $add_ons['other_premium'] = array_sum($add_ons['other']);
            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'premium_type' => $premium_type,
                'Data' => [
                    'idv' => ($premium_type == 'third_party' ? 0 : (int) $idv),
                    'min_idv' => ($premium_type == 'third_party' ? 0 : (int) (int) $idv_min),
                    'max_idv' => ($premium_type == 'third_party' ? 0 : (int) (int) $idv_max),
                    'vehicle_idv' => $idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $policy__type,
                    'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : $requestData->business_type,
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $rto_code,
                    'rto_no' => $rto_code,
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => (int)($premium_type == 'third_party' ? 0 : $requestData->applicable_ncb),
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
                    'ic_vehicle_discount' => (($res_array['DISC'])),
                    'ribbon' => $ribbonMessage,
                    'basic_premium' => ($premium_type != 'third_party') ? (string) ($res_array['OD_PREMIUM']) : '0',
                    'total_accessories_amount(net_od_premium)' => ($res_array['ELEC'] + $res_array['CNG']),
                    'total_own_damage' => ($final_od_premium),
                    'tppd_premium_amount' => ($premium_type == 'own_damage')  ? '0' : (string) ($res_array['TP_PREMIUM']),
                    'compulsory_pa_own_driver' => ($premium_type == 'own_damage')  ? '0' : ($res_array['PA_OWNER']), // Not added in Total TP Premium
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'deduction_of_ncb' =>  ($res_array['NCB_DIS']),
                    'aai_discount' => '', //$automobile_association,
                    'other_discount' => '',
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
                        'manf_name' => $mmv_data->manf_name,
                        'model_name' => $mmv_data->model_name,
                        'version_name' => $mmv_data->varient_name,
                        'fuel_type' => $mmv_data->fuel_type,
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'carrying_capacity' => $mmv_data->seating_capacity,
                        'cubic_capacity' => $mmv_data->cc,
                        'gross_vehicle_weight' => '',
                        'vehicle_type' => 'Private Car',
                    ],
                    'lopb_applicable' => $lopb_data_applicable,
                    'lopb_selected_option' => $lopb_selected_option,
                    'lopb_data' => $lopb_data,
                    'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                ],
                            ];
            $included_additional = [
                'included' =>[]
            ];

            if(isset($flex_22))
            {
                if($requestData->business_type == 'newbusiness' && $flex_22 == 0)
                {
                    // unset($data_response['Data']['compulsory_pa_own_driver']);
                    $data_response['Data']['multi_Year_Cpa'] =  $res_array['PA_OWNER'];
                }
            }

            if($Electricalaccess === 1)
            {
                $data_response['Data']['motor_electric_accessories_value'] = (($premium_type != 'third_party') ? ($res_array['ELEC']) : 0);
            }
            if($NonElectricalaccess === 1)
            {
                $data_response['Data']['motor_non_electric_accessories_value'] = 0;
                $included_additional['included'][] = 'motorNonElectricAccessoriesValue';
            }
            if($externalCNGKIT === '1')
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = (($premium_type != 'third_party') ? ($res_array['CNG']) : 0);
            }

            if($voluntary_insurer_discounts !== 0 || $voluntary_insurer_discounts !== '0')
            {
                $data_response['Data']['voluntary_excess'] = (($res_array['VOL_ACC_DIS']));
            }
            if($antitheft === '1')
            {
                $data_response['Data']['antitheft_discount'] = (($res_array['ANTI_THEFT']));
            }

            if($LimitedTPPDYN === '1')
            {
                $data_response['Data']['tppd_discount'] = $res_array['TPPD'];
            }

            if($LLtoPaidDriverYN === '1')
            {
                $data_response['Data']['default_paid_driver'] = (($premium_type == 'own_damage')  ? 0 : $res_array['LL_PAID_DRIVER']);
            }
            if($PAforUnnamedPassenger === 1)
            {
                $data_response['Data']['cover_unnamed_passenger_value'] = ($premium_type == 'own_damage')  ? 0 : $res_array['UNNAMED_PASSENGER'];
            }
            if($PAPaidDriverConductorCleaner === 1)
            {
                $data_response['Data']['motor_additional_paid_driver'] = ($premium_type == 'own_damage')  ? 0 : (string) ($res_array['PA_PAID_DRIVER']);
            }
            if($internalCNG == '1')
            {
                $data_response['Data']['cng_lpg_tp'] = ($premium_type == 'own_damage')  ? 0 : ($res_array['OTHER_FUEL2']);
                $data_response['Data']['motor_lpg_cng_kit_value'] = (($premium_type != 'third_party') ? ($res_array['OTHER_FUEL1']) : 0);
            }
            if($res_array['CNG_TP'] != '0' && $res_array['CNG_TP'] != '')
            {
                $data_response['Data']['cng_lpg_tp'] = ($premium_type == 'own_damage')  ? 0 : ($res_array['CNG_TP']);
            }
            if($res_array['OTHER_FUEL2'] != '0' && $res_array['OTHER_FUEL2'] != '')
            {
                $data_response['Data']['cng_lpg_tp'] = ($premium_type == 'own_damage')  ? 0 : ($res_array['OTHER_FUEL2']);
            }
            if($res_array['OTHER_FUEL1'] != '0' && $res_array['OTHER_FUEL1'] != '')
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = (($premium_type != 'third_party') ? ($res_array['OTHER_FUEL1']) : 0);
            }
            $data_response['Data']['included_additional'] = $included_additional;
            return camelCase($data_response);
        } else {
            $return_data = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => $quote_res_array['ERROR_CODE'],
            ];
        }
    }
    return $return_data;
}
