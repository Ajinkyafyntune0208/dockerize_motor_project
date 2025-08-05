<?php

use Illuminate\Support\Str;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\UserProposal;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

function getQuoteV1JSON($enquiryId, $requestData, $productData)
{
   
   
    $refer_wbservice = $productData->db_config['quote_db_cache'];
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
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $bike_age = ceil($age / 12);
        // zero depriciation validation

        // if ($bike_age > 5 && $productData->zero_dep == '0') {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //         'request'=>[
        //             'bike_age'=>$bike_age,
        //             'productData'=>$productData->zero_dep
        //         ]
        //     ];
        // }

        $rto_code = $requestData->rto_code;
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
        // Re-arrange for Delhi RTO code - start 
        $rto_code = explode('-', $rto_code);
        if ((int)$rto_code[1] < 10) {
            $rto_code[1] = '0'.(int)$rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        $previous_not_sure = strtolower($requestData->previous_policy_expiry_date) == 'new';
        $vehicle_in_90_days = 'N';
        if ($requestData->business_type== 'newbusiness') {
            $BusinessType = '1';
            $ISNewVehicle = 'true';
            $Registration_Number = 'NEW';
            $proposalType = 'FRESH';
            $NCBEligibilityCriteria = '1';
            $policy_start_date = today();
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');

            $PreviousPolicyFromDt = $PreviousPolicyToDt =  $previous_ncb = '';
            $break_in = 'NO';
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
            $soapAction = "GenerateLTTwoWheelerProposal";
        } elseif($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            if($requestData->business_type == 'breakin'){
                // If previous policy is not sure then consider it as a break in case with more than 90days case.
                if ($previous_not_sure) { 
                    $policy_start_date = today()->addDay(2);
                }else{
                    $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= now()) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(2) : today()->addDay(2);
                }
            }else{
                $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= now()) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1) : today()->addDay(1);
            }
            $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));          
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);

            $BusinessType = '2';
            $ISNewVehicle = 'false';
            $proposalType = "RENEWAL OF OTHERS";

            if ($previous_not_sure) 
            {
                $proposalType = "RENEWAL.WO.PRV INS DTL"; 
            } 
            
            $PreviousPolicyToDt = $previous_not_sure ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y');
            $PreviousPolicyFromDt = $previous_not_sure ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');

            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
            $previous_ncb = $NCBEligibilityCriteria == '1' ? "" : $requestData->previous_ncb;
            if($requestData->is_claim == 'Y'){
                $previous_ncb = $requestData->previous_ncb;
            }

            if ($requestData->vehicle_registration_no != '') {
                $Registration_Number = $requestData->vehicle_registration_no;
            } else {
                $Registration_Number = $rto_code . '-AB-1234';
            }

            $break_in = "No";
            $soapAction = "GenerateProposal";
            //$nilDepreciationCover = ($bike_age > 5) ? 0 : 1; 
        }
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        
        $state_details = DB::table('shriram_rto_location')
            ->where('rtocode', $rto_code)
            ->orWhere('rtocode', $requestData->rto_code)
            ->first();
            
            if (!empty($state_details->rtoname))
            {   
                $findstring = explode(' ', $state_details->rtoname); 
                $rto_details = DB::table('shriram_pin_city_state')->where(function ($q) use ($findstring) {
                    foreach ($findstring as $value) {
                        $q->orWhere('pin_desc', 'like', "%{$value}");
                    }
                })->select('*')->first();
                if(!$rto_details){
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'request' => $requestData,
                        'state_details' => $state_details,
                        'message' => 'RTO city pincode not available in master.',
                    ];
                }
            }else
            {
                return ['premium_amount' => 0, 'status' => false, 'message' => 'RTO city not available', ];
            }

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false;
        $tpPolAddr = '';
        $tpPolComp = '';
        $tpPolNo ='';
        $tp_start_date = '';
        $tp_end_date  = '';
        switch ($premium_type) 
               {
                   case 'comprehensive':
                       $ProdCode =  "MOT-PRD-002";
                       $policy_type = ($requestData->business_type== 'newbusiness') ? "MOT-PLT-014" :  "MOT-PLT-001";
                       $policy__type = 'Comprehensive';
                       $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-001') : "MOT-PLT-002";
                       $URL = config('IC.SHRIRAM.V1.BIKE.QUOTE_URL'); //constants.motor.shriram.QUOTE_URL_JSON
                       break;
                    case 'breakin':
                        $ProdCode ="MOT-PRD-002";
                        $policy_type = 'MOT-PLT-001';
                        $policy__type = 'Comprehensive';
                        $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-001') : "MOT-PLT-002";
                        $URL = config('IC.SHRIRAM.V1.BIKE.QUOTE_URL');//constants.motor.shriram.QUOTE_URL_JSON
                        break;
                   case 'third_party':
                       $ProdCode =  "MOT-PRD-002";
                       $policy_type = 'MOT-PLT-002';
                       $policy__type = 'Third Party';
                       $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-002') : 'MOT-PLT-002';
                       $URL = config('IC.SHRIRAM.V1.BIKE.QUOTE_URL'); //constants.motor.shriram.QUOTE_URL_JSON
                       break;
                    case 'third_party_breakin':
                        $ProdCode = "MOT-PRD-002";
                        $policy_type = 'MOT-PLT-002';
                        $policy__type = 'Third Party';
                        $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-002') : 'MOT-PLT-002';
                        $URL = config('IC.SHRIRAM.V1.BIKE.QUOTE_URL'); //constants.motor.shriram.QUOTE_URL_JSON
                        break;
                    case 'own_damage':
                       $ProdCode = "MOT-PRD-002";
                       $policy_type = 'MOT-PLT-013';
                       $policy__type = 'Own Damage';
                       $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($requestData->business_type == 'newbusiness'? '' :'MOT-PLT-009') : "MOT-PLT-002";
                       $soapAction = "GenerateLTTwoWheelerProposal";
                       $tp_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                       $tp_end_date = date('d-M-Y   ', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
                       $tpPolAddr = 'HGJHKLK';
                       $tpPolComp = 'Bajaj Allianz General Insurance Company Limited';
                       $tpPolNo ='464841654';
                       $URL = config('IC.SHRIRAM.V1.BIKE.QUOTE_URL'); //constants.motor.shriram.QUOTE_URL_JSON
                       break;
                    case 'own_damage_breakin':
                        $ProdCode = "MOT-PRD-002";
                        $policy_type = 'MOT-PLT-013';
                        $policy__type = 'Own Damage';
                        $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-002';
                        $soapAction = "GenerateLTTwoWheelerProposal";
                        $tp_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                        $tp_end_date = date('d-M-Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
                        $tpPolAddr = 'HGJHKLK';
                        $tpPolComp = 'Bajaj Allianz General Insurance Company Limited';
                        $tpPolNo ='464841654';
                        $URL = config('IC.SHRIRAM.V1.BIKE.QUOTE_URL'); //constants.motor.shriram.QUOTE_URL_JSON
                        break;
                   
               }
        $zero_dep = false;
        $NilDepreciationCoverYN = 'No';
        $without_addon_product = $productData->product_identifier == 'BASIC_ADDONS';      
        /*if ($productData->zero_dep == '0') {
            if($bike_age <= 4)
            {
                $NilDepreciationCoverYN = 'YES';
                $DepDeductWaiverYN = "N";
            }else
            
            #$pckg_name = "ADDON_03";
            $zero_dep = true;
            $consumable = '1';
            $Eng_Protector = '1';
        } elseif($without_addon_product) {
            $NilDepreciationCoverYN = '0';
            $DepDeductWaiverYN = "Y";
            $consumable = '0';
            $Eng_Protector = '0';
            #$pckg_name = ($bike_age > 5) ? "ADDON_01" : "ADDON_03";
        }
        else{
            $NilDepreciationCoverYN = '0';
            $DepDeductWaiverYN = "Y";
            $consumable = '0';
            $Eng_Protector = '0';
        }*/

        $NilDepreciationCoverYN = 'N';
        $DepDeductWaiverYN = "N";
        $InvReturnYN = "N";
        $consumable = 'N';
        $Eng_Protector = 'N';
        // if ($interval->y < 5) {
            if ($productData->zero_dep == '0') {
                $NilDepreciationCoverYN = 'Y';
                $DepDeductWaiverYN = "Y";
            // }

         
        }

      
        $pckg_name = $productData->zero_dep == '0' ? "ADDON_03" : "ADDON_01";
        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $PAforUnnamedPassengerSI = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
         $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
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
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)){
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
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

        /*$PAOwnerDriverExclusion = "1";
        $PAOwnerDriverExReason = "PA_TYPE2";
        if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
            $PAOwnerDriverExclusion = "0";
            $PAOwnerDriverExReason = "";
        }*/
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posp_name = '';
        $posp_pan_number = '';
    
        /* $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
    
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $posp_name = $pos_data->agent_name;
                $posp_pan_number = $pos_data->pan_no;
            }
        } */
        $RSACover = 'N';
        if ($tp_only) {
            $RSACover = 'N';
        } 
        // else {
        //     $RSACover = 'Y'; //($bike_age < 12 ? 'Y':'N');
        // }

       
        foreach ($addons as $key => $value) {
          
            if (in_array('Road Side Assistance', $value)) {
                $RSACover = 'Y';
            }

            if (in_array('Return To Invoice', $value)) {
                $InvReturnYN = 'Y';
            }

            if (in_array('Consumable', $value)) {
                $consumable = 'Y';
            }
            if (in_array('Engine Protector', $value)) {
                $Eng_Protector = 'Y';
            }
        }

        

        if($requestData->business_type == 'breakin')
        {
            $NilDepreciationCoverYN = 'N';
            $DepDeductWaiverYN = "N";
            $InvReturnYN = "N";
            $consumable = 'N';
            $Eng_Protector = 'N';
        }
        
        $quote_data = [
            "objPolicyEntryETT" => 
            [
                "ReferenceNo" => "",
                "ProdCode" => $ProdCode,
                "PolicyFromDt" => $policy_start_date->format('d-m-Y'), //"19/08/2021",
                "PolicyToDt" => $policy_end_date, //"18/08/2022",
                "PolicyIssueDt" => today()->format('d-m-y'),
                "InsuredPrefix" => ($requestData->vehicle_owner_type == 'I') ? "1" : "3",
                "InsuredName" => ($requestData->user_fname ?? "Test") . ' ' . ($requestData->user_lname ?? "Test"), //"Gopi",
                "Gender" => ($requestData->vehicle_owner_type == 'I') ? "M" : "", //"M",
                "Address1" => "Address1",
                "Address2" => "Address2",
                "Address3" => "Address3",
                "State" => $rto_details->state,
                "City" => $rto_details->city, 
                "PinCode" => $rto_details->pin_code,
                "PanNo" => null,
                "GSTNo" => null,
                "TelephoneNo" => "",
                "ProposalType" => $proposalType, //"Renewal",
                "PolicyType" => $policy_type, //"MOT-PLT-001",
                "DateOfBirth" => ($requestData->vehicle_owner_type == 'I') ? "05 JUN 1993" : "",
                "MobileNo" => $requestData->user_mobile ?? "9876543211", //"9626616284",
                "FaxNo" => "",
                "EmailID" => $requestData->user_email ?? 'ABC@testmail.com', //"Gopi@testmail.com",
                "POSAgentName" => $posp_name, //"Gopi",
                "POSAgentPanNo" => $posp_pan_number, //"12344",
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                "VehicleCode" => $mmv_data->veh_code, //"M_10075",
                // "VehicleCode" => "M_16905", //"M_10075",
                "FirstRegDt" => date('d/m/Y', strtotime($requestData->vehicle_register_date)), //"10/07/2021", //,
                "VehicleType" => $BusinessType == "1" ? "W" : "U",
                "EngineNo" => Str::upper(Str::random(8)),
                "ChassisNo" => Str::upper(Str::random(17)),
                "RegNo1" => explode('-', $rto_code)[0],
                "RegNo2" => explode('-', $rto_code)[1],
                "RegNo3" => !empty($vehicale_registration_number[2]) ? substr($vehicale_registration_number[2], 0, 3) : strtoupper(generateRandomString(2)), // "OK",
                "RegNo4" => $vehicale_registration_number[3] ?? mt_rand(1000,9999), // "4521",
                "RTOCode" => $rto_code, // "MH-01",
                "IDV_of_Vehicle" => "",
                "Colour" => "",
                "VoluntaryExcess" => $voluntary_insurer_discounts,//$BusinessType == "2" ? "PCVE1" : "PCVE2", //"MOT-DED-002", $voluntary_insurer_discounts,
                "NoEmpCoverLL" => "0",
                "NoOfCleaner" => "",
                "NoOfDriver" => "1",
                "NoOfConductor" => "",
                "VehicleMadeinindiaYN" => "Y",
                "VehiclePurposeYN" => "",
                "NFPP_Employees" => "",
                "NFPP_OthThanEmp" => "",
                "LimitOwnPremiseYN" => $LimitOwnPremiseYN,
                "Bangladesh" => $Bangladesh,
                "Bhutan" => $Bhutan,
                "SriLanka" => $SriLanka,
                "Nepal" => $Nepal,
                "Pakistan" => $Pakistan,
                "Maldives" => $Maldives,
                "CNGKitYN" => $externalCNGKIT,
                "CNGKitSI" => $externalCNGKITSI,
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
                "ElectricalaccessYN" => ($tp_only) ? 0 : $Electricalaccess,
                "ElectricalaccessSI" =>  ($tp_only) ? 0 :$ElectricalaccessSI,
                "ElectricalaccessRemarks" => "electric",
                "NonElectricalaccessYN" =>  ($tp_only) ? 0 :$NonElectricalaccess,
                "NonElectricalaccessSI" =>  ($tp_only) ? 0 :$NonElectricalaccessSI,
                "NonElectricalaccessRemarks" => "non electric",
                "PAPaidDriverConductorCleanerYN" =>$PAPaidDriverConductorCleaner,
                "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                "PAPaidDriverCount" => "0",
                "PAPaidConductorCount" => "",
                "PAPaidCleanerCount" => "",
                "NomineeNameforPAOwnerDriver" => ($requestData->vehicle_owner_type == 'I') ? "Test Nominee" : "",
                "NomineeAgeforPAOwnerDriver" => ($requestData->vehicle_owner_type == 'I') ? "30" : "",
                "NomineeRelationforPAOwnerDriver" => ($requestData->vehicle_owner_type == 'I') ? "Brother" : "",
                "AppointeeNameforPAOwnerDriver" => "",
                "AppointeeRelationforPAOwnerDriver" => "",
                "LLtoPaidDriverYN" => $LLtoPaidDriverYN,
                "AntiTheftYN" => $antitheft,
                "PreviousPolicyNo" => ($BusinessType == "1" || $previous_not_sure) ? "" : "POL1234",
                "PreviousInsurer" => ($BusinessType == "1" || $previous_not_sure) ? "" : "Bajaj Allianz General Insurance Company Limited",
                "PreviousPolicyFromDt" => $PreviousPolicyFromDt, //"19/08/2020",
                "PreviousPolicyToDt" => $PreviousPolicyToDt, // "18/08/2021", 
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0',
                "PreviousPolicyUWYear" => "",
                "PreviousPolicyNCBPerc" =>$requestData->previous_ncb ? $requestData->previous_ncb : '0',
                "PreviousPolicyType" => $previous_policy_type,
                "NilDepreciationCoverYN" => $NilDepreciationCoverYN,//$nilDepreciationCover,
                "PreviousNilDepreciation" => "1",
                "RSACover" => $RSACover,//($bike_age < 12 ? 'Y':'N'), //$tp_only || $without_addon_product ? 'N' : 'Y',
                "HypothecationType" => "",
                "HypothecationBankName" => "",
                "HypothecationAddress1" => "",
                "HypothecationAddress2" => "",
                "HypothecationAddress3" => "",
                "HypothecationAgreementNo" => "",
                "HypothecationCountry" => "",
                "HypothecationState" => "",
                "HypothecationCity" => "",
                "HypothecationPinCode" => "",
                "SpecifiedPersonField" => "",
                "PAOwnerDriverExclusion" => ($requestData->vehicle_owner_type == 'I') ? '' : '1',//$PAOwnerDriverExclusion,
                "PAOwnerDriverExReason" => ($requestData->vehicle_owner_type == 'I') ? "" : 'PA_TYPE1',//$PAOwnerDriverExReason,
                "CPAInsComp" => "",
                "CPAPolicyFmDt" => "",
                "CPAPolicyNo" => "",
                "CPAPolicyToDt" => "",
                "CPASumInsured" => "",
                "DepDeductWaiverYN"=>$DepDeductWaiverYN,
                "InvReturnYN"  => ($premium_type == 'third_party') ? "N": $InvReturnYN,
                "Consumables" =>  $consumable,
                "EmergencyTranHotelExpRemYN"=>  "N",
                "Eng_Protector"=>  $Eng_Protector,
                "KeyReplacementYN"=>  "Y",
                "DailyExpRemYN"=>  "N",
                "tpPolAddr"=>  $tpPolAddr,
                "tpPolComp"=> $tpPolComp,
                "tpPolFmdt"=>  $tp_start_date,
                "tpPolNo"=>  $tpPolNo,
                "tpPolTodt"=>  $tp_end_date,
                "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                "VehicleManufactureYear" => $manufacture_year,
            ],
        ];

    
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' =>  [
                'Username' => config('IC.SHRIRAM.V1.BIKE.USERNAME'), //constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON
                'Password' => config('IC.SHRIRAM.V1.BIKE.PASSWORD'), //constants.IcConstants.shriram.SHRIRAM_PASSWORD_JSON
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Bike',
            'method' => 'Premium Calculation',
            'transaction_type' => 'quote',
            'productName' => $productData->product_name. " ($requestData->business_type)",
        ];

       $temp_data = $quote_data;
       unset($temp_data['objPolicyEntryETT']['ChassisNo'], $temp_data['objPolicyEntryETT']['EngineNo'], $temp_data['objPolicyEntryETT']['RegNo4'], $temp_data['objPolicyEntryETT']['RegNo3'],);
       $checksum_data = checksum_encrypt($temp_data);
       $additional_data['checksum'] =$checksum_data;
       $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'shriram',$checksum_data,'BIKE');
       if($is_data_exist_for_checksum['found'] && $refer_wbservice && $is_data_exist_for_checksum['status']){
           $get_response = $is_data_exist_for_checksum;
       }else{
           $get_response = getWsData($URL, $quote_data, 'shriram', $additional_data);
       }
       
       $response = $get_response['response'];
        
       $response = json_decode($response,True);
       
        if (isset($response['MessageResult']['Result']) && $response['MessageResult']['Result'] == 'Success') {
            $skip_second_call = false;
            $quote_response =$response['GetQuotResult']; 
            $idv = $quote_response['VehicleIDV'];
            $idv_min = $tp_only ? '0' : (string) ceil(0.85 * $quote_response['VehicleIDV']);
            $idv_max = $tp_only ? '0' : (string) floor(1.20 * $quote_response['VehicleIDV']);
            if ($requestData->is_idv_changed == 'Y') 
            {
                if ($requestData->edit_idv >= floor($idv_max)) 
                {
                    $quote_data['objPolicyEntryETT']['IDV_of_Vehicle'] = floor($idv_max);
                } 
                elseif ($requestData->edit_idv <= ceil($idv_min)) 
                {
                    $quote_data['objPolicyEntryETT']['IDV_of_Vehicle'] = ceil($idv_min);
                } 
                else 
                {
                    $quote_data['objPolicyEntryETT']['IDV_of_Vehicle'] = $requestData->edit_idv;
                }
            }
            else
            {
                #$quote_data['objPolicyEntryETT']['IDV_of_Vehicle'] =  $idv_min;
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $data['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv;
                        $skip_second_call = true;
                        break;
                    case 'min_idv':
                        $data['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_min;
                        break;
                    case 'max_idv':
                        $data['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_max;
                        break;
                    default:
                        $data['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv_min;
                        break;
                } 
            }
            if(!$tp_only && !$skip_second_call){
                $additional_data['method'] = 'Premium Re Calculation';
                $temp_data = $quote_data;
                unset($temp_data['objPolicyEntryETT']['ChassisNo'], $temp_data['objPolicyEntryETT']['EngineNo'], $temp_data['objPolicyEntryETT']['RegNo4'], $temp_data['objPolicyEntryETT']['RegNo3'],);
                $checksum_data = checksum_encrypt($temp_data);
                $additional_data['checksum'] =$checksum_data;
                $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'shriram',$checksum_data,'BIKE');

                if($is_data_exist_for_checksum['found'] && $refer_wbservice && $is_data_exist_for_checksum['status']){
                    $get_response = $is_data_exist_for_checksum;
                }else{
                    $get_response = getWsData($URL, $quote_data, 'shriram', $additional_data);
                }
                $response = $get_response['response'];
                $response =json_decode($response,TRUE);
                $quote_response =$response['GetQuotResult'];
            }
            // if($proposalType == "RENEWAL" && $premium_type != 'own_damage'){
            //     $quote_response = $response['GetQuotResult'];
            // }elseif($proposalType == 'RENEWAL' && $premium_type == 'own_damage')
            // {
            //     $quote_response = $response['GenerateLTTwoWheelerProposalResult'];
            // }else{
            //     $quote_response = $response['GenerateLTTwoWheelerProposalResult'];
            // }
        
            if ($response['MessageResult']['Result'] != 'Success') {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'msg' => $quote_response['ERROR_DESC'],
                ];
            }
            $idv = $quote_response['VehicleIDV'];
            $igst           = $anti_theft = $other_discount = 0;
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 0;
            $ncb_discount   = $tppd = $final_tp_premium =  0;
            $final_od_premium = $final_net_premium =0;
            $final_payable_amount = $basic_od = $electrical_accessories = 0;
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = $tppd_discount=0;
            $pa_owner       = $voluntary_excess = $pa_unnamed =  0;
            $ll_paid_driver =$engine_protection = $consumables_cover = $return_to_invoice = $loading_amount = 0;
            $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
            $Minimum_OD_Loading = $NilDepreciationLoading = 0;
            $applicable_addons = [];
           
            foreach ($quote_response['CoverDtlList'] as $key => $value) {

                if ( in_array($value['CoverDesc'], array('Basic OD Premium','Basic Premium - 1 Year','Basic Premium - OD','Daily Expenses Reimbursement - OD','Basic Premium - 1 Year - OD')) ) {
                    $basic_od = $value['Premium'];
                    $od_key = $key;
                }
                if (in_array($value['CoverDesc'], ['Voluntary excess/deductibles','Voluntary excess/deductibles - 1 Year','Voluntary excess/deductibles - 1 Year - OD','Voluntary excess/deductibles - OD'])) {
                    $voluntary_excess = abs($value['Premium']);
                }
                if ( in_array($value['CoverDesc'], array('OD Total')) ) {
                    $final_od_premium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Basic TP Premium','Basic Premium - TP')) ) {
                    $tppd = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'Basic Premium - 1 Year' &&  $value['Premium'] != $basic_od && $key != $od_key) {
                    $tppd = $value['Premium'];
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

                if (in_array($value['CoverDesc'], array('NCB Discount','NCB Discount  - OD','NCB Discount - OD'))) {
                    $ncb_discount = abs($value['Premium']);
                }

                if ($value['CoverDesc'] == 'UW LOADING-MIN PREMIUM') {
                    $loading_amount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('Minimum OD Loading','Minimum OD Loading - OD'))) {
                    $Minimum_OD_Loading = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['Nil Depreciation Cover','Nil Depreciation Cover - 1 Year','Nil Depreciation Cover - OD', 'Nil Depreciation Cover - 1 Year - OD'] )) {
                    $zero_dep_amount = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Return to Invoice', 'Return to Invoice - 1 Year', 'Return to Invoice - 1 Year - OD')) ) {
                    $return_to_invoice = $value['Premium'];
                     array_push($applicable_addons, "returnToInvoice");
                }
                if (in_array($value['CoverDesc'], ['Consumable','Consumable - 1 Year','Consumable - OD', 'Consumable - 1 Year - OD'])) {
                    $consumables_cover = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Engine Protector','Engine Protector - 1 Year','Engine Protector - OD', 'Engine Protector - 1 Year - OD'])) {
                    $engine_protection = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR41-Cover For Electrical and Electronic Accessories','GR41-Cover For Electrical and Electronic Accessories - OD','GR41-Cover For Electrical and Electronic Accessories - 1 Year - OD'])) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT'))) {
                    $lpg_cng = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER'))) {
                    $lpg_cng_tp = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('Cover For Non Electrical Accessories','Cover For Non Electrical Accessories - OD','Cover For Non Electrical Accessories - 1 Year - OD'))) {
                    $non_electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)','GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year','GR36B2-PA Cover For Passengers (Un-Named Persons) - TP'])) {
                    $pa_unnamed = $value['Premium'];
                }

                if ($value['CoverDesc'] == '  ') {
                    $pa_paid_driver = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER','GR36A-PA FOR OWNER DRIVER - 1 Year','GR36A-PA FOR OWNER DRIVER - TP'])) {
                    $pa_owner = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver','Legal Liability Coverages For Paid Driver - 1 Year','Legal Liability Coverages For Paid Driver - TP'])) {
                    $ll_paid_driver = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['De-Tariff Discount' ,'De-Tariff Discount - 1 Year', 'De-Tariff Discount - OD','De-Tariff Discount - 1 Year - OD'])) {
                    $other_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover'])) {
                    $anti_theft = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('Road Side Assistance','Road Side Assistance - 1 Year','Road Side Assistance - OD','Road Side Assistance - 1 Year - OD')) ) {
                    $rsapremium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('GR39A-Limit The Third Party Property Damage Cover','GR39A-Limit The Third Party Property Damage Cover - TP')) ) {
                    //$tppd_discount = $value['Premium'];
                    $tppd_discount = abs(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }
                if ( in_array($value['CoverDesc'], ['TP Total']) ) {
                    $final_tp_premium = $value['Premium'];
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 5) + $pa_owner ) - $tppd_discount : $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension','GR4-Geographical Extension - 1 Year']) ) {
                    if($geo_ext_one > 0)
                    {
                        $geo_ext_two = $value['Premium'];
                    }else
                    {
                        $geo_ext_one = $value['Premium'];
                    }
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 5) + $pa_owner ) - $tppd_discount : $value['Premium'];
                }
                
                if (in_array($value['CoverDesc'], ['Nil Depreciation Loading','Nil Depreciation Loading - 1 Year','Nil Depreciation Loading - OD','Nil Depreciation Loading - 1 Year - OD'])) {
                    $NilDepreciationLoading = $value['Premium'];
                }

                // Basic TP
                if (in_array($value['CoverDesc'], ['Basic Premium - 1 Year - TP'])) {
                    $tppd = (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 2 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 3 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 4 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 5 Year - TP'])) {
                    $tppd = $tppd + (float)($value['Premium']);
                }

                // CPA
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 1 Year - TP'])) {
                    $pa_owner = (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 2 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 3 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 4 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 5 Year - TP'])) {
                    $pa_owner = $pa_owner + (float)($value['Premium']);
                }

                //TPPD Discount
                if (in_array($value['CoverDesc'], array('GR39A-Limit The Third Party Property Damage Cover','GR39A-Limit The Third Party Property Damage Cover - TP','GR39A-Limit The Third Party Property Damage Cover - 1 Year - TP')) ) {
                    $tppd_discount = (float)(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }

                //LL paid driver
                if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver','Legal Liability Coverages For Paid Driver - 1 Year','Legal Liability Coverages For Paid Driver - TP','Legal Liability Coverages For Paid Driver - 1 Year - TP'])) {
                    $ll_paid_driver = (float)(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }

                //PA Passenger
                if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)','GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year','GR36B2-PA Cover For Passengers (Un-Named Persons) - TP','GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year - TP'])) {
                    $pa_unnamed = (float)(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }

                // GEO Extension
                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension','GR4-Geographical Extension - 1 Year','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - 1 Year - OD']) ) {
                    $geo_ext_one = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - 1 Year - TP']) ) {
                    $geo_ext_two = (float)(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }

                
                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - OD']) ) {
                    $geog_Extension_OD_Premium = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - TP']) ) {
                    $geog_Extension_TP_Premium = $value['Premium'];
                }
            }
            // if ((int) $NonElectricalaccessSI > 0) {
            //     $non_electrical_accessories = round(($NonElectricalaccessSI * 3.283 ) / 100);
            //     $basic_od = ($basic_od - $non_electrical_accessories);
            // }

           

            if(!$tp_only)
            {
                if($geo_ext_one > $geo_ext_two)
                {
                    $geog_Extension_TP_Premium = $geo_ext_two;
                    $geog_Extension_OD_Premium = $geo_ext_one;
                }else
                {
                    $geog_Extension_OD_Premium = ( $geo_ext_one <= 0 ) ? $geog_Extension_OD_Premium : $geo_ext_one ;
                    $geog_Extension_TP_Premium = ( $geo_ext_two <= 0 ) ? $geog_Extension_TP_Premium : $geo_ext_two ;
                } 
            }else
            {                              
                $geog_Extension_TP_Premium = ( $geo_ext_two <= 0 ) ? $geog_Extension_TP_Premium : $geo_ext_two ;             
            }

            if($requestData->business_type== 'newbusiness')
            {
                // $geog_Extension_TP_Premium = $geog_Extension_TP_Premium *5;
                $geog_Extension_OD_Premium = ( $geo_ext_one <= 0 ) ? $geog_Extension_OD_Premium : $geo_ext_one ;
                $geog_Extension_TP_Premium = ( $geo_ext_two <= 0 ) ? $geog_Extension_TP_Premium : $geo_ext_two ;
            }
  
            $final_gst_amount = isset($igst) ? $igst : 0;

            //$final_tp_premium = $final_tp_premium - ($pa_owner) - $lpg_cng_tp - $pa_paid_driver - $ll_paid_driver;
            //$final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount + $ll_paid_driver + $pa_unnamed;
            $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount;// - $pa_paid_driver - $ll_paid_driver - $pa_unnamed;

            // print_pre("final tp".$final_tp_premium." ".$pa_owner." => ".$tppd_discount);

            $final_total_discount = abs($ncb_discount) + abs($other_discount) + abs($voluntary_excess) + abs($tppd_discount);
           
            
            // print_pre("final disc".$ncb_discount." ".$other_discount." => ".$voluntary_excess."=> ".$tppd_discount);

            if($Minimum_OD_Loading > 0)
            {
                $basic_od = round($basic_od + $Minimum_OD_Loading);     
            }
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geog_Extension_OD_Premium;

            // print_pre("final od".$basic_od." ".$non_electrical_accessories." => ".$electrical_accessories."=> ".$lpg_cng);

            $add_ons = [];
           
            /*if ($bike_age > 4) {
                $add_ons = [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => round($engine_protection),
                        'consumables'           => round($consumables_cover),
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
            }elseif($zero_dep){
                $add_ons = [
                    'in_built' => [	
					],
                    'additional' => [   
                        'zero_depreciation'     => round($zero_dep_amount + $NilDepreciationLoading),                     
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => round($engine_protection),
                        'consumables'           => round($consumables_cover),
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                array_push($applicable_addons, "zeroDepreciation");
                array_push($applicable_addons, "engineProtector");
                array_push($applicable_addons, "consumables");
            }else{
                $add_ons = [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => round($engine_protection),
                        'consumables'           => round($consumables_cover),
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                array_push($applicable_addons, "zeroDepreciation");
            }*/


            if ($productData->zero_dep == '0')
            {
                if ($zero_dep_amount <=0){

                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Zero Dep Amount Should not be Zero',

                    ];
                }

                $add_ons = [
                    'in_built' => [ 
                        'zero_depreciation'     => round($zero_dep_amount + $NilDepreciationLoading),   
                    ],
                    'additional' => [   
                                         
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => round($engine_protection),
                        'consumables'           => round($consumables_cover),
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                array_push($applicable_addons, "zeroDepreciation");
                array_push($applicable_addons, "engineProtector");
                array_push($applicable_addons, "consumables");
            }
            else
            {
                $add_ons = [
                    'in_built' => [
                    ],
                    'additional' => [                       
                    ],
                    'other' => []
                ];
                 $applicable_addons = [ "roadSideAssistance"];
            }
            if($without_addon_product)
            {
                $add_ons = [
                    'in_built' => [
                    ],
                    'additional' => [   
                        // 'zero_depreciation'     => 0,                     
                        'road_side_assistance'  => round($rsapremium),
                        // 'engine_protector'      => 0,
                        // 'consumables'           => 0,
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                 $applicable_addons = [ "roadSideAssistance"];
            }
           
            $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
            $add_ons['additional_premium'] = array_sum($add_ons['additional']);
            $add_ons['other_premium'] = array_sum($add_ons['other']);
            $loading_amount =  50 - ($final_od_premium - abs($ncb_discount) - abs($other_discount) - abs($voluntary_excess));
            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => (int) $idv,
                    'min_idv' => (int) $idv_min,
                    'max_idv' => (int) $idv_max,
                    'vehicle_idv' => $idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    "UnderwritingLoadingAmount" => 0 ,//$loading_amount,
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
                        'segment_id' => '0',
                        'rto_cluster_id' => '0',
                        'bike_age' => 2, //$bike_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => round(abs($other_discount)),
                    ],
                    'ic_vehicle_discount' => round(abs($other_discount)),
                    'basic_premium' => round($basic_od),
                    'motor_electric_accessories_value' => round($electrical_accessories),
                    'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                    'motor_lpg_cng_kit_value' => round($lpg_cng),
                    'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                    'total_own_damage' => round($final_od_premium),
                    'tppd_premium_amount' => round($final_tp_premium),#($requestData->business_type== 'newbusiness') ? round($tppd * 5): round($tppd),
                    'tppd_discount' => $tppd_discount,
                    'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'default_paid_driver' => $ll_paid_driver,
                    'motor_additional_paid_driver' => round($pa_paid_driver),
                    'GeogExtension_ODPremium'     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'     => $geog_Extension_TP_Premium,
                    'cng_lpg_tp' => round($lpg_cng_tp),
                    'seating_capacity' => $mmv_data->veh_seat_cap,
                    'deduction_of_ncb' => round(abs($ncb_discount)),
                    'antitheft_discount' => '',
                    'aai_discount' => '', //$automobile_association,
                    'voluntary_excess' => round($voluntary_excess),
                    'other_discount' => round(abs($other_discount)),
                    'total_liability_premium' => round($final_tp_premium),
                    'net_premium' => round($final_net_premium),
                    'service_tax_amount' => round($final_gst_amount),
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => round($final_payable_amount),

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

                    'add_ons_data'      => $add_ons,
                    'applicable_addons' => $without_addon_product ? [] : $applicable_addons,
                    'final_od_premium'  => round($final_od_premium),
                    'final_tp_premium'  => round($final_tp_premium),
                    'final_total_discount' => round(abs($final_total_discount)),
                    'final_net_premium' => round($final_net_premium),
                    'final_gst_amount'  => round($final_gst_amount),
                    'final_payable_amount' => round($final_payable_amount),
                    'mmv_detail'    => [
                        'manf_name'     => $mmv_data->manf,
                        'model_name'    => $mmv_data->model_desc,
                        'version_name'  => '',
                        'fuel_type'     => $mmv_data->fuel,
                        'seating_capacity' => $mmv_data->veh_seat_cap,
                        'carrying_capacity' => $mmv_data->veh_seat_cap,
                        'cubic_capacity' => $mmv_data->veh_cc,
                        'gross_vehicle_weight' => '',
                        'vehicle_type'  => 'Bike',
                    ],
                ],
                'ic_cover_details' => $quote_response['CoverDtlList'],
            ];
          
            return camelCase($data_response);
        } else {
            $msg="Insurer not reachable";

            if (!empty($response)) {
                $msg='';
                if(isset($response['MessageResult']['ErrorMessage']) && $response['MessageResult']['ErrorMessage']) {
                    $msg=$response['MessageResult']['ErrorMessage'];
                }

                if(isset($response['GetQuotResult']['ERROR_DESC']) && $response['GetQuotResult']['ERROR_DESC']) {
                    $msg.=$response['GetQuotResult']['ERROR_DESC'];
                }
            }
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'msg' => $msg,
            ];
        }
    }
}