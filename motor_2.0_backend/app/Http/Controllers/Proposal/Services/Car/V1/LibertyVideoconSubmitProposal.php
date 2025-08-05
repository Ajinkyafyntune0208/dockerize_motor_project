<?php

namespace App\Http\Controllers\Proposal\Services\Car\V1;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

use App\Http\Controllers\SyncPremiumDetail\Car\LibertyVideoconPremiumDetailController;
use App\Models\CvBreakinStatus;
use Carbon\Carbon;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use DateTime;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

class LibertyVideoconSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }

        $inspection_no = '';
        $mmv = get_mmv_details($productData, $requestData->version_id, 'liberty_videocon');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $m_seating_capacity = $mmv->seating_capacity;

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();


        if ($mmv->fuel_type == 'Battery' && in_array($productData->product_identifier, ['DCE_PA_LCA', 'DCET_PA_LCA', 'DCEG_PA_LCA'])) 
        {
            // For Fuel types (Electric)
            return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message'  => $productData->product_identifier . ' is not allowed for electrical vehicles.',
                    'request' => [
                        'product_identifier' => $productData->product_identifier,
                        'message' => $productData->product_identifier . ' is not allowed for electrical vehicles.',
                        ]
                   ];
        }
        else if ($mmv->fuel_type != 'Battery' && in_array($productData->product_identifier, ['DCEV_PA_LCA', 'DCEVT_PA_LCA', 'DCEVTG_PA_LCA']))
        {
            // For Fuel types (Petrol, Diesel, Hybrid, CNG & LPG)
            return [
                'status' => false,
                'premium_amount' => 0,
                'message'  => $productData->product_identifier . ' is not allowed for non-electrical vehicles.',
                'request' => [
                    'product_identifier' => $productData->product_identifier,
                    'message' => $productData->product_identifier . ' is not allowed for non-electrical vehicles.',
                ]
            ];
        }

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $inspection_type_self = strtolower($proposal->inspection_type) == 'manual' ? 'false' : 'true';
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od          = ((in_array($premium_type,['own_damage', 'own_damage_breakin'])) ? true : false);
        $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin     = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $noPreviousData = ($requestData->previous_policy_type == 'Not sure');

        $current_date = date('Y-m-d');

        $is_breakin = (
            ($requestData->business_type == 'breakin')
            || (!$is_new && $noPreviousData)
            || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
            || (!$is_new && strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)));

        $date1 = new \DateTime($requestData->vehicle_register_date);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;

        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $vehicle_age = $interval->y;//ceil($age / 12);
        // if(($interval->y > 5) && in_array($productData->product_identifier,['DG_PA_LCA','DCG_PA_LCA','DCEG_PA_LCA','DCET_PA_LCA','DCETG_PA_LCA']))
        // {
        //         return [
        //             'premium_amount' => 0,
        //             'status' => false,
        //             'message' => 'Quotes are not allowed for Vehicle age greater than 5 years',
        //             'request' => [
        //                 'product_identifier' => $productData->product_identifier,
        //             ]
        //         ];
        // }
        // if(($interval->y > 7) && in_array($productData->product_identifier,['D_PA_LCA','DC_PA_LCA','DCE_PA_LCA']))
        // {
        //         return [
        //             'premium_amount' => 0,
        //             'status' => false,
        //             'message' => 'Quotes are not allowed for Vehicle age greater than 7 years',
        //             'request' => [
        //                 'product_identifier' => $productData->product_identifier,
        //             ]
        //         ];
        // }


        if ($is_new)
        {
            $policy_start_date = ($premium_type == 'third_party') ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d');
            // $policy_end_date = $is_liability ? date('Y-m-d', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }
        else
        {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));

            if ($is_breakin || $is_liability) {
                $policy_start_date = date('Y-m-d');
                if($premium_type=='third_party_breakin')
                {
                    $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
                }
                if(!(strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)))
                {
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                }
            }

            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }


        $vehicle_registration_no = explode('-', $proposal->vehicale_registration_number);
        if ($vehicle_registration_no[0] == 'DL') {
            $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0].$vehicle_registration_no[1],true); 
            $vehicle_registration_no = $registration_no.'-'.$vehicle_registration_no[2].'-'.$vehicle_registration_no[3];
            $vehicle_registration_no = explode('-', $vehicle_registration_no);
        } else {
            $vehicle_registration_no = explode('-', $proposal->vehicale_registration_number);
        }
        
        if($is_new){
            $vehicle_registration_no = explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true).'--');
        }
        if($vehicle_registration_no[0] == 'DL')
        {
            $vehicle_registration_no = explode('-',getRegisterNumberWithHyphen($vehicle_registration_no[0].$vehicle_registration_no[1].$vehicle_registration_no[2].$vehicle_registration_no[3]));
            $requestData->rto_code = $vehicle_registration_no[0].'-'.$vehicle_registration_no[1];
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts','addons')
            ->first();

        $cpa_selected = 'No';
        $CPAAlreadyAvailable = false;
        $noDrivingLicense = false;
        $PAOwnerDriverTenure = '0';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                    $cpa_selected = 'Yes';
                    $PAOwnerDriverTenure = '1';
                    $PAOwnerDriverTenure = isset($data['tenure'])? (string)$data['tenure'] :$PAOwnerDriverTenure;
                }
                else if(isset($data['reason']) && ($data['reason'] == 'I do not have a valid driving license.'))
                {
                    $CPAAlreadyAvailable = false;
                    $noDrivingLicense = true;
                }
                else if(isset($data['reason']) && (in_array($data['reason'], ['I have another PA policy with cover amount of INR 15 Lacs or more', 'I have another motor policy with PA owner driver cover in my name', 'I have another PA policy with cover amount greater than INR 15 Lacs'])))
                {
                    $CPAAlreadyAvailable = true;
                    $noDrivingLicense = false;
                }
            }
        }
        if ($requestData->vehicle_owner_type == 'I'  && $premium_type != "own_damage") {
            if($requestData->business_type == 'newbusiness')
            {
                $PAOwnerDriverTenure = isset($PAOwnerDriverTenure) ? $PAOwnerDriverTenure : 3 ;
            }
            else
            {
                $PAOwnerDriverTenure = isset($PAOwnerDriverTenure) ? $PAOwnerDriverTenure : 1 ;
            }
        }


        $electrical_accessories = 'No';
        $electrical_accessories_details = '';
        $non_electrical_accessories = 'No';
        $non_electrical_accessories_details = '';
        $external_fuel_kit = 'No';
        $fuel_type = $mmv->fuel_type;
        $external_fuel_kit_amount = '';

        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $external_fuel_kit = 'Yes';
                    $fuel_type = 'CNG';
                    $external_fuel_kit_amount = $data['sumInsured'];
                }

                if ($data['name'] == 'Non-Electrical Accessories') {
                    $non_electrical_accessories = 'Yes';
                    $non_electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }

                if ($data['name'] == 'Electrical Accessories') {
                    $electrical_accessories = 'Yes';
                    $electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }
            }
        }

        $road_side_assistance_selected = false;
        $engine_protection_selected = false;
        $return_to_invoice_selected = false;
        $consumables_selected = false;
        $key_and_lock_protection_selected = false;
        $zero_dep = false;
        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Road Side Assistance') {
                    $road_side_assistance_selected = true;
                }

                if ($data['name'] == 'Engine Protector') {
                    $engine_protection_selected = true;
                }

                if ($data['name'] == 'Return To Invoice') {
                    $return_to_invoice_selected = true;
                }

                if ($data['name'] == 'Consumable') {
                    $consumables_selected = true;
                }

                if ($data['name'] == 'Key Replacement') {
                    $key_and_lock_protection_selected = true;
                }
                if ($data['name'] == 'Zero Depreciation') {
                    $zero_dep = true;
                }
            }
        }

        $zero_depreciation_cover = 'No';
        $road_side_assistance_cover = 'No';
        $consumables_cover = 'No';
        $engine_protection_cover = 'No';
        $key_and_lock_protection_cover = 'No';
        $return_to_invoice_cover = 'No';
        $passengerAssistCover = 'No';
        $QuoteSelectedAddons = empty($additional['addons']) ? [] : array_column($additional['addons'], 'name'); 
        
        switch ($productData->product_identifier) {
            case 'basic':
                $zero_depreciation_cover = 'No';
                $road_side_assistance_cover = 'No';
                $consumables_cover = 'No';
                $engine_protection_cover = 'No';
                $key_and_lock_protection_cover = 'No';
                $return_to_invoice_cover = 'No';
                $ev_secure = 'No';
                $tyre_secure = 'No';
                break;
        
            case 'DC_PA_LCA':
                $zero_depreciation_cover = $zero_dep ? 'Yes' : 'No';
                $consumables_cover = $consumables_selected ? 'Yes' : 'No';
                $road_side_assistance_cover = $road_side_assistance_selected ? 'Yes' : 'No';
                $key_and_lock_protection_cover = $key_and_lock_protection_selected ? 'Yes' : 'No';
                $passengerAssistCover = 'Yes';
                break;
    
            case 'DCE_PA_LCA':
                $zero_depreciation_cover = $zero_dep ? 'Yes' : 'No';
                $consumables_cover = $consumables_selected ? 'Yes' : 'No';
                $engine_protection_cover = $engine_protection_selected ? 'Yes' : 'No';
                $road_side_assistance_cover = $road_side_assistance_selected ? 'Yes' : 'No';
                $key_and_lock_protection_cover = $key_and_lock_protection_selected ? 'Yes' : 'No';
                $passengerAssistCover = 'Yes';
                break;
            
            case 'D_PA_LCA':
                $zero_depreciation_cover = 'Yes';
                $road_side_assistance_cover = 'Yes';
                $passengerAssistCover = 'Yes';

                break;
            case 'DG_PA_LCA':
                $zero_depreciation_cover = 'Yes';
                $return_to_invoice_cover = 'Yes';
                $road_side_assistance_cover = 'Yes';
                $passengerAssistCover = 'Yes';
                break;
    
            case 'DCG_PA_LCA':
                $zero_depreciation_cover = 'Yes';
                $return_to_invoice_cover = 'Yes';
                $road_side_assistance_cover = 'Yes';
                $consumables_cover = 'Yes';
                $passengerAssistCover = 'Yes';
                break;
    
            case 'DCETG_PA_LCA':
                $zero_depreciation_cover = 'Yes';
                $consumables_cover = 'Yes';
                $engine_protection_cover = 'Yes';
                $tyre_secure = 'Yes';
                $return_to_invoice_cover = 'Yes';
                $road_side_assistance_cover = 'Yes';
                $passengerAssistCover = 'Yes';
                break;
    
            case 'BASIC_WITH_ADDONS':
                if(in_array('Road Side Assistance', $QuoteSelectedAddons))
                {
                    $road_side_assistance_cover = 'Yes';
                }
                if(in_array('Key Replacement', $QuoteSelectedAddons))
                {
                    $key_and_lock_protection_cover = 'Yes';
                }
                if(in_array('Emergency Medical Expenses', $QuoteSelectedAddons))
                {
                    $passengerAssistCover = 'Yes';
                }
                    $zero_depreciation_cover = 'No';
                    $consumables_cover = 'No';
                    $engine_protection_cover = 'No';
                    $return_to_invoice_cover = 'No';
                    $ev_secure = 'No';
                    $tyre_secure = 'No';
                    break;

            
            // case 'DCET_PA_LCA':
            //     $zero_depreciation_cover = $zero_dep ? 'Yes' : 'No';
            //     $consumables_cover = 'Yes';
            //     $engine_protection_cover = 'Yes';
            //     $tyre_secure = 'Yes';
            //     $road_side_assistance_cover = $road_side_assistance_selected ? 'Yes' : 'No';
            //     break;
            
            case 'DCEG_PA_LCA':
                $zero_depreciation_cover = $zero_dep ? 'Yes' : 'No';
                $consumables_cover = $consumables_selected ? 'Yes' : 'No';
                $engine_protection_cover = 'Yes';
                $tyre_secure = 'Yes';
                $return_to_invoice_cover = $return_to_invoice_selected ? 'Yes' : 'No';
                $road_side_assistance_cover = $road_side_assistance_selected ? 'Yes' : 'No';
                $key_and_lock_protection_cover = $key_and_lock_protection_selected ? 'Yes' : 'No';
                $passengerAssistCover = 'Yes';
                break;

            case 'BASIC_ADDON':
                $key_and_lock_protection_cover = $key_and_lock_protection_selected ? 'Yes' : 'No';
                break;
    
            // case 'DCEV_PA_LCA':
            //     $zero_depreciation_cover = 'Yes';
            //     $consumables_cover = 'Yes';
            //     $ev_secure = 'Yes';
            //     $road_side_assistance_cover = 'Yes';
            //     break;
    
            // case 'DCEVT_PA_LCA':
            //     $zero_depreciation_cover = 'Yes';
            //     $consumables_cover = 'Yes';
            //     $engine_protection_cover = 'Yes';
            //     $ev_secure = 'Yes';
            //     $tyre_secure = 'Yes';
            //     $road_side_assistance_cover = 'Yes';
            //     break;
    
            // case 'DCEVTG_PA_LCA':
            //     $zero_depreciation_cover = 'Yes';
            //     $consumables_cover = 'Yes';
            //     $engine_protection_cover = 'Yes';
            //     $ev_secure = 'Yes';
            //     $tyre_secure = 'Yes';
            //     $return_to_invoice_cover = 'Yes';
            //     $road_side_assistance_cover = 'Yes';
            //     break;

        }
        if(in_array('Road Side Assistance', $QuoteSelectedAddons))
        {
            $road_side_assistance_cover = 'Yes';
        }
        if(in_array('Key Replacement', $QuoteSelectedAddons))
        {
            $key_and_lock_protection_cover = 'Yes';
        }
        if(in_array('Emergency Medical Expenses', $QuoteSelectedAddons))
        {
            $passengerAssistCover = 'Yes';
        }

        // if($interval->y > 2 || ($interval->y == 2 && $interval->m > 11) || ($interval->y == 2 && $interval->m == 11 && $interval->d > 29))
        // {
        //     $return_to_invoice_cover = 'No';
        // }

        $return_to_invoice_cover = $return_to_invoice_cover == 'Yes' && $return_to_invoice_selected ? 'Yes' : 'No';

        // $return_to_invoice_cover = 'No';
        if(config('IC.LIBERTY_VIDEOCON.V1.CAR.RTI_DISABLED') == 'Y') {
            $return_to_invoice_cover = 'No'; // RTI is disable by IC
        }
        // $road_side_assistance_cover = $road_side_assistance_cover == 'Yes' && $road_side_assistance_selected && $vehicle_age < 10 ? 'Yes' : 'No';

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = 'No';
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $no_of_pa_unnamed_passenger = 1;

        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = 'Yes';
                    $cover_pa_paid_driver_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger = 'Yes';
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                    $no_of_pa_unnamed_passenger = $mmv->seating_capacity;
                }

                if ($data['name'] == 'LL paid driver') {
                    $cover_ll_paid_driver = 'Yes';
                }
            }
        }

         //checking last addons
         $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsEngine_Cover = $is_addon_breakin = false;
         if(!empty($proposal->previous_policy_addons_list) && env('APP_ENV') == 'local')
         {
             $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
             foreach ($previous_policy_addons_list as $key => $value) {
                if($key == 'zeroDepreciation' && $value)
                {
                     $PreviousPolicy_IsZeroDept_Cover = true;  
                }
                if($key == 'engineProtector' && $value)
                {
                     $PreviousPolicy_IsEngine_Cover = true;  
                }
             }
             if($zero_dep && !$PreviousPolicy_IsZeroDept_Cover)
            {
               $is_breakin = $is_addon_breakin = true;
            }                
             if($engine_protection_selected && !$PreviousPolicy_IsEngine_Cover)
            {
               $is_breakin = $is_addon_breakin = true;
            }                
         }
         if($is_addon_breakin)
         {
            if((strtotime($requestData->previous_policy_expiry_date) > strtotime($current_date)))
                {
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                }
         }
        if ($requestData->business_type == "rollover" && $premium_type == 'third_party') {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }
        $is_anti_theft = 'No';
        $is_anti_theft_device_certified_by_arai = 'false';
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = 0;

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'anti-theft device') {
                    $is_anti_theft = 'Yes';
                    $is_anti_theft_device_certified_by_arai = 'true';
                }

                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                    $is_voluntary_access = 'Yes';
                    $voluntary_excess_amt = $data['sumInsured'];
                }
            }
        }

        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $salutation = 'Mr';
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $salutation = 'Miss';
                } else {
                    $salutation = 'Mrs';
                }
            }
        }
        else{
            $salutation = 'M/S';
        }

        $proposal_addtional_details = json_decode($proposal->additional_details, true);

        if($is_od){
            $tp_insured         = $proposal_addtional_details['prepolicy']['tpInsuranceCompany'];
            $tp_insurer_name    = $proposal_addtional_details['prepolicy']['tpInsuranceCompanyName'];
            $tp_start_date      = $proposal_addtional_details['prepolicy']['tpStartDate'];
            $tp_end_date        = $proposal_addtional_details['prepolicy']['tpEndDate'];
            $tp_policy_no       = $proposal_addtional_details['prepolicy']['tpInsuranceNumber'];

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
            $tp_insurer_address = keysToLower($tp_insurer_address);
        }
        if($is_package)
        {
            if($premium_type == 'third_party' || $requestData->business_type == 'newbusiness')
            {
                $tp_start_date      = date('d-m-Y',strtotime($policy_start_date));
                $tp_end_date        = date('d-m-Y', strtotime('+3 year -1 day', strtotime($tp_start_date)));
            }

            if($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')
            {
                $tp_start_date      = date('Y-m-d',strtotime($policy_start_date));
                $tp_end_date        = date('d-m-Y', strtotime('+1 year -1 day', strtotime($tp_start_date)));
            }
        }

        if($is_new)
        {
            if($premium_type == 'third_party')
            {
                $tp_start_date      = date('d-m-Y',strtotime($policy_start_date));
                $tp_end_date        = date('d-m-Y', strtotime('+3 year -1 day', strtotime($tp_start_date)));
            }

        }
        if($is_liability)
        {
            if($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')
            {
                $tp_start_date      = date('Y-m-d',strtotime($policy_start_date));
                $tp_end_date        = date('d-m-Y', strtotime('+1 year -1 day', strtotime($tp_start_date)));
            }
        }
       
        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_name   = '';
        $pos_type   = '';
        $pos_code   = '';
        $pos_aadhar = '';
        $pos_pan    = '';
        $pos_mobile = '';
        $imd_number = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER');
        $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME');

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
            
            if(config('IS_LIBERTY_CAR_NON_POS') == 'Y') {
                $pos_name = '';$pos_type = '';$pos_code = '';$pos_aadhar = '';$pos_pan = '';$pos_mobile = '';
            }else{
                $pos_name   = $pos_data->agent_name;
                $pos_type   = 'POSP';
                $pos_code   = $pos_data->pan_no;
                $pos_aadhar = $pos_data->aadhar_no;
                $pos_pan    = $pos_data->pan_no;
                $pos_mobile = $pos_data->agent_mobile;
                $imd_number = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER_POS');
                $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME_POS');
            }
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_LIBERTY_VIDEOCON') == 'Y' && $quote->idv <= 5000000)
        {
            $pos_name   = 'Agent';
            $pos_type   = 'POSP';
            $pos_code   = 'ABGTY8890Z';
            $pos_aadhar = '569278616999';
            $pos_pan    = 'ABGTY8890Z';
            $pos_mobile = '8850386204';
        }

        if (isset($requestData->is_claim) && ($requestData->is_claim == 'Y')){
            $no_of_claims = '1';
            $claim_amount = '';
        }
        else{
            $no_of_claims = '';
            $claim_amount = '';
        }

        if($is_individual && ($proposal->last_name === null || $proposal->last_name == ''))
        {
            $proposal->last_name = '.';
        }

        if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
        {
            $no_of_claims = '';
            $claim_amount = '';
            $requestData->applicable_ncb = 0;
        }

        #config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_TP')/3155;
        $productcode = ($is_od) ? config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_OD') :(($is_liability) ? config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_TP') :config('IC.LIBERTY_VIDEOCON.V1.CAR.PRODUCT_CODE_PACKAGE'));

        $buyer_state = DB::table('liberty_videocon_state_master')->where('num_state_cd', $proposal->state_id)->first();

        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 60,
            'address_2_limit'   => 60,
            'address_2_limit'   => 60,            
        ];
        $getAddress = self::getAddress($address_data);
        $KeyLossCoverSI = self::getKeyLossCoverSI($mmv);    

        $isLiabilityToEmployeeCovered = "No";
        if ($requestData->vehicle_owner_type == 'C' && !($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')) {
            $isLiabilityToEmployeeCovered = "Yes";
        }
        
        $proposal_request = [
            'QuickQuoteNumber' => config('IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
            'IMDNumber' => $imd_number,
            'AgentCode' => '',
            'TPSourceName' => $tp_source_name,
            'ProductCode' => $productcode,
            'IsFullQuote' => ((!$is_liability && $is_breakin) ? 'false' : 'true'),
            'BusinessType' => ($is_new ? 'New Business' : 'Roll Over'),
            'MakeCode' => $mmv->manufacturer_code,
            'ModelCode' => $mmv->vehicle_model_code,
            'ManfMonth' => date('m', strtotime('01-'.$proposal->vehicle_manf_year)),
            'ManfYear' => date('Y', strtotime('01-'.$proposal->vehicle_manf_year)),
            'RtoCode' => RtoCodeWithOrWithoutZero($requestData->rto_code,true), //$requestData->rto_code,
            'RegNo1' => $vehicle_registration_no[0],
            'RegNo2' => $vehicle_registration_no[1],
            'RegNo3' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[2] : '',
            'RegNo4' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[3] : $vehicle_registration_no[2],
            'DeliveryDate' => date('d/m/Y', strtotime($vehicleDate)),
            'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
            'VehicleIDV' => $quote->idv,
            'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
            'PolicyEndDate' => date('d/m/Y', strtotime($policy_end_date)),
            'PolicyTenure' => $is_new && $is_liability ? '1':'1',
            'GeographicalExtn' => 'No',
            'GeographicalExtnList' => '',
            'DrivingTuition' => '',
            'VintageCar' => '',
            'LegalLiabilityToPaidDriver' => $cover_ll_paid_driver,
            'NoOfPassengerForLLToPaidDriver' => '1',
            'LegalliabilityToEmployee' => $isLiabilityToEmployeeCovered,
            'NoOfPassengerForLLToEmployee' => $requestData->vehicle_owner_type == 'C' ? $m_seating_capacity : '',
            'PAUnnnamed' => $cover_pa_unnamed_passenger,
            'NoOfPerunnamed' => $no_of_pa_unnamed_passenger,
            'UnnamedPASI' => $cover_pa_unnamed_passenger_amt,
            'PAOwnerDriver' => $requestData->vehicle_owner_type == 'I' ? $cpa_selected : 'No',
            'PAOwnerDriverTenure' => $requestData->vehicle_owner_type == 'I' ? $PAOwnerDriverTenure : '0',
            'LimtedtoOwnPremise' => '',
            'CPAAlreadyAvailable' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'No' ? 'true' : 'false',
            'ElectricalAccessories' => $electrical_accessories,
            'lstAccessories' => $electrical_accessories_details,
            'NonElectricalAccessories' => $non_electrical_accessories,
            'lstNonElecAccessories' => $non_electrical_accessories_details,
            'ExternalFuelKit' => $external_fuel_kit,
            'FuelType' => $fuel_type,
            'FuelSI' => $external_fuel_kit_amount,
            'PANamed' => 'No',
            'NoOfPernamed' => '0',
            'NamedPASI' => '0',
            'PAToPaidDriver' => $cover_pa_paid_driver,
            'NoOfPaidDriverPassenger' => '1',
            'PAToPaidDriverSI' => $cover_pa_paid_driver_amt,
            'AAIMembship' => 'No',
            'AAIMembshipNumber' => '',
            'AAIAssociationCode' => '',
            'AAIAssociationName' => '',
            'AAIMembshipExpiryDate' => '',
            'AntiTheftDevice' => $is_anti_theft,
            'IsAntiTheftDeviceCertifiedByARAI' => $is_anti_theft_device_certified_by_arai,
            'TPPDDiscount' => 'No',
            'ForeignEmbassy' => '',
            'VoluntaryExcess' => $is_voluntary_access,
            'VoluntaryExcessAmt' => $voluntary_excess_amt,
            'NoNomineeDetails' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'Yes' ? 'false' : 'true',
            'NomineeFirstName' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'Yes' ? $proposal->nominee_name : '',
            'NomineelastName' => '.',
            'NomineeRelationship' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'Yes' ?  $proposal->nominee_relationship : '',
            'OtherRelation' => '',
            'IsMinor' => 'false',
            'RepFirstName' => '',
            'RepLastName' => '',
            'RepRelationWithMinor' => '',
            'RepOtherRelation' => '',
            'NoPreviousPolicyHistory'   => ($is_new ? 'true' : 'false'),
            'IsNilDepOptedInPrevPolicy' => ($is_new ? 'true' : 'false'),
            'PreviousPolicyInsurerName' => ($is_new ? '' : $proposal->previous_insurance_company),
            'PreviousPolicyType'        => ($is_new ? 'true' : (($requestData->previous_policy_type == 'Third-party') ? 'LIABILITYPOLICY' :'PackagePolicy')),
            'PreviousPolicyStartDate'   => ($is_new ? '' : date('d/m/Y', strtotime('-1 Year +1 dayr', strtotime($requestData->previous_policy_expiry_date)))),
            'PreviousPolicyEndDate'     => ($is_new ? '' : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date))),
            'PreviousPolicyNumber'      => ($is_new ? '' : $proposal->previous_policy_number),
            'PreviousYearNCBPercentage' => ($is_new ? '' : $requestData->previous_ncb),
            'ClaimAmount' => $claim_amount,
            'NoOfClaims' => $no_of_claims,
            'PreviousPolicyTenure' => '1',
            'IsInspectionDone' => 'false',
            'InspectionDoneByWhom' => '',
            'ReportDate' => '',
            'InspectionDate' => '',
            'ConsumableCover' => $consumables_cover,
            'DepreciationCover' => $zero_depreciation_cover,
            'RoadSideAsstCover' => $road_side_assistance_cover,
            'GAPCover' => $return_to_invoice_cover,
            'GAPCoverSI' => '0',
            'EngineSafeCover' => $engine_protection_cover,
            'KeyLossCover' => $key_and_lock_protection_cover,
            'KeyLossCoverSI' => ($key_and_lock_protection_cover == 'Yes' ? $KeyLossCoverSI : '0'),
            'IsFinancierDetails' => $proposal->is_vehicle_finance ? 'true' : 'false',
            'AgreementType' => $proposal->is_vehicle_finance ? $proposal->financer_agreement_type : '',
            'FinancierName' => $proposal->is_vehicle_finance ? $proposal->name_of_financer : '',
            'FinancierAddress' => '',
            'PassengerAsstCover' => $passengerAssistCover,
            'EngineNo' => $proposal->engine_number,
            'ChassisNo' => $proposal->chassis_number,
            'BuyerState'        => ((!empty($proposal->gst_number)) ? strtoupper($buyer_state->buyer_state_name ?? '') : ($buyer_state->buyer_state_name ?? '')),
            'POSPName'          => $pos_name,
            'POSPType'          => $pos_type,
            'POSPCode'          => $pos_code,
            'POSPAadhar'        => $pos_aadhar,
            'POSPPAN'           => $pos_pan,
            'POSPMobileNumber'  => $pos_mobile,
            'IsSelfInspection'          => (in_array($premium_type,['breakin','own_damage_breakin']) ? $inspection_type_self : 'false'),
            'CustmerObj' => [
                'TPSource' => 'TPService',
                'CustomerType' => $requestData->vehicle_owner_type,
                'Salutation' => $salutation,
                'FirstName' => str_replace(".", "", $proposal->first_name ?? ''),
                'LastName' => $proposal->last_name,
                'DOB' => date('d/m/Y', strtotime($proposal->dob)),
                'EmailId' => $proposal->email,
                'MobileNumber' => $proposal->mobile_number,
                'AddressLine1' => $proposal->is_car_registration_address_same == 1 ? $getAddress['address_1'] : $proposal->car_registration_address1,
                'AddressLine2' => $proposal->is_car_registration_address_same == 1 ? $getAddress['address_2'] : $proposal->car_registration_address2,
                'AddressLine3' => $proposal->is_car_registration_address_same == 1 ? $getAddress['address_3'] : $proposal->car_registration_address3,
                'PinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                'StateCode' => $proposal->state_id,
                'StateName' => $proposal->state,
                'PinCodeLocality' => '',
                'PanNo' => $proposal->pan_number ?? '',
                'PermanentLocationSameAsMailLocation' => $proposal->is_car_registration_address_same == 1 ? 'true' : 'false',
                'MailingAddressLine1' => $proposal->address_line1,
                'MailingPinCode' => $proposal->pincode,
                'MailingPinCodeLocality' => $proposal->city,
                'IsEIAAvailable' => 'No',
                'EIAAccNo' => '',
                'IsEIAPolicy' => 'No',
                'EIAAccWith' => '',
                'EIAPanNo' => '',
                'EIAUIDNo' => '',
                'GSTIN' => $proposal->gst_number ?? ''
            ]
        ];
        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $ckyc_meta_data = json_decode(($proposal->ckyc_meta_data ?? "[]"), true);

            $kyc_param = [
                'Aggregator_KYC_Req_No'    => ($ckyc_meta_data["Aggregator_KYC_Req_No"] ?? ''),
                'IC_KYC_No'    => ($ckyc_meta_data["IC_KYC_No"] ?? ''),
            ];

            $proposal_request = array_merge($proposal_request, $kyc_param);
        }
        
        if($requestData->vehicle_owner_type == 'I' && !$is_od)
        {
            $proposal_request['NoDrivingLicense'] = ($cpa_selected == 'Yes' ? 'false' : ($noDrivingLicense ? 'true' : 'false'));
            $proposal_request['CPAAlreadyAvailable'] = ($cpa_selected == 'Yes' ? 'false' : ($CPAAlreadyAvailable ? 'true' : 'false'));
        }
        if(isset($request['declaredAddons']) && !empty($request['declaredAddons']))
        {
            $zero_depreciation_cover = (isset($request['declaredAddons']['zeroDepreciation']) && $request['declaredAddons']['zeroDepreciation'] == 'true') ? 'Yes' : 'No';
            $engine_protection_cover = (isset($request['declaredAddons']['engineProtector']) && $request['declaredAddons']['engineProtector'] == 'true') ? 'Yes' : 'No';
        }
        if (!$is_new && !$is_liability) {
            $proposal_request['lstPreviousAddonDetails'] = [
                [
                    'IsConsumableOptedInPrevPolicy' => $consumables_cover == 'Yes' ? 'true' : 'false',
                    'IsEngineSafeOptedInPrevPolicy' => $engine_protection_cover == 'Yes' ? 'true' : 'false',
                    'IsGAPCoverOptedInPrevPolicy' => $return_to_invoice_cover == 'Yes' ? 'true' : 'false',
                    'IsKeyLossOptedInPrevPolicy' => $key_and_lock_protection_cover == 'Yes' ? 'true' : 'false',
                    'IsNilDepreicationOptedInPrevPolicy' => $zero_depreciation_cover == 'Yes' ? 'true' : 'false',
                    'IsPassengerAsstOptedInPrevPolicy' => 'false',
                    'IsRoadSideAsstOptedInPrevPolicy' => $road_side_assistance_cover == 'Yes' ? 'true' : 'false'
                ]
            ];
        }

        if ($is_od) {
            $proposal_request['PreviousPolicyODStartDate'] = date('d/m/Y', strtotime('-1 Year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $proposal_request['PreviousPolicyODEndDate'] = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
            $proposal_request['PreviousPolicyTPStartDate'] = Carbon::parse($tp_start_date)->format('d/m/Y');
            $proposal_request['PreviousPolicyTPEndDate'] = Carbon::parse($tp_end_date)->format('d/m/Y');
            $proposal_request['LegalLiabilityToPaidDriver'] = 'No';
            $proposal_request['PAOwnerDriver'] = 'No';
            $proposal_request['PAToPaidDriver'] = 'No';
            $proposal_request['PreviousPolicyInsurerNameTP'] = $tp_insured;
            $proposal_request['PreviousPolicyNumberTP']      = $tp_policy_no;

            if($requestData->previous_policy_type == 'Comprehensive')
            {
                $proposal_request['PreviousPolicyType'] = 'BUNDLEDPOLICY';
                $proposal_request['PreviousPolicyInsurerNameOD'] = $proposal->previous_insurance_company ?? '';
                $proposal_request['PreviousPolicyNumberOD']      = $proposal->previous_policy_number ?? '';
            }
            if($requestData->previous_policy_type == 'Own-damage')
            {
                $proposal_request['PreviousPolicyType'] = 'STANDALONEPOLICY';

                $proposal_request['PreviousPolicyInsurerNameOD'] = $proposal->previous_insurance_company ?? '';
                $proposal_request['PreviousPolicyNumberOD']      = $proposal->previous_policy_number ?? '';

                $proposal_request['PreviousTPPolicyTenure']      = '3';
                $proposal_request['PreviousPolicyInsurerNameTP'] = $tp_insured;
                $proposal_request['PreviousPolicyNumberTP']      = $tp_policy_no;
                $proposal_request['isActiveTPPolicyAvailable']   = 'true';
            }
            if($requestData->previous_policy_type == 'Third-party' && $is_od)
            {
                $proposal_request['isActiveTPPolicyAvailable'] = 'true';
                $proposal_request['IsInspectionDone'] = 'true';
                $proposal_request['InspectionDoneByWhom']   = 'test';
                $proposal_request['ReportDate']             = date('d/m/Y');
                $proposal_request['InspectionDate']         = date('d/m/Y');
            }
        }

        if($noPreviousData)
        {   
            $proposal_request['NoPreviousPolicyHistory']   = 'true';
            $proposal_request['IsNilDepOptedInPrevPolicy'] = '';
            $proposal_request['PreviousPolicyInsurerName'] = '';
            $proposal_request['PreviousPolicyType']        = '';
            $proposal_request['PreviousPolicyStartDate']   = '';
            $proposal_request['PreviousPolicyEndDate']     = '';
            $proposal_request['PreviousYearNCBPercentage'] = '';
        }
        if(!$is_liability && $is_breakin || $requestData->previous_policy_type == 'Third-party')
        {
            $proposal_request['IsFullQuote'] = 'true';
            $proposal_request['IsInspectionDone']       = 'true';
            $proposal_request['InspectionDoneByWhom']   = 'test';
            $proposal_request['ReportDate']             = date('d/m/Y');
            $proposal_request['InspectionDate']         = date('d/m/Y');
        }
        if(!$is_liability && $is_breakin && strtolower($proposal->inspection_type) == 'self')
        {
            $proposal_request['IsFullQuote'] = 'true';
            $proposal_request['IsSelfInspection'] = 'true';
        }
        if($premium_type == 'third_party_breakin'){
            $proposal_request['IsInspectionDone']       = 'true';
            $proposal_request['InspectionDoneByWhom']   = 'Self';
            $proposal_request['ReportDate']             = date('d/m/Y');
            $proposal_request['InspectionDate']         = date('d/m/Y');
        }

        $get_response = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION'), $proposal_request, 'liberty_videocon', [
            'enquiryId' => customDecrypt($request['enquiryId']),
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'liberty_videocon',
            'section' => $productData->product_sub_type_code,
            'method' =>'Proposal Submission',
            'transaction_type' => 'proposal',
        ]);

        $data = $get_response['response'];
        if ($data) {
            $proposal_response = json_decode($data, TRUE);
           $proposal_response['ErrorText'] = isset($proposal_response['ErrorText']) ? $proposal_response['ErrorText'] : '';

            if (trim($proposal_response['ErrorText']) == "") {
                $llpaiddriver_premium = ($proposal_response['LegalliabilityToPaidDriverValue']);
                $ll_to_employee_premium = ($proposal_response['LegalliabilityToEmployeeValue']) ?? 0;
                $cover_pa_owner_driver_premium = ($proposal_response['PAToOwnerDrivervalue']);
                $cover_pa_paid_driver_premium = ($proposal_response['PatoPaidDrivervalue']);
                $cover_pa_unnamed_passenger_premium = ($proposal_response['PAToUnnmaedPassengerValue']);
                $voluntary_excess = ($proposal_response['VoluntaryExcessValue']);
                $anti_theft = ($proposal_response['AntiTheftDiscountValue']);
                $ic_vehicle_discount = ($proposal_response['Loading']) + ($proposal_response['Discount'] ?? 0);
                $ncb_discount = ($proposal_response['DisplayNCBDiscountvalue']);
                $od = ($proposal_response['BasicODPremium']);
                $tppd = ($proposal_response['BasicTPPremium']);
                $cng_lpg = ($proposal_response['FuelKitValueODpremium']);
                $cng_lpg_tp = ($proposal_response['FuelKitValueTPpremium']);
                $zero_depreciation = ($proposal_response['NilDepValue']);
                $road_side_assistance = ($proposal_response['RoadAssistCoverValue']);
                $engine_protection = ($proposal_response['EngineCoverValue']);
                $return_to_invoice = ($proposal_response['GAPCoverValue']);
                $consumables = ($proposal_response['ConsumableCoverValue']);
                $key_protect = ($proposal_response['KeyLossCoverValue']);
                $passenger_assist_cover = ($proposal_response['PassengerAssistCoverValue']);
                $electrical_accessories_amt = ($proposal_response['ElectricalAccessoriesValue']);
                $non_electrical_accessories_amt = ($proposal_response['NonElectricalAccessoriesValue']);

                $addon_premium = $zero_depreciation + $road_side_assistance + $engine_protection + $return_to_invoice + $key_protect + $consumables + $passenger_assist_cover;
                $final_od_premium = ($proposal_response['TotalODPremiumValue']);
                $final_tp_premium = ($proposal_response['TotalTPPremiumValue']);
                $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $anti_theft;
                $total_od_premium = ($od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt) - $final_total_discount;
                $total_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $ll_to_employee_premium + $cover_pa_owner_driver_premium;
                $final_net_premium = ($proposal_response['NetPremium']);
                $final_gst_amount = ($proposal_response['GST']);
                $final_payable_amount  = $proposal_response['TotalPremium'];

                $vehicleDetails = [
                    'manufacture_name' => $mmv->manufacturer,
                    'model_name' => $mmv->vehicle_model,
                    'version' => $mmv->variant,
                    'fuel_type' => $mmv->fuel_type,
                    'seating_capacity' => $mmv->seating_capacity,
                    'carrying_capacity' => $mmv->seating_capacity - 1,
                    'cubic_capacity' => $mmv->cubic_capacity,
                    'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
                    'vehicle_type' => 'PRIVATE CAR'
                ];

                LibertyVideoconPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                if(!$is_liability && $is_breakin){
                    if(strtolower($proposal->inspection_type) == 'manual')
                    {
                        // $request_details['vehicle_registration_no'] = $vehicle_registration_no;
                        // $request_details['enquiryId']       = $request['enquiryId'];
                        // $request_details['is_individual']   = $is_individual;

                        // $lead_response = self::leadCreation($proposal, $mmv, $request_details, $productData, $requestData);

                        // if(!$lead_response['status']){
                        //     return $lead_response;
                        // }

                        $inspection_no = $proposal_response['LeadNumber'];
                    }
                    else
                    {
                        $inspection_no = $proposal_response['LeadNumber'];
                    }
                } else {
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                        'proposal_id' => $proposal->user_proposal_id,
                    ]);
                }
                if(!$is_liability && $is_breakin)
                {
                    $breakin_data = CvBreakinStatus::
                    updateOrInsert(
                        [
                            'ic_id'             => $productData->company_id,
                            'breakin_number'    => $proposal_response['LeadNumber'],
                            'breakin_id'        => $proposal_response['LeadNumber'],
                            'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                            'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                            'breakin_response'  => $data,
                            'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                            'created_at'        => Carbon::today()->toDateString()
                        ],
                        [
                            'user_proposal_id'  => $proposal->user_proposal_id
                        ]
                    );
            
                    updateJourneyStage([
                        'user_product_journey_id'   => $proposal->user_product_journey_id,
                        'ic_id'                     => $productData->company_id,
                        'stage'                     => STAGE_NAMES['INSPECTION_PENDING'],
                        'proposal_id'               => $proposal->user_proposal_id
                    ]);
                }

                // proposal_request

                $proposal_addtional_details['liberty']['proposal_request'] = $proposal_request;
               
                UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                    ->update([
                    'od_premium' => $total_od_premium,
                    'tp_premium' => $total_tp_premium,
                    'ncb_discount' => $ncb_discount,
                    'total_discount' => $final_total_discount,
                    'addon_premium' => $addon_premium,
                    'total_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'cpa_premium' => $cover_pa_owner_driver_premium,
                    'proposal_no' => $proposal_response['PolicyID'] != 0 ? $proposal_response['PolicyID'] : null,
                    'customer_id' => $proposal_response['CustomerID'],
                    'unique_proposal_id' => $proposal_response['QuotationNumber'],
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?  date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))): date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)))),
                    'tp_start_date' =>$tp_start_date,
                    'tp_end_date' =>$tp_end_date,
                    'ic_vehicle_details' => $vehicleDetails,
                    'is_breakin_case' => (!$is_liability && $is_breakin) ? 'Y' : 'N',
                    'additional_details' => $proposal_addtional_details,
                    'product_code'      =>  $productcode
                ]);

                return [
                    'status' => true,
                    'msg' => 'Proposal submitted successfully',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'proposalNo' => $proposal_response['PolicyID'] != 0 ? $proposal_response['PolicyID'] : null,
                        'odPremium' => $final_od_premium,
                        'tpPremium' => $final_tp_premium,
                        'ncbDiscount' => $ncb_discount,
                        'totalPremium' => $final_net_premium,
                        'serviceTaxAmount' => $final_gst_amount,
                        'finalPayableAmount' => $final_payable_amount,
                        'isBreakinCase' => (!$is_liability && $is_breakin) ? 'Y' : 'N',
                        'is_breakin'    =>(!$is_liability && $is_breakin) ? 'Y' : 'N',
                        'inspection_number' => $inspection_no
                    ]
                ];
            } else {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium' => '0',
                    'message' => $proposal_response['ErrorText']
                ];
            }
        } else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium' => '0',
                'message' => 'Insurer not reachable'
            ];
        }
    }

    public static function renewalSubmit($proposal, $request)
    {
        if(config("ENABLE_LIBERTY_RENEWAL_API") === 'Y')
        {
        
            $requestData = getQuotation($proposal->user_product_journey_id);
            $enquiryId   = customDecrypt($request['enquiryId']);
            $productData = getProductDataByIc($request['policyId']);
            $mmv = get_mmv_details($productData,$requestData->version_id,'liberty_videocon');
            if($mmv['status'] == 1)
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
                        'mmv' => $mmv,
                    ]
                ];
            }
            $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

            if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
                return [   
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle Not Mapped',
                    'request' => [
                        'mmv' => $mmv_data,
                        'message' => 'Vehicle not mapped',
                    ]
                ];        
            } elseif ($mmv_data->ic_version_code == 'DNE') {
                return [   
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle code does not exist with Insurance company',
                    'request' => [
                        'mmv' => $mmv_data,
                        'message' => 'Vehicle code does not exist with Insurance company',
                    ]
                ];        
            }
    
            $prev_policy_end_date = $requestData->previous_policy_expiry_date;
    
            $vehicle_invoice_date = new DateTime($requestData->vehicle_invoice_date);
            $registration_date = new DateTime($requestData->vehicle_register_date);
        
            $date1 = !empty($requestData->vehicle_invoice_date) ? $vehicle_invoice_date : $registration_date;
            $date2 = new DateTime($prev_policy_end_date);
            $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
    
            $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
            $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
            $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
            $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
            $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);
            $interval = $date1->diff($date2);
          
            $noPreviousData = ($requestData->previous_policy_type == 'Not sure');
            $is_breakin = (
                (
                    (strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
                ) ? false
                : true);
  
      
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $fetch_url = config('IC.LIBERTY_VIDEOCON.V1.CAR.FETCH_RENEWAL_URL');
            // if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $proposal->idv > 5000000)
            // {
            //     $is_pos_disable = 'Y';
            // }
            // else
            // {
                $is_pos_disable = config('constants.motorConstant.IS_LIBERTY_POS_DISABLED');
            // }
            $is_pos     = (($is_pos_disable == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED'));
            $pos_name   = '';
            $pos_type   = '';
            $pos_code   = '';
            $pos_aadhar = '';
            $pos_pan    = '';
            $pos_mobile = '';
            $imd_number = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER');
            $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME');
    
            $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('seller_type','P')
                ->first();
    
            if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
    
                if(config('IS_LIBERTY_CAR_NON_POS') == 'Y')
                {
                    $pos_name = $pos_type = $pos_code = $pos_aadhar = $pos_pan = $pos_mobile = '';
    
    
                }
                else
                {
                    $pos_name   = $pos_data->agent_name;
                    $pos_type   = 'POSP';
                    $pos_code   = $pos_data->pan_no;
                    $pos_aadhar = $pos_data->aadhar_no;
                    $pos_pan    = $pos_data->pan_no;
                    $pos_mobile = $pos_data->agent_mobile;
                    $imd_number = config('IC.LIBERTY_VIDEOCON.V1.CAR.IMD_NUMBER_POS');
                    $tp_source_name = config('IC.LIBERTY_VIDEOCON.V1.CAR.TP_SOURCE_NAME_POS');
                }
            }
            $renewal_fetch_array =
            [
                "QuotationNumber"=> config('IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
                "RegNo1"=> "",
                "RegNo2"=> "",
                "RegNo3"=> "",
                "RegNo4"=> "",
                "EngineNumber"=> "",
                "ChassisNumber"=> "",
                "IMDNumber"=> $imd_number,
                "PreviousPolicyNumber"=> $proposal->previous_policy_number,
                "TPSourceName"=> $tp_source_name,
            ];
            $get_response = getWsData($fetch_url,$renewal_fetch_array, 'liberty_videocon', [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'productName'       => $productData->product_name,
                'company'           => 'liberty',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Fetch Policy Details',
                'transaction_type'  => 'proposal',
            
            ]);  
            $data = $get_response['response'];
            $response_data = json_decode($data);
            $resp_imd_number = '';
            if (!empty($response_data) && empty($response_data->ErrorText)) 
            {

                $resp_imd_number = $response_data->IMDNumber ;
                $productcode = $response_data->ProductCode;
                    //  * get applicable addons from fetch api
                    // dd("checking isset ",isset($response_data->DepreciationCover));
                $zero_depreciation_cover = 'No';
                $road_side_assistance_cover = 'No';
                $consumables_cover = 'No';
                $engine_protection_cover = 'No';
                $key_and_lock_protection_cover = 'No';
                $return_to_invoice_cover = 'No';  
    
                switch ($productData->product_identifier) {
                    case 'key_replacement':
                        $zero_depreciation_cover = 'Yes';
                        $road_side_assistance_cover = $key_and_lock_protection_cover ? 'Yes' : 'No';
                        $consumables_cover = 'Yes';
                        $engine_protection_cover = 'Yes';
                        $key_and_lock_protection_cover = 'Yes';
                        $return_to_invoice_cover = $key_and_lock_protection_cover ? 'Yes' : 'No';
                        break;
            
                    case 'consumable':
                        $zero_depreciation_cover = 'Yes';
                        $road_side_assistance_cover = 'Yes';
                        $consumables_cover = 'Yes';
                        $engine_protection_cover = 'No';
                        $key_and_lock_protection_cover = 'No';
                        $return_to_invoice_cover = 'Yes';
                        break;
            
                    case 'engprotect':
                        $zero_depreciation_cover = 'Yes';
                        $road_side_assistance_cover = 'Yes';
                        $consumables_cover = 'Yes';
                        $engine_protection_cover = 'Yes';
                        $key_and_lock_protection_cover = 'No';
                        $return_to_invoice_cover = 'Yes';
                        break;
            
                    case 'zerodep':
                        $zero_depreciation_cover = 'Yes';
                        $road_side_assistance_cover = 'Yes';
                        $consumables_cover = 'No';
                        $engine_protection_cover = 'No';
                        $key_and_lock_protection_cover = 'No';
                        $return_to_invoice_cover = 'No';
                        break;
            
                    case 'basic':
                        $zero_depreciation_cover = 'No';
                        $road_side_assistance_cover = 'Yes';
                        $consumables_cover = 'No';
                        $engine_protection_cover = 'No';
                        $key_and_lock_protection_cover = 'No';
                        $return_to_invoice_cover = 'Yes';
                        break;
                }
            
                if($interval->y > 2 || ($interval->y == 2 && $interval->m > 11) || ($interval->y == 2 && $interval->m == 11 && $interval->d > 28))
                {
                    $return_to_invoice_cover = 'No';
                }
            
                $return_to_invoice_cover = $return_to_invoice_cover == 'Yes' ? 'Yes' : 'No';
            
                $road_side_assistance_cover = (($interval->y > 9) || ($interval->y == 9 && $interval->m > 11) || ($interval->y == 9 && $interval->m == 11 && $interval->d > 28)) ? 'No' : 'Yes';
                if($is_liability)
                {
                    $zero_depreciation_cover = 'No';
                    $road_side_assistance_cover = 'No';
                    $consumables_cover = 'No';
                    $engine_protection_cover = 'No';
                    $key_and_lock_protection_cover = 'No';
                    $return_to_invoice_cover = 'No';
                }
                
                // $return_to_invoice_cover = 'No'; // RTI is disable by IC
                if(config('IC.LIBERTY_VIDEOCON.V1.CAR.RTI_DISABLED') == 'Y') {
                    $return_to_invoice_cover = 'No'; // RTI is disable by IC
                }
            
                $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                ->first();
            
                $electrical_accessories_details = '';
                $non_electrical_accessories_details = '';
                $external_fuel_kit = 'No';
                $fuel_type = $mmv_data->fuel_type;
                $external_fuel_kit_amount = '';
            
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                            if ($data['sumInsured'] < 15000 || $data['sumInsured'] > 50000) {
                                return [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
                                    'request' => [
                                        'cng_lpg_amt' => $data['sumInsured'],
                                        'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
                                    ]
                                ];
                            } else {
                                $external_fuel_kit = 'Yes';
                                $fuel_type = 'CNG';
                                $external_fuel_kit_amount = $data['sumInsured'];
                            }
                        }
            
                        if ($data['name'] == 'Non-Electrical Accessories') {
                            if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                                return [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                                    'request' => [
                                        'non_elec_acc_value' => $data['sumInsured'],
                                        'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                                    ]
                                ];
                            } else {
                                $non_electrical_accessories_details = [
                                    [
                                        'Description'     => 'Other',
                                        'Make'            => 'Other',
                                        'Model'           => 'Other',
                                        'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                                        'SerialNo'        => '1001',
                                        'SumInsured'      => $data['sumInsured'],
                                    ]
                                ];
                            }
                        }
            
                        if ($data['name'] == 'Electrical Accessories') {
                            if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                                return [
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'message' => 'Vehicle electric accessories value should be between 10000 to 25000',
                                    'request' => [
                                        'electrical_amount' => $data['sumInsured'],
                                        'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                                    ]
                                ];
                            } else {
                                $electrical_accessories_details = [
                                    [
                                        'Description'     => 'Other',
                                        'Make'            => 'Other',
                                        'Model'           => 'Other',
                                        'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                                        'SerialNo'        => '1001',
                                        'SumInsured'      => $data['sumInsured'],
                                    ]
                                ];
                            }
                        }
                    }
                }
            
                $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = 'No';
                $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
                $no_of_pa_unnamed_passenger = 1;
            
                if (!empty($additional['additional_covers'])) {
                    foreach ($additional['additional_covers'] as $key => $data) {
                        if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                            $cover_pa_paid_driver = 'Yes';
                            $cover_pa_paid_driver_amt = $data['sumInsured'];
                        }
            
                        if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                            $cover_pa_unnamed_passenger = 'Yes';
                            $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                            $no_of_pa_unnamed_passenger = $mmv_data->seating_capacity;
                        }
            
                        if ($data['name'] == 'LL paid driver') {
                            $cover_ll_paid_driver = 'Yes';
                        }
                    }
                }
            
                $is_anti_theft = 'No';
                $is_anti_theft_device_certified_by_arai = 'false';
                $is_voluntary_access = 'No';
                $voluntary_excess_amt = 0;
            
                if (!empty($additional['discounts'])) {
                    foreach ($additional['discounts'] as $key => $data) {
                        if ($data['name'] == 'anti-theft device') {
                            $is_anti_theft = 'Yes';
                            $is_anti_theft_device_certified_by_arai = 'true';
                        }
            
                        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                            $is_voluntary_access = 'Yes';
                            $voluntary_excess_amt = $data['sumInsured'];
                        }
                    }
                }
                // * Quote not allowed for ownership changed vehicle
                if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Quotes not allowed for ownership changed vehicle',
                        'request' => [
                            'message' => 'Quotes not allowed for ownership changed vehicle',
                            'requestData' => $requestData
                        ]
                    ];
                    $requestData->applicable_ncb = 0;
                }   
             
                if ($requestData->vehicle_owner_type == "I") {
                    if ($proposal->gender == "M") {
                        $salutation = 'Mr';
                    }
                    else{
                        if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                            $salutation = 'Mrs';
                        } else {
                            $salutation = 'Miss';
                        }
                    }
                }
                else{
                    $salutation = 'M/S';
                }
                $address_data = [
                    'address' => $proposal->address_line1,
                    'address_1_limit'   => 60,
                    'address_2_limit'   => 60,
                    'address_2_limit'   => 60,            
                ];
                $getAddress = self::getAddress($address_data);
                $policystart_date = !empty($response_data->PreviousPolicyEndDate) ? Carbon::createFromFormat('d/m/Y',$response_data->PreviousPolicyEndDate)->startOfDay()->addDay()->format('d/m/Y H:i:s') : Carbon::now()->format('d/m/Y H:i:s'); //pass the current date is previous policy end date is null 
                $policyend_date = Carbon::createFromFormat('d/m/Y H:i:s',$policystart_date)->addYear()->endOfDay()->subDay()->format('d/m/Y H:i:s');

            
                // * propsal submit request 
                $proposal_request = [
                    'QuickQuoteNumber' => config('IC.LIBERTY_VIDEOCON.V1.CAR.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
                    'IMDNumber' => $resp_imd_number ,//$imd_number,
                    'AgentCode' => '',
                    'TPSourceName' => $tp_source_name,
                    'ProductCode' => $productcode,
                    'IsFullQuote' => 'true',// ($is_breakin ? 'false' : 'true'),
                    'BusinessType' => $response_data->BusinessType,
                    'MakeCode' => $response_data->MakeCode,
                    'ModelCode' => $response_data->ModelCode,
                    'ManfMonth' => $response_data->ManfMonth,
                    'ManfYear' => $response_data->ManfYear,
                    'RtoCode' => $response_data->RtoCode, //$requestData->rto_code,
                    'RegNo1' =>$response_data->RegNo1,
                    'RegNo2' => $response_data->RegNo2,
                    'RegNo3' => $response_data->RegNo3,
                    'RegNo4' => $response_data->RegNo4,
                    'DeliveryDate' => $response_data->DeliveryDate,
                    'RegistrationDate' => $response_data->RegistrationDate,
                    'VehicleIDV' => $response_data->VehicleIDV,
                    'PolicyStartDate' => $policystart_date,
                    'PolicyEndDate' => $policyend_date,
                    'PolicyTenure' => $response_data->PolicyTenure,
                    'GeographicalExtn' => 'No',
                    'GeographicalExtnList' => '',
                    'DrivingTuition' => '',
                    'VintageCar' => '',
                    'LegalLiabilityToPaidDriver' => $response_data->LegalLiabilityToPaidDriver ,
                    'NoOfPassengerForLLToPaidDriver' => $response_data->NoOfPassengerForLLToPaidDriver ,
                    'LegalliabilityToEmployee' => $response_data->LegalliabilityToEmployee ,
                    'NoOfPassengerForLLToEmployee' => $response_data->NoOfPassengerForLLToEmployee ,
                    'PAUnnnamed' => $response_data->PAUnnnamed,
                    'NoOfPerunnamed' => $response_data->NoOfPerunnamed,
                    'UnnamedPASI' => $response_data->UnnamedPASI,
                    'PAOwnerDriver' => $response_data->PAOwnerDriver,
                    'PAOwnerDriverTenure' => $response_data->PAOwnerDriverTenure,
                    'LimtedtoOwnPremise' => '',
                    'ElectricalAccessories' => $response_data->ElectricalAccessories,
                    'lstAccessories' =>  'No',
                    'NonElectricalAccessories' => $response_data->NonElectricalAccessories,
                    'lstNonElecAccessories' => (!empty($response_data->lstNonElecAccessories))? $response_data->lstNonElecAccessories : 'No',
                    'ExternalFuelKit' => $external_fuel_kit,
                    'FuelType' => $response_data->FuelType,
                    'FuelSI' => $external_fuel_kit_amount,
                    'PANamed' => 'No',
                    'NoOfPernamed' => '0',
                    'NamedPASI' => '0',
                    'PAToPaidDriver'          => $response_data->PAToPaidDriver ,
                    'NoOfPaidDriverPassenger' => $response_data->NoOfPaidDriverPassenger,
                    'PAToPaidDriverSI' => $cover_pa_paid_driver_amt,
                    'AAIMembship' => 'No',
                    'AAIMembshipNumber' => $response_data->AAIMembshipNumber,
                    'AAIAssociationCode' => $response_data->AAIAssociationCode,
                    'AAIAssociationName' => $response_data->AAIAssociationName,
                    'AAIMembshipExpiryDate' => $response_data->AAIMembshipExpiryDate,
                    'AntiTheftDevice'           => $response_data->AntiTheftDevice ,
                    'IsAntiTheftDeviceCertifiedByARAI' => $is_anti_theft_device_certified_by_arai,
                    'TPPDDiscount' => $response_data->TPPDDiscount,
                    'ForeignEmbassy' => '',
                    'VoluntaryExcess' => $is_voluntary_access,
                    'VoluntaryExcessAmt' => $voluntary_excess_amt,
                    'NoNomineeDetails' => $requestData->vehicle_owner_type == 'I' ? 'false' : 'true',
                    'NomineeFirstName' => 'john',
                    'NomineelastName' => 'doe',
                    'NomineeRelationship' => 'brother',
                    'OtherRelation' => '',
                    'IsMinor' => 'false',
                    'RepFirstName' => '',
                    'RepLastName' => '',
                    'RepRelationWithMinor' => '',
                    'RepOtherRelation' => '',
                    'NoPreviousPolicyHistory'   => $response_data->NoPreviousPolicyHistory,
                    'IsNilDepOptedInPrevPolicy' => $response_data->IsNilDepOptedInPrevPolicy,
                    'PreviousPolicyInsurerName' => $response_data->PreviousPolicyInsurerName,
                    'PreviousPolicyType'        => $response_data->PreviousPolicyType,#LIABILITYPOLICY
                    'PreviousPolicyStartDate'   => $response_data->PreviousPolicyStartDate,
                    'PreviousPolicyEndDate'     => $response_data->PreviousPolicyEndDate,
                    'PreviousPolicyNumber'      => $response_data->PreviousPolicyNumber,
                    'PreviousYearNCBPercentage' => $response_data->PreviousYearNCBPercentage,
                    'ClaimAmount'               => $response_data->ClaimAmount,
                    'NoOfClaims'                => $response_data->NoOfClaims,
                    'PreviousPolicyTenure'      => $response_data->PreviousPolicyTenure,
                    'IsInspectionDone'          => $response_data->IsInspectionDone,
                    'InspectionDoneByWhom'      => $response_data->InspectionDoneByWhom,
                    'ReportDate'                => $response_data->ReportDate,
                    'InspectionDate'            => $response_data->InspectionDate,
                    'ConsumableCover'           => $response_data->ConsumableCover,
                    'DepreciationCover'         => $response_data->DepreciationCover,
                    'RoadSideAsstCover'         => $response_data->RoadSideAsstCover,
                    'GAPCover'                  => $response_data->GAPCover,
                    'GAPCoverSI'                => '0',
                    'EngineSafeCover'           => $response_data->EngineSafeCover,
                    'KeyLossCover'              => $response_data->KeyLossCover,  
                    'KeyLossCoverSI'            => $response_data->KeyLossCoverSI,
                    'PassengerAsstCover'        => $response_data->PassengerAsstCover,
                    'EngineNo'                  => $response_data->EngineNo,
                    'ChassisNo'                 => $response_data->ChassisNo,
                    'BuyerState'                => $response_data->BuyerState,
                    'POSPName'                  => $pos_name,
                    'POSPType'                  => $pos_type,
                    'POSPCode'                  => $pos_code,
                    'POSPAadhar'                => $pos_aadhar,
                    'POSPPAN'                   => $pos_pan,
                    'POSPMobileNumber'          => $pos_mobile,
                    'CustmerObj' => [
                        'TPSource' => 'TPService',
                        'CustomerType' => $requestData->vehicle_owner_type,
                        'Salutation' => $salutation,
                        'FirstName' => str_replace(".", "", $proposal->first_name ?? ''),
                        'LastName' => $proposal->last_name,
                        'DOB' => date('d/m/Y', strtotime($proposal->dob)),
                        'EmailId' => $proposal->email,
                        'MobileNumber' => $proposal->mobile_number,
                        'AddressLine1' => $proposal->is_car_registration_address_same == 1 ? $getAddress['address_1'] : $proposal->car_registration_address1,
                        'AddressLine2' => $proposal->is_car_registration_address_same == 1 ? $getAddress['address_2'] : $proposal->car_registration_address2,
                        'AddressLine3' => $proposal->is_car_registration_address_same == 1 ? $getAddress['address_3'] : $proposal->car_registration_address3,
                        'PinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                        'StateCode' => $proposal->state_id,
                        'StateName' => $proposal->state,
                        'PinCodeLocality' => '',
                        'PanNo' => $proposal->pan_number ?? '',
                        'PermanentLocationSameAsMailLocation' => $proposal->is_car_registration_address_same == 1 ? 'true' : 'false',
                        'MailingAddressLine1' => $proposal->address_line1,
                        'MailingPinCode' => $proposal->pincode,
                        'MailingPinCodeLocality' => $proposal->city,
                        'IsEIAAvailable' => 'No',
                        'EIAAccNo' => '',
                        'IsEIAPolicy' => 'No',
                        'EIAAccWith' => '',
                        'EIAPanNo' => '',
                        'EIAUIDNo' => '',
                        'GSTIN' => $proposal->gst_number ?? ''
                    ]
                    
                ];

                if (!empty($response_data->PreviousPolicyODStartDate)) {
                    $proposal_request['PreviousPolicyODStartDate'] = $response_data->PreviousPolicyODStartDate;
                }
                if (!empty($response_data->PreviousPolicyODEndDate)) {
                    $proposal_request['PreviousPolicyODEndDate'] = $response_data->PreviousPolicyODEndDate;
                }
                if (!empty($response_data->PreviousPolicyTPStartDate)) {
                    $proposal_request['PreviousPolicyTPStartDate'] = $response_data->PreviousPolicyTPStartDate;
                }
                if (!empty($response_data->PreviousPolicyTPEndDate)) {
                    $proposal_request['PreviousPolicyTPEndDate'] = $response_data->PreviousPolicyTPEndDate;
                }
                if (!empty($response_data->PreviousTPPolicyTenure)) {
                    $proposal_request['PreviousTPPolicyTenure'] = $response_data->PreviousTPPolicyTenure;
                }
                if (!empty($response_data->PreviousPolicyInsurerNameTP)) {
                    $proposal_request['PreviousPolicyInsurerNameTP'] = $response_data->PreviousPolicyInsurerNameTP;
                }
                if (!empty($response_data->PreviousPolicyInsurerNameOD)) {
                    $proposal_request['PreviousPolicyInsurerNameOD'] = $response_data->PreviousPolicyInsurerNameOD;
                }
                if (!empty($response_data->PreviousPolicyNumberTP)) {
                    $proposal_request['PreviousPolicyNumberTP'] = $response_data->PreviousPolicyNumberTP;
                }
                if (!empty($response_data->PreviousPolicyNumberOD)) {
                    $proposal_request['PreviousPolicyNumberOD'] = $response_data->PreviousPolicyNumberOD;
                }
                if (!empty($response_data->isActiveTPPolicyAvailable)) {
                    $proposal_request['isActiveTPPolicyAvailable'] = $response_data->isActiveTPPolicyAvailable;
                }

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $ckyc_meta_data = json_decode(($proposal->ckyc_meta_data ?? "[]"), true);
        
                    $kyc_param = [
                        'Aggregator_KYC_Req_No'    => ($ckyc_meta_data["Aggregator_KYC_Req_No"] ?? ''),
                        'IC_KYC_No'    => ($ckyc_meta_data["IC_KYC_No"] ?? ''),
                    ];
        
                    $proposal_request = array_merge($proposal_request, $kyc_param);
                }
            
                $get_response = getWsData(config('IC.LIBERTY_VIDEOCON.V1.CAR.END_POINT_URL_PREMIUM_CALCULATION'), $proposal_request, 'liberty_videocon', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'liberty_videocon',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Premium Calculation',
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'Content-Type' => 'Application/json'
                    ]
                ]);
                
                $data = $get_response['response'];
                if ($data) 
                {
                    $proposal_response = json_decode($data, TRUE);
        
                    if (empty($proposal_response['ErrorText'])) 
                    {
                        $llpaiddriver_premium = ($proposal_response['LegalliabilityToPaidDriverValue']);
                        $cover_pa_owner_driver_premium = ($proposal_response['PAToOwnerDrivervalue']);
                        $cover_pa_paid_driver_premium = ($proposal_response['PatoPaidDrivervalue']);
                        $cover_pa_unnamed_passenger_premium = ($proposal_response['PAToUnnmaedPassengerValue']);
                        $voluntary_excess = ($proposal_response['VoluntaryExcessValue']);
                        $anti_theft = ($proposal_response['AntiTheftDiscountValue']);
                        $ic_vehicle_discount = ($proposal_response['Loading']) + ($proposal_response['Discount'] ?? 0);
                        $ncb_discount = ($proposal_response['DisplayNCBDiscountvalue']);
                        $od = ($proposal_response['BasicODPremium']);
                        $tppd = ($proposal_response['BasicTPPremium']);
                        $cng_lpg = ($proposal_response['FuelKitValueODpremium']);
                        $cng_lpg_tp = ($proposal_response['FuelKitValueTPpremium']);
                        $zero_depreciation = ($proposal_response['NilDepValue']);
                        $road_side_assistance = ($proposal_response['RoadAssistCoverValue']);
                        $engine_protection = ($proposal_response['EngineCoverValue']);
                        $return_to_invoice = ($proposal_response['GAPCoverValue']);
                        $consumables = ($proposal_response['ConsumableCoverValue']);
                        $key_protect = ($proposal_response['KeyLossCoverValue']);
                        $passenger_assist_cover = ($proposal_response['PassengerAssistCoverValue']);
                        $electrical_accessories_amt = ($proposal_response['ElectricalAccessoriesValue']);
                        $non_electrical_accessories_amt = ($proposal_response['NonElectricalAccessoriesValue']);
        
                        $addon_premium = $zero_depreciation + $road_side_assistance + $engine_protection + $return_to_invoice + $key_protect + $consumables + $passenger_assist_cover;
                        $final_od_premium = ($proposal_response['TotalODPremiumValue']);
                        $final_tp_premium = ($proposal_response['TotalTPPremiumValue']);
                        $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $anti_theft;
                        $final_net_premium = ($proposal_response['NetPremium']);
                        $final_gst_amount = ($proposal_response['GST']);
                        $final_payable_amount  = $proposal_response['TotalPremium'];
        
                        $vehicleDetails = [
                            'manufacture_name' => $mmv_data->manufacturer,
                            'model_name' => $mmv_data->vehicle_model,
                            'version' => $mmv_data->variant,
                            'fuel_type' => $mmv_data->fuel_type,
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'carrying_capacity' => $mmv_data->seating_capacity - 1,
                            'cubic_capacity' => $mmv_data->cubic_capacity,
                            'gross_vehicle_weight' => $mmv_data->gross_vehicle_weight,
                            'vehicle_type' => 'PRIVATE CAR'
                        ];
                        $vehicle_registration_no = explode('-', $proposal->vehicale_registration_number);
                        if(!$is_liability && $is_breakin)
                        {
                            $request_details['vehicle_registration_no'] = $vehicle_registration_no;
                            $request_details['enquiryId']       = $request['enquiryId'];
                            $request_details['is_individual']   = $is_individual;
        
                            $lead_response = self::leadCreation($proposal, $mmv, $request_details, $productData, $requestData);
        
                            if(!$lead_response['status'])
                            {
                                return $lead_response;
                            }
        
                            $inspection_no = $lead_response['LeadID'];
                        } else 
                        {
                            updateJourneyStage([
                                'user_product_journey_id' => $proposal->user_product_journey_id,
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        }
                        $policy_start_date = Carbon::createFromFormat('d/m/Y H:i:s',$proposal_response['PolicyStartDate']) ;
                        $policy_end_date = Carbon::createFromFormat('d/m/Y H:i:s',$proposal_response['PolicyEndDate']);
                        $policy_start_date = $policy_start_date->format('d-m-Y');
                        $policy_end_date = $policy_end_date->format('d-m-Y');
                        // dd(date('d-m-Y', strtotime($policy_start_date)),date('d-m-Y', strtotime($policy_end_date)));
                        // proposal_request

                        $proposal_addtional_details = json_decode($proposal->additional_details, true);              
                        $proposal_addtional_details['liberty']['proposal_request'] = $proposal_request;
        
                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                            'od_premium' => $final_od_premium,
                            'tp_premium' => $final_tp_premium,
                            'ncb_discount' => $ncb_discount,
                            'total_discount' => $final_total_discount,
                            'addon_premium' => $addon_premium,
                            'total_premium' => $final_net_premium,
                            'service_tax_amount' => $final_gst_amount,
                            'final_payable_amount' => $final_payable_amount,
                            'cpa_premium' => $cover_pa_owner_driver_premium,
                            'proposal_no' => $proposal_response['PolicyID'] != 0 ? $proposal_response['PolicyID'] : null,
                            'customer_id' => $proposal_response['CustomerID'],
                            'unique_proposal_id' => $proposal_response['QuotationNumber'],
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                            'ic_vehicle_details' => $vehicleDetails,
                            'is_breakin_case' => (!$is_liability && $is_breakin) ? 'Y' : 'N',
                            'additional_details' => $proposal_addtional_details,
                        ]);

                        LibertyVideoconPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
        
                        return [
                            'status' => true,
                            'msg' => 'Proposal submitted successfully',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'proposalNo' => $proposal_response['PolicyID'] != 0 ? $proposal_response['PolicyID'] : null,
                                'odPremium' => $final_od_premium,
                                'tpPremium' => $final_tp_premium,
                                'ncbDiscount' => $ncb_discount,
                                'totalPremium' => $final_net_premium,
                                'serviceTaxAmount' => $final_gst_amount,
                                'finalPayableAmount' => $final_payable_amount,
                                'isBreakinCase' =>  'N',
                                'is_breakin'  =>   'N',
                            ]
                        ];
                    } else 
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium' => '0',
                            'message' => $proposal_response['ErrorText']
                        ];
                    }
                } else 
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium' => '0',
                        'message' => 'Insurer not reachable'
                    ];
                }
            }
            elseif($response_data->ErrorText!='')
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium' => '0',
                    'message' => $response_data->ErrorText
                ];
            }
        }
        else
        {
            return [
                'status' => false,
                'premium' => '0',
                
                'message' => "no proposal submit for Renewal contact ic "
            ];
        }
}

    public static function leadCreation($proposal, $mmv, $request_details, $productData, $requestData){
        $lead_request = [
            "UserID"            => config('IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_USER_ID'),
            "Passwd"            => config('IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_PASSWORD'),
            "IntimatorName"     => config('IC.LIBERTY_VIDEOCON.V1.CAR.breakin.PI_USER_ID'),
            "IntimatorPhone"    => '',
            "IntimatorEmailId"  => config('IC.LIBERTY_VIDEOCON.V1.CAR.EMAIL'),
            "ProposalID"        => $proposal->user_proposal_id . time(),
            "AgencyID"          => config('IC.LIBERTY_VIDEOCON.V1.CAR.breakin.AgencyID'),//'tp123',//$proposal_details['agency_name'],
            "BranchID"          => config('IC.LIBERTY_VIDEOCON.V1.CAR.breakin.BranchID'),//'1199',//$proposal_details['branch_id'],

            "CustomerName"      => ($request_details['is_individual'] ? (str_replace(".", "", $proposal->first_name ?? '') . " " . $proposal->last_name) : $proposal->first_name),
            "CustomerPhone"     => $proposal->mobile_number,

            "VehicleInspectionAddress" => (
                $proposal->address_line1
                . "," . $proposal->address_line2
                . "," . $proposal->address_line3
                . "," . $proposal->pincode
            ),

            "ModelID"           => $mmv->vehicle_model_code,
            "MakeID"            => $mmv->manufacturer_code,

            "VehicleType"       => "PVT. CAR",
            "VehicleRegNo"      => $request_details['vehicle_registration_no'][0]
                . '' . $request_details['vehicle_registration_no'][1]
                . '' . $request_details['vehicle_registration_no'][2]
                . '' . $request_details['vehicle_registration_no'][3],
            "EngineNo"          => $proposal->engine_number,
            "ChassisNo"         => $proposal->chassis_number,
            "MfgYear"           => date('Y', strtotime('01-'.$requestData->manufacture_year)),

            "InspectionDate"    => date('Y-m-d'),
            "InspectionTime"    => date('H:i:s'),

            "RegistrationType"  => "Registered",
            "Purposeofinspection" => "Break-In",

            "ZipFileSize"       => "",
            "ChannelType"       => "",
            "FeestobeBornBy"    => "",
            "SalesManager"      => config('IC.LIBERTY_VIDEOCON.V1.CAR.SALESMANAGER'),
        ];

        $lead_create_request = [
            $lead_request
        ];

        $container = '
            <Envelope
                xmlns="http://schemas.xmlsoap.org/soap/envelope/"
            >
                <Body>
                <CreateLeadJson xmlns="http://www.claimlook.com/">
                    <json>#replace</json>
                </CreateLeadJson>
                </Body>
            </Envelope>
        ';

        $additional_data = [
            'enquiryId'     => $proposal->user_product_journey_id,
            'requestMethod' => 'post',
            'productName'   => $productData->product_name,
            'company'       => 'liberty_videocon',
            'section'       => $productData->product_sub_type_code,
            'method'        => 'Lead Creation - Proposal',
            'transaction_type' => 'proposal',
            'root_tag'      => 'json',
            // 'soap_action'   => 'CreateLeadJson',
            'SOAPAction'   => 'http://www.claimlook.com/CreateLeadJson',
            'content_type'  => 'text/xml;',
            'container'     => $container,
        ];

        $get_response = getWsData(
            config('IC.LIBERTY_VIDEOCON.V1.CAR.breakin.END_POINT_URL_PI_LEAD_ID_CREATE'),
            $lead_request,
            'liberty_videocon',
            $additional_data
        );

        $lead_create_response = $get_response['response'];
        if(!$lead_create_response || $lead_create_response == ''){
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium'   => '0',
                'message'   => 'insurer Not Reachable',
                'LeadID'    => '',
                'data'      => []
            ];
        }

        $lead_response = html_entity_decode($lead_create_response);
        $lead_response = XmlToArray::convert($lead_response);
        $lead_response = json_decode($lead_response['soap:Body']['CreateLeadJsonResponse']['CreateLeadJsonResult'], true)[0];

        if($lead_response['Return'] != 'S'){
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium'   => '0',
                'message'   => $lead_response['Error'],
                'LeadID'    => '',
                'data'      => []
            ];
        }

        $inspection_no = $lead_response['LeadID'];

        DB::table('cv_breakin_status')
        ->updateOrInsert(
            [
                'ic_id'             => $productData->company_id,
                'breakin_number'    => $lead_response['LeadID'],
                'breakin_id'        => $lead_response['LeadID'],
                'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                'breakin_response'  => $lead_create_response,
                'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                'created_at'        => Carbon::today()->toDateString()
            ],
            [
                'user_proposal_id'  => $proposal->user_proposal_id
            ]
        );

        updateJourneyStage([
            'user_product_journey_id'   => $proposal->user_product_journey_id,
            'ic_id'                     => $productData->company_id,
            'stage'                     => STAGE_NAMES['INSPECTION_PENDING'],
            'proposal_id'               => $proposal->user_proposal_id
        ]);

        return [
            'status'    => true,
            'premium'   => '0',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message'   => 'success',
            'LeadID'    => $lead_response['LeadID'],
            'data'      => $lead_response
        ];
    }

    public static function getAddress($addressData)
    {
        $addressarr = explode(' ', preg_replace('/\s+/', ' ', trim($addressData['address'])));

        $min = (count($addressarr)/3);

        $address_line1 = '';
        $address_line2 = '';
        $address_line3 = '';

        foreach($addressarr as $key => $value)
        {
            if(($key < $min) && (strlen($address_line1) < $addressData['address_1_limit']))
            {
                $address_line1 = trim($address_line1. ' ' . $value);
            }
            else{
                if((($key >= $min) && ($key < $min*2)) && (strlen($address_line2) < $addressData['address_2_limit']))
                {
                    $address_line2 = trim($address_line2. ' ' . $value);
                }
                else
                {
                    $address_line3 = trim($address_line3. ' ' . $value);
                }
            }
        }

        return [
            'address_1' => $address_line1,
            'address_2' => $address_line2,
            'address_3' => $address_line3,
        ];
    }
    public static function getKeyLossCoverSI($mmv_data)
    {
        $KeyLossCoverSIArray = [
            'MID SIZE' => 10000,
            'COMPACT' => 10000,
            'EXECUTIVE' => 20000,
            'HIGH END' => 30000,
            'SUV 1' => 20000,
            'SUV 2' => 20000,
        ];
        return $KeyLossCoverSIArray[$mmv_data->segment_type];
    }
}
