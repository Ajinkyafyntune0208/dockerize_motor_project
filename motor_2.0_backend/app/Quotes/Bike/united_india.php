<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];              
    try{
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
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);

        $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);

        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin     = ($requestData->business_type != "breakin" ? false : true);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $motor_manf_date = '01-' . $requestData->manufacture_year;

        $is_tp_breakin = (($premium_type == 'third_party_breakin') ? true : false);

        if($is_breakin && !$is_tp_breakin)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Break-In Quotes Are Allowed Only for Third-Party Policy',
                'request' => [
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'message' => 'Break-In Quotes Are Allowed Only for Third-Party Policy',
                ]
            ];
        }
        
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

        if (empty($requestData->rto_code)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request'=>[
                    'rto_code'=>$requestData->rto_code,
                    'request_data'=>$requestData
                ]
            ];
        }
        // if(($requestData->previous_policy_type == 'Not sure') && $requestData->business_type != 'newbusiness')
        // {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Previous Policy Not Sure Quotes Not Allowed',
        //         'request' => [
        //             'message' => 'Previous Policy Not Sure Quotes Not Allowed',
        //             'previous_policy_type' => $requestData->previous_policy_type
        //         ]
        //     ];
        // }
        $mmv = get_mmv_details($productData, $requestData->version_id, 'united_india');

        $mmv_data = united_mmv_check($mmv);

        if(!$mmv_data['status']){
            return $mmv_data;
        }

        $mmv = $mmv_data['mmv_data'];
        if (str_contains($mmv->variant_period, '+')) 
        { 
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Invalid vehicle variant period',
                'request' => [
                    'variant_period' => $mmv->variant_period,
                    'message' 	     => 'Invalid vehicle variant period',
                ]
            ];
        }

        $str["from"]   = strpos($mmv->variant_period, "[");
        $str["to"]     = strpos($mmv->variant_period, "]");
        $str["rem"]    = $str["to"] - $str["from"];

        $str["period"] = explode(' - ', substr($mmv->variant_period, $str["from"]+1, $str["rem"]-1));

        $veh_start_period = $str["period"][0];
        $veh_end_period = (($str["period"][1] == '') ? (date('Y')+1) : $str["period"][1]);

        if(!$is_liability){
            if((Carbon::parse($motor_manf_date)->format('Y') < $veh_start_period || Carbon::parse($motor_manf_date)->format('Y') > $veh_end_period) && config('constants.IcConstants.united_india.IS_VARIANT_PERIOD_ACTIVE') == 'Y'){
                return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => 'Vehicle manufacturing year does not match with variant period '.$mmv->variant_period.'.',
                    'request'=>[
                        'veh_start_period'=>$veh_start_period,
                        'Vehicle_manufacturing_year'=>$motor_manf_date
                    ]
                ];
            }
        }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $selected_addons        = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons                 = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories            = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers      = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts      = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPassenger = $NonElectricalaccess = $PAPaidDriverConductorCleaner = $llpaidDriver = "N";

        // additional covers
        $externalCNGKITSI = $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = 0;
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess               = "Y";
                $ElectricalaccessSI             = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess            = "Y";
                $NonElectricalaccessSI          = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT                 = "Y";
                $externalCNGKITSI               = $value['sumInsured'];
            }

            if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
                $PAPaidDriverConductorCleaner   = "Y";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }

        foreach ($additional_covers as $key => $value) {
            if (in_array('PA cover for additional paid driver', $value)) {
                $PAforaddionaldPaidDriver       = "Y";
                $PAforaddionaldPaidDriverSI     = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger          = "Y";
                $PAforUnnamedPassengerSI        = $value['sumInsured'];
            }

            if (in_array('LL paid driver', $value)) {
                $llpaidDriver                   = "Y";
                $llpaidDriverSI                 = $value['sumInsured'];
            }
        }

        $is_anti_theft = $is_voluntary_access = $is_tppd = 'N';
        $voluntary_excess_amt = 0;
        $tppd_amount = '100000';
        foreach ($discounts as $key => $value) {
            if (in_array('anti-theft device', $value) && !$is_liability)
            {
                $is_anti_theft = 'Y';
            }
            if (in_array('voluntary_insurer_discounts', $value) && isset($value['sumInsured']))
            {
                $is_voluntary_access = 'Y';
                $voluntary_excess_amt = $value['sumInsured'];
            }
            if (in_array('TPPD Cover', $value) && !$is_od)
            {
                $is_tppd = 'Y';
                $tppd_amount = '6000';
            }
        }
        // end additional covers


        $vehicleDate    = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1          = new DateTime($vehicleDate);
        $date2          = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') :($requestData->business_type == "breakin" ? date('Y-m-d', strtotime('+3 day', time())): $requestData->previous_policy_expiry_date));
        $interval       = $date1->diff($date2);
        $age            = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $vehicle_age    = $interval->y;
        $vehicle_age_limit = ceil($age / 12);

        
        $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        
        if (($interval->y >= 15) && ($tp_check == 'true')){
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
            ];
        }
        if ($vehicle_age_limit > 15) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Bike Age greater than 15 years',
                'request'=> [
                    'vehicle_age' => $vehicle_age_limit,
                    'Vehicle_manufacturing_year' => $motor_manf_date
                ]
            ];
        }

        if($is_zero_dep)
        {
            if ($vehicle_age >= 5 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC'))))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 4.5 Years'
                ];
            }
            else if($vehicle_age == 4 && $interval->m >= 6 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC'))))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 4.5 Years',
                    $interval
                ];
            }
        }

        $is_zero_dep_applicable = true;

        if (($vehicle_age >= 5) || $vehicle_age == 4 && $interval->m >= 6)
        {
            $is_zero_dep_applicable = false;
        }

        if (isset($selected_addons->accessories[2]['sumInsured']) && $selected_addons->accessories[2]['sumInsured'] > 50000) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Motor Electric/Non Electric Accessories should not be greater than 50000'
            ];
        }

        $vehicle_in_90_days = 0;

        $current_date = date('Y-m-d');
        $requestData->previous_policy_expiry_date = (($requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $datetime2      = new DateTime($current_date);
        $datetime1      = new DateTime($requestData->previous_policy_expiry_date);
        $intervals      = $datetime1->diff($datetime2);
        $difference     = $intervals->invert;

        if ($requestData->business_type == "newbusiness") {
            $policy_start_date  = date('d-m-Y');
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
        }elseif($requestData->previous_policy_type == "Not sure"){
            $policy_start_date = ($premium_type == 'third_party') ? date('d-m-Y',strtotime('+1 day')) : date('d-m-Y',strtotime('+2 day'));
            $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
            $requestData->applicable_ncb = 0;
            $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
        } else {
            $policy_start_date  = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }

            if($is_tp_breakin){
                $policy_start_date = date('d-m-Y', strtotime('+2 day', time()));
            }

            if ($requestData->business_type == 'breakin' && in_array($premium_type, ['comprehensive', 'own_damage'])) {
                $policy_start_date = date('d-m-Y', strtotime('+3 day', time()));
            }

            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
        }

        $tp_start_date      = date('d-m-Y', strtotime($requestData->vehicle_register_date));
        $tp_end_date        = date('d-m-Y', strtotime($tp_start_date.'-1 days +5 year'));

        // echo "<pre>";print_r([$mmv]);echo "</pre>";die();
        $mmv->seating_capacity = 2;

        $mmv_data = [
            'manf_name'             => $mmv->make,
            'model_name'            => $mmv->model,
            'version_name'          => $mmv->variant,
            'seating_capacity'      => $mmv->seating_capacity,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel_type,
            'vehicle_type'          => 'BIKE',
            'version_id'            => $mmv->ic_version_code,
        ];

        $mmvdata = (array)$mmv;

        $mmv->idv = $mmvdata['ex_showroom_price'];

        if($age <= 6)
        {
            #$mmv->idv = $mmvdata['6_mths'];
            $mmv->idv= ($mmvdata['ex_showroom_price'] * (100 - 5) / 100);
        }
        else if($age > 6 && $age <= 12)
        {
            #$mmv->idv = $mmvdata['6m_to_1_yr'];
            $mmv->idv= ($mmvdata['ex_showroom_price'] * (100 - 15) / 100);
        }
        else
        {
            $getidv = getIdvData($mmvdata['ex_showroom_price'] , $interval);
            if(!$getidv->status && !$is_liability)
            {
                return (array)$getidv;
            }
            $mmv->idv = $getidv->idv ;
            /* for ($i=1; $i < 10; $i++) {
                if($age > ($i*12) && $age <= (($i+1)*12)){
                    if($i < 5)
                    {
                        $mmv->idv = $mmvdata[$i.'_'.($i+1).'yr'];
                    }
                    else
                    {
                        $mmv->idv = $mmvdata[$i.'_'.($i+1).'_yr'];
                    }

                    break;
                }
            } */
        }

        /* if($mmv->idv == '' || $mmv->idv == '0' || $mmv->idv == 0)
        {
            $mmv->idv = $mmvdata['ex_showroom_price'];
        } */

        unset($mmvdata);

        $FuelType       = strtoupper($mmv->fuel_type);
        $cngLpgIDV      = $externalCNGKITSI;

        $inbuiltCNGLPG = ($FuelType === 'CNG') ? -1 : 0; // As per git id 29421
        // if($FuelType == '' || $FuelType == null){
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Fuel Type Not Found'
        //     ];
        // }
        // if ($FuelType == 'PETROL' || $FuelType == 'DIESEL' || $FuelType == 'ELECTRIC')
        // {
        //     $inbuiltCNGLPG = 0;
        //     if ($cngLpgIDV == 0 || $cngLpgIDV == '')
        //     {
        //         $cngLpgIDV = 0;
        //     }
        // }elseif($FuelType == 'CNG'){
        //     $inbuiltCNGLPG = -1;
        // }else{
        //     $inbuiltCNGLPG = null;
        // }

        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code = ($requestData->business_type == "rollover" || $is_breakin) ? "2" : "1";
        $btype_name = ($requestData->business_type == "rollover" || $is_breakin) ? "Roll Over" : "New Business";

        if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
            $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
        } else {
            $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
        }

        $yn_paid_driver                     = 'N';
        $txt_cover_period                   = 1; //($requestData->business_type == 'newbusiness') ? 5 : 1;

        $zero_dep                           = 0;
        $consumable                         = 'N';
        $loss_of_belongings                 = 'N';
        $engine_secure                      = 'N';
        $tyre_secure                        = 'N';
        $key_replacement                    = 'N';
        $road_side_assistance               = 'N';
        $return_to_invoice                  = 'N';

        if ($is_zero_dep)
        {
            $zero_dep               = -1; 
            if($vehicle_age < 3)
            {
                $return_to_invoice      = 'Y';
            }
        }

        if($vehicle_age < 5 && !$is_liability)
        {
            $engine_secure          = 'Y';
            // $key_replacement        = 'Y';
            $consumable             = 'Y';
        }
        if($vehicle_age < 3 && !$is_liability)
        {
            // $tyre_secure            = 'Y';
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
        if ($requestData->business_type == 'newbusiness') {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '5';
            // $cpa_year = '1'; // By Default CPA will be 1 year
        } else {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
        }
        $cpa_cover                          = (($is_individual && !$is_od) ? -1 : 0);
        $cpa_cover_period                   = (($is_individual && !$is_od) ? $cpa_tenure : '');
        $anti_theft_flag                    = (($is_anti_theft == 'Y') ? -1 : 0);

        if(!$is_od && $is_individual)
        {
            $yn_paid_driver                         = 'Y';
        }

        if ($requestData->business_type == 'breakin')//addon not applicable in case of breakin
        {
            $zero_dep                           = 0;
            $consumable                         = 'N';
            $loss_of_belongings                 = 'N';
            $engine_secure                      = 'N';
            $tyre_secure                        = 'N';
            $key_replacement                    = 'N';
            $road_side_assistance               = 'N';
            $return_to_invoice                  = 'N';
        }

        $rto_data = DB::table('united_india_rto_master')->where('TXT_RTA_CODE', strtr($requestData->rto_code, ['-' => '']))->first();
        $rto_data = keysToLower($rto_data);
        if(empty($rto_data))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request'=>[
                    'rto_code'=>$requestData->rto_code,
                    'rto_data' => $rto_data
                ]
            ];
        }
        $legal_liability_to_paid_driver_flag = (($is_od || $llpaidDriver == "N") ? '0' : '1');

        $rsa_cover = ($is_od || $is_package) ? 'Y' : 'N';

        // quick Quote Service

        $is_aa_apllicable = false;

        $proposal_date = date('d/m/Y');
        $expdate=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $date_difference = get_date_diff('day',$expdate);
        $vehicle_more_than_90_days = ($date_difference >= 90) ? true : false;
        $quote_array = [
            'HEADER' => [
                'BIFUELKITODPREMIUM'                => 0,
                'BIFUELKITTPPREMIUM'                => 0,
                'CUR_DEALER_GROSS_PREM'             => 0,
                'CUR_DEALER_NET_OD_PREM'            => 0,
                'CUR_DEALER_NET_TP_PREM'            => 0,
                'CUR_DEALER_SERVICE_TAX'            => 0,

                // POLICY AND BUSINESS TYPE
                'NUM_CLIENT_TYPE'                   => ($is_individual ? 'I' : 'C'),
                'NUM_POLICY_TYPE'                   => ($is_package ? 'PackagePolicy' : ($is_liability ? 'LiabilityOnly' : 'StandAloneOD')),
                'NUM_BUSINESS_CODE'                 => $btype_name,

                // DATES
                'DAT_PREV_POLICY_EXPIRY_DATE'       => Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y'),
                'DAT_PROPOSAL_DATE'                 => $proposal_date,
                'DAT_DATE_OF_EXPIRY_OF_POLICY'      => Carbon::parse($policy_end_date)->format('d/m/Y'),
                'DAT_DATE_OF_ISSUE_OF_POLICY'       => Carbon::parse($policy_start_date)->format('d/m/Y'),
                'DAT_DATE_OF_REGISTRATION'          => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                'DAT_DATE_OF_PURCHASE'              => Carbon::parse($vehicleDate)->format('d/m/Y'),
                'DAT_UTR_DATE'                      => '',
                'DAT_DRIVING_LICENSE_EXP_DATE'      => '',

                'DAT_HOURS_EFFECTIVE_FROM'          => '00:00',
                'TXT_TITLE'                         => 'MR.',
                'MEM_ADDRESS_OF_INSURED'            => 'abcd efgh',
                'TXT_MOBILE'                        => '9898989899',
                'TXT_EMAIL_ADDRESS'                 => 'demoexample@gmail.com',
                'TXT_GENDER'                        => 'Male',
                'TXT_DOB'                           => '07/07/1992',
                'NUM_PIN_CODE'                      => '400705',
                'TXT_NAME_OF_INSURED'               => 'abcd',
                'TXT_NAME_OF_NOMINEE'               => 'abcde',
                
                'NOCLAIMBONUSDISCOUNT'              => 0,
                'NUM_AGREEMENT_NAME_1'              => '',
                'NUM_AGREEMENT_NAME_2'              => '',
                'NUM_COMPULSORY_EXCESS_AMOUNT'      => 0,
                'NUM_DAYS_COVER_FOR_COURTESY'       => 0,
                'NUM_FINANCIER_NAME_1'              => '',
                'NUM_FINANCIER_NAME_2'              => '',
                'NUM_GEOGRAPHICAL_EXTN_PREM'        => 0,
                'NUM_IEV_BASE_VALUE'                => ((!$is_liability) ? (int) round($mmv->idv) : '0'),
                'NUM_NO_OF_NAMED_DRIVERS'           => 0,
                'NUM_PA_UNNAMED_NUMBER'             => ((!$is_od) ? '1' : '0'),
                'NUM_POLICY_NUMBER'                 => '1234567890',
                'NUM_PREVIOUS_IDV'                  => '',
                'NUM_SPECIAL_DISCOUNT_RATE'         => (((!$is_liability) ? config('constants.IcConstants.united_india.bike.OD_DISCOUNT_RATE') : '0')),
               # 'NUM_TPPD_AMOUNT'                   => (!$is_od ? '6000' : '6000'),
                'NUM_TPPD_AMOUNT'                   => $tppd_amount,
                'NUM_UTR_PAYMENT_AMOUNT'            => '',
                'NUM_YEAR_OF_MANUFACTURE'           => Carbon::parse('01-'.$requestData->manufacture_year)->format('Y'),
                'NUM_MONTH_OF_MANUFACTURE'          => Carbon::parse('01-'.$requestData->manufacture_year)->format('m'),
                'ODDiscount'                        => $premium_type != "own_damage" ? (config('IC.UNITED_INDIA.BIKE.OD_DISCOUNT')) : (config('IC.UNITED_INDIA.BIKE.OD_DISCOUNT_OD')),
                'PAODPremium'                       => 0,
                'TXT_CHASSIS_NUMBER'                => randomString(17),
                'TXT_ENGINE_NUMBER'                 => randomString(17),
                'TXT_MEDICLE_COVER_LIMIT'           => '',
                'TXT_NON_ELEC_DESC'                 => 0,
                'TXT_OEM_DEALER_CODE'               => config('constants.IcConstants.united_india.bike.BIKE_OEM_DEALER_CODE'),
                'TXT_OEM_TRANSACTION_ID'            => randomString(17, 'int'),
                'TXT_PAN_NO'                        => '',
                'TXT_PREVIOUS_INSURER'              => 'ITGI',
                'TXT_PREV_INSURER_CODE'             => 'ITGI',
                'TXT_REGISTRATION_NUMBER_1'         => $requestData->business_type == "newbusiness" ? 'NEW' : explode('-', $requestData->rto_code)[0],
                'TXT_REGISTRATION_NUMBER_2'         => $requestData->business_type == "newbusiness" ? '' : explode('-', $requestData->rto_code)[1],
                'TXT_REGISTRATION_NUMBER_3'         => $requestData->business_type == "newbusiness" ? '' : 'ZO',
                'TXT_REGISTRATION_NUMBER_4'         => $requestData->business_type == "newbusiness" ? '' : randomString(4, 'int'),
                'TXT_RELATION_WITH_NOMINEE'         => 'Other',
                'TXT_TELEPHONE'                     => '',
                'TXT_TYPE_BODY'                     => $mmv->body_type,
                'YN_COMMERCIAL_FOR_PRIVATE'         => 0,
                'YN_COURTESY_CAR'                   => 0,
                'YN_DELETION_OF_IMT26'              => 0,
                'YN_DRIVING_TUTION'                 => 0,
                'YN_FOREIGN_EMBASSY'                => 0,
                'YN_HANDICAPPED'                    => 0,
                'YN_IMT32'                          => 0,
                'YN_INBUILT_LPG'                    => 0,
                'NUM_IEV_LPG_VALUE'                 => '',
                'YN_LIMITED_TO_OWN_PREMISES'        => 0,
                'YN_MEDICLE_EXPENSE'                => 0,
                'YN_PERSONAL_EFFECT'                => 0,
                'YN_VALID_DRIVING_LICENSE'          => (($is_od || !$is_individual) ? 'N' : 'Y'),

                // MMV
                'NUM_CUBIC_CAPACITY'                => $mmv->fyntune_version['fuel_type'] == 'ELECTRIC' ? ceil((float)$mmv->fyntune_version['kw']) : ceil((float)$mmv->cubic_capacity),
                'NUM_RGSTRD_SEATING_CAPACITY'       => $mmv->seating_capacity,
                'TXT_FUEL'                          => strtoupper($mmv->fuel_type) == 'CNG' ? 'PETROL/CNG' : strtoupper($mmv->fuel_type),
                'TXT_NAME_OF_MANUFACTURER'          => strtoupper(str_replace("-" ," " , $mmv->make)),
                'TXT_OTHER_MAKE'                    => $mmv->model,
                'TXT_VARIANT'                       => $mmv->variant,
                'NUM_VEHICLE_MODEL_CODE'            => '',

                // RTO
                'TXT_RTA_DESC'                      => $rto_data->txt_rta_code,
                'TXT_VEHICLE_ZONE'                  => $rto_data->txt_registration_zone,

                // ADDONS & COVERS
                'YN_NIL_DEPR_WITHOUT_EXCESS'        => $zero_dep,
                'YN_RSA_COVER'                      => $rsa_cover,
                'YN_CONSUMABLE'                     => $consumable,
                'YN_RTI_APPLICABLE'                 => $return_to_invoice,
                'YN_ENGINE_GEAR_COVER_PLATINUM'     => $engine_secure,
                'YN_LOSS_OF_KEY'                    => $key_replacement,
                'NUM_LOSS_OF_KEY_SUM_INSURED'       => (($key_replacement == 'Y' && !$is_liability) ? '10000' : ''),
                'YN_TYRE_RIM_PROTECTOR'             => $tyre_secure,
                'NUM_TYRE_RIM_SUM_INSURED'          => (($tyre_secure == 'Y') ? '50000' : '0'),
                'YN_COMPULSORY_PA_DTLS'             => $cpa_cover,
                'TXT_CPA_COVER_PERIOD'              => $cpa_cover_period,

                'YN_CLAIM'                          => (($requestData->is_claim == 'N') ? 'no' : 'yes'),
                'YN_PAID_DRIVER'                    => $yn_paid_driver,
                'TXT_COVER_PERIOD'                  => ($is_liability ? '1' : $txt_cover_period),
                'CUR_BONUS_MALUS_PERCENT'           => $vehicle_more_than_90_days ?'0' :$requestData->previous_ncb,
                'NUM_IEV_CNG_VALUE'                 => $cngLpgIDV,
                'YN_INBUILT_CNG'                    => $inbuiltCNGLPG,

                'NUM_LL1'                           => $legal_liability_to_paid_driver_flag,
                'YN_ANTI_THEFT'                     => $anti_theft_flag,

                'NUM_VOLUNTARY_EXCESS_AMOUNT'       => (string)(($is_voluntary_access == 'Y') ? $voluntary_excess_amt : 0),
                'NUM_IMPOSED_EXCESS_AMOUNT'         => '',//((isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value != 0) ? $requestData->voluntary_excess_value : ''),

                'NUM_IEV_ELEC_ACC_VALUE'            => (int)(($Electricalaccess == 'Y') ? $ElectricalaccessSI : '0'),
                'ELECTRICALACCESSORIESPREM'         => (($Electricalaccess == 'Y') ? $ElectricalaccessSI : '0'),

                'NUM_IEV_NON_ELEC_ACC_VALUE'        => (int)(($NonElectricalaccess == 'Y') ? $NonElectricalaccessSI : '0'),
                'NONELECTRICALACCESSORIESPREM'      => (($NonElectricalaccess == 'Y') ? $NonElectricalaccessSI : '0'),

                'NUM_PA_UNNAMED_AMOUNT'             => ($requestData->unnamed_person_cover_si != '' && !$is_od) ? $requestData->unnamed_person_cover_si : '0' ,

                'NUM_LL2'                           => '',
                'NUM_LL3'                           => '',
                'NUM_PAID_UP_CAPITAL'               => '',
                'NUM_PA_NAME1_AMOUNT'               => '',
                'NUM_PA_NAME2_AMOUNT'               => '',
                'NUM_PA_NAMED_AMOUNT'               => '',
                'NUM_PA_NAMED_NUMBER'               => '',
                'TXT_BANK_CODE'                     => '',
                'TXT_BANK_NAME'                     => '',
                'TXT_DRIVING_LICENSE_NO'            => '',
                'TXT_ELEC_DESC'                     => '',
                'TXT_PA_NAME1'                      => '',
                'TXT_PA_NAME2'                      => '',
                'TXT_TRANSACTION_ID'                => '',
                'TXT_UTR_NUMBER'                    => '', 
                'NUM_IEV_FIBRE_TANK_VALUE'          => '',
                'NUM_IEV_SIDECAR_VALUE'             => '',
                'NUM_LD_CLEANER_CONDUCTOR'          => '',
                'TXT_FINANCIER_BRANCH_ADDRESS1'     => '',
                'TXT_FINANCIER_BRANCH_ADDRESS2'     => '',
                'TXT_FIN_ACCOUNT_CODE_1'            => '',
                'TXT_FIN_ACCOUNT_CODE_2'            => '',
                'TXT_FIN_BRANCH_NAME_1'             => '',
                'TXT_FIN_BRANCH_NAME_2'             => '',
                'TXT_GEOG_AREA_EXTN_COUNTRY'        => '',
                'TXT_GSTIN_NUMBER'                  => '',
                'TXT_LICENSE_ISSUING_AUTHORITY'     => '',
                'TXT_MEMBERSHIP_CODE'               => '',
                'TXT_NAMED_PA_NOMINEE1'             => '',
                'TXT_NAMED_PA_NOMINEE2'             => '',
                'TXT_VAHICLE_COLOR'                 => '',
            ]
        ];
        if(strtoupper($requestData->fuel_type) == 'PETROL')
        {
            $quote_array['HEADER']['TXT_FUEL'] = 'PETROL';
        }
        if($is_liability){
            $quote_array['HEADER']['NUM_VOLUNTARY_EXCESS_AMOUNT'] = 0;
        }
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
        if($is_pos_enabled =='Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P'){
            $quote_array['HEADER']['TXT_POSP_CODE'] = $pos_data->relation_united_india;
            $quote_array['HEADER']['TXT_POSP_NAME'] = $pos_data->agent_name;
        }
            
        if($is_aa_apllicable){
            // AUTOMOBILE ASSOCIATION
            $quote_array['HEADER']['TXT_AA_FLAG']                       = 'Y';
            $quote_array['HEADER']['TXT_AA_MEMBERSHIP_NUMBER']          = '987654567654567765';
            $quote_array['HEADER']['DAT_AA_EXPIRY_DATE']                = date('m/Y', strtotime(date('Y-m-d', strtotime('+1 month', strtotime(strtr($policy_start_date, '/', '-'))))));
            $quote_array['HEADER']['TXT_AA_MEMBERSHIP_NAME']            = 'WIAA';
            $quote_array['HEADER']['TXT_AA_DISC_PREM']                  = '200';
        }
        if($is_od){
            $quote_array['HEADER']['TXT_TP_POLICY_NUMBER']          = '1234567890';
            $quote_array['HEADER']['TXT_TP_POLICY_INSURER']         = 'ITGI';
            $quote_array['HEADER']['TXT_TP_POLICY_START_DATE']      = Carbon::parse($tp_start_date)->format('d/m/Y');
            $quote_array['HEADER']['TXT_TP_POLICY_END_DATE']        = Carbon::parse($tp_end_date)->format('d/m/Y');
            $quote_array['HEADER']['TXT_TP_POLICY_INSURER_ADDRESS'] = 'rt, jhgfdjhgfd';
            $quote_array['HEADER']['TXT_TP_POLICY_NUMBER']          = '1234567890';
            $quote_array['HEADER']['TXT_TP_POLICY_INSURER']         = 'ITGI';
            $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE']     = $requestData->applicable_ncb == 0 ? 50 : 60;
        }

        if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy')
        {
            
        
        if ($is_new && !$is_liability ) {
            $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_NEWBUSINESS_WITH_NCB'); //80
        } elseif ($premium_type == "comprehensive" && $interval->y <= 9 &&  $requestData->applicable_ncb != 0) {
            $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_COMPREHENSIVE_WITH_NCB');//80
        } elseif ($premium_type == "own_damage" && $interval->y <= 5 && $requestData->applicable_ncb != 0) {
            $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_OWN_DAMAGE_WITH_NCB');//70
        } elseif ($interval->y >= 15) {
            $Updated_discount_grid = 0;
        } else {
            $Updated_discount_grid = 0; // Default value if none of the above conditions are met
        }
        
      
        if ($requestData->applicable_ncb == 0 && !$is_new) {
            if ($premium_type == "comprehensive" && $interval->y <= 9 ) {
                $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_COMPREHENSIVE_WITHOUT_NCB');//70
            } elseif ($premium_type == "own_damage" && $interval->y <= 5 ) {
                $Updated_discount_grid = config('IC.UNITED_INDIA.V1.BIKE.DISCOUNT_GRID_OWNDAMAGE_WITHOUT_NCB');//60
            }elseif ($interval->y >= 15) {
                $Updated_discount_grid = 0;
            } else {
                $Updated_discount_grid = 0; // Default value if none of the above conditions are met
            }
        }


            $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $Updated_discount_grid; 
       
        // if(!$Updated_discount_grid){
        //     return  [
        //         'status'            => false,
        //         'message'           =>  $mmv->model .' Not Available In Discount Grid ',
        //     ];
        // }

}

        $request_container = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
            <soapenv:Header/>
            <soapenv:Body>
                <ws:calculatePremium>
                    <application>'.config('constants.IcConstants.united_india.bike.BIKE_APPLICATION_ID').'</application>
                    <userid>'.config('constants.IcConstants.united_india.bike.BIKE_USER_ID').'</userid>
                    <password>'.config('constants.IcConstants.united_india.bike.BIKE_USER_PASSWORD').'</password>
                    <proposalXml>
                        <![CDATA[#replace]]>
                    </proposalXml>
                    <productCode>'.config('constants.IcConstants.united_india.bike.BIKE_PRODUCT_CODE').'</productCode>
                    <subproductCode>'.config('constants.IcConstants.united_india.bike.BIKE_SUBPRODUCT_CODE').'</subproductCode>
                </ws:calculatePremium>
            </soapenv:Body>
            </soapenv:Envelope>
        ';

        // quick quote service input
        $temp_data = $quote_array;
        unset($temp_data['HEADER']['TXT_CHASSIS_NUMBER'], $temp_data['HEADER']['TXT_ENGINE_NUMBER'], $temp_data['HEADER']['TXT_OEM_TRANSACTION_ID'], $temp_data['HEADER']['TXT_REGISTRATION_NUMBER_4']);
        $checksum_data = checksum_encrypt($temp_data);
        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'united_india',$checksum_data,"BIKE");

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'xml',
            'section'           => 'Bike',
            'method'            => 'Premium Calculation',
            'transaction_type'  => 'quote',
            'root_tag'          => 'ROOT',
            'checksum'          => $checksum_data,
            'soap_action'       => 'calculatePremium',
            'container'         => $request_container,
            'productName'       => $productData->product_name,
        ];

        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
            $get_response = $is_data_exist_for_checksum;
        }else{
            $get_response = getWsData(config('constants.IcConstants.united_india.bike.BIKE_END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
        }

        $response=$get_response['response'];
        if ($response) {
            $quote_output = html_entity_decode($response);
            $quote_output = XmlToArray::convert($quote_output);
            
            $header         = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER'];

            // echo "<pre>";print_r([
            //     $header,
            //     $quote_array,
            //     json_encode($quote_array)
            // ]);echo "</pre>";die();

            $error_message  = $header['TXT_ERR_MSG'];

            if($error_message != [] && $error_message != ''){
                return [
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'status'    => false,
                    'msg'       => $error_message,
                    'message'   => json_encode($error_message),
                    'quote_array' => $quote_array
                ];
            }
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success" );

            $total_idv  = $header['NUM_IEV_BASE_VALUE'];

            $min_idv = (int) round($total_idv * 0.9);
            $max_idv =  (int) round($total_idv * 1.2);

            // IDV change

            $quote_array['HEADER']['NUM_IEV_BASE_VALUE'] = $min_idv;
            $quote_array['Uid'] = date('ymd').time().rand(100,999);

            if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y')
            {
                if ($requestData->edit_idv >= $max_idv)
                {
                    $quote_array['HEADER']['NUM_IEV_BASE_VALUE'] = $max_idv;
                }
                elseif ($requestData->edit_idv <= $min_idv)
                {
                    $quote_array['HEADER']['NUM_IEV_BASE_VALUE']  = $min_idv;
                }
                else
                {
                    $quote_array['HEADER']['NUM_IEV_BASE_VALUE']  = $requestData->edit_idv;
                }
            }else{
                $quote_array['HEADER']['NUM_IEV_BASE_VALUE']  = $min_idv;
            }

            // quick quote service input
            $additional_data['method'] = 'Premium Re-Calculation';

            $temp_data = $quote_array;
            unset($temp_data['HEADER']['TXT_CHASSIS_NUMBER'], $temp_data['HEADER']['TXT_ENGINE_NUMBER'], $temp_data['HEADER']['TXT_OEM_TRANSACTION_ID'], $temp_data['HEADER']['TXT_REGISTRATION_NUMBER_4'], $temp_data['Uid']);    
            $checksum_data = checksum_encrypt($temp_data);
            $additional_data['checksum'] = $checksum_data;
            $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'united_india',$checksum_data,"BIKE");
            if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                $get_response = $is_data_exist_for_checksum;
            }else{
                $get_response = getWsData(config('constants.IcConstants.united_india.bike.BIKE_END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
            }

            $response=$get_response['response'];
            if ($response)
            {
                $quote_output = html_entity_decode($response);
                $quote_output = XmlToArray::convert($quote_output);

                $header       = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER'];
                
                $error_message  = $header['TXT_ERR_MSG'];

                if($error_message != [] && $error_message != ''){
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status'    => false,
                        'msg'       => $error_message,
                        'message'   => json_encode($error_message)
                    ];
                }

            }
            else
            {
                return  [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => 'Bike Insurer Not found',
                ];
            }

            // END IDV change

            $total_od_premium       = 0;
            $total_tp_premium       = 0;
            $od_premium             = 0;
            $tp_premium             = 0;
            $liability              = 0;
            $pa_owner               = 0; 
            $pa_unnamed             = 0;
            $lpg_cng_amount         = 0;
            $lpg_cng_tp_amount      = 0;
            $electrical_amount      = 0;
            $non_electrical_amount  = 0;
            $ncb_discount           = 0;
            $discount_amount        = 0;
            $bonus_discount         = 0;
            $tppd                   = 0;
            $detariff_discount = 0;

            $base_cover = [
                'od_premium'            =>  0,
                'tp_premium'            =>  0,
                'pa_owner'              =>  0,
                'liability'             =>  0,
                'eng_prot'              =>  0,
                'return_to_invoice'     =>  0,
                'road_side_assistance'  =>  0,
                'zero_dep_amount'       =>  0,
                'medical_expense'       =>  0,
                'consumable'            =>  0,
                'key_replacement'       =>  0,
                'tyre_secure'           =>  0,
                'tppd'           =>  0,
            ];
            $base_cover_codes = [
                'od_premium'        =>  'Basic OD',
                'tp_premium'        =>  'Basic TP',
                'pa_owner'          =>  'PA Owner Driver',
                'liability'         =>  'LL to Paid Driver IMT 28',
                'eng_prot'          =>  'Engine and Gearbox Protection Platinum AddOn Cover',
                'return_to_invoice' =>  'Return To Invoice',
                'road_side_assistance' => 'Road Side Assistance',
                'zero_dep_amount'   =>  'Nil Depreciation Without Excess',
                'medical_expense'   =>  'Medical Expenses',
                'consumable'        =>  'Consumables Cover',
                'key_replacement'   =>  'Loss Of Key Cover',
                'tyre_secure'       =>  'Tyre And Rim Protector Cover',
                'tppd'              =>  'TPPD Discount',
            ];
            $base_cover_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];

            $discount_codes = [
                'bonus_discount'            =>  'No Claim Bonus Discount',
                'anti_theft_discount'       =>  'Anti-Theft Device - OD',
                'automobile_association'    =>  'Automobile Association Discount',
                'voluntary'                 =>  'Voluntary Excess Discount-OD',
                'detariff'                  =>  'Detariff Discount',
            ];
            $match_arr = [
                'name'  => 'PropLoadingDiscount_Description',
                'value' => 'PropLoadingDiscount_CalculatedAmount',
            ];
            $discount = [
                'bonus_discount'            =>  0,
                'anti_theft_discount'       =>  0,
                'automobile_association'    =>  0,
                'voluntary'                 =>  0,
                'detariff' => 0
            ];

            $cng_codes = [
                'lpg_cng_tp_amount'     =>  'CNG Kit-TP',
                'lpg_cng_amount'        =>  'CNG Kit-OD',
            ];
            $cng_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];
            $cng = [
                'lpg_cng_tp_amount'     =>  0,
                'lpg_cng_amount'        =>  0,
            ];

            $loading_code = [
                'cng_od' => 'Built in CNG - OD loading - OD',
                'cng_tp' => 'Built in CNG-TP Loading-TP'
            ];

            $loading = [
                'cng_od' => 0,
                'cng_tp' => 0,
            ];

            $worksheet = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['TXT_PRODUCT_USERDATA']['WorkSheet'];

            // echo "<pre>";print_r([$worksheet]);echo "</pre>";die();
            
            // print_pre(['foreach block PropRisks_Co -> Risks', $worksheet['PropRisks_Col']['Risks']]);
            

            // return [$worksheet, $quote_array['HEADER']];

            if(isset($worksheet['PropRisks_Col']['Risks'][0])){
                foreach ($worksheet['PropRisks_Col']['Risks'] as $risk_key => $risk_value)
                {
                    if(is_array($risk_value) && isset($risk_value['PropRisks_VehicleSIComponent']))
                    {
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Vehicle Base Value')
                        {
                            $base_cover = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                            if(!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0]))
                            {
                                $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                            $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                                else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                            $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                            }
                            else{
                                foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                    if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP' )
                                    {

                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                            if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                                if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        }
                                    }
                                    else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD' )
                                    {
                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                            if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                                if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                                $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'CNG')
                        {
                            $cng = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Unnamed PA Cover')
                        {
                            $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Electrical Accessories')
                        {
                            $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if($risk_value['PropRisks_VehicleSIComponent'] == 'Non-Electrical Accessories')
                        {
                            $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                        }
                    }
                }
            }else{
                $risk_value = $worksheet['PropRisks_Col']['Risks'];
                if(is_array($risk_value) && isset($risk_value['PropRisks_VehicleSIComponent'])){
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Vehicle Base Value')
                    {
                        $base_cover = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                        if(!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0]))
                        {
                            $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                            if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP')
                            {
                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                        $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            }
                            else if ($v['PropCoverDetails_CoverGroups'] == 'Basic - OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic OD')
                            {
                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                        $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }
                                }
                            }
                        }
                        else{
                            foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                            $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                                else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD')
                                {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount') {
                                            $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'CNG')
                    {
                        $cng = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Unnamed PA Cover')
                    {
                        $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Electrical Accessories')
                    {
                        $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if($risk_value['PropRisks_VehicleSIComponent'] == 'Non-Electrical Accessories')
                    {
                        $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                    }
                }
            }

            if(!$is_liability){
                if(is_array($worksheet['PropLoadingDiscount_Col']) && !empty($worksheet['PropLoadingDiscount_Col'])){
                    $discount = united_india_cover_addon_values($worksheet['PropLoadingDiscount_Col']['LoadingDiscount'], $discount_codes, $match_arr, $discount);
                    $loading = united_india_cover_addon_values($worksheet['PropLoadingDiscount_Col']['LoadingDiscount'], $loading_code, $match_arr, $loading);
                }
            }

            //inbuilt cng kit (bajaj freedom)
            $cng['lpg_cng_amount'] += $loading['cng_od'];
            $cng['lpg_cng_tp_amount'] += $loading['cng_tp'];

            $base_cover['tppd'] = $tppd;
            $discount['detariff'] = $detariff_discount;


            $idv = ($quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['NUM_IEV_BASE_VALUE']);

            if(($requestData->business_type == 'breakin') && $idv > 50000){
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Quotes not allowed if idv is more than 50000 for Break-In',
                    'IDV Amount' => $idv
                ];
            }

            if($is_liability){
                $add_on_data = [
                    'in_built'     => [],
                    'additional' => [
                        // 'key_replace'                       => 0,
                        // 'consumables'                       => 0,
                        // 'tyre_secure'                       => 0,
                        // 'return_to_invoice'                 => 0,
                        // 'lopb'                              => 0,
                        'zero_depreciation'                 => 0,
                        // 'engine_protector'                  => 0,
                        'road_side_assistance'              => 0,
                        // 'ncb_protection'                    => 0,
                    ],
                    'other'            => []
                ];
            }
            if($is_zero_dep){
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'             => $base_cover['zero_dep_amount'],
                    ],
                    'additional' => [
                        // 'key_replace'                   => ($vehicle_age <= 5) ? $base_cover['key_replacement'] : 0,
                        'engine_protector'              => ($vehicle_age <= 5) ? $base_cover['eng_prot'] : 0,
                        // 'ncb_protection'                => 0,
                        'consumables'                   => ($vehicle_age <= 5) ? $base_cover['consumable'] : 0,
                        // 'tyre_secure'                   => ($vehicle_age <= 3) ? $base_cover['tyre_secure'] : 0,
                        'return_to_invoice'             => ($vehicle_age < 3)  ? $base_cover['return_to_invoice'] : 0,
                        'road_side_assistance'          => $base_cover['road_side_assistance'],
                        // 'lopb'                          => 0
                    ],
                    'other'      => [],
                ];
            }
            else{
                $add_on_data = [
                    'in_built'   => [],
                    'additional' => [
                        'zero_depreciation'             => $base_cover['zero_dep_amount'],
                        // 'key_replace'                   => ($vehicle_age <= 5) ? $base_cover['key_replacement'] : 0,
                        'engine_protector'              => ($vehicle_age <= 5) ? $base_cover['eng_prot'] : 0,
                        // 'ncb_protection'                => 0,
                        'consumables'                   => ($vehicle_age <= 5) ? $base_cover['consumable'] : 0,
                        // 'tyre_secure'                   => ($vehicle_age <= 3) ? $base_cover['tyre_secure'] : 0,
                        'return_to_invoice'             => 0,
                        'road_side_assistance'          => $base_cover['road_side_assistance'],
                        // 'lopb'                          => 0
                    ],
                    'other'      => [],
                ];
            }

            $in_built_premium = 0;
            foreach ($add_on_data['in_built'] as $key => $value) {
                $in_built_premium = $in_built_premium + $value;
            }

            $additional_premium = 0;
            // return $add_on_data['additional'];
            foreach ($add_on_data['additional'] as $key => $value) {
                $additional_premium = $additional_premium + $value;
            }

            $other_premium = 0;
            foreach ($add_on_data['other'] as $key => $value) {
                $other_premium = $other_premium + $value;
            }

            $add_on_data['in_built_premium'] = $in_built_premium;
            $add_on_data['additional_premium'] = $additional_premium;
            $add_on_data['other_premium'] = $other_premium;


            $applicable_addons = [
                'zeroDepreciation',
                // 'roadSideAssistance',
                // 'lopb',
                // 'keyReplace',
                'consumables',
                // 'tyreSecure',
                'engineProtector',
                'returnToInvoice',
                // 'ncbProtection',
            ];
            if (!$is_zero_dep_applicable) {
                 array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                 array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }
            if ($vehicle_age >= 3) {
                 array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            }

            if ($vehicle_age >= 5) {
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
            }

            $final_total_premium = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['CUR_FINAL_TOTAL_PREMIUM'];

            // return $quote_output;

            $total_od_premium           = $base_cover['od_premium'] + $cng['lpg_cng_amount'] + $non_electrical_amount + $electrical_amount;
            $total_tp_premium           = $base_cover['tp_premium'] + $base_cover['liability'] + $cng['lpg_cng_tp_amount'] + $pa_unnamed;
            $ncb_discount = $requestData->business_type == 'newbusiness' ? 0 : $discount['bonus_discount'];
            $total_discount_premium     =  $ncb_discount + $discount['voluntary'] + $base_cover['tppd'] + $discount['anti_theft_discount'] + $discount['detariff'];//$discount['bonus_discount'] + $discount['automobile_association'] + $discount['anti_theft_discount'] + $discount['voluntary'];
            $total_base_premium         = $total_od_premium +  $total_tp_premium - $total_discount_premium;

            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : ($idv),
                    'min_idv' => $premium_type == 'third_party' ? 0 : ($min_idv),
                    'max_idv' => $premium_type == 'third_party' ? 0 : ($max_idv),
                    'qdata' => NULL,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Own Damage')),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => "", //$premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => $discount['voluntary'],
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => $premium_type == 'third_party' ? 0 : $idv,
                    'fuel_type' => $requestData->fuel_type,
                    'vehicle_idv' => $premium_type == 'third_party' ? 0 : $idv,
                    'ncb_discount' =>  ($is_liability ? 0 : $requestData->applicable_ncb),
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
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
                        'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $motor_manf_date,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => ($discount['detariff']), // ($insurer_discount)
                    ],
                    'ic_vehicle_discount' => ($discount['detariff']),
                    'basic_premium' => $base_cover['od_premium'],
                    'deduction_of_ncb' => $requestData->business_type == 'newbusiness' ? '0' : $discount['bonus_discount'],
                    'tppd_premium_amount' => $base_cover['tp_premium'],
                    'seating_capacity' => $mmv->seating_capacity,
                    'compulsory_pa_own_driver' => $base_cover['pa_owner'],
                    'total_accessories_amount(net_od_premium)' => "", //$total_accessories_amount,
                    'total_own_damage' => $total_od_premium, //$final_od_premium,
                    'total_liability_premium' => $total_tp_premium,
                    'net_premium' => $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['CUR_NET_FINAL_PREMIUM'], //$result['result']['plans'][0]['tenures'][0]['premium']['net']['value'],
                    'service_tax_amount' => "", //$result['result']['plans'][0]['tenures'][0]['premium']['gst']['value'],
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => '', //$result['result']['plans'][0]['tenures'][0]['premium']['gross']['value'],
                    'final_od_premium' => ($total_od_premium) ?? 0,
                    'final_tp_premium' => ($total_tp_premium) ?? 0, //$final_tp_premium,
                    'final_total_discount' => ($total_discount_premium),
                    'final_net_premium' => ($total_base_premium) ?? 0,
                    'tppd_discount' => ($base_cover['tppd']),
                    'final_gst_amount' => ($quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['CUR_FINAL_SERVICE_TAX']) ?? 0,
                    'final_payable_amount' => ($final_total_premium) ?? 0,
                    'service_data_responseerr_msg' => '',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll Over')),
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => $vehicle_in_90_days,
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
                    'add_ons_data' =>    $add_on_data,
                    'applicable_addons' => $applicable_addons,
                    'base_cover' => $base_cover
                ],
                'premium_data' => [
                    'base_cover'                => $base_cover,
                    'cng'                       => $cng,
                    'discount'                  => $discount,
                    'pa_unnamed'                => $pa_unnamed,
                    'electrical_amount'         => $electrical_amount,
                    'non_electrical_amount'     => $non_electrical_amount,
                ]
            ];

            // $PAforaddionaldPaidDriver       = "Y";
            // $PAPaidDriverConductorCleaner   = "Y";

            if($externalCNGKIT == 'Y'  || $FuelType == "CNG")
            {
                $data_response['Data']['cng_lpg_tp'] = $cng['lpg_cng_tp_amount'];
            }
            if($llpaidDriver == 'Y')
            {
                $data_response['Data']['default_paid_driver'] = $base_cover['liability'];
            }
            if($PAforUnnamedPassenger == 'Y')
            {
                $data_response['Data']['cover_unnamed_passenger_value'] = $pa_unnamed;
            }

            if($is_anti_theft == 'Y')
            {
                $data_response['Data']['antitheft_discount'] = $discount['anti_theft_discount'];
            }

            // if($is_tppd == 'Y')
            // {
            //     $data_response['Data']['tppd_discount'] = $tppd;
            // }

            // if($is_voluntary_access == 'Y')
            // {
            //     $data_response['Data']['voluntary_excess'] = (isset($response['data']['C10']) ? ($response['data']['C10']['rate']) : 0);
            // }

            if($Electricalaccess == 'Y')
            {
                $data_response['Data']['motor_electric_accessories_value'] = $electrical_amount;
            }
            if($NonElectricalaccess == 'Y')
            {
                $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical_amount;
            }
            if($externalCNGKIT == 'Y' || $FuelType == "CNG")
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = $cng['lpg_cng_amount'];
            }
            if(isset($cpa_cover_period)&&$requestData->business_type == 'newbusiness' && $cpa_cover_period == '5')
            {
                $data_response['Data']['multi_Year_Cpa'] = $base_cover['pa_owner'];
            }
            return camelCase($data_response);

        }
        else{
            return  [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => 'Bike Insurer Not found',
            ];
        }
    }
    catch(Exception $e){
        return  [
            'premium_amount'    => '0',
            'status'            => false,
            'message'           => 'Bike Insurer Not found '.$e->getMessage().' '.$e->getLine(),
        ];
    }
}


function united_india_cover_addon_values($value_arr, $cover_codes, $match_arr, $covers){
    if(!isset($value_arr[0]))
    {
        $value = $value_arr;
        foreach ($cover_codes as $k => $v) {
            if($value[$match_arr['name']] == $v)
            {
                $covers[$k] = ($value[$match_arr['value']]);
            }
        }
    }
    else
    {
        foreach ($value_arr as $key => $value)
        {
            foreach ($cover_codes as $k => $v) {
                if($value[$match_arr['name']] == $v)
                {
                    $covers[$k] = ($value[$match_arr['value']]);
                }
            }
        }
    }
    return $covers;
}

function united_mmv_check($mmv){
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return    [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' =>$mmv
        ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' =>$mmv
        ]);
    } elseif ($mmv->ic_version_code == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' =>$mmv
        ]);
    }else{
        return ['status' => true, 'mmv_data' => $mmv ,'request' =>$mmv];
    }
}
function randomString($length = 17, $type = 'char') {
    $characters = (($type == 'char') ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' : (($type == 'int') ? '0123456789' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
    $charactersLength = strlen($characters);
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, $charactersLength - 1)];
    }
    return $random_string;
}