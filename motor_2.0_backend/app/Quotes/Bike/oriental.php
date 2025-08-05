<?php

use App\Models\MasterRto;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

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
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } else {
        //$refer_webservice = config('ENABLE_TO_GET_DATA_FROM_WEBSERVICE_ORIENTAL_BIKE') == 'Y';
        $refer_webservice = $productData->db_config['quote_db_cache'];
        $no_prev_data   = ($requestData->previous_policy_type == 'Not sure') ? true : false;
        $car_age = 0;
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        if($no_prev_data)
        {
            $date2 = new DateTime(date('Y-m-d'));
        }
        else
        {
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        }

        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d == 0 ? 0 : 1);
        $car_age = floor($age / 12);

        if ($age > 168) {
             $idv = $mmv_data->upto_15_year;
        }elseif ($age > 156) {
            $idv = $mmv_data->upto_14_year;
        }elseif ($age > 144) {
            $idv = $mmv_data->upto_13_year;
        }elseif ($age > 132) {
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
   
        if (((($interval->y > 5) || !($interval->y > 5 && $interval->m > 0)  || ($interval->y == 5 && $interval->m == 0 && $interval->d > 0)) && $productData->zero_dep == '0' && $requestData->is_claim == 'Y'))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years and if claim made in existing policy ',
                'request'=> [
                'car_age'=> $car_age,
                'product_data'=>$productData->zero_dep
                ]
            ];
        }
        elseif ((($interval->y > 6) || ($interval->y == 6 && $interval->m > 5) || ($interval->y == 6 && $interval->m == 5 && $interval->d > 0)) && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC')))) 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed for vehicle age greater than 6.5 years ',
                'request'=> [
                'car_age'=> $car_age,
                'product_data'=>$productData->zero_dep
                ]
            ];
        }
            ///changes as per git #25179
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
                'request'=> [
                    'rto_number'=> $rto_data->rto_number,
                    'rto_location'=>$rto_location
                ]
            ];
        }
        $mmv_data->zone = $rto_location->rto_zone;

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

        $is_breakin     = (in_array($requestData->business_type, ['newbusiness', 'rollover'])) ? false : true;

        $is_individual  = ($requestData->vehicle_owner_type == "I");

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
            $engine_number = config('BIKE_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('BIKE_QUOTE_CHASSIS_NUMBER');
        } else {
            // $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= Carbon::parse(date('d-m-Y'))) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1)->format('d-M-Y') : date('d-M-Y');

            if($no_prev_data)
            {
                $policy_start_date = date('Y-m-d');
            }
            else
            {
                $policy_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1)->format('d-M-Y');
            }

            if($is_breakin){
                $policy_start_date = date('d-M-Y' , strtotime('+3 day'));
            }
            if($is_od && $is_breakin)
            {
                $policy_start_date = Carbon::parse(date('d-M-Y'))->addDay(1)->format('d-M-Y');
            }

            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);

            $BusinessType = 'Roll Over';


            if($no_prev_data)
            {
                $PreviousPolicyFromDt = '';
                $PreviousPolicyToDt = '';
                $PreviousPolicyType = '';
            }
            else
            {
                $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                $PreviousPolicyToDt = Carbon::parse($requestData->previous_policy_expiry_date)->format('d-M-Y');
                $PreviousPolicyType = "MOT-PLT-001";
            }

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
                    $Registration_Number = config('BIKE_QUOTE_REGISTRATION_NUMBER');
                    $engine_number = config('BIKE_QUOTE_ENGINE_NUMBER');
                    $chassis_number = config('BIKE_QUOTE_CHASSIS_NUMBER');
                }
                
            } else {
                $Registration_Number = config('BIKE_QUOTE_REGISTRATION_NUMBER');
                $engine_number = config('BIKE_QUOTE_ENGINE_NUMBER');
                $chassis_number = config('BIKE_QUOTE_CHASSIS_NUMBER');
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

        $is_breakin = (in_array($requestData->previous_policy_type, ['Not sure', 'Third-party']) && !$is_liability) ? true : $is_breakin;

        switch ($premium_type) {
            case 'comprehensive':
                $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-013" : "MOT-PRD-002";
                $policy_type = ($requestData->business_type == 'newbusiness') ? "MOT-PLT-012" : "MOT-PLT-001";
                $policy__type = 'Comprehensive';
                if(!$no_prev_data)
                {
                    $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? (($requestData->business_type == 'newbusiness') ? '' : 'MOT-PLT-001') : "MOT-PRD-002";
                }
                break;
            case 'third_party':
                $ProdCode = ($requestData->business_type == 'newbusiness') ? "MOT-PRD-013" : "MOT-PRD-002";
                $policy_type = 'MOT-PLT-002';
                $policy__type = 'Third Party';
                if(!$no_prev_data)
                {
                    $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? (($requestData->business_type == 'newbusiness') ? '' : 'MOT-PLT-002') : 'MOT-PLT-002'; 
                }
                $URL = ($requestData->business_type == 'newbusiness') ? config('constants.motor.oriental.NBQUOTE_URL') : config('constants.motor.oriental.QUOTE_URL');
                break;
            case 'own_damage':
                $ProdCode = "MOT-PRD-016";
                $policy_type = 'MOT-PLT-001';
                $policy__type = 'Own Damage';
                if(!$no_prev_data)
                {
                    $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-001';
                    $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                    $PreviousPolicyToDt = Carbon::parse($PreviousPolicyFromDt)->addYear(5)->subDay(1)->format('d-M-Y');   
                }
                break;
        }

        if($no_prev_data)
        {
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $PreviousPolicyType = '';
        }
        $zero_dep = ($productData->zero_dep == '0') ? '1': '0';
        $is_zero_dep = ($productData->zero_dep == '0') ? true: false;


        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $flex_22 = '';
        if (!empty($selected_addons['compulsory_personal_accident'])) {
            foreach ($selected_addons['compulsory_personal_accident'] as $key => $cpaArr)  {
                if (isset($cpaArr['name']) && $cpaArr['name'] == 'Compulsory Personal Accident')  {
                    $cpa_selected = '1';
                    $tenure = 1;
                    $tenure = isset($cpaArr['tenure'])? $cpaArr['tenure'] :$tenure;
                    if($tenure === 5 || $tenure === '5')
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
        $ElectricalaccessSI = $rsacover = $PAforUnnamedPassengerSI = $antitheft = $voluntary_insurer_discountsf = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = $LimitedTPPDYN = 0;
        //$addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $Electricalaccess = $NonElectricalaccess = '0';
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $voluntary_insurer_discounts = 'TWVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'TWVE1', // Discount of 0
            '500'  => 'TWVE2', // Discount of 750
            '750'  => 'TWVE3', // Discount of 1500
            '1000'  => 'TWVE4', // Discount of 2000
            '1500' => 'TWVE5', 
            '3000' => 'TWVE6',// Discount of 2500
        ];
        foreach ($discounts as $key => $value) {
            if (in_array('anti-theft device', $value)) {
                $antitheft = '1';
            }
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)){
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
                if($voluntary_insurer_discounts == 750)
                {
                    $voluntary_insurer_discounts = 700;
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = '1';
            }
        }
        $LLtoPaidDriverYN = '0';
        $is_geo_ext = false;
        $countries = [];
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

            if ($value['name'] == 'Geographical Extension') {
                $countries = $value['countries'] ;
                $is_geo_ext = "true";
            }
        }
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 'ELEC';
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 'NONELEC';
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = '1';
                $externalCNGKITSI = $value['sumInsured'];
            }
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

        $fuel_type_code = $fuelType[strtoupper($mmv_data->veh_fuel_desc)];

        if($mmv_data->fuel_type_code == null || $mmv_data->fuel_type_code == '')
        {
            $mmv_data->fuel_type_code = $fuel_type_code;
        }

        $state = explode("-", $rto_data->rto_number);
        $is_pos_enabled = 'N'; #config('constants.motorConstant.IS_POS_ENABLED');
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

        $ProductCodeArray = [
            'pos' => [
                'newbusiness' => [
                    'comprehensive' => 'MOT-POS-013',
                    'third_party' => 'MOT-POS-013',

                ],
                'rollover' => [
                    'comprehensive' => 'MOT-POS-002',
                    'third_party' => 'MOT-POS-002',
                    'own_damage' => 'MOT-POS-016',
                ],
            ],
            'non_pos' => [
                'newbusiness' => [
                    'comprehensive' => 'MOT-PRD-013',
                    'third_party' => 'MOT-PRD-013',
                ],
                'rollover' => [
                    'comprehensive' => 'MOT-PRD-002',
                    'third_party' => 'MOT-PRD-002',
                    'own_damage' => 'MOT-PRD-016',
                ],
            ],
        ];

        $is_no_ncb = (($requestData->is_claim == 'Y') || $is_liability || in_array($requestData->previous_policy_type, ['Not sure', 'Third-party'])) ? true : false;

        $discount_grid = DB::table('oriental_non_maruti_discount_grid')
            ->where('VEH_MODEL', $mmv_data->vehicle_code)
            ->first();
        $discount_grid = keysToLower($discount_grid);
        $discount_percentage = '';
        $discount_per_array = json_decode(config("NEW_DISCOUNT_PERCENTAGE_VALUES"), 1) ?? [];
        if (config("NEW_DISCOUNT_PERCENTAGE_CHANGES") == "Y" && !empty($discount_per_array) && !$is_liability) {
            if ($is_new) {
                $discount_percentage = $discount_per_array["oriental"]["bike"]["newbusiness"];
            } else if ($is_od) {
                $discount_percentage = $discount_per_array["oriental"]["bike"]["own_damage"];
            } else {
                foreach ($discount_per_array["oriental"]["bike"]['other'] as $val) {
                    if ($val['from'] < $age && $val['to'] >= $age) {
                        $discount_percentage = $val['percentage'];
                        break;
                    }
                }
            }
        } else if (!empty($discount_grid) && !$is_liability) {
            if ($car_age <= 5) {
                $discount_percentage = $discount_grid->disc_upto_5yrs;
            } else {
                $discount_percentage = $discount_grid->disc_5_to_10yrs;
            }
        }

        $agentDiscountSelected = false;
        $userSelectedPercentage = $discount_percentage;
        if ($premium_type != 'third_party') {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'oriental', 'bike');
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

        $idv_min = ($premium_type == 'third_party' ? 0 : ($idv * 0.85));
        $idv_max = ($premium_type == 'third_party' ? 0 : ($idv * 1.15));
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
                $idv =  $idv_min;
            }
        }

        if ($mmv_data->cubic_capacity <= 150 && $idv < 5000){
            $idv = $idv_min = 5000;
        } elseif ($mmv_data->cubic_capacity > 150 && $mmv_data->cubic_capacity <= 350 && $idv < 6000)
        {
            $idv = $idv_min = 6000;
        } elseif ($mmv_data->cubic_capacity > 350 && $idv < 7000)
        {
            $idv = $idv_min = 7000;
        }

        $vehIdv = '';
        if ($premium_type != 'third_party') {
            $vehIdv = $idv + $ElectricalaccessSI + $NonElectricalaccessSI + $externalCNGKITSI;
        }
        
        $model_config_premium = [
            'soap:Body' => 
                  [
                    $soapAction => 
                    [
                      'objGetQuoteMotorETT' => 
                      [
                          'LOGIN_ID' => config('constants.motor.oriental.LOGIN_ID_ORIENTAL_MOTOR'),
                        //   'DLR_INV_NO' => 'POLBZR122',
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
                          'ROAD_TRANSPORT_YN' => '',//'1', //addon rsa
                          'INSURED_KYC_VERIFIED' => '',
                          'MOU_ORG_MEM_ID' => '',
                          'MOU_ORG_MEM_VALI' => '',
                          'MANUF_VEHICLE_CODE' => $mmv_data->ven_manf_code,
                          'VEHICLE_CODE' => $mmv_data->vehicle_code, //'VEH_MAK_5267'
                          'VEHICLE_TYPE_CODE' => ($requestData->business_type== 'newbusiness') ? 'W' : 'P',
                          'VEHICLE_CLASS_CODE' => 'CLASS_3',//'CLASS_2', //for nonpos PC class_2
                          'MANUF_CODE' => $mmv_data->ven_manf_code, //need to discuss'VEH_MANF_044'
                          'VEHICLE_MODEL_CODE' => $mmv_data->vehicle_code,
                          'TYPE_OF_BODY_CODE' => 'SALOON', //for the time include in table
                          'VEHICLE_COLOR' => 'DAJJHHH',
                          'VEHICLE_REG_NUMBER' => ($requestData->business_type == 'newbusiness') ? 'NEW-1234' :$Registration_Number,
                          'FIRST_REG_DATE' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                          'ENGINE_NUMBER' => $engine_number,#'ENGINE123456789',
                          'CHASSIS_NUMBER' => $chassis_number,#'CHASSIS1234567891',
                          'VEH_IDV' => $vehIdv,//$idv, //isset($idv) ? $idv : ($requestData->business_type== 'newbusiness') ? $mmv_data['UPTO_3_YEAR'] : $mmv_data['UPTO_1_YEAR'],
                          'CUBIC_CAPACITY' => $mmv_data->cubic_capacity,
                          'THREEWHEELER_YN' => '0',
                          'SEATING_CAPACITY' => $mmv_data->seating_capacity,
                          'VEHICLE_GVW' => '',
                          'NO_OF_DRIVERS' => '1',
                          'FUEL_TYPE_CODE' => $mmv_data->fuel_type_code, 
                          'RTO_CODE' => $rto_code,
                          'ZONE_CODE' => $mmv_data->zone,
                          'GEO_EXT_CODE' => '',
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
                          'LIMITED_TPPD_YN' => $LimitedTPPDYN,
                          'RALLY_COVER_YN' => '',
                          'RALLY_DAYS' => '',
                          'NIL_DEP_YN' => $zero_dep, //Zero DEP
                          'FIBRE_TANK_VALUE' => '0',
                          'ALT_CAR_BENEFIT' => '',
                          'PERS_EFF_COVER' => '', //Loss of personal belongings
                          'NO_OF_PA_OWNER_DRIVER' => $PAPaidDriverConductorCleaner, // Pa cover to owner driver
                          'NO_OF_PA_NAMED_PERSONS' => '',
                          'PA_NAMED_PERSONS_SI' => '',
                          'NO_OF_PA_UNNAMED_PERSONS' => $PAforUnnamedPassenger,
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
                          'NCB_PERCENTAGE' => ($is_no_ncb ? '' : $requestData->previous_ncb),
                          'PREV_INSU_COMPANY' => ($no_prev_data || $requestData->business_type== 'newbusiness') ? '' : 'Bajaj Allianz General Insurance Company Limited',
                          'PREV_POL_NUMBER' => ($no_prev_data || $requestData->business_type == 'newbusiness') ? '' : '211200/31/2018/86734',
                          'PREV_POL_START_DATE' => $PreviousPolicyFromDt,
                          'PREV_POL_END_DATE' => $PreviousPolicyToDt,
                          'EXIS_POL_FM_OTHER_INSR' => '0',
                          'IP_ADDRESS' => '',
                          'MAC_ADDRESS' => '',
                          'WIN_USER_ID' => '',
                          'WIN_MACHINE_ID' => '',
                          'DISCOUNT_PERC' => trim($discount_percentage),
                        //   'P_FLEX_19' => 'LF0000000041',
                          'LPE_01' => '',//Loss of personal belongings
                          'FLEX_01' => $PAPaidDriverConductorCleanerSI,
                          'FLEX_02' => '',
                          'FLEX_03' => ($requestData->business_type== 'newbusiness') ? '' : date('Y', strtotime('01-'.$requestData->manufacture_year)), //manf year
                          'FLEX_05' => '',
                          'FLEX_06' => '',
                          'FLEX_07' => '',
                          'FLEX_08' => '', //GSTNO
                          'FLEX_09' => '', //towing
                          'FLEX_10' => '',
                          'FLEX_19' => $posp_code,
                          'FLEX_20' => (!$is_od && $is_individual) ? 'N':'Y',
                          //'FLEX_12' => ((($car_age < 5) && $zero_dep == '1') ? '20' : ''),
                          'FLEX_12' =>  $zero_dep == '1' ? '20' : '',
                          'FLEX_21' => 'N',//($car_age > 10) ? 'N' : 'N', //engine protector
                          'FLEX_22' => $flex_22,
                          'FLEX_24' => (($premium_type == 'own_damage') ? ('Bajaj Allianz General Insurance Company Limited' . '~' . 2112003120186734 . '~' . $PreviousPolicyFromDt . '~' . $PreviousPolicyToDt) : '' ),
                      ],
                    '_attributes' => [
                        "xmlns" => "http://MotorService/"
                    ],
                    ],
                ],
        ];
        if ($is_geo_ext)
        {
            if (in_array('Sri Lanka',$countries))
            {
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD5';
            }
            if (in_array('Bangladesh',$countries))
            {
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD1'; 
            }
            if (in_array('Bhutan',$countries))
            {
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD2'; 
            }
            if (in_array('Nepal',$countries))
            {
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD3'; 
            }
            if (in_array('Pakistan',$countries))
            {
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD4'; 
            }
            if (in_array('Maldives',$countries))
            {
                $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['GEO_EXT_CODE']='GEO-EXT-COD6'; 
            }
        }
        if (($externalCNGKITSI != '0')) {
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['CNG_KIT_VALUE'] = $externalCNGKITSI;
            $model_config_premium['soap:Body'][$soapAction]['objGetQuoteMotorETT']['FLEX_04'] = '';
        }
             
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
                'section' => 'Bike',
                'method' => 'Premium Calculation',
                'product' => 'Bike',
                'transaction_type' => 'quote',
                'productName'       => $productData->product_name,
                'checksum' => $checksum_data,
                'policy_id' => $productData->policy_id
            ];

            $service_rehit = true;
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'oriental',$checksum_data,'BIKE');
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
        $response = $get_response['response'];

        if (empty($get_response['response'])) {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'msg' => 'Insurer not reachable',
            ];
        }
        
        $response = XmlToArray::convert($response);

        if($response){
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quotation converted to proposal successfully", "Success" );
        }else{
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], $response['message_txt'], "Failed" );
        }

        $quote_res_array = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];

        if (($quote_res_array['ERROR_CODE'] == '0 0'))
        {
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
            
            if (empty($get_response['response'])) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'msg' => 'Insurer not reachable',
                ];
            }

            // $response = XmlToArray::convert($get_response['response']);
            // $quote_res_array = $response['soap:Body']['GetQuoteMotorResponse']['GetQuoteMotorResult'];

            if ($quote_res_array['ERROR_CODE'] != '0 0') {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'msg' => $quote_res_array['ERROR_CODE'],
                ];
            }
            $final_tp_premium = $final_od_premium = $final_net_premium = $final_payable_amount = $final_total_discount=0;
            $GeogExtension_od = 0;
            $GeogExtension_tp = 0;
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
                'TPPD'  => '0',
                'OD_LOADING_AMOUNT'  => '0',
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
                }
                if ($cover[0] == "MOT-CVR-019")
                {
                    $res_array['TPPD'] = $cover[1];
                }
                if ($cover[0] == "MOT-OD-LOD")
                {
                    $res_array['OD_LOADING_AMOUNT'] = $cover[1];
                }
                if ($cover[0] == 'MOT-CVR-006') {
                    $GeogExtension_od = $cover[1];
                }
                if ($cover[0] == 'MOT-CVR-051')
                {
                    $GeogExtension_tp = $cover[1];
                }
                $new_array[$cover[0]] = $cover;
            }

            $final_payable_amount = (string) ($res_array['ANNUAL_PREMIUM']);
            $final_net_premium = ($final_payable_amount / (1 + (18.0 / 100)));

            $final_tp_premium = ($premium_type == 'own_damage')  ? '0' : ((string) ($res_array['TP_PREMIUM']) + (string) ($res_array['LL_PAID_DRIVER']) + (string) ($res_array['PA_PAID_DRIVER'])+(string) ($res_array['UNNAMED_PASSENGER'])+(string) ($res_array['CNG_TP'] + $GeogExtension_tp));

            $final_total_discount = $res_array['ANTI_THEFT'] + $res_array['NCB_DIS'] + $res_array['VOL_ACC_DIS'] + $res_array['DISC'] + $res_array['TPPD'];

            $final_od_premium = ($premium_type == 'Third Party')  ? '0' :($res_array['OD_PREMIUM'] + $res_array['ELEC'] + $res_array['CNG']+ $GeogExtension_od);

            $ribbonMessage = null;
            if ($agentDiscountSelected  && $res_array['DISC'] > 0) {
                $agentDiscountPercentage = (($res_array['DISC'] / $final_od_premium) * 100);
                if ($userSelectedPercentage != $agentDiscountPercentage) {
                    $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount').' '.$agentDiscountPercentage.'%';
                }
            }

            $add_ons = [];
            $applicable_addons = [];
                      
            if ((($interval->y > 6) || ($interval->y == 6 && $interval->m > 5) || ($interval->y == 6 && $interval->m == 5 && $interval->d > 0))) {
                $add_ons = [
                    'in_built' => [
                        'road_side_assistance'  => 0,
                    ],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        // 'road_side_assistance'  => 0,
                        'engine_protector'      => 0, //$engine_protection,
                        'consumables'           => 0, //$consumables,
                        'return_to_invoice' => 0,
                    ],
                    'other' => [
                       // 'other_fuel' => $res_array['OTHER_FUEL1'] + $res_array['OTHER_FUEL2'],
                    ]
                ];
            } elseif ($is_zero_dep && ($res_array['ZERO_DEP']) > 0) {
                $add_ons = [
                    'in_built' => [
                        'zero_depreciation'     => ($res_array['ZERO_DEP'] - $res_array['ZERO_DEP_DISC']),
                        'road_side_assistance'  => 0,
                    ],
                    'additional' => [
                        // 'road_side_assistance'  => 0,
                        'engine_protector'      => 0, //$engine_protection,
                        'consumables'           => 0, //$consumables,
                        'return_to_invoice' => ($res_array['RTI']),
                    ],
                    'other' => [
                        //'other_fuel' => $res_array['OTHER_FUEL1'] + $res_array['OTHER_FUEL2'],
                    ]
                ];
                array_push($applicable_addons, "zeroDepreciation", "returnToInvoice" , "roadSideAssistance");
            } else {
                $add_ons = [
                    'in_built' => [
                        'road_side_assistance'  => 0,
                    ],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        // 'road_side_assistance'  => 0,
                        'engine_protector'      => 0, //$engine_protection,
                        'consumables'           => 0, //$consumables,
                        'return_to_invoice'     => ($res_array['RTI']),
                    ],
                    'other' => []
                ];
                array_push($applicable_addons, "zeroDepreciation", "returnToInvoice", "roadSideAssistance");
            }
            if($premium_type == "third_party")
            {
                $add_ons = [
                    'in_built' => [],
                    'additional' => [],
                    'other' => []
                ];
            }
            if(($res_array['RTI']) == '0'){        
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }
            foreach($add_ons['additional'] as $k=>$v){
                if(empty($v)){
                    unset($add_ons['additional'][$k]);
                }
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
                    'idv' => ($premium_type=='third_party' || $premium_type== 'third_party_breakin') ? 0 : (int) $idv,
                    'min_idv' => (int) $idv_min,
                    'max_idv' => (int) $idv_max,
                    'vehicle_idv' => ($premium_type=='third_party' || $premium_type== 'third_party_breakin') ? 0 : (int) $idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $policy__type,
                    'business_type' => (
                        $is_breakin
                        ? 'Break-in'
                        : (
                            ($requestData->business_type == 'newbusiness')
                            ? 'New Business'
                            : $requestData->business_type)),
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
                    'ic_vehicle_discount' => (($res_array['DISC'])),
                    'ribbon' => $ribbonMessage,
                    'basic_premium' => ($premium_type != 'third_party') ? (string) ($res_array['OD_PREMIUM']) : 0,
                    'total_accessories_amount(net_od_premium)' => ($res_array['ELEC'] + $res_array['CNG']),
                    'total_own_damage' => ($final_od_premium),
                    'tppd_premium_amount' => ($premium_type == 'own_damage')  ? 0 : (string) ($res_array['TP_PREMIUM']),
                    'underwriting_loading_amount'=> $premium_type == 'third_party' ? 0 : (int)($res_array['OD_LOADING_AMOUNT']),
                    'compulsory_pa_own_driver' => ($premium_type == 'own_damage')  ? 0 : ($res_array['PA_OWNER']), // Not added in Total TP Premium
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'deduction_of_ncb' => (string) (($res_array['NCB_DIS'])),
                    'aai_discount' => '', //$automobile_association,
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
                    'GeogExtension_ODPremium' => ($GeogExtension_od),
                    'GeogExtension_TPPremium' => ($GeogExtension_tp),
                    'applicable_addons' => $applicable_addons,
                    'final_od_premium' => ($final_od_premium),
                    'final_tp_premium' => ($final_tp_premium),
                    'final_total_discount' => (abs($final_total_discount)),
                    'final_net_premium' => ($final_net_premium),
                    'final_gst_amount' => ($final_payable_amount - $final_net_premium),
                    'final_payable_amount' => ($final_payable_amount),
                    'mmv_detail' => [
                        'manf_name' => $mmv_data->veh_manf_desc,
                        'model_name' => $mmv_data->veh_model_desc,
                        'version_name' => '',
                        'fuel_type' => $mmv_data->veh_fuel_desc,
                        'seating_capacity' => $mmv_data->seating_capacity,
                        'carrying_capacity' => $mmv_data->seating_capacity,
                        'cubic_capacity' => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight' => '',
                        'vehicle_type' => 'Bike',
                    ],
                ],
                $res_array
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

            if($Electricalaccess == 'ELEC')
            {
                $data_response['Data']['motor_electric_accessories_value'] = (($premium_type != 'third_party') ? ($res_array['ELEC']) : 0);
            }
            if($NonElectricalaccess === 'NONELEC')
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
            if($externalCNGKIT === '1')
            {
                $data_response['Data']['cng_lpg_tp'] = ($premium_type == 'own_damage')  ? 0 : ($res_array['CNG_TP']);
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
