<?php

namespace App\Http\Controllers\Proposal\Services\Bike;
namespace App\Http\Controllers\Proposal\Services\Bike\V2;
use App\Models\CvBreakinStatus;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\TataAigPremiumDetailController;
use Facade\Ignition\DumpRecorder\Dump;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/TataAigCkycHelper.php';

class tataAigSubmitProposals
{

    public static function submit($proposal, $request)
    {
        ######PLAN NAME########
        $enquiryId      = customDecrypt($request["userProductJourneyId"]);
        $requestData    = getQuotation($enquiryId);
        $productData    = getProductDataByIc($request["policyId"]);

       
        $masterProduct  = MasterProduct::where("master_policy_id", $productData->policy_id)
            ->first();
         
        $premium_type   = DB::table("master_premium_type")
            ->where("id", $productData->premium_type_id)
            ->pluck("premium_type_code")
            ->first();
        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
            ->first();

        $additionalDetailsData = json_decode($proposal->additional_details);     

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
        $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
        $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin = ($requestData->business_type == "breakin");
        $any_short_term = ($premium_type == 'short_term_3' || $premium_type == 'short_term_6' || $premium_type == 'short_term_6_breakin' || $premium_type == 'short_term_3_breakin') ? true : false;

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
        $zero_dep_age_available = true;
        $noPrevPolicy = ($requestData->previous_policy_type == 'Not sure');

        $idv = $quote_log_data->idv;
        
        $check_mmv = self::checkTataAigMMV($productData, $requestData->version_id);
     
        if(!$check_mmv['status'])
        {
            return $check_mmv;
        }

        // $mmv = (object)$check_mmv['data'];
        $mmv= (object) array_change_key_case((array) $check_mmv['data'], CASE_LOWER);
    
        $customer_type = $is_individual ? "Individual" : "Organization";

        $prevPolyStartDate = '';
        if (in_array($requestData->previous_policy_type, ['Comprehensive', 'Third-party', 'Own-damage'])) {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        }
    
        if ($requestData->prev_short_term == "1") {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subMonth(3)->addDay(1)->format('Y-m-d');
        } 

        //Special registration number
       $special_regno = "false";
       if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
           $pattern = '/^[A-Z]{2}-[0-9]{2}--[0-9]+$/';
           if (preg_match($pattern, $requestData->vehicle_registration_no)) {
               $special_regno = "true";
           } else {
               $special_regno = "false";
           }
       }
        if($is_new){
            $policyStartDate  = strtotime($requestData->vehicle_register_date);//date('Y-m-d');

            if($is_liability){
                $policyStartDate  = strtotime($requestData->vehicle_register_date);
            }

            $policy_start_date = date('Y-m-d', $policyStartDate);

            // $policy_end_date = date('Y-m-d', strtotime($policy_start_date . '-1 days + 5 year'));
            if ($premium_type == 'comprehensive') {
                $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' + 1 year - 1 days'));
            } elseif ($premium_type == 'third_party') {
                $policy_end_date = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 5 year'));
            }
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = date('d-m-Y', strtotime($policy_start_date . ' - 1 days + 5 year'));
        }
        else
        {
            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == "New" ? date("Y-m-d") : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = $interval->y * 12 + $interval->m + 1;
            $vehicle_age = $interval->y;

            $motor_manf_date = "01-" . $requestData->manufacture_year;

            $current_date = date("Y-m-d");

            if($is_breakin)
            {
                $policy_start_date = date("Y-m-d", strtotime(date('Y-m-d'). "+1 days"));
            }
            else
            {
                $policy_start_date = date("Y-m-d", strtotime($requestData->previous_policy_expiry_date . " + 1 days"));
            }

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date))
            {
                $policy_start_date = date("Y-m-d", strtotime(date('Y-m-d'). "+1 days"));
            }

            $policy_end_date = date("Y-m-d", strtotime($policy_start_date . " - 1 days + 1 year"));
            $tp_start_date      =  $policy_start_date;
            $tp_end_date        = $policy_end_date;
        }


        $vehicle_register_no = explode("-", $proposal->vehicale_registration_number);

        $previousInsurerList = PreviousInsurerList::where([
            "company_alias" => 'tata_aig',
            "name" => $proposal->insurance_company_name,
        ])->first();

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();

        
        $DepreciationReimbursement = $RoadsideAssistance = $ac_opted_in_pp = "false";

    $ConsumablesExpenses = 'N';
    $ReturnToInvoice = 'N';

    if($is_zero_dep)
    {
        $DepreciationReimbursement = 'true';
        $NoOfClaimsDepreciation = '2';
    }
    else
    {
        $DepreciationReimbursement = 'false';
        $NoOfClaimsDepreciation = '';
    }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAPaidDriverConductorCleaner = $PAforaddionaldPassenger = $llpaidDriver = $NonElectricalaccess = "No";

        $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = '';

        $is_anti_theft = false;
        $is_automobile_assoc = false;
        $is_anti_theft_device_certified_by_arai = "false";
        $is_tppd = false;
        $tppd_amt = 0;
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = '';

        $is_electrical = false;
        $is_non_electrical = false;
        $is_lpg_cng = false;


        $NonElectricalaccess = "false";
        $NonElectricalaccessSI = "";
        $Electricalaccess = "false";
        $ElectricalaccessSI ="";
        $is_tppd = 'false';
        $is_anti_theft = "false" ;
        $tppd_amt = 0;
        $voluntary_excess_amt = '';
        $PAforaddionaldPaidDriverSI = "";
    
    $is_ll_paid = "false";
    $is_pa_paid = "false";
    $is_pa_unnamed = false;
    $LLNumberDriver = 0;
    $LLNumberConductor = 0;
    $LLNumberCleaner = 0;
    $countries = [];

        foreach ($accessories as $key => $value)
        {
            if (in_array('Electrical Accessories', $value))
            {
                $is_electrical = true;
                $Electricalaccess = "true";
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value))
            {
                $is_non_electrical = true;
                $NonElectricalaccess = "true";
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value))
            {
                $is_lpg_cng = true;
                $externalCNGKIT = "Yes";
                $externalCNGKITSI = $value['sumInsured'];
                if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                    $mmv->txt_fuel = 'External CNG';
                    $mmv->txt_fuelcode = '5';
                }
            }

            if (in_array('PA To PaidDriver Conductor Cleaner', $value))
            {
                $PAPaidDriverConductorCleaner = "Y";
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
        }




        $is_ll_paid = "false";
        $is_pa_paid = "false";
        $is_pa_unnamed = false;
        $LLNumberDriver = 0;
        $LLNumberConductor = 0;
        $LLNumberCleaner = 0;
        $is_Geographical = "false";
        foreach ($additional_covers as $key => $value) {
            if ($value['name'] == 'PA paid driver/conductor/cleaner') {
                $PAforaddionaldPaidDriver = "true";
                $PAforaddionaldPaidDriverSI = $value['sumInsured'];
                $is_pa_paid = "true";
            }
            if ($value['name'] == 'LL paid driver/conductor/cleaner') {
                $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
                $is_ll_paid = "true";
                $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
                $is_ll_paid = "true";
                $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
                $is_ll_paid = "true";
    
                $llpaidDriver = (in_array('DriverLL', $value['selectedLLpaidItmes']) || in_array('CleanerLL', $value['selectedLLpaidItmes']) || in_array('ConductorLL', $value['selectedLLpaidItmes'])) ? 'Yes' : 'No';
            }
            if($value['name'] == 'LL paid driver'){
                $is_ll_paid = "true";
                $llpaidDriver = 'true';
                $LLNumberDriver = 1;
            }
            if ($value['name'] == 'LL paid driver') {
                $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
                $is_ll_paid = "true";
                $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
                $is_ll_paid = "true";
                $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
                $is_ll_paid = "true";
          
                $selectedLLpaidItmes = $value['selectedLLpaidItmes'] ?? [] ;
            
                $llpaidDriver = (in_array('DriverLL', $selectedLLpaidItmes ) || in_array('CleanerLL', $selectedLLpaidItmes) || in_array('ConductorLL', $selectedLLpaidItmes)) ? 'Yes' : 'No';
            }
            if (in_array('PA cover for additional paid driver', $value)) {
                $PAforaddionaldPaidDriver = "true";
                $PAforaddionaldPaidDriverSI = $value['sumInsured'];
            }
    
            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = "Y";
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
    
            if (in_array('LL paid driver', $value)) {
                $llpaidDriver = "true";
                $llpaidDriverSI = $value['sumInsured'];
            }

            if ($value['name'] == 'Geographical Extension') {
                $countries = $value['countries'] ;
                $is_Geographical = "true";
            }

        }
    

        foreach ($discounts as $key => $discount)
        {
            if ($discount['name'] == 'anti-theft device' && !$is_liability)
            {
                $is_anti_theft = 'true';
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured']))
            {
                $is_voluntary_access = "Yes";
                $voluntary_excess_amt = $discount['sumInsured'];
            }

            if ($discount['name'] == 'TPPD Cover' && !$is_od)
            {
                $is_tppd = 'true';
                $tppd_amt = '9999';
            }
        }

        
        if(config('IC.TATA_AIG.V2.BIKE.NO_VOLUNTARY_DISCOUNT') == 'Y')
        {
            $is_voluntary_access = false;
            $voluntary_excess_amt = '';
        }

        // cpa vehicle

        $proposal_additional_details = json_decode($proposal->additional_details, true);

        // cpa vehicle
        $driver_declaration = "None";
        $pa_owner_tenure = '';
        if (isset($selected_addons->compulsory_personal_accident[0]["name"]))
        {
            $cpa_cover = true;
            $driver_declaration = "None";
            
            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure'])? $selected_addons->compulsory_personal_accident[0]['tenure'] : $tenure;
            if($tenure === 5 || $tenure === '5')
            {
                $pa_owner_tenure = '5';
            }
            else
            {
                $pa_owner_tenure = '1';
            }
        }
        else
        {
            $cpa_cover = false;
            if ($customer_type == "Individual")
            {
                if (isset($proposal_additional_details["prepolicy"]["reason"]) && $proposal_additional_details["prepolicy"]["reason"] == "I have another motor policy with PA owner driver cover in my name")
                {
                    
                    $driver_declaration = "Other motor policy with CPA";
                }
                elseif (isset($proposal_additional_details["prepolicy"]["reason"]) && in_array($proposal_additional_details["prepolicy"]["reason"], ["I have another PA policy with cover amount greater than INR 15 Lacs", 'I have another PA policy with cover amount of INR 15 Lacs or more']))
                {
                    $driver_declaration = "Have standalone CPA >= 15 L";
                }
                elseif (isset($proposal_additional_details["prepolicy"]["reason"]) &&$proposal_additional_details["prepolicy"]["reason"] == "I do not have a valid driving license.")
                {
                    $driver_declaration = "No valid driving license";
                }
                else
                {
                    $driver_declaration = "None";
                }
            }
        }
        // END ADDONS
        $applicableAddon['TyreSecure'] = "false";
        $applicableAddon['EngineSecure'] = "false";
        $applicableAddon['NoOfClaimsDepreciation'] = "false";
        $applicableAddon['ReturnToInvoice'] = "false";
        $applicableAddon['KeyReplacement'] = "false";
        $applicableAddon['LossOfPersonalBelongings'] = "false";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "false";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "false";
        $applicableAddon['ConsumablesExpenses'] = "false";
        $applicableAddon['RoadsideAssistance'] = "false";
        $applicableAddon['NCBProtectionCover'] = "false";
        $applicableAddon['DepreciationReimbursement'] = "false";
        foreach ($addons as $key => $value) {
            if (!$is_liability && in_array('Road Side Assistance', $value)) {
                $applicableAddon['RoadsideAssistance'] = "true";
            }

            if (!$is_liability && in_array('NCB Protection', $value)) {
                $applicableAddon['NCBProtectionCover'] = "true";
            } 
            if (!$is_liability && in_array('Consumable', $value)) {
                $applicableAddon['ConsumablesExpenses'] = "true";
            }

            if (!$is_liability && in_array('Return To Invoice', $value)) {
                $applicableAddon['ReturnToInvoice'] = "true";
            }

            if ($value['name'] == 'Zero Depreciation' && ($is_new || $interval->y < 5) && $productData->zero_dep == '0') {
                $applicableAddon['DepreciationReimbursement'] = "true";
            }
        }

        if ($is_new || $requestData->applicable_ncb < 25) { //NCB protection cover is not allowed for NCB less than or equal to 20%
            $applicableAddon['NCBProtectionCover'] = "true";
        }

        $DepreciationReimbursement = $ac_opted_in_pp = "false";
        if($is_zero_dep)
        {
            $DepreciationReimbursement  = 'true';
            $NoOfClaimsDepreciation     = "2";
        }
        else
        {
            $DepreciationReimbursement  = 'false';
            $NoOfClaimsDepreciation     = "";
        }

        if($is_liability){
            $DepreciationReimbursement = 'false';
            $NoOfClaimsDepreciation = "" ;
        }
        // END ADDONS

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_aadhar = '';
        $pos_pan    = '';
        $sol_id     = "";//config('constants.IcConstants.tata_aig.SOAL_ID');
        $is_posp = 'N';
        $q_office_location = 0;

        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log_data->idv <= 5000000) {
            if(!empty($pos_data->pan_no)){
                $is_posp = 'Y';
                $sol_id = $pos_data->pan_no;
                if(!empty($pos_data->relation_tata_aig))
                {
                    $q_office_location = $pos_data->relation_tata_aig ;

                }
                else{
                    $q_office_location = config('constants.motor.constants.IcConstants.tata_aig_v2.TATA_AIG_V2_POS_Q_OFFICE_LOCATION_CODE');
                }
            }
        }elseif(config('constants.motor.constants.IcConstants.tata_aig_v2.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2') == 'Y')
        {
            $is_posp = 'Y';
            $sol_id  = config('IC.TATA_AIG.V2.BIKE.SOL_ID'); //55552
            $q_office_location = config('IC.TATA_AIG.V2.BIKE.Q_OFFICE_LOCATION'); //90300
        }
        else
        {
            $is_pos = 'N';
        }

        if($is_od){
            $tp_insured         = $proposal_additional_details['prepolicy']['tpInsuranceCompany'];
            $tp_insurer_name    = $proposal_additional_details['prepolicy']['tpInsuranceCompanyName'];
            $tp_start_date      = $proposal_additional_details['prepolicy']['tpStartDate'];
            $tp_end_date        = $proposal_additional_details['prepolicy']['tpEndDate'];
            $tp_policy_no       = $proposal_additional_details['prepolicy']['tpInsuranceNumber'];

            $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $tp_insurer_name)->first();
        }

        $rto_code = explode('-', $requestData->rto_code);

        // $rto_data = DB::table('tata_aig_v2_rto_master')
        //     ->where('txt_rto_code', 'like', '%'.$rto_code[0].$rto_code[1].'%')
        //     ->first();
        $rto_data = DB::table('tata_aig_v2_rto_master')
        ->where('txt_rto_code', str_replace('-', '',RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
        ->first();

        $token_response = self::getToken($enquiryId, $productData);

        if(!$token_response['status'])
        {

            return $token_response;
        }

        
        if(config('IC.TATA_AIG.V2.BIKE.NO_ANTITHEFT') == 'Y')
        {
            $is_anti_theft = false;
        }

        

        if(config('IC.TATA_AIG.V2.BIKE.NO_NCB_PROTECTION') == 'Y')
        {
            $applicableAddon['NCBProtectionCover'] = 'No';
        }

        if(in_array(strtoupper($mmv->txt_segment), ['MINI','COMPACT', 'MPS SUV', 'MPV SUV', 'MID SIZE']))
        {
            $engineProtectOption = 'WITH DEDUCTIBLE';
        }
        else
        {
            $engineProtectOption = 'WITHOUT DEDUCTIBLE';
        }


        if($zero_dep_age_available){
            $ReturnToInvoice = 'true';
            $RoadsideAssistance = 'true';
        }
    
        // $ConsumablesExpenses = ($vehicle_age < 4) ?  'Y' : 'N';
    
        if($is_liability){
            $DepreciationReimbursement = 'false';
            $NoOfClaimsDepreciation = '';
            $RoadsideAssistance = 'false';
            $ac_opted_in_pp = 'false';
    
            $ConsumablesExpenses = 'false';
            $ReturnToInvoice = 'false';
        }
    
        if($is_od && $is_breakin)
        {
            $RoadsideAssistance = 'false';
            $ConsumablesExpenses = 'false';
            $ReturnToInvoice = 'false';
        }

        $first_name = '';
        $middle_name = '';
        $last_name = '';

        $nameArray = $is_individual ? (explode(' ', trim($proposal->first_name.' '.$proposal->last_name))) : explode(' ', trim($proposal->first_name));

        $first_name = $nameArray[0];
        
          // for TATA f_name and l_name should only contain 1 word and rest will be in m_name
          if(count($nameArray) > 2){
            $last_name = end($nameArray);
            array_pop($nameArray);
            array_shift($nameArray);
            $middle_name = implode(' ', $nameArray);
        }

        else
        {
            $middle_name = '';
            if(env('APP_ENV') == 'local')
            {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '.');
            }else
            {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '');
            }
        }


        if ($is_individual) {
            if ($proposal->gender == "M" || $proposal->gender == "Male")
            {
                $gender = 'Male';
                $insured_prefix = 'Mr';
            }
            else
            {
                $gender = 'Female';
                if ($proposal->marital_status != "Single")
                {
                    $insured_prefix = 'Mrs';
                }
                else
                {
                    $insured_prefix = 'Ms';
                }
            }
        }
        else
        {
            $gender = 'Others';
            $insured_prefix = 'M/s.';
        }
       
        $input_array_info = [
            "quote_id" =>"",
            "vehicle_idv" =>"",
            "q_producer_code" => config('IC.TATA_AIG.V2.BIKE.PRODUCER_CODE'),
            "q_producer_email" =>config('IC.TATA_AIG.V2.BIKE.PRODUCER_EMAIL'),
            "pol_tenure" =>"5",
            'business_type' =>($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
            "proposer_type" => $customer_type,
            "plan_type" => ($is_liability ? 'Standalone TP' : "Package (1 year OD + 5 years TP)" ),
            "fleet_policy" =>"false",
            "fleet_code" =>"",
            "fleet_name" =>"indu@22",
            "pol_start_date" => $policy_start_date ,
            'pa_owner' => (($is_individual && !$is_od) ? ($cpa_cover ? 'true' : 'false') : 'false'),
            'pa_owner_declaration' =>"None",
            'pa_owner_tenure' => $pa_owner_tenure,
            "driver_age" =>"",
            "driver_gender" =>"",
            "driver_occupation" =>"",
            'claim_last'                    => ($is_new ? 'false' : (($requestData->is_claim == 'N' || $is_liability) ? 'false' : 'true')),
            'claim_last_amount'             => '',
            'claim_last_count'              => '',
            "pre_pol_ncb" => ((($is_new && $noPrevPolicy) || ($requestData->is_claim == 'Y')) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
            "pre_pol_protect_ncb" => "NA",
            "proposer_salutation" =>$insured_prefix,
            "proposer_first_name" => $first_name,
            "proposer_last_name" => $last_name,
            "proposer_email" => $proposal->email,
            "proposer_mobile" => $proposal->mobile_number,
            "no_past_pol" =>"N",
            "prev_pol_end_date" => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d')),
            "prev_pol_start_date" => (($is_new || $noPrevPolicy) ? '' : $prevPolyStartDate),
            "prev_pol_type" => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 5 Year TP)' : 'LiabilityOnly')),
            "rtn_invoice" => $applicableAddon['ReturnToInvoice'],
            "cng_lpg" =>"false",
            "dep_reimb" =>"true",
            "special_regno" => $special_regno,
            // "regno_1" => "MH",
            // "regno_2" => "01",
            // "regno_3" => "CK",
            // "regno_4" => "1776",
            "regno_1"                       => $vehicle_register_no[0] ?? "",
            "regno_2"                       => $is_new ? "" : (string)(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code, true))[1] ?? ($vehicle_register_no[1] ?? "")), // (string)($vehicle_register_no[1] ?? ""), 
            "regno_3"                       => $vehicle_register_no[2] ?? "",
            "regno_4"                       => (string)($vehicle_register_no[3] ?? ""),
            'place_reg'                     => $rto_data->txt_rtolocation_name,
            'place_reg_no'                  => $rto_data->txt_rtolocation_code,   
            "proposer_pincode" => $rto_data->num_pincode,
            // "vehicle_make" =>"BAJAJ",
            // "vehicle_model" =>"PLATINA",
            // "vehicle_variant" =>"110 H GEAR DRUM",
            'vehicle_make'                  => $mmv->make,   
            'vehicle_model'                 => $mmv->txt_model,
            'vehicle_variant'               => $mmv->txt_model_variant,
            "dor_first" => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
            "dor" => Carbon::parse($requestData->vehicle_register_date)->format('Y-m-d'),
            "man_year" => Carbon::parse($requestData->vehicle_register_date)->format('Y'),
            "manu_month" =>Carbon::parse($requestData->vehicle_register_date)->format('m'),
            // "make_code" =>"939",
            // "model_code" =>"15667",
            // "variant_code" =>"132501",  
            "make_code" => (string)($mmv->make_code),
            "model_code" => (string)($mmv->num_model_code),
            "variant_code" => (string)$mmv->num_model_variant_code,
            "veh_plying_city" => "TARIKERE",
            "side_car" =>"false",
            "side_car_idv" =>"",
            "non_electrical_acc" => $NonElectricalaccess,
            "non_electrical_si" =>  (string)$NonElectricalaccessSI,
            "non_electrical_des" => "xcx",
            "electrical_acc" => $Electricalaccess,
            "electrical_si" =>  (string)$ElectricalaccessSI,
            "electrical_des" => "jasj",
            "cng_lpg_cover"=> "false",
            "cng_lpg_si" => "",
            "automobile_association_cover"=> "false",
            "automobile_association_mem_exp_date"=> "",
            "automobile_association_mem_no"=> "",
            "antitheft_cover" =>  $is_anti_theft ,
            "vehicle_blind"=> "false",
            "own_premises"=> "false",
            "tppd_discount"=> $is_tppd ,
            "voluntary_deductibles"=> "false",
            "voluntary_amount"=> "",
            "uw_discount"=> "",
            "uw_loading"=> "",
            'pa_paid'                       => $is_pa_paid,  // 'pa_paid_no'                    =>  $is_pa_paid == "Yes" ? 1 : 0,
            'pa_paid_si'                    => (string)$PAforaddionaldPaidDriverSI,
            "pa_paid_no" => "",
            "pa_unnamed" => "false",
            "pa_unnamed_no" => "",
            "pa_unnamed_si" => "",
            'dep_reimburse'                 => $DepreciationReimbursement,
            'dep_reimburse_claims'          =>  $NoOfClaimsDepreciation,
            "dep_reimburse_deductible"=> $productData->product_identifier == "basic" || $productData->product_identifier == "basic_withaddons"  ? "" : "0" ,
            "return_invoice" =>  $applicableAddon['ReturnToInvoice'],
            "consumbale_expense" => $applicableAddon['ConsumablesExpenses'],
            "add_towing"=> "false",
            "add_towing_amount"=> "",
            "rsa"=> $applicableAddon['RoadsideAssistance'],
            "emg_med_exp"=> "false",
            "emg_med_exp_si"=> "",
            'll_paid'                       => $is_ll_paid,
            'll_paid_no'                    => $is_ll_paid === "true" ? 1 : 0,
            "ll_emp"=> "false",
            "ll_emp_no"=> "",
            "add_tppd"=> "false",
            "add_tppd_si"=> "",
            "add_pa_owner"=> "false",
            "add_pa_owner_si"=> "",
            "add_pa_unnamed"=> "false",
            "add_pa_unnamed_si"=> "",
            "driving_tution"=> "false",
            "vehicle_trails_racing"=> "false",
            "event_name"=> "",
            "promoter_name"=> "",
            "event_from_date"=> "",
            "event_to_date"=> "",
            "ext_racing"=> "false",
            "imported_veh_without_cus_duty"=> "false",
            "fibre_fuel_tank"=> "false",
            "loss_accessories"=> "false",
            "loss_accessories_idv"=> "",
            "geography_extension" => $is_Geographical,
            "geography_extension_bang" => in_array('Bangladesh', $countries) ? "true" : "false",
            "geography_extension_bhutan" => in_array('Bhutan', $countries) ? "true" : "false",
            "geography_extension_lanka" => in_array('Sri Lanka', $countries) ? "true" : "false",
            "geography_extension_maldives" => in_array('Nepal', $countries) ? "true" : "false",
            "geography_extension_nepal" =>in_array('Maldives', $countries) ? "true" : "false",
            "geography_extension_pak" => in_array('Pakistan', $countries) ? "true" : "false",
            "__finalize"=> "1",
            "revised_idv" => "",
    
    
        ];

        if ($requestData->vehicle_owner_type == "C") {
            $nameArray = explode(' ', trim($proposal->first_name));
            $count = count($nameArray);
            $midpoint = floor($count / 2);

            $first_name = implode(" ", array_slice($nameArray, 0, $midpoint));
            $last_name = implode(" ", array_slice($nameArray, $midpoint));
            if($count == 1){
                $first_name = $nameArray[0];
                $last_name = ' ';
            }
            $proposalRequest['proposer_fname'] = $first_name;
            $proposalRequest['proposer_mname'] = '';
            $proposalRequest['proposer_lname'] = $last_name;
        }

        if((config('IC.TATA_AIG_V2.CAR.PROPRIETORSHIP.ENABLED') == 'Y') && $requestData->vehicle_owner_type == "C"){
            if(!empty($additionalDetailsData->owner->organizationType) && $additionalDetailsData->owner->organizationType == 'Proprietorship'){
                $proposalRequest['prop_flag'] = 'true';
                $proposalRequest['prop_name'] = $proposal->proposer_ckyc_details->related_person_name;
            }else{
                $proposalRequest['prop_flag'] = 'false';
                $proposalRequest['prop_name'] = '';
            }
        }

        if($requestData->business_type =="newbusiness"  &&  $premium_type == "third_party"  )
        {
            $input_array_info['antitheft_cover'] = "false";
            $input_array_info['plan_type'] = "Standalone TP (5 years)";
            $input_array_info['pol_end_date'] = "";
            $input_array_info['pol_plan_id'] = "3";
            $input_array_info['non_electrical_acc'] = "false";
            $input_array_info['non_electrical_si']= "" ;
            $input_array_info['non_electrical_des']= "" ;
            $input_array_info['electrical_acc']= "false" ;
            $input_array_info['electrical_si']= "" ;
            $input_array_info['electrical_des']= "" ;
    
        }

        if($requestData->business_type =="rollover"  &&  $premium_type == "comprehensive" )
        {

            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['business_type_no'] = "03";
            $input_array_info['fleet_name'] = "";
            $input_array_info['no_past_pol'] = "false";
            $input_array_info['plan_type'] = "Package (1 year OD + 1 year TP)";
            $input_array_info['prev_pol_type'] = "PackagePolicy";
            $input_array_info['pol_tenure'] = "1";    
            $input_array_info['voluntary_deductibles_amt'] = "";
            $input_array_info['veh_plying_city'] = "";
            $input_array_info['return_invoice'] = "false";
            $input_array_info['consumbale_expense'] = "false"; 
           
    
        }
        if($requestData->business_type =="rollover"  &&  $premium_type == "third_party"  )
        {
            $input_array_info['antitheft_cover'] = "false";
            $input_array_info['plan_type'] = "Standalone TP (1 year)";
            $input_array_info['pol_end_date'] = "";
            $input_array_info['pol_plan_id'] = "3";
            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['non_electrical_acc'] = "false";
            $input_array_info['non_electrical_si']= "" ;
            $input_array_info['non_electrical_des']= "" ;
            $input_array_info['electrical_acc']= "false" ;
            $input_array_info['electrical_si']= "" ;
            $input_array_info['electrical_des']= "" ;
    
        }


        if($requestData->business_type =="rollover"  &&  $premium_type == "own_damage" )
        {

            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['business_type_no'] = "03";
            $input_array_info['fleet_name'] = "";
            $input_array_info['no_past_pol'] = "false";
            $input_array_info['plan_type'] = "Standalone OD (1 year)";
            $input_array_info['prev_pol_type'] = "PackagePolicy";
            $input_array_info['pol_tenure'] = "1";   
            $input_array_info['voluntary_deductibles_amt'] = "";          
            $input_array_info['veh_plying_city'] = "";
            $input_array_info['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
            $input_array_info['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
            $input_array_info['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
            $input_array_info['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
            $input_array_info['dep_reimb'] = "true";
        
    
        }


        if($requestData->previous_policy_type == "Not sure")
        {
            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['business_type_no'] = "03";
            $input_array_info['fleet_name'] = "";
            $input_array_info['no_past_pol'] = "false";
            $input_array_info['plan_type'] = "Package (1 year OD + 1 year TP)";
            // $input_array_info['prev_pol_type'] = "PackagePolicy";
            $input_array_info['pol_tenure'] = "1";    
            $input_array_info['voluntary_deductibles_amt'] = "";      
            $input_array_info['veh_plying_city'] = "";          
            $input_array_info['no_past_pol'] = "true";
            $input_array_info['no_past_policy'] = 'true';
    
        }

        
      

        if($requestData->business_type =="breakin"  &&  $premium_type == "breakin")
        {
            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['business_type_no'] = "03";
            $input_array_info['fleet_name'] = "";
            $input_array_info['no_past_pol'] = "false";
            $input_array_info['plan_type'] = "Package (1 year OD + 1 year TP)";
            // $input_array_info['prev_pol_type'] = "PackagePolicy";
            $input_array_info['pol_tenure'] = "1";  
            $input_array_info['voluntary_deductibles_amt'] = "";       
            $input_array_info['veh_plying_city'] = "";         
            $input_array_info['no_past_pol'] = "true";
  
        }


        if($requestData->business_type =="breakin"  &&  $premium_type == "third_party_breakin" || $requestData->business_type =="rollover"  &&  $premium_type == "third_party_breakin")
        {
            $input_array_info['antitheft_cover'] = "false";
            $input_array_info['plan_type'] = "Standalone TP (1 year)";
            $input_array_info['pol_end_date'] = "";
            $input_array_info['pol_plan_id'] = "3";
            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['non_electrical_acc'] = "false";
            $input_array_info['non_electrical_si']= "" ;
            $input_array_info['non_electrical_des']= "" ;
            $input_array_info['electrical_acc']= "false" ;
            $input_array_info['electrical_si']= "" ;
            $input_array_info['electrical_des']= "" ;
    
        }


        if($requestData->business_type =="rollover"  &&  $premium_type == "breakin")
        {
            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['business_type_no'] = "03";
            $input_array_info['fleet_name'] = "";
            $input_array_info['no_past_pol'] = "false";
            $input_array_info['plan_type'] = "Package (1 year OD + 1 year TP)";
            // $input_array_info['prev_pol_type'] = "PackagePolicy";
            $input_array_info['pol_tenure'] = "1";
            $input_array_info['voluntary_deductibles_amt'] = "";         
            $input_array_info['veh_plying_city'] = "";          
    
        }

        if($requestData->business_type =="breakin"  &&  $premium_type == "own_damage_breakin")
        {
  
            $input_array_info['business_type'] = "Roll Over";
            $input_array_info['business_type_no'] = "03";
            $input_array_info['fleet_name'] = "";
            $input_array_info['no_past_pol'] = "false";
            $input_array_info['plan_type'] = "Package (1 year OD + 1 year TP)";
            // $input_array_info['prev_pol_type'] = "PackagePolicy";
            $input_array_info['pol_tenure'] = "1";   
            $input_array_info['voluntary_deductibles_amt'] = "";      
            $input_array_info['veh_plying_city'] = "";          
        
        }

        if($is_posp == "Y")
        {
            $input_array_info['is_posp'] = $is_posp;
            $input_array_info['sol_id'] = $sol_id;
            $input_array_info['q_office_location'] = $q_office_location;
        }

        if(!$is_new || !$noPrevPolicy)
        {
            $quoteRequest['no_past_pol'] = 'N';
        }
        else
        {
            $quoteRequest['no_past_pol'] = 'Y';
        }

        if(!$is_new)
        {
            $quoteRequest['no_past_pol'] = 'N';
        }
        if($noPrevPolicy){
            $quoteRequest['no_past_pol'] = 'Y';
        }
    

        if($applicableAddon['NCBProtectionCover'] == 'Yes')
        {
            $quoteRequest['ncb_no_of_claims'] = '1';
        }
        //checking last addons
        $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsConsumable_Cover = $PreviousPolicy_IsReturnToInvoice_Cover = $PreviousPolicy_IsTyre_Cover = $PreviousPolicy_IsEngine_Cover = $PreviousPolicy_IsLpgCng_Cover = $is_breakin_case =  false;
        if (!empty($proposal->previous_policy_addons_list)) {
            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
            foreach ($previous_policy_addons_list as $key => $value) {
                if ($key == 'zeroDepreciation' && $value) {
                    $PreviousPolicy_IsZeroDept_Cover = true;
                } else if ($key == 'consumables' && $value) {
                    $PreviousPolicy_IsConsumable_Cover = true;
                }else if ($key == 'tyreSecure' && $value) {
                    $PreviousPolicy_IsTyre_Cover = true;
                }else if ($key == 'engineProtector' && $value) {
                    $PreviousPolicy_IsEngine_Cover = true;
                }else if ($key == 'externalBiKit' && $value) {
                    $PreviousPolicy_IsLpgCng_Cover = true;
                }else if ($key == 'returnToInvoice' && $value) {
                    $PreviousPolicy_IsReturnToInvoice_Cover = true;
                }
            }
        }
        if(!$is_new && !$noPrevPolicy)
        {
            if ($is_lpg_cng) {
                $quoteRequest['prev_cnglpg'] = 'Yes';
                if (!$PreviousPolicy_IsLpgCng_Cover && !$is_liability) {
                    $is_breakin_case =  true;
                    $quoteRequest['prev_cnglpg'] = 'No';
                }
            }

            if($is_liability)
            {
                $quoteRequest['prev_cnglpg'] = 'No';
            }

            if ($applicableAddon['ConsumablesExpenses'] == 'Yes') {
                $quoteRequest['prev_consumable'] = 'Yes';
                if (!$PreviousPolicy_IsConsumable_Cover) {
                    $quoteRequest['prev_consumable'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['ReturnToInvoice'] == 'Yes') {
                $quoteRequest['prev_rti'] = 'Yes';
                if (!$PreviousPolicy_IsReturnToInvoice_Cover) {
                    $quoteRequest['prev_rti'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['TyreSecure'] == 'Yes') {
                $quoteRequest['prev_tyre'] = 'Yes';
                if (!$PreviousPolicy_IsTyre_Cover) {
                    $quoteRequest['prev_tyre'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['EngineSecure'] == 'Yes') {
                $quoteRequest['prev_engine'] = 'Yes';
                if (!$PreviousPolicy_IsEngine_Cover) {
                    $quoteRequest['prev_engine'] = 'No';
                    $is_breakin_case =  true;
                }
            }

            if ($applicableAddon['DepreciationReimbursement'] == 'true') {
                $quoteRequest['prev_dep'] = 'true';
                if (!$PreviousPolicy_IsZeroDept_Cover) {
                    $quoteRequest['prev_dep'] = 'false';
                    $is_breakin_case =  true;
                }
            }
        }

        if(!$is_od)
        {
            if($PAforUnnamedPassenger == 'Yes')
            {
                $quoteRequest['pa_unnamed'] = $PAforUnnamedPassenger;
                $quoteRequest['pa_unnamed_csi'] = '';
                $quoteRequest['pa_unnamed_no'] = (string)($mmv->num_seating_capacity);
                $quoteRequest['pa_unnamed_si'] = (string)$PAforUnnamedPassengerSI;
            }
            if($llpaidDriver == 'Yes')
            {
                $quoteRequest['ll_paid'] = $llpaidDriver;
                $quoteRequest['ll_paid_no'] = '1';
            }
            if($PAforaddionaldPaidDriver == 'Yes')
            {
                $quoteRequest['pa_paid'] = $PAforaddionaldPaidDriver;
                $quoteRequest['pa_paid_no'] = '1';
                $quoteRequest['pa_paid_si'] = $PAforaddionaldPaidDriverSI;
            }
        }

        if($is_od)
        {
            $input_array_info['ble_tp_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('Y-m-d');
            $input_array_info['ble_tp_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');

            $input_array_info['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');
            $input_array_info['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
        }


        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

    
        $get_response = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_QUOTE'), $input_array_info, 'tata_aig_v2', $additional_data);

        $quoteResponse = $get_response['response'];

        if(!($quoteResponse && $quoteResponse != '' && $quoteResponse != null))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        $quoteResponse = json_decode($quoteResponse, true);

        if(empty($quoteResponse))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }
        if(isset($quoteResponse['message']) && $quoteResponse['message'] == 'Endpoint request timed out')
        {
            return [
                'status'        => false,
                'msg'           => $quoteResponse['message'],
                'webservice_id' => $get_response['webservice_id'],
                'table'         => $get_response['table'],
                'Request'       => $quoteRequest,
                'stage'         => 'quote'
            ];
            
        }

        if(isset($quoteResponse['status']) && $quoteResponse['status'] != 200)
        {
            if(!isset($quoteResponse['message_txt']))
            {
                return [
                    'status'    => false,
                    'msg'       => 'Insurer Not Reachable',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $quoteRequest,
                    'stage'     => 'quote'
                ];
            }
            return [
                'status'    => false,
                'msg'       => $quoteResponse['message_txt'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $quoteRequest,
                'stage'     => 'quote'
            ];
        }

        
    $response = $get_response['response'];
    if (empty($response)) {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer not reachable',
        ];
    }

    $response = json_decode($response, true);

    if($response){
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quotation converted to proposal successfully", "Success" );
    }

    // $idv            =  $response['data']['data']['vehicle_idv'];
  
    // $input_array_info['vehicle_idv'] = (string)($idv);
   
    // pass idv
    $max_idv    = ($is_liability ? 0 : ($response['data']['data']['max_idv']?? 0)  +  ($quoteResponse['max_idv_chassis'] ?? 0));
    $min_idv    = ($is_liability ? 0 : ($response['data']['data']['min_idv'] ?? 0) +  ($quoteResponse['min_idv_chassis'] ?? 0));
    // $idv = $is_liability ? 0 : ($input_array_info['vehicle_idv'] ?? 0);


     $input_array_info['vehicle_idv'] = (string)($idv);
     $input_array_info['__finalize'] = '1';

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Recalculation - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        
         $get_response = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_QUOTE'), $input_array_info, 'tata_aig_v2', $additional_data);;
         $quoteResponse = $get_response['response'];
         
         $premWebServiceId = $get_response['webservice_id'];

         
         if(!($quoteResponse && $quoteResponse != '' && $quoteResponse != null))
         {
             return [
                 'status'    => false,
                 'msg'       => 'Insurer Not Reachable',
                 'webservice_id' => $get_response['webservice_id'],
                 'table' => $get_response['table'],
                 'Request'   => $quoteRequest,
                 'stage'     => 'quote'
             ];
         }
         $quoteResponse = json_decode($quoteResponse, true);
 
         if(empty($quoteResponse))
         {
             return [
                 'status'    => false,
                 'msg'       => 'Insurer Not Reachable',
                 'webservice_id' => $get_response['webservice_id'],
                 'table' => $get_response['table'],
                 'Request'   => $quoteRequest,
                 'stage'     => 'quote'
             ];
         }
         if(isset($quoteResponse['message']) && $quoteResponse['message'] == 'Endpoint request timed out')
         {
             return [
                 'status'        => false,
                 'msg'           => $quoteResponse['message'],
                 'webservice_id' => $get_response['webservice_id'],
                 'table'         => $get_response['table'],
                 'Request'       => $quoteRequest,
                 'stage'         => 'quote'
             ];
             
         }
 
         if(isset($quoteResponse['status']) && $quoteResponse['status'] != 200)
         {
             if(!isset($quoteResponse['message_txt']))
             {
                 return [
                     'status'    => false,
                     'msg'       => 'Insurer Not Reachable',
                     'webservice_id' => $get_response['webservice_id'],
                     'table' => $get_response['table'],
                     'Request'   => $quoteRequest,
                     'stage'     => 'quote'
                 ];
             }
             return [
                 'status'    => false,
                 'msg'       => $quoteResponse['message_txt'],
                 'webservice_id' => $get_response['webservice_id'],
                 'table' => $get_response['table'],
                 'Request'   => $quoteRequest,
                 'stage'     => 'quote'
             ];
         }
          
     $response = $get_response['response'];
     if (empty($response)) {
         return [
             'webservice_id' => $get_response['webservice_id'],
             'table' => $get_response['table'],
             'premium_amount' => 0,
             'status' => false,
             'message' => 'Insurer not reachable',
         ];
     }
 
     $response = json_decode($response, true);
  
   if($response){
      
         update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quotation converted to proposal successfully", "Success" );
     }
 
     // pass idv
    $max_idv    = ($is_liability ? 0 : ($response['data']['data']['max_idv']?? 0)  +  ($quoteResponse['max_idv_chassis'] ?? 0));
    $min_idv    = ($is_liability ? 0 : ($response['data']['data']['min_idv'] ?? 0) +  ($quoteResponse['min_idv_chassis'] ?? 0));
    // $idv = $is_liability ? 0 : ($input_array_info['vehicle_idv'] ?? 0);
 
        $policy_id = $response['data']['policy_id'];
        $quote_id = $response['data']['quote_id'];
        $quote_no = $response['data']['quote_no'];
        $proposal_id = $response['data']['proposal_id'];
        $product_id = "M300000000002"; // hard code 
        $totalOdPremium = $response['data']['premium_break_up']['total_od_premium'];

         
    $totalAddons    = $response['data']['premium_break_up']['total_addOns'];
  
    $totalTpPremium = $response['data']['premium_break_up']['total_tp_premium'];
  
    $netPremium     = (float)(isset($response['data']['premium_break_up']['net_premium']) ? $response ['data']['premium_break_up']['net_premium'] : 0);
    
    $finalPremium   = (float)(isset($response['data']['premium_break_up']['final_premium']) ?$response['data']['premium_break_up']['final_premium']: 0);
 
    $basic_od       = (float)(isset($totalOdPremium['od']['basic_od']) ? $totalOdPremium['od']['basic_od'] : 0);
    
    $non_electrical = (float)(isset($totalOdPremium['od']['non_electrical_prem']) ?$totalOdPremium['od']['non_electrical_prem'] : 0);
  

    $electrical     = (float)(isset($totalOdPremium['od']['electrical_prem']) ? ($totalOdPremium['od']['electrical_prem']) : 0);
    
    $lpg_cng_od     = (float)(isset($totalOdPremium['od']['cng_lpg_od_prem']) ? $totalOdPremium['od']['cng_lpg_od_prem'] : 0);
   
    $basic_tp       = (float)(isset($totalTpPremium['basic_tp_prem']) ? $totalTpPremium['basic_tp_prem'] : 0);
  
    $pa_unnamed     = (float)(isset($totalTpPremium['pa_unnamed_prem']) ? $totalTpPremium['pa_unnamed_prem'] : 0);

    $ll_paid        = (float)(isset($totalTpPremium['ll_paid_driver_prem']) ? $totalTpPremium['ll_paid_driver_prem'] : 0);

    $lpg_cng_tp     = (float)(isset($totalTpPremium['cng_lpg_tp_prem']) ? $totalTpPremium['cng_lpg_tp_prem'] : 0);
  
    $pa_paid        = (float)(isset($totalTpPremium['pa_paid_driver_prem']) ? $totalTpPremium['pa_paid_driver_prem'] : 0);
   
    $pa_owner       = (float)(isset($totalTpPremium['pa_cover_prem']) ? $totalTpPremium['pa_cover_prem'] : 0);
   
    $tppd_discount  = (float)(isset($response['data']['premium_break_up']['total_tp_premium']['disc_tppd_prem']) ? $response['data']['premium_break_up']['total_tp_premium']['disc_tppd_prem'] : 0);

    $tp_gio = (float)(isset($totalTpPremium['geo_extension_tp_prem']) ? $totalTpPremium['geo_extension_tp_prem'] : 0 );
 
    $od_gio = (float)(isset($totalOdPremium['od']['geography_extension_od_prem']) ? $totalOdPremium['od']['geography_extension_od_prem'] : 0 );
    
    $final_tp_premium = $basic_tp + $pa_unnamed + $ll_paid + $lpg_cng_tp + $pa_paid + $tp_gio;
   
    $final_od_premium = $basic_od + $non_electrical + $electrical + $lpg_cng_od + $od_gio;
   
    $anti_theft_amount      = (float)(isset($totalOdPremium['discount_od']['disc_antitheft_prem']) ? $totalOdPremium['discount_od']['disc_antitheft_prem'] : 0);
    $automoble_amount       = (float)(isset($totalOdPremium['discount_od']['aam_disc_prem']) ? $totalOdPremium['discount_od']['aam_disc_prem'] : 0);
    $voluntary_deductible   = (float)(isset($totalOdPremium['discount_od']['vd_disc_prem']) ? $totalOdPremium['discount_od']['vd_disc_prem'] : 0);
    
     $ncb_discount_amount    = (float)(isset($totalAddons['ncb_prem']) ? $totalAddons['ncb_prem'] : 0);    
    $final_total_discount = $ncb_discount_amount + $anti_theft_amount + $automoble_amount + $voluntary_deductible + $tppd_discount;
    $zero_dep_amount            = (float)(isset($totalAddons['dep_reimburse_prem']) ? $totalAddons['dep_reimburse_prem'] : 0);

    $rsa_amount                 = (float)(isset($totalAddons['rsa_prem']) ? $totalAddons['rsa_prem'] : 0);

    $ncb_protect_amount         = (float)(isset($totalAddons['ncb_protection_prem']) ? $totalAddons['ncb_protection_prem'] : 0);
    $engine_seccure_amount      = (float)(isset($totalAddons['engine_secure_prem']) ? $totalAddons['engine_secure_prem'] : 0);
    $tyre_secure_amount         = (float)(isset($totalAddons['tyre_secure_prem']) ? $totalAddons['tyre_secure_prem'] : 0);
    $rti_amount                 = (float)(isset($totalAddons['return_invoice_prem']) ? $totalAddons['return_invoice_prem'] : 0);
    $counsumable_amount         = (float)(isset($totalAddons['consumbale_expense_prem']) ? $totalAddons['consumbale_expense_prem'] : 0);
    $key_replacment_amount      = (float)(isset($totalAddons['key_replace_prem']) ? $totalAddons['key_replace_prem'] : 0);
    $personal_belongings_amount = (float)(isset($totalAddons['personal_loss_prem']) ? $totalAddons['personal_loss_prem'] : 0);
    $emergency_expense_amount   = (float)(isset($totalAddons['emergency_medical_expense_prem']) ? $totalAddons['emergency_medical_expense_prem'] : 0);
    $repair_glass_prem          = (float)(isset($totalAddons['repair_glass_prem']) ? $totalAddons['repair_glass_prem'] : 0);
 
    $final_addon_amount         = (float)(isset($totalAddons['total_addon']) ? $totalAddons['total_addon'] : 0);

    $total_od       = $totalOdPremium['total_od'];
    $total_tp       = $totalTpPremium['total_tp_prem']; 
    $premium_without_gst = $response['data']['premium_break_up']['net_premium'];
  


        if ($is_individual) {
            if ($proposal->gender == "M" || $proposal->gender == "Male")
            {
                $gender = 'Male';
                $insured_prefix = 'Mr';
            }
            else
            {
                $gender = 'Female';
                if ($proposal->marital_status != "Single")
                {
                    $insured_prefix = 'Mrs';
                }
                else
                {
                    $insured_prefix = 'Ms';
                }
            }
        }
        else
        {
            $gender = 'Others';
            $insured_prefix = 'M/s.';
        }

        $occupation = $is_individual ? $proposal_additional_details['owner']['occupation'] : '';

        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if($proposal_additional_details['vehicle']['isVehicleFinance'])
        {
            $financerAgreementType = $proposal_additional_details['vehicle']['financerAgreementType'];
            $nameOfFinancer = $proposal_additional_details['vehicle']['nameOfFinancer'];
            $hypothecationCity = $proposal_additional_details['vehicle']['hypothecationCity'];
            if(isset($proposal_additional_details['vehicle']['financer_sel'][0]['name']))
            {
                $nameOfFinancer = $proposal_additional_details['vehicle']['financer_sel'][0]['name'];
            }
        }

        $pucExpiry = $pucNo = '';

        if(isset($proposal_additional_details['vehicle']['pucExpiry']))
        {
            $pucExpiry = Carbon::parse($proposal_additional_details['vehicle']['pucExpiry'])->format('Y-m-d');
        }
        if(isset($proposal_additional_details['vehicle']['pucNo']))
        {
            $pucNo = $proposal_additional_details['vehicle']['pucNo'];
        }

        if(is_numeric($nameOfFinancer))
        {
            $financeData   = DB::table("tata_aig_finance_master")
            ->where("code", $nameOfFinancer)
            ->first();
            if(!empty($financeData))
            {
                $nameOfFinancer = $financeData->name;
            }
        }

        $first_name = '';
        $middle_name = '';
        $last_name = '';

        $nameArray = $is_individual ? (explode(' ', trim($proposal->first_name.' '.$proposal->last_name))) : explode(' ', trim($proposal->first_name));

        $first_name = $nameArray[0];

        // for TATA f_name and l_name should only contain 1 word and rest will be in m_name
        if(count($nameArray) > 2){
            $last_name = end($nameArray);
            array_pop($nameArray);
            array_shift($nameArray);
            $middle_name = implode(' ', $nameArray);
        }
        else
        {
            $middle_name = '';
            if(env('APP_ENV') == 'local')
            {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '.');
            }else
            {
                $last_name = (isset($nameArray[1]) ? trim($nameArray[1]) : '');
            }
        }
     
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 55,
            'address_2_limit'   => 55,         
            'address_3_limit'   => 55,         
        ];
        $getAddress = getAddress($address_data);

        
        $inspection_type_self = ($is_breakin ? (($proposal->inspection_type ?? '') == 'Manual' ? 'No' : 'Yes') : 'No');
        
        $proposalRequest = [
            'proposer_gender' => $gender,
            'proposer_marital' => $is_individual ? $proposal->marital_status :'',
            'proposer_fname' => $first_name,
            'proposer_mname' => $middle_name,
            'proposer_lname' => empty($last_name) ? '.' : $last_name,
            'proposer_email' => strtolower($proposal->email),
            'proposer_mobile' => $proposal->mobile_number,
            'proposer_salutation' => $insured_prefix,
            'proposer_add1' => trim($getAddress['address_1']) ?? '',
            'proposer_add2' => trim($getAddress['address_2']) ?? '',
            'proposer_add3' => trim($getAddress['address_3']) ?? '',
            'proposer_occupation' => $occupation,
            'proposer_pan' => (string)($proposal->pan_number),
            'proposer_dob' => $is_individual ? Carbon::parse($proposal->dob)->format('Y-m-d') : '',
            'vehicle_puc_expiry' => $pucExpiry,
            'vehicle_puc' => $pucNo,
            'vehicle_puc_declaration' => 'true',
            'pre_insurer_name' => (($is_new || $noPrevPolicy) ? '' : $previousInsurerList->code),
            'pre_insurer_no' => (($is_new || $noPrevPolicy) ? '' : $proposal->previous_policy_number),
            'financier_type' => $financerAgreementType,
            'financier_name' => $nameOfFinancer,
            'proposal_id' => $proposal_id,      
            'vehicle_chassis' => $proposal->chassis_number,
            'vehicle_engine' => $proposal->engine_number,
            'carriedOutBy' => $inspection_type_self,
            '__finalize' => '1',
            "proposer_state" => $proposal->state,
            // "nominee_name" =>  "xyz",
            // "nominee_relation" =>"Daughter",
            // "nominee_age" => 12,
            'nominee_name' => (($is_individual && !$is_od) ? ($proposal->nominee_name ?? '') : ''),
            'nominee_relation' => (($is_individual && !$is_od) ? ($proposal->nominee_relationship ?? '') : ''),
            'nominee_age' => ((($is_individual && !$is_od) ? (int) ($proposal->nominee_age ?? '') : 0)),
            'appointee_name' => '',
            'appointee_relation' => '',
            "bund_od_add" => "",
            "bund_od_insurer_name" => "",
            "bund_od_pol_number" => "",
            "bund_tp_add" => "",
            "bund_tp_insurer_name" => "",
            "bund_tp_pol_number" => "",
            "pre_od_insurer_code" => "",
            "pre_od_insurer_name" => "",
            "pre_od_policy_no" => "",
            "pre_tp_insurer_code" => (($is_new || $noPrevPolicy) ? '' : $previousInsurerList->code),
            "pre_tp_insurer_name" => (($is_new || $noPrevPolicy) ? '' : $previousInsurerList->code),
            "pre_tp_pol_no" => "",
            "proposalInspectionOverride" => "true"
        ];

        if(config('DISABLING_TATA_RENEWAL_FOR_COMP_BIKE') == 'Y'){
            if($any_short_term == false){
                if($proposalRequest['pre_insurer_name'] === 'TAGIC'){
                    return response()->json([
                        'status' => false,
                        'message' => 'Renewal not allowed',
                    ]);
                }
            }
        }


        if($is_posp == "Y")
        {
            $proposalRequest['is_posp'] = $is_posp;
            $proposalRequest['sol_id'] = $sol_id;
            $proposalRequest['q_office_location'] = $q_office_location;
        }
        if($occupation == 'OTHER')
        {
            $proposalRequest['proposer_occupation_other'] = 'OTHER';
        }

        if($is_od)
        {
            $proposalRequest['ble_od_start']   = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
            $proposalRequest['ble_od_end']     = Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d');

            $proposalRequest['ble_tp_type']             = 'Package';
            $proposalRequest['ble_tp_tenure']           = '3';
            $proposalRequest['ble_tp_no']               = $tp_policy_no;
            $proposalRequest['ble_tp_name']             = $tp_insured;
            $proposalRequest['ble_tp_start']   = Carbon::parse($tp_start_date)->format('Y-m-d');
            $proposalRequest['ble_tp_end']     = Carbon::parse($tp_end_date)->format('Y-m-d');

            $proposalRequest['ble_saod_prev_no']        = $proposal_additional_details['prepolicy']['previousPolicyNumber'];

            $proposalRequest['od_pre_insurer_name']     = '';
            $proposalRequest['od_pre_insurer_no']       = '';
            $proposalRequest['od_pre_insurer_address']  = '';
        }

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [
                'Content-Type'  => 'application/JSON',
                'Authorization'  => 'Bearer '.$token_response['token'],
                'x-api-key'  	=> config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_XAPI_KEY')
            ],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Proposal Submition - Proposal',
            'transaction_type'  => 'proposal',
            'productName'       => $productData->product_name,
            'token'             => $token_response['token'],
        ];

        
        $get_response = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_PROPOSAL'), $proposalRequest, 'tata_aig_v2', $additional_data);
        $proposalResponse = $get_response['response'];

        if(!($proposalResponse && $proposalResponse != '' && $proposalResponse != null))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        }
        $proposalResponse = json_decode($proposalResponse, true);


        if(empty($proposalResponse))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];
        }

        if(!isset($proposalResponse['status']) || $proposalResponse['status'] != 200)

        {
         
            if(!isset($proposalResponse['message_txt']) )
            {
                return [
                    'status'    => false,
                    'msg'       => $proposalResponse['message'] ?? 'Insurer Not Reachable',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Request'   => $proposalRequest,
                    'stage'     => 'proposal'
                ];
            }
            return [
                'status'    => false,
                'msg'       => $proposalResponse['message_txt'],
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Request'   => $proposalRequest,
                'stage'     => 'proposal'
            ];

        }
        else
        {

            $proposalResponse2  = $proposalResponse;
            $proposalResponse   = $proposalResponse['data'][0];
            $proposal->od_premium               = $total_od; //+ $final_addon_amount;
            $proposal->tp_premium               = $total_tp;
            $proposal->cpa_premium              = $pa_owner;
            $proposal->addon_premium            = $final_addon_amount;
            $proposal->ncb_discount             = $ncb_discount_amount;
            $final_total_discount               = $final_total_discount + $tppd_discount;
            $proposal->service_tax_amount       = $premium_without_gst * 0.18;
            $proposal->total_premium            = $premium_without_gst;
            $proposal->proposal_no              = $proposalResponse['proposal_no'];
            $proposal->final_payable_amount     = $proposalResponse['premium_value'];
            $proposal->policy_start_date        = Carbon::parse($policy_start_date)->format('d-m-Y');
            $proposal->policy_end_date          = Carbon::parse($policy_end_date)->format('d-m-Y');
            $proposal->tp_start_date = Carbon::parse($tp_start_date)->format('d-m-Y');
            $proposal->tp_end_date =  Carbon::parse($tp_end_date)->format('d-m-Y');

            $tata_aig_v2_data = [
                'quote_no'       => $proposalResponse['quote_no'],
                'proposal_no'    => $proposalResponse['proposal_no'],
                'proposal_id'    => $proposalResponse['proposal_id'],
                'payment_id'     => $proposalResponse['payment_id'],
                'document_id'    => $proposalResponse['document_id'],
                'policy_id'      => $proposalResponse['policy_id'],
                'master_policy_id' => $productData->policy_id,
            ];



            // $isBreakinInspectionRequired = (isset($proposalResponse['inspectionFlag']) && $proposalResponse['inspectionFlag'] == 'true') ? true : false;
        
            // if($isBreakinInspectionRequired && (config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_INSPECTION_ENABLED') == 'Y'))
            // {
            //     return [
            //         'status'    => false,
            //         'msg'       => 'Inspection is not allowed',
            //         'webservice_id' => $get_response['webservice_id'],
            //         'table' => $get_response['table'],
            //         'Request'   => $proposalRequest,
            //         'Request'   => $proposalResponse,
            //         'stage'     => 'proposal'
            //     ];
            // }
          

            $proposal_additional_details['tata_aig_v2'] = $tata_aig_v2_data;
            $proposal->additional_details = json_encode($proposal_additional_details);
            $proposal->additional_details_data = $proposal_additional_details;
            $proposal->save();
            $data['user_product_journey_id'] = customDecrypt($request['userProductJourneyId']);
            $data['ic_id'] = $productData->policy_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
    
            updateJourneyStage($data);

            TataAigPremiumDetailController::saveV2PremiumDetails($premWebServiceId);

            if (config('constants.IS_CKYC_ENABLED') == 'Y' && config('constants.IS_CKYC_ENABLED_TATA_AIG') == 'Y') {

                try {
                    // $is_breakin_case = ($isBreakinInspectionRequired ? 'Y' : 'N');

                    if (config('constants.IcConstants.tata_aig_v2.IS_NEW_CKYC_FLOW_ENABLED_FOR_TATA_AIG_V2') == 'Y') {
                        $webserviceData = $get_response;
                        $proposalSubmitResponse = $proposalResponse;
                        $validateCKYC = ckycVerifications(compact('proposal', 'proposalSubmitResponse', 'webserviceData', 'is_breakin_case'));
                        $validateCKYCJSON = $validateCKYC;
                        if ( ! $validateCKYC['status']) {
                            return response()->json($validateCKYC);
                        }
                    } else {

                        $validateCKYC = self::validateCKYC($proposal, $proposalResponse, $get_response, $is_breakin_case);

                        $validateCKYCJSON = $validateCKYC->getOriginalContent();

                        if(!$validateCKYCJSON['status'])
                        {
                            return $validateCKYC;
                        }
                    }

                    return $validateCKYC;
                } catch(\Exception $e) {
                    return response()->json([
                        'status' => false,
                        'message' => $e->getMessage(),
                        'dev_msg' => 'Line No. : ' . $e->getLine(),
                    ]);
                }
            }

        $finsall = new \App\Http\Controllers\Finsall\FinsallController();
        $finsall->checkFinsallAvailability('tata_aig', 'cv', $premium_type, $proposal);
        $submitProposalResponse = [
            'status' => true,
            'msg' => 'Proposal Submited Successfully..!',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $proposal->user_product_journey_id,
                    'proposalNo' => $proposalResponse['proposal_no'],
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => $proposal->is_breakin_case,
                    'isBreakinCase' => $proposal->is_breakin_case,
                    'inspection_number' => (isset($proposalResponse['ticket_number']) ? $proposalResponse['ticket_number'] : '')
            ]
        ];
        if(config('constants.IS_CKYC_ENABLED_TATA_AIG') != 'Y') {
            $submitProposalResponse['data']['verification_status'] = true;
        }
        return response()->json($submitProposalResponse);

        }
        return response()->json([
            'status' => false,
            'msg' => 'Something went wrong.'
        ]);

    }

    public static function checkTataAigMMV($productData, $version_id)
    {

        $product_sub_type_id = $productData->product_sub_type_id;
        $mmv = get_mmv_details($productData, $version_id, 'tata_aig_v2');


        if ($mmv["status"] == 1)
        {
            $mmv_data = $mmv["data"];
        }
        else
        {
            return [
                "premium_amount" => "0",
                "status" => false,
                "message" => $mmv["message"],
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == "")
        {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" => "Vehicle Not Mapped",
            ]);
        }
        elseif ($mmv_data->ic_version_code == "DNE")
        {
            return camelCase([
                "premium_amount" => "0",
                "status" => false,
                "message" =>
                    "Vehicle code does not exist with Insurance company",
            ]);
        }

        return (array)$mmv;
    }

    public static function getToken($enquiryId, $productData, $transaction_type = 'proposal')
    {

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Token Generation',
            'transaction_type'  => $transaction_type,
            'productName'       => $productData->product_name,
            'type'              => 'token'
        ];

        $tokenRequest = [
            'grant_type'    => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_GRANT_TYPE'),
            'scope'         => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SCOPE'),
            'client_id'     => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_ID'),
            'client_secret' => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_SECRET'),
        ];

        $get_response = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_TOKEN'), $tokenRequest, 'tata_aig_v2', $additional_data);
        $tokenResponse = $get_response['response'];

        if($tokenResponse && $tokenResponse != '' && $tokenResponse != null)
        {
            $tokenResponse = json_decode($tokenResponse, true);

            if(!empty($tokenResponse))
            {
                if(isset($tokenResponse['error']))
                {
                    return [
                        'status'    => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg'       => $tokenResponse['error'],
                        'stage'     => 'token'
                    ];
                }
                else
                {
                    return [
                        'status'    => true,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'token'     => $tokenResponse['access_token'],
                        'stage'     => 'token'
                    ];
                }
            }
            else
            {
                return [
                    'status'    => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg'       => 'Insurer Not Reachable',
                    'stage'     => 'token'
                ];
            }
        }
        else
        {
            return [
                'status'    => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg'       => 'Insurer Not Reachable',
                'stage'     => 'token'
            ];
        }
    }


    public static function validaterequest($response)
    {

        if(!($response && $response != '' && $response != null))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
            ];
        }
        $response = json_decode($response, true);

        if(empty($response))
        {
            return [
                'status'    => false,
                'msg'       => 'Insurer Not Reachable',
            ];
        }

        if(isset($response['status']) && $response['status'] != 200)
        {
            return [
                'status'    => false,
                'msg'       => $response['message_txt'] ?? ($response['message'] ?? 'Insurer Not Reachable'),
            ];
        }
        else
        {
            return [
                'status'    => isset($response['data']) ? true : false,
                'data'      => $response['data'] ?? 'Insurer Not Reachable'
            ];
        }
    }

    public static function validateCKYC(UserProposal $proposalData, Array $proposalSubmitResponse, Array $webserviceData, $is_breakin_case)
    {
        $request_data = [
            "companyAlias" => "tata_aig",
            "enquiryId" => customEncrypt($proposalData->user_product_journey_id),
            "mode" => 'pan_number',
        ];
        $ckycController = new CkycController;
        $ckyc_response = $ckycController->ckycVerifications(new Request($request_data));
        $ckyc_response = $ckyc_response->getOriginalContent();
        if ($ckyc_response['data']['verification_status'] == true) {
            return response()->json([
                'status' => true,
                'ckyc_status' => true,
                'msg' => 'Proposal Submited Successfully..!',
                'webservice_id' => $webserviceData['webservice_id'],
                'table' => $webserviceData['table'],
                'data' => [
                    'verification_status' => true,
                    'proposalId' => $proposalData->user_proposal_id,
                    'userProductJourneyId' => $proposalData->user_product_journey_id,
                    'proposalNo' => $proposalSubmitResponse['proposal_no'],
                    'finalPayableAmount' => $proposalData->final_payable_amount,
                    'is_breakin' => $is_breakin_case,
                    'isBreakinCase' => $is_breakin_case,
                    'inspection_number' => (isset($proposalSubmitResponse['ticket_number']) ? $proposalSubmitResponse['ticket_number'] : ''),
                    'kyc_verified_using' => $ckyc_response['ckyc_verified_using'],
                    'kyc_status' => true
                ],
            ]);
        } else {
            if(!empty($ckyc_response['data']['otp_id'] ?? '')) {
                return response()->json([
                    "status" => true,
                    "message" => "OTP Sent Successfully!",
                    "data" => [
                        "verification_status" => false,
                        "message" => "OTP Sent Successfully!",
                        'otp_id' => $ckyc_response['data']['otp_id'],
                        'is_breakin' => 'N',//$is_breakin_case,
                        'isBreakinCase' => 'N',//$is_breakin_case,
                        'kyc_status' => false
                    ]
                ]);
            }
            return response()->json([
                'status' => false,
                'ckyc_status' => false,
                'msg' => $ckyc_response['data']['message'] ?? 'Something went wrong while doing the CKYC. Please try again.',
            ]);
        }
    }

    public static function createTataBreakindata($proposalData, $ticketNumber)
    {

        UserProposal::where(['user_proposal_id' => $proposalData->user_proposal_id])
            ->update([
                'is_breakin_case' => 'Y'
            ]);
        
        CvBreakinStatus::updateOrCreate(
            ['user_proposal_id'  => $proposalData->user_proposal_id],
            [
                'ic_id'             => $proposalData->ic_id,
                'breakin_number'    => $ticketNumber,
                'breakin_id'        => $ticketNumber,
                'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                'created_at'        => Carbon::today()->toDateString()
            ]
        );
        updateJourneyStage([
            'user_product_journey_id' => $proposalData->user_product_journey_id,
            'ic_id' => $proposalData->ic_id,
            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
            'proposal_id' => $proposalData->user_proposal_id
        ]);
    }


}