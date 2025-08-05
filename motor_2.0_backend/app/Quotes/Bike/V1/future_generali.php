<?php

use App\Models\IcVersionMapping;
use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\BikeManufacturer;
use App\Models\BikeModel;
use App\Models\BikeModelVersion;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

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
    // mmv checks
    // $mmv_data = DB::table('ic_version_mapping as icvm')
    // ->leftJoin('future_generali_model_master as cvrm', 'cvrm.vehicle_code', '=', 'icvm.ic_version_code')
    // ->where([
    //     'icvm.fyn_version_id' => trim($requestData->version_id),
    //     'icvm.ic_id' => trim($productData->company_id)
    // ])
    // ->select('icvm.*', 'cvrm.*')
    // ->first();

    // $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);
    $vehicle_block_data = DB::table('vehicle_block_data')
                        ->where('registration_no', str_replace("-", "",$requestData->vehicle_registration_no))
                        ->where('status', 'Active')
                        ->select('ic_identifier')
                        ->get()
                        ->toArray();
    if(isset($vehicle_block_data[0]))
    {
        $block_bool = false;
        $block_array = explode(',',$vehicle_block_data[0]->ic_identifier);
        if(in_array('ALL',$block_array))
        {
            $block_bool = true;
        }
        else if(in_array($productData->company_alias,$block_array))
        {
           $block_bool = true; 
        }
        if($block_bool == true)
        {
            return  [
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => $requestData->vehicle_registration_no." Vehicle Number is Declined",
                'request'           => [
                    'message'           => $requestData->vehicle_registration_no." Vehicle Number is Declined",
                ]
            ];            
        }        
    }
    
    $mmv = get_mmv_details($productData,$requestData->version_id,'future_generali');

    
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
            'request'=>$mmv
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
    
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>$mmv_data
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>$mmv_data
        ];
    }


    $mmv_data->manf_name = $mmv_data->make;
    $mmv_data->model_name = $mmv_data->model;
    $mmv_data->version_name = '';//$mmv_data->model;
    $mmv_data->seating_capacity = $mmv_data->seating_capacity;
    $mmv_data->cubic_capacity = $mmv_data->cc;
    $mmv_data->fuel_type = $mmv_data->fuel_code;
    $mmv_data->kw = $mmv_data->cc;

    // bike age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $bike_age = ceil($age / 12);
    $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code','premium_type')
        ->first();
    $premium_type = $premium_type_array->premium_type_code;
    $policy_type = $premium_type_array->premium_type;
    
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
//    if (($interval->y >= 15) && ($tp_check == 'true')){
//        return [
//            'premium_amount' => 0,
//            'status' => false,
//            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
//        ];
//    }
//    if ((($bike_age > 15) || ($interval->y == 15 && ($interval->m > 0 || $interval->d > 0))))  //changes as per kit #20121
//    {
//        return [
//            'status'         => false,
//            'message'        => 'Quotes are not available for vehicle age above 15 years',
//        ];
//    }

    // if (($bike_age > 5) && ($productData->zero_dep == '0'))
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status'         => false,
    //         'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //         'request'=> [
    //             'bike_age'=> $bike_age,
    //             'product_data' => $productData->zero_dep
    //         ] 
    //     ];
    // }

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


    try{

        $IsPos = 'N';
        $is_FG_pos_disabled = config('constants.motorConstant.IS_FG_POS_DISABLED');
        $is_pos_enabled = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = ($is_FG_pos_disabled == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_FUTURE_GENERALI');
        //$is_employee_enabled = config('IC.FUTURE_GENERALI.V1.BIKE.IS_EMPLOYEE_ENABLED');
        $PanCardNo = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code

        $rto_data = DB::table('future_generali_rto_master')
                ->where('rta_code', strtr($rto_code, ['-' => '']))
                ->first();
        if (empty($rto_data)) {
            return [
                'status' => false,
                'message' => 'RTO not available.',
                'request' => [
                    'message' => 'RTO not available.',
                    'requestData' => $requestData
                ]
            ];
        }
        $usedCar = 'N';  

        if ($requestData->business_type == 'newbusiness')
        {
            $bike_no_claim_bonus = '0';
            $bike_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = 'N';
            $ncb_declaration = 'N';
            $Newbike = 'Y';
            $rollover = 'N';
            $policy_start_date = date('d/m/Y');
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $PolicyNo = $InsuredName = $PreviousPolExpDt = $ClientCode = $Address1 = $Address2 = $tp_start_date = $tp_end_date ='';
            $contract_type = 'F15';
            $risk_type = 'F15';
            $reg_no = str_replace('-', '', $requestData->rto_code.'-AB-1234');
            /* if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }

            }
            elseif($pos_testing_mode === 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                $contract_type = 'P15';
                $risk_type = 'F15';
            } */

        }
        else
        {
            $current_date = date('Y-m-d');
            $expdate = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $vehicle_in_30_days = get_date_diff('day', $current_date, $expdate);

            if ($requestData->business_type == 'rollover' &&  $vehicle_in_30_days > 30) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Future Policy Expiry date is allowed only upto 30 days',
                    'request' => [
                        'car_age' => $bike_age,
                        'date_duration_in_days' => $vehicle_in_30_days,
                        'message' => 'Future Policy Expiry date is allowed only upto 30 days',
                    ]
                ];
            }
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                
            }
            $bike_no_claim_bonus = '0';
            $bike_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = $requestData->is_claim;
            $ncb_declaration = 'N';
            $Newbike = 'N';
            $rollover = 'Y';
            $today_date = date('Y-m-d');
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+3 day"));
            } else {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
            }

            if($requestData->previous_policy_type == 'Not sure')
            {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
                $usedCar = 'Y';
                $rollover = 'N';     
            }

            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $PolicyNo = '1234567';
            $InsuredName = 'Bharti Axa General Insurance Co. Ltd.';
            $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
            $ClientCode = '43207086';
            $Address1 = 'ABC';
            $Address2 = 'PQR';
            $tp_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year', strtotime($requestData->previous_policy_expiry_date)))));
            $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
            $reg_no = isset($requestData->vehicle_registration_no) ? str_replace("-", "", $requestData->vehicle_registration_no) : '';
            // check bike policy expired more than 90 days
            
            if($date_diff > 90)
            {
              $bike_expired_more_than_90_days = 'Y';
            }
            else
            {
                $bike_expired_more_than_90_days = 'N';
             
            }


            if ($claimMadeinPreviousPolicy == 'N' && $bike_expired_more_than_90_days == 'N' && $premium_type != 'third_party') {

                $ncb_declaration = 'Y';
                $bike_no_claim_bonus = $requestData->previous_ncb;
                $bike_applicable_ncb = $requestData->applicable_ncb;

            }
            else
            {
                $ncb_declaration = 'N';
                $bike_no_claim_bonus = '0';
                $bike_applicable_ncb = '0';
            }

            if($claimMadeinPreviousPolicy == 'Y' && $premium_type != 'third_party') {
                $bike_no_claim_bonus = $requestData->previous_ncb;
            }
            if($requestData->previous_policy_type == 'Third-party')
            {
                $ncb_declaration = 'N';
                $bike_no_claim_bonus = '0';
                $bike_applicable_ncb = '0';
            }

            if($premium_type== "own_damage")
            {
               $contract_type = 'TWO';
               $risk_type = 'TWO';

            }
            else
            {   
                $contract_type = 'FTW';
                $risk_type = 'FTW';
            }

            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }
                }

                if($pos_testing_mode === 'Y')
                {
                    $IsPos = 'Y';
                    $PanCardNo = 'ABGTY8890Z';
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                }

            }
            elseif($pos_testing_mode === 'Y')
            {
                $IsPos = 'Y';
                $PanCardNo = 'ABGTY8890Z';
                if($premium_type== "own_damage")
                {
                   $contract_type = 'PWO';
                   $risk_type = 'TWO';

                }
                else
                {   
                    $contract_type = 'PTW';
                    $risk_type = 'FTW';
                }
                if($requestData->business_type == 'newbusiness')
                {
                    $contract_type = 'P15';
                    $risk_type = 'F15';
                }
            }
        }

        if($is_pos_enabled == 'Y')
            {
                if($pos_data)
                {
                    $IsPos = 'Y';
                    $PanCardNo = $pos_data->pan_no;
                    if($premium_type== "own_damage")
                    {
                       $contract_type = 'PWO';
                       $risk_type = 'TWO';

                    }
                    else
                    {   
                        $contract_type = 'PTW';
                        $risk_type = 'FTW';
                    }
                    if($requestData->business_type == 'newbusiness')
                    {
                        $contract_type = 'P15';
                        $risk_type = 'F15';
                    }
                }
            }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_FUTURE_GENERALI') == 'Y')
        {
            $IsPos = 'Y';
            $PanCardNo = 'ABGTY8890Z';
            if($requestData->business_type == 'newbusiness')
            {
                $contract_type = 'P15';
                $risk_type = 'F15';
            }
            else
            {
                if($premium_type== "own_damage")
                {
                   $contract_type = 'PWO';
                   $risk_type = 'TWO';
                }
                else
                {   
                    $contract_type = 'PTW';
                    $risk_type = 'FTW';
                }
            }
        }
        
        $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_year_data = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }
        if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" && $premium_type != "own_damage_breakin") 
        {
            $CPAReq = 'Y';
            $cpa_nom_name = 'Legal Hair';
            $cpa_nom_age = '21';
            $cpa_nom_age_det = 'Y';
            $cpa_nom_perc = '100';
            $cpa_relation = 'SPOU';
            $cpa_appointee_name = '';
            $cpa_appointe_rel = '';
            if ($requestData->business_type == 'newbusiness')
            {
                $cpa_year= isset($cpa_year_data) ? $cpa_year_data : '5';
                // $cpa_year = '5';
            }
            else
            {
                $cpa_year= isset($cpa_year_data) ? $cpa_year_data : '1';
            }
            
        } 
        else 
        {
            $CPAReq = 'N';
            $cpa_nom_name = '';
            $cpa_nom_age = '';
            $cpa_nom_age_det = '';
            $cpa_nom_perc = '';
            $cpa_relation = '';
            $cpa_appointee_name = '';
            $cpa_appointe_rel = '';
            $cpa_year = '';
        }
        if ($requestData->vehicle_owner_type == 'I'  && $premium_type != "own_damage") {
            if ($requestData->business_type == 'newbusiness')
            {
                $cpa_year = !empty($cpa_year_data) ? $cpa_year_data : 3;
            }
            else
            {
                $cpa_year = !empty($cpa_year_data) ? $cpa_year_data : 1;
            }   
        }

        


        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
        $addon = [];
        $AddonReq = 'N';
        if (/*($bike_age <= 5) && */($productData->zero_dep == 0) || $productData->product_identifier == 'ZERO_DEP')
        {
            $AddonReq = 'Y';
            $addon[] = [
                'CoverCode' => 'ZODEP',
                ];  
        }


        if($productData->product_identifier == "BASIC")
        {
            $addon[]  = ['CoverCode' => ''];

        }
        elseif($productData->product_identifier == 'WITH_BASIC_ADDON')
        {
        $AddonReq = 'Y';
            $addon[] = [
                'CoverCode' => 'RODSA',
                ];
            $addon[] = [
                'CoverCode' => 'CONSM',
                ];  
        }
        

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = 'false';

        $is_electrical = false;
        $is_non_electrical = false;

        if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
            {
                $accessories = ($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if($value['name'] == 'Electrical Accessories')
                    {
                        $is_electrical = true;
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'Non-Electrical Accessories')
                    {
                        $is_non_electrical = true;
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value['sumInsured'];
                    }
                    else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $type_of_fuel = '5';
                        $bifuel = 'true';
                        $Fueltype = 'CNG';
                        $BiFuelKitSi = $value['sumInsured'];
                    }
                }
            }

             //PA for un named passenger
             $IsPAToUnnamedPassengerCovered = 'false';
             $PAToUnNamedPassenger_IsChecked = '';
             $PAToUnNamedPassenger_NoOfItems = '';
             $PAToUnNamedPassengerSI = 0;
             $IsLLPaidDriver = '';
             $is_geo_ext = false;

             if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = $selected_addons->additional_covers;
                foreach ($additional_covers as $value) {
                   if($value['name'] == 'Unnamed Passenger PA Cover')
                   {
                        $IsPAToUnnamedPassengerCovered = 'true';
                        $PAToUnNamedPassenger_IsChecked = 'true';
                        $PAToUnNamedPassenger_NoOfItems = '1';
                        $PAToUnNamedPassengerSI = $value['sumInsured'];
                   }
                   if($value['name'] == 'LL paid driver')
                   {
                        $IsLLPaidDriver = '1';
                   }

                   if ($value['name'] == 'Geographical Extension') {
                       $is_geo_ext = true;
                       $countries = $value['countries'];
                   }
                }
            }


            $IsAntiTheftDiscount = 'false';

            if($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '')
            {
                $discount = $selected_addons->discount;
                foreach ($discount as $value) {
                   if($value->name == 'anti-theft device')
                   {
                        $IsAntiTheftDiscount = 'true';
                   }
                }
            }

        $idv = 0;


        

        switch($premium_type)
        {
            case "comprehensive":
            $cover_type = "CO";
            break;
            case "own_damage":
            $cover_type = "OD";

            break;
            case "third_party":
            $cover_type = "LO";
            break;

            case "breakin":
            $cover_type = "CO";
            break;

            case "own_damage_breakin":
            $cover_type = "OD";
            break;

            case "third_party_breakin":
            $cover_type = "LO";
            break;

        }


        $quote_array = [
            '@attributes'  => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            ],
            'Uid'          => time().rand(10000, 99999), //date('Ymshis').rand(0,9),
            'VendorCode'   => config('IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE'),
            'VendorUserId' =>  config('IC.FUTURE_GENERALI.V1.BIKE.VENDOR_CODE'),
            'PolicyHeader' => [
                'PolicyStartDate' => $policy_start_date,
                'PolicyEndDate'   => $policy_end_date,
                'AgentCode'       => config('IC.FUTURE_GENERALI.V1.BIKE.AGENT_CODE'),
                'BranchCode'      => ($IsPos == 'Y') ? '' : config('IC.FUTURE_GENERALI.V1.BIKE.BRANCH_CODE'),
                'MajorClass'      => 'MOT',
                'ContractType'    => $contract_type,
                'METHOD'          => 'ENQ',
                'PolicyIssueType' => 'I',
                'PolicyNo'        => '',
                'ClientID'        => '',
                'ReceiptNo'       => '',
            ],
            'POS_MISP'     => [
                'Type'  => ($IsPos == 'Y') ? 'P' : '',
                'PanNo' => ($IsPos == 'Y') ? $PanCardNo : '',
            ],
            'Client'       => [
                'ClientType'    => $requestData->vehicle_owner_type,
                'CreationType'  => 'C',
                'Salutation'    => '',
                'FirstName'     => '',
                'LastName'      => '',
                'DOB'           => '',
                'Gender'        => '',
                'MaritalStatus' => '',
                'Occupation'    => 'OTHR',
                'PANNo'         => '',
                'GSTIN'         => '',
                'AadharNo'      => '',
                'EIANo'         => '',
                'CKYCNo'        => '',
                'CKYCRefNo'     => '',
                
                'Address1'      => [
                    'AddrLine1'   => '',
                    'AddrLine2'   => '',
                    'AddrLine3'   => '',
                    'Landmark'    => '',
                    'Pincode'     => '',
                    'City'        => '',
                    'State'       => '',
                    'Country'     => '',
                    'AddressType' => '',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => '',
                    'EmailAddr'   => '',
                ],
                'Address2'      => [
                    'AddrLine1'   => '',
                    'AddrLine2'   => '',
                    'AddrLine3'   => '',
                    'Landmark'    => '',
                    'Pincode'     => '',
                    'City'        => '',
                    'State'       => '',
                    'Country'     => '',
                    'AddressType' => '',
                    'HomeTelNo'   => '',
                    'OfficeTelNo' => '',
                    'FAXNO'       => '',
                    'MobileNo'    => '',
                    'EmailAddr'   => '',
                ],
            ],
            'Receipt'      => [
                'UniqueTranKey'   => '',
                'CheckType'       => '',
                'BSBCode'         => '',
                'TransactionDate' => '',
                'ReceiptType'     => '',
                'Amount'          => '',
                'TCSAmount'       => '',
                'TranRefNo'       => '',
                'TranRefNoDate'   => '',
            ],
            'Risk'         => [
                'RiskType'          => $risk_type,
                'Zone'              => $rto_data->zone,
                'Cover'             => $cover_type,
                'Vehicle'           => [
                    'TypeOfVehicle'           => '',
                    'VehicleClass'            => '',
                    'RTOCode'                 => $rto_data->rta_code,//str_replace('-', '', $requestData->rto_code),
                    'Make'                    => $mmv_data->make,
                    'ModelCode'               => $mmv_data->vehicle_model_code,
                    'RegistrationNo'          => $requestData->business_type == 'newbusiness' ? '' : (!empty($reg_no) ? $reg_no : str_replace('-', '', $rto_data->rta_code.'-AB-1234')),
                    'RegistrationDate'        => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'ManufacturingYear'       => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'FuelType'                => $requestData->fuel_type == 'PETROL' ? 'P' : ($requestData->fuel_type == 'DIESEL' ? 'D' : ''),
                    'CNGOrLPG'                => [
                        'InbuiltKit'    =>  $requestData->fuel_type != 'Petrol' && $requestData->fuel_type != 'DIESEL' ? 'N' : 'Y',
                        'IVDOfCNGOrLPG' => $bifuel == 'true' ? $BiFuelKitSi : '',
                    ],
                    'BodyType' => 'TWWH',
                    'EngineNo' => 'TESTENGINEE123456',
                    'ChassiNo' => 'TESTCHASSIS876789',
                    'CubicCapacity'           => $mmv_data->cc,
                    'SeatingCapacity'         => $mmv_data->seating_capacity,
                    'IDV'                     => $idv,
                    'GrossWeigh'              => '',
                    'CarriageCapacityFlag'    => '',
                    'ValidPUC'                => 'Y',
                    'TrailerTowedBy'          => '',
                    'TrailerRegNo'            => '',
                    'NoOfTrailer'             => '',
                    'TrailerValLimPaxIDVDays' => '',
                ],
                'InterestParty'     => [
                    'Code'     => '',
                    'BankName' => '',
                ],
                'AdditionalBenefit' => [
                    'Discount'                                 => '',
                    'ElectricalAccessoriesValues'              => $IsElectricalItemFitted == 'true' ? $ElectricalItemsTotalSI : '',
                    'NonElectricalAccessoriesValues'           => $IsNonElectricalItemFitted == 'true' ? $NonElectricalItemsTotalSI : '',
                    'FibreGlassTank'                           => '',
                    'GeographicalArea'                         => '',
                    'PACoverForUnnamedPassengers'              => $IsPAToUnnamedPassengerCovered == 'true' ? $PAToUnNamedPassengerSI : '',
                    'LegalLiabilitytoPaidDriver'               => $IsLLPaidDriver,
                    'LegalLiabilityForOtherEmployees'          => '',
                    'LegalLiabilityForNonFarePayingPassengers' => '',
                    'UseForHandicap'                           => '',
                    'AntiThiefDevice'                          => '',
                    'NCB'                                      => $bike_applicable_ncb,
                    'RestrictedTPPD'                           => '',
                    'PrivateCommercialUsage'                   => '',
                    'CPAYear' => $cpa_year, 
                    'IMT23'                                    => '',
                    'CPAReq'                                   => $CPAReq,
                    'CPA'                                      => [
                        'CPANomName'       => $cpa_nom_name,
                        'CPANomAge'        => $cpa_nom_age,
                        'CPANomAgeDet'     => $cpa_nom_age_det,
                        'CPANomPerc'       => $cpa_nom_perc,
                        'CPARelation'      => $cpa_relation,
                        'CPAAppointeeName' => $cpa_appointee_name,
                        'CPAAppointeRel'   => $cpa_appointe_rel
                    ],
                    'NPAReq'              => 'N',
                    'NPA'                 => [
                        'NPAName'         => '',
                        'NPALimit'        => '',
                        'NPANomName'      => '',
                        'NPANomAge'       => '',
                        'NPANomAgeDet'    => '',
                        'NPARel'          => '',
                        'NPAAppinteeName' => '',
                        'NPAAppinteeRel'  => '',
                    ],
                ],
                'AddonReq'          =>   !(in_array($premium_type, ['third_party', 'third_party_breakin'])) ? $AddonReq : 'N',
                'Addon'             => !empty($addon) && !(in_array($premium_type, ['third_party' ,'third_party_breakin'])) ? $addon : ['CoverCode' => ''],
                'PreviousTPInsDtls' =>[
                            'PreviousInsurer' => ($premium_type == "own_damage") ? 'RG':'',
                            'TPPolicyNumber' => ($premium_type== "own_damage") ? 'RG874592': '',
                            'TPPolicyEffdate' => ($premium_type == "own_damage") ? $tp_start_date :'',
                            'TPPolicyExpiryDate' => ($premium_type == "own_damage") ? $tp_end_date :''

                            ],
                'PreviousInsDtls'   => [
                    'UsedCar' => $usedCar,
                    'UsedCarList' => [
                            'PurchaseDate' => ($usedCar == 'Y') ? date('d/m/Y', strtotime($requestData->vehicle_register_date)) : '', 
                            'InspectionRptNo' =>'', 
                            'InspectionDt' => '',
                        ],

                    'RollOver'       => $rollover,
                    'RollOverList'   => [
                        'PolicyNo'              => ($rollover == 'N') ? '' :'1234567',
                        'InsuredName'           => ($rollover == 'N') ? '' :$InsuredName,
                        'PreviousPolExpDt'      => ($rollover == 'N') ? '' :$PreviousPolExpDt,
                        'ClientCode'            => ($rollover == 'N') ? '' :$ClientCode,
                        'Address1'              => ($rollover == 'N') ? '' :$Address1,
                        'Address2'              => ($rollover == 'N') ? '' :$Address2,
                        'Address3'              => '',
                        'Address4'              => '',
                        'Address5'              => '',
                        'PinCode'               => ($rollover == 'N') ? '' :'400001',
                        'InspectionRptNo'       => '',
                        'InspectionDt'          => '',
                        'NCBDeclartion'         => ($rollover == 'N') ? 'N' :$ncb_declaration,
                        'ClaimInExpiringPolicy' => ($rollover == 'N') ? 'N' :$claimMadeinPreviousPolicy,
                        'NCBInExpiringPolicy'   => ($rollover == 'N') ? 0 :$bike_no_claim_bonus,
                    ],
                    'NewVehicle'     => $Newbike,
                    'NewVehicleList' => [
                        'InspectionRptNo' => '',
                        'InspectionDt'    => '',
                    ],
                ],
            ],
        ];
       
        $data = $quote_array;
        unset($data['Uid']);
        $checksum_data = checksum_encrypt($data);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'future_generali',$checksum_data,'BIKE');
        $additional_data = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'CreatePolicy',
            'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
            'method' => 'Quote Generation',
            'section' => 'bike',
            'transaction_type' => 'quote',
            'checksum' => $checksum_data,
            'productName'  => $productData->product_name
        ];
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
            $get_response = $is_data_exits_for_checksum;
        }else{
            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
        }
        
        
        $data = $get_response['response'];
        if ($data) 
        {
            $quote_output = html_entity_decode($data);
            $quote_output = XmlToArray::convert($quote_output);
            
            $skip_second_call = false;
            if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'])) 
            {
                $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

                if ($quote_output['Status'] == 'Fail') {
                    if (isset($quote_output['Error'])) {
                        return [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                        ];
                    } elseif (isset($quote_output['ErrorMessage'])) {
                        return [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                        ];
                    } elseif (isset($quote_output['ValidationError'])) {

                        return [
                            'webservice_id'=> $get_response['webservice_id'],
                            'table'=> $get_response['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                        ];
                    }
                }else{
                    update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quote Generation - Success", "Success" );
                    
                    if (isset($quote_output['VehicleIDV'])) {
                        $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                    }

                    $idv = ($premium_type == 'third_party') ? 0 : round($quote_output['VehicleIDV']);
                    $min_idv = ceil($idv * 0.9);   //changes as per kit #20121
                    $max_idv = floor($idv * 1.1);

                    // idv change condition
                    if ($requestData->is_idv_changed == 'Y') {
                        if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                            $idv = floor($max_idv);
                        } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                            $idv = ceil($min_idv);
                        } else {
                            $idv = $requestData->edit_idv;
                        }
                    }else{
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $quote_output['VehicleIDV'] = $idv;
                                $skip_second_call = true;
                                $idv = $idv;
                                break;
                            case 'min_idv':
                                $quote_output['VehicleIDV'] = $idv;
                                $idv = $min_idv;
                                break;
                            case 'max_idv':
                                $quote_output['VehicleIDV'] = $max_idv;
                                $idv = $max_idv;
                                break;
                            default:
                            $quote_output['VehicleIDV'] = $idv;
                                $idv = $min_idv;
                                break;
                        }
                        // $idv = $min_idv;
                    }

                    $quote_array['Risk']['Vehicle']['IDV'] =  $idv;
                    $quote_array['Uid'] = time().rand(10000, 99999);

                    $additional_data = [
                        'requestMethod' => 'post',
                        'enquiryId' => $enquiryId,
                        'soap_action' => 'CreatePolicy',
                        'container'   => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><CreatePolicy xmlns="http://tempuri.org/"><Product>Motor</Product><XML>#replace</XML></CreatePolicy></Body></Envelope>',
                        'method' => 'Quote Generation - IDV changed',
                        'section' => 'bike',
                        'transaction_type' => 'quote',
                        'productName'  => $productData->product_name
                    ];

                    if(!$skip_second_call) {
                        $data = $quote_array;
                        unset($data['Uid']);
                        $checksum_data = checksum_encrypt($data);
                        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'future_generali',$checksum_data,'BIKE');
                        $additional_data['checksum'] = $checksum_data;
                        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                            $get_response = $is_data_exits_for_checksum;
                        }else{
                            $get_response = getWsData(config('IC.FUTURE_GENERALI.V1.BIKE.END_POINT_URL'), $quote_array, 'future_generali', $additional_data);
                        } 
                    }

                    $data = $get_response['response'];
                    if ($data) {
                        $quote_output = html_entity_decode($data);
                        $quote_output = XmlToArray::convert($quote_output);

                        if (isset($quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'])) {
                            $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult']['Root']['Policy'];

                            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quote Generation - IDV Changed - Success", "Success" );

                            if ($quote_output['Status'] == 'Fail') {
                                if (isset($quote_output['Error'])) {
                                    return [
                                        'webservice_id'=>$get_response['webservice_id'],
                                        'table'=>$get_response['table'],
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                                    ];
                                } elseif (isset($quote_output['ErrorMessage'])) {
                                    return [
                                        'webservice_id'=>$get_response['webservice_id'],
                                        'table'=>$get_response['table'],
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                                    ];
                                } elseif (isset($quote_output['ValidationError'])) {

                                    return [
                                        'webservice_id'=>$get_response['webservice_id'],
                                        'table'=>$get_response['table'],
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                                    ];
                                }
                            }
                        }
                        else
                        {
                            
                            $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult'];
                            
                            if(isset($quote_output['ValidationError']))
                            {
                                return $return_data = [
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                                ]; 
                            }
                            elseif(isset($quote_output['ErrorMessage'])) 
                            {
                                return $return_data = [
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                                ]; 
                                               
                            }
                            elseif (isset($quote_output['Error']))
                            {
                                return $return_data = [
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                                ];   
                            }  
                        }

                    }

                    if (isset($quote_output['VehicleIDV'])) {
                        $quote_output['VehicleIDV'] = str_replace(',', '', $quote_output['VehicleIDV']);
                    }

                    $total_idv = ($premium_type == 'third_party') ? 0 : round($quote_output['VehicleIDV']);
                    $total_od_premium = 0;
                    $total_tp_premium = 0;
                    $od_premium = 0;
                    $tp_premium = 0;
                    $liability = 0;
                    $pa_owner = 0;
                    $pa_unnamed = 0;
                    $lpg_cng_amount = 0;
                    $lpg_cng_tp_amount = 0;
                    $electrical_amount = 0;
                    $non_electrical_amount = 0;
                    $ncb_discount = 0;
                    $discount_amount = 0;
                    $discperc = 0;
                    $zero_dep_amount = 0;
                    $eng_prot = 0;
                    $ncb_prot = 0;
                    $rsa = 0;
                    $tyre_secure = 0;
                    $return_to_invoice = 0;
                    $consumable = 0;
                    $basePremium = 0;
                    $total_od = 0;
                    $total_tp = 0;
                    $total_discount = 0;

                    foreach ($quote_output['NewDataSet']['Table1'] as $key => $cover) {

                        $cover = array_map('trim', $cover);
                        $value = $cover['BOValue'];

                        if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD')) {
                            $total_od_premium = $value;
                        } elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP')) {
                            $total_tp_premium = $value;
                        } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
                            $od_premium = $value;
                        } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP')) {
                            $tp_premium = $value;
                        } elseif (($cover['Code'] == 'LLDE') && ($cover['Type'] == 'TP')) {
                            $liability = $value;
                        } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                            $pa_owner = $value;
                        } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                            $pa_unnamed = $value;
                        } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD')) {
                            $lpg_cng_amount = $value;
                        } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP')) {
                            $lpg_cng_tp_amount = $value;
                        } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                            $electrical_amount = $value;
                        } elseif (($cover['Code'] == 'NEA') && ($cover['Type'] == 'OD')) {
                            $non_electrical_amount = $value;
                        } elseif (($cover['Code'] == 'ZODEP') && ($cover['Type'] == 'OD')) {
                            $zero_dep_amount = $value;
                        } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                            $ncb_discount = abs($value);
                        } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                            $discount_amount = round(str_replace('-', '', $value));
                        } elseif (($cover['Code'] == 'ENGPR') && ($cover['Type'] == 'OD')) {
                            $eng_prot = $value;
                        } elseif (($cover['Code'] == '00004') && ($cover['Type'] == 'OD')) {
                            $ncb_prot = $value;
                        } elseif (($cover['Code'] == 'RODSA') && ($cover['Type'] == 'OD')) {
                            $rsa = $value;
                        } elseif (($cover['Code'] == '00001') && ($cover['Type'] == 'OD')) {
                            $tyre_secure = $value;
                        } elseif (($cover['Code'] == '00006') && ($cover['Type'] == 'OD')) {
                            $return_to_invoice = $value;
                        } elseif (($cover['Code'] == 'CONSM') && ($cover['Type'] == 'OD')) {
                            $consumable = $value;
                        } elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                            $discperc = $value;
                        }
                    }

                    if ($discperc > 0) {
                        $od_premium = $od_premium + $discount_amount;
                        $discount_amount = 0;
                    }


                    $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
                    $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount;
                    $total_discount = $ncb_discount + $discount_amount;
                    $basePremium = $total_od + $total_tp - $total_discount;
                    #$total_addons = $zero_dep_amount;

                    $final_tp = $total_tp + $pa_owner ;

                    $od_base_premium = $total_od;

                    $total_premium_amount = $total_od_premium + $total_tp_premium ;
                    $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));

                    $totalTax = $basePremium * 0.18;

                    $final_premium = $basePremium + $totalTax;

                    $policystartdatetime = DateTime::createFromFormat('d/m/Y', $policy_start_date);
                    $policy_start_date = $policystartdatetime->format('d-m-Y');

                    

                    

                    
                    /* switch (strtolower($masterProduct->product_identifier)) {
                        case 'zero_dep':
                            $selected_addons_data = [
                                'in_built'   => [
                                    'keyReplace' => 0,
                                    'lopb' => 0,
                                    'zeroDepreciation' =>  (int)$zero_dep_amount,
                                    'roadSideAssistance' => 0,
                                ],
                                'additional' => [
                                    'engineProtector' => (int)$eng_prot,
                                    'ncbProtection' =>(int) $ncb_prot,
                                    'consumables' => (int)$consumable,
                                    'tyreSecure' => (int)$tyre_secure,
                                    'returnToInvoice' => (int)$return_to_invoice,
                                    
                                ],
                                 'other_premium' => 0
                            ];
                            break;

                        default:
                        $selected_addons_data = [
                                'in_built'   => [],
                                'additional' => [
                                    'roadSideAssistance' => (int)$rsa,
                                ],
                                'other_premium' => 0
                            ];
                        break;
                    } */
                    $selected_addons_data = [
                        'in_built'   => [
                        ],
                        'additional' => [
                            'zeroDepreciation' =>  (int)$zero_dep_amount,
                            'roadSideAssistance' => (int)$rsa,
                            'consumables' => (int)$consumable,
                        ],
                         'other_premium' => 0
                    ];
                    if($productData->zero_dep == 0){
                        $selected_addons_data = [
                            'in_built'   => [
                                'zeroDepreciation' =>  (int)$zero_dep_amount,
                            ],
                            'additional' => [
                                'roadSideAssistance' => (int)$rsa,
                                'consumables' => (int)$consumable,
                            ],
                             'other_premium' => 0
                        ];
                    }
                    $selected_addons_data['in_built_premium'] = array_sum($selected_addons_data['in_built']);
                    $selected_addons_data['additional_premium'] = array_sum($selected_addons_data['additional']);
                    foreach($selected_addons_data['additional'] as $k=>$v){
                        if($v == 0){
                            unset($selected_addons_data['additional'][$k]);
                        }
                    }
                    $applicable_addons = [
                        'zeroDepreciation','roadSideAssistance','consumables'
                    ];

                    // if($bike_age > 5 )
                    // {
                    //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    // }
                    if($rsa == 0 )
                    {
                        array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                    }
                    if($consumable == 0 )
                    {
                        array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                    }
                   
                    $data_response = [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv' => $premium_type == 'third_party' ? 0 : round($total_idv),
                            'vehicle_idv' => $total_idv,
                            'min_idv' => $min_idv,
                            'max_idv' => $max_idv,
                            'rto_decline' => NULL,
                            'rto_decline_number' => NULL,
                            'mmv_decline' => NULL,
                            'mmv_decline_name' => NULL,
                            'policy_type' => $premium_type == 'third_party' ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => '',
                            'vehicle_registration_no' => $requestData->rto_code,
                            'rto_no' => $requestData->rto_code,
                            'voluntary_excess' => $requestData->voluntary_excess_value,
                            'version_id' => $mmv_data->ic_version_code,
                            'showroom_price' => 0,
                            'fuel_type' => $requestData->fuel_type,
                            'ncb_discount' => $bike_applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_name' => $productData->product_sub_type_name,
                            'mmv_detail' => $mmv_data,
                            'master_policy_id' => [
                                'policy_id' => $productData->policy_id,
                                'policy_no' => $productData->policy_no,
                                'policy_start_date' => '',
                                'policy_end_date' =>   '',
                                'sum_insured' => $productData->sum_insured,
                                'corp_client_id' => $productData->corp_client_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'insurance_company_id' => $productData->company_id,
                                'status' => $productData->status,
                                'corp_name' => '',
                                'company_name' => $productData->company_name,
                                'logo' => env('APP_URL') . config('constants.bikeConstant.logos') . $productData->logo,
                                'product_sub_type_name' => $productData->product_sub_type_name,
                                'flat_discount' => $productData->default_discount,
                                'is_premium_online' => $productData->is_premium_online,
                                'is_proposal_online' => $productData->is_proposal_online,
                                'is_payment_online' => $productData->is_payment_online
                            ],
                            'bike_manf_date' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'vehicle_register_date' => $requestData->vehicle_register_date,
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
                                'bike_age' => $bike_age,
                                'aai_discount' => 0,
                                'ic_vehicle_discount' =>  round($discount_amount),
                            ],
                            'basic_premium' => round($od_premium),
                            'deduction_of_ncb' => round($ncb_discount),
                            'tppd_premium_amount' => round($tp_premium),
                            'bike_electric_accessories_value' =>round($electrical_amount),
                            'bike_non_electric_accessories_value' => round($non_electrical_amount),
                            'bike_lpg_cng_kit_value' => round($lpg_cng_amount),
                            'cover_unnamed_passenger_value' => round($pa_unnamed),
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'default_paid_driver' => $liability,
                            'bike_additional_paid_driver' => 0,
                            'compulsory_pa_own_driver' => $pa_owner,
                            'total_accessories_amount(net_od_premium)' => 0,
                            'total_own_damage' =>  round($total_od),
                            'cng_lpg_tp' => $lpg_cng_tp_amount,
                            'total_liability_premium' => round($total_tp),
                            'net_premium' => round($basePremium),
                            'service_tax_amount' => 0,
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'voluntary_excess' => 0,
                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                            'quotation_no' => '',
                            'premium_amount' => round($final_premium),
                            'antitheft_discount' => '',
                            'final_od_premium' => round($total_od),
                            'final_tp_premium' => round($total_tp),
                            'final_total_discount' => round($total_discount),
                            'final_net_premium' => round($final_premium),
                            'final_payable_amount' => round($final_premium),
                            'service_data_responseerr_msg' => 'true',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => $requestData->business_type == 'newbusiness' ?  'New Business' : (($requestData->business_type == "rollover") ? 'Roll Over' : $requestData->business_type),
                            'service_err_code' => NULL,
                            'service_err_msg' => NULL,
                            'policyStartDate' => $policy_start_date,
                            'policyEndDate' => $policy_end_date,
                            'ic_of' => $productData->company_id,
                            'ic_vehicle_discount' => round($discount_amount),
                            'vehicle_in_90_days' => 0,
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
                            'add_ons_data' => $selected_addons_data,
                            'tppd_discount' => 0,
                            'applicable_addons' => $applicable_addons
                        ]
                    ];

                    if($is_electrical)
                    {
                        $data_response['Data']['motor_electric_accessories_value'] = round($electrical_amount);
                    }
                    if($is_non_electrical)
                    {
                        $data_response['Data']['motor_non_electric_accessories_value'] = round($non_electrical_amount);
                    }
                    if($is_geo_ext)
                    {
                        $data_response['Data']['GeogExtension_ODPremium'] = 0;
                        $data_response['Data']['GeogExtension_TPPremium'] = 0;
                    }
                    if($requestData->business_type == 'newbusiness' && $cpa_year_data == '5')
                    {
                        
                        $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
                    }

                    return camelCase($data_response);

                }
            }
            else
            {
                
                $quote_output = $quote_output['s:Body']['CreatePolicyResponse']['CreatePolicyResult'];
                
                if(isset($quote_output['ValidationError']))
                {
                    return $return_data = [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => preg_replace('/^.{19}/', '', $quote_output['ValidationError'])
                    ]; 
                }
                elseif(isset($quote_output['ErrorMessage'])) 
                {
                    return $return_data = [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => preg_replace('/^.{19}/', '', $quote_output['ErrorMessage'])
                    ]; 
                                   
                }
                elseif (isset($quote_output['Error']))
                {
                    return $return_data = [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => preg_replace('/^.{19}/', '', $quote_output['Error'])
                    ];   
                }  
                else
                {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => 'bike Insurer Not found'
                    ];
                }
            }

        }
        else
        {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status'         => false,
                'message'        => 'bike Insurer Not found'
            ];
        }

    }catch (\Exception $e) {
       return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'bike Insurer Not found' . $e->getMessage() . ' line ' . $e->getLine()
        ];
    }

}
