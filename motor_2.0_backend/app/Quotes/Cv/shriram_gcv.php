<?php
namespace App\Quotes\Cv\shriram_gcv;

use Illuminate\Support\Str;
use App\Models\SelectedAddons;
use App\Quotes\Cv\shriram_gcv\shriramgcv;
use DateTime;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

function getasQuote($enquiryId, $requestData, $productData)
{  
//    if(($requestData->ownership_changed ?? '' ) == 'Y')
//     {      
//         return [
//             'premium_amount' => 0,
//             'status' => false,
//             'message' => 'Quotes not allowed for ownership changed vehicle',
//             'request' => [
//                 'message' => 'Quotes not allowed for ownership changed vehicle',
//                 'requestData' => $requestData
//             ]
//         ];
//     }
    
    $mmv = get_mmv_details($productData,$requestData->version_id,'shriram');
    



    if ($mmv['status'] == 1)
    {
        $mmv = $mmv['data'];
    }
    else
    {
        return  [   
            'premium_amount' => '0',
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];          
    }
    if(config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR'){
        if ($requestData->gcv_carrier_type != 'PUBLIC') {
            return [
                'premium_amount' => 0,
                'status'    => false,
                'message'   => 'Insurer doesn\'t provide quotes for GCV - Private Carrier',
                'request' => [
                    'requestData' => $requestData,
                    'carrier_type' => $requestData->gcv_carrier_type,
                    'message' => 'Insurer doesn\'t provide quotes for GCV - Private Carrier',
                ]
            ];
        }
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
  
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '')
    {
        return [
            'premium_amount' => '0',
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    }
    elseif ($mmv_data->ic_version_code == 'DNE')
    {
        return [
            'premium_amount' => '0',
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }
    else
    
    {
        $rto_code = $requestData->rto_code;

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        
            if($premium_type == 'breakin')
            {
                $premium_type = 'comprehensive';
            }
            else if($premium_type == 'third_party_breakin')
            {
                $premium_type = 'third_party';
            }
            else if($premium_type == 'own_damage_breakin')
            {
                $premium_type = 'own_damage';
            }

        $policy_type = ($premium_type == 'comprehensive' ? 'MOT-PLT-001' : 'MOT-PLT-002');
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

        if ($requestData->previous_policy_type == 'Third-party' && $tp_only == 'false')
        {
            return  [   
                'premium_amount' => '0',
                'status' => false,
                'message' => 'Quotes not available for Previous policy as Third-Party.',
                'request' => [
                    'requestData' => $requestData,
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'message' => 'Quotes not available for Previous policy as Third-Party.',
                ]
            ]; 
        }

        if ( ! empty($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no!="NEW")
        {
            $vehicale_registration_number = explode('-', getRegisterNumberWithOrWithoutZero($requestData->vehicle_registration_no));
        }
        else
        {
            $rto = explode('-', RtoCodeWithOrWithoutZero($rto_code,true));
        }

        $registration_numbers = [
            $vehicale_registration_number[0] ?? $rto[0],
            $vehicale_registration_number[1] ?? $rto[1],
            $vehicale_registration_number[2] ?? ($requestData->business_type != 'newbusiness' ? 'AT' : 'AT'),
            $vehicale_registration_number[3] ?? ($requestData->business_type != 'newbusiness' ? '1234' : '1234')
        ];
        $manufacture_year = explode('-',$requestData->manufacture_year)[1];
            $car_age=0;
        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $ISNewVehicle = 'true';
            $Registration_Number = $rto_code;
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $proposalType = 'Fresh';
            $policy_start_date = today()->format('d-m-Y');
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');

            $PreviousPolicyFromDt = $PreviousPolicyToDt = $PreviousNilDepreciation = $PreviousPolicyType = $previous_ncb = '';
            $break_in = 'NO';
            $vehicle_in_90_days = 'N';
            $previous_ncb = $requestData->previous_ncb ? $requestData->previous_ncb : '0';
        }
        else
        {
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new \DateTime($vehicleDate);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $policy_start_date = $requestData->business_type != 'breakin' ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1)->format('d-m-Y') : today()->addDay(1)->format('d-m-Y');
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $car_age = ceil($age / 12);

            $BusinessType = '5';
            $ISNewVehicle = 'false';
            $proposalType = ($requestData->previous_policy_type == 'Not sure') ? "RENEWAL.WO.PRV INS DTL" : "RENEWAL OF OTHERS";
            $PreviousPolicyFromDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d-m-Y');
            $PreviousPolicyToDt = ($requestData->previous_policy_type == 'Not sure') ? "" : Carbon::parse($requestData->previous_policy_expiry_date)->format('d-m-Y');
            $PreviousPolicyType = $requestData->previous_policy_type == 'Third-party' ? "MOT-PLT-002" : "MOT-PLT-001";
            $PreviousNilDepreciation = 1;
            $previous_ncb = $requestData->previous_ncb ? $requestData->previous_ncb : '0';

            if ($requestData->vehicle_registration_no != '')
            {
                $Registration_Number = $requestData->vehicle_registration_no;
            }
            else
            {
                $Registration_Number = $rto_code;
            }
            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
            // $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            // $break_in = (Carbon::parse($requestData->previous_policy_expiry_date)->diffInDays($policy_start_date) > 0) ? 'YES' : 'NO';
            $break_in = "No";
            $vehicle_in_90_days = 'N';
        }

        $zero_dep_amount = false;
        if ($productData->zero_dep == '0') {
            $zero_dep_amount = true;
        }
        // if ($car_age > 5 && $zero_dep_amount) {
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

        //  if ( $mmv['veh_gvw'] > 7499 && $zero_dep_amount) {
           
        //     return [
        //         'premium_amount' => '0',
        //         'status' => 'false',
        //         'message' => 'Zero dep is not allowed for  GVW Greater  Range 0-7499 ',
        //         'request' => [
        //             'productData' => $productData,
        //             'message' => 'Zero dep is not allowed for  GVW Greater  Range 0-7499',
        //             'car_age' => $car_age
        //         ]
        //     ];
        // }
        $parent_id = get_parent_code($productData->product_sub_type_id);
        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons_v2 = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        

        $LLtoPaidDriverSI=$ElectricalaccessSI = $PAforUnnamedPassengerSI = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKITSI = $antitheft = $LimitOwnPremiseYN = $LimitedTPPDYN = $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $userSelectedPercentage = 0;

        $countries = [];
    
        
        if ($selected_addons)
        {
            if (isset($selected_addons->accessories) && ! is_null($selected_addons->accessories))
            {
                foreach ($selected_addons->accessories as $accessories)
                {
                    if ($accessories['name'] == 'Electrical Accessories' && $tp_only == 'false')
                    {
                        $Electricalaccess = 1;
                        $ElectricalaccessSI = $accessories['sumInsured'];
                       
                    }
                    elseif ($accessories['name'] == 'Non-Electrical Accessories' && $tp_only == 'false')
                    {
                        $NonElectricalaccess = 1;
                        $NonElectricalaccessSI = $accessories['sumInsured'];
                        
                    }
                    elseif ($accessories['name'] == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $externalCNGKIT = 1;
                        $externalCNGKITSI = $accessories['sumInsured'];
                        if($accessories['sumInsured'] < 15000)
                        {
                            return [
                                'status' => false,
                                'message' => 'External Bi-Fuel Kit CNG/LPG value should be more than 15000.',
                                'request' => [
                                    'requestData' => $requestData,
                                    'accessories' => $accessories,
                                    'message' => 'External Bi-Fuel Kit CNG/LPG value should be more than 15000.',
                                ]
                            ]; 
                        } elseif ($externalCNGKITSI > 30000) {
   
                        }
                    }
                }
            }

            if (isset($selected_addons->discounts) && ! is_null($selected_addons->discounts))
            {
                foreach ($selected_addons->discounts as $discount)
                {
                    if ($discount['name'] == 'anti-theft device')
                    {
                        $antitheft = 1;
                    }
                    elseif ($discount['name'] == 'Vehicle Limited to Own Premises')
                    {
                        $LimitOwnPremiseYN = 1;
                    }
                    elseif ($discount['name'] == 'TPPD Cover')
                    {
                        $LimitedTPPDYN = 1;
                    }
                }
            }
         
          

     $NoOfCleaner =  $PAPaidDriverConductorCleanerSI  =  $NoOfConductor = $NoOfDriver = 0;
     $PAPaidDriverConductorCleanerYN = 'N';
     if (isset($selected_addons->additional_covers) && ! is_null($selected_addons->additional_covers))  
     {        
    foreach ($selected_addons->additional_covers as $key => $value) {
      
        if ($value['name'] == 'PA cover for additional paid driver'){
            $PAPaidDriverConductorCleanerYN = 1;
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
              
        if ($value['name'] == 'PA paid driver/conductor/cleaner') {
            $PAPaidDriverConductorCleanerYN = 1;
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
    }
}       
        //Have to send Y as we need premium to load the functionality from frotnend
        $addtowcharge = 'Y';

        // foreach ($addons_v2 as $key => $value) {
        //     if (in_array('Additional Towing', $value)) {
        //         $addtowcharge = 'Y';
        //     }
        // }
    $PAOwnerDriverExclusion = "0";
    $PAOwnerDriverExReason = "0";
   
    if($requestData->vehicle_owner_type = 'I'  ){
       
        $PAOwnerDriverExclusion = "";
        $PAOwnerDriverExReason = "";
    }


            $cover_pa_paid_driver = $cover_pa_unnamed_passenger = 'No';
            $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = $no_of_cleanerLL = $no_of_driverLL = $no_of_conductorLL = $total_no_of_coolie_cleaner = $no_ll_paid_driver  =  0;
            $LLtoPaidDriverYN = '0';
            $geoExtension = 'No';
           
            if (isset($selected_addons->additional_covers) && ! is_null($selected_addons->additional_covers))
            {
              
                foreach ($selected_addons->additional_covers as $additional_cover)
                {
                   
    
                    if ($additional_cover['name'] == 'LL paid driver ') 
                    {
                        $LLtoPaidDriverYN = '1';
                        $no_ll_paid_driver = 1;
                      
                    }
    
                    if ($additional_cover['name'] == 'LL paid driver/conductor/cleaner' && isset($additional_cover['LLNumberCleaner']) && $additional_cover['LLNumberCleaner'] > 0) 
                    {
                        $LLtoPaidDriverYN = '1';
                        $no_of_cleanerLL = $additional_cover['LLNumberCleaner'];
                       
                    }
    
                    if ($additional_cover['name']== 'LL paid driver/conductor/cleaner' && isset($additional_cover['LLNumberConductor']) && $additional_cover['LLNumberConductor'] > 0) 
                    {
                        $LLtoPaidDriverYN = '1';
                        $no_of_conductorLL = $additional_cover['LLNumberConductor'];
                    }
                    if ($additional_cover['name'] == 'LL paid driver/conductor/cleaner' && isset($additional_cover['LLNumberDriver']) && $additional_cover['LLNumberDriver'] > 0) 
                    {
                        $LLtoPaidDriverYN = '1';
                        $no_of_driverLL = $additional_cover['LLNumberDriver'];
                    }
        
                    if ($additional_cover['name'] == 'Geographical Extension') {
                    
                    
                        $countries = $additional_cover['countries'];
                   
                }
            }
          
        }
        
            $is_voluntary_access = 'No';
            $voluntary_excess_amt = 0;
            $TPPDCover = 'No';
            $noOfPaiddriverOrCleaner = 0;
            
            if($LLtoPaidDriverYN == '1')
            {
              
            $noOfPaiddriverOrCleaner =  $no_of_driverLL;
           
            }
          
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $posAgentName = $posAgentPanNo = $MISPDealerCode = '';
            $misp_testing_mode = config('MISP_TESTING_MODE_ENABLE_SHRIRAM_GCV') == 'Y';
            $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->whereIn('seller_type', ['P','misp'])
                    ->first();
            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
                if ($pos_data)
                {
                    $posAgentName = $pos_data->agent_name;
                    $posAgentPanNo = $pos_data->pan_no;
                }
            }else if(isset($pos_data->seller_type) && $pos_data->seller_type == 'misp'){
                $MISPDealerCode = $pos_data->relation_shriram;
                    if (empty($pos_data->relation_shriram) && !$misp_testing_mode)
                    {
                        return [
                            'status' => false,
                            'message' => 'MISP CODE not available',
                        ];
                    }
                    if($misp_testing_mode){
                        $MISPDealerCode = config('MISP_DEALER_CODE_SHRIRAM_GCV'); //'BRC0000796',
                    }
            }

        $zero_dep_amount = false;
        if ($productData->zero_dep == '0') {
            $zero_dep_amount = true;
        }
        // if ($car_age > 5 && $zero_dep_amount) {
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

        //  if ( $mmv['veh_gvw'] > 7499 && $zero_dep_amount) {
           
        //     return [
        //         'premium_amount' => '0',
        //         'status' => 'false',
        //         'message' => 'Zero dep is not allowed for  GVW Greater  Range 0-7499 ',
        //         'request' => [
        //             'productData' => $productData,
        //             'message' => 'Zero dep is not allowed for  GVW Greater  Range 0-7499',
        //             'car_age' => $car_age
        //         ]
        //     ];
        // }
        
     
           
        
        
        $applicable_addons = ['roadSideAssistance'];
        
      
        $imt_23 = 1;
        $roadsideassistance = 1;
        if ($tp_only == 'true') {
            $applicable_addon = [];
            $nilDepreciationCover = $PreviousNilDepreciation = $roadsideassistance = 0;
        }

        $vehicleClass = '';
        if ($mmv_data->no_of_wheels == '3' && $requestData->gcv_carrier_type == "PUBLIC") {
        $vehicleClass = "CLASS_4A3"; //GOODS CARRYING MOTORISED THREE WHEELERS AND MOTORISED PEDAL CYCLES- PUBLIC CARRIERS
                }
        elseif($mmv_data->no_of_wheels == '3'  && $requestData->gcv_carrier_type == "PRIVATE")
            {
        $vehicleClass = "CLASS_4A4"; //GOODS CARRYING MOTORISED THREE WHEELERS AND MOTORISED PEDAL  CYCLES- PRIVATE CARRIERS
            }
        elseif ($requestData->gcv_carrier_type == "PRIVATE") {
        $vehicleClass = "CLASS_4A2";  //GCCV-PRIVATE CARRIERS OTHER THAN THREE WHEELERS
            } elseif ($requestData->gcv_carrier_type == "PUBLIC") {
        $vehicleClass = "CLASS_4A1"; //GCCV-PUBLIC CARRIERS OTHER THAN THREE WHEELERS
             }
			 

        $inputArray = [
            "objPolicyEntryETT" => [
                "ReferenceNo" => "",
                "ProdCode" => 'MOT-PRD-003',//$mmv_data->vap_prod_code, // kit pref
                "PolicyFromDt" => $policy_start_date,
                "PolicyToDt" => $policy_end_date,
                "PolicyIssueDt" => today()->format('d-m-y'),
                "InsuredPrefix" => "1", // kit prefix
                "InsuredName" => ($requestData->user_fname ?? 'Shriram') . ' ' . ($requestData->user_lname ?? 'Insurance'), #9954
                "Gender" => '',
                "tpPolFmdt"=> "",
                "tpPolTodt"=> "",
                "tpPolNo"=> "",
                "tpPolComp"=> "",
                "tpPolAddr"=> "",
                "Address1" => 'sa',
                "Address2" => 'sas',
                "Address3" => 'sas',
                "State" => explode('-', $rto_code)[0],
                "City" => 'Mumbai',
                "PinCode" => '400005',
                "PanNo" => '',
                "GSTNo" => '',
                "TelephoneNo" => '',
                "ProposalType" => $proposalType, // kit
                "PolicyType" => $policy_type, // kit
                "DateOfBirth" => '',
                "MobileNo" => $requestData->user_mobile ?? '9999999999', #9954
                "FaxNo" => "",
                "EmailID" => $requestData->user_email ?? 'insurer@gmail.com', #9954
                "POSAgentName" => $posAgentName,
                "POSAgentPanNo" => $posAgentPanNo,
                "MISPDealerCode" => $MISPDealerCode,
                "CoverNoteNo" => "",
                "CoverNoteDt" => "",
                // "VehicleCategory" => $mmv_data->no_of_wheels == '3' ? "CLASS_4A3" : "CLASS_4A1",
                // "VehicleCategory" => $mmv_data->no_of_wheels == '3'   ? "CLASS_4A3" :  ($requestData->gcv_carrier_type=="PRIVATE" ? "CLASS_4A2" : "CLASS_4A1"), // "CLASS_4A3" in case of 3W
                "VehicleCategory" =>$vehicleClass,
                // "VehicleCode" =>"M_3598",
                "VehicleCode" => $mmv_data->veh_code,
                "FirstRegDt" => $requestData->vehicle_register_date, //  car regi date
                "VehicleType" => "U", // kit
                "EngineNo" => Str::upper(Str::random(8)),
                "ChassisNo" => Str::upper(Str::random(12)),
                "RegNo1" => $registration_numbers[0],
                "RegNo2" => $registration_numbers[1],
                "RegNo3" => $registration_numbers[2],
                "RegNo4" => $registration_numbers[3],
                "RTOCode" => $rto_code,
                "IDV_of_Vehicle" => 0,//'',
                "Colour" => '',
                "VoluntaryExcess" => '',
                "NoOfCleaner" => "",
                "NoOfDriver" => $no_ll_paid_driver,//"1",
                "NoOfConductor" => "",
                "VehicleMadeinindiaYN" => "",
                "VehiclePurposeYN" => "",
                "DriverAgeYN"=> "",
                "NFPP_Employees"  => '',
                "NFPP_OthThanEmp" => "",
                "LimitOwnPremiseYN" => $LimitOwnPremiseYN,   
                // "Bangladesh" => $car_age > 5   ? '' :  (in_array('Bangladesh', $countries) ? 1 : 0), //$car_age > 5   ? '' :  ($productData->zero_dep == 0 ? 'Y' : '') ,
                // "Bhutan" => $car_age > 5  ? '' :  (in_array('Bhutan', $countries) ? 1 : 0),
                // "Srilanka" =>$car_age > 5  ? '' :  (in_array('Sri Lanka', $countries) ? 1 : 0),
                // "Nepal" => $car_age > 5   ? '' :  (in_array('Nepal', $countries) ? 1 : 0),
                // "Pakistan" => $car_age > 5   ? '' :  (in_array('Pakistan', $countries) ? 1 : 0),
                // "Maldives" =>$car_age > 5    ? '' :  (in_array('Maldives', $countries) ? 1 : 0),                 
                "Bangladesh" =>   in_array('Bangladesh', $countries) ? 1 : 0, //$car_age > 5   ? '' :  ($productData->zero_dep == 0 ? 'Y' : '') ,
                "Bhutan" =>  in_array('Bhutan', $countries) ? 1 : 0,
                "SriLanka" =>  in_array('Sri Lanka', $countries) ? 1 : 0,
                "Nepal" =>  in_array('Nepal', $countries) ? 1 : 0,
                "Pakistan" =>  in_array('Pakistan', $countries) ? 1 : 0,
                "Maldives" =>in_array('Maldives', $countries) ? 1 : 0,                 
                "CNGKitYN" => $externalCNGKIT,
                "CNGKitSI" => $externalCNGKITSI,
                "InBuiltCNGKitYN"=> "0",
                "InBuiltCNGKit" => $requestData->fuel_type == 'CNG' ? 1 : 0,
                // "LimitedTPPDYN" =>  $car_age > 5 ? '' : $LimitedTPPDYN,
                //  "LimitedTPPDYN" =>   $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                "DeTariff" => 0,//$productData->default_discount == 0 ? 0 : $productData->default_discount,
                "SPLDISCOUNT" => 0,
                "IMT23" => $imt_23 ? '1' : false ,
                "BreakIn" => "No", // prev insu expiry date and today date
                "AddonPackage"=> "",
                "PreInspectionReportYN" => "0", 
                "PreInspection" => "",
                "FitnessCertificateno" => "",
                "FitnessValidupto" => "",
                "VehPermit" => "",
                "PermitNo" => "",
                "PAforUnnamedPassengerYN" => "",
                "PAforUnnamedPassengerSI" => "",
                // "ElectricalaccessYN" =>  $car_age > 5 ? '' : $Electricalaccess ,
                // "ElectricalaccessSI" =>  $car_age > 5 ? '' : $ElectricalaccessSI,
                "ElectricalaccessYN" =>   $Electricalaccess ,
                "ElectricalaccessSI" =>   $ElectricalaccessSI,
                "ElectricalaccessRemarks" => "",
                // "NonElectricalaccessYN" =>   $car_age > 5 ? '' : $NonElectricalaccess,
                // "NonElectricalaccessSI" =>   $car_age > 5  ? '' : $NonElectricalaccessSI,
                "NonElectricalaccessYN" =>   $NonElectricalaccess,
                "NonElectricalaccessSI" =>   $NonElectricalaccessSI,
                "NonElectricalaccessRemarks" => "",
                "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleanerYN,
                "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                "PAPaidDriverCount" => "1",
                "PAPaidConductorCount" => "1",
                "PAPaidCleanerCount" => "1",
                "NomineeNameforPAOwnerDriver" => '',
                "NomineeAgeforPAOwnerDriver" => '',
                "NomineeRelationforPAOwnerDriver" => '',
                "AppointeeNameforPAOwnerDriver" => "",
                "AppointeeRelationforPAOwnerDriver" => "",
                "AntiTheftYN" => $antitheft,
                // "LLtoPaidDriverYN"=> $car_age > 5 ? 0 :  $no_of_driverLL ,
                "LLtoPaidDriverYN"=>  $no_of_driverLL ,
                "NoEmpCoverLL"=> "1",
                "NomineeAgeforPAOwnerDriver"=> "26",
                "NomineeRelationforPAOwnerDriver"=> "Son",
                "AppointeeNameforPAOwnerDriver"=> "",
                "AppointeeRelationforPAOwnerDriver"=> "",
                "PreviousPolicyNo" => '45654645',
                "PreviousInsurer" => 'Acko General Insurance Ltd',
                "PreviousPolicyFromDt" => $PreviousPolicyFromDt,
                "PreviousPolicyToDt" => $PreviousPolicyToDt,
                "PreviousPolicySI" => "",
                "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0',
                "PreviousPolicyUWYear" => "",
                "PreviousPolicyType" => "MOT-PRD-003",
                "PreviousPolicyType" => $PreviousPolicyType,
                // "NilDepreciationCoverYN" => $car_age > 5   ? '' :  ($productData->zero_dep == 0  ? 'Y' : '') , 
                "NilDepreciationCoverYN" => $productData->zero_dep == 0  ? 'Y' : '' ,
                // "NilDepreciationCoverYN" =>$car_age > 5   ? '' :  ($productData->zero_dep == 0  ? 'Y' : '')  ,
                //  "NilDepreciationCoverYN" => $car_age > 5   ? '' :  ($productData->zero_dep == 0  && $mmv['veh_gvw'] <= 7499 ? 'Y' : '') , 
                "PreviousNilDepreciation" => $PreviousNilDepreciation,
                // "RSACover" => $car_age > 12  ? 'N':'Y',
                "RSACover" => $roadsideassistance,
                "DailyExpRemYN"=> "Y",
                "KeyReplacementYN"=> "Y",
                "LossOfPersonBelongYN"=> "Y",
                "EmergencyTranHotelExpRemYN"=> "Y",
                "MultiCarBenefitYN"=> "Y",
                "Eng_Protector"=> "Y",
                "HypothecationType" => '',
                "SHRIMOTORPROTECTION_YN"=> "N",//"Y",  //DISABLING AS REQUESTED BY SAHIL 20-08-2024 git #28278
                "Consumables"=> "Y",
                "HypothecationBankName" => '',
                "HypothecationAddress1" => '',
                "HypothecationAddress2" => '',
                "HypothecationAddress3" => '',
                "HypothecationAgreementNo" => '',
                "HypothecationCountry" => '',
                "HypothecationState" => '',
                "HypothecationCity" => '',
                "HypothecationPinCode" => '',
                "SpecifiedPersonField" => '',
                "AadharNo"=> "",
                "AadharEnrollNo"=> "",
                "PAOwnerDriverExclusion" => $PAOwnerDriverExclusion ,
                "PAOwnerDriverExReason" => $PAOwnerDriverExReason ,
                "TRANSFEROFOWNER" => (($requestData->ownership_changed ?? '') == 'Y') ? '1' : '0',
                "VehicleManufactureYear" => $manufacture_year,
                "LLtoCleanerYN" => $no_of_conductorLL,
                "LLtoConductorYN" => $no_of_conductorLL,
                "isTowingYN" => $addtowcharge
            ],
        ];

        if($requestData->rto_code == 'AP-39')
        {
            $inputArray['objPolicyEntryETT']['RTOCity'] = 'Prakasam';
        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'shriram', strtolower($parent_id));
            if ($agentDiscount['status'] ?? false) {
                $inputArray['objPolicyEntryETT']['DeTariff']    = $agentDiscount['discount'];
                $inputArray['objPolicyEntryETT']['SPLDISCOUNT'] = $agentDiscount['discount'];
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
    
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' =>  [
                'Username' => config('constants.IcConstants.shriram.SHRIRAMGCV_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Taxi',
            'productName' => $productData->product_name,
            'method' => 'Premium Calculation',
            'transaction_type' => 'quote',
        ];

      
        $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_GCV_CV_JSON_QUOTE_URL'), $inputArray, 'shriram', $additional_data);
       
       
        $response = $get_response['response'];   
   
        if ($response)
        {          
            $response = json_decode($response, true);

            $idv = $min_idv = $max_idv = 0;
            $skip_second_call = false;
            if ($response['MessageResult']['Result'] == 'Success')
            {
                if ( ! in_array($premium_type, ['third_party', 'third_party_breakin']))
                {
                    $idv = $response['GetQuotResult']['VehicleIDV'];
                    $min_idv = (int) ceil((int) $idv * 0.85);
                    $max_idv = (int) floor((int) $idv * 1.2);

                    if ($requestData->is_idv_changed == 'Y')
                    {                       	
                        if ($requestData->edit_idv >= $max_idv)
                        {
                            $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $max_idv;
                        }
                        elseif ($requestData->edit_idv <= $min_idv)
                        {
                            $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                        }
                        else
                        {
                            $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $requestData->edit_idv;
                        }
                    }
                    else
                    {
                        #$inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $idv;
                                $skip_second_call = true;
                                break;
                            case 'min_idv':
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                                break;
                            case 'max_idv':
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $max_idv;
                                break;
                            default:
                                $inputArray['objPolicyEntryETT']['IDV_of_Vehicle'] = $min_idv;
                                break;
                        }
                    }

                    $additional_data = [
                        'enquiryId' => $enquiryId,
                        'headers' =>  [
                            'Username' => config('constants.IcConstants.shriram.SHRIRAMGCV_USERNAME'),
                            'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ],
                        'requestMethod' => 'post',
                        'requestType' => 'json',
                        'section' => 'Taxi',
                        'productName' => $productData->product_name,
                        'method' => 'Premium Recalculation',
                        'transaction_type' => 'quote',
                    ];
                    if(!$skip_second_call){
                  
                    $get_response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_GCV_CV_JSON_QUOTE_URL'), $inputArray, 'shriram', $additional_data);
                    }
                   
                    $response = $get_response['response'];
                    if ($response)
                    {
                        $response = json_decode($response, true);

                        if ( ! isset($response['MessageResult']['Result']) || $response['MessageResult']['Result'] != 'Success')
                        {
                            return [
                                'status' => false,
                                'premium_amount' => 0,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $response['MessageResult']['ErrorMessage'] ?? 'Insurer not reachable'
                            ];
                        }

                        $idv = $response['GetQuotResult']['VehicleIDV'];
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'premium_amount' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Insurer not reachable'
                        ];
                    }
                }
                

                $final_payable_amount =  $imt_23 = $final_net_premium = $final_od_premium = $final_tp_premium = $basic_tp = $ncb_discount = $rsapremium = $anti_theft = $other_discount = $zero_dep_amount =  $pa_owner = $non_electrical_accessories = $lpg_cng_tp = $lpg_cng = $electrical_accessories = $basic_od  = $geoextensionod = $geoextensiontp = $tppd_discount = $limited_to_own_premises = $pa_unnamed = $ll_paid_driver = $pa_paid_driver = $ll_cleaner = $ll_conductor = 0;
                $shri_motor_protection = 0;
                $addtowchargeprem = 0;
                foreach ($response['GetQuotResult']['CoverDtlList'] as $key => $value)
                {
                    if ($value['CoverDesc'] == 'Road Side Assistance - OD')
                    {
                        $rsapremium = $value['Premium'];
                    }
                    if ($value['CoverDesc'] == 'Cover for lamps tyres / tubes mudguards bonnet /side parts bumpers headlights and paintwork of damaged portion only (IMT-23) - OD') {
                        $imt_23 = $value['Premium'];
                    }
                  
                    if ($value['CoverDesc'] == 'Basic Premium - OD')
                    {
                        $basic_od = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR41-Cover For Electrical and Electronic Accessories - OD')
                    {
                        $electrical_accessories = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'] , ['InBuilt  CNG  Cover - OD','InBuilt CNG Cover - OD']))
                    {
                        $lpg_cng = $value['Premium'];
                    }
                    if (in_array($value['CoverDesc'] , ['InBuilt  CNG  Cover - TP','InBuilt CNG Cover - TP']))
                    {
                        $lpg_cng_tp = $value['Premium'];
                    }
                   

                    /* if ($value['CoverDesc'] == 'InBuilt  CNG  Cover' || $value['CoverDesc'] == 'GR42-Outbuilt CNG\/LPG-Kit-Cover' || $value['CoverDesc'] ==  'GR42-Outbuilt CNG/LPG-Kit-Cover')
                    {
                        if ($value['Premium'] == 60)
                        {
                            $lpg_cng_tp = $value['Premium'];
                        }
                        else
                        {
                            $lpg_cng = $value['Premium'];
                        }
                    } */

                    if ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - OD') {
                        $lpg_cng = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR42-Outbuilt CNG/LPG-Kit-Cover - TP') {
                        $lpg_cng_tp = $value['Premium'];
                    }

                    /* if ($value['CoverDesc'] == 'GR4-Geographical Extension')
                    {
                        if ($value['Premium'] == 100)
                        {
                            $geoextensiontp = $value['Premium'];
                        }
                        else
                        {
                            $geoextensionod = $value['Premium'];
                        }
                    } */

                    if ($value['CoverDesc'] == 'GR4-Geographical Extension - OD') {
                        $geoextensionod = $value['Premium'];
                      
                    }

                    if ($value['CoverDesc'] == 'GR4-Geographical Extension - TP') {
                        $geoextensiontp = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Cover For Non Electrical Accessories - OD')
                    {
                        $non_electrical_accessories = $value['Premium'];
                    }

                  
                    if ($value['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER - TP')
                    {
                        $pa_owner = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Legal Liability Coverages For Paid Driver - TP')
                    {
                        $ll_paid_driver = $value['Premium'];
                    }
                    if ($value['CoverDesc'] == 'LL paid driver ')
                    {
                        $LLtoPaidDriverYN = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], ['LL TO PAID DRIVER', 'LL TO PAID CLEANER', 'LL TO PAID CONDUCTOR'])) {
                        $ll_paid_driver = $ll_paid_driver + $value['Premium'];
                    }
       

                    if ($value['CoverDesc'] == 'GR39A-Limit The Third Party Property Damage Cover - TP')
                    {
                        $tppd_discount = abs($value['Premium']);
                    }

                    if ($value['CoverDesc'] == 'GR35-Cover For Limited To Own Premises')
                    {
                        $limited_to_own_premises = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Nil Depreciation Cover - OD' || $value['CoverDesc'] == 'Nil Depreciation Loading - OD')
                    {
                        $zero_dep_amount += $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], ['De-Tariff Discount - OD','De-Tariff Discount','Special Discount','Special Discount - OD']))
                    {
                        $other_discount += abs($value['Premium']);
                    }

                    if ($value['CoverDesc'] == 'GR30-Anti Theft Discount Cover')
                    {
                        $anti_theft = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'Basic Premium - TP')
                    {
                        $basic_tp = $value['Premium'];
                    }
                    if ($value['CoverDesc'] == 'Basic Premium - TP')
                    {
                        $basic_tp = $value['Premium'];
                    }

                    if ($value['CoverDesc'] == 'GR36B3-PA-Paid Driver, Conductor,Cleaner - TP')
                    {
                        $pa_paid_driver = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array('Legal Liability Coverages For Conductor - TP'))) {
                        $ll_conductor = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array('Legal Liability Coverages For Cleaner - TP'))) {
                        $ll_cleaner = $value['Premium'];
                    }

                    if (in_array($value['CoverDesc'], array('Towing - OD','Towing'))){
                        $addtowchargeprem = $value['Premium'];
                    }
                    //DISABLING AS REQUESTED BY SAHIL 20-08-2024 git #28278

                    // if ($value['CoverDesc'] == 'Motor Protection - OD')
                    // {
                    //     $shri_motor_protection = $value['Premium'];
                    // }
                }
              

                $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $geoextensionod;
                $ncb_discount = ($final_od_premium - $other_discount) * ($requestData->applicable_ncb / 100);
                $final_tp_premium = $basic_tp + $lpg_cng_tp + $pa_unnamed +  $pa_paid_driver + $ll_paid_driver + $geoextensiontp + $ll_conductor + $ll_cleaner;
                $final_total_discount = $ncb_discount + $other_discount + $limited_to_own_premises + $tppd_discount;
                $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;
                $final_gst_amount = $final_net_premium * 0.18;
                $final_payable_amount = $final_net_premium + $final_gst_amount;
              
                $applicable_addons = ['roadSideAssistance',];
             
                if ($productData->zero_dep == 0 && $zero_dep_amount > 0)
                {
                    array_push($applicable_addons, "zero_depreciation");
                    array_push($applicable_addons, "imt23");
                }
                if ($productData->product_identifier == 'IMT-23')
                {
                   
                    array_push($applicable_addons, "imt23");

                }
           

                $business_types = [
                    'rollover' => 'Rollover',
                    'newbusiness' => 'New Business',
                    'breakin' => 'Break-in'
                ];

                if ($productData->zero_dep == 0 && $zero_dep_amount == 0)
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                        'request' => [
                            'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                        ]
                    ];
                }        
               elseif($productData->product_identifier == 'IMT-23'  && $imt_23 == 0)
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'IMT-23 Premium not available for IMT-23 Product',
                        'request' => [
                            'message' => 'IMT-23 Premium not available for IMT-23 Product',
                        ]
                    ];
                }

                $ribbonMessage = null;
                if ($other_discount > 0) {
                    $agentDiscountPercentage = round(($other_discount / ($final_od_premium - $electrical_accessories) * 100));
                    if ($userSelectedPercentage != $agentDiscountPercentage && $userSelectedPercentage > $agentDiscountPercentage) {
                        $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount') . ' ' . $agentDiscountPercentage . '%';
                    }
                }
             
                $data_response = [
                    'status' => true,
                    'msg' => 'Found',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'Data' => [
                        'idv' => $idv,
                        'min_idv' => $min_idv,
                        'max_idv' => $max_idv,
                        'vehicle_idv' => $idv,
                        'qdata' => null,
                        'pp_enddate' => $requestData->previous_policy_expiry_date,
                        'addonCover' => null,
                        'addon_cover_data_get' => '',
                        'rto_decline' => null,
                        'rto_decline_number' => null,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $rto_code,
                        'rto_no' => $rto_code,
                        'version_id' => $requestData->version_id,
                        'selected_addon' => [],
                        'showroom_price' => 0,
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'tppd_discount' => $tppd_discount,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                        'product_name' => $productData->product_sub_type_name,
                        "IMT23" =>  $imt_23,
                        "NilDepreciationCoverYN" => $productData->zero_dep == 0  ? 'Y' : '' ,
                        // "NilDepreciationCoverYN" =>$car_age > 5   ? '' :  ($productData->zero_dep == 0  ? 'Y' : '')  ,
                        //  "NilDepreciationCoverYN" => $car_age > 5   ? '' :  ($productData->zero_dep == 0  && $mmv['veh_gvw'] <= 7499 ? 'Y' : '') , 
                        "PreviousNilDepreciation" => $PreviousNilDepreciation,
                        // "LLtoPaidDriverYN"=>  $car_age > 5 ? 0 :  $no_of_driverLL , 
                        "LLtoPaidDriverYN"=>  $no_of_driverLL , 
                        "PAOwnerDriverExclusion" => $PAOwnerDriverExclusion ,
                        "PAOwnerDriverExReason" => $PAOwnerDriverExReason ,
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
                            'car_age' => 2, //$car_age,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' => round(abs($other_discount)),
                        ],
                        'ribbon' => $ribbonMessage,
                        'ic_vehicle_discount' => round(abs($other_discount)),
                        'basic_premium' => round($basic_od),
                        'motor_electric_accessories_value' => round($electrical_accessories),
                        'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                        'motor_lpg_cng_kit_value' => round($lpg_cng),
                        'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                        'total_own_damage' => round($final_od_premium),
                        'tppd_premium_amount' => round($basic_tp),
                        'compulsory_pa_own_driver' => round($pa_owner),
                        'GeogExtension_ODPremium' => $geoextensionod,
                        'GeogExtension_TPPremium' => $geoextensiontp,
                        'default_paid_driver' => round($ll_paid_driver),
                        'll_paid_driver_premium' => $ll_paid_driver,
                        'll_paid_conductor_premium' => $ll_conductor,
                        'll_paid_cleaner_premium' => $ll_cleaner,
                        'motor_additional_paid_driver' => round($pa_paid_driver),
                        'cng_lpg_tp' => round($lpg_cng_tp),
                        'deduction_of_ncb' => round(abs($ncb_discount)),
                        'aai_discount' => '', //$automobile_association,
                        'voluntary_excess' => '', //$voluntary_excess,
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
                        'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type] ?? 'Rollover',
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
                        'add_ons_data' => [
                            'in_built' => [],
                            'additional' => [
                                'road_side_assistance' => round($rsapremium),
                                'additional_towing'    => $addtowchargeprem      
                            ],
                            'in_built_premium'   => 0,
                            'additional_premium' => array_sum( [$rsapremium, $addtowchargeprem]),//, $imt_23] ),
                            'other_premium'      => 0,
                        ],
                        'applicable_addons' => $applicable_addons,
                        'final_od_premium' => round($final_od_premium),
                        'final_tp_premium' => round($final_tp_premium),
                        'final_total_discount' => round(abs($final_total_discount)),
                        'final_net_premium' => round($final_net_premium),
                        'final_gst_amount' => round($final_gst_amount),
                        'final_payable_amount' => round($final_payable_amount),
                        'distance' => 5000,
                        'additional_towing_options' => [
                            '5000'
                        ],
                        'mmv_detail' => [
                            'manf_name' => $mmv_data->manf,
                            'model_name' => $mmv_data->model_desc,
                            'version_name' => '',
                            'fuel_type' => $mmv_data->fuel,
                            'seating_capacity' => $mmv_data->veh_seat_cap,
                            'carrying_capacity' => $mmv_data->veh_seat_cap,
                            'cubic_capacity' => $mmv_data->veh_cc,
                            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? '',
                            'vehicle_type' => 'GCV',
                        ],
                    ],
                ];

            
                // if ( $shri_motor_protection > 0 && !in_array($premium_type, ['third_party', 'third_party_breakin'])) 
                // { 
                //     $data_response['Data']['add_ons_data']['other']['Motor_Protection_OD'] = round($shri_motor_protection);

                // }

             
//  $applicable_addons = ($car_age > 12) ? array_splice($applicable_addons, array_search("roadSideAssistance", $applicable_addons), 1) : $applicable_addons;


                // if ($car_age > 5 && $productData->zero_dep) {
                   
                //     return [
                //         'premium_amount' => '0',
                //         'status' => 'false',
                //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                //         'request' => [
                //             'productData' => $productData,
                //             'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                //             'car_age' => $car_age
                //         ]
                //     ];
                // }
                // if ( $mmv['veh_gvw'] > 7499 && $productData->zero_dep) {
                   
                //     return [
                //         'premium_amount' => '0',
                //         'status' => 'false',
                //         'message' => 'Zero dep is not allowed for  GVW Greater  Range 0-7499',
                //         'request' => [
                //             'productData' => $productData,
                //             'message' => 'Zero dep is not allowed for  GVW Greater  Range 0-7499',
                //             'car_age' => $car_age
                //         ]
                //     ];
                // }
         
                if ($productData->zero_dep == 0 && $zero_dep_amount > 0)
                {
                   
                   
                    $data_response['Data']['add_ons_data']['in_built']['zero_depreciation'] = round($zero_dep_amount);
                    $data_response['Data']['add_ons_data']['in_built']['imt23'] = round($imt_23);
                   
                    array_push($applicable_addons, "zero_depreciation");
                    array_push($applicable_addons, "imt23");
                 
                           
                   
                }
                   
                if ($productData->product_identifier == 'IMT-23')
                {
                    
                    $data_response['Data']['add_ons_data']['in_built']['imt23'] = round($imt_23);

                }

              

              
                if ($data_response['Data']['motor_lpg_cng_kit_value'] == 0)
                {
                    unset($data_response['Data']['motor_lpg_cng_kit_value']);
                }

                if ($data_response['Data']['cng_lpg_tp'] == 0)
                {
                    unset($data_response['Data']['cng_lpg_tp']);
                }

                if ($data_response['Data']['motor_additional_paid_driver'] == 0)
                {
                    unset($data_response['Data']['motor_additional_paid_driver']);
                }

                if ($data_response['Data']['motor_electric_accessories_value'] == 0)
                {
                    unset($data_response['Data']['motor_electric_accessories_value']);
                }

                if ($data_response['Data']['motor_non_electric_accessories_value'] == 0)
                {
                    unset($data_response['Data']['motor_non_electric_accessories_value']);
                }

                if ($data_response['Data']['GeogExtension_ODPremium'] == 0)
                {
                    unset($data_response['Data']['GeogExtension_ODPremium']);
                }

                return camelCase($data_response);
            }
            else
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'msg' => $response['MessageResult']['ErrorMessage'] ?? 'Insurer not reachable'
                ];
            }
        }
        else
        {
            return [
                'status' => false,
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    }
}

}