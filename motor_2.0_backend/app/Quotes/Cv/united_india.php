<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\unitedIndiaRtoCityDiscountCv;
use App\Models\unitedIndiaWithNcbDiscountGridCv;
use App\Models\unitedIndiaWithoutNcbDiscountGridCv;


include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
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
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
        // $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
        $is_breakin     = (((strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')) ? false : true);
        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
        $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

        $motor_manf_date = '01-' . $requestData->manufacture_year;

        if (empty($requestData->rto_code)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request' => [
                    'rto_code' => $requestData->rto_code,
                    'message' => 'RTO not available',
                ]
            ];
        }

        if(!$is_new && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Break-In Quotes Not Allowed',
                'request' => [
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'message' => 'Break-In Quotes Not Allowed',
                ]
            ];
        }

        $mmv = get_mmv_details($productData, $requestData->version_id, 'united_india');

        $parent_id = get_parent_code($productData->product_sub_type_id);

        $mmv_data = united_mmv_check($mmv);

        if (!$mmv_data['status']) {
            return $mmv_data;
        }

        $mmv = $mmv_data['mmv_data'];

        $product_code = config('constants.IcConstants.united_india.CV.PRODUCT_CODE');
        $bus_category = '';

        if($parent_id == 'PCV' && $mmv->carryingcapacity <= 6){
            $sub_product_code = config('constants.IcConstants.united_india.CV.C1A.SUB_PRODUCT_CODE');
        }
        
        if ($parent_id == 'PCV' && $mmv->carryingcapacity > 6) {
            $sub_product_code = config('constants.IcConstants.united_india.CV.C1B.SUB_PRODUCT_CODE');

            $bus_category = 'Others';
        }

        if ($parent_id == 'GCV') {
            $sub_product_code = config('constants.IcConstants.united_india.CV.GCV.SUB_PRODUCT_CODE');
        }


        if($productData->product_sub_type_id == 10){
            $bus_category = 'School Bus';
        }

        // if (str_contains($mmv->variant_period, '+')) {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Invalid vehicle variant period',
        //         'request' => [
        //             'variant_period' => $mmv->variant_period,
        //             'message'          => 'Invalid vehicle variant period',
        //         ]
        //     ];
        // }

        // $str["from"]   = strpos($mmv->variant_period, "[");
        // $str["to"]     = strpos($mmv->variant_period, "]");
        // $str["rem"]    = $str["to"] - $str["from"];

        // $str["period"] = explode(' - ', substr($mmv->variant_period, $str["from"] + 1, $str["rem"] - 1));

        // $veh_start_period = $str["period"][0];
        // $veh_end_period = ((!isset(["period"][1]) || empty($str["period"][1]) || (trim($str["period"][1]) == '')) ? (date('Y') + 1) : $str["period"][1]);

        // if (!$is_liability) {
        //     if ((Carbon::parse($motor_manf_date)->format('Y') < $veh_start_period || Carbon::parse($motor_manf_date)->format('Y') > $veh_end_period) && config('constants.IcConstants.united_india.IS_VARIANT_PERIOD_ACTIVE') == 'Y') {
        //         return [
        //             'premium_amount' => 0,
        //             'status' => false,
        //             'message' => 'Vehicle manufacturing year does not match with variant period ' . $mmv->variant_period . '.',
        //             'request' => [
        //                 'variant_period' => $mmv->variant_period,
        //                 'veh_start_period' => $veh_start_period,
        //                 'veh_end_period' => $veh_end_period,
        //                 'motor_manf_date' => $motor_manf_date,
        //             ]
        //         ];
        //     }
        // }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $selected_addons        = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons                 = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories            = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers      = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts              = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $addons_v2              = ($selected_addons->addons == null ? [] : $selected_addons->addons);

        // additional covers
        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger =  $NonElectricalaccess = $llpaidDriver = $imt23 = "N";
        $externalCNGKITSI = $ElectricalaccessSI = $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $NonElectricalaccessSI = $llpaidDriverCleanerSI = $imt23SI = $llpaidDriverSI = $llpaidDriverConductorSI = 0;
        $nfpp = false;
        $nfpp_si = 0;
        $geo_extn_countries = '';

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
        }

        foreach ($additional_covers as $key => $value) {
            if (in_array('PA cover for additional paid driver', $value)) {
                $PAforaddionaldPaidDriver       = 1;
                $PAforaddionaldPaidDriverSI     = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger          = "Y";
                $PAforUnnamedPassengerSI        = $value['sumInsured'];
            }

            if (in_array('IMT - 23', $value)) {
                $imt23          = -1;
                $imt23SI        = $value['sumInsured'];
            }

            if (in_array($value['name'], ['LL paid driver', 'LL paid driver/conductor/cleaner'])) {
                $llpaidDriver                   = "Y";
                $llpaidDriverCleanerSI          = $value['LLNumberCleaner'] ?? 0;
                $llpaidDriverSI                 = $value['LLNumberDriver'] ?? 0;
                $llpaidDriverConductorSI        = $value['LLNumberConductor'] ?? 0;
            }

            if (in_array('PA paid driver/conductor/cleaner', $value)) {
                $PAforaddionaldPaidDriver       = 3;
                $PAforaddionaldPaidDriverSI     = $value['sumInsured'];
            }

            if(in_array('Geographical Extension', $value)){
                foreach($value['countries'] as $country){
                    if($country){
                        $geo_extn_countries .= $geo_extn_countries == '' ? $country : '/' . $country;
                    }
                }
            }

            if(in_array('NFPP Cover', $value)){
                $nfpp = true;
                $nfpp_si = $value['nfppValue'];
            }
        }
        $LLnumberSum = 0;
        $LLnumberSum = $llpaidDriverCleanerSI + $llpaidDriverSI + $llpaidDriverConductorSI;
        // end additional covers

        // discounts
        $is_tppd = false;
        $is_anti_theft = false;
        $is_limited_to_own_premises = 0;

        foreach ($discounts as $key => $discount) {
            if (in_array('TPPD Cover', $discount)) {
                $is_tppd = true;
            }

            if (in_array('Vehicle Limited to Own Premises', $discount)) {
                $is_limited_to_own_premises = -1;
            }

            if (!$is_liability && in_array('anti-theft device', $discount)) {
                $is_anti_theft = true;
            }
        }
        // end discounts

        $date1          = new DateTime($requestData->vehicle_register_date);
        $date2          = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval       = $date1->diff($date2);
        $age            = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age    = floor($age / 12);

        // if ($vehicle_age > 8) {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Car Age greater than 8 years',
        //         'request' => [
        //             'vehicle_age' => $vehicle_age,
        //             'message' => 'Car Age greater than 8 years',
        //         ]
        //     ];
        // }

        // if ($is_zero_dep && $vehicle_age >= 3) {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 3 Years',
        //         'request' => [
        //             'vehicle_age' => $vehicle_age,
        //             'is_zero_dep' => false,
        //             'message' => 'Zero Depriciation Cover Is Not Available For Vehicle Age More than 3 Years',
        //         ]
        //     ];
        // }

        $vehicle_in_90_days = 0;

        $current_date = date('Y-m-d');
        if ($requestData->previous_policy_expiry_date != 'New') {
            $datetime2      = new DateTime($current_date);
            $datetime1      = new DateTime($requestData->previous_policy_expiry_date);
            $intervals      = $datetime1->diff($datetime2);
            $difference     = $intervals->invert;
        } else {
            $requestData->previous_policy_expiry_date = $current_date;
        }

        if ($requestData->business_type == "newbusiness") {
            $policy_start_date  = date('d-m-Y');
            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
        } else {
            $policy_start_date  = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
            }

            $policy_end_date    = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 1 year'));
        }

        $tp_start_date      = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
        $tp_end_date        = date('d-m-Y', strtotime($tp_start_date . '+1 year'));

        $mmv_data = [
            'manf_name'             => $mmv->manufacturer,
            'model_name'            => $mmv->vehiclemodel,
            'version_name'          => $mmv->txt_variant,
            'seating_capacity'      => $mmv->seatingcapacity,
            'carrying_capacity'     => $mmv->carryingcapacity,
            'cubic_capacity'        => $mmv->cubiccapacity,
            'fuel_type'             => $mmv->txt_fuel,
            'gross_vehicle_weight'  => $mmv->grossvehicleweight,
            'vehicle_type'          => !empty($mmv->body_type) ? $mmv->body_type : $mmv->fyntune_version['vehicle_built_up'] ?? '',
            'version_id'            => $mmv->ic_version_code,
        ];

        $mmvdata = (array)$mmv;
        // $mmv->idv = $mmvdata['ex_showroom_price'];
        $mmv->idv = isset($mmv->{'exshowroom price'}) ? $mmv->{'exshowroom price'} : ($mmv->exshowroom_price ?? 0); //exshowroom price for idv.

        $FuelType       = strtoupper($mmv->txt_fuel);
        $cngLpgIDV      = $externalCNGKITSI;
        $inbuiltCNG     = -1;
        $inbuiltLPG     = -1;
        if ($FuelType == 'PETROL' || $FuelType == 'DIESEL') {
            $inbuiltCNG = 0;
            $inbuiltLPG = 0;
            if ($cngLpgIDV == 0 || $cngLpgIDV == '') {
                $cngLpgIDV = 0;
            }
        } elseif ($FuelType == 'CNG') {
            $inbuiltLPG     = 0;
        } elseif ($FuelType == 'LPG') {
            $inbuiltCNG = 0;
        }

        $customer_type  = $requestData->vehicle_owner_type == "I" ? "Individual" : "organization";

        $btype_code     = $requestData->business_type == "rollover" ? "2" : "1";
        // $btype_name     = ($requestData->business_type == 'newbusiness') ? 'New Business' : (($requestData->business_type == "breakin" || ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party')) ? 'Breakin' : 'Roll Over');
        $btype_name = ($requestData->business_type == "rollover" || $is_breakin) ? "Roll Over" : "New Business";
        
        if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
            $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
        } else {
            $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
        }

        //Age validations
        $yn_paid_driver                     = 'N';
        $zero_dep                           =  0;
        $consumable                         = 'N';
        $engine_secure                      = 'N';
        $tyre_secure                        = 'N';
        $key_replacement                    = 'N';
        $return_to_invoice                  = 'N';
        $imt_23_inclusion                   =  0;
        $road_side_assistance               = 'N';  //as per the ic
        $ncb_protection                     = False;  //as per the ic
        $addtowcharge                       = 0;

        foreach ($addons_v2 as $key => $value) {
            if (in_array('Additional Towing', $value)) {
                $addtowcharge = isset($value['sumInsured']) ? $value['sumInsured'] : 20000;
            }
        }

        if ($is_individual && $llpaidDriver == 'N') {
            $yn_paid_driver                         = 'Y';
        }

        $is_individual = $requestData->vehicle_owner_type == 'I';
        $cpa_cover                          = ($is_individual ? -1 : 0);
        $cpa_cover_period                   = ($is_individual ? 1 : '');
        $anti_theft_flag                    = ((!$is_liability && ($requestData->anti_theft_device == 'Y')) ? -1 : 0);
        $legal_liability_to_paid_driver_flag = ($llpaidDriver == "N" ? '0' : '1');

        //RTO Details
        $rto_data = DB::table('united_india_rto_master')->where('TXT_RTA_CODE', strtr($requestData->rto_code, ['-' => '']))->first();

        $rto_data = keysToLower($rto_data);
        if (empty($rto_data)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request' => [
                    'rto_code' => $requestData->rto_code,
                    'rto_data' => $rto_data
                ]
            ];
        }

        // Quick Quote Service
        $proposal_date = date('d/m/Y');
        if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != "NEW") {
            $requestData->vehicle_registration_no = formatRegistrationNo($requestData->vehicle_registration_no);
            $reg_no = explode("-", $requestData->vehicle_registration_no);
        } else {
            $reg_no = explode("-", $requestData->rto_code);
        }

        //Registration number
        $registration_1 = $reg_no[0];
        $registration_2 = $reg_no[1];
        if ($btype_name == 'New Business') {
            $registration_1 = 'New';
            $registration_2 = '';
        }
        $registration_3 = isset($reg_no[2]) ? $reg_no[2] : substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);
        $registration_4 = isset($reg_no[3]) ? $reg_no[3] : sprintf("%04d", rand(0, 9999));
        $chassis_number = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 15);
        $engine_number = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 15);
        if (strlen($registration_3) == 3 && $registration_2 < 10) $registration_2 = str_replace('0', '', $registration_2);


        if($is_liability){
            $zero_dep = 0;
            $consumable = 'N';
            $engine_secure = 'N';
            $tyre_secure = 'N';
            $key_replacement = 'N';
            $return_to_invoice = 'N';
            $road_side_assistance = 'N';
            $ncb_protection = False;
            $addtowcharge = 0;
        }

        // dd($parent_id, $mmv->carryingcapacity);

        if($productData->product_identifier == 'BASIC_ADDONS'){
            $consumable                         = 'Y';
            $imt_23_inclusion                   =  0;
            $road_side_assistance               = 'Y';
            $addtowcharge                       = isset($addtowcharge) ? $addtowcharge : 20000;
        }

        if($productData->product_identifier == 'imt_23'){
            $consumable                         = 'Y';
            $imt_23_inclusion                   =  -1;
            $road_side_assistance               = 'Y';
            $addtowcharge                       = isset($addtowcharge) ? $addtowcharge : 20000;
        }

        if($productData->product_identifier == 'zero_dep'){
            $zero_dep                           = -1;
            $consumable                         = 'Y';
            $imt_23_inclusion                   = $parent_id == 'GCV' || ($parent_id == 'PCV' && $mmv->carryingcapacity > 6) ? -1 : 0;
            $road_side_assistance               = 'Y';
            $addtowcharge                       = isset($addtowcharge) ? $addtowcharge : 20000;
        }

        if(config('CHECK_DIAMLER_MANUFACTURER') == 'Y' && ($mmv->manufacturer = 'DIAMLER (DICV)' || $mmv->manufacturer = 'DIAMLER INDIA COMMERCIAL VEHICLES P LTD.')){
            $mmv->manufacturer = 'BHARAT BENZ';
        }
        
        $quote_array = [
            'HEADER' => [
                // 'BIFUELKITODPREMIUM'                => 0,
                // 'BIFUELKITTPPREMIUM'                => 0,
                // 'CUR_DEALER_GROSS_PREM'             => 0,
                // 'CUR_DEALER_NET_OD_PREM'            => 0,
                // 'CUR_DEALER_NET_TP_PREM'            => 0,
                // 'CUR_DEALER_SERVICE_TAX'            => 0,

                // POLICY AND BUSINESS TYPE
                'NUM_CLIENT_TYPE'                   => ($is_individual ? 'I' : 'C'),
                'NUM_POLICY_TYPE'                   => ($is_package ? 'PackagePolicy' :  'LiabilityOnly'), // add
                'NUM_BUSINESS_CODE'                 => $btype_name,

                // DATES
                'DAT_PREV_POLICY_EXPIRY_DATE'       => Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y'),
                'DAT_PROPOSAL_DATE'                 => $proposal_date,
                'DAT_DATE_OF_EXPIRY_OF_POLICY'      => Carbon::parse($policy_end_date)->format('d/m/Y'),
                'DAT_DATE_OF_ISSUE_OF_POLICY'       => Carbon::parse($policy_start_date)->format('d/m/Y'),
                'DAT_DATE_OF_REGISTRATION'          => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                'DAT_DATE_OF_PURCHASE'              => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                // 'DAT_UTR_DATE'                      => '',
                'DAT_DRIVING_LICENSE_EXP_DATE'      => '',

                'DAT_HOURS_EFFECTIVE_FROM'          => '0:00:00',
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
                // 'NUM_DAYS_COVER_FOR_COURTESY'       => 0,
                'NUM_FINANCIER_NAME_1'              => '',
                'NUM_FINANCIER_NAME_2'              => '',
                'NUM_GEOGRAPHICAL_EXTN_PREM'        => 0,
                'NUM_IEV_BASE_VALUE'                => ((!$is_liability) ? $mmv->idv : '0'),
                'NUM_NO_OF_NAMED_DRIVERS'           => $PAforaddionaldPaidDriver,
                'NUM_PA_UNNAMED_NUMBER'             => (($requestData->unnamed_person_cover_si != '') ? ($mmv->seatingcapacity - 1) : 0),
                'NUM_POLICY_NUMBER'                 => '1234567890',
                'NUM_PREVIOUS_IDV'                  => '',
                'NUM_SPECIAL_DISCOUNT_RATE'         => 0,
                'NUM_TPPD_AMOUNT'                   => '750000',
                // 'NUM_UTR_PAYMENT_AMOUNT'            => '',
                'NUM_YEAR_OF_MANUFACTURE'           => Carbon::parse($requestData->vehicle_register_date)->format('Y'),
                'NUM_MONTH_OF_MANUFACTURE'          => Carbon::parse($requestData->vehicle_register_date)->format('m'),
                'NUM_IEV_TRAILER_VALUE'             => 0, //Total Trailer Idv
                // 'ODDiscount'                        => 0,
                // 'PAODPremium'                       => 0,
                'TXT_CHASSIS_NUMBER'                => $chassis_number,
                'TXT_ENGINE_NUMBER'                 => $engine_number,
                // 'TXT_MEDICLE_COVER_LIMIT'           => '',
                'TXT_NON_ELEC_DESC'                 => '',
                'TXT_OEM_TRANSACTION_ID'            => 'Test123', // add
                'TXT_PAN_NO'                        => '',
                'TXT_PREVIOUS_INSURER'              => 'ITGI',
                'TXT_PREV_INSURER_CODE'             => 'ITGI',
                'TXT_REGISTRATION_NUMBER_1'         => $requestData->business_type == "newbusiness" ? 'NEW' : $registration_1,
                'TXT_REGISTRATION_NUMBER_2'         => $requestData->business_type == "newbusiness" ? '' : $registration_2,
                'TXT_REGISTRATION_NUMBER_3'         => $requestData->business_type == "newbusiness" ? '' : $registration_3,
                'TXT_REGISTRATION_NUMBER_4'         => $requestData->business_type == "newbusiness" ? '' : $registration_4,
                'TXT_RELATION_WITH_NOMINEE'         => 'Other',
                'TXT_TELEPHONE'                     => '',
                'TXT_TYPE_BODY'                     => !empty($mmv->body_type) ? $mmv->body_type : $mmv->fyntune_version['vehicle_built_up'] ?? '',//we are not getting body type in master so we passing vehicle_built_up
                // 'YN_COMMERCIAL_FOR_PRIVATE'         => 0,
                // 'YN_COURTESY_CAR'                   => 0,
                'YN_DELETION_OF_IMT26'              => $imt_23_inclusion,
                'YN_DRIVING_TUTION'                 => 0,
                'YN_FOREIGN_EMBASSY'                => '',
                'YN_HANDICAPPED'                    => 0,
                // 'YN_IMT32'                          => 0,
                'YN_INBUILT_LPG'                    => $inbuiltLPG,
                'NUM_IEV_LPG_VALUE'                 => '',
                'YN_LIMITED_TO_OWN_PREMISES'        => ((!$is_liability) ? $is_limited_to_own_premises : 0 ),
                // 'YN_MEDICLE_EXPENSE'                => 0,

                //Personal Effects(0-No,-1-Yes)
                // 'CUR_LD_PERSONAL_EFFECT'            => 0, //Personal Effect value(5000/10000)
                // 'CUR_ADDLTOWCHARGE'                 => 0,
                // 'YN_PLATINUM_PA'                    => 'N', //Platinum PA for Occupants Add-On Cover       (Y-Yes, N-No)
                // 'NUM_PLATINUM_PA_SUM_INSURED'       => 0, //Sum Insured for Platinum PA for Occupants Add-On Cover ( PC – 500000/1000000/1500000 )
                // 'YN_PETCARE'                        => 'N', //Y if applicable,N if not applicable
                // 'TXT_PETCARE_DESCRIPTION'           => '',
                // 'NUM_PETCARE_SUM_INSURED'           => 0,
                // 'TXT_FASTAG_ID'                     => '', //FAST TAG ID- 24 alphanumeric characters

                // 'YN_PERSONAL_EFFECT'                => 0,
                'YN_VALID_DRIVING_LICENSE'          => ((!$is_individual) ? 'N' : 'Y'),

                // MMV
                'NUM_CUBIC_CAPACITY'                => $mmv->cubiccapacity,
                'NUM_RGSTRD_SEATING_CAPACITY'       => $mmv->seatingcapacity,
                'NUM_RGSTRD_GROSS_VEH_WEIGHT'       => $mmv->grossvehicleweight ?? '0',
                // 'NUM_RGSTRD_CARRYING_CAPACITY'      => '',
                'TXT_FUEL'                          => strtoupper($mmv->txt_fuel) == 'CNG' ? 'PETROL/CNG' : strtoupper($mmv->txt_fuel), 
                'TXT_NAME_OF_MANUFACTURER'          => strtoupper($mmv->manufacturer),
                'TXT_OTHER_MAKE'                    => $mmv->vehiclemodel,
                'TXT_VARIANT'                       => $mmv->txt_variant,
                // 'NUM_VEHICLE_MODEL_CODE'            => '',

                // RTO
                'TXT_RTA_DESC'                      => $rto_data->txt_rta_code,
                'TXT_VEHICLE_ZONE'                  => $rto_data->txt_registration_zone,

                // ADDONS & COVERS
                'YN_NIL_DEPR_WITHOUT_EXCESS'        => $zero_dep,
                'YN_CONSUMABLE'                     => $consumable,
                'YN_RTI_APPLICABLE'                 => $return_to_invoice,
                'YN_ENGINE_GEAR_COVER'              => '',  //Y—for Engine and Gear Box applicable
                'YN_ENGINE_GEAR_COVER_PLATINUM'     => $engine_secure,
                'YN_LOSS_OF_KEY'                    => $key_replacement,
                'NUM_LOSS_OF_KEY_SUM_INSURED'       => (($key_replacement == 'Y' && !$is_liability) ? '10000' : ''),
                'YN_TYRE_RIM_PROTECTOR'             => $tyre_secure,
                'NUM_TYRE_RIM_SUM_INSURED'          => (($tyre_secure == 'Y') ? '50000' : '0'),
                'YN_RSA_COVER'                      => ((!$is_liability) ? $road_side_assistance  : 'N'), // Y/N RSA ADDON
                'YN_NCB_PROTECT'                    => ((!$is_liability) ? $ncb_protection : False),
                // 'YN_EMI_COVER'                      => 'N', //Y if applicable,N if not applicable
                // 'NUM_EMI_COVER_AMOUNT'              => 0,
                'YN_COMPULSORY_PA_DTLS'             => $cpa_cover,
                'TXT_CPA_COVER_PERIOD'              => $cpa_cover_period,

                'YN_CLAIM'                          => (($requestData->is_claim == 'N') ? 'no' : 'yes'),
                'YN_PAID_DRIVER'                    => $yn_paid_driver,
                'CUR_BONUS_MALUS_PERCENT'           => ($requestData->is_claim == 'Y') ? '0' : $requestData->previous_ncb,
                'NUM_IEV_CNG_VALUE'                 => $cngLpgIDV,
                'YN_INBUILT_CNG'                    => $inbuiltCNG,

                'NUM_LL1'                           => ($llpaidDriver == 'Y') ? $LLnumberSum : 0 ,
                'NUM_LL2'                           => '',
                'NUM_LL3'                           => ($nfpp) ? $nfpp_si : 0,

                'YN_ANTI_THEFT'                     => $anti_theft_flag,

                'NUM_VOLUNTARY_EXCESS_AMOUNT'       => ((isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value != 0) ? $requestData->voluntary_excess_value : '0'),
                'NUM_IMPOSED_EXCESS_AMOUNT'         => ((isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value != 0) ? $requestData->voluntary_excess_value : ''),

                'NUM_IEV_ELEC_ACC_VALUE'            => (int)(!is_null($requestData->electrical_acessories_value) && $requestData->electrical_acessories_value != '0') ? $requestData->electrical_acessories_value : '0',
                'ELECTRICALACCESSORIESPREM'         => (!is_null($requestData->electrical_acessories_value) && $requestData->electrical_acessories_value != '0') ? $requestData->electrical_acessories_value : '0',

                'NUM_IEV_NON_ELEC_ACC_VALUE'        => (int)(!is_null($requestData->nonelectrical_acessories_value) && $requestData->nonelectrical_acessories_value != '0') ? $requestData->nonelectrical_acessories_value  : '0',
                'NONELECTRICALACCESSORIESPREM'      => (!is_null($requestData->nonelectrical_acessories_value) && $requestData->nonelectrical_acessories_value != '0') ? $requestData->nonelectrical_acessories_value : '0',

                'NUM_PA_UNNAMED_AMOUNT'             => ($requestData->unnamed_person_cover_si != '') ? $requestData->unnamed_person_cover_si : '0',

                'TXT_BUS_CATEGORY_C2'                  => $bus_category,
                'TXT_OCCUPATION'                       => '',
                // 'NUM_LL2'                           => '',
                // 'NUM_LL3'                           => '',
                'NUM_PAID_UP_CAPITAL'               => '',
                // 'NUM_PA_NAME1_AMOUNT'               => '',
                // 'NUM_PA_NAME2_AMOUNT'               => '',
                // 'NUM_PA_NAME3_AMOUNT'               => '',
                // 'NUM_PA_NAME4_AMOUNT'               => '',
                // 'NUM_PA_NAME5_AMOUNT'               => '',
                // 'NUM_PA_NAME6_AMOUNT'               => '',
                // 'NUM_PA_NAME7_AMOUNT'               => '',
                // 'NUM_PA_NAME8_AMOUNT'               => '',
                // 'NUM_PA_NAMED_AMOUNT'               => '',
                // 'NUM_PA_NAMED_NUMBER'               => '',
                // 'TXT_BANK_CODE'                     => '',
                // 'TXT_BANK_NAME'                     => '',
                'TXT_DRIVING_LICENSE_NO'            => '',
                'TXT_ELEC_DESC'                     => '',
                // 'TXT_PA_NAME1'                      => '',
                // 'TXT_PA_NAME2'                      => '',
                // 'TXT_PA_NAME3'                      => '',
                // 'TXT_PA_NAME4'                      => '',
                // 'TXT_PA_NAME5'                      => '',
                // 'TXT_PA_NAME6'                      => '',
                // 'TXT_PA_NAME7'                      => '',
                // 'TXT_PA_NAME8'                      => '',
                'TXT_TRANSACTION_ID'                => 'GR202003200004602677',
                // 'TXT_UTR_NUMBER'                    => '',
                'NUM_IEV_FIBRE_TANK_VALUE'          => '',
                // 'NUM_IEV_SIDECAR_VALUE'             => '',
                'NUM_LD_CLEANER_CONDUCTOR'          => $PAforaddionaldPaidDriverSI, 
                'TXT_FINANCIER_BRANCH_ADDRESS1'     => '',
                'TXT_FINANCIER_BRANCH_ADDRESS2'     => '',
                'TXT_FIN_ACCOUNT_CODE_1'            => '',
                'TXT_FIN_ACCOUNT_CODE_2'            => '',
                'TXT_FIN_BRANCH_NAME_1'             => '',
                'TXT_FIN_BRANCH_NAME_2'             => '',
                'TXT_GEOG_AREA_EXTN_COUNTRY'        => $geo_extn_countries,
                // 'TXT_GSTIN_NUMBER'                  => '',
                // // 'TXT_AADHAR_NUMBER'                 => '',
                // // 'TXT_ENROLLMENT_NO'                 => '',
                // // 'DAT_ENROLEMENT_DATE'               => '',
                'TXT_LICENSE_ISSUING_AUTHORITY'     => '',
                // 'TXT_MEMBERSHIP_CODE'               => '',
                // 'TXT_NAMED_PA_NOMINEE1'             => '',
                // 'TXT_NAMED_PA_NOMINEE2'             => '',
                // 'TXT_NAMED_PA_NOMINEE3'             => '',
                // 'TXT_NAMED_PA_NOMINEE4'             => '',
                // 'TXT_NAMED_PA_NOMINEE5'             => '',
                // 'TXT_NAMED_PA_NOMINEE6'             => '',
                // 'TXT_NAMED_PA_NOMINEE7'             => '',
                // 'TXT_NAMED_PA_NOMINEE8'             => '',
                'TXT_VAHICLE_COLOR'                 => 'NA',
                'CUR_ADDLTOWCHARGE'                 => $addtowcharge
            ]
        ];


        //$is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $misp_testing_mode = config('MISP_TESTING_MODE_ENABLE_UNITED_INDIA_CV') == 'Y';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->whereIn('seller_type', ['P','misp'])
            ->first();
        if (isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            $quote_array['HEADER']['TXT_POSP_CODE'] = $pos_data->relation_united_india;
            $quote_array['HEADER']['TXT_POSP_NAME'] = $pos_data->agent_name;
            if (empty($pos_data->relation_united_india))
            {
                return [
                    'status' => false,
                    'message' => 'POSP CODE not available',
                ];
            }
        }
        else if(isset($pos_data->seller_type) && $pos_data->seller_type == 'misp')
        {
            $quote_array['HEADER']['TXT_OEM_DEALER_CODE'] = $pos_data->relation_united_india;
            if (empty($pos_data->relation_united_india) && !$misp_testing_mode)
            {
                return [
                    'status' => false,
                    'message' => 'MISP CODE not available',
                ];
            }
            if($misp_testing_mode)
            {
                $quote_array['HEADER']['TXT_OEM_DEALER_CODE'] = config('constants.IcConstants.united_india.CV.OEM_DEALER_CODE'); //'BRC0000796',          
            }
        }
        $rtoCode = explode("-", $requestData->rto_code);

        if (isset($rtoCode[0]) && in_array(strtoupper($rtoCode[0]), ['DL'])) {
            if (isset($rtoCode[1]) && is_numeric($rtoCode[1]) && $rtoCode[1] > 0 && $rtoCode[1] < 10) {
                $quote_array['HEADER']['TXT_RTA_DESC'] = $rto_data->rto_location_desc;
            }
        }

        $quote_array['HEADER']['NUM_VEHICLE_MODEL_CODE'] = $mmv->vehiclemodelcode;

        $request_container = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.uiic.com/">
            <soapenv:Header/>
            <soapenv:Body>
                <ws:calculatePremium>
                    <application>' . config('constants.IcConstants.united_india.CV.APPLICATION_ID') . '</application>
                    <userid>' . config('constants.IcConstants.united_india.CV.USER_ID') . '</userid>
                    <password>' . config('constants.IcConstants.united_india.CV.USER_PASSWORD') . '</password>
                    <proposalXml>
                        <![CDATA[#replace]]>
                    </proposalXml>
                    <productCode>' . $product_code . '</productCode>
                    <subproductCode>' . $sub_product_code . '</subproductCode>
                </ws:calculatePremium>
            </soapenv:Body>
            </soapenv:Envelope>
        ';

        if(in_array($mmv->manufacturer,['MARUTI SUZUKI','MARUTI']) && $requestData->applicable_ncb == 0 && !$is_liability )
        {
            if($age>5){
                $age = '5 to 10';
            }
            elseif($age>10){
                $age = 'Above 10';
            }
            $Updated_discount_rto_data = unitedIndiaRtoCityDiscountCv::where('rto_code',$rto_data->txt_rta_code)->select('discount_grid_rto_city')->first();
            $Updated_discount_grid_value = unitedIndiaWithoutNcbDiscountGridCv::where([['age',$age],['rto_city_location',$Updated_discount_rto_data['discount_grid_rto_city']]])
            ->first();
            
            $Updated_discount_grid = array_change_key_case((array)$Updated_discount_grid_value?->toArray(), CASE_LOWER)?? null;
            $mmv_model = str_replace([' ', '-'], '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model.'_'.$mmv->txt_fuel) ?? null;

            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            if(!$Updated_discount_grid_data){
            $mmv_model = str_replace('-', '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model) ?? null;

            // $model_data = str_replace('-', ' ', $model_name);
            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            }
            if($Updated_discount_grid_data){
                $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $Updated_discount_grid_data;

            }

            if(!$Updated_discount_grid_data){
                return  [
                    'status'            => false,
                    'message'           =>  $mmv->vehiclemodel .' Not Available In Discount Grid For Maruti',
                ];
            }
        }
        elseif(in_array($mmv->manufacturer,['MARUTI SUZUKI','MARUTI']) && $requestData->applicable_ncb > 0 && !$is_liability)
        {
         
            if($age>5){
                $age = '5 to 10';
            }
            elseif($age>10){
                $age = 'Above 10';
            }
            $Updated_discount_rto_data = unitedIndiaRtoCityDiscountCv::where('rto_code',$rto_data->txt_rta_code)->select('discount_grid_rto_city')->first();
            $Updated_discount_grid_value = unitedIndiaWithNcbDiscountGridCv::where([['Age',$age],['rto_city_location',$Updated_discount_rto_data['discount_grid_rto_city']]])
            ->first();

            $Updated_discount_grid = array_change_key_case((array)$Updated_discount_grid_value?->toArray(), CASE_LOWER)?? null;
            $mmv_model = str_replace([' ', '-'], '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model.'_'.$mmv->txt_fuel) ?? null;

            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            
            if(!$Updated_discount_grid_data){
            $mmv_model = str_replace('-', '_', $mmv->vehiclemodel);
            $model_data = strtolower($mmv_model) ?? null;
                
            // $model_data = str_replace('-', ' ', $model_name);
            $Updated_discount_grid_data = $Updated_discount_grid[$model_data]  ?? null;
            }
            if($Updated_discount_grid_data){
                $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $Updated_discount_grid_data;

            }
            if(!$Updated_discount_grid_data){
                return  [
                    'status'            => false,
                    'message'           =>  $mmv->vehiclemodel .' Not Available In Discount Grid For Maruti',
                ];
            }
        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'united_india', strtolower($parent_id));
            if ($agentDiscount['status'] ?? false) {
                $quote_array['HEADER']['NUM_SPECIAL_DISCOUNT_RATE'] = $agentDiscount['discount'];
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }
        // quick quote service input

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'xml',
            'section'           => $productData->product_sub_type_code,
            'productName'       => $productData->product_name,
            'method'            => 'Premium Calculation',
            'transaction_type'  => 'quote',
            'root_tag'          => 'ROOT',
            'soap_action'       => 'calculatePremium',
            'container'         => $request_container,
        ];

        $data = $quote_array;
        unset($data['HEADER']['TXT_CHASSIS_NUMBER'], $data['HEADER']['TXT_ENGINE_NUMBER'], $data['HEADER']['TXT_REGISTRATION_NUMBER_3'], $data['HEADER']['TXT_REGISTRATION_NUMBER_4'], $data['Uid']);
        $checksum_data = checksum_encrypt($data);
        $additional_data['checksum'] = $checksum_data;
        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'united_india', $checksum_data, 'TAXI');
        if ($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']) {
            $response = $is_data_exist_for_checksum;
        } else {
            $response = getWsData(config('constants.IcConstants.united_india.CV.END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
        }

        if ($response['response']) {

            $quote_output = html_entity_decode($response['response']);
            $quote_output = XmlToArray::convert($quote_output);
            $header         = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER'];

            $error_message  = $header['TXT_ERR_MSG'];

            if ($error_message != [] && $error_message != '') {
                return [
                    'webservice_id' => $response['webservice_id'],
                    'table' => $response['table'],
                    'status'    => false,
                    'msg'       => $error_message,
                    'message'   => json_encode($error_message)
                ];
            }

            update_quote_web_servicerequestresponse($response['table'], $response['webservice_id'], 'success', 'success');
            $total_idv  = $header['NUM_IEV_BASE_VALUE'];

            $min_idv = ceil($total_idv * 0.95); # +5 -5 idv deviation
            $max_idv = floor($total_idv * 1.05);


            // IDV change
            $quote_array['HEADER']['NUM_IEV_BASE_VALUE'] = $min_idv;
            $quote_array['Uid'] = date('ymd') . time() . rand(100, 999);

            if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                if ($requestData->edit_idv >= $max_idv) {
                    $quote_array['HEADER']['NUM_IEV_BASE_VALUE'] = $max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $quote_array['HEADER']['NUM_IEV_BASE_VALUE']  = $min_idv;
                } else {
                    $quote_array['HEADER']['NUM_IEV_BASE_VALUE']  = $requestData->edit_idv;
                }
            } else {
                $quote_array['HEADER']['NUM_IEV_BASE_VALUE']  = $min_idv;
            }

            // quick quote service input

            $additional_data['method'] = 'Premium Re-Calculation';

            $data = $quote_array;

            unset($data['HEADER']['TXT_CHASSIS_NUMBER'], $data['HEADER']['TXT_ENGINE_NUMBER'], $data['HEADER']['TXT_REGISTRATION_NUMBER_3'], $data['HEADER']['TXT_REGISTRATION_NUMBER_4'], $data['Uid']);
            $checksum_data = checksum_encrypt($data);
            $additional_data['checksum'] = $checksum_data;
            $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'united_india', $checksum_data, 'TAXI');
            if ($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']) {
                $response = $is_data_exist_for_checksum;
            } else {
                $response = getWsData(config('constants.IcConstants.united_india.CV.END_POINT_URL_SERVICE'), $quote_array, 'united_india', $additional_data);
            }

            if ($response["response"]) {
                $quote_output = html_entity_decode($response['response']);
                $quote_output = XmlToArray::convert($quote_output);
                $header       = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER'];

                $error_message  = $header['TXT_ERR_MSG'];

                if ($error_message != [] && $error_message != '') {
                    return [
                        'webservice_id' => $response['webservice_id'],
                        'table' => $response['table'],
                        'status'    => false,
                        'msg'       => $error_message,
                        'message'   => json_encode($error_message)
                    ];
                }
            } else {
                return  [
                    'webservice_id' => $response['webservice_id'],
                    'table' => $response['table'],
                    'premium_amount'    => '0',
                    'status'            => false,
                    'message'           => 'Car Insurer Not found',
                ];
            }
            // END IDV change

            //Declaration
            $total_od_premium       = 0;
            $total_tp_premium       = 0;
            $od_premium             = 0;
            $tp_premium             = 0;
            $liability              = 0;
            $pa_owner               = 0;
            $pa_unnamed             = 0;
            $paAddionalPaidDriver   = 0;
            $lltoPassenger          = 0;
            $lpg_cng_amount         = 0;
            $lpg_cng_tp_amount      = 0;
            $electrical_amount      = 0;
            $non_electrical_amount  = 0;
            $ncb_discount           = 0;
            $discount_amount        = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;

            $base_cover = [
                'od_premium'            =>  0,
                'tp_premium'            =>  0,
                'pa_owner'              =>  0,
                'liability'             =>  0,
                'eng_prot'              =>  0,
                'return_to_invoice'     =>  0,
                'road_side_assistance'  =>  0,
                'imt23'                =>   0,
                'zero_dep_amount'       =>  0,
                'medical_expense'       =>  0,
                'consumable'            =>  0,
                'key_replacement'       =>  0,
                'tyre_secure'           =>  0,
                'ncb_protection'        =>  0,
                'lltoPassenger'         =>  0,
                'additional_towing'     =>  0,
                'nfpp'                  =>  0
            ];
            $base_cover_codes = [
                'od_premium'        =>  'Basic - OD',
                'tp_premium'        =>  'Basic - TP',
                'pa_owner'          =>  'PA Owner Driver',
                'liability'         =>  'LL to Paid Driver IMT 28',
                'eng_prot'          =>  'Engine and Gearbox Protection Platinum AddOn Cover',
                'return_to_invoice' =>  'Return To Invoice',
                'road_side_assistance' => 'Road Side Assistance',
                'imt23'            =>  'Cover for lamps, tyres, tubes etc',
                'zero_dep_amount'   =>  'Nil Depreciation Without Excess',
                'medical_expense'   =>  'Medical Expenses',
                'consumable'        =>  'Consumables Cover',
                'key_replacement'   =>  'Loss Of Key Cover',
                'tyre_secure'       =>  'Tyre And Rim Protector Cover',
                'ncb_protection'    =>  'NCB Protect',
                'lltoPassenger'     =>  'Legal Liability to Passenger',
                'additional_towing' =>  'Additional Towing Charge',
                'nfpp'              =>  'Legal Liability to Non-Fare Paying Passenger(Employee)'
            ];
            $base_cover_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];

            $discount_codes = [
                'bonus_discount'            =>  'Bonus Discount - OD',
                'anti_theft_discount'       =>  'Anti-Theft Device - OD',
                'automobile_association'    =>  'Automobile Association Discount',
                'voluntary'                 =>  'Voluntary Excess Discount',
                'tppd'                      =>  'TPPD Discount',
                'limited_to_own_premises'   =>  'Limited to Own Premises - OD',
                'detariff'                  =>  'Detariff Discount  (Applicable on Basic OD Rate)-OD',
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
                'tppd'                      =>  0,
                'detariff'                  =>  0,
                'limited_to_own_premises'   =>  0,
            ];

            $cng_codes = [
                'lpg_cng_tp_amount'     =>  'CNG kit - TP',
                'lpg_cng_amount'        =>  'CNG kit - OD',
            ];
            $cng_match_arr = [
                'name'  => 'PropCoverDetails_CoverGroups',
                'value' => 'PropCoverDetails_Premium',
            ];
            $cng = [
                'lpg_cng_tp_amount'     =>  0,
                'lpg_cng_amount'        =>  0,
            ];

            $tppd = 0;
            $detariff_discount = 0;
            $detairff_percentage = 0;
            //declaration end

            $worksheet = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['TXT_PRODUCT_USERDATA']['WorkSheet'];
            // print_pre(['foreach block PropRisks_Co -> Risks', $worksheet['PropRisks_Col']['Risks']]);

            if (isset($worksheet['PropRisks_Col']['Risks'][0])) {
                foreach ($worksheet['PropRisks_Col']['Risks'] as $risk_key => $risk_value) {
                    if (is_array($risk_value) && isset($risk_value['PropRisks_SIComponent'])) {
                        if ($risk_value['PropRisks_SIComponent'] == 'VehicleBaseValue') {
                            $base_cover = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);

                            if (!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0])) {
                                $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD') {
                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                        if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                $detairff_percentage = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Rate'];
                                            }
                                        }
                                    }
                                }
                            } else {
                                foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {

                                    if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {
                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {

                                            if(isset($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']) && $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'] != ''){
                                                if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                    $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }

                                                if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Geographical Extension - TP') {
                                                   $geog_Extension_TP_Premium = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }

                                        }
                                    } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD') {
                                        if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {

                                            $discountTags = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'];

                                            if(isset($discountTags) && $discountTags != ''){

                                                if(isset($discountTags[0]['PropLoadingDiscount_Description'])){ // to check whether it has single Property or multiple
                                                    foreach($discountTags as $val){
                                                        if ($val['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                           $detariff_discount = $val['PropLoadingDiscount_EndorsementAmount'];
                                                           $detairff_percentage = $val['PropLoadingDiscount_Rate'];
                                                        }
                                       
                                                        if ($val['PropLoadingDiscount_Description'] == 'Geographical Extension - OD') {
                                                           $geog_Extension_OD_Premium = $val['PropLoadingDiscount_EndorsementAmount'];
                                                        }
                                                    }
                                                } else {
                                                    if ($discountTags['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                        $detariff_discount = $discountTags['PropLoadingDiscount_EndorsementAmount'];
                                                    }   

                                                    if ($discountTags['PropLoadingDiscount_Description'] == 'Geographical Extension - OD') {
                                                        $geog_Extension_OD_Premium = $discountTags['PropLoadingDiscount_EndorsementAmount'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'CNG') {
                            $cng = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'Unnamed Hirer or Driver PA') {
                            $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'PAForPaidDriveretc') {
                            $paAddionalPaidDriver = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'ElectricalAccessories') {
                            $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                        }
                        if ($risk_value['PropRisks_SIComponent'] == 'NonElectricalAccessories') {
                            $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                        }
                    }
                }
            } else {
                $risk_value = $worksheet['PropRisks_Col']['Risks'];
                if (is_array($risk_value) && isset($risk_value['PropRisks_SIComponent'])) {
                    if ($risk_value['PropRisks_SIComponent'] == 'VehicleBaseValue') {
                        $base_cover = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $base_cover_codes, $base_cover_match_arr, $base_cover);
                        if (!isset($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'][0])) {
                            $v = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'];
                            if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {


                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {

                                    if(isset($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']) && $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'] != ''){
                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                            $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }

                                        if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Geographical Extension - TP') {
                                           $geog_Extension_TP_Premium = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                        }
                                    }

                                }

                                // if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                //     if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                //         if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                //             $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                //         }
                                //     }
                                // }


                            } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic - OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic OD') {


                                if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {

                                    $discountTags = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'];

                                    if(isset($discountTags) && $discountTags != ''){

                                        if(isset($discountTags[0]['PropLoadingDiscount_Description'])){ // to check whether it has single Property or multiple
                                            foreach($discountTags as $val){
                                                if ($val['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                   $detariff_discount = $val['PropLoadingDiscount_EndorsementAmount'];
                                                }
                               
                                                if ($val['PropLoadingDiscount_Description'] == 'Geographical Extension - OD') {
                                                   $geog_Extension_OD_Premium = $val['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        } else {
                                            if ($discountTags['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                $detariff_discount = $discountTags['PropLoadingDiscount_EndorsementAmount'];
                                            }   

                                            if ($discountTags['PropLoadingDiscount_Description'] == 'Geographical Extension - OD') {
                                                $geog_Extension_OD_Premium = $discountTags['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
                                    }
                                }

                                // if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                //     if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                //         if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                //             $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                //         }
                                //     }
                                // }


                            }
                        } else {
                            foreach ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'] as $k => $v) {
                                if ($v['PropCoverDetails_CoverGroups'] == 'Basic TP' || $v['PropCoverDetails_CoverGroups'] == 'Basic - TP') {

                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {

                                        if(isset($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']) && $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'] != ''){
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                                $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
    
                                            if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Geographical Extension - TP') {
                                               $geog_Extension_TP_Premium = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                            }
                                        }
    
                                    }

                                    // if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    //     if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                    //         if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'TPPD Discount') {
                                    //             $tppd = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                    //         }
                                    //     }
                                    // }
                                } else if ($v['PropCoverDetails_CoverGroups'] == 'Basic OD' || $v['PropCoverDetails_CoverGroups'] == 'Basic - OD') {

                                    if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {

                                        $discountTags = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount'];

                                        if(isset($discountTags) && $discountTags != ''){

                                            if(isset($discountTags[0]['PropLoadingDiscount_Description'])){ // to check whether it has single Property or multiple
                                                foreach($discountTags as $val){
                                                    if ($val['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                       $detariff_discount = $val['PropLoadingDiscount_EndorsementAmount'];
                                                    }
                                   
                                                    if ($val['PropLoadingDiscount_Description'] == 'Geographical Extension - OD') {
                                                       $geog_Extension_OD_Premium = $val['PropLoadingDiscount_EndorsementAmount'];
                                                    }
                                                }
                                            } else {
                                                if ($discountTags['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                                    $detariff_discount = $discountTags['PropLoadingDiscount_EndorsementAmount'];
                                                    $detairff_percentage = $discountTags['PropLoadingDiscount_Rate'];
                                                }   

                                                if ($discountTags['PropLoadingDiscount_Description'] == 'Geographical Extension - OD') {
                                                    $geog_Extension_OD_Premium = $discountTags['PropLoadingDiscount_EndorsementAmount'];
                                                }
                                            }
                                        }
                                    }

                                    // if (!empty($v['PropCoverDetails_LoadingDiscount_Col'])) {
                                    //     if (!isset($v['PropCoverDetails_LoadingDiscount_Col'][0])) {
                                    //         if ($v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_Description'] == 'Detariff Discount  (Applicable on Basic OD Rate)-OD') {
                                    //             $detariff_discount = $v['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'];
                                    //         }
                                    //     }
                                    // }
                                }
                            }
                        }
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'CNG') {
                        $cng = united_india_cover_addon_values($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails'], $cng_codes, $cng_match_arr, $cng);
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'Unnamed Hirer or Driver PA') {
                        $pa_unnamed = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'PAForPaidDriveretc') {
                        $paAddionalPaidDriver = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'ElectricalAccessories') {
                        $electrical_amount = $risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'];
                    }
                    if ($risk_value['PropRisks_SIComponent'] == 'NonElectricalAccessories') {
                        $non_electrical_amount = ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_Premium'] - ($risk_value['PropRisks_CoverDetails_Col']['Risks_CoverDetails']['PropCoverDetails_LoadingDiscount_Col']['CoverDetails_LoadingDiscount']['PropLoadingDiscount_EndorsementAmount'] ?? 0));
                    }
                }
            }
            if (!$is_liability) {
                if (is_array($worksheet['PropLoadingDiscount_Col']) && !empty($worksheet['PropLoadingDiscount_Col'])) {
                    $discount = united_india_cover_addon_values($worksheet['PropLoadingDiscount_Col']['LoadingDiscount'], $discount_codes, $match_arr, $discount);
                }
            }

            $idv = round($quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['NUM_IEV_BASE_VALUE']);

            $discount['detariff'] = $detariff_discount;
            $discount['tppd'] = $tppd;

            if ($is_liability) {
                $add_on_data = [
                    'in_built'     => [],
                    'additional' => [
                        'key_replace'                       => 0,
                        'consumables'                       => 0,
                        'tyre_secure'                       => 0,
                        'return_to_invoice'                 => 0,
                        'zero_depreciation'                 => 0,
                        'engine_protector'                  => 0,
                        'road_side_assistance'              => 0,
                        'imt23'                             => 0,
                        'ncb_protection'                    => 0,
                        'additional_towing'                 => 0
                    ],
                    'other' => []
                ];
            }

            if ($is_zero_dep) {
                $add_on_data = [
                    'in_built'   => [
                        'zero_depreciation'             => $base_cover['zero_dep_amount'],
                    ],
                    'additional' => [
                        'key_replace'                   => $base_cover['key_replacement'],
                        'engine_protector'              => $base_cover['eng_prot'],
                        'ncb_protection'                => $base_cover['ncb_protection'],
                        'consumables'                   => $base_cover['consumable'],
                        'tyre_secure'                   => $base_cover['tyre_secure'],
                        'return_to_invoice'             => $base_cover['return_to_invoice'],
                        'road_side_assistance'          => $base_cover['road_side_assistance'],
                        'additional_towing'             => $base_cover['additional_towing']
                    ],
                    'other' => [],
                ];
            } else {
                if($productData->product_identifier == 'imt_23'){
                    $add_on_data = [
                        'in_built'   => [
                            'imt23'                         => $base_cover['imt23']
                        ],
                        'additional' => [
                            'zero_depreciation'             => 0,
                            'key_replace'                   => $base_cover['key_replacement'],
                            'engine_protector'              => $base_cover['eng_prot'],
                            'ncb_protection'                => $base_cover['ncb_protection'],
                            'consumables'                   => $base_cover['consumable'],
                            'tyre_secure'                   => $base_cover['tyre_secure'],
                            'return_to_invoice'             => 0,
                            'road_side_assistance'          => $base_cover['road_side_assistance'],
                            'additional_towing'             => $base_cover['additional_towing']
                        ],
                        'other' => [],
                    ];
                } else {
                    $add_on_data = [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation'             => 0,
                            'key_replace'                   => $base_cover['key_replacement'],
                            'engine_protector'              => $base_cover['eng_prot'],
                            'ncb_protection'                => $base_cover['ncb_protection'],
                            'consumables'                   => $base_cover['consumable'],
                            'tyre_secure'                   => $base_cover['tyre_secure'],
                            'return_to_invoice'             => 0,
                            'road_side_assistance'          => $base_cover['road_side_assistance'],
                            'imt23'                         => $base_cover['imt23'],
                            'additional_towing'             => $base_cover['additional_towing']
                        ],
                        'other' => [],
                    ];
                }
            }

            if($is_zero_dep && ($parent_id == 'GCV' || ($parent_id == 'PCV' && $mmv->carryingcapacity > 6))){
                $add_on_data['in_built']['imt23'] = $base_cover['imt23'];
            }

            $in_built_premium = 0;
            foreach ($add_on_data['in_built'] as $key => $value) {
                $in_built_premium = $in_built_premium + $value;
            }

            $additional_premium = 0;
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
                'roadSideAssistance',
                'keyReplace',
                'consumables',
                'engineProtector',
                'returnToInvoice',
                'ncbProtection',
                "imt23",
                "additionalTowing"
            ];

            // if ($vehicle_age > 3) {
            //     array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
            //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            // }

            // if ($vehicle_age > 5) {
            //     array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            //     array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
            //     array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
            // }

            $basic_tp = $base_cover['tp_premium'] + $base_cover['lltoPassenger'];

            $final_total_premium = $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['CUR_FINAL_TOTAL_PREMIUM'];

            $total_od_premium = $base_cover['od_premium'] + $cng['lpg_cng_amount'] + $non_electrical_amount + $electrical_amount - $discount['limited_to_own_premises'] + $geog_Extension_OD_Premium;

            $total_tp_premium = $basic_tp  + $base_cover['liability'] + $cng['lpg_cng_tp_amount'] + $pa_unnamed + $paAddionalPaidDriver + $geog_Extension_TP_Premium + $base_cover['nfpp'];

            $bonus_amount = $discount['bonus_discount'];
            
            // $discount['bonus_discount'] = $discount['bonus_discount'] - (($base_cover['zero_dep_amount'] + $base_cover['return_to_invoice']) * $requestData->applicable_ncb / 100);

            if ($discount['bonus_discount'] < 0) {
                $discount['bonus_discount'] = $bonus_amount;
            }

            if($is_new){
                $discount['bonus_discount'] = 0;
            }

            $total_discount_premium     = $discount['bonus_discount'] + $discount['automobile_association'] + $discount['anti_theft_discount'] + $discount['voluntary'] + $discount['detariff'];

            $total_base_premium         = $total_od_premium +  $total_tp_premium - $total_discount_premium;

            $data_response = [
                'webservice_id' => $response['webservice_id'],
                'table' => $response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : $idv,
                    'min_idv' => $premium_type == 'third_party' ? 0 : ($min_idv),
                    'max_idv' => $premium_type == 'third_party' ? 0 : ($max_idv),
                    'qdata' => NULL,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'distance' => isset($addtowcharge) ? $addtowcharge : 20000,
                    'addonCover' => NULL,
                    'addon_cover_data_get' => '',
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => (($is_package) ? 'Comprehensive' : 'Third Party' ),
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
                    'ncb_discount' => ($is_liability ? 0 : $requestData->applicable_ncb),
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
                        'ic_vehicle_discount' => round($discount['detariff']),
                    ],
                    'ic_vehicle_discount' => round($discount['detariff']),
                    'basic_premium' => $base_cover['od_premium'],
                    'deduction_of_ncb' => $discount['bonus_discount'],
                    'tppd_premium_amount' => $basic_tp,
                    'seating_capacity' => $mmv->seatingcapacity,
                    'compulsory_pa_own_driver' => $base_cover['pa_owner'],
                    'total_accessories_amount(net_od_premium)' => "",
                    'total_own_damage' => $total_od_premium,
                    'total_liability_premium' => $total_tp_premium,
                    'net_premium' => $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['CUR_NET_FINAL_PREMIUM'],
                    'service_tax_amount' => "",
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'additional_towing_options' => [
                        '10000',
                        '20000'
                    ],
                    'quotation_no' => '',
                    'premium_amount' => '',
                    'LimitedtoOwnPremises_OD' => round($discount['limited_to_own_premises']),
                    'final_od_premium' => $total_od_premium ?? 0,
                    'final_tp_premium' => $total_tp_premium ?? 0,
                    'final_total_discount' => $total_discount_premium,
                    'final_net_premium' => $total_base_premium ?? 0,
                    'final_gst_amount' => $quote_output['S:Body']['ns2:calculatePremiumResponse']['return']['ROOT']['HEADER']['CUR_FINAL_SERVICE_TAX'] ?? 0,
                    'final_payable_amount' => $final_total_premium ?? 0,
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
                    'base_cover' => $base_cover,
                    'antitheft_discount' => $discount['anti_theft_discount'],
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                ],
                'premium_data' => [
                    'base_cover'                => $base_cover,
                    'cng'                       => $cng,
                    'discount'                  => $discount,
                    'pa_unnamed'                => $pa_unnamed,
                    'electrical_amount'         => $electrical_amount,
                    'non_electrical_amount'     => $non_electrical_amount,
                    'nfpp'                      => $base_cover['nfpp'],
                ]
            ];

            
            if ($idv > 5000000 && $pos_data->seller_type == 'P') {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Quotes are not allowed if IDV is more than 50 lakhs',
                    'IDV Amount' => $idv
                ];
            }

            if($nfpp){
                $data_response['Data']['nfpp'] = $base_cover['nfpp'];
            }
            
            if ($externalCNGKIT == 'Y') {
                $data_response['Data']['cng_lpg_tp'] = $cng['lpg_cng_tp_amount'];
            }
            if ($llpaidDriver == 'Y') {
                $data_response['Data']['default_paid_driver'] = $base_cover['liability'];
            }
            if ($PAforUnnamedPassenger == 'Y') {
                $data_response['Data']['cover_unnamed_passenger_value'] = $pa_unnamed;
            }
            // if ($PAforaddionaldPaidDriver == 1) {
                $data_response['Data']['motor_additional_paid_driver'] = $paAddionalPaidDriver;
            // }
            if ($is_tppd) {
                $data_response['Data']['tppd_discount'] = $discount['tppd'];
            }
            
            if ($Electricalaccess == 'Y') {
                $data_response['Data']['motor_electric_accessories_value'] = $electrical_amount;
            }
            if ($NonElectricalaccess == 'Y') {
                $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical_amount;
            }
            if ($externalCNGKIT == 'Y' || $cng['lpg_cng_amount'] > 0) {
                $data_response['Data']['motor_lpg_cng_kit_value'] = $cng['lpg_cng_amount'];
            }
            if ($Electricalaccess == 'Y') {
                $data_response['Data']['motor_electric_accessories_value'] = $electrical_amount;
            }
            if ($NonElectricalaccess == 'Y') {
                $data_response['Data']['motor_non_electric_accessories_value'] = $non_electrical_amount;
            }
            if ($externalCNGKIT == 'Y' || $cng['lpg_cng_amount'] > 0) {
                $data_response['Data']['motor_lpg_cng_kit_value'] = $cng['lpg_cng_amount'];
            }

            return camelCase($data_response);
        } else {
            return  [
                'webservice_id' => $response['webservice_id'],
                'table' => $response['table'],
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => 'Car Insurer Not found',
            ];
        }
    } catch (Exception $e) {
        return  [
            'premium_amount'    => '0',
            'status'            => false,
            'message'           => 'Car Insurer Not found ' . $e->getMessage() . ' ' . $e->getLine(),
            'request' => [
                'mmv_data' => $mmv_data ?? '',
                'requestData' => $requestData
                ]
            ];
        }
    }



function united_india_cover_addon_values($value_arr, $cover_codes, $match_arr, $covers)
{
    if (!isset($value_arr[0])) {
        $value = $value_arr;
        foreach ($cover_codes as $k => $v) {
            if ($value[$match_arr['name']] == $v) {
                $covers[$k] = (float)$value[$match_arr['value']];
            }
        }
    } else {
        foreach ($value_arr as $key => $value) {
            foreach ($cover_codes as $k => $v) {
                if ($value[$match_arr['name']] == $v) {
                    $covers[$k] = (float)$value[$match_arr['value']];
                }
            }
        }
    }
    return $covers;
}

function united_mmv_check($mmv)
{
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return    [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ]
        ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ]);
    } elseif ($mmv->ic_version_code == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ]);
    } else {
        return ['status' => true, 'mmv_data' => $mmv];
    }
}
