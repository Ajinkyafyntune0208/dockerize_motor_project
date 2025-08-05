<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\SyncPremiumDetail\Car\EdelweissPremiumDetailController;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use DateTime;

include_once app_path().'/Helpers/CarWebServiceHelper.php';

class edelweissSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
    	$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request['policyId']);
        $is_pos_enabled = '';
        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        {
            return  response()->json([
                'status' => false,
                'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            ]);
        } */
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $vehicleAge = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date ,'ceil');
        $current_date = date('Y-m-d');
        $no_prev_data   = ($requestData->previous_policy_type == 'Not sure') ? true : false;
        

        $current_ncb_rate = 0;
            $applicable_ncb_rate = 0;
            $maf_year =explode('-',$requestData->manufacture_year);
            $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
            $is_package         = (($premium_type == 'comprehensive'|| $premium_type == 'breakin') ? true : false);
            $is_liability       = ((in_array($premium_type,['third_party','third_party_breakin'])) ? true : false);
            $is_od              = (($premium_type == 'own_damage'|| $premium_type == 'own_damage_breakin') ? true : false);
            $new_business       = (($requestData->business_type == 'newbusiness') ? true : false);

            if($is_package)
                {
                    $policyType = 'Package Policy';
                }
            if ($requestData->business_type == 'newbusiness')
                {
                    $newOrUsed = 'N';
                    $policyStartDate = date('Y-m-d');
                    $typeOfBusiness = 'New';
                    $policyType = 'Bundled Insurance';
                    $current_ncb_rate = '';
                    $applicable_ncb_rate = $requestData->applicable_ncb;
                    $transferOfNcb = 'N';
                    $caTenure ='3';
                    $new_vehicle = true;
                    // $policyEndDay = date('Y-m-d', strtotime('+3 year -1 day', strtotime($policyStartDate)));
                    if ($premium_type == 'comprehensive') {
                        $policyEndDay =   date('Y-m-d', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                    } elseif ($premium_type == 'third_party') {
                        $policyEndDay =   date('Y-m-d', strtotime('+3 year -1 day', strtotime($policyStartDate)));
                    }

                }
                else
                {
                    $newOrUsed = 'U';
                    $typeOfBusiness    = 'Rollover'; 
                    $policyType        = 'Package Policy';
                    $caTenure          = '1';
                    if($no_prev_data || get_date_diff('day', $requestData->previous_policy_expiry_date) > 0)
                    {
                        $policyStartDate = date('Y-m-d', strtotime('+2 day'));
                    }else{
                        $policyStartDate = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    }
                    
                    $policyEndDay = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                    if ($requestData->is_claim == 'N')
                    {
                        $applicable_ncb_rate = $requestData->applicable_ncb;
                        $current_ncb_rate = $requestData->previous_ncb;
                        $transferOfNcb = 'Yes';
                        
                    }
                    else
                    {
                        $transferOfNcb = 'N';
                    }

                    if ($requestData->applicable_ncb == 0) {
                        $transferOfNcb = 'N';
                    }
                }
                if($is_od)
                {
                    $policyType = 'Standard Alone';
                }
                if($is_liability)
                {
                    $policyType = 'Liability Only';
                    $transferOfNcb = 'N';
                }
                $previousPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
                $previousPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($previousPolicyEndDate)));
                //$policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                $get_response = getWsData(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION'),'', 'edelweiss',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'edelweiss',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Token genration',
                    'userId' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME'),
                    'password' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD'),
                    'type' => 'Token genration',
                    'transaction_type' => 'proposal',
                ]);   
                $token_data = $get_response['response'];
                $token_data = json_decode($token_data,TRUE);
               if(isset($token_data['access_token']))
            {
                $proposal->gender =ucwords(strtolower($proposal->gender));
                $proposal->marital_status =ucwords(strtolower($proposal->marital_status));
                
                if($proposal->owner_type == 'I')
                {
                    //driver age
                    $date1 = new DateTime($proposal->dob);
                    $date2 = new DateTime();
                    $interval = $date1->diff($date2);
                    $driver_age = $interval->y;
                    $partnerType = '1'; //Indivisual- Private
                    $salutation = ($proposal->gender == 'Male') ? 'Mr.' : (($proposal->marital_status == 'Single') ? 'Miss' : 'Mrs.');
                    $dateOfBirth = date('Y-m-d', strtotime($proposal->dob));
                    $nomineeDob = ''.//date('Y-m-d', strtotime($nominee_dob));
                    $nominee_age = $proposal->nominee_age;
                }else{
                    $partnerType = '2'; //Company
                    $salutation = 'M/s';//Company
                    $gender = '';
                    $dateOfBirth = '';
                    $maritalStatus = '';
                    $occupation = '';
                    $nomineeDob = '';
                    $nominee_age = '';
                    $driver_age = '';
                    $paOwnerDriverCover = 'N';
                }
                $mmv = get_mmv_details($productData,$requestData->version_id,'edelweiss');
                if($mmv['status'] == 1)
                {
                    $mmv = $mmv['data'];
                }
                else
                {
                    return  [   
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => $mmv['message']
                    ];          
                }
                $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
                //dd($mmv_data);
                if (empty($mmv_data)) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Vehicle does not exist with insurance company'
                    ];
                } /*elseif ($mmv_data->s_cap > 7) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Premium not available for vehicle with seating capacity greater than 7'
                    ];
                }*/
                $veh_rto_data = DB::table('edelweiss_rto_master')
                        ->where('rto_code', RtoCodeWithOrWithoutZero($requestData->rto_code, true))
                        ->first();
                $veh_rto_data = keysToLower($veh_rto_data);
                $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
                $insurer = keysToLower($insurer);

                $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                        ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                        ->first();
                $zerodep_addon_age_limit = date('Y-m-d', strtotime($policyStartDate . ' - 28 days - 11 months - 7 year'));
                $rti_addon_age_limit = date('Y-m-d', strtotime($policyStartDate . ' - 28 days - 11 months - 3 year'));
                if(strtotime($zerodep_addon_age_limit) > strtotime($requestData->vehicle_register_date))
                {
                    $zero_addon_age= false;
                }else{
                    $zero_addon_age= true;
                }
                if(strtotime($rti_addon_age_limit) > strtotime($requestData->vehicle_register_date))
                {
                    $rti_addon_age= false;
                }else{
                    $rti_addon_age= true;
                }

                $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                $vehicle_register_date = new DateTime($vehicleDate);
                $previous_policy_expiry_date = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
                $age_interval = $vehicle_register_date->diff($previous_policy_expiry_date);

                $add_on_array = [];//addon array
                if (!empty($additional['applicable_addons'])) {
                    foreach ($additional['applicable_addons'] as $key => $data) {
                        if ($data['name'] == 'Road Side Assistance') {
                            $add_on_array[] = [ "subCoverage" => "Basic Road Assistance" ];
                        }

                        if ($data['name'] == 'Zero Depreciation' && $zero_addon_age) {
                            $add_on_array[] = [ "subCoverage" => "Zero Depreciation" ];
                        }

                        if ($data['name'] == 'Engine Protector') {
                            $add_on_array[] = [ "subCoverage" => "Engine Protect" ];
                        }
        
                        if ($data['name'] == 'NCB Protection' && $applicable_ncb_rate >= 20) {
                            $add_on_array[] = [ "subCoverage" => "Protection of NCB" ];  
                        }
        
                        if ($data['name'] == 'Return To Invoice' && $rti_addon_age) {
                            $add_on_array[] =  [ "subCoverage" => "Return To Invoice" ];
                        }
        
                        if ($data['name'] == 'Consumable') {
                            $add_on_array[] = [ "subCoverage" => "Consumable Cover" ];
                        }
        
                        if ($data['name'] == 'Loss of Personal Belongings') {
                            $add_on_array[] = [ "subCoverage" => "Loss of Personal Belongings" ]; 
                        }
        
                        if ($data['name'] == 'Key Replacement') {
                            $add_on_array[] = [ "subCoverage" => "Key Replacement" ];
                        }
                        if ($data['name'] == 'Tyre Secure') {
                            if(!(($age_interval->y > 2) || ($age_interval->y == 2 && $age_interval->m > 11) || ($age_interval->y == 2 && $age_interval->m == 11 && $age_interval->d > 29)))
                            {
                                $add_on_array[] = [ "subCoverage" => "Tyre Safeguard" ];
                            }
                        }
                    }
                }  

                $discount_array = [];//discount section
                if($requestData->is_claim == 'N' && !$is_liability && !$new_business && $requestData->applicable_ncb != 0)
                {
                    $discount_array[] = "No Claim Bonus Discount";
                }
                
                $is_antitheft = 'N';
                $tppd_selection = false;
                $tp_coverage_array = [];
                $additional_covers  = [];
                $thirdPartyPropertyDamageLimit = "750000";
                if (!empty($additional['discounts'])) {
                    foreach ($additional['discounts'] as $data) {
                        if ($data['name'] == 'anti-theft device') {
                            $is_antitheft = 'Y';
                            $discount_array[] = "AntiTheft Discount";
                        }else if ($data['name'] == 'TPPD Cover') {
                            $thirdPartyPropertyDamageLimit = "6000";
                            $tppd_selection = true;
                        }
                    }
                }

                $additional_covers[] =  [
                    "subCoverage" => "Own Damage Basic",
                    "limit" => "Own Damage Basic Limit"
                ];
                #for inbuit cng
                if($mmv_data->fuel == 'CNG' && !$is_liability)
                {
                    $additional_covers[] =  [
                        "subCoverage" => "In built CNG LPG Kit Own Damage",
                        "valueofKit"  => "0"
                    ];
                }
                if($mmv_data->fuel == 'CNG' && !$is_od)
                {
                    $tp_coverage_array[] = [ "subCoverage" => "CNG LPG Kit Liability" ];
                }
                //tp covers
                $tp_coverage_array[] = [
                    "subCoverage" => "Third Party Basic Sub Coverage",
                    "limit" => "Third Party Property Damage Limit",
                    "discount" => "Third Party Property Damage Discount",
                    "thirdPartyPropertyDamageLimit" => $thirdPartyPropertyDamageLimit
                ];
                if (!empty($additional['additional_covers'])) {
                    foreach ($additional['additional_covers'] as $data) {
                        if ($data['name'] == 'LL paid driver') {
                            $tp_coverage_array[] = [
                                "subCoverage" => "Legal Liability to Paid Drivers",
                                "numberofPaidDrivers" => "1"
                            ];
                        }
                    }
                }
                if($requestData->vehicle_owner_type == 'C'){
                    $tp_coverage_array[] = [
                        "subCoverage" => "Legal Liability to Employees",
                        "numberofEmployees" => $mmv_data->s_cap
                    ];
                }
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'Non-Electrical Accessories') {
                            $non_electrical_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "Non Electrical Accessories",
                                "accessoryDescription" => "",
                                "valueOfAccessory" => (string)$non_electrical_amt,
                                "limit" => "Non Electrical Accessories Limit"
                            ]; 
                        }
                        if ($data['name'] == 'Electrical Accessories') {
                            $electrical_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "Electrical Electronic Accessories",
                                "accessoryDescription" => "",
                                "valueOfAccessory" => (string)$electrical_amt,
                                "limit" => "Electrical Electronic Accessories Limit"
                            ];
                        }
                    }
                }
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                            $cng_lpg_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "CNG LPG Kit Own Damage",
                                "limit" => "CNG LPG Kit Own Damage Limit",
                                "valueofKit" => (string)$cng_lpg_amt
                            ];
                            if (array_search('CNG LPG Kit Liability', array_column($tp_coverage_array, 'subCoverage')) === false) {
                                $tp_coverage_array[] = [ "subCoverage" => "CNG LPG Kit Liability" ];
                            }
                        }
                    }
                }
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                    }
                }

                if(!empty($additional['additional_covers']))
                {
                    foreach ($additional['additional_covers'] as $key => $data) 
                    {
                        if($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']))
                        {
                            $tp_coverage_array[] = [
                                "subCoverage" =>  "PA to Paid Driver Cleaner Conductor",
                                "limit" =>  "PA to Paid Driver Cleaner Conductor Limit",
                                "sumInsuredperperson" =>  "100000",
                                "numberofPaidDrivers" =>  "1"
                            ];
                        }

                        if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']) && !$is_od )
                        {
                            $cover_pa_unnamed_passenger = $data['sumInsured'];
                            $tp_coverage_array[] = [
                                "subCoverage" => "PA Unnamed Passenger",
                                "limit" => "PA Unnamed Passenger Limit",
                                "sumInsuredperperson" => $cover_pa_unnamed_passenger
                            ];
                        }
                    }
                }

                $contractDetails = [
                    [
                        "contract" => "Own Damage Contract",
                        "coverage" => [
                            "coverage" => "Own Damage Coverage",
                            "deductible" => "Own Damage Basis Deductible",
                            "discount" => $discount_array,
                            "subCoverage" => $additional_covers
                        ]
                    ],
                    [
                        "contract" => "Addon Contract",
                        "coverage" => [
                            "coverage" => "Add On Coverage",
                            //"deductible" => "Key Replacement Deductible",
                            "underwriterDiscount" => "0.0",
                            "subCoverage" => $add_on_array
                        ]
                    ],                               
                    [
                        "contract" => "Third Party Multiyear Contract",
                        "coverage" => [
                            "coverage" => "Legal Liability to Third Party Coverage",
                            "deductible" => "TP Deductible",
                            "subCoverage" => $tp_coverage_array
                        ]
                    ]
                ];

                $contractDetails = (!$is_liability ? [
                    [
                        "contract" => "Own Damage Contract",
                        "coverage" => [
                            "coverage" => "Own Damage Coverage",
                            "deductible" => "Own Damage Basis Deductible",
                            "discount" => $discount_array,
                            "subCoverage" => $additional_covers
                        ]
                    ],
                ]:[]);
            if($add_on_array)
            {
                $addon_contract=[
                    "contract" => "Addon Contract",
                    "coverage" => [
                        "coverage" => "Add On Coverage",
                        //"deductible" => "Key Replacement Deductible",
                        "underwriterDiscount" => "0.0",
                        "subCoverage" => $add_on_array
                    ]
                    ];
                    if(!$is_liability){
                        array_push($contractDetails, $addon_contract);
                    }
                }
                $tp_block =
                    (!$is_od ? [
                        "contract" => "Third Party Multiyear Contract",
                        "coverage" => [
                            "coverage" => "Legal Liability to Third Party Coverage",
                            "deductible" => "TP Deductible",
                            "discount" => ($tppd_selection == true ) ? "Third Party Property Damage Discount" : "",
                            "subCoverage" => $tp_coverage_array
                        ]
                        ] : []);
            #multiyear cpa code            
            $tenure = '1';
            if(!$is_od){
            if (!empty($additional['compulsory_personal_accident'])) {//cpa
                foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident' && $requestData->vehicle_owner_type == 'I')  {
                        $tenure = isset($data['tenure']) && $data['tenure'] == 3 ? '3' : '1';
                $contractDetails[] = 
                                [
                                "contract" => "PA Compulsary Contract",
                                "coverage" => [
                                    "coverage" => "PA Owner Driver Coverage",
                                    "subCoverage" => [
                                    "subCoverage" => "PA Owner Driver",
                                    "limit" => "PA Owner Driver Limit",
                                    "sumInsuredperperson" => "1500000"
                                    ]
                                ]
                                ];
                    }
                }
            }
        }
        if(!$is_od){
            array_push($contractDetails, $tp_block);
            }
            //quote req
            $quote_request_data = [
                'commissionContractId' => config('constants.IcConstants.edelweiss.EDELWEISS_CONTRACT_COMMISION_ID'),
                'channelCode'       => '002',
                'branch'            => config('constants.IcConstants.edelweiss.EDELWEISS_BRANCH'),
                'make'              => $mmv_data->make,
                'model'             => $mmv_data->model,
                'variant'           => $mmv_data->variant,
                'idvcity'           => $veh_rto_data->city,
                'rtoStateCode'      => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                'rtoLocationName'   => $veh_rto_data->rto_code,
                'clusterZone'       => $veh_rto_data->cluster,
                'carZone'           => $veh_rto_data->car_zone,
                'rtoZone'           => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                'rtoCityOrDistrict' => $veh_rto_data->district_name != '' ? $veh_rto_data->district_name : $veh_rto_data->city,
                'idv'                               => $quote->idv,
                'registrationDate'                  => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                'previousInsurancePolicy'           => ($new_business) ? '0' :'1',
                'previousPolicyExpiryDate'          => ($new_business) ? '' :(!empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null),
                'typeOfBusiness'                    => $typeOfBusiness,
                'renewalStatus'                     => 'New Policy',
                'policyType'                        => $policyType,
                'policyStartDate'                   => $policyStartDate,
                'policyTenure'                      => ($new_business) ? '3' :'1',
                'claimDeclaration'                  => '',
                'licencedCarryingCapacity'          => $mmv_data->s_cap,
                'previousNcb'                       => $requestData->is_claim == 'Y' ? $requestData->previous_ncb : $current_ncb_rate,
                'annualMileage'                     => '10000',
                'fuelType'                          => $mmv_data->fuel,//'CNG (Inbuilt)',
                'transmissionType'                  => 'Automatic',//Pass only if Engine Cover
                'dateOfTransaction'                 => date('Y-m-d'),
                'subPolicyType'                     => '',
                'validLicenceNo'                    => 'Y',
                'transferOfNcb'                     => $transferOfNcb,//'Yes',
                'transferOfNcbPercentage'           => $applicable_ncb_rate,
                'proofProvidedForNcb'               => 'NCB Reserving Letter',
                'protectionofNcbValue'              => $applicable_ncb_rate,
                'breakinInsurance'                  => 'NBK',
                'contractTenure'                    => ($new_business) ? '3.0' :'1.0',
                'overrideAllowableDiscount'         => 'N',
                'fibreGlassFuelTank'                => 'N',
                'antiTheftDeviceInstalled'          => $is_antitheft,
                'automobileAssociationMember'       => 'N',
                'bodystyleDescription'              => 'HATCHBACK',
                'dateOfFirstPurchaseOrRegistration' => date('Y-m-d', strtotime($vehicleDate)),
                'dateOfBirth'                       => $dateOfBirth,
                'policyHolderGender'                => $proposal->gender,
                'policyholderOccupation'            => 'Medium to High',
                'typeOfGrid'                        => config('constants.IcConstants.edelweiss.EDELWEISS_GRID') ? config('constants.IcConstants.edelweiss.EDELWEISS_GRID') : 'Grid 1',
                'contractDetails'                   => $contractDetails
            ];       
            if($requestData->business_type == 'newbusiness'){
                $quote_request_data['caTenure'] = '3';
            }
            //Changes 35085
            if($requestData->vehicle_owner_type == 'C'){ 
                $quote_request_data['caTenure'] = '1';
            }
            $get_response = getWsData(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_QUOTE_GENERATION'),$quote_request_data, 'edelweiss',
                        [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Premium Calculation',
                            'authorization'  => $token_data['access_token'],
                            'userId' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME'),
                            'password' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD'),
                            'transaction_type' => 'proposal',
                        ]);
                        $quote_data = $get_response['response'];
                if($quote_data)
                {

                $quote_data = json_decode($quote_data,TRUE);
                if(isset($quote_data['policyData']))
                {
                    $allowableDiscount = 0;
                    if(!$is_liability)
                        {
                    if(isset($quote_data['contractDetails'][0]))
                        {
                            foreach ($quote_data['contractDetails'] as $sections)
                            {
                                if($sections['salesProductTemplateId'] == 'MOCNMF00')
                                {
                                    if(isset($sections['coverage']['subCoverage']['allowableDiscount']))
                                    {
                                    $allowableDiscount = $sections['coverage']['subCoverage']['allowableDiscount'];
                                    }
                                    else
                                    {
                                        foreach ($sections['coverage']['subCoverage'] as $key => $value)
                                        {
                                            if($value['salesProductTemplateId'] == 'MOSCMF00')
                                            {
                                            $allowableDiscount = $value['allowableDiscount'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        else
                        {
                            $sections=$quote_data['contractDetails'];
                            if($sections['salesProductTemplateId'] == 'MOCNMF00')
                                {
                                    if(isset($sections['coverage']['subCoverage']['allowableDiscount']))
                                    {
                                    $allowableDiscount = $sections['coverage']['subCoverage']['allowableDiscount'];
                                    }
                                    else
                                    {
                                        foreach ($sections['coverage']['subCoverage'] as $key => $value)
                                        {
                                            if($value['salesProductTemplateId'] == 'MOSCMF00')
                                            {
                                            $allowableDiscount = $value['allowableDiscount'];
                                            }
                                        }
                                    }
                                }
                        }
                        }

                // if($allowableDiscount < 300){
                    foreach($contractDetails as $key => $value)
                        {                            
                            if($value['contract'] == 'Own Damage Contract')
                            {
                                foreach ($value['coverage']['subCoverage'] as $key1 => $value1) 
                                {
                                    if($value1['subCoverage'] == 'CNG LPG Kit Own Damage')
                                    {
                                       $contractDetails[$key]['coverage']['subCoverage'][$key1] = [
                                            "subCoverage"           =>  "CNG LPG Kit Own Damage",
                                            "limit"                 =>  "CNG LPG Kit Own Damage Limit",
                                            "valueOfKit"            => (string)$cng_lpg_amt,
                                            "accessoryDescription"  =>  "CNG"
                                        ];                                       
                                    }
                                   else if($value1['subCoverage'] == 'In built CNG LPG Kit Own Damage')
                                    {
                                       $contractDetails[$key]['coverage']['subCoverage'][$key1] = [
                                            "subCoverage"           =>  "In built CNG LPG Kit Own Damage",
                                            "valueOfKit"            => '0',
                                        ];                                       
                                    }
                                }
                                
                            }
                            else if($value['contract'] == 'Third Party Multiyear Contract')
                            {                                
                                foreach ($value['coverage']['subCoverage'] as $key1 => $value1) 
                                {
                                    if($value1['subCoverage'] == 'Legal Liability to Paid Drivers')
                                    {
                                       $contractDetails[$key]['coverage']['subCoverage'][$key1] = [
                                            "subCoverage" => "Legal Liability to Paid Drivers",
                                            "numberOfPaidDrivers" => "1"
                                        ];                                       
                                    }
                                    else if($value1['subCoverage'] == 'PA Unnamed Passenger')
                                    {
                                       $contractDetails[$key]['coverage']['subCoverage'][$key1] = [
                                            "subCoverage"           => "PA Unnamed Passenger",
                                            "limit"                 => "PA Unnamed Passenger Limit",
                                            "sumInsuredPerPerson"   => $cover_pa_unnamed_passenger
                                        ];                                       
                                    }
                                    else if($value1['subCoverage'] == 'PA to Paid Driver Cleaner Conductor')
                                    {
                                        $contractDetails[$key]['coverage']['subCoverage'][$key1] = [
                                            "subCoverage" => "PA to Paid Driver Cleaner Conductor",
                                            "limit"  => "PA to Paid Driver Cleaner Conductor Limit",
                                            "sumInsuredPerPerson"  => "100000",
                                            "numberOfPaidDrivers"  => "1"
                                        ];
                                    }
                                }
                            }
                            else if($value['contract'] == 'PA Compulsary Contract' && $requestData->vehicle_owner_type == 'I')
                            {
                               $contractDetails[$key] = [
                                    "contract" => "PA Compulsary Contract",
                                    "coverage" => [
                                        "coverage" => "PA Owner Driver Coverage",
                                        "subCoverage" => [
                                            "subCoverage" => "PA Owner Driver",
                                            "limit" => "PA Owner Driver Limit",
                                            "sumInsuredPerPerson" => "1500000"
                                        ]
                                    ]
                                ];
                            }                            
                        }

                        if($proposal->owner_type == 'I' && ($proposal->last_name === null || $proposal->last_name == ''))
                        {
                            $proposal->last_name = '.';
                        }

                        $fuel_type_code = [
                            'PETROL'    =>  'Petrol',
                            'DIESEL'    =>  'Diesel',
                            'CNG'       =>  'CNG (Inbuilt)',
                            'LPG'       =>  'LPG (Inbuilt)',
                            'HYBRID'    =>  'Hybrid',
                            'OTHER'     =>  'Any other',
                            'BATTERY'   =>  'Battery',
                            'CNGEXT'    =>  'CNG (External Kit)',
                            'LPGEXT'    =>  'LPG (External Kit)',
                            'ELECTRIC'  => 'ELECTRIC'

                        ];
                        $mmv_data->fuel = $fuel_type_code[$mmv_data->fuel];
                        $reg = explode("-",$proposal->vehicale_registration_number);
                        if($requestData->vehicle_registration_no == 'NEW')
                        {
                            $vehicle_registration_no  = str_replace("-", " ", $requestData->rto_code);
                            $reg = explode("-",$requestData->rto_code);
                        }
                        $address_data = [
                            'address' => str_replace(["'", '"'], "", $proposal->address_line1),
                            'address_1_limit'   => 60,
                            'address_2_limit'   => 40,         
                            'address_3_limit'   => 40,    
                        ];
                        $getAddress = getAddress($address_data);
                        $districtCode = $reg[1];

                        if (isBhSeries($requestData->vehicle_registration_no)) {
                            $districtCode = strtok(strtok($requestData->rto_code, '-'));
                        }

                        $rtoLocationName = $veh_rto_data->rto_code; 
                        $rtoLocationArray = explode('-',$rtoLocationName);
                        if(isset($rtoLocationArray[1]) && strlen($rtoLocationArray[1]) == 1) { // checking if rtoLocationArray[1] is set and it's length is equal to 1
                            $rtoLocationName = $rtoLocationArray[0].'-0'.($rtoLocationArray[1]); // appending 0 if rtoLocationArray[1]'s length is equal to 1
                        }

                        $proposal_request_data = [
                            'commissionContractId'            => config('constants.IcConstants.edelweiss.EDELWEISS_CONTRACT_COMMISION_ID'),
                            'branch'                          => config('constants.IcConstants.edelweiss.EDELWEISS_BRANCH'),
                            'agentEmail'                      => config('constants.IcConstants.edelweiss.EDELWEISS_AGENT_EMAIL'),
                            'saleManagerCode'                 => config('constants.IcConstants.edelweiss.EDELWEISS_SALES_MANAGER_CODE'),
                            'saleManagerName'                 => config('constants.IcConstants.edelweiss.EDELWEISS_SALES_MANGER_NAME'),
                            'mainApplicantField'              => $partnerType,//company or Indivisual
                            'typeOfBusiness'                  => $typeOfBusiness,//'Rollover',
                            'policyType'                      => $policyType,
                            'policyStartDate'                 => $policyStartDate,
                            'policyStartTime'                 => '000000',
                            'policyEndDay'                    => $policyEndDay,
                            'policyEndTime'                   => '235900',
                            'previousInsurancePolicy'         => ($new_business) ? '0' :'1',
                            'previousInsuranceCompanyName'    => ($new_business) ? '' :$proposal->previous_insurance_company,
                            'previousInsuranceCompanyAddress' => ($new_business) ? '' : ($insurer->address_line_1 ?? ''),
                            'previousPolicyStartDate'         => ($new_business) ? '' : $previousPolicyStartDate,
                            'previousPolicyEndDate'           => ($new_business) ? '' :$previousPolicyEndDate,
                            'previousPolicyNo'                => ($new_business) ? '' :$proposal->previous_policy_number,
                            'natureOfLoss'                    => 'NA',
                            'policyTenure'                    => ($new_business) ? '3' :'1',
                            'caTenure'                        => $tenure,#($new_business) ? '3' :'1',
                            'previousTPTenure'                => ($is_od ? '3' : (($new_business) ? '3' :'1')),
                            'kindOfPolicy'                    => empty($add_on_array) ?'Package WithOut AddOn' : 'Package With AddOn',
                            'make'                            =>  $mmv_data->make,
                            'model'                           => $mmv_data->model,
                            'variant'                         => $mmv_data->variant,
                            'idvCity'                         => $veh_rto_data->city,
                            'cubicCapacity'                   => $mmv_data->capacity,
                            'licencedSeatingCapacity'         => $mmv_data->s_cap,
                            'licencedCarryingCapacity'        => $mmv_data->s_cap,
                            'fuelType'                        => $mmv_data->fuel,               
                            'newOrUsed'                       => $newOrUsed,
                            'yearOfManufacture'               => $maf_year[1],
                            'registrationDate'                => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            'vehicleAge'                      => $vehicleAge,//'2',
                            'engineeNumber'                   => $proposal->engine_number,
                            'chassisNumber'                   => $proposal->chassis_number,
                            'fibreGlassFuelTank'              => 'N',
                            'bodystyleDescription'            => 'HATCHBACK',
                            'bodyType'                                  => 'Saloon',
                            'transmissionType'                          => 'Automatic',
                            'automobileAssociationMember'               => 'N',
                            'automobileAssociationMembershipNumber'     => '',
                            'automobileAssociationMembershipExpiryDate' => '',
                            'antiTheftDeviceInstalled'                  => $is_antitheft,
                            'typeOfDeviceInstalled'                     => $is_antitheft == 'Y' ? 'Burglary Alarm' : '',
                            'stateCode'                                 => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                            'districtCode'                              => $districtCode,
                            'rtoLocationName'                            => $veh_rto_data->rto_code,
                            'vehicleSeriesNumber'                       => ($requestData->vehicle_registration_no == 'NEW') ? '' :$reg[2],
                            'registrationNumber'                        => ($requestData->vehicle_registration_no == 'NEW') ? '' :  (isBhSeries($requestData->vehicle_registration_no) ? $reg[1] : ($reg[3] ?? '')),
                            'vehicleRegistrationNumber'                 => ($requestData->vehicle_registration_no == 'NEW') ? $vehicle_registration_no : strtoupper(trim($reg[0].' '.$reg[1].' '.$reg[2].' '.($reg[3] ?? ''))),
                            'rtoState'                                  => explode('-', $requestData->rto_code)[0],//$veh_rto_data->state_code,
                            'rtoCityOrDistrict'                         => $veh_rto_data->district_name != '' ? $veh_rto_data->district_name : $veh_rto_data->city,
                            'clusterZone'                               => $veh_rto_data->cluster,
                            'carZone'                                   => $veh_rto_data->car_zone,
                            'rtoZone'                                   => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                            'protectionofNcbValue'                      => $applicable_ncb_rate, 
                            'transferOfNcb'                             => ($transferOfNcb == 'Yes') ? 'Y' : 'N',
                            'transferOfNcbPercentage'                   => $current_ncb_rate,
                            'proofDocumentDate'                         => '2020-05-19',//same 
                            'proofProvidedForNcb'                       => $requestData->business_type == 'rollover' ? 'NCB Declaration' : 'NCB Reserving Letter', 
                            'applicableNcb'                             => $applicable_ncb_rate,
                            'previousClaimMade'                         => $requestData->is_claim == 'N' ? 'N' : 'Y',
                            'exshowroomPrice'                           => $mmv_data->ex_price,
                            'originalIdvValue'                          => $quote->idv,
                            'requiredDiscountOrLoadingPercentage'       => $allowableDiscount,//'-40.0',
                            'financeType'                               => !is_null($proposal->financer_agreement_type) ? $proposal->financer_agreement_type : "",
                            'financierName'                             => !is_null($proposal->name_of_financer) ? $proposal->name_of_financer : '',
                            'branchNameAndAddress'                      => !is_null($proposal->financer_location) ? $proposal->financer_location: '' ,
                            'salutation'                                => $salutation,//'Mr.',
                            'firstName'                                 => $proposal->first_name,
                            'lastName'                                  => $proposal->last_name ?? '',
                            'gender'                                    => $proposal->gender,//'Male',
                            'policyHolderGender'                        => $proposal->gender,//'Male',
                            'maritalStatus'                             => $proposal->marital_status,//'SINGLE',
                            'dateOfBirth'                               => date('Y-m-d',strtotime($proposal->dob)),//'1983-05-06',
                            'street'                                    => trim($getAddress['address_1']),//'A28, Ak Compound',
                            'area'                                      => trim($getAddress['address_2']),//'Juhu Road',
                            'location'                                  => trim($getAddress['address_3']),//'Juhu Road',
                            'currentCountry'                            => 'IN',
                            'pincode'                                   => (string)$proposal->pincode,//'400058',
                            'currentCity'                               => $proposal->city,//'Mumbai',
                            'currentState'                              => $proposal->state_id,//'Mumbai',
                            'mobileNumber'                              => $proposal->mobile_number,//'9819714534',
                            'emailId'                                   => $proposal->email,//'Amar@gmail.com',
                            'occupation'                                => $proposal->occupation,//'Salaried',
                            'policyHolderOccupation'                    => 'Medium to High',
                            'GSTNo'                                     => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
                            'PAN'                                       => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                            //nominee details pass  hardcoded if details not available sample send by ic
                            'nomineeName'                               => !is_null($proposal->nominee_name) ? $proposal->nominee_name : 'NA',//'Mishal',
                            'relationshipWithApplicant'                 => !is_null($proposal->nominee_relationship) ? $proposal->nominee_relationship: 'NA',//'Brother',
                            'isNomineeMinor'                            => 'N',
                            'nomineeAge'                                => !empty($proposal->nominee_age) ? $proposal->nominee_age : '22',
                            'nomineeDob'                                => $proposal->owner_type == 'I' && !empty($proposal->nominee_dob) ? date('Y-m-d', strtotime($proposal->nominee_dob)) :'2000-01-01',#'2003-10-14',
                            'overrideAllowableDiscount'                 => 'Y', //Changing to Y as per git 33644
                            'renewalstatus'                             => 'New Policy',
                            'annualmileageofthecar'                     => '10000',
                            'breakininsurance'                          => 'No Break',
                            'typeofGrid'                                => config('constants.IcConstants.edelweiss.EDELWEISS_GRID') ? config('constants.IcConstants.edelweiss.EDELWEISS_GRID') : 'Grid 1',//'Grid 1',
                            'staffCode'                                 => 'ww223',
                            'validLicenceNo'                            => $proposal->owner_type == 'I' ? 'Y' : '',//blank
                            'validDrivingLicense'                       => $proposal->owner_type == 'I' ? 'Y': '',//blank
                            'driverDetails' => 
                                [
                                  'nameofDriver'              => $proposal->first_name.' '.$proposal->last_name,
                                  'middleName'                => '',
                                  'lastName'                  => '',
                                  'dateofBirth'               => $proposal->owner_type == 'I' ?date('Y-m-d',strtotime($proposal->dob)) : '1970-01-01',
                                  'genderoftheDriver'         => $proposal->owner_type == 'I' ?$proposal->gender : 'MALE',
                                  'ageofDriver'               => !empty($driver_age) ? $driver_age : '18',//
                                  'relationshipwithProposer'  => $proposal->owner_type == 'I' ?'Self' : 'SELF',//
                                  'drivingExperienceinyears'  => '1',
                                  
                                ],
                            'contractDetails'                           => $contractDetails
                        ];
                        if ($requestData->vehicle_owner_type == 'C') {
                            $proposal_request_data['caTenure'] = '1'; //As per git 35085
                            
                            //Name Changes as per 35086 for company case
                            $fullName = trim($proposal->first_name);

                            if (strlen($fullName) > 40) {
                                $words = explode(' ', $fullName);
                                $firstName = '';
                                $lastName = '';
                                foreach ($words as $word) {
                                    if (strlen($firstName . ' ' . $word) <= 40) {
                                        $firstName .= ($firstName ? ' ' : '') . $word;
                                    } else {
                                        $lastName .= ($lastName ? ' ' : '') . $word;
                                    }
                                }
                            } else {
                                $firstName = $fullName;
                                $lastName = '';
                            }

                            $proposal_request_data['firstName'] = $firstName;
                            $proposal_request_data['lastName'] = $lastName;
                        }
                        if ($requestData->vehicle_owner_type == 'C') {
                            $allcontracts = array_column($contractDetails, 'contract');
                            $index = array_search('Third Party Multiyear Contract', $allcontracts);
                            if ($index !== false) {
                                $tpcontract = $proposal_request_data['contractDetails'][$index];
                                $tpsubcoverage = $tpcontract['coverage']['subCoverage'];
                                $llindex = array_column($tpsubcoverage, 'subCoverage');
                                $llarray = array_search('Legal Liability to Employees', $llindex);
                                if ($llarray !== false) {
                                    $proposal_request_data['contractDetails'][$index]['coverage']['subCoverage'][$llarray] = [
                                        "subCoverage" => "Legal Liability to Employees",
                                        "numberOfEmployees" => $mmv_data->s_cap
                                    ];
                                }
                            }
                        }
                        // dd($proposal_request_data);
                        if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                            unset($proposal_request_data['previousTPTenure']);
                            $proposal_request_data['previousInsuranceCompanyName'] = '';
                            $proposal_request_data['previousInsuranceCompanyAddress'] = '';
                            $proposal_request_data['previousPolicyStartDate'] = '';
                            $proposal_request_data['previousPolicyEndDate'] = '';
                            $proposal_request_data['previousPolicyNo'] = '';
                        }
                        if($is_od)
                        {
                            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                                $proposal_request_data['TPInsurerName'] = $proposal->tp_insurance_company;
                                $proposal_request_data['TPPolicyNumber'] = $proposal->tp_insurance_number;
                                $proposal_request_data['TPPolicyStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                                $proposal_request_data['TPPolicyEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                            }
                            $proposal_request_data['inspectionNumber'] = '';
                            $proposal_request_data['ewSource'] = 'NA';
                            $proposal_request_data['inspectionPurpose'] = 'NA';
                            $proposal_request_data['approvalDeclineReason'] = 'NA';
                        }
                        if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $quote->idv >= 5000000){
                            $proposal_request_data['SubIntermediaryCategory'] = false;
                            $proposal_request_data['SubIntermediaryName'] = null;
                            $proposal_request_data['SubIntermediaryPhoneorEmail'] = null;
                            $proposal_request_data['POSPPANorAadharNo'] = null;
                        }else{
                        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                        }
                        $is_testing_pos_enabled = config('constants.motorConstant.IS_TESTING_POS_ENABLED_EDELWEISS');

                        $pos_data = DB::table('cv_agent_mappings')
                            ->where('user_product_journey_id', $requestData->user_product_journey_id)
                            ->where('user_proposal_id',$proposal['user_proposal_id'])
                            ->where('seller_type','P')
                            ->first();

                        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000)
                        {
                            if($pos_data) {
                                $proposal_request_data['SubIntermediaryCategory'] = 'POSP';
                                //$proposal_request_data['SubIntermediaryCode'] = $pos_data->agent_name;
                                $proposal_request_data['SubIntermediaryName'] = $pos_data->agent_name;
                                $proposal_request_data['SubIntermediaryPhoneorEmail'] = $pos_data->agent_mobile;
                                $proposal_request_data['POSPPANorAadharNo'] = (!empty($pos_data->pan_no) ? $pos_data->pan_no : $pos_data->aadhar_no);
                                // ($pos_data->pan_no ?? $pos_data->aadhar_no);#$pos_data->aadhar_no
                            }
                            else if($is_testing_pos_enabled == 'Y')
                            {
                                $proposal_request_data['SubIntermediaryCategory'] = 'POSP';
                                //$proposal_request_data['SubIntermediaryCode'] = $pos_data->agent_name;
                                $proposal_request_data['SubIntermediaryName'] = 'Test';
                                $proposal_request_data['SubIntermediaryPhoneorEmail'] = '8099999999';
                                $proposal_request_data['POSPPANorAadharNo'] = '569278616999';
                            }
                        }else if($is_testing_pos_enabled == 'Y')
                        {
                            $proposal_request_data['SubIntermediaryCategory'] = 'POSP';
                            //$proposal_request_data['SubIntermediaryCode'] = $pos_data->agent_name;
                            $proposal_request_data['SubIntermediaryName'] = 'Test';
                            $proposal_request_data['SubIntermediaryPhoneorEmail'] = '8099999999';
                            $proposal_request_data['POSPPANorAadharNo'] = '569278616999';
                        }
                        // print_pre($proposal_request_data['contractDetails']);
                        /* foreach ($proposal_request_data['contractDetails'] as $keycon => $value) {
                            if($value['contract'] == 'Third Party Multiyear Contract')
                            {
                                if(isset($value['coverage']['subCoverage'][0]))
                                {
                                    foreach ($value['coverage']['subCoverage'] as $keysub => $val) {
                                        if($val['subCoverage'] == 'PA to Paid Driver Cleaner Conductor')
                                        {
                                            unset($proposal_request_data['contractDetails'][$keycon]['coverage']['subCoverage'][$keysub]);
                                            $proposal_request_data['contractDetails'][$keycon]['coverage']['subCoverage'][$keysub] =  
                                            [                                        
                                                "subCoverage" => "PA to Paid Driver Cleaner Conductor",
                                                "limit"  => "PA to Paid Driver Cleaner Conductor Limit",
                                                "sumInsuredPerPerson"  => "100000",
                                                "numberOfPaidDrivers"  => "1"
                                            ];
                                        }
                                    }
                                }
                            }
                        } */

                        $get_response = getWsData(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_PROPOSAL_GENERATION'),$proposal_request_data, 'edelweiss',
                        [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Proposal Service',
                            'authorization'  => $token_data['access_token'],
                            'userId' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME'),
                            'password' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD'),
                            'transaction_type' => 'proposal',
                        ]);
                        $proposal_data = $get_response['response'];
                        $proposal_data = json_decode($proposal_data,TRUE);
                        if(isset($proposal_data['code']) && ($proposal_data['code'] == 422)){
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $proposal_data['msg'] ?? 'Insurer not reachable'
                            ];
                        }
                        if(isset($proposal_data['policyLevelDetails']['quoteNo']) && $proposal_data['policyLevelDetails']['quoteNo'] != '')
                        {
                            $Total_Own_Damage_Amount = $Own_Damage_Basic = $Non_Electrical_Accessories = $Electrical_Accessories = $CNG_LPG_Kit_Own_Damage = 0;
                                $Total_Discounts = $Auto_Mobile_Association_Discount = $AntiTheft_Discount = $No_Claim_Bonus_Discount = 0;
                                $Tyre_Safeguard = $Zero_Depreciation = $Engine_Protect = $Return_To_Invoice = $Key_Replacement = 
                                $Loss_of_Personal_Belongings = $Protection_of_NCB = $Basic_Road_Assistance = 
                                $Consumable_Cover =  $Waiver_of_Policy = 0;
                                $total_TP_Amount = $Third_Party_Basic_Sub_Coverage = $CNG_LPG_Kit_Liability = $Legal_Liability_to_Paid_Drivers = $PA_Unnamed_Passenger = $motor_additional_paid_driver = $tppd_discount =  0;
                                $PA_Owner_Driver = 0;
                                $total_add_ons_premium = 0;
                                $llpdemp_amt = 0;
                                //echo json_encode($proposal_data['contractDetails']['coveragePackage']['coverage'],true);die;
                      if(isset($proposal_data['contractDetails'][0]))
                      {
                        foreach ($proposal_data['contractDetails'] as $sections) 
                        {
                            $templateid =$sections['salesProductTemplateId'];

                            switch($templateid)
                            {
                                case 'MOCNMF00'://od Section
                                    $od_section_array = $sections;
                                    if(isset($od_section_array['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId']))
                                    {
                                        if($od_section_array['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF00')
                                        {
                                            $Total_Own_Damage_Amount = $Own_Damage_Basic =  ($od_section_array['coveragePackage']['coverage']['subCoverage']['totalPremium']);
                                        } 
                                    }
                                    else
                                    {
                                        foreach ($od_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) 
                                        {
                                            if($subCoverage['salesProductTemplateId'] == 'MOSCMF00')
                                            {
                                                $Total_Own_Damage_Amount += $Own_Damage_Basic =  ($subCoverage['totalPremium']);
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'MOSCMF01')
                                            {
                                               $Total_Own_Damage_Amount += $Non_Electrical_Accessories =  ($subCoverage['totalPremium']); 
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'MOSCMF02')
                                            {
                                               $Total_Own_Damage_Amount += $Electrical_Accessories =  ($subCoverage['totalPremium']); 
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'MOSCMF03')
                                            {
                                               $Total_Own_Damage_Amount += $CNG_LPG_Kit_Own_Damage =  ($subCoverage['totalPremium']);
                                            }                                 
                                        }                                        
                                    } 
                                    
                                    //Discount Section  
                                    
                                    if(isset($od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']))
                                    {
                                        $response_discount_array  = $od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'];
                                        
                                        if(isset($response_discount_array['salesProductTemplateId']))
                                        {
                                            if($response_discount_array['salesProductTemplateId'] == 'MOSDMFB1')
                                            {
                                                $Total_Discounts += $Auto_Mobile_Association_Discount =  $response_discount_array['amount'];
                                            }
                                            else if($response_discount_array['salesProductTemplateId'] == 'MOSDMFB2')
                                            {
                                               $Total_Discounts += $AntiTheft_Discount =  $response_discount_array['amount']; 
                                            }
                                            else if($response_discount_array['salesProductTemplateId'] == 'MOSDMFB7')
                                            {
                                               $Total_Discounts += $No_Claim_Bonus_Discount =  $response_discount_array['amount']; 
                                            }                                           
                                        }
                                        else
                                        {                                            
                                            foreach ($od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) 
                                            {
                                                if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB1')
                                                {
                                                    $Total_Discounts += $Auto_Mobile_Association_Discount =  $subCoverage['amount'];
                                                }
                                                else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB2')
                                                {
                                                   $Total_Discounts += $AntiTheft_Discount =  $subCoverage['amount']; 
                                                }
                                                else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB7')
                                                {
                                                   $Total_Discounts += $No_Claim_Bonus_Discount =  $subCoverage['amount']; 
                                                }
                                            }                                          
                                        }
                                    }

                                break;

                                 case 'MOCNMF01' ://addon Section
                                    $add_ons_section_array = $sections;
                                if(isset($add_ons_section_array['coveragePackage']['coverage']['subCoverage'][0]))
                                {
                                    foreach ($add_ons_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF06')
                                        {
                                            $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                        }
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF07')
                                        {
                                            $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                        }
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF08')
                                        {
                                            $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium']; 
                                        }
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF09')
                                        {
                                           $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF10')
                                        {
                                           $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF11')
                                        {
                                           $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF12')
                                        {
                                           $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF13')
                                        {
                                           $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium']; 
                                        }                              
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF15')
                                        {
                                           $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF16')
                                        {
                                           $Waiver_of_Policy =  $subCoverage['totalPremium']; 
                                        }                                
                                    }
                                }
                                else
                                {
                                $subCoverage = $add_ons_section_array['coveragePackage']['coverage']['subCoverage'];
                                    if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF06')
                                    {
                                        $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                    }
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF07')
                                    {
                                        $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                    }
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF08')
                                    {
                                        $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium']; 
                                    }
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF09')
                                    {
                                        $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF10')
                                    {
                                        $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF11')
                                    {
                                        $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF12')
                                    {
                                        $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF13')
                                    {
                                        $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium']; 
                                    }                              
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF15')
                                    {
                                        $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF16')
                                    {
                                        $Waiver_of_Policy =  $subCoverage['totalPremium']; 
                                    }
                                }
                                break;

                                case 'MOCNMF02' ://Third Party Section
                                    $TP_section_array = $sections;
                                if(isset($sections['coveragePackage']['coverage']['subCoverage'][0]))
                                {  
                                    foreach ($TP_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if($subCoverage['salesProductTemplateId'] == 'MOSCMF25')
                                        {
                                            $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF17')
                                        {
                                            $total_TP_Amount += $CNG_LPG_Kit_Liability =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF20')
                                        {
                                            $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF24')
                                        {
                                            $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium']; 
                                        }else if($subCoverage['salesProductTemplateId'] == 'MOSCMF27')
                                        {
                                            $total_TP_Amount += $motor_additional_paid_driver =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF19')
                                        {
                                            $total_TP_Amount += $llpdemp_amt =  $subCoverage['totalPremium']; 
                                        }
                                    }
                                }else{
                                        $subCoverage = $sections['coveragePackage']['coverage']['subCoverage'];
                                        if($subCoverage['salesProductTemplateId'] == 'MOSCMF25')
                                        {
                                            $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF17')
                                        {
                                            $total_TP_Amount += $CNG_LPG_Kit_Liability =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF20')
                                        {
                                            $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF24')
                                        {
                                            $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium']; 
                                        }else if($subCoverage['salesProductTemplateId'] == 'MOSCMF27')
                                        {
                                            $total_TP_Amount += $motor_additional_paid_driver =  $subCoverage['totalPremium']; 
                                        }
                                    }
                                    // print_pre($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']);
                                    // dd();
                                    if(isset($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']))
                                    {
                                        if(isset($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'][0]))
                                        {
                                            foreach ($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $subCoverageDiscount) 
                                            {
                                                if($subCoverageDiscount['salesProductTemplateId'] == 'MOSDMFB9')
                                                {
                                                    $Total_Discounts += $tppd_discount =  ($new_business ? $subCoverageDiscount['totalSurchargeandDiscounts'] : $subCoverageDiscount['amount']); 
                                                } 
                                            }
                                        }else
                                        {

                                            if($TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']['salesProductTemplateId'] == 'MOSDMFB9')
                                            {
                                                $Total_Discounts += $tppd_discount =  ($new_business ? $TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']['totalSurchargeandDiscounts'] : $TP_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']['amount']); 
                                            }
                                        }

                                    }
                                break;

                                case 'MOCNMF03' ://PA Owner Driver
                                    if(isset($sections['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId']) && $sections['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF26')
                                    {
                                        $PA_Owner_Driver = $sections['coveragePackage']['coverage']['subCoverage']['totalPremium'];
                                    }
                                break;
                            }

                        }
                      }else{
                        $sections = $proposal_data['contractDetails'];
                        if($sections['salesProductTemplateId'] == 'MOCNMF00')
                            {  
                                $Total_Own_Damage_Amount =  $sections['contractPremium']['contractPremiumBeforeTax'];
                            }
                            else if($sections['salesProductTemplateId'] == 'MOCNMF01')
                            { 
                                $total_add_ons_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];                                     
                            } 
                            else if($sections['salesProductTemplateId'] == 'MOCNMF02')
                            {
                                $total_TP_Amount =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                            }                                
                            else if($sections['salesProductTemplateId'] == 'MOCNMF03')
                            {
                                //PA Owner Driver
                                $PA_Owner_Driver =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                            }
                      }          
                       /*  foreach ($proposal_data['contractDetails'] as $sections) 
                        {
                            $templateid =$sections['salesProductTemplateId'];

                            switch($templateid)
                            {
                                case 'MOCNMF00'://od Section
                                    $od_section_array = $sections;
                                    if(isset($od_section_array['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId']))
                                    {
                                        if($od_section_array['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF00')
                                        {
                                            $Total_Own_Damage_Amount = $Own_Damage_Basic =  ($od_section_array['coveragePackage']['coverage']['subCoverage']['totalPremium']);
                                        } 
                                    }
                                    else
                                    {
                                        foreach ($od_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) 
                                        {
                                            if($subCoverage['salesProductTemplateId'] == 'MOSCMF00')
                                            {
                                                $Total_Own_Damage_Amount += $Own_Damage_Basic =  ($subCoverage['totalPremium']);
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'MOSCMF01')
                                            {
                                               $Total_Own_Damage_Amount += $Non_Electrical_Accessories =  ($subCoverage['totalPremium']); 
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'MOSCMF02')
                                            {
                                               $Total_Own_Damage_Amount += $Electrical_Accessories =  ($subCoverage['totalPremium']); 
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'MOSCMF03')
                                            {
                                               $Total_Own_Damage_Amount += $CNG_LPG_Kit_Own_Damage =  ($subCoverage['totalPremium']);
                                            }                                 
                                        }                                        
                                    } 
                                    
                                    //Discount Section  
                                    
                                    if(isset($od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']))
                                    {
                                        $response_discount_array  = $od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'];
                                        
                                        if(isset($response_discount_array['salesProductTemplateId']))
                                        {
                                            if($response_discount_array['salesProductTemplateId'] == 'MOSDMFB1')
                                            {
                                                $Total_Discounts = $Auto_Mobile_Association_Discount =  $response_discount_array['amount'];
                                            }
                                            else if($response_discount_array['salesProductTemplateId'] == 'MOSDMFB2')
                                            {
                                               $Total_Discounts = $AntiTheft_Discount =  $response_discount_array['amount']; 
                                            }
                                            else if($response_discount_array['salesProductTemplateId'] == 'MOSDMFB7')
                                            {
                                               $Total_Discounts = $No_Claim_Bonus_Discount =  $response_discount_array['amount']; 
                                            }                                           
                                        }
                                        else
                                        {                                            
                                            foreach ($od_section_array['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) 
                                            {
                                                if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB1')
                                                {
                                                    $Total_Discounts += $Auto_Mobile_Association_Discount =  $subCoverage['amount'];
                                                }
                                                else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB2')
                                                {
                                                   $Total_Discounts += $AntiTheft_Discount =  $subCoverage['amount']; 
                                                }
                                                else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSDMFB7')
                                                {
                                                   $Total_Discounts += $No_Claim_Bonus_Discount =  $subCoverage['amount']; 
                                                }
                                            }                                          
                                        }
                                    }

                                break;

                                 case 'MOCNMF01' ://addon Section
                                    $add_ons_section_array = $sections;
                                if(isset($add_ons_section_array['coveragePackage']['coverage']['subCoverage'][0]))
                                {
                                    foreach ($add_ons_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF06')
                                        {
                                            $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                        }
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF07')
                                        {
                                            $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                        }
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF08')
                                        {
                                            $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium']; 
                                        }
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF09')
                                        {
                                           $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF10')
                                        {
                                           $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF11')
                                        {
                                           $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF12')
                                        {
                                           $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF13')
                                        {
                                           $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium']; 
                                        }                              
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF15')
                                        {
                                           $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF16')
                                        {
                                           $Waiver_of_Policy =  $subCoverage['totalPremium']; 
                                        }                                
                                    }
                                }
                                else
                                {
                                $subCoverage = $add_ons_section_array['coveragePackage']['coverage']['subCoverage'];
                                    if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF06')
                                    {
                                        $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                    }
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF07')
                                    {
                                        $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                    }
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF08')
                                    {
                                        $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium']; 
                                    }
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF09')
                                    {
                                        $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF10')
                                    {
                                        $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF11')
                                    {
                                        $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF12')
                                    {
                                        $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF13')
                                    {
                                        $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium']; 
                                    }                              
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF15')
                                    {
                                        $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium']; 
                                    }                                
                                     if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'MOSCMF16')
                                    {
                                        $Waiver_of_Policy =  $subCoverage['totalPremium']; 
                                    }
                                }
                                break;

                                case 'MOCNMF02' ://Third Party Section
                                    $TP_section_array = $sections;
                                if(isset($sections['coveragePackage']['coverage']['subCoverage'][0]))
                                {  
                                    foreach ($TP_section_array['coveragePackage']['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if($subCoverage['salesProductTemplateId'] == 'MOSCMF25')
                                        {
                                            $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF17')
                                        {
                                            $total_TP_Amount += $CNG_LPG_Kit_Liability =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF20')
                                        {
                                            $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF24')
                                        {
                                            $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium']; 
                                        }
                                    }
                                }else{
                                        $subCoverage = $sections['coveragePackage']['coverage']['subCoverage'];
                                        if($subCoverage['salesProductTemplateId'] == 'MOSCMF25')
                                        {
                                            $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF17')
                                        {
                                            $total_TP_Amount += $CNG_LPG_Kit_Liability =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF20')
                                        {
                                            $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF24')
                                        {
                                            $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium']; 
                                        }
                                    }
                                break;

                                case 'MOCNMF03' ://PA Owner Driver
                                    if(isset($sections['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId']) && $sections['coveragePackage']['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF26')
                                    {
                                        $PA_Owner_Driver = $sections['coveragePackage']['coverage']['subCoverage']['totalPremium'];
                                    }
                                break;
                            }

                        } */
                        #inbulit cng
                        if(isset($proposal_data['contractDetails'][0]['coveragePackage']['coverage']['subCoverage'][1]['salesProductTemplateId']) && $proposal_data['contractDetails'][0]['coveragePackage']['coverage']['subCoverage'][1]['salesProductTemplateId'] == 'MOSCMF04')
                        {
                            $Total_Own_Damage_Amount += isset($proposal_data['contractDetails'][0]['coveragePackage']['coverage']['subCoverage'][1]['totalPremium']) ? $proposal_data['contractDetails'][0]['coveragePackage']['coverage']['subCoverage'][1]['totalPremium'] : 0;
                        }
                            /* if(isset($proposal_data['contractDetails'][0]))
                            {
                                foreach ($proposal_data['contractDetails'] as $sections) 
                                {
                                    if($sections['salesProductTemplateId'] == 'MOCNMF00')
                                    {  
                                        $od_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                                    }
                                    else if($sections['salesProductTemplateId'] == 'MOCNMF01')
                                    { 
                                        $add_ons_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];                                     
                                    } 
                                    else if($sections['salesProductTemplateId'] == 'MOCNMF02')
                                    {
                                         $tp_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                                    }                                
                                    else if($sections['salesProductTemplateId'] == 'MOCNMF03')
                                    {
                                        //PA Owner Driver
                                        $cpa_premium =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                                    }
                                }
                            }
                            else{
                                $sections = $proposal_data['contractDetails'];
                                if($sections['salesProductTemplateId'] == 'MOCNMF00')
                                    {  
                                        $od_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                                    }
                                    else if($sections['salesProductTemplateId'] == 'MOCNMF01')
                                    { 
                                        $add_ons_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];                                     
                                    } 
                                    else if($sections['salesProductTemplateId'] == 'MOCNMF02')
                                    {
                                        $tp_premium =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                                    }                                
                                    else if($sections['salesProductTemplateId'] == 'MOCNMF03')
                                    {
                                        //PA Owner Driver
                                        $cpa_premium =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                                    }
                            }

                            if(isset($proposal_data['contractDetails'][0]))
                        {   
                            
                             if(isset($proposal_data['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'][0]))
                             {
                                
                                
                                foreach ($proposal_data['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'] as $sections) 
                                {
                                   
                               
                                    if($sections['salesProductTemplateId'] == 'MOSDMFB1')
                                    {
                                       
                                        $Total_Discounts = $Auto_Mobile_Association_Discount =  $sections['amount'];
                                        
                                    }
                                    else if($sections['salesProductTemplateId'] == 'MOSDMFB2')
                                    {

                                       $Total_Discounts = $AntiTheft_Discount =  $sections['amount']; 
                                    }
                                    else if($sections['salesProductTemplateId'] == 'MOSDMFB7')
                                    {
                                      
                                       $Total_Discounts = $No_Claim_Bonus_Discount =  $sections['amount']; 
                                    }   

                                }

                            }
                            else
                            {
                                if(!empty($proposal_data['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']))
                                {
                                    $section = $proposal_data['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'];
                                    
                                        if($section['salesProductTemplateId'] == 'MOSDMFB1')
                                        {
                                           
                                            $Total_Discounts = $Auto_Mobile_Association_Discount =  $section['amount'];
                                            
                                        }
                                       else if($section['salesProductTemplateId'] == 'MOSDMFB2')
                                        {
    
                                           $Total_Discounts = $AntiTheft_Discount =  $section['amount']; 
                                        }
                                      else  if($section['salesProductTemplateId'] == 'MOSDMFB7')
                                        {
                                         
                                           $Total_Discounts = $No_Claim_Bonus_Discount =  $section['amount']; 
                                        }   
    
                                }    
    
                            }
                        } */
                        /* $applicable_dis = $No_Claim_Bonus_Discount + $AntiTheft_Discount  +             $Auto_Mobile_Association_Discount;
                            $tp_premium += $cpa_premium;
                            $net_premium = round($od_premium + $add_ons_premium + $tp_premium);
                            $final_gst_amount   = round($net_premium * 0.18);
                            $final_payable_amount  = round($net_premium + $final_gst_amount); */
                        $final_od_premium = $Total_Own_Damage_Amount ;
                        $final_tp_premium = $total_TP_Amount + $PA_Owner_Driver;
                        $final_total_discount = $Total_Discounts;
                        $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount + $total_add_ons_premium;
                        $final_gst_amount   = $final_net_premium * 0.18;
                        $final_payable_amount  = round($final_net_premium + $final_gst_amount);
                            $vehicleDetails = [
                                'manf_name' => $mmv_data->make,
                                'model_name' => $mmv_data->model,
                                'version_name' => $mmv_data->variant,
                                'seating_capacity' => $mmv_data->s_cap,
                                'carrying_capacity' => $mmv_data->s_cap - 1,
                                'cubic_capacity' => $mmv_data->capacity,
                                'fuel_type' =>  $mmv_data->fuel,
                                'vehicle_type' => 'CAR',
                                'version_id' => $mmv_data->ic_version_code ,
                            ];

                            UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                ->update([
                                    'od_premium' => $final_od_premium,
                                    'tp_premium' => $final_tp_premium,
                                    'ncb_discount' => $final_total_discount,
                                    'addon_premium' => $total_add_ons_premium,
                                    'total_premium' => $final_net_premium,
                                    'service_tax_amount' => $final_gst_amount,
                                    'final_payable_amount' => $final_payable_amount,
                                    'cpa_premium' => $PA_Owner_Driver,
                                    //'policy_no' => $proposal_response->policyNumber,
                                    'proposal_no' => $proposal_data['policyLevelDetails']['quoteNo'],
                                    /*'unique_proposal_id' => $proposal_response->applicationId,*/
                                    'is_policy_issued' => $proposal_data['policyLevelDetails']['quoteStatus'],
                                    'unique_quote'  => $proposal_data['policyLevelDetails']['quoteOptionNo'],
                                    'policy_start_date' =>  date('d-m-Y',strtotime($policyStartDate)),
                                    'policy_end_date' =>  date('d-m-Y',strtotime($policyEndDay)),
                                    'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policyStartDate))),
                                    'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime(str_replace('/','-',$policyStartDate)))) : date('d-m-Y',strtotime(str_replace('/','-',$policyEndDay)))),
                                    'ic_vehicle_details' => $vehicleDetails,
                                    'is_breakin_case' => 'N',
                                ]);

                            updateJourneyStage([
                                'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);


                            EdelweissPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                            return response()->json([
                                'status' => true,
                                'msg' => "Proposal Submitted Successfully!",
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'data' => [                            
                                    'proposalId' => $proposal->user_proposal_id,
                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                    'proposalNo' => $proposal_data['policyLevelDetails']['quoteNo'],
                                    'finalPayableAmount' => $final_payable_amount, 
                                    'is_breakin' => 'N',
                                    'inspection_number' => '',
                                ]
                            ]);
                        }//proposal response
                        else {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    // }else{
                    //     return [
                    //         'premium_amount' => 0,
                    //         'status' => false,
                    //         'webservice_id' => $get_response['webservice_id'],
                    //         'table' => $get_response['table'],
                    //         'message' => 'Loading More than 300 Is Not Allowed'
                    //     ];
                    // }
                }else
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $quote_data['message'] ?? $quote_data['msg'] ?? 'Insurer Not Reachable',
                    ]; 
                }

            }else
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Insurer Not Reachable',
                ];
            }
        }else
        {
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => $token_data['error'] 
            ]; 
        }
    }

}