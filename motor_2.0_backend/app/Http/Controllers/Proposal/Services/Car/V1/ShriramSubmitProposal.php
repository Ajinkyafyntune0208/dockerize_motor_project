<?php

namespace App\Http\Controllers\Proposal\Services\Car\V1;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ckycUploadDocuments;
use App\Models\ShriramPinCityState;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\Proposal\ProposalController;
use App\Http\Controllers\Proposal\Services\shriramSubmitProposal as ServicesShriramSubmitProposal;
use App\Http\Controllers\SyncPremiumDetail\Car\ShriramPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class ShriramSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

  
    public static function submitV1($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $proposal->gender = (strtolower($proposal->gender) == "male" || $proposal->gender == "M") ? "M" : "F";
        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }

        UserProposal::where('user_product_journey_id', $enquiryId)
        ->update([
            'is_ckyc_verified' => 'N'
        ]);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

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

        $zero_dep = ($productData->zero_dep  == 0) ? true : false;
        $is_od    = (($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false);

        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d/M/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/M/Y');
        $mmv = get_mmv_details($productData,$requestData->version_id,'shriram');
        if($mmv['status'] == 1)
        {
          $mmv = $mmv['data'];
        }
        else
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        /*if (!$ic_version_mapping) {
            return [
                'status' => false,
                'msg' => 'Vehicle does not exist with insurance company'
            ];
        }*/

        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($proposal->prev_policy_expiry_date == 'New' ? date('Y-m-d') : $proposal->prev_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
        //$pkg_selected = ($car_age > 5) ? "ADDON_01" : "ADDON_03";
        $pkg_selected = $productData->zero_dep == '0' ? "ADDON_03" : "ADDON_01";
        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $RSACover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
        // new business
        $additional_details = json_decode($proposal->additional_details);
        $PreviousNilDepreciation = '0'; // addon
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
        if ($requestData->business_type == 'newbusiness') {
            $is_new = true;
            $proposal->previous_policy_number = $proposal->previous_insurance_company = $PreviousPolicyFromDt = $PreviousPolicyToDt = $previous_ncb = $PreviousPolicyType = "";            
            $policy_start_date = today()->format('d-m-Y');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d-m-Y');
            $proposalType = "FRESH";
            $soapAction = "GenerateLTPvtCarProposal";
            $URL = config('IC.SHRIRAM.V1.CAR.PROPOSAL_URL');//constants.motor.shriram.PROPOSAL_URL_JSON
        } else {
            $is_new = false;
            $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d-M-Y');
            $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d-M-Y');
            $proposalType = "RENEWAL OF OTHERS";
            $PreviousPolicyType = "MOT-PLT-001";
            $previous_ncb = $requestData->previous_ncb;
            $soapAction = "GenerateProposal";
            $URL = config('IC.SHRIRAM.V1.CAR.PROPOSAL_URL');//constants.motor.shriram.PROPOSAL_URL_JSON
            $prev_policy_details = isset($additional_details->prepolicy) ? $additional_details->prepolicy:'';
        }

        if($requestData->business_type == 'breakin' && $premium_type == 'third_party'){
            $policy_start_date = date('Y-M-d', strtotime('+2 day', strtotime(date('Y-m-d'))));
            $policy_end_date = date('Y-M-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        $DailyExpRemYN = $InvReturnYN = $KeyReplacementYN = $MultiCarBenefitYN = $EmergencyTranHotelExpRemYN = $LossOfPersonBelongYN = ($pkg_selected == 'ADDON_01' ? 'N' : 'Y' );

        if ($productData->zero_dep == '0') {
            $DepDeductWaiverYN = "N";
            $nilDepreciationCover = 1;
            $PreviousNilDepreciation = 1; // addon
        } else {
            $DepDeductWaiverYN = "Y";
        }

        $LLtoPaidDriverYN = '0';
        $LimitOwnPremiseYN = 'N';

        $Bangladesh="0";
        $Bhutan="0";
        $SriLanka="0";
        $Nepal="0";
        $Maldives="0";
        $Pakistan="0";
        foreach($additional_covers as $key => $value) {
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

            if(in_array('Geographical Extension',$value))
            {
                foreach($value['countries'] as $Countries)
                {
                    if($Countries == 'Bhutan')
                    {
                        $Bhutan = '1';
                    }

                    if($Countries == 'Sri Lanka')
                    {
                        $SriLanka = '1';
                    }

                    if($Countries == 'Nepal')
                    {
                        $Nepal = '1';
                    }

                    if($Countries == 'Bangladesh')
                    {
                        $Bangladesh = '1';
                    }

                    if($Countries == 'Pakistan')
                    {
                        $Pakistan = '1';
                    }

                    if($Countries == 'Maldives')
                    {
                        $Maldives = '1';
                    }
                }
                $LimitOwnPremiseYN = 'Y';
            }
        }
        $consumable = $engine_protection = $InvReturnYN = $KeyReplacementYN = $RSACover = $LossOfPersonBelongYN = 'N';
        $NilDepreciationCoverYN = "NO";
        foreach ($addons as $key => $value) {

            if (in_array('Road Side Assistance', $value)) {
                $RSACover = 'Y';
            }

            if (in_array('Key Replacement', $value) ) {
                $KeyReplacementYN =  'Y' ;
            }

            if (in_array('Return To Invoice', $value) ) {
                $InvReturnYN = 'Y';
            }

            if (in_array('Engine Protector', $value) ) {
                $engine_protection = 'Y';
            }

            if (in_array('Consumable', $value) ) {
                $consumable = 'Y';
            }

            /*if (in_array('car benefit text here', $value)) {
                $MultiCarBenefitYN = "Y";
            }*/

            /*if (in_array('hotel text here', $value)) {
                $EmergencyTranHotelExpRemYN = "Y";
            }*/

            if (in_array('Loss of Personal Belongings', $value) ) {
                $LossOfPersonBelongYN = 'Y';
            }

            if (in_array('Zero Depreciation', $value) ) {
                // 
                $NilDepreciationCoverYN = "YES";
                $PreviousNilDepreciation = 1; // addon
            }

            $pkg_selected = 'ADDON_05';
        }
        if($productData->zero_dep == '0' ) {
            $NilDepreciationCoverYN = "YES";
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
                $externalCNGKIT = 1;
                $externalCNGKITSI = $value['sumInsured'];
            }
        }


        if($premium_type == 'third_party')
        {
            $NilDepreciationCoverYN     = '0';
            $DepDeductWaiverYN          = 'Y';
            $key_rplc_yn                = 'N';
            $consumable                 = '0';
            $Eng_Protector              = '0';
            $pckg_name                  = 'ADDON_01';
        }

        $voluntary_insurer_discounts = 'PCVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'PCVE1', // Discount of 0
            '2500'  => 'PCVE2', // Discount of 750
            '5000'  => 'PCVE3', // Discount of 1500
            '7500'  => 'PCVE4', // Discount of 2000
            '15000' => 'PCVE5'  // Discount of 2500
        ];
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            // As suggested by Paras sir, Disabling Anti Theft - 20-08-2021
            /*if (in_array('anti-theft device', $value)) {
                $antitheft = '1';
            }*/
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)) {
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
        }

        // salutaion
        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $insured_prefix = '1'; // Mr
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '2'; // Mrs
                } else {
                    $insured_prefix = '4'; // Miss
                }
            }
        }
        else{
            $insured_prefix = '3'; // M/S
        }
        // salutaion
        // CPA
        $PAOwnerDriverExclusion = "1";
        $excludeCPA = false;
        $PAOwnerDriverExReason = '';
        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I' && !$is_od) {
            if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
                $PAOwnerDriverExclusion = "0";
                $PAOwnerDriverExReason = "";
            }
            else {
                if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
                    $PAOwnerDriverExReason = "PA_TYPE2";
                    $excludeCPA = true;
                } else {
                    $PAOwnerDriverExReason = "PA_TYPE4";
                }
            }
        } elseif ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
            $PAOwnerDriverExReason = "PA_TYPE1";
            $excludeCPA = true;
        }
        
        
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if (!$is_od && !($PAOwnerDriverExclusion == '0' || $excludeCPA) ) {
            $cPAInsComp = $prev_policy_details->cPAInsComp ?? '';
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo ?? '';
            $cPASumInsured = $prev_policy_details->cPASumInsured ?? '';
            $cPAPolicyFmDt = !empty($prev_policy_details->cPAPolicyFmDt ?? '') ? Carbon::parse(str_replace('/', '-', $prev_policy_details->cPAPolicyFmDt))->format('d-M-Y') : '';
            $cPAPolicyToDt = !empty($prev_policy_details->cPAPolicyToDt ?? '') ? Carbon::parse(str_replace('/', '-', $prev_policy_details->cPAPolicyToDt))->format('d-M-Y') : '';
        }
        // CPA
        // Policy Type

        if ($master_policy->premium_type_id == 1) {
            $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
            $policy_type = 'MOT-PLT-001';
        } else {
            $quote_log->ex_showroom_price_idv = '';
            $policy_type = 'MOT-PLT-002';
        }
        switch ($master_policy->premium_type_id) 
               {
                   case '1':
                       $ProdCode = $is_new ? "MOT-PRD-001" : "MOT-PRD-001";
                       $policy_type = $is_new ? "MOT-PLT-014" : 'MOT-PLT-001';
                       $PreviousPolicyType = $is_new ? '' : 'MOT-PLT-001';
                       $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                       break;
                    case '4':
                        $ProdCode = $is_new ? "MOT-PRD-001" : "MOT-PRD-001";
                        $policy_type = $is_new ? "MOT-PLT-014" :'MOT-PLT-001';
                        $PreviousPolicyType = $is_new ? '' : 'MOT-PLT-001';
                        $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                        break;
                   case '2':
                   case '7':
                       $ProdCode = $is_new ? "MOT-PRD-001" : "MOT-PRD-001";
                       $policy_type = $is_new ? "MOT-PLT-014" :'MOT-PLT-002';
                       $PreviousPolicyType = $is_new ? '' : 'MOT-PLT-002';
                       $quote_log->ex_showroom_price_idv = '';
                       break;
                    case '3':
                       $ProdCode = "MOT-PRD-001";
                       $policy_type = 'MOT-PLT-013';
                       $PreviousPolicyType = 'MOT-PLT-013';
                       $soapAction = "GenerateLTPvtCarProposal";
                       $proposalType = 'RENEWAL OF OTHERS';
                       $tp_start_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
                       $tp_end_date = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
                       $prev_tp_policy_no = '';
                       $prev_tp_comp_name = '';
                       $prev_tp_address = '';
                       #$insured_prefix = '3';
                       #$PAOwnerDriverExReason = "PA_TYPE1";
                       $quote_log->ex_showroom_price_idv = $quote_log->ex_showroom_price_idv;
                       break;
                   
               }

                if($is_od)
                {
                    $tp_start_date = Carbon::parse($additional_details->prepolicy->tpStartDate)->format('d-M-Y');
                    $tp_end_date = Carbon::parse($additional_details->prepolicy->tpEndDate)->format('d-M-Y');
                    $prev_tp_policy_no = $additional_details->prepolicy->tpInsuranceNumber;
                    $prev_tp_comp_name = $additional_details->prepolicy->tpInsuranceCompany;
                    $tp_insurer_address_array = DB::table('insurer_address')->where('Insurer', $additional_details->prepolicy->tpInsuranceCompanyName)->first();
                    $tp_insurer_address_array = keysToLower($tp_insurer_address_array);
                    $prev_tp_address = $tp_insurer_address_array->address_line_1.' '.$tp_insurer_address_array->address_line_2;
                }

        // Policy Type

        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number[0] = '';
        }
        $previous_policy_type = "MOT-PLT-001";
        if ($requestData->previous_policy_type == 'Comprehensive') 
        {
            $previous_policy_type = "MOT-PLT-001";
        }
        elseif ($requestData->previous_policy_type == 'Own-damage')
        {
            $previous_policy_type = 'MOT-PLT-013';
        }
        elseif ($requestData->previous_policy_type == 'Third-party')
        {
            $previous_policy_type = 'MOT-PLT-002';
        }
        
        if($premium_type == 'own_damage')
        {
            $previous_policy_type = 'MOT-PLT-014';
            if ($requestData->previous_policy_type == 'Comprehensive') {
                $previous_policy_type = 'MOT-PLT-014';
            } elseif ($requestData->previous_policy_type == 'Own-damage') {
                $previous_policy_type = 'MOT-PLT-013';
            } elseif ($requestData->previous_policy_type == 'Third-party') {
                $previous_policy_type = 'MOT-PLT-002';
            }

        }
       

        //Hypothecation
        $HypothecationType = $HypothecationBankName = $HypothecationAddress1 = $HypothecationAddress2 = $HypothecationAddress3 = $HypothecationAgreementNo = $HypothecationCountry = $HypothecationState = $HypothecationCity = $HypothecationPinCode = '';
        $vehicleDetails = $additional_details->vehicle;
        
        if ($vehicleDetails->isVehicleFinance == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType;
            $HypothecationBankName = $vehicleDetails->nameOfFinancer;
            $HypothecationAddress1 = $vehicleDetails->hypothecationCity;
            $HypothecationAddress2 = '';
            $HypothecationAddress3 = '';
            $HypothecationAgreementNo = '';
            $HypothecationCountry = '';
            $HypothecationState = '';
            $HypothecationCity = $vehicleDetails->hypothecationCity;
            $HypothecationPinCode = '';
        }
        //Hypothecation

        $rto_code = $quote_log->premium_json['rtoNo'];

        // state_code
        $state_code = ShriramPinCityState::where('pin_code', $proposal->pincode)->first()->state;
        // state_code
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posp_name = '';
        $posp_pan_number = '';
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
    
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->idv <= 5000000) {
            if($pos_data) {
                $posp_name = $pos_data->agent_name;
                $posp_pan_number = $pos_data->pan_no;
            }
        }elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_SHRIRAM') == 'Y' && $quote_log->idv <= 5000000){
            $posp_name = 'Ravindra Singh';
            $posp_pan_number = 'DNPPS5548E';
        }
        $tp_only = ($premium_type == 'third_party') ? true : false;
        if($RSACover == 'Y'){
            if ($tp_only) {
    
                $RSACover = 'N';
            } else {
                $RSACover =  'Y';//($car_age < 12 ? 'Y':'N');//'Y';
    
            }
        }else{
            $RSACover = 'N';
        }
        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 60,
            'address_2_limit'   => 20,         
            'address_3_limit'   => 20,         
        ];

        $getAddress = getAddress($address_data);
        
        $input_array = [
            "objPolicyEntryETT" => [
                "ReferenceNo" => "",
                "ProdCode" => $ProdCode,
                "PolicyFromDt" => str_replace("/","-",$policy_start_date),//"25-Jan-2022",
                "PolicyToDt" =>  str_replace("/","-",$policy_end_date),//"24-Jan-2023",
                "PolicyIssueDt" => today()->format('d-M-Y'),//"22-Jan-2022",
                "InsuredPrefix" => ($requestData->vehicle_owner_type == 'I') ? "1" : "3",
                "InsuredName" => $proposal->first_name . '' .  $proposal->last_name,//"Amar",
                "Gender" => ($requestData->vehicle_owner_type == 'I') ? $proposal->gender : "", //"M",
                "Address1" => empty(trim($getAddress['address_1'])) ? "." : trim($getAddress['address_1']),
                "Address2" => empty(trim($getAddress['address_2'])) ? "." : trim($getAddress['address_2']),
                "Address3" => empty(trim($getAddress['address_3'])) ? "." : trim($getAddress['address_3']),
                "State" => explode('-', $rto_code)[0], //"TN",
                "City" => $proposal->city, //"Erode",
                "PinCode" => $proposal->pincode,
                "PanNo" => null,
                "GSTNo" => null,
                "TelephoneNo" => "",
                "ProposalType" => $proposalType, //"Renewal",
                "PolicyType" => $policy_type, //"MOT-PLT-001",
                "DateOfBirth" =>  ($requestData->vehicle_owner_type == 'I') ? 
                date("d-M-Y",strtotime($proposal->dob)) : "",
                "MobileNo" => $proposal->mobile_number, //"9626616284",
                "FaxNo" => "",
                "EmailID" => $proposal->email, //"Gopi@testmail.com",
                "POSAgentName" => $posp_name, //"Gopi",
                "POSAgentPanNo" => $posp_pan_number, //"12344",
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                "VehicleCode" =>  $mmv_data->veh_code, //"M_10075",
                "FirstRegDt" => date('d-M-Y', strtotime($requestData->vehicle_register_date)), //"10/07/2021", //,"06-09-2016",
                "VehicleType" => $is_new ? "W" : "U",//"U",
                "EngineNo" => $proposal->engine_number,//Str::upper(Str::random(8)),
                "ChassisNo" => $proposal->chassis_number,//Str::upper(Str::random(12)),
                "RegNo1" => explode('-', $rto_code)[0],
                "RegNo2" => explode('-', $rto_code)[1],
                "RegNo3" => !empty($vehicale_registration_number[2]) ? substr($vehicale_registration_number[2], 0, 3) : 'TT', // "OK",
                "RegNo4" => $vehicale_registration_number[3] ?? '4521', // "4521",
                "RTOCode" => $rto_code, // "MH-01",
                "IDV_of_Vehicle" => $quote_log->idv,
                "Colour" => "RED",
                "VoluntaryExcess" => ($premium_type == 'third_party') ? 0 :$voluntary_insurer_discounts,//$BusinessType == "2" ? "PCVE1" : "PCVE2", //"MOT-DED-002", $voluntary_insurer_discounts,
                "NoEmpCoverLL" => "0",
                "NoOfCleaner" => "",
                "NoOfDriver" => "1",
                "NoOfConductor" => "",
                "VehicleMadeinindiaYN" => "",
                "VehiclePurposeYN" => "",
                "NFPP_Employees" => "",
                "NFPP_OthThanEmp" => "",
                "LimitOwnPremiseYN" => "",
                "Bangladesh" => $Bangladesh,
                "Bhutan" => $Bhutan,
                "SriLanka" => $SriLanka,
                "Nepal" => $Nepal,
                "Pakistan" => $Pakistan,
                "Maldives" => $Maldives,
                "CNGKitYN" => $externalCNGKIT,
                "CNGKitSI" =>$externalCNGKITSI,
                "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? "1" : "0",
                // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                "DeTariff" => 0,
                "IMT23YN" => "",
                "BreakIn" => "No",
                "PreInspectionReportYN" => "0",
                "PreInspection" => "",
                "FitnessCertificateno" => "",
                "FitnessValidupto" => "",
                "VehPermit" => "",
                "PermitNo" => "",
                "PAforUnnamedPassengerYN" => $PAforUnnamedPassenger,
                "PAforUnnamedPassengerSI" => $PAforUnnamedPassengerSI,
                "ElectricalaccessYN" => ($premium_type == 'third_party') ? 0 :$Electricalaccess,
                "ElectricalaccessSI" => ($premium_type == 'third_party') ? 0 :$ElectricalaccessSI,
                "ElectricalaccessRemarks" => "electric",
                "NonElectricalaccessYN" => ($premium_type == 'third_party') ? 0 :$NonElectricalaccess,
                "NonElectricalaccessSI" => ($premium_type == 'third_party') ? 0 :$NonElectricalaccessSI,
                "NonElectricalaccessRemarks" => "non electric",
                "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleaner,
                "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                "PAPaidDriverCount" => "0",
                "PAPaidConductorCount" => "",
                "PAPaidCleanerCount" => "",
                "NomineeNameforPAOwnerDriver" => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                "NomineeAgeforPAOwnerDriver" => $proposal->nominee_age == null ? '0' : $proposal->nominee_age,
                "NomineeRelationforPAOwnerDriver" => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                "AppointeeNameforPAOwnerDriver" => "",
                "AppointeeRelationforPAOwnerDriver" => "",
                "LLtoPaidDriverYN" => $LLtoPaidDriverYN,
                "AntiTheftYN" => $antitheft,
                "PreviousPolicyNo" => $is_new ? "" : $proposal->previous_policy_number,
                "PreviousInsurer" => $is_new ? "" : $proposal->previous_insurance_company,
                "PreviousPolicyFromDt" => $PreviousPolicyFromDt,//"25-JAN-2021",
                "PreviousPolicyToDt" => $PreviousPolicyToDt,//"24-JAN-2022",
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0', 
                "PreviousPolicyUWYear" => "",
                //"PreviousPolicyNCBPerc" => $requestData->is_claim == 'Y' ? '' : $previous_ncb,
                "PreviousPolicyNCBPerc" => $previous_ncb,
                "PreviousPolicyType" => $previous_policy_type,
                'AddonPackage' => $pkg_selected,
                "NilDepreciationCoverYN" => $NilDepreciationCoverYN,// "No",
                "PreviousNilDepreciation" => "1",
                "RSACover" => $RSACover,//($car_age < 12 ? $RSACover:'0'),  //($premium_type == 'third_party') ? 'N' :$RSACover,
                "LossOfPersonBelongYN" =>($premium_type == 'third_party') ? 'N' : $LossOfPersonBelongYN,
                "Consumables" => ($premium_type == 'third_party') ? 'N' :$consumable,
                "Eng_Protector" => ($premium_type == 'third_party') ? 'N' :$engine_protection,
                "DepDeductWaiverYN"=>$productData->zero_dep  == 0 ? 'Y' : 'N',
                "InvReturnYN" => ($premium_type == 'third_party') ? 'N' :$InvReturnYN,
                "KeyReplacementYN" => ($premium_type == 'third_party') ? 'N' :$KeyReplacementYN,
                "NCBPROTECTIONPREMIUM" => ($premium_type == 'third_party') ? 'N' :"Y",
                "HypothecationType" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationType : '',
                "HypothecationBankName" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationBankName :'',
                "HypothecationAddress1" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationAddress1 : '',
                "HypothecationAddress2" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationAddress2 : '',
                "HypothecationAddress3" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationAddress3 : '',
                "HypothecationAgreementNo" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationAgreementNo : '',
                "HypothecationCountry" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationCountry : '',
                "HypothecationState" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationState : '',
                "HypothecationCity" =>  ($vehicleDetails->isVehicleFinance == true) ? $vehicleDetails->hypothecationCity :'',
                "HypothecationPinCode" => ($vehicleDetails->isVehicleFinance == true) ? $HypothecationPinCode : '',
                "SpecifiedPersonField" => "",
                "PAOwnerDriverExclusion" => ($requestData->vehicle_owner_type == 'I') ? $PAOwnerDriverExclusion : '1',//$PAOwnerDriverExclusion,
                "PAOwnerDriverExReason" => ($requestData->vehicle_owner_type == 'I') ? $PAOwnerDriverExReason : 'PA_TYPE1',//$PAOwnerDriverExReason,
                "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                'CPAInsComp'                => $cPAInsComp,
                'CPAPolicyNo'               => $cPAPolicyNo,
                'CPASumInsured'             => $cPASumInsured,
                'CPAPolicyFmDt'             => $cPAPolicyFmDt,
                'CPAPolicyToDt'             => $cPAPolicyToDt,
                "VehicleManufactureYear" => $manufacture_year,
            ],
        ];

        if($premium_type == 'own_damage')
        {
            $input_array['objPolicyEntryETT']['tpPolAddr'] = $prev_tp_address ;
            $input_array['objPolicyEntryETT']['tpPolComp'] = $prev_tp_comp_name ;
            $input_array['objPolicyEntryETT']['tpPolFmdt'] = $tp_start_date;
            $input_array['objPolicyEntryETT']['tpPolTodt'] = $tp_end_date;
            $input_array['objPolicyEntryETT']['tpPolNo'] = $prev_tp_policy_no;
        }
       
        $URL = config('IC.SHRIRAM.V1.CAR.PROPOSAL_URL');//constants.motor.shriram.PROPOSAL_URL_JSON

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' =>  [
                'Username' => config('IC.SHRIRAM.V1.CAR.USERNAME'),
                'Password' => config('IC.SHRIRAM.V1.CAR.PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Car',
            'method' => 'Premium Calculation',
            'transaction_type' => 'proposal',
            'productName' => $productData->product_name . ($zero_dep ? ' (zero_dep)' : ''),
        ];
        
        if(config('constants.IS_CKYC_ENABLED') == 'Y') {

            $userProductJouney=UserProductJourney::where('user_product_journey_id',$enquiryId)->first();
                $input_array['objPolicyEntryETT']['CKYC_NO']='';
                $input_array['objPolicyEntryETT']['DOB']=$proposal->dob ? date('d-m-Y',strtotime($proposal->dob)) : '';
                $input_array['objPolicyEntryETT']['POI_Type']='';
                $input_array['objPolicyEntryETT']['POI_ID']='';
                $input_array['objPolicyEntryETT']['POA_Type']='';
                $input_array['objPolicyEntryETT']['POA_ID']='';
                $input_array['objPolicyEntryETT']['FatherName']=$proposal->proposer_ckyc_details->related_person_name ?? '';
                $input_array['objPolicyEntryETT']['POI_DocumentFile']='';
                $input_array['objPolicyEntryETT']['POA_DocumentFile']='';
                $input_array['objPolicyEntryETT']['Insured_photo']='';
                $input_array['objPolicyEntryETT']['POI_DocumentExt']='';
                $input_array['objPolicyEntryETT']['POA_DocumentExt']='';
                $input_array['objPolicyEntryETT']['Insured_photoExt']='';

            if ($proposal->ckyc_type == 'ckyc_number') {
                $input_array['objPolicyEntryETT']['CKYC_NO']=$proposal->ckyc_type_value;
            } else if($proposal->ckyc_type == 'documents') {

                if (\Illuminate\Support\Facades\Storage::exists('ckyc_photos/' . $request['userProductJourneyId'])) {
                    $filesList = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId']);
                    
                    $poaFile=\Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId'].'/poa');
                    $poiFile=\Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId'].'/poi');
                    $photoFile=\Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/'.$request['userProductJourneyId'].'/photos');

                    if (empty($photoFile)) {
                        return [
                            'status' => false,
                            'message' => 'Please upload photograph to complete proposal.'
                        ];
                    }

                    if (empty($poiFile)) {
                        return [
                            'status' => false,
                            'message' => 'Please upload Proof of Identity file to complete proposal.'
                        ];
                    }

                    if (empty($poaFile)) {
                        return [
                            'status' => false,
                            'message' => 'Please upload Proof of Address file to complete proposal.'
                        ];
                    }

                    

                    $ckycDocumentData=ckycUploadDocuments::
                    select('cky_doc_data')
                    ->where('user_product_journey_id',$proposal->user_product_journey_id)->first();
                    $ckycDocumentData=json_decode($ckycDocumentData->cky_doc_data, true);

                    $poiType=$ckycDocumentData['proof_of_identity']['poi_identity'];
                    $poaType=$ckycDocumentData['proof_of_address']['poa_identity'];

                    $photoExtension=explode('.',$photoFile[0]);
                    $photoExtension='.'.end($photoExtension);

                    $poaExtension=explode('.',$poaFile[0]);
                    $poaExtension='.'.end($poaExtension);

                    $poiExtension=explode('.',$poiFile[0]);
                    $poiExtension='.'.end($poiExtension);

                    switch ($poiType) {
                        case 'panNumber':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'PAN';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_panNumber'];

                            // AML tags
                            $input_array['objPolicyEntryETT']['PANorForm60'] = 'PAN';
                            $input_array['objPolicyEntryETT']['PanNo'] = $ckycDocumentData['proof_of_identity']['poi_panNumber'];
                            $input_array['objPolicyEntryETT']['Pan_Form60_Document_Name'] = "1";
                            $input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $poiExtension;
                            // $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poiFile[0]));
                            $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($poiFile[0]));
                            break;
                        case 'aadharNumber':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'PROOF OF POSSESSION OF AADHAR';
                            $input_array['objPolicyEntryETT']['POI_ID']= substr($ckycDocumentData['proof_of_identity']['poi_aadharNumber'], -4);
                            break;
                        case 'passportNumber':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'PASSPORT';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_passportNumber'];
                            break;
                        case 'drivingLicense':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'Driving License';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_drivingLicense'];
                            break;
                        case 'voterId':
                            $input_array['objPolicyEntryETT']['POI_Type'] = 'VOTER ID';
                            $input_array['objPolicyEntryETT']['POI_ID']=$ckycDocumentData['proof_of_identity']['poi_voterId'];
                            break;
                        default:
                            return [
                                'status' => false,
                                'message' => 'Proof of Identity details not found'
                            ];
                    }
                    switch ($poaType) {
                        case 'aadharNumber':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'PROOF OF POSSESSION OF AADHAR';
                            $input_array['objPolicyEntryETT']['POA_ID']= substr($ckycDocumentData['proof_of_address']['poa_aadharNumber'], -4);
                            break;
                        case 'passportNumber':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'PASSPORT';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_passportNumber'];
                            break;
                        case 'drivingLicense':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'Driving License';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_drivingLicense'];
                            break;
                        case 'voterId':
                            $input_array['objPolicyEntryETT']['POA_Type'] = 'VOTER ID';
                            $input_array['objPolicyEntryETT']['POA_ID']=$ckycDocumentData['proof_of_address']['poa_voterId'];
                            break;
                        default:
                            return [
                                'status' => false,
                                'message' => 'Proof of Address details not found'
                            ];
                    }

                    // $input_array['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poiFile[0]));
                    // $input_array['objPolicyEntryETT']['POA_DocumentFile'] = base64_encode(\Illuminate\Support\Facades\Storage::get($poaFile[0]));
                    // $input_array['objPolicyEntryETT']['Insured_photo'] = base64_encode(\Illuminate\Support\Facades\Storage::get($photoFile[0]));

                    $input_array['objPolicyEntryETT']['POI_DocumentFile'] = base64_encode(ProposalController::getCkycDocument($poiFile[0]));
                    $input_array['objPolicyEntryETT']['POA_DocumentFile'] = base64_encode(ProposalController::getCkycDocument($poaFile[0]));
                    $input_array['objPolicyEntryETT']['Insured_photo'] = base64_encode(ProposalController::getCkycDocument($photoFile[0]));


                    $input_array['objPolicyEntryETT']['POI_DocumentExt']=$poiExtension;
                    $input_array['objPolicyEntryETT']['POA_DocumentExt']=$poaExtension;
                    $input_array['objPolicyEntryETT']['Insured_photoExt']=$photoExtension;

                    if (config('SHRIRAM_AML_ENABLED') != 'Y') {
                        unset($input_array['objPolicyEntryETT']['PANorForm60']);
                        unset($input_array['objPolicyEntryETT']['PanNo']);
                        unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Name']);
                        unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext']);
                        unset($input_array['objPolicyEntryETT']['Pan_Form60_Document']);
                    }
                }
            }
        }

        if (config('SHRIRAM_AML_ENABLED') == 'Y') {

            $panFile = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId'] . '/pan_document');
            
            if (!empty($panFile) && !empty($proposal->pan_number)) {
                $panFileExtension = explode('.', $panFile[0]);
                $panFileExtension = '.' . end($panFileExtension);
                $input_array['objPolicyEntryETT']['PANorForm60'] = 'PAN';
                $input_array['objPolicyEntryETT']['PanNo'] = $proposal->pan_number;
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Name'] = '1';
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $panFileExtension;
                // $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($panFile[0]));
                $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($panFile[0]));
            }
            $form60File = \Illuminate\Support\Facades\Storage::allFiles('ckyc_photos/' . $request['userProductJourneyId'] . '/form60');
        
            if (!empty($form60File)) {
                $form60Extension = explode('.', $form60File[0]);
                $form60Extension = '.' . end($form60Extension);
                $input_array['objPolicyEntryETT']['PANorForm60'] = 'FORM60';
                $input_array['objPolicyEntryETT']['PanNo'] = '';
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Name'] = '1';
                $input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext'] = $form60Extension;
                // $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(\Illuminate\Support\Facades\Storage::get($form60File[0]));
                $input_array['objPolicyEntryETT']['Pan_Form60_Document'] = base64_encode(ProposalController::getCkycDocument($form60File[0]));
            }

            if (!isset($input_array['objPolicyEntryETT']['PANorForm60'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Please upload Pan or Form60 document'
                ]);
            }
        } else {
            unset($input_array['objPolicyEntryETT']['PANorForm60']);
            unset($input_array['objPolicyEntryETT']['PanNo']);
            unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Name']);
            unset($input_array['objPolicyEntryETT']['Pan_Form60_Document_Ext']);
            unset($input_array['objPolicyEntryETT']['Pan_Form60_Document']);
        }

        $additional_data['url'] = $URL;
        ServicesShriramSubmitProposal::proposalSubmit($input_array, $proposal, $additional_data, $request);
        $get_response = getWsData($URL, $input_array, 'shriram', $additional_data);
        $response = $get_response['response'];

        $quote_response = json_decode($response,TRUE);



        $vehicleDetails = [
            'manufacture_name'  => $mmv_data->veh_model,
            'model_name'        => $mmv_data->model_desc,
            'version'           => $mmv_data->veh_body,
            'fuel_type'         => $mmv_data->fuel,
            'seating_capacity'  => $mmv_data->veh_seat_cap,
            'carrying_capacity' => $mmv_data->veh_seat_cap,
            'cubic_capacity'    => $mmv_data->veh_cc,
            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
            'vehicle_type'      => $mmv_data->veh_ob_type ?? '',
        ];


        
        if (isset($quote_response['MessageResult']['Result']) && $quote_response['MessageResult']['Result'] == 'Success') {
            $idv = $quote_response['GenerateProposalResult']['VehicleIDV'];
            $quote_response = $quote_response['GenerateProposalResult'];
            $proposal->proposal_no = $quote_response['PROPOSAL_NO'];
            $proposal->pol_sys_id = $quote_response['POL_SYS_ID'];
            $proposal->is_ckyc_verified = 'Y';
            $proposal->ic_vehicle_details = $vehicleDetails;
            $proposal->save();
            
            $final_od_premium = $final_tp_premium = $cpa_premium = $NetPremium = $addon_premium = $ncb_discount = $total_discount = 0;
            $igst           = $anti_theft = $other_discount = 
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 
            $ncb_discount   = $tppd = $final_tp_premium = 
            $final_od_premium = $final_net_premium =
            $final_payable_amount = $basic_od = $electrical_accessories = 
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = 
            $pa_owner       = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount =
            $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $return_to_invoice = $basic_tp_premium =
            $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
            $zero_dep_loading = $engine_protection_loading = $consumable_loading=   0;

          
            foreach ($quote_response['CoverDtlList'] as $key => $value) {
                $value['CoverDesc'] = trim($value['CoverDesc']);
                /*if ($value['CoverDesc'] == 'Road Side Assistance') {
                    $rsapremium = $value['Premium'];
                }*/
                if (in_array($value['CoverDesc'], array('Basic OD Premium','Basic OD Premium - 1 Year','Basic Premium - 1 Year','Basic Premium - OD','Daily Expenses Reimbursement - OD')) ) {
                    $basic_od = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Voluntary excess/deductibles','Voluntary excess/deductibles - 1 Year','Voluntary excess/deductibles - OD'])) {
                    $voluntary_excess = abs($value['Premium']);
                }

                if ($value['CoverDesc'] == 'OD Total') {
                    $final_od_premium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('Basic TP Premium','Basic TP Premium - 1 Year','Basic TP Premium - 2 Year','Basic TP Premium - 3 Year','Basic Premium - TP')) ) {
                    $basic_tp_premium += $value['Premium'];
                }
                
                if ($value['CoverDesc'] == 'Total Premium') {
                    $final_net_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST(18.00%)') {
                    $igst = $igst + $value['Premium'];
                }

                if ($value['CoverDesc'] == 'SGST/UTGST(0.00%)') {
                    $sgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'CGST(0.00%)') {
                    $cgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'Total Amount') {
                    $final_payable_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('NCB Discount','NCB Discount - OD'))) {
                    $ncb_discount = abs($value['Premium']);
                }

                if ( in_array($value['CoverDesc'], array('Depreciation Deduction Waiver (Nil Depreciation) - 1 Year','Depreciation Deduction Waiver (Nil Depreciation)')) ) {
                    $zero_dep_amount = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('GR41-Cover For Electrical and Electronic Accessories - 1 Year','GR41-Cover For Electrical and Electronic Accessories', 'GR41-Cover For Electrical and Electronic Accessories - OD')) ) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'GR42-Outbuilt CNG/LPG-Kit-Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                    'GR42-Outbuilt CNG\/LPG-Kit-Cover - OD',
                    'InBuilt CNG Cover - OD',
                    'InBuilt  CNG  Cover - OD',
                    'InBuilt CNG Cover'
                )) && $value['Premium'] != 60) {
                    $lpg_cng = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'GR42-Outbuilt CNG/LPG-Kit-Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                    'InBuilt CNG Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year - TP',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 2 Year - TP',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 3 Year - TP'
                )) && $value['Premium'] == 60) {
                    $lpg_cng_tp += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER','CNG/LPG KIT - TP  COVER-GR-42 - 1 YEAR', 'InBuilt CNG Cover - TP', 'InBuilt  CNG  Cover - TP'))) {
                    $lpg_cng_tp = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('Cover For Non Electrical Accessories - 1 Year', 'Cover For Non Electrical Accessories','Cover For Non Electrical Accessories - OD'))) {
                    $non_electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)','GR36B2-PA Cover For Passengers (Un-Named Persons) - TP'])) {
                    $pa_unnamed = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3','PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3 - 1 YEAR'])) {
                    $pa_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                // if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER'])) {
                //     $pa_owner = $value['Premium'];
                // }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR','GR36A-PA FOR OWNER DRIVER - 1 Year','GR36A-PA FOR OWNER DRIVER - TP'])) {
                    $pa_owner = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver','Legal Liability Coverages For Paid Driver - TP'])) {
                    $ll_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], ['TP Total','TP Total']) ) {
                    $final_tp_premium += $value['Premium'];
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 3)+ $pa_owner +$pa_unnamed +$pa_paid_driver+$ll_paid_driver+$lpg_cng_tp): $final_tp_premium;
                }

                if (in_array($value['CoverDesc'], ['De-Tariff Discount - 1 Year', 'De-Tariff Discount','De-Tariff Discount - OD'])) {
                    $other_discount = abs($value['Premium']);
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'ANTI-THEFT DISCOUNT-GR-30'])) {
                    $anti_theft = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Road Side Assistance','Road Side Assistance - 1 Year','Road Side Assistance - OD')) ) {
                    $rsapremium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('KEY REPLACEMENT', 'KEY REPLACEMENT - 1 YEAR','Key Replacement','Key Replacement - 1 Year')) ) {
                    $key_rplc = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Engine Protector','Engine Protector - 1 Year'])) {
                    $engine_protection += $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Consumable','Consumable - 1 Year'])) {
                    $consumables_cover += $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Personal Belonging','Personal Belonging - 1 Year')) ) {
                    $personal_belonging = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('INVOICE RETURN', 'INVOICE RETURN - 1 YEAR','Return to Invoice - 1 Year')) ) {
                    $return_to_invoice = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR39A-Limit The Third Party Property Damage Cover','GR39A-Limit The Third Party Property Damage Cover - 1 Year','GR39A-Limit The Third Party Property Damage Cover - 2 Year','GR39A-Limit The Third Party Property Damage Cover - 3 Year','GR39A-Limit The Third Party Property Damage Cover - TP'])) {
                    $tppd_discount += abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Nil Depreciation Loading'])) {
                    $zero_dep_loading += abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Engine Protector Loading'])) {
                    $engine_protection_loading += abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Consumable Loading'])) {
                    $consumable_loading += abs($value['Premium']);
                }
                // GEO Extension
                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension','GR4-Geographical Extension - 1 Year','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - OD','GR4-Geographical Extension']) ) {
                    $geo_ext_one = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - 1 Year - TP','GR4-Geographical Extension - TP','GR4-Geographical Extension']) ) {
                    $geo_ext_two = (float)(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }
            }
            $final_gst_amount = isset($igst) ? $igst : 0;
        
            
            $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount ;
            $basic_od += $zero_dep_loading + $consumable_loading + $engine_protection_loading;
            $final_total_discount = $anti_theft + $ncb_discount + $other_discount + $voluntary_excess + $tppd_discount ;

            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            

            $addon_premium = $zero_dep_amount + $rsapremium + $key_rplc + $engine_protection + $consumables_cover + $personal_belonging +$return_to_invoice;
            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date'     => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                    'policy_end_date'       => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                    'proposal_no'           => $proposal->proposal_no,
                    'unique_proposal_id'    => $proposal->proposal_no,
                    'od_premium'            => $final_od_premium - $addon_premium,
                    'tp_premium'            => $final_tp_premium,
                    'addon_premium'         => $addon_premium,
                    'cpa_premium'           => $cpa_premium,
                    'final_premium'         => $final_net_premium,
                    'total_premium'         => $final_net_premium,
                    'service_tax_amount'    => $final_gst_amount,
                    'final_payable_amount'  => $final_payable_amount,
                    'product_code'          => $ProdCode,#$mmv_data->vap_prod_code,
                    'ic_vehicle_details'    => json_encode($vehicleDetails),
                    'ncb_discount'          => $ncb_discount,
                    'total_discount'        => $final_total_discount,
                    'cpa_ins_comp'          => $cPAInsComp,
                    'cpa_policy_fm_dt'      => str_replace('/', '-', $cPAPolicyFmDt),
                    'cpa_policy_no'         => $cPAPolicyNo,
                    'cpa_policy_to_dt'      => str_replace('/', '-', $cPAPolicyToDt),
                    'cpa_sum_insured'       => $cPASumInsured,
                    'electrical_accessories'    => $ElectricalaccessSI,
                    'non_electrical_accessories'=> $NonElectricalaccessSI
                ]);
                
            $data['user_product_journey_id'] = $enquiryId;
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            ShriramPremiumDetailController::saveJsonPremiumDetails($get_response['webservice_id']);

            return response()->json([
                'status' => true,
                'msg' => $quote_response['MessageResult']['SuccessMessage'] ?? 'Proposal Submitted Successfully' ,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $data['user_product_journey_id'],
                    'proposalNo' => $proposal->proposal_no,
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => '',
                    'inspection_number' => ''
                ]
            ]);
        } else {
            
            if(isset($quote_response['MessageResult']) && $quote_response['MessageResult']['ErrorMessage']) {
                $msg=$quote_response['MessageResult']['ErrorMessage'];
                if(isset($quote_response['GenerateProposalResult']['ERROR_DESC']) && $quote_response['GenerateProposalResult']['ERROR_DESC']!= "") {
                    $msg .= "<br>" . $quote_response['GenerateProposalResult']['ERROR_DESC']; 
                }
            } else if (isset($quote_response['GenerateProposalResult']) && isset($quote_response['GenerateProposalResult']['ERROR_DESC']) && $quote_response['GenerateProposalResult']['ERROR_DESC']) {
                $msg=$quote_response['GenerateProposalResult']['ERROR_DESC'];
            } else  {
                $msg='IC service issue';
            }
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' =>$msg,
            ]);
        }
    }

  
}