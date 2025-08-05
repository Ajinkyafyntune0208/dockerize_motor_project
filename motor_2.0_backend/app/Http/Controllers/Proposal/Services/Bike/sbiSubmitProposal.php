<?php

namespace App\Http\Controllers\Proposal\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/SbiCkycHelper.php';

use DateTime;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\SbiPremiumDetailController;
use Illuminate\Support\Carbon;
use Exception;
use App\Models\ckycUploadDocuments;

class sbiSubmitProposal
{
    public static function submit($proposal, $request)
    {
        if ($proposal->is_ckyc_verified != 'Y' && config('SBI.CKYC_VALIDATION') == 'Y') {
            return  [
                'status' => false,
                'message' => 'CKYC Failed'
            ];
        }
        if ($proposal->is_ckyc_verified != 'Y' && config('SBI.COMPANY_CASE.CKYC_VALIDATION') == 'Y' && $proposal->owner_type == 'C') {
            return  [
                'status' => false,
                'message' => 'CKYC Failed for Company Case'
            ];
        }
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);

        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        {
            return  response()->json([
                'status' => false,
                'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            ]);
        } */
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $jsontoarray=json_decode($quote->premium_json,true);
        $quotationNo=$jsontoarray['quotationNo'];
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'sbi');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $additional_details = json_decode($proposal->additional_details, true);

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime(($requestData->previous_policy_expiry_date == 'New')|| ($requestData->business_type == 'breakin') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $carage = ceil($age / 12);
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }
     $rto_data = DB::table('sbi_bike_rto_location')
        ->where('RTO_CODE', 'like', $requestData->rto_code)
        ->first();
    $rto_data = keysToLower($rto_data);
        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $KindofPolicy = '1'; //'6';
            // $PolicyType = '6';
            $policy_start_date =date('Y-m-d');
            $IsPreviousClaim = '0';
            $preinsurercode = '';
            $policy_end_date =date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            $ExpiryDate = date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            $nb_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime($policy_start_date)));
            $time = date('h:i:s');
        } else {
            $KindofPolicy = ($premium_type == 'third_party') ? '2' : (($premium_type == 'own_damage') ? '3' :  '1');
            if ($premium_type == 'own_damage') {

                //$PolicyType = '9';
                $BusinessType = '2';
                $tp_start_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));
            } else {

                //$PolicyType = '1';
                $BusinessType = '2';
                $tp_start_date = '';
                $tp_end_date = '';
            }
            /* $sbilocality = DB::table('sbi_pincode_state_city_master')
                            ->select('LCLTY_SUBRB_TALUK_TEHSL_NM as locality')
                            ->where('PIN_CD', $proposal->pincode)->first(); */
            if ($requestData->business_type == 'rollover') {
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            } elseif ($requestData->business_type == 'breakin') {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }
            $IsPreviousClaim = ($requestData->is_claim == 'Y') ? '0' : '1';
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            $age = get_date_diff('year', $proposal->dob);
            $expdate =($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $days = get_date_diff('day', $expdate);
            $time =( $days > 0) ? date('h:i:s'): '00:00:00';
        }
        
        if ($premium_type == 'comprehensive' || $premium_type == 'breakin') {
            $PolicyType = ($requestData->business_type == 'newbusiness') ? '6' :'1';
        }elseif($premium_type == 'third_party'){
            if ($requestData->business_type == 'newbusiness') {
                $PolicyType = '8';
            } else {
                $PolicyType = '2';
            }
        }elseif($premium_type == 'own_damage'){
            $PolicyType = '9';
        }

        $arr_idv = [
            'idv_amount' => $quote->idv,
            'exshowroomprice' => $quote->idv,
        ];
        if ($requestData->previous_policy_expiry_date === '') {
            $prepolstartdate = '1900-01-01';
            $prepolicyenddate = '1900-01-01';
        } else {
            $prepolstartdate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $prepolicyenddate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
        }
        $vehicle_regno = explode("-",$proposal->vehicale_registration_number);
        if($requestData->vehicle_registration_no == 'NEW')
        {
            $vehicle_registration_no  = str_replace("-", " ", $requestData->rto_code);
            $vehicle_regno = explode("-",$requestData->rto_code);
        }
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $expdate=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $date_difference = get_date_diff('day', $expdate);
            if ($date_difference > 90) {  
                $requestData->applicable_ncb = 0;
            }
        }
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                            ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                            ->first();

        $electrical_accessories = false;
        $electrical_accessories_amount = 0;
        $non_electrical_accessories = false;
        $non_electrical_accessories_amount = 0;
        $external_fuel_kit = false;
        $external_fuel_kit_amount = 0;
        $compulsory_personal_accident = false;
        $road_side_assistance_selected = false;
        $zero_depreciation_cover_selected = false;
        $rti= false;
        $Key_Replacement_Cover = false;
        $engine= false;
        $is_ncb_protection= false;
        $is_consumable_protection= false;
        $loss_of_personal_belongings = false;
        $Tyre_secure = false;
        $is_tppd = false;

        #addon age limit code start
        $engineprotector_age_limit =  date('Y-m-d', strtotime($policy_start_date . ' - 28 days - 11 months - 4 year'));
        $rsa_addon_age_limit = date('Y-m-d', strtotime($policy_start_date . ' - 28 days - 11 months - 6 year'));
        $rti_addon_age_limit = date('Y-m-d', strtotime($policy_start_date . ' - 28 days - 11 months - 2 year'));
        if(strtotime($engineprotector_age_limit) > strtotime($requestData->vehicle_register_date))
        {
            $engineprotector_age = false;
            $consumable_age  = false;
        }else{
            $engineprotector_age = true;
            $consumable_age  = true;
        }

        if(strtotime($rsa_addon_age_limit) > strtotime($requestData->vehicle_register_date))
        {
            $rsa_addon_age= false;
        }else{
            $rsa_addon_age= true;
        }

        if(strtotime($rti_addon_age_limit) > strtotime($requestData->vehicle_register_date))
        {
            $rti_addon_age = false;
        }else{
            $rti_addon_age = true;
        }
        $rti_addon_age = false;
        #addon age limit code end
        $tenure = '';
        if (!empty($additional['compulsory_personal_accident'])) {//cpa
            foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                    $compulsory_personal_accident = true;
                    $tenure = isset($data['tenure']) && $data['tenure'] == 5 ? '5' : '1';
                }
            }
        }

        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
        {
            if($requestData->business_type == 'newbusiness')
            {
                $tenure = isset($tenure) ? $tenure :'5'; 
            }
            else{
                $tenure = isset($tenure) ? $tenure :'1';
            }
        }
        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Road Side Assistance' && $rsa_addon_age) {
                    $road_side_assistance_selected = true;
                }
                if ($data['name'] == 'Zero Depreciation' && $productData->zero_dep == '0' && $carage <= 5) {
                    $zero_depreciation_cover_selected = true;
                }
                if ($data['name'] == 'Return To Invoice' && $rti_addon_age) {
                    $rti = true;
                }
                if ($data['name'] == 'Engine Protector' && $engineprotector_age) {
                    $engine = true;
                }
                if ($data['name'] == 'Consumable' && $consumable_age) {
                    $is_consumable_protection = true;
                }
            }
        }
        $is_consumable_protection = false;//CONSUMABLE COVER IS NOT PROVIDED BY IC AS PER MAIL GIT ID 9372
        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    if ($data['sumInsured'] < 15000 || $data['sumInsured'] > 50000) {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
                        ];
                    } else {
                        $external_fuel_kit = true;
                        $fuel_type = 'CNG';
                        $external_fuel_kit_amount = $data['sumInsured'];
                    }
                }

                if ($data['name'] == 'Non-Electrical Accessories') {
                    $non_electrical_accessories = true;
                    $non_electrical_accessories_amount = $data['sumInsured'];
                }

                if ($data['name'] == 'Electrical Accessories') {
                    $electrical_accessories = true;
                    $electrical_accessories_amount = $data['sumInsured']; 
                }
            }
        }
        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $data) {
                if ($data['name'] == 'TPPD Cover') {
                    $is_tppd = true;
                }
            }
        }

        $is_anti_theft = '0';
        $is_anti_theft_device_certified_by_arai = 'false';
        $is_voluntary_access = false;
        $voluntary_excess_amt = 0;

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'anti-theft device') {
                    $is_anti_theft = '1';
                    $is_anti_theft_device_certified_by_arai = 'true';
                }

                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                    $is_voluntary_access = true;
                    $voluntary_excess_amt = $data['sumInsured'];
                }
            }
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = false;
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $no_of_paid_driver = 0;
        $is_geo_ext = false;
        $is_geo_code = 0;
        $srilanka = 0;
        $pak = 0;
        $bang = 0;
        $bhutan = 0;
        $nepal = 0;
        $maldive = 0;

        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']) && $requestData->vehicle_owner_type == 'I') {
                    $cover_pa_paid_driver = true;
                    $cover_pa_paid_driver_amt = $data['sumInsured'];
                    $no_of_paid_driver = 1;
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']) && $requestData->vehicle_owner_type == 'I') {
                    $cover_pa_unnamed_passenger = true;
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                    $no_of_pa_unnamed_passenger = $mmv->seating_capacity;
                }

                if ($data['name'] == 'LL paid driver') {
                    $cover_ll_paid_driver = true;
                    $no_of_paid_driver = 1;
                }
                if ($data['name'] == 'Geographical Extension') {
                    $is_geo_ext = true;
                    $is_geo_code = 1;
                    $countries = $data['countries'];
                    if (in_array('Sri Lanka', $countries)) {
                        $srilanka = 1;
                    }
                    if (in_array('Bangladesh', $countries)) {
                        $bang = 1;
                    }
                    if (in_array('Bhutan', $countries)) {
                        $bhutan = 1;
                    }
                    if (in_array('Nepal', $countries)) {
                        $nepal = 1;
                    }
                    if (in_array('Pakistan', $countries)) {
                        $pak = 1;
                    }
                    if (in_array('Maldives', $countries)) {
                        $maldive = 1;
                    }
                }
            }
        }
        $is_pos = false;
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_SBI_TESTING');
        $posp_name = '';
        $posp_unique_number = '';
        $posp_pan_number = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
            $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                        ->pluck('sbi_code')
                        ->first();
            if ((empty($pos_code) || is_null($pos_code)) && (config('IS_SBI_BIKE_NON_POS') != 'Y'))
            {
                return [
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'Child Id Is Not Available'
                ];
            }
            if (config('IS_SBI_BIKE_NON_POS') != 'Y') {
                $is_pos = true;
                $posp_name = $pos_data->agent_name;
                $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
                $posp_pan_number = $pos_data->pan_no;
            }
        }else if($is_pos_enabled_testing == 'Y' && $quote->idv <= 5000000)
        {
            $is_pos = true;
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = '569278616999';
        }

        $registration_number = str_replace('-', '', $proposal->vehicale_registration_number);

        //rto code DL condition
        $reg_no = explode('-', isset($proposal->vehicale_registration_number) && $proposal->vehicale_registration_number != 'NEW' ? $proposal->vehicale_registration_number : $requestData->rto_code);
        if(isset($reg_no[0]) && ($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10))
        {
            $registration_number = $reg_no[0] . (!empty(substr($reg_no[1],1)) ? substr($reg_no[1],1) : substr($reg_no[1],0)) . (isset($reg_no[2]) ? $reg_no[2] : '') . (isset($reg_no[3]) ? $reg_no[3] : '');
        }

        if (
            str_starts_with(strtoupper($registration_number), 'DL0')
        ) {
            $registration_number = getRegisterNumberWithHyphen($registration_number);
            $registration_number = explode('-', $registration_number);
            $registration_number[1] = ((int) $registration_number[1] * 1);
            $registration_number = implode('-', $registration_number);
            $registration_number = str_replace('-', '', $registration_number);
        }

        // $district_data = DB::table('sbi_motor_city_master')
        //     ->where('city_cd', 'like', $proposal->city_id)
        //     ->first();
        $district_data = DB::table('sbi_pincode_state_city_master')
            ->where('CITY_CD', '=', $proposal->city_id)
            ->first();
        $district_data = keysToLower($district_data);
            if(config('constants.brokerName') == 'TMIBASL')
            {
                if($requestData->business_type == 'newbusiness')
                {
                    $KindofPolicy = '1';
                }
                else if(in_array($requestData->previous_policy_type, ['Comprehensive']))
                {
                    $KindofPolicy = '1';
                    if(in_array($premium_type, ['own_damage', 'own_damage_breakin']))
                    {
                        $KindofPolicy = '1'; // as per dhruv and nirmal sir
                    }
                }
                else if(in_array($requestData->previous_policy_type, ['Third-party']))
                {
                    $KindofPolicy = '2';
                    if(in_array($premium_type, ['own_damage', 'own_damage_breakin']))
                    {
                        $KindofPolicy = '5';
                    }
                }else if(in_array($requestData->previous_policy_type, ['Own-damage']))
                {
                    $KindofPolicy = '1';
                    if(in_array($premium_type, ['own_damage', 'own_damage_breakin']))
                    {
                        $KindofPolicy = '1';
                    }
                }
            }

            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 24,
                'address_2_limit'   => 24,
                'address_3_limit'   => 100            
            ];
            $getAddress = getAddress($address_data);
//            if(isset($getAddress['address_4']) && !empty($getAddress['address_4']))
//            {
//                return  [
//                    'premium_amount' => 0,
//                    'status' => false,
//                    'message' => " Address should not exceed 72 Characters "
//                ];
//            }

            // if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  != 'Y') {
            //     $kyc_status = $verification_status = $status = true;
            //     $ckyc_result = [];
            //     $msg = $kyc_message = 'Proposal Submitted Successfully!';

            //     $ckyc_result = ckycVerifications($proposal);

            //     if ( ! isset($ckyc_result['status']) || ! $ckyc_result['status']) {
            //         $kyc_status = $verification_status  = $status = false;
            //         $defaultErr = $ckyc_result['message'] ?? 'An unexpected error occurred';
            //         $isModeCKYC = ! empty($ckyc_result['mode']) && $ckyc_result['mode'] == 'ckyc_number';
            //         $defaultErrCKYCNO = 'CKYC verification failed using CKYC number. Please check the entered CKYC Number or try with another method';

            //         $msg = $kyc_message = ($proposal->proposer_ckyc_details?->is_document_upload == 'Y' ? $defaultErr : ( $isModeCKYC ? $defaultErrCKYCNO : 'CKYC verification failed. Try other method'));

            //         return [
            //             'status' => false,
            //             'msg' => $msg,
            //         ];
            //     }
            //     $proposal = UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            // }
            //CKYC Address
            $address_ckyc_data = json_decode($proposal->ckyc_meta_data);

            $proposal_array = [
                'RequestHeader' =>
                [
                    'requestID' => mt_rand(100000, 999999),
                    'action' => 'fullQuote',
                    'channel' => 'SBIG',
                    'transactionTimestamp' => date('d-M-Y-H:i:s'),
                ],
                'RequestBody' =>
                [
                    'QuotationNo' => $quotationNo,
                    'AdditonalCompDeductible' => '',
                    'AgreementCode' => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID'),
                    'BusinessSubType' => '1',
                    'BusinessType' => $BusinessType,
                    'ChannelType' => '3',
                    'CustomerGSTINNo' => isset($proposal->gst_number) ? $proposal->gst_number:'',
                    'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? date('Y-m-d',strtotime($policy_start_date)).'T'.date('H:i:s') : date('Y-m-d',strtotime($policy_start_date)).'T00:00:00',
                    'ExpiryDate' => $policy_end_date . 'T23:59:59',
                    'GSTType' => 'IGST',
                    'HasPrePolicy' => $requestData->business_type == 'newbusiness' ? '0' : '1',
                    'IntermediaryCode' => config('constants.IcConstants.sbi.SBI_INTERMEDIARY_CODE_VALUE_BIKE') ?? '',
                    'IssuingBranchCode' => 'HO',
                    'KindofPolicy' => $KindofPolicy,
                    'NewBizClassification' => (string) $BusinessType,
                    'PolicyCustomerList' =>
                    [
                        0 =>
                        [
                           
                            'AadharNumUHID' => '', 
                            'BuildingHouseName' => $address_ckyc_data->addressLine2 ?? $getAddress['address_2'] ??  '',
                            'City' => $proposal->city_id, 
                            'Email' => $proposal->email, 
                            'ContactEmail' =>  $proposal->email, 
                            'ContactPersonTel' => '', 
                            'CountryCode' => '1000000108', 
                            //'Title' => ($quote_data->vehicle_owner_type == 'I') ? (($proposal->gender == 'MALE') ? '9000000001' : '9000000003') : '9000000001', 
                            'FirstName' =>  $proposal->first_name, 
                            'MiddleName' => '', 
                            'LastName' => $proposal->last_name , 
                            'CustomerName' => ($quote_data->vehicle_owner_type == 'I') ? $proposal->first_name . ' ' . $proposal->last_name : $proposal->first_name, 
                            'DateOfBirth' => ($quote_data->vehicle_owner_type == 'I') ? date('Y-m-d', strtotime($proposal->dob)) : '', 
                            'DateOfIncorporation' => ($quote_data->vehicle_owner_type == 'C') ? date('Y-m-d', strtotime($proposal->dob)) : '', 
                            'District' => $district_data->district_code, 
                            'GSTRegistrationNum' => '', 
                            'GSTInputState' => '', 
                            'GenderCode' => $proposal->gender, 
                            'GroupCompanyName' => 'SBIG', 
                            'IdType' => '1', 
                            'IsInsured' => 'N', 
                            'IsOrgParty' => ($quote_data->vehicle_owner_type == 'I') ? 'N'  : 'Y',
                            'IsPolicyHolder' => 'Y', 
                            'Locality' => '', 
                            'MaritalStatus' => empty($proposal->marital_status) ? '' : (ucwords(strtolower($proposal->marital_status)) == 'Married' ? '1' : '2'), 
                            'Mobile' => $proposal->mobile_number, 
                            'NationalityCode' => 'IND', 
                            'OccupationCode' => $proposal->occupation, 
                            'IdNo' => !empty($proposal?->pan_number) ? $proposal?->pan_number : '', #PAN changes
                            'PartyStatus' => '1', 
                            'PlotNo' => $address_ckyc_data->addressLine1 ?? $getAddress['address_1'] ?? '',
                            'PostCode' => $proposal->pincode, 
                            'PreferredContactMode' => 'Mail', 
                            // 'RegistrationName' => 'lokip', 
                            'SBIGCustomerId' => '', 
                            'STDCode' => '021', 
                            'State' => $proposal->state_id,#$rto_data->STATE_ID,
                            'StreetName' => $address_ckyc_data->addressLine3 ?? $getAddress['address_3'] ??  '',
                            //Regulatory changes #29326
                            "AccountNo" => $additional_details['owner']['accountNumber'] ?? '',
                            "IFSCCode"  => $additional_details['owner']['ifsc'] ?? '',
                            "BankName"  => $additional_details['owner']['bankName'] ?? '',
                            "BankBranch"=> $additional_details['owner']['branchName'] ?? '', 
                        ],
                    ],
                    'PolicyLobList' =>
                    [
                        0 =>
                        [
                            'PolicyRiskList' =>
                            [
                                0 =>
                                [
                                    'VehicleRegistrationType' => isBhSeries($requestData->vehicle_registration_no) ? '2' : '1',
                                    'DailyUseDistance' => '1', 
                                    'NightParkLoc' => '1', 
                                    'NightParkLocPinCode' => $proposal->pincode, 
                                    'DayParkLoc' => '1', 
                                    'DayParkLocPinCode' => '',//$proposal->pincode, 
                                    'IsAAMember' => '0', 
                                    'IsImportedWithoutDuty' => '0', 
                                    'IsHandicappedMod' => '0', 
                                    'VehicleCategory' => '1', 
                                    'TypeOfPermit' => '1', 
                                    'Last3YrDriverClaimPresent' => '0', 
                                    'NoOfDriverClaims' => '', 
                                    'DriverClaimYr' => '', 
                                    'DriverClaimAmt' => '', 
                                    //  'ClaimStatus' => '', 
                                    // // 'ClaimType' => '', 
                                    // 'FNBranchName' => 'FNBranchName',
                                    'RoadType' => '1', 
                                    'IMT34' => '0', 
                                    'SiteAddress' => '', 
                                    'IMT23' => '0', 
                                    'AntiTheftAlarmSystem' => $is_anti_theft, 
                                    'ChassisNo' => removeSpecialCharactersFromString($proposal->chassis_number),
                                    //'EmployeeCount' => 1, 
                                    'EngineNo' => $proposal->engine_number, 
                                    'GeoExtnBangladesh' => $bang,
                                    'GeoExtnBhutan' => $bhutan,
                                    'GeoExtnMaldives' => $maldive,
                                    'GeoExtnNepal' => $nepal,
                                    'GeoExtnPakistan' => $pak,
                                    'GeoExtnSriLanka' => $srilanka,
                                    'IDV_User' => $quote->idv, 
                                    //'IsConfinedOwnPremises' => '', 
                                    'IsDrivingTuitionsUse' => '0', 
                                    'IsFGFT' => '0', 
                                    'IsGeographicalExtension' => $is_geo_code,
                                    'IsNCB' =>  0, //Made changes according to git #30395 $requestData->is_claim == 'Y' ? '1' : '0', 
                                    'IsNewVehicle' => $requestData->business_type == 'newbusiness' ? '1' : '0', 
                                    'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)), 
                                    'NCB' => $requestData->applicable_ncb / 100, 
                                    'NCBLetterDate' => $policy_start_date .'T'. $time, 
                                    'NCBPrePolicy' => $requestData->previous_ncb / 100, 
                                    'NCBProof' => '1', 
                                    'PaidDriverCount' => $no_of_paid_driver,// (($owner_type == 'I') && ($product_type != 'O') && ($cpa_type != 'false')) ? '1' :'0', 
                                    'ProductElementCode' => 'R10005', 
                                    'RTOCityDistric' => $rto_data->rto_dis_code,
                                    'RTOCluster' => $rto_data->rto_cluster,
                                    'RTOLocation' => $rto_data->location_name,
                                    'RTOLocationID' => $rto_data->loc_id,
                                    'RegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                                    //'RegistrationNo' => $proposal->vehicale_registration_number, 
                                    'RegistrationNo' => $requestData->business_type == 'newbusiness' ? '' : strtoupper($registration_number),#str_replace('-', '', $proposal->vehicale_registration_number),
                                    'Variant' => $mmv->variant_id,
                                    'VehicleMake' => $mmv->vehicle_manufacturer_code,
                                    'VehicleModel' => $mmv->vehicle_model_code,
                                    'VoluntaryDeductible' => $is_voluntary_access ? $voluntary_excess_amt : '', 
                                    'PolicyCoverageList' =>
                                    [],
                                ],
                            ],
                            'ProductCode' => 'PM2W001',
                            'PolicyType' => $PolicyType,
                            'BranchInfo' => 'HO',
                        ],
                    ],
                    'PremiumCurrencyCode' => 'INR',
                    'PremiumLocalExchangeRate' => 1,
                    'ProductCode' => 'PM2W001',
                    'ProductVersion' => '1.0',
                    'ProposalDate' => $policy_start_date,
                    'QuoteValidTo' => date('Y-m-d', strtotime('+30 day', strtotime($policy_start_date))),
                    'SBIGBranchStateCode' => $rto_data->state_id,
                    'SiCurrencyCode' => 'INR',
                    'TransactionInitiatedBy' => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
                ],
            ];
            if (config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y') {
                $proposal_array['RequestBody']['SourceType'] = config('constants.IcConstants.sbi.SBI_BIKE_SOURCE_TYPE', '9');
            }
            if($requestData->vehicle_owner_type != 'I'){
                unset($proposal_array['RequestBody']['PolicyCustomerList'][0]['GenderCode']);
            }
            if($proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == '0' || $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == 0){
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate'] = '';
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBProof'] = '';
            }
            if($requestData->business_type == 'newbusiness')
            {
                unset($proposal_array['RequestBody']['KindofPolicy']);
            }
            if ($premium_type != 'third_party') {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][]=[
                'ProductElementCode' => 'C101064',
                               'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                               'PolicyBenefitList' =>
                               [
                                   0 =>
                                   [
                                       'ProductElementCode' => 'B00002',
                                   ],
                               ],
                               'ExpiryDate' => ($PolicyType == '6') ? $ExpiryDate . 'T23:59:59' : $policy_end_date . 'T23:59:59',
               ];
            }
            if ($premium_type != 'own_damage')
            {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                    'PolicyBenefitList' =>
                    [
                        0 =>
                        [
                            // 'SumInsured' => 750000,
                            'ProductElementCode' => 'B00008',
                        ]
                    ],
                    'ExpiryDate' => ($requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
                    'ProductElementCode' => 'C101065',
                ];
                if ($cover_pa_unnamed_passenger|| $cover_pa_paid_driver || $compulsory_personal_accident) {
                    $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                        'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                        'PolicyBenefitList' =>
                        [
                        ],
                        'OwnerHavingValidDrivingLicence' => '1',
                        'OwnerHavingValidPACover' => '1',
                        'ExpiryDate' => (isset($tenure) && ($tenure == '5') && $requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
                        'ProductElementCode' => 'C101066',
                    ];
                }
                $tp_only = search_for_id_sbi('C101065', $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];
        
            }
            $index_id ='';
            if ($cover_pa_unnamed_passenger || $cover_pa_paid_driver|| $compulsory_personal_accident) {
            $index_id = search_for_id_sbi('C101066', $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];
            }
         if($quote_data->vehicle_owner_type == 'I'){
            $title = [
                             'Title' =>  (($proposal->gender == 'M') ? '9000000001' : '9000000003'),
                        ];
                        $proposal_array['RequestBody']['PolicyCustomerList'][0] = array_merge($proposal_array['RequestBody']['PolicyCustomerList'][0], $title);
            }

         if (($quote_data->vehicle_owner_type == 'I') && ($premium_type != 'own_damage') && ($compulsory_personal_accident)) {
            if ($index_id != '') {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                    'SumInsured' => 1500000,
                    'ProductElementCode' => 'B00015',
                    'NomineeName' => $proposal->nominee_name,
                    'NomineeRelToProposer' => $proposal->nominee_relationship,
                    'NomineeDOB' => date('Y-m-d', strtotime($proposal->nominee_dob)),
                    'NomineeAge' => $proposal->nominee_age,
                    'AppointeeName' => '',
                    'AppointeeRelToNominee' => '2',
                    'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                    'ExpiryDate' => (isset($tenure) && ($tenure == '5') && $requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
                ];
            } else {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                    'PolicyBenefitList' =>
                    [
                        0 =>
                        [
                            'SumInsured' => 1500000,
                            'ProductElementCode' => 'B00015',
                            'NomineeName' => $proposal->nominee_name,
                            'NomineeRelToProposer' => $proposal->nominee_relationship,
                            'NomineeDOB' => date('Y-m-d', strtotime($proposal->nominee_dob)),
                            'NomineeAge' => $proposal->nominee_age,
                            'AppointeeName' => '', //$owner_driver_appointee_name,
                            'AppointeeRelToNominee' => '2', //($owner_driver_appointee_relationship != '') ? $owner_driver_appointee_relationship : '2',
                            'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                            'ExpiryDate' => (isset($tenure) && ($tenure == '5') && $requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
                        ],
                    ],
                    'OwnerHavingValidDrivingLicence' => '1',
                    'OwnerHavingValidPACover' => '1',
                    'ExpiryDate' => $policy_end_date . 'T23:59:59',
                    'ProductElementCode' => 'C101066',
                ];
            }
         }
            $add_cover = $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];
            foreach ($add_cover as $key => $add) {
                if ($add['ProductElementCode'] == 'C101064') {
                    if ($non_electrical_accessories) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'SumInsured' => $non_electrical_accessories_amount,
                            'Description' => 'Description',
                            'ProductElementCode' => 'B00003'
                        ];
                    }
                    if ($electrical_accessories) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'SumInsured' => $electrical_accessories_amount,
                            'Description' => 'Description',
                            'ProductElementCode' => 'B00004'
                        ];
                    }
                    if ($external_fuel_kit) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                            'SumInsured' => $external_fuel_kit_amount,
                            'Description' => 'Description',
                            'ProductElementCode' => 'B00005'
                        ];
                    }
                }
                if ($add['ProductElementCode'] == 'C101065') {
                    if ($external_fuel_kit) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                            'ProductElementCode' => 'B00010',
                        ];
                    }
                    if ($cover_ll_paid_driver) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                            'ProductElementCode' => 'B00013',
                        ];
                    }

                    //IMT29- Legal Liability to Employees Benefit is mandatory for Organization Customer.
                    // if ($requestData->vehicle_owner_type == 'C') {
                    //     $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                    //         'ProductElementCode' => 'B00012',
                    //     ];
                    // }
                    if ($is_tppd) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                            'SumInsured' => 6000,
                            'ProductElementCode' => 'B00009',
                        ];
                    }
                }
                if ($add['ProductElementCode'] == 'C101066'){
                    if ($cover_pa_unnamed_passenger) {
                        $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                            'SumInsuredPerUnit' => $cover_pa_unnamed_passenger_amt,
                            'TotalSumInsured' => $cover_pa_unnamed_passenger_amt * ($mmv->carrying_capacity),
                            'ProductElementCode' => 'B00075',
                        ];
                    }
                }
            }
            
            if ($zero_depreciation_cover_selected) {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'SumInsured' => 1000,
                    'ProductElementCode' => 'C101072',
                    'DRClaimLimit'      => '1',
                    'TypeofGarragePrf'  => '1'
                ];
            }
            if($road_side_assistance_selected){
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101069',
                ];
            }
            if ($rti &&  $carage <= 3) {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101067',
                    // 'SumInsured' => 99999999999,
                ];
            }
            if ($engine &&  $carage <= 5) {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101108',
                ];
            }
            if ($is_consumable_protection &&  $carage <= 5) {
                $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101111',
                ];
            }
        if ($proposal->is_vehicle_finance == '1') {
            $financetype = [
                'FNName' => $proposal->name_of_financer,
                'FNType' => $proposal->financer_agreement_type,
                'FNBranchName' => $proposal->financer_location,
            ];
            $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0] = array_merge($proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0], $financetype);
        }
        if ($proposal->vehicle_color != '') {
            $color =[
                'black'=>'1',
                'blue'=>'2',
                'yellow'=>'3',
                'ivory'=>'4',
                'red'=>'5',
                'white'=>'6',
                'green'=>'7',
                'purple'=>'8',
                'violet'=>'9',
                'maroon'=>'10',
                'silver'=>'11',
                'gold'=>'12',
                'beige'=>'13',
                'orange'=>'14',
                'still grey'=>'15',
        ];
            if(isset($color[strtolower($proposal->vehicle_color)]) && !empty($color[strtolower($proposal->vehicle_color)]))
            {
                $color_code=$color[strtolower($proposal->vehicle_color)];
            }else{
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'This Vehicle Color Is Not Available',
                ];
            }
            $vehiclecolor = [
                'VehicleColor' => $color_code
            ];
            $proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0] = array_merge($proposal_array['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0], $vehiclecolor);
        }
        if ($requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure') {
            $previouspolicy_details = [
                'PreInsuranceComAddr' => $proposal->address_line1 . ',' . $proposal->address_line1,
                'PreInsuranceComName' => $proposal->previous_insurance_company,
                'PrePolicyEndDate' => $prepolicyenddate . 'T23:59:59',
                'PrePolicyNo' => $proposal->previous_policy_number,
                'PrePolicyStartDate' => $prepolstartdate .'T'.date('H:i:s'),
            ];
            $proposal_array['RequestBody'] = array_merge($proposal_array['RequestBody'], $previouspolicy_details);
        }
            if ($premium_type == 'own_damage') {
                $proposal_array1 = [
                    'ActiveLiabilityPolicyEffDate' => date('Y-m-d', strtotime($proposal->tp_start_date)) .'T'.date('H:i:s'),
                    'ActiveLiabilityPolicyExpDate' => date('Y-m-d', strtotime($proposal->tp_end_date)) .'T'.date('H:i:s'),
                    'ActiveLiabilityPolicyNo' => $proposal->tp_insurance_number,
                    'ActiveLiabilityPolicyInsurer' => $proposal->tp_insurance_company,
                ];

                $proposal_array['RequestBody'] = array_merge($proposal_array['RequestBody'], $proposal_array1);
            }
            if($is_pos)
            {
                $proposal_array['RequestBody']['AgentType']='POSP';
                $proposal_array['RequestBody']['AgentFirstName']=$posp_name;
                $proposal_array['RequestBody']['AgentMiddleName']='';
                $proposal_array['RequestBody']['AgentLastName']='';
                $proposal_array['RequestBody']['AgentPAN']=$posp_pan_number;
                $proposal_array['RequestBody']['AgentMobile']=$posp_unique_number;
                $proposal_array['RequestBody']['AgentBranchID']='';
                $proposal_array['RequestBody']['AgentBranch']='';
            }
            
            //CKYC CHNAGES START
            if (isset($additional_details['owner']['isCkycDetailsRejected'])) {
                if ($additional_details['owner']['isCkycDetailsRejected'] == 'Y') {
                    UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update([
                        'is_ckyc_verified' => 'N',
                    ]);
                }
                $proposal->refresh();
            }
            $is_ckyc_verified = $proposal->is_ckyc_verified;
            $is_ckyc_details_rejected = $proposal->is_ckyc_details_rejected == 'Y' ? true : false;

            /* 1. PAN
            2. Passport
            3. Ration ID
            4. Voter ID
            5. GOV UID
            6. Driving License
            7. Aadhar  */
            $DOCTypeId = NULL;
            $DOCTypeName = NULL;
            switch($proposal->ckyc_type)
            {
                case 'pan_card' : 
                    $DOCTypeId = 1;
                    $DOCTypeName = $proposal->ckyc_type_value;
                break;

                case 'passport' : 
                    $DOCTypeId = 2;
                    $DOCTypeName = 'Passport';
                break;

                case 'ration_id' : 
                    $DOCTypeId = 3;
                    $DOCTypeName = 'Ration ID';
                break;

                case 'voter_id' : 
                    $DOCTypeId = 4;
                    $DOCTypeName = 'Voter ID';
                break;

                case 'driving_license' : 
                    $DOCTypeId = 6;
                    $DOCTypeName = 'Driving License';
                break;

                case 'aadhar_card' : 
                    $DOCTypeId = 7;
                    $DOCTypeName = $proposal->ckyc_type_value;
                break;

                default:
                break;
            }

            if ($is_ckyc_verified != 'Y')
            {
             //Checking Document Upload Properly
            $CKYCUniqueId = self::getUniqueId($proposal); 
            $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            $get_doc_data = json_decode($document_upload_data->cky_doc_data ?? '', true);

            if (empty($get_doc_data) || empty($document_upload_data)) {
                return response()->json([
                    'data' => [
                        'message' => 'No documents found for CKYC Verification. Please upload any and try again.',
                        'verification_status' => false,
                    ],
                    'status' => false,
                    'message' => 'No documents found for CKYC Verification. Please upload any and try again.'
                ]);
            }
            
            if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y' && $proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                $ckyc_doc_validation = ckycVerifications($proposal);
                if ($ckyc_doc_validation['status'] != 'false' && $ckyc_doc_validation['message'] != 'File Upload successfully at both place') {
                    return [
                        'status' => false,
                        'message' => 'File Upload Unsuccessfully' . $ckyc_doc_validation['message']
                    ];
                }
            }
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCVerified'] = "N";
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['KYCCKYCNo']    = "";
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['DOCTypeId']    = "";
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['DOCTypeName']  = "";
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'] = $CKYCUniqueId ?? '';//$proposal->ckyc_reference_id;
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCSourceType'] = strtoupper(config('constants.motorConstant.SMS_FOLDER'));    

            }
            else
            {
                $CKYCUniqueId = self::getUniqueId($proposal); 
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCVerified'] = $is_ckyc_verified;
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['KYCCKYCNo']    = $is_ckyc_verified == 'Y' ? $proposal->ckyc_number: NULL;
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['DOCTypeId']    = $is_ckyc_verified == 'Y' ? $DOCTypeId : NULL;
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['DOCTypeName']  = $is_ckyc_verified == 'Y' ? $DOCTypeName : NULL;
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'] = $CKYCUniqueId; //$proposal->ckyc_reference_id; //CKYCUniqueID to be passed according to git #30366
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCSourceType'] = strtoupper(config('constants.motorConstant.SMS_FOLDER'));     
            }
        //OVD CKYC Verified Changes when discarding data
            if (isset($additional_details['owner']['isCkycDetailsRejected']) && $additional_details['owner']['isCkycDetailsRejected'] == 'Y') {
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['CKYCVerified'] = 'N';
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['KYCCKYCNo']    = '';
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['DOCTypeId']    = '';
                $proposal_array['RequestBody']['PolicyCustomerList'][0]['DOCTypeName']  = ''; //Pass empty when data is discarded after ckyc verification success
            }
            //END CKYC CHANGES
            $get_response = getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
                'enquiryId' => $enquiryId,
                'requestMethod' =>'get',
                'productName'  => $productData->product_name,
                'company'  => 'sbi',
                'section' => $productData->product_sub_type_code,
                'method' =>'Get Token',
                'transaction_type' => 'proposal'
            ]);
            $data = $get_response['response'];
                $token_data = json_decode($data, TRUE);
                $get_response = getWsData(
                    config('constants.IcConstants.sbi.SBI_END_POINT_URL_BIKE_FULLQUOTE'), $proposal_array, 'sbi', [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'authorization' => $token_data['access_token'],
                        'productName'  => $productData->product_name,
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Proposal Submit',
                        'transaction_type' => 'proposal'
                    ]
                );
                $data = $get_response['response'];
                if(empty($data))
                {
                    return [
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => 'Insurer not rechable'
                    ];
                }
        
           $arr_premium = json_decode($data, true);
            if (isset($arr_premium['UnderwritingResult']['MessageList']) || isset($arr_premium['message']) || isset($arr_premium['ValidateResult']['message']) || isset($arr_premium['httpMessage'])) {

                if (isset($arr_premium['messages']) && isset($arr_premium['messages'][0]['message'])) {
                    $error_message = json_encode($arr_premium['messages'][0]['message'], true);
                }

                else if(isset($arr_premium['ValidateResult']['message'])){
                    $error_message = $arr_premium['ValidateResult']['message'];
                }else if(isset($arr_premium['httpMessage'])) {
                    $error_message = $arr_premium['httpMessage'];
                } else {
                    $error_message = json_encode($arr_premium['UnderwritingResult']['MessageList'], true);
                }

                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => $error_message
                ];
            }else
            {
                $proposal_date = date('Y-m-d H:i:s');
                $Own_Damage_Basic = 0;
                $Non_Electrical_Accessories = 0;
                $Electrical_Accessories = 0;
                $LPG_CNG_Cover = 0;
                $Inbuilt_LPG_CNG_Cover = 0;
                $Trailer_OD = 0;
                $Road_Side_Assistance = 0;
                $Add_Road_Side_Assistance = 0;
                $Third_Party_Bodily_Injury = 0;
                $Third_Party_Property_Damage = 0;
                $CNG_LPG_Liability = 0;
                $Trailer_TP = 0;
                $Legal_Liability_Employees = 0;
                $Legal_Liability_Paid_Drivers = 0;
                $Legal_Liability_Defence = 0;
                $PA_Owner_Driver = 0;
                $PA_Unnamed_Passenger = 0;
                $PA_Paid_Driver = 0;
                $EN_PA_Owner_Driver = 0;
                $EN_PA_Unnamed_Passenger = 0;
                $EN_PA_Paid_Driver = 0;
                $HCC_Owner_Driver = 0;
                $HCC_Unnamed_Passenger = 0;
                $HCC_Paid_Driver = 0;
                $Depreciation_Reimbursement = 0;
                $Return_to_Invoice = 0;
                $Protection_NCB = 0;
                $Key_Replacement_Cover = 0;
                $Inconvience_Allowance = 0;
                $Loss_Personal_Belongings = 0;
                $Engine_Guard = 0;
                $EMI_Protector = 0;
                $Tyre_Guard = 0;
                $Consumables = 0;
                $voluntary_excess = 0;
                $tppd_discount = ($is_tppd)? (($requestData->business_type == 'newbusiness') ? 250 : 50) :0;
                $anti_theft =0;
                $OD_BasePremium=0;
                $array_cover = $arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];
                foreach ($array_cover as $key => $cover) {
                    if ($cover['ProductElementCode'] == 'C101064') {
                        foreach ($cover['PolicyBenefitList'] as $key => $ODcover) {
                            if ($ODcover['ProductElementCode'] == 'B00002') {
                                $Own_Damage_Basic = $ODcover['GrossPremium'];
                            } elseif ($ODcover['ProductElementCode'] == 'B00003') {
                                $Non_Electrical_Accessories = $ODcover['GrossPremium'];
                            } elseif ($ODcover['ProductElementCode'] == 'B00004') {
                                $Electrical_Accessories = $ODcover['GrossPremium'];
                            } elseif ($ODcover['ProductElementCode'] == 'B00005') {
                                $LPG_CNG_Cover = $ODcover['GrossPremium'];
                            }
                        }
                    } elseif ($cover['ProductElementCode'] == 'C101069') {
                        $road_side_assistance = $cover['GrossPremium'];
                     }elseif ($cover['ProductElementCode'] == 'C101065') {
                        foreach ($cover['PolicyBenefitList'] as $key => $LLTPcover) {
                            if ($LLTPcover['ProductElementCode'] == 'B00008') {
                                $Third_Party_Bodily_Injury = $LLTPcover['GrossPremium'];
                            } elseif ($LLTPcover['ProductElementCode'] == 'B00009') {
                                $Third_Party_Property_Damage = $LLTPcover['GrossPremium'];
                            } elseif ($LLTPcover['ProductElementCode'] == 'B00010') {
                                $CNG_LPG_Liability = $LLTPcover['GrossPremium'];
                            } elseif ($LLTPcover['ProductElementCode'] == 'B00013') {
                                $Legal_Liability_Paid_Drivers = $LLTPcover['GrossPremium'];
                            } elseif ($LLTPcover['ProductElementCode'] == 'B00012') {
                                $Legal_Liability_Employees = $LLTPcover['GrossPremium'];
                            }
                        }
                    } elseif ($cover['ProductElementCode'] == 'C101066') {
                        foreach ($cover['PolicyBenefitList'] as $key => $PAcover) {
                            if ($PAcover['ProductElementCode'] == 'B00015') {
                                $PA_Owner_Driver = $PAcover['GrossPremium'];
                            } elseif ($PAcover['ProductElementCode'] == 'B00075') {
                                $PA_Unnamed_Passenger = $PAcover['GrossPremium'];
                            } elseif ($PAcover['ProductElementCode'] == 'B00027') {
                                $PA_Paid_Driver = $PAcover['GrossPremium'];
                            }
                        }
                    } elseif ($cover['ProductElementCode'] == 'C101072') {
                        $Depreciation_Reimbursement = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101067') {
                        $Return_to_Invoice = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101068') {
                        $Protection_NCB = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101073') {
                        $Key_Replacement_Cover = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101075') {
                        $Loss_Personal_Belongings = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101108') {
                        $Engine_Guard = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101110') {
                        $Tyre_Guard = $cover['GrossPremium'];
                    } elseif ($cover['ProductElementCode'] == 'C101111') {
                        $Consumables = $cover['GrossPremium'];
                    }
                }
                 $vehicleDetails = [
                    'manf_name' => $mmv->vehicle_manufacturer,
                    'model_name' => $mmv->vehicle_model_name,
                    'version_name' => $mmv->variant,
                    'seating_capacity' => $mmv->seating_capacity,
                    'carrying_capacity' => $mmv->seating_capacity - 1,
                    'cubic_capacity' => $mmv->cubic_capacity,
                    'fuel_type' =>  $mmv->fuel,
                    'vehicle_type' => 'CAR',
                    'version_id' => $mmv->ic_version_code ,
                ];
                if($is_anti_theft =='1' && $premium_type!='third_party')
                {
                    $anti_theft =isset($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['AntiTheftDiscAmt']) ?($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['AntiTheftDiscAmt']): '0';
                }
                if ($is_voluntary_access) {  //NA for bike
                    $voluntary_excess = (isset($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['VolDeductDiscAmt'])?$arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['VolDeductDiscAmt'] :'0');
                }
                $addon_dis = $Depreciation_Reimbursement + $Road_Side_Assistance + $Return_to_Invoice + $Protection_NCB + $Key_Replacement_Cover + $Loss_Personal_Belongings + $Engine_Guard + $Consumables +$Tyre_Guard;

                $od_premium = ($Own_Damage_Basic + $Electrical_Accessories + $Non_Electrical_Accessories + $LPG_CNG_Cover);

                $tp_premium = ($premium_type != 'own_damage') ? $Third_Party_Bodily_Injury + $Legal_Liability_Paid_Drivers + $Legal_Liability_Employees + $PA_Unnamed_Passenger + $CNG_LPG_Liability + $PA_Paid_Driver + $PA_Owner_Driver: '0';

                $total_discount = (($premium_type != 'third_party') ? ($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt']) :'0') + $voluntary_excess  + $anti_theft;#+ $tppd_discount

                $net_premium = ($od_premium + $tp_premium - $total_discount + $addon_dis );
                //$final_gst_amount   = ($net_premium * 0.18);
                $final_gst_amount   = (float)($arr_premium['PolicyObject']['TGST']);
                $final_payable_amount  = ($net_premium + $final_gst_amount);
                $cpa_end_date = '';
                if($compulsory_personal_accident == true && $requestData->business_type == 'newbusiness') 
                {
                    $cpa_end_date=date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date)));
                }
                else if($compulsory_personal_accident == true && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ) {
                    $cpa_end_date =date('d-m-Y',strtotime($policy_end_date));
                }
                // UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                // ->update([
                //     'od_premium' => $od_premium - $total_discount,
                //     'tp_premium' => $tp_premium - $tppd_discount,
                //     'ncb_discount' => $premium_type != 'third_party' ? ($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt']) :'0',
                //     'proposal_no' => $quotationNo,
                //     'unique_proposal_id' => $quotationNo,
                //     'addon_premium' => $addon_dis,
                //     'total_premium' => $net_premium,
                //     'total_discount' => $total_discount + $tppd_discount,
                //     'service_tax_amount' => $final_gst_amount,
                //     'final_payable_amount' => ($arr_premium['PolicyObject']['DuePremium']),
                //     'cpa_premium' => $PA_Owner_Driver,
                //     'policy_start_date' =>  date('d-m-Y',strtotime($policy_start_date)),
                //     'policy_end_date' =>  date('d-m-Y',strtotime($policy_end_date)),
                //     'ic_vehicle_details' => $vehicleDetails,
                //     'is_breakin_case' => 'N',
                //     'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                //     'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
                //     'cpa_start_date' => (($compulsory_personal_accident == true ) ? date('d-m-Y',strtotime($policy_start_date)) :''),
                //     'cpa_end_date'   => $cpa_end_date,
                //     'is_cpa' => ($compulsory_personal_accident == true) ?'Y' : 'N',
                // ]);


                $NewBusinessTpEndDate = date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date)));

                $policyEndDate = ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') 
                    ? $NewBusinessTpEndDate 
                    : date('d-m-Y', strtotime($policy_end_date));

                $updateData = [
                    'od_premium' => $od_premium - $total_discount,
                    'tp_premium' => $tp_premium - $tppd_discount,
                    'ncb_discount' => $premium_type != 'third_party' ? ($arr_premium['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt']) :'0',
                    'proposal_no' => $quotationNo,
                    'unique_proposal_id' => $quotationNo,
                    'addon_premium' => $addon_dis,
                    'total_premium' => $net_premium,
                    'total_discount' => $total_discount + $tppd_discount,
                    'service_tax_amount' => $final_gst_amount,
                    'final_payable_amount' => ($arr_premium['PolicyObject']['DuePremium']),
                    'cpa_premium' => $PA_Owner_Driver,
                    'policy_start_date' =>  date('d-m-Y',strtotime($policy_start_date)),
                    'policy_end_date' =>   $policyEndDate,
                    'ic_vehicle_details' => $vehicleDetails,
                    'is_breakin_case' => 'N',
                    'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime($policy_start_date)),
                    'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))) : date('d-m-Y',strtotime($policy_end_date))),
                    'cpa_start_date' => (($compulsory_personal_accident == true ) ? date('d-m-Y',strtotime($policy_start_date)) :''),
                    'cpa_end_date'   => $cpa_end_date,
                    'is_cpa' => ($compulsory_personal_accident == true) ?'Y' : 'N',
            ];
            if ($premium_type == 'own_damage') {
                unset($updateData['tp_start_date']);
                unset($updateData['tp_end_date']);
            }
            $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($updateData);

                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                    'ic_id' => $productData->company_id,
                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                    'proposal_id' => $proposal->user_proposal_id,
                ]);

                SbiPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IcConstants.sbi.IS_DOCUMENT_UPLOAD_ENABLED_FOR_SBI_CKYC') == 'Y') {
                    $kyc_status = $verification_status = $status = ($proposal->is_ckyc_verified == 'Y');
                    $ckyc_result = [];
                    $msg = $kyc_message = 'Proposal Submitted Successfully!';

                    // $ckyc_result = ckycVerifications($proposal);

                    // if ( ! isset($ckyc_result['status']) || ! $ckyc_result['status']) {
                    //     $kyc_status = $verification_status  = $status = false;
                    //     $msg = $kyc_message = $proposal->proposer_ckyc_details?->is_document_upload == 'Y' ? ($ckyc_result['message'] ?? 'An unexpected error occurred') : ( ! empty($ckyc_result['mode']) && $ckyc_result['mode'] == 'ckyc_number' ? 'CKYC verification failed using CKYC number. Please check the entered CKYC Number or try with another method' : 'CKYC verification failed. Try other method');
                    // }

                    return response()->json([
                        'status' => true,
                        'msg' => $msg,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'verification_status' => $verification_status,
                        'data' => [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal->user_product_journey_id,
                            'finalPayableAmount' => $final_payable_amount,
                            'is_breakin' => 'N',
                            'inspection_number' => '',
                            'kyc_status' => $kyc_status,
                            'kyc_message' => $kyc_message,
                        ]
                    ]);
                }

                return response()->json([
                    'status' => true,
                    'msg' => "Proposal Submitted Successfully!",
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'proposalNo' => $quotationNo,
                        'userProductJourneyId' => $proposal->user_product_journey_id,
                        'finalPayableAmount' => $final_payable_amount,
                        'is_breakin' => 'N',
                        'inspection_number' => ''
                    ]
                ]);
            }

    }
    public static function getUniqueId($proposal)
    {
        $partner_name = (strtoupper(config('constants.motorConstant.SMS_FOLDER')));
        $todayDate = str_replace("-", "", (Carbon::now()->format('d-m-Y')));
        $hms = str_replace(":", "", (Carbon::now()->format('h:m:s')));
        $UniqueId = $partner_name . $todayDate . $hms;
        $additional_details = json_decode($proposal->additional_details, true);
        $additional_details['CKYCUniqueId'] = $UniqueId;
        $proposal->additional_details = json_encode($additional_details, true);
        $proposal->save();   
        return $UniqueId;
    }
}