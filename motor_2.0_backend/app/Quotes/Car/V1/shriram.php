<?php

use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\stateBlockData;


include_once app_path() . '/Helpers/CarWebServiceHelper.php';



function getQuoteV1($enquiryId, $requestData, $productData)
{

    $refer_webservice = $productData->db_config['quote_db_cache'];
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
            'request' => [
                'mmv' => $mmv
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
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    } else {
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData-> vehicle_register_date;

        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
        // if ($car_age > 5 && $productData->zero_dep == '0') {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //         'request' => [
        //             'productData' => $productData,
        //             'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //             'car_age' => $car_age
        //         ]
        //     ];
        // }

        $rto_code = $requestData->rto_code;
        $rto_code = explode('-', $rto_code);
        if ((int)$rto_code[1] < 10) {
            $rto_code[1] = '0'.(int)$rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];

        $vehicle_in_90_days = 'N';

        if ($requestData->business_type == 'newbusiness') {
            $is_new = true;
            $proposalType = 'FRESH';
            $NCBEligibilityCriteria = '1';
            $policy_start_date = today();
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
            $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousPolicyType = $previous_ncb = '';
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
            $soapAction = "GenerateLTPvtCarProposal";
            $URL = config('IC.SHRIRAM.V1.CAR.QUOTE_URL');//constants.motor.shriram.QUOTE_URL_JSON
            $previous_ncb ='0';
        } else {
            $is_new = false;
            $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= now()) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1) : today()->addDay(1);
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');            
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
            $proposalType = "RENEWAL OF OTHERS";
            $PreviousPolicyFromDt = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-m-Y');
            $PreviousPolicyToDt = Carbon::parse($requestData->previous_policy_expiry_date)->format('d-m-Y');
            $PreviousPolicyType = "MOT-PLT-001";
            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
            $URL = config('IC.SHRIRAM.V1.CAR.QUOTE_URL'); //constants.motor.shriram.QUOTE_URL_JSON
            $previous_ncb = $NCBEligibilityCriteria == '1' ? "" : $requestData->previous_ncb;
            // if($requestData->is_claim == 'Y'){
                $previous_ncb = $requestData->previous_ncb ? $requestData->previous_ncb : '0';
            // }
            
            $soapAction = "GenerateProposal";
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

        //$policy_type = ($premium_type == 'comprehensive' ? 'MOT-PLT-014' : 'MOT-PLT-002');
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

        $tp_only = ($premium_type == 'third_party') ? true : false;

        switch ($premium_type) 
        {
            case 'comprehensive':
                $ProdCode = $is_new ?  "MOT-PRD-001" : "MOT-PRD-001";//"MOT-PRD-001" : "MOT-PRD-4129";
                $policy_type =  $is_new ? "MOT-PLT-014" : "MOT-PLT-001";
                $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($is_new ? '' :'MOT-PLT-001') : "MOT-PLT-002";
            break;
            case 'third_party':
               $ProdCode = $is_new ? "MOT-PRD-001" : "MOT-PRD-001";
               $policy_type = 'MOT-PLT-002';
               $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($is_new ? '' :'MOT-PLT-001') : 'MOT-PLT-002';
            //    $URL = $is_new ? config('constants.motor.shriram.NBQUOTE_URL') : config('constants.motor.shriram.QUOTE_URL');
            break;
            case 'own_damage':
                $ProdCode = "MOT-PRD-001";
                $policy_type = 'MOT-PLT-013';
                $proposalType = 'RENEWAL OF OTHERS';
                //$previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-002';
                $previous_policy_type = 'MOT-PLT-014';
                if ($requestData->previous_policy_type == 'Comprehensive') 
                {
                    $previous_policy_type = 'MOT-PLT-014';
                } 
                elseif ($requestData->previous_policy_type == 'Own-damage') 
                {
                    $previous_policy_type = 'MOT-PLT-013';
                }
                $soapAction = "GenerateLTPvtCarProposal";
                $tp_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-M-Y');
                $tp_end_date = Carbon::parse($requestData->previous_policy_expiry_date)->addYear(2)->format('d-M-Y');
               
                // $URL = config('constants.motor.shriram.NBQUOTE_URL');
            break;
        }

        $zero_dep = false;

        $NilDepreciationCoverYN = 'N';
        $DepDeductWaiverYN = "N";
        $key_rplc_yn = 'N';
        $pckg_name = '';
        $consumable = 'N';
        $Eng_Protector = 'N';
        $InvReturnYN = 'N';
        $LossOfPersonBelongYN="N";

        // if ($interval->y < 5) {
            if ($productData->zero_dep == '0') {
                $NilDepreciationCoverYN = 'Y';
                $DepDeductWaiverYN = "Y";
            }

            $key_rplc_yn = 'Y';
            $pckg_name = "ADDON_03";
            $consumable = 'Y';
            $Eng_Protector = 'Y';
            $LossOfPersonBelongYN = "Y";
        // }

        // if($interval->y < 1)
        // {
            $InvReturnYN = "Y";
        // }

        // if ($productData->zero_dep == '0') {
        //     $NilDepreciationCoverYN = 'YES';
        //     $DepDeductWaiverYN = "N";
        //     $key_rplc_yn = 'N';
        //     $pckg_name = "ADDON_03";
        //     $consumable = 'Y';
        //     $Eng_Protector = 'Y';
        //     $zero_dep = true;
        // }
        // else
        // {
        //     $NilDepreciationCoverYN = 'NO';
        //     $DepDeductWaiverYN = "Y";
        //     $key_rplc_yn = 'N';
        //     $consumable = 'N';
        //     $Eng_Protector = 'N';
        //     $pckg_name = "ADDON_01";
        // }

        if($tp_only)
        {
            $NilDepreciationCoverYN     = '0';
            $DepDeductWaiverYN          = 'Y';
            $key_rplc_yn                = 'N';
            $consumable                 = '0';
            $Eng_Protector              = '0';
            $pckg_name                  = '';
            $InvReturnYN                = 'N';
        }

        // Additional Covers, Accessories and Discounts
        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $rsacover = $PAforUnnamedPassengerSI = $antitheft = $voluntary_insurer_discountsf = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

        $voluntary_insurer_discounts = 'PCVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'PCVE1', // Discount of 0
            '2500'  => 'PCVE2', // Discount of 750
            '5000'  => 'PCVE3', // Discount of 1500
            '7500'  => 'PCVE4', // Discount of 2000
            '15000' => 'PCVE5'  // Discount of 2500
        ];

        $LLtoPaidDriverYN = '0';
        $LimitedTPPDYN = 0;
        // $is_geo_ext = false;

        $Bangladesh="0";
        $Bhutan="0";
        $SriLanka="0";
        $Nepal="0";
        $Maldives="0";
        $Pakistan="0";

        foreach ($discounts as $key => $value) {
            // As suggested by Paras sir, Disabling Anti Theft - 20-08-2021
            // if (in_array('anti-theft device', $value)) {
            //     $antitheft = '1';
            // }
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)){
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
        }

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
            /* if ($value['name'] == 'Geographical Extension') {
                $is_geo_ext = true;
                $countries = $value['countries'];
            } */
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
                if($value['sumInsured'] < 15000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'External Bi-Fuel Kit CNG/LPG value less than Rs. 15,000 is not supported by the insurer',
                        'request' => [
                            'productData' => $productData,
                            'message' => 'External Bi-Fuel Kit CNG/LPG value less than Rs. 15,000 is not supported by the insurer',
                            'kit_value' => $value['sumInsured']
                        ]
                    ];
                }
            }
        }
        // END Additional Covers, Accessories and Discounts

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

        foreach ($addons as $key => $value) {
          
            if (in_array('Road Side Assistance', $value)) {
                $RSACover = 'Y';
            }
        }

        $data = [
            "objPolicyEntryETT" => [
                "ReferenceNo" => "",
                "ProdCode" => $ProdCode,
                "PolicyFromDt" => $policy_start_date->format('d-m-Y'),//"25-Jan-2022",
                "PolicyToDt" => date('d-m-Y',strtotime($policy_end_date)),//"24-Jan-2023",
                "PolicyIssueDt" => today()->format('d-m-Y'),//"22-Jan-2022",
                "InsuredPrefix" => ($requestData->vehicle_owner_type == 'I') ? "1" : "3",
                "InsuredName" => 'Test Test',//"Amar",
                "Gender" => ($requestData->vehicle_owner_type == 'I') ? "M" : "", //"M",
                "Address1" =>"Address1",
                "Address2" => "Address2",
                "Address3" => "Address3",
                "State" => explode('-', $rto_code)[0], //"TN",
                "City" => 'Mumbai', //"Erode",
                "PinCode" => "400005",
                "PanNo" => null,
                "GSTNo" => null,
                "TelephoneNo" => "",
                "ProposalType" => $proposalType, //"Renewal",
                "PolicyType" => $policy_type, //"MOT-PLT-001",
                "DateOfBirth" =>  ($requestData->vehicle_owner_type == 'I') ? "05 JUN 1993" : "",
                "MobileNo" => $requestData->user_mobile ?? "9876543211", //"9626616284",
                "FaxNo" => "",
                "EmailID" => $requestData->user_email ?? 'ABC@testmail.com', //"Gopi@testmail.com",
                "POSAgentName" => $posp_name, //"Gopi",
                "POSAgentPanNo" => $posp_pan_number, //"12344",
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                "VehicleCode" =>  $mmv_data->veh_code, //"M_10075",
                "FirstRegDt" => date('d/m/Y', strtotime($requestData->vehicle_register_date)), //"10/07/2021", //,"06-09-2016",
                "VehicleType" => $is_new ? "W" : "U",//"U",
                "EngineNo" => "6584D218",//Str::upper(Str::random(8)),
                "ChassisNo" => "6589311F4SDSA3FFH",//Str::upper(Str::random(12)),
                "RegNo1" => explode('-', $rto_code)[0],
                "RegNo2" => explode('-', $rto_code)[1],
                "RegNo3" => !empty($vehicale_registration_number[2]) ? substr($vehicale_registration_number[2], 0, 3) : strtoupper(generateRandomString(2)), // "OK",
                "RegNo4" => $vehicale_registration_number[3] ??  mt_rand(1000,9999), // "4521",
                "RTOCode" => $rto_code, // "MH-01",
                "IDV_of_Vehicle" => 0,
                "Colour" => "RED",
                "VoluntaryExcess" =>  ($premium_type == 'third_party') ? 0 : $voluntary_insurer_discounts,//$BusinessType == "2" ? "PCVE1" : "PCVE2", //"MOT-DED-002", $voluntary_insurer_discounts,
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
                "ElectricalaccessYN" => ($premium_type == 'third_party') ? 0 : $Electricalaccess,
                "ElectricalaccessSI" =>  ($premium_type == 'third_party') ? 0 :$ElectricalaccessSI,
                "ElectricalaccessRemarks" => "electric",
                "NonElectricalaccessYN" =>  ($premium_type == 'third_party') ? 0 :$NonElectricalaccess,
                "NonElectricalaccessSI" =>  ($premium_type == 'third_party') ? 0 :$NonElectricalaccessSI,
                "NonElectricalaccessRemarks" => "non electric",
                "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleaner,
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
                "PreviousPolicyNo" => $is_new ? "" : "POL1234",
                "PreviousInsurer" => $is_new ? "" : "Bajaj Allianz General Insurance Company Limited",
                "PreviousPolicyFromDt" => date('d-m-Y',strtotime($PreviousPolicyFromDt)),//"25-JAN-2021",
                "PreviousPolicyToDt" =>  date('d-m-Y',strtotime($PreviousPolicyToDt)),//"24-JAN-2022",
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0', 
                "PreviousPolicyUWYear" => "",
                //"PreviousPolicyNCBPerc" => $requestData->is_claim == 'Y' ? '' : $previous_ncb,
                "PreviousPolicyNCBPerc" => $requestData->previous_ncb ? $requestData->previous_ncb : '0',
                "PreviousPolicyType" => $previous_policy_type,
                "NilDepreciationCoverYN" => $NilDepreciationCoverYN,// "No",
                "PreviousNilDepreciation" => "1",
                "RSACover" => $RSACover,//($car_age < 12 ? 'Y':'N'), // ($premium_type == 'third_party') ? 'N' : 'Y',
                "LossOfPersonBelongYN" =>  ($premium_type == 'third_party') ? "N" :$LossOfPersonBelongYN,
                "DepDeductWaiverYN"=>($premium_type == 'third_party') ? 'N' : $DepDeductWaiverYN,
                "Consumables" =>  ($premium_type == 'third_party') ? 'N' : $consumable,
                "Eng_Protector" =>  ($premium_type == 'third_party') ? 'N' :$Eng_Protector,
                "InvReturnYN" =>  ($premium_type == 'third_party') ? "N": $InvReturnYN,
                "KeyReplacementYN" =>  ($premium_type == 'third_party') ? "N" : $key_rplc_yn,
                "NCBPROTECTIONPREMIUM" =>  ($premium_type == 'third_party') ? "N" :"Y",
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
                "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                "VehicleManufactureYear" => $manufacture_year,
            ],
        ];

 
        if($premium_type == 'own_damage')
        {
            $data['objPolicyEntryETT']['tpPolAddr'] = 'HGJHKLK';
            $data['objPolicyEntryETT']['tpPolComp'] = 'Bajaj Allianz General Insurance Company Limited';
            $data['objPolicyEntryETT']['tpPolFmdt'] = $tp_start_date;
            $data['objPolicyEntryETT']['tpPolTodt'] = $tp_end_date;
            $data['objPolicyEntryETT']['tpPolNo'] = '464841654';

             
        }
       
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' =>  [
                'Username' => config('IC.SHRIRAM.V1.CAR.USERNAME'),//constants.IcConstants.shriram.SHRIRAM_USERNAME_JSON
                'Password' => config('IC.SHRIRAM.V1.CAR.PASSWORD'),//constants.IcConstants.shriram.SHRIRAM_PASSWORD_JSON
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Car',
            'method' => 'Premium Calculation',
            'transaction_type' => 'quote',
            'productName' => $productData->product_name . ($zero_dep ? ' (zero_dep)' : ''),
        ];

        $temp_data = $data;
        unset($temp_data['objPolicyEntryETT']['RegNo3'], $temp_data['objPolicyEntryETT']['RegNo4']);
        $checksum_data = checksum_encrypt($temp_data);
        $additional_data['checksum'] = $checksum_data;
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'shriram', $checksum_data, "CAR");
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
            $response = $is_data_exits_for_checksum;
        }else{
            $response = getWsData($URL, $data, 'shriram', $additional_data);
        }
                
        $quote_response = json_decode($response['response'],TRUE);
        
        if (isset($quote_response['MessageResult']['Result']) && $quote_response['MessageResult']['Result'] == 'Success') {
            $skip_second_call = false;
            update_quote_web_servicerequestresponse($response['table'], $response['webservice_id'], "Premium Calculation Success..!", "Success" );
            $idv = $quote_response['GetQuotResult']['VehicleIDV'];
            $idv_min = (string) ceil(0.85 * $quote_response['GetQuotResult']['VehicleIDV']);
            $idv_max = (string) floor(1.20 * $quote_response['GetQuotResult']['VehicleIDV']);
            if ($requestData->is_idv_changed == 'Y') 
            {
                if ($requestData->edit_idv >= floor($idv_max)) 
                {
                    $data['IDV_of_Vehicle'] = floor($idv_max);
                } 
                elseif ($requestData->edit_idv <= ceil($idv_min)) 
                {
                    $data['IDV_of_Vehicle'] = ceil($idv_min);
                } 
                else 
                {
                    $data['IDV_of_Vehicle'] = $requestData->edit_idv;
                }
            }
            else
            {
               #$data['IDV_of_Vehicle'] =  $idv_min;
               $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $data['IDV_of_Vehicle'] = $idv;
                        $skip_second_call = true;
                        break;
                    case 'min_idv':
                        $data['IDV_of_Vehicle'] = $idv_min;
                        break;
                    case 'max_idv':
                        $data['IDV_of_Vehicle'] = $idv_max;
                        break;
                    default:
                        $data['IDV_of_Vehicle'] = $idv_min;
                        break;
                } 
            }
            $additional_data['method'] = 'Premium Re Calculation IDV changed';
            $input_array = $data;
            if(!$skip_second_call){
                $temp_data = $input_array;
                unset($temp_data['objPolicyEntryETT']['RegNo3'], $temp_data['objPolicyEntryETT']['RegNo4']);
                $checksum_data = checksum_encrypt($temp_data);
                $additional_data['checksum'] = $checksum_data;
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'shriram', $checksum_data, "CAR");
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                    $get_response = $is_data_exits_for_checksum;
                }else{
                    $get_response = getWsData($URL, $input_array, 'shriram', $additional_data);
                }
                $response = json_decode($get_response['response'],TRUE);
            }
            if ($quote_response['MessageResult']['Result'] !== 'Success') {
                return [
                    'webservice_id' => $response['webservice_id'],
                    'table' => $response['table'],
                    'status' => false,
                    'msg' => $quote_response['MessageResult']['ErrorMessage']." => ".$quote_response['GetQuotResult']['ERROR_DESC'],
                ];
            }

            $quote_response = $quote_response['GetQuotResult'];
            $idv = $quote_response['VehicleIDV'];
            $igst           = $anti_theft = $other_discount = 
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 
            $ncb_discount   = $tppd = $final_tp_premium = 
            $final_od_premium = $final_net_premium =
            $final_payable_amount = $basic_od = $electrical_accessories = 
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = 
            $pa_owner       = $voluntary_excess = $pa_unnamed = $key_rplc = $tppd_discount =
            $ll_paid_driver = $personal_belonging = $engine_protection = $consumables_cover = $return_to_invoice = $basic_tp_premium = 
            $geog_Extension_TP_Premium = $geog_Extension_OD_Premium = $geo_ext_one = $geo_ext_two = 0;
            $zero_dep_loading = $engine_protection_loading = $consumable_loading=  0;
            $applicable_addons = [];

            foreach ($quote_response['CoverDtlList'] as $key => $value) {
                $value['CoverDesc'] = trim($value['CoverDesc']);
                /*if ($value['CoverDesc'] == 'Road Side Assistance') {
                    $rsapremium = $value['Premium'];
                }*/
                
                //if ( $key == 0 && in_array($value['CoverDesc'], array('Basic Premium - 1 Year')) ) {
                /* if ( $key == 0 && in_array($value['CoverDesc'], array('Basic OD Premium - 1 Year','Basic Premium - 1 Year')) ) {
                    $basic_od = $value['Premium'];
                } */

                if (in_array($value['CoverDesc'], array('Basic OD Premium','Basic OD Premium - 1 Year','Basic Premium - 1 Year','Basic Premium - OD','Daily Expenses Reimbursement - OD','Basic Premium - 1 Year - OD')) ) {
                    $basic_od = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['Voluntary excess/deductibles','Voluntary excess/deductibles - 1 Year','Voluntary excess/deductibles - 1 Year - OD','Voluntary excess/deductibles - OD'])) {
                    $voluntary_excess = abs($value['Premium']);
                }
                if ($value['CoverDesc'] == 'OD Total') {
                    $final_od_premium = $value['Premium'];
                }
                //if ( in_array($value['CoverDesc'], array('Basic TP Premium','TP Total')) ) {
                if (in_array($value['CoverDesc'], array('Basic TP Premium','Basic TP Premium - 1 Year','Basic TP Premium - 2 Year','Basic TP Premium - 3 Year','Basic Premium - TP')) ) {
                    $basic_tp_premium += $value['Premium'];
                }
                //basic tp for NB
                if (in_array($value['CoverDesc'], ['Basic Premium - 1 Year - TP'])) {
                    $basic_tp_premium = (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 2 Year - TP'])) {
                    $basic_tp_premium = $basic_tp_premium + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Basic Premium - 3 Year - TP'])) {
                    $basic_tp_premium = $basic_tp_premium + (float)($value['Premium']);
                }
                
                //End basic tp for NB 
                
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
                if (in_array($value['CoverDesc'], ['NCB Discount','NCB Discount  - OD','NCB Discount - OD'])) {
                    $ncb_discount = abs($value['Premium']);
                }

                if ( in_array($value['CoverDesc'], array('Depreciation Deduction Waiver (Nil Depreciation) - 1 Year','Depreciation Deduction Waiver (Nil Depreciation)','Depreciation Deduction Waiver (Nil Depreciation) - OD','Depreciation Deduction Waiver (Nil Depreciation) - 1 Year - OD')) ) {
                    $zero_dep_amount = $value['Premium'];
                    array_push($applicable_addons,"zeroDepreciation");
                }
               // if ( in_array($value['CoverDesc'], array('GR41-Cover For Electrical and Electronic Accessories')) ) {
                if (in_array($value['CoverDesc'], array('GR41-Cover For Electrical and Electronic Accessories - 1 Year','GR41-Cover For Electrical and Electronic Accessories', 'GR41-Cover For Electrical and Electronic Accessories - OD','GR41-Cover For Electrical and Electronic Accessories - 1 Year - OD')) ) {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array(
                    'GR42-Outbuilt CNG/LPG-Kit-Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - OD',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year - OD',
                    'InBuilt CNG Cover - OD',
                    'InBuilt  CNG  Cover - OD',
                    'InBuilt CNG Cover'
                )) && $value['Premium'] != 60) {
                    $lpg_cng = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array(
                    'GR42-Outbuilt CNG/LPG-Kit-Cover',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 1 Year - TP',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 2 Year - TP',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - 3 Year - TP',
                    'GR42-Outbuilt CNG/LPG-Kit-Cover - TP',
                    'InBuilt CNG Cover'
                )) && $value['Premium'] == 60) {
                    $lpg_cng_tp += $value['Premium'];
                }
                //$quote_response['CoverDtlList']
                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER','CNG/LPG KIT - TP  COVER-GR-42 - 1 YEAR', 'InBuilt CNG Cover - TP', 'InBuilt  CNG  Cover - TP'))) {
                    $lpg_cng_tp = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }

               // if ($value['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                if (in_array($value['CoverDesc'], array('Cover For Non Electrical Accessories - 1 Year', 'Cover For Non Electrical Accessories','Cover For Non Electrical Accessories - OD','Cover For Non Electrical Accessories - 1 Year - OD'))) {
                    $non_electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR36B2-PA Cover For Passengers (Un-Named Persons)','GR36B2-PA Cover For Passengers (Un-Named Persons) - TP','GR36B2-PA Cover For Passengers (Un-Named Persons) - 1 Year - TP'])) {
                    $pa_unnamed = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3','PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3 - 1 YEAR'])) {
                    $pa_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                // if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER'])) {
                //     $pa_owner = $value['Premium'];
                // }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR','GR36A-PA FOR OWNER DRIVER - 1 Year','GR36A-PA FOR OWNER DRIVER - TP','GR36A-PA FOR OWNER DRIVER - 1 Year - TP'])) {
                    $pa_owner = $value['Premium'];
                }
                //Cpa for NB
                // if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 1 Year - TP'])) {
                //     $pa_owner = (float)($value['Premium']);
                // }
                // if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 2 Year - TP'])) {
                //     $pa_owner = $pa_owner + (float)($value['Premium']);
                // }
                // if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER - 3 Year - TP'])) {
                //     $pa_owner = $pa_owner + (float)($value['Premium']);
                // }
                //End Cpa for NB


                if (in_array($value['CoverDesc'], ['Legal Liability Coverages For Paid Driver','Legal Liability Coverages For Paid Driver - TP','Legal Liability Coverages For Paid Driver - 1 Year - TP'])) {
                    $ll_paid_driver = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 3) : $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], ['TP Total','TP Total']) ) {
                    $final_tp_premium += $value['Premium'];
                    //$final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 3)+ $pa_owner +$pa_unnamed +$pa_paid_driver+$ll_paid_driver+$lpg_cng_tp): $final_tp_premium;
                }

                if (in_array($value['CoverDesc'], ['De-Tariff Discount - 1 Year', 'De-Tariff Discount','De-Tariff Discount - OD','De-Tariff Discount - 1 Year - OD'])) {
                    $other_discount = abs($value['Premium']);
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'ANTI-THEFT DISCOUNT-GR-30'])) {
                    $anti_theft = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('Road Side Assistance','Road Side Assistance - 1 Year','Road Side Assistance - OD','Road Side Assistance - 1 Year - OD')) ) {
                    $rsapremium = $value['Premium'];
                    array_push($applicable_addons,"roadSideAssistance");
                }
                if ( in_array($value['CoverDesc'], array('KEY REPLACEMENT', 'KEY REPLACEMENT - 1 YEAR','Key Replacement','Key Replacement - 1 Year','Key Replacement - OD','Key Replacement - 1 Year - OD')) ) {
                    $key_rplc = $value['Premium'];
                    array_push($applicable_addons,"keyReplace");
                }

                if (in_array($value['CoverDesc'], ['Engine Protector','Engine Protector - 1 Year','Engine Protector - OD','Engine Protector - 1 Year - OD'])) {
                    $engine_protection += $value['Premium'];
                    array_push($applicable_addons,"engineProtector");
                }

                if (in_array($value['CoverDesc'], ['Consumable','Consumable - 1 Year','Consumable - OD','Consumable - 1 Year - OD'])) {
                    $consumables_cover += $value['Premium'];
                    array_push($applicable_addons,"consumables");
                }
                if ( in_array($value['CoverDesc'], array('Personal Belonging','Personal Belonging - 1 Year','Personal Belonging - OD','Personal Belonging - 1 Year - OD')) ) {
                    $personal_belonging = $value['Premium'];
                    array_push($applicable_addons,"lopb");
                }

                if ( in_array($value['CoverDesc'], array('INVOICE RETURN', 'INVOICE RETURN - 1 YEAR','Return to Invoice - 1 Year','Return to Invoice - 1 Year - OD')) ) {
                    $return_to_invoice = $value['Premium'];
                    array_push($applicable_addons,"returnToInvoice");
                }
                if (in_array($value['CoverDesc'], ['GR39A-Limit The Third Party Property Damage Cover','GR39A-Limit The Third Party Property Damage Cover - 1 Year','GR39A-Limit The Third Party Property Damage Cover - 2 Year','GR39A-Limit The Third Party Property Damage Cover - 3 Year','GR39A-Limit The Third Party Property Damage Cover - TP'])) {
                    $tppd_discount += abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR39A-Limit The Third Party Property Damage Cover - 1 Year - TP'])) {
                    $tppd_discount = $tppd_discount+(float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR39A-Limit The Third Party Property Damage Cover - 2 Year - TP'])) {
                    $tppd_discount = $tppd_discount + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['GR39A-Limit The Third Party Property Damage Cover - 3 Year - TP'])) {
                    $tppd_discount = $tppd_discount + (float)($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Nil Depreciation Loading - 1 Year','Nil Depreciation Loading - OD','Nil Depreciation Loading - 1 Year - OD'])) {
                    // $zero_dep_loading += abs($value['Premium']);
                   //$zero_dep_amount+=abs($value['Premium']);
                   $zero_dep_loading = abs($value['Premium']); 
                }
                if (in_array($value['CoverDesc'], ['Engine Protector Loading','Engine Protector Loading - OD'])) {
                    // $engine_protection_loading += abs($value['Premium']);
                    $engine_protection +=abs($value['Premium']);
                }
                if (in_array($value['CoverDesc'], ['Consumable Loading','Consumable Loading - OD'])) {
                    // $consumable_loading += abs($value['Premium']);
                    $consumables_cover +=abs($value['Premium']);
                }

                // GEO Extension
                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension','GR4-Geographical Extension - 1 Year','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - 1 Year - OD','GR4-Geographical Extension - OD']) ) {
                    $geo_ext_one = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], ['GR4-Geographical Extension - 1 Year - TP','GR4-Geographical Extension - TP']) ) {
                    $geo_ext_two = (float)(($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium']);
                }
            }
            
            $zero_dep_amount += $zero_dep_loading;
        
            // if ((int) $NonElectricalaccessSI > 0) {
            //     $non_electrical_accessories = round(($NonElectricalaccessSI * 3.283 ) / 100);
            //     $basic_od = ($basic_od - $non_electrical_accessories);
            // }
            //echo $lpg_cng;die;
            
            if(!$tp_only)
            {
                if($geo_ext_one > $geo_ext_two)
                {
                    $geog_Extension_TP_Premium = $geo_ext_two;
                    $geog_Extension_OD_Premium = $geo_ext_one;
                }else
                {
                    $geog_Extension_TP_Premium = $geo_ext_one;
                    $geog_Extension_OD_Premium = $geo_ext_two;
                } 
            }else
            {
                $geog_Extension_TP_Premium = ($geo_ext_one > $geo_ext_two) ? $geo_ext_one : $geo_ext_two;   
            }

            if($requestData->business_type== 'newbusiness')
            {
                // $geog_Extension_TP_Premium = $geog_Extension_TP_Premium *5;
                $geog_Extension_OD_Premium = $geo_ext_one;
                $geog_Extension_TP_Premium = $geo_ext_two;
            }

            $final_gst_amount = isset($igst) ? $igst : 0;

            
            //$final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount ;
            $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount;

            $final_total_discount = abs($anti_theft) + abs($ncb_discount) + abs($other_discount) + abs($voluntary_excess) + abs($tppd_discount);
            // $basic_od += $zero_dep_loading + $consumable_loading + $engine_protection_loading;
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geog_Extension_OD_Premium;

            $add_ons = [];
            $without_addon_product = $productData->product_identifier == 'BASIC_ADDONS';          
            if ($productData->zero_dep != '0') 
            {
                $add_ons = [
                    'in_built' => [],
                    'additional' => [                    
                    ],
                    'other' => []
                ];
            }
            else
            {
                if($zero_dep_amount <= 0) {
                    return [
                        'status'=>false,
                        'msg'=>'Zero Depreciation amount cannot be zero'
                    ];
                }
                $add_ons = [
                    'in_built' => [
                        'zero_depreciation'     => round($zero_dep_amount),
                    ],
                    'additional' => [                                               
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => round($engine_protection),
                        'ncb_protection'        => 0,
                        'key_replace'           => round($key_rplc),
                        'consumables'           => round($consumables_cover),
                        'tyre_secure'           => 0,
                        'return_to_invoice'     => round($return_to_invoice),
                        'lopb'                  => round($personal_belonging),
                    ],
                    'other' => []
                ];
                
            }
            if($without_addon_product)
            {
                $add_ons = [
                    'in_built' => [
                    ],
                    'additional' => [   
                        'zero_depreciation'     => 0,
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => 0,//$engine_protection,
                        'ncb_protection'        => 0,
                        'key_replace'           => round($key_rplc),
                        'consumables'           => 0,//$consumables,
                        'tyre_secure'           => 0,
                        'return_to_invoice'     => round($return_to_invoice),
                        'lopb'                  => round($personal_belonging),
                    ],
                    'other' => []
                ];
                 $applicable_addons = [ "roadSideAssistance"];
            }
          
            if ($rsapremium == 0) {

                unset($add_ons['additional']['road_side_assistance']);
            }
            
            if($is_new)
            {
                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
            }
           
            $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
            $add_ons['additional_premium'] = array_sum($add_ons['additional']);
            $add_ons['other_premium'] = array_sum($add_ons['other']);
            $data_response = [
                'webservice_id' => $get_response['webservice_id'] ?? $response['webservice_id'] ?? '',
                'table' => $get_response['table'] ?? $response['table'] ?? '',
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
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                    'business_type' => ($requestData->business_type == 'rollover') ? 'Roll Over' : 'New Business',
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
                        'car_age' => 2, //$car_age,
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
                    'tppd_premium_amount' => round($basic_tp_premium),#($requestData->business_type== 'newbusiness') ? round($tppd * 3): round($tppd),
                    'tppd_discount' => $tppd_discount,
                    'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'default_paid_driver' => $ll_paid_driver,
                    'motor_additional_paid_driver' => round($pa_paid_driver),
                    'GeogExtension_ODPremium'      => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'      => $geog_Extension_TP_Premium,
                    'cng_lpg_tp' => round($lpg_cng_tp),
                    'seating_capacity' => $mmv_data->veh_seat_cap,
                    'deduction_of_ncb' => round(abs($ncb_discount)),
                    'antitheft_discount' => round(abs($anti_theft)),
                    'aai_discount' => '', //$automobile_association,
                    'voluntary_excess' => $voluntary_excess,
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
                        'manf_name'     => $mmv_data->veh_model,
                        'model_name'    => $mmv_data->model_desc,
                        'version_name'  => $mmv_data->veh_body,
                        'fuel_type'     => $mmv_data->fuel,
                        'seating_capacity' => $mmv_data->veh_seat_cap,
                        'carrying_capacity' => $mmv_data->veh_seat_cap,
                        'cubic_capacity' => $mmv_data->veh_cc,
                        'gross_vehicle_weight' => '',
                        'vehicle_type'  => 'Private Car',
                    ],
                ],
            ];
            
            /* if($is_geo_ext)
            {
                $data_response['Data']['GeogExtension_ODPremium'] = 0;
                $data_response['Data']['GeogExtension_TPPremium'] = 0;
            } */

            $garage_count = DB::table('shriram_cashless_garage')->select(DB::raw('COUNT(*) as total_rows'))->first();
            $data_response['Data']['garage_count'] = $garage_count->total_rows;
            return camelCase($data_response);
        } else {
            $msg="Insurer not reachable";

            if (!empty($quote_response)) {
                $msg='';
                if(isset($quote_response['MessageResult']['ErrorMessage']) && $quote_response['MessageResult']['ErrorMessage']) {
                    $msg=$quote_response['MessageResult']['ErrorMessage'];
                }

                if(isset($quote_response['GetQuotResult']['ERROR_DESC']) && $quote_response['GetQuotResult']['ERROR_DESC']) {
                    $msg.=$quote_response['GetQuotResult']['ERROR_DESC'];
                }
            }
            return [
                'webservice_id' => $response['webservice_id'],
                'table' => $response['table'],
                'status' => false,
                'msg' =>$msg,
            ];
        }
    }
}

// function spliceArayKey($arr, $key)
// {
//     if(array_search($key, $arr) !== false){
//         array_splice($arr, array_search($key, $arr), 1);
//     }
//     return $arr;
// }