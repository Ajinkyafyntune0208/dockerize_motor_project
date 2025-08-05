<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\QuoteLog;
use App\Http\Controllers\Proposal\Services\Car\tataAigV2SubmitProposal as TATA_AIG; 


include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

function getV2Quote($enquiryId, $requestData, $productData)
{
    
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
   

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_od          = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);
    $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = ($requestData->business_type != "breakin" ? false : true);

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);
    $zero_dep_age_available = true;
    $noPrevPolicy = ($requestData->previous_policy_type == 'Not sure');


    if (in_array($requestData->previous_policy_type, ['Not sure']) && $requestData->business_type != 'newbusiness') {
        // $BusinessType = '6';//6 means ownership change
        $isPreviousPolicyDetailsAvailable = false;
        $NCBEligibilityCriteria = '1';
        $PreviousNCB = '0';
        $IsNCBApplicable = 'false';
    }

    if(in_array($requestData->previous_policy_type, ['Not sure']))
    {
        $isPreviousPolicyDetailsAvailable = false;
        $previous_insurance_details = ['IsPreviousPolicyDetailsAvailable' => 'false'];
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
 
    $mmv = get_mmv_details($productData, $requestData->version_id,'tata_aig_v2');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return    [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request'=>[
               'mmv'=> $mmv,
               'version_id'=>$requestData->version_id
            ]
        ];
    }
    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ]);
    } elseif ($mmv->ic_version_code == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ]);
    }

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
    

    if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
        $addons = $selected_addons->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ?'5': '1';

            }
        }   
    }

    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure :'5'; 
        }
        else{
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure :'1';
        }
    }
    $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

    $Electricalaccess = $ElectricalaccessSI = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $PAforaddionaldPassenger = $PAforaddionaldPassengerSI = $externalCNGKITSI = $NonElectricalaccess =  $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $llpaidDriver = $llpaidDriverSI = $is_voluntary_access =  "N";

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

     if ($value['name'] == 'Zero Depreciation' && ($is_new ) && $productData->zero_dep == '0') {
         $applicableAddon['DepreciationReimbursement'] = "true";
     }

     if (!$is_liability && in_array('Emergency Medical Expenses', $value)) {
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "true";
    }

 }

 if ($is_new || $requestData->applicable_ncb < 25) { //NCB protection cover is not allowed for NCB less than or equal to 20%
     $applicableAddon['NCBProtectionCover'] = "true";
 }



    foreach ($accessories as $key => $value) {

        if (in_array('Electrical Accessories', $value)) {
            $Electricalaccess = "true";
            $ElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value)) {
            $NonElectricalaccess = "true";
            $NonElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKIT = "Yes";
            $externalCNGKITSI = $value['sumInsured'];
            if ($mmv->txt_fuel != ' External CNG' || $mmv->txt_fuel != ' External LPG') {
                $mmv->txt_fuel = 'External CNG';
                $mmv->txt_fuelcode = '5';
            }
        }

        if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
            $PAPaidDriverConductorCleaner = "Y";
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
    }

    

    $countries = [];
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
        ///added 
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

    foreach ($discounts as $key => $data) {
        if ($data['name'] == 'anti-theft device' && !$is_liability) {
            $is_anti_theft = 'true';
            $is_anti_theft_device_certified_by_arai = 'true';
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
            $is_voluntary_access = 'Y';
            $voluntary_excess_amt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od) {
            $is_tppd = 'true';
            $tppd_amt = '9999';
        }
    }

    // addon

    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = $interval->y;//floor($age / 12);

    if(!$is_liability && ($requestData->previous_policy_type == 'third_party'))
    {

        return ['premium_amount' => 0, 'status' => true, 'message' => 'Liability policies can be renew offline only.'];
    }

    $rollover_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 8 months - 15 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            !(strtotime($rollover_age_limit) <= strtotime($requestData->vehicle_register_date))
    )
    {
        if(!$is_liability)
        {
            return [
                'premium_amount' => 0,
                'status' => true,
                'message' => 'Bike Age greater than 15 years 8 months 28 days',
                'request' => [
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'rollover_age_limit' => $rollover_age_limit
                ]
            ];
        }
    }
    
    $tp_rollover_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 8 months - 20 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            !(strtotime($tp_rollover_age_limit) <= strtotime($requestData->vehicle_register_date))
    )
    {
        return [
            'premium_amount' => 0,
            'status' => true,
            'message' => 'Bike Age greater than 20 years 8 months 28 days',
            'request' => [
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'rollover_age_limit' => $tp_rollover_age_limit
            ]
        ];
    }

    if ($vehicle_age > 20) {
        return ['premium_amount' => 0, 'status' => false, 'message' => 'Bike Age greater than 20 years'];
    }

    $zero_dep_age_limit = !$is_new ? date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' - 28 days - 8 months - 3 year')) : date('d-m-Y');
    if (
        !$is_new
        &&
            strtotime($zero_dep_age_limit) > strtotime($requestData->vehicle_register_date)
    )
    {
        if($is_zero_dep && $vehicle_age > 5)
        {
            return [
                'premium_amount' => 0,
                'status' => true,
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                'request' => [
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'zero_dep_age_limit' => $zero_dep_age_limit
                ]
            ];
        }
        $zero_dep_age_available = false;
    }

    if (!$requestData->applicable_ncb > 25) {
        $NCBProtectionCover = "N";
    }

    

    $vehicle_in_90_days = 0;

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    $current_date = date('Y-m-d');

    if($is_new){
        $policy_start_date = date('d-m-Y', strtotime($requestData->vehicle_register_date));
        if($is_liability){
            $policy_start_date = date('d-m-Y', strtotime($requestData->vehicle_register_date . '+ 1day'));
            
        }
        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
    }
    else
    {
        $policy_start_date = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
    
        if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date))
        {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));
        }

        $policy_end_date    = date('Ymd', strtotime($policy_start_date . ' - 1 days + 1 year'));
    }

 
    $policy_start_date = date('Y-m-d', strtotime($policy_start_date));

    $mmv_data = [
        'manf_name' => $mmv->make,
        'model_name' => $mmv->txt_model,
        'version_name' => $mmv->txt_model_variant,
        'seating_capacity' => $mmv->num_seating_capacity,
        'carrying_capacity' => $mmv->num_seating_capacity - 1,
        'cubic_capacity' => $mmv->num_cubic_capacity,
        'fuel_type' =>    $requestData->fuel_type,
        'gross_vehicle_weight' => $mmv->num_gross_vehicle_weight,
        'vehicle_type' => 'BIKE',
        'version_id' => $mmv->ic_version_code,
        'kw' => $mmv->num_cubic_capacity,
    ];

    $prevPolyStartDate = '';
    if (in_array($requestData->previous_policy_type, ['Comprehensive', 'Third-party', 'Own-damage'])) {
        $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('Y-m-d');
    }

    if ($requestData->prev_short_term == "1") {
        $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subMonth(3)->addDay(1)->format('Y-m-d');
    } 
    $customer_type = $requestData->vehicle_owner_type == "I" ? "Individual" : "Organization";

    $btype_code = ($requestData->business_type == "rollover" || $is_breakin) ? "2" : "1";
    $btype_name = ($requestData->business_type == "rollover" || $is_breakin) ? "Roll Over" : "New Business";

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
        $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
    } else {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }

    if($zero_dep_age_available){
        $ReturnToInvoice = 'true';
        $RoadsideAssistance = 'true';
    }

    $ConsumablesExpenses = ($vehicle_age < 4) ?  'Y' : 'N';

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
 
    $is_pos = config('constants.motorConstant.IS_POS_ENABLED');
    $pos_testing_mode =  config('constants.motor.constants.IcConstants.tata_aig_v2.IS_POS_TESTING_MODE_ENABLE_TATA_AIGV2');

    $pos_aadhar = '';
    $pos_pan    = '';
    $sol_id     = '';//config('constants.IcConstants.tata_aig.SOAL_ID');
    $is_posp = 'N';
    $q_office_location = 0;

    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
    ->where('seller_type','P')
    ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
    {
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
        }else
        {
            
            if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_POS_TO_NON_POS') == 'N')
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Pos unique id for agent id - '.$pos_data->agent_id.' is not available in TATA IC relationship table',
                ];

            }
        }
    }
    elseif($pos_testing_mode == 'Y')
    {
        $is_posp = 'Y';
        $sol_id  = config('IC.TATA_AIG.V2.BIKE.SOL_ID'); //55552
        $q_office_location = config('IC.TATA_AIG.V2.BIKE.Q_OFFICE_LOCATION'); //90300
    }
    else
    {
        $is_pos = 'N';
    }
    $rto_code = $requestData->rto_code;  
    $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code 
    $rto_location = DB::table('tata_aig_vehicle_rto_location_master')->where('txt_rto_location_code', $rto_code)->first();

    
    $token_response = tata_aigv2::getToken($enquiryId, $productData, 'quote');
  

    if(!$token_response['status'])
    {
       
        $token_response['product_identifier'] = $masterProduct->product_identifier;
        return $token_response;
    }

    $rto_data = DB::table('tata_aig_v2_rto_master')
    ->where('txt_rto_code', str_replace('-', '',RtoCodeWithOrWithoutZero($requestData->rto_code,true)))
    ->first();

if (empty($rto_data)) {
    return [
        'premium_amount' => 0,
        'status' => false,
        'message' => 'RTO Data Not Found',
        'request' => [
            'message' => 'RTO Data Not Found',
            'rto_data' => $rto_data,
            'txt_rto_code' => $rto_code[0] . $rto_code[1]
        ]
    ];
}

    $customer_type = $is_individual ? "Individual" : "Organization";
       $special_regno = "false";
       if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null) {
           $pattern = '/^[A-Z]{2}-[0-9]{2}--[0-9]+$/';
           if (preg_match($pattern, $requestData->vehicle_registration_no)) {
               $special_regno = "true";
           } else {
               $special_regno = "false";
           }
       }



    $applicableAddon['RoadsideAssistance'] = "true";  
    $applicableAddon['ConsumablesExpenses'] = "true";
    $applicableAddon['TyreSecure'] = "false";
    $applicableAddon['EngineSecure'] = "false";
    $applicableAddon['ReturnToInvoice'] = "true";
    $applicableAddon['KeyReplacement'] = "false";
    $applicableAddon['EmergTrnsprtAndHotelExpense'] = "true";
    $applicableAddon['LossOfPersonalBelongings'] = "false";
    $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "true";
    $applicableAddon['DepreciationReimbursement'] = "true";
    $applicableAddon['NCBProtectionCover'] = 'false';
   
 
    if ($productData->product_identifier == "basic") {
        $applicableAddon['RoadsideAssistance'] = "false";
        $applicableAddon['ConsumablesExpenses'] = "false";
        $applicableAddon['TyreSecure'] = "false";
        $applicableAddon['EngineSecure'] = "false";
        $applicableAddon['ReturnToInvoice'] = "false";
        $applicableAddon['KeyReplacement'] = "false";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "false";
        $applicableAddon['LossOfPersonalBelongings'] = "false";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "false";
        $applicableAddon['NCBProtectionCover'] = 'false';
    }
    elseif ($productData->product_identifier == "basic_withaddons") {   // basic with addon expect zero_dep   
        $applicableAddon['RoadsideAssistance'] = "true";
        $applicableAddon['ConsumablesExpenses'] = "true";
        $applicableAddon['TyreSecure'] = "false";
        $applicableAddon['EngineSecure'] = "false";
        $applicableAddon['ReturnToInvoice'] = "true";
        $applicableAddon['KeyReplacement'] = "false";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "true";
        $applicableAddon['LossOfPersonalBelongings'] = "false";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "true";
        $applicableAddon['DepreciationReimbursement'] = "false";
        $applicableAddon['NCBProtectionCover'] = 'false';
    }
    elseif ($productData->product_identifier == "zerodept_withaddons") {   // basic with addon expect zero_dep   
        $applicableAddon['RoadsideAssistance'] = "true";
        $applicableAddon['ConsumablesExpenses'] = "true";
        $applicableAddon['TyreSecure'] = "false";
        $applicableAddon['EngineSecure'] = "false";
        $applicableAddon['ReturnToInvoice'] = "true";
        $applicableAddon['KeyReplacement'] = "false";
        $applicableAddon['EmergTrnsprtAndHotelExpense'] = "true";
        $applicableAddon['LossOfPersonalBelongings'] = "false";
        $applicableAddon['RepairOfGlasPlastcFibNRubrGlas'] = "true";
        $applicableAddon['DepreciationReimbursement'] = "true";
        $applicableAddon['NCBProtectionCover'] = 'false';
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
        'pa_owner'                      => ($is_individual && !$is_od) ? 'true' : 'false',
        'pa_owner_declaration'          => 'None',
        'pa_owner_tenure'               => ($is_individual && !$is_od && isset($cpa_tenure))? $cpa_tenure : '',
        "driver_age" =>"",
        "driver_gender" =>"",
        "driver_occupation" =>"",
        'claim_last'                    => ($is_new ? 'false' : (($requestData->is_claim == 'N' || $is_liability) ? 'false' : 'true')),
        'claim_last_amount'             => '',
        'claim_last_count'              => '',
        "pre_pol_ncb" => ((($is_new && $noPrevPolicy) || ($requestData->is_claim == 'Y')) ? '' : (($is_liability) ? '0' : $requestData->previous_ncb)),
        "pre_pol_protect_ncb" => "NA",
        "proposer_salutation" =>"Mr",
        "proposer_first_name" =>"Test",
        "proposer_last_name" =>"Test",
        "proposer_email" =>"",
        "proposer_mobile" =>"",
        "no_past_pol" =>"N",
        "prev_pol_end_date" => (($is_new || $noPrevPolicy) ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('Y-m-d')),
        "prev_pol_start_date" => (($is_new || $noPrevPolicy) ? '' : $prevPolyStartDate),
        "prev_pol_type" => (($is_new || $noPrevPolicy) ? '' : ((in_array($requestData->previous_policy_type, ['Comprehensive'])) ? 'Package (1 year OD + 5 Year TP)' : 'LiabilityOnly')),
        "rtn_invoice" =>  $applicableAddon['ReturnToInvoice'],
        "cng_lpg" =>"false",
        "dep_reimb" =>"true",
        "special_regno" => $special_regno,
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
        "variant_code"                  => (string)$mmv->num_model_variant_code,
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
        "dep_reimburse_deductible"=> $productData->product_identifier == "basic"  || $productData->product_identifier == "basic_withaddons"  ? "" : "0",
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
    
    if ($is_posp == "Y") {
        $input_array_info['is_posp'] = $is_posp;
        $input_array_info['sol_id'] = $sol_id;
        $input_array_info['q_agent_pan'] = $sol_id;
        $input_array_info['q_office_location'] = $q_office_location;
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
        $input_array_info['no_past_policy'] = 'true';

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
        $input_array_info['dep_reimb'] = "true";
     

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

    if($requestData->business_type =="breakin"  &&  $premium_type == "third_party_breakin" || $requestData->business_type =="rollover"  &&  $premium_type == "third_party_breakin"  )
    {
 
        $input_array_info['antitheft_cover'] = "false";
        $input_array_info['plan_type'] = "Standalone TP (1 year)";
        $input_array_info['pol_end_date'] = "";
        $input_array_info['pol_plan_id'] = "3";
        $input_array_info['business_type'] = "Used Vehicle";
        $input_array_info['non_electrical_acc'] = "false";
        $input_array_info['non_electrical_si']= "" ;
        $input_array_info['non_electrical_des']= "" ;
        $input_array_info['electrical_acc']= "false" ;
        $input_array_info['electrical_si']= "" ;
        $input_array_info['electrical_des']= "" ;
    }




  
   
    
    $input_array = [
        "functionality" => "validatequote",
        "quote_type" => "quick",
        "vehicle" => $input_array_info,
        "cover" => [
            "C1" => [
                "opted" => (($is_liability) ? 'N' : 'Y')
            ],
            "C2" => [
                "opted" => ($is_od ? 'N' : 'Y')
            ],
            "C3" => [
                "opted" => (($is_individual && !$is_od) ? 'Y' : 'N')
            ]
        ]
    ];
  

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
        'method'            => 'Premium Calculation',
        'transaction_type'  => 'quote',
        'productName'       => $productData->product_name,
        'token'             => $token_response['token'],
    ];

   
    $get_response = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_QUOTE'), $input_array_info, 'tata_aig_v2', $additional_data);

    if(isset($cpa_tenure))
    {
    if($requestData->business_type == 'newbusiness' && $cpa_tenure=='5'){
        $input_array_info['pa_owner_tenure'] = '5';
        $additional_data['productName'] = $productData->product_name." CPA 5 Year";
        $get_response_cpa = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_QUOTE'), $input_array_info, 'tata_aig_v2', $additional_data);
        $cpa_multiyear = json_decode($get_response_cpa['response'], true);
    }
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
    if(!isset($response['status']) || $response['status'] != 200)
    {
        return camelCase([
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => $response['message_txt'] ?? $response['message'] ?? 'Insurer Not Reachable'
        ]);
    }
    
    $skip_second_call = false;
   

    if($response == NULL){
        return camelCase([
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Insurer Not Reachable',
        ]);
    }

    if(!isset($response['data'])){
        return camelCase([
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => $response['message_txt'],
        ]);
    }


    if($response){
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quotation converted to proposal successfully", "Success" );
    }else{
        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], $response['message_txt'], "Failed" );
    }

    $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
    ->first();


    $idv            =  $response['data']['data']['vehicle_idv'];
  
    $input_array_info['vehicle_idv'] = (string)($idv);
   

    //full quote service input
    $input_array = [
        "functionality" => "validatequote",
        "quote_type" => "full",
        "vehicle" => $input_array_info,
        "cover" => [
            "C1" => [
                "opted" => (($is_liability) ? 'N' : 'Y')
            ],
            "C2" => [
                "opted" => ($is_od ? 'N' : 'Y')
            ],
            "C3" => [
                "opted" => (($is_individual && !$is_od) ? 'Y' : 'N')
            ],
            "C4" => [
                "opted" => (!$is_liability ? $Electricalaccess : 'N'),
                "SI"    => (!$is_liability ? $ElectricalaccessSI : '0')
            ],
            "C5" => [
                "opted" => (!$is_liability ? $NonElectricalaccess : 'N'),
                "SI"    => (!$is_liability ? $NonElectricalaccessSI : '0')
            ],
            "C6" => [
                "opted" => "N",
                "SI" => ""
            ],
            "C7" => [
                "opted" => "N",
                "SI" => ""
            ],
            "C8" => [
                "opted" => "N"
            ],
            "C10" => [
                "opted" => 'N',//$is_voluntary_access,
                "SI" => ''
            ],
            "C11" => [
                "opted" => $is_anti_theft
            ],
            "C12" => [
                "opted" => $is_tppd
            ],
            "C13" => [
                "opted" => "N"
            ],
            "C14" => [
                "opted" => "N"
            ],
            "C15" => [
                "opted" => "N",
                "perc" => ""
            ],
            "C17" => [
                "opted" => (!$is_od ? $PAforUnnamedPassenger : 'N'),
                "SI" => (!$is_od ? $PAforUnnamedPassengerSI : '0'),
                "persons" => (!$is_od ? $mmv->num_seating_capacity : '0')
            ],
            "C18" => [
                "opted" => (!$is_od ? $llpaidDriver : 'N'),
                "persons" => (!$is_od ? '1' : '0'),
            ],
            "C29" => [
                "opted" => "N",
            ],
            "C35" => [
                "opted" => $DepreciationReimbursement,
                "no_of_claims" => $NoOfClaimsDepreciation,
                "Deductibles" => "0"
            ],
            "C37" => [
                "opted" => $ConsumablesExpenses,
            ],
            "C38" => [
                "opted" => $ReturnToInvoice,
            ],
            "C39" => [
                "opted" => "N",
            ],
            "C40" => [
                "opted" => "N",
            ],
            "C41" => [
                "opted" => "N",
            ],
            "C42" => [
                "opted" => "N"
            ],
            "C43" => [
                "opted" => "N",
            ],
            "C44" => [
                "opted" => "N",
            ],
            "C45" => [
                "opted" => "N",
            ],
            "C47" => [
                "opted" => $RoadsideAssistance
            ],
            "C48" => [
                "opted" => "N", // $EmergTrnsprtAndHotelExpense,
                "SI" => null
            ],
            "C49" => [
                "opted" => 'N',
                "SI" => ''
            ],
            "C50" => [
                "opted" => "N", //$PAforaddionaldPaidDriver,
                "SI" => "", //$PAforaddionaldPaidDriverSI
            ],
            "C51" => [
                "opted" => "N",
                "SI" => NULL,
            ]
        ]
    ];

    // pass idv
    $max_idv    = ($is_liability ? 0 : ($response['data']['data']['max_idv']?? 0)  +  ($quoteResponse['max_idv_chassis'] ?? 0));
 
    $min_idv    = ($is_liability ? 0 : ($response['data']['data']['min_idv'] ?? 0) +  ($quoteResponse['min_idv_chassis'] ?? 0));

    $idv = $is_liability ? 0 : ($input_array_info['vehicle_idv'] ?? 0);

    $skip_second_call = false;

     if ($requestData->is_idv_changed == "Y")
     {
         if ($requestData->edit_idv >= $max_idv)
         {
             $idv = ($max_idv);
         }
         else if ($requestData->edit_idv <= $min_idv)
         {
             $idv = ($min_idv);
         }
         else
         {
             $idv = ($requestData->edit_idv);
         }
     } else {
         $getIdvSetting = getCommonConfig('idv_settings');
         switch ($getIdvSetting) {
             case 'default':
                 $skip_second_call = true;
                 $idv = ($idv);
                 break;
             case 'min_idv':
                 $idv = ($min_idv);
                 break;
             case 'max_idv':
                 $idv = ($max_idv);
                 break;
             default:
                 $idv = ($min_idv);
                 break;
         }
         
     }
     $input_array_info['vehicle_idv'] = (string)($idv);
     $input_array_info['__finalize'] = '1';
     $input_array_info["chassis_idv"] = ($idv);

    $additional_data['method'] = 'Premium Calculation - Full Quote';

    if(!$skip_second_call) {
        $input_array_info['pa_owner_tenure'] = ($is_individual && !$is_od) ? '1' : '';
        $get_response = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_QUOTE'), $input_array_info, 'tata_aig_v2', $additional_data);

        if(isset($cpa_tenure))
        {
        if($requestData->business_type == 'newbusiness' && $cpa_tenure=='5') {
            $input_array_info['pa_owner_tenure'] = '5';
            $additional_data['productName'] = $productData->product_name." CPA 5 Year";
            $get_response_cpa = getWsData(config('IC.TATA_AIG.V2.BIKE.END_POINT_URL_QUOTE'), $input_array_info, 'tata_aig_v2', $additional_data);
            $cpa_multiyear = json_decode($get_response_cpa['response'], true);
        }
    }
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
           
  $applicableAddon['RoadsideAssistance'] = "true";
  $applicableAddon['ConsumablesExpenses'] = "true";


      // pass idv

    if($premium_type == 'third_party_breakin')
    {
        $premium_type = 'third_party';
    }

    

   
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
   
    $anti_theft_amount      = (isset($totalOdPremium['discount_od']['disc_antitheft_prem']) ? $totalOdPremium['discount_od']['disc_antitheft_prem'] : 0);
   
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

    if ($is_zero_dep) {
        $add_on_data = [
            'in_built'   => [
                'zero_depreciation' => $zero_dep_amount
            ],
            'additional' => [
                'road_side_assistance' => $rsa_amount,
                'consumables' => $counsumable_amount

            ],
            'other'      => [
                
            ]
        ];
    } else {
        $add_on_data = [
            'in_built'   => [],
            'additional' => [
                'road_side_assistance' =>  $rsa_amount,
                'consumables' => $counsumable_amount,
                'return_to_invoice'     => $rti_amount,
            ],
            'other'      => [
               
            ]
        ];
      
        if($productData->product_identifier == 'basic')
        {
            $add_on_data = [
                'in_built'   => [],
                'additional' => [],
                'other'      => []
            ];
        }
    }

    if($productData->product_identifier == "zerodept_withaddons")
    {
        $add_on_data = [
            'in_built'   => [  'zero_depreciation' => $zero_dep_amount ,
        ],
            'additional' => [
                'consumables' => $counsumable_amount,
                'road_side_assistance' =>  $rsa_amount,
                'return_to_invoice'     => $rti_amount,           
               
            ],
            'other'      => [
              
            ]
        ];
    }


    $in_built_premium = 0;
    foreach ($add_on_data['in_built'] as $key => $value) {
        if ($value === 0) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'No value for In-Built addon'
            ];
        }
        $in_built_premium = $in_built_premium + $value;
    }

    $applicable_addons = [];
    if($productData->product_identifier != 'short_term_3_month_basic')
{
    if (isset($add_on_data['additional']['consumables']) && $add_on_data['additional']['consumables'] !== 0) {
        array_push($applicable_addons, 'consumables');
    }
    if (isset($add_on_data['additional']['road_side_assistance']) && $add_on_data['additional']['road_side_assistance'] !== 0) {
        array_push($applicable_addons, 'road_side_assistance');
    }
    if (isset($add_on_data['additional']['return_to_invoice']) && $add_on_data['additional']['return_to_invoice'] !== 0) {
        array_push($applicable_addons, 'return_to_invoice');
    }
    if (isset($add_on_data['in_built']['zero_depreciation']) && $add_on_data['in_built']['zero_depreciation'] !== 0) {
        array_push($applicable_addons, 'zero_depreciation');
    }   
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
   
    
    $data_response = [
        'webservice_id' => $get_response['webservice_id'],
        'table' => $get_response['table'],
        'status' => true,
        'msg' => 'Found',
        'premium_type' => $premium_type,
        'Data' => [
          
            'idv' => $premium_type == 'third_party' ? 0 : ($input_array_info['vehicle_idv']),
                            'min_idv' => $premium_type == 'third_party' ? 0 : ($min_idv),
                            'max_idv' => $premium_type == 'third_party' ? 0 : ($max_idv),
            'qdata' => NULL,
            'pp_enddate' => ($is_new ? '' : $requestData->previous_policy_expiry_date),
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
            'voluntary_excess' => isset($response['data']['C10']) ? ($response['data']['C10']['rate']) : 0,
            'version_id' => $requestData->version_id,
            'selected_addon' => [],
            'fuel_type' => $requestData->fuel_type,
            'vehicle_idv' => $premium_type == 'third_party' ? 0 : (int)$input_array_info['vehicle_idv'],
            'ncb_discount' => $requestData->applicable_ncb,
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
                'aai_discount' => 0,
                'ic_vehicle_discount' => '', // ($insurer_discount)
            ],
            'ic_vehicle_discount' => 0,
            'basic_premium' => $basic_od,
            'deduction_of_ncb' => $ncb_discount_amount,
            'tppd_premium_amount' => $basic_tp,
            'seating_capacity' => $mmv->num_seating_capacity,
            'GeogExtension_ODPremium'                     => $od_gio,
            'GeogExtension_TPPremium'                     => $tp_gio,
            'compulsory_pa_own_driver' => $pa_owner,
            'total_accessories_amount(net_od_premium)' => "", //$total_accessories_amount,
            'total_own_damage' => $final_od_premium,
            'total_liability_premium' => $final_tp_premium,
            'net_premium' => $final_tp_premium,        
            'service_tax_amount' => "", //$result['result']['plans'][0]['tenures'][0]['premium']['gst']['value'],
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => '', //$result['result']['plans'][0]['tenures'][0]['premium']['gross']['value'],
            'final_od_premium' => $final_od_premium,
            'final_tp_premium' => $final_tp_premium,
            'final_total_discount' =>    $final_total_discount,
            'final_net_premium' => $netPremium,
            'final_gst_amount' => ($netPremium * 18 / 100),
            'final_payable_amount' => $finalPremium,
            'service_data_responseerr_msg' => '',
            'user_id' => $requestData->user_id,
            'product_sub_type_id' => $productData->product_sub_type_id,
            'user_product_journey_id' => $requestData->user_product_journey_id,
            'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
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

        ]
    ];

    if(isset($cpa_tenure))
    {
    if($requestData->business_type == 'newbusiness' && $cpa_tenure  == '5')
    {
        // unset($data_response['Data']['compulsory_pa_own_driver']);
        $data_response['Data']['multi_Year_Cpa'] =  $pa_owner;
    }
    }


    if($requestData->business_type == 'newbusiness'){
        $cpa_multiyear_prem = isset($cpa_multiyear['data']['premium_break_up']['total_tp_premium']['pa_cover_prem']) ? $cpa_multiyear['data']['premium_break_up']['total_tp_premium']['pa_cover_prem'] : 0;
        $data_response['Data']['multi_year_cpa'] = $cpa_multiyear_prem;
    }

    if($externalCNGKIT == 'Y')
    {
        $data_response['Data']['cng_lpg_tp'] =  $response['data']['C29']['premium'] ?? 0;
    }
   
    if($llpaidDriver == 'true')
    { 
        $data_response['Data']['default_paid_driver'] =  $totalTpPremium['ll_paid_driver_prem'] ?? 0;
     
    }
    if($PAforUnnamedPassenger == 'Y')
    {
        $data_response['Data']['cover_unnamed_passenger_value'] = $response['data']['C17']['premium'] ?? 0;
    }

    if($is_anti_theft == 'true')
    {  
        $data_response['Data']['antitheft_discount'] = $response['data']['premium_break_up']['total_od_premium']['discount_od']['disc_antitheft_prem'] ?? 0;

    }
    if($is_tppd == 'true')
    {   
        $data_response['Data']['tppd_discount'] =  $response['data']['premium_break_up']['total_tp_premium']['disc_tppd_prem'] ?? 0;
        
    }
    if($is_voluntary_access == 'Y')
    {
        $data_response['Data']['voluntary_excess'] = $response['data']['C10']['rate'] ?? 0;
    }
    if($Electricalaccess == 'true')
    {
       
        $data_response['Data']['motor_electric_accessories_value'] =  $totalOdPremium['od']['electrical_prem'] ?? 0;
    }
    if($NonElectricalaccess == 'true')
    {
      
        $data_response['Data']['motor_non_electric_accessories_value'] =  $totalOdPremium['od']['non_electrical_prem'] ?? 0;
    }
    if($externalCNGKIT == 'Y')
    {
        $data_response['Data']['motor_lpg_cng_kit_value'] = $response['data']['C7']['premium'] ?? 0;
    }

    return camelCase($data_response);

}


class tata_aigv2
 {
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

}