<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use App\Http\Controllers\SyncPremiumDetail\Bike\EdelweissPremiumDetailController;
use Config;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Models\IcVersionMapping;
use App\Models\MasterRto;
use App\Models\SelectedAddons;
use DateTime;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

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

        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        {
            return  response()->json([
                'status' => false,
                'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            ]);
        } */
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $quote_data = getQuotation(customDecrypt($request['userProductJourneyId']));
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $vehicleAge = car_age($vehicleDate, $requestData->previous_policy_expiry_date);
        $no_prev_data   = ($requestData->previous_policy_type == 'Not sure') ? true : false;
        $current_date = date('Y-m-d');
        

        $current_ncb_rate = 0;
            $applicable_ncb_rate = 0;
            $motor_manf_date = '01-'.$requestData->manufacture_year;
            $maf_year =explode('-',$requestData->manufacture_year);
            $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
                $is_package         = (($premium_type == 'comprehensive'|| $premium_type == 'breakin') ? true : false);
                $is_liability       = (($premium_type == 'third_party'|| $premium_type == 'third_party_breakin') ? true : false);
                $is_od              = (($premium_type == 'own_damage'|| $premium_type == 'own_damage_breakin') ? true : false);
                $new_business       = (($requestData->business_type == 'newbusiness') ? true : false);
            if($is_package)
            {
                $policyType = 'Package Policy';
            }
            if ($requestData->business_type == 'newbusiness')
                {
                    $newOrUsed = 'New';
                    $policyStartDate = date('Y-m-d');
                    $typeOfBusiness = 'New';
                    $policyType = 'Bundled Insurance';
                    $new_vehicle = true;
                    $applicable_ncb_rate = $requestData->applicable_ncb;
                    $transferOfNcb = 'N';
                    // $policyEndDay = date('Y-m-d', strtotime('+5 year -1 day', strtotime($policyStartDate)));
                    if ($premium_type == 'comprehensive') {
                        $policyEndDay =   date('Y-m-d', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                    } elseif ($premium_type == 'third_party') {
                        $policyEndDay =   date('Y-m-d', strtotime('+5 year -1 day', strtotime($policyStartDate)));
                    }
                }
                else
                {
                    $newOrUsed = 'Used';
                    $typeOfBusiness    = 'Rollover'; 
                    $policyType        = 'Package Policy';
                    if ($no_prev_data || get_date_diff('day', $requestData->previous_policy_expiry_date) > 0)
                    {
                        $policyStartDate = date('Y-m-d', strtotime('+2 day'));
                    }
                    else
                    {
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
                    $policyType = 'standalone OD';
                }
                if($is_liability)
                {
                    $policyType = 'Liability Only';
                    $transferOfNcb = 'N';
                }
                $previousPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
                $previousPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($previousPolicyEndDate)));
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
                    $date1 = new DateTime(date('d-m-Y', strtotime($proposal->dob)));
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
                if (empty($mmv_data)) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Vehicle does not exist with insurance company'
                    ];
                } elseif ($mmv_data->s_cap > 7) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Premium not available for vehicle with seating capacity greater than 7'
                    ];
                }
                $veh_rto_data = DB::table('edelweiss_rto_master')
                        ->where('rto_code', RtoCodeWithOrWithoutZero($requestData->rto_code, true))
                        ->first();
                $veh_rto_data = keysToLower($veh_rto_data);
                $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();

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
                $add_on_array = [];//addon array
                if (!empty($additional['applicable_addons'])) {
                    foreach ($additional['applicable_addons'] as $key => $data) {
                       
                        if ($data['name'] == 'Zero Depreciation' && $productData->zero_dep == 0 && $zero_addon_age) {
                            $add_on_array[] = [ "subCoverage" => "Zero Depreciation" ];
                        }
                        if ($data['name'] == 'Return To Invoice' && $rti_addon_age) {
                            $add_on_array[] =  [ "subCoverage" => "Return To Invoice" ];
                        }
                        if ($data['name'] == 'Consumable' && $vehicleAge <= 10) {
                            $add_on_array[] = [ "subCoverage" => "Consumable Cover" ];
                        }

                    }
                }  
                $is_antitheft = 'N';
                $discount_array = [];//discount section
                if($requestData->is_claim == 'N' && !$is_liability && !$new_business && $requestData->applicable_ncb != 0)
                    {
                        $discount_array[] = "No Claim Bonus Discount";
                    }
                if (isset($requestData->voluntary_excess_value) && ($requestData->voluntary_excess_value !=0)) {
                    $discount_array[] = "Voluntary Deductible Discount";
                    }
                    if ($requestData->anti_theft_device =='Y' && $requestData->business_type != 'newbusiness') {
                        $is_antitheft = 'Y';
                         $discount_array[] = "AntiTheft Discount";
                         }
                
               

                $additional_covers = [];//additional covers
                $tp_coverage_array = [];
                $additional_covers[] =  [
                    "subCoverage" => "Own Damage Basic",
                    "limit" => "Own Damage Basic Limit"
                ];
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'Non-Electrical Accessories') {
                            $non_electrical_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "Non Electrical Accessories",
                                "accessoryDescription" => "",
                                "valueOfAccessory" => $non_electrical_amt,
                                "limit" => "Non Electrical Accessories Limit"
                            ]; 
                        }
            
                        if ($data['name'] == 'Electrical Accessories') {
                            $electrical_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "Electrical Electronic Accessories",
                                "accessoryDescription" => "",
                                "valueOfAccessory" => $electrical_amt,
                                "limit" => "Electrical Electronic Accessories Limit"
                            ];
                        }
                    }
                }

                //tp covers
                $tp_coverage_array[] = [
                    "subCoverage" => "Third Party Basic Sub Coverage",
                    "limit" => "Third Party Property Damage Limit",
                    "discount" => "Third Party Property Damage Discount",
                    "thirdPartyPropertyDamageLimit" => "750000"
                ];
            
                if(!empty($additional['additional_covers']))
                {
                    foreach ($additional['additional_covers'] as $key => $data) 
                    {
                        if($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']))
                        {
                            $cover_pa_paid_driver = $data['sumInsured'];
                        }
                        if ($data['name'] == 'LL paid driver') {
                            $tp_coverage_array[] = [
                            "subCoverage" => "Legal Liability to Paid Drivers",
                            "numberofPaidDrivers" => "1"
                            ];
                        }
                        if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']) && !$is_od )
                        {
                            $cover_pa_unnamed_passenger = $data['sumInsured'];
                            $tp_coverage_array[] = [
                                "subCoverage" => "PA Unnamed Passenger",
                                "limit" => "PA Unnamed Passenger Limit",
                                "sumInsuredPerPerson" => $cover_pa_unnamed_passenger,
                                "noofPersons"=>'1'
                            ];
                        }
                    }
                }

                $contractDetails = [];
                $vol=
                    [
                        "contract" => "Own Damage Contract",
                        "coverage" => [
                            "coverage" => "Own Damage Coverage",
                            "deductible" => ["Own Damage Basis Deductible"],
                            "discount" => $discount_array,
                            "subCoverage" => $additional_covers
                        ]
                    ];
                if(!$is_liability){
                    if (isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value !=0) {
                        $vol['coverage']['voluntaryDeductible']= $requestData->voluntary_excess_value;
                    }
                   array_push($contractDetails,$vol);
                }
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
                            "subCoverage" => $tp_coverage_array
                        ]
                        ] : []);

            $tenure = '1';
            if(!$is_od){
            if (!empty($additional['compulsory_personal_accident'])) {//cpa
                foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                    if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                        $tenure = isset($data['tenure']) && $data['tenure'] == 5 ? '5' : '1';
                        $contractDetails[] = 
                                [
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
            }
        }
        if(!$is_od){
            array_push($contractDetails, $tp_block);
            }
            //quote req
            $quote_request_data = [
                'channelCode'                       => '002',
                'branch'                            => config('constants.IcConstants.edelweiss.EDELWEISS_BRANCH'),
                'make'                              => $mmv_data->make,
                'model'                             => $mmv_data->model,
                'variant'                           => $mmv_data->variant,
                'idvCity'                           => $veh_rto_data->city,
                'rtoStateCode'                      => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                'rtoLocationName'                   => $veh_rto_data->rto_code,
                'clusterZone'                       => $veh_rto_data->cluster,
                'carZone'                           => $veh_rto_data->car_zone,
                'rtoZone'                           => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                'rtoCityOrDistrict'                 => $veh_rto_data->district_name != '' ? $veh_rto_data->district_name : $veh_rto_data->city,
                'idv'                               => $quote->idv,//'610847.0',
                'registrationDate'                  => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                'previousInsurancePolicy'           => ($new_business) ? '0' :'1',
                'previousPolicyExpiryDate'          => ($new_business) ? '' :(!empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null),
                'typeOfBusiness'                    => $typeOfBusiness,
                'renewalStatus'                     => 'New Policy',
                'policyType'                        => $policyType,
                'policyStartDate'                   => $policyStartDate,
                'policyTenure'                      => ($new_business) ? '5' :'1',
                'claimDeclaration'                  => '',
                'previousNCB'                       => $requestData->is_claim == 'Y' ? $requestData->previous_ncb : $current_ncb_rate,
                'annualMileage'                     => '10000',
                'fuelType'                          => $mmv_data->fuel,//'CNG (Inbuilt)',
                'transmissionType'                  => '',//Pass only if Engine Cover
                'dateOfTransaction'                 => date('Y-m-d'),
                'subPolicyType'                     => '',
                'validLicenceNo'                    => 'Y',
                'transferOfNcb'                     => (($transferOfNcb == 'Yes') && (!in_array($requestData->previous_policy_type, ['Third-party']))) ? 'Y' : 'N',//'Yes',
                'transferOfNcbPercentage'           => $applicable_ncb_rate,
                "proofOfNcb"                        => "NCBRESRV",
                'protectionOfNcbValue'              => $applicable_ncb_rate,
                'breakinInsurance'                  => 'NBK',
                'contractTenure'                    => ($new_business) ? '':'1.0',
                'overrideAllowableDiscount'         => 'N',
                'fibreGlassFuelTank'                => 'No',
                'antiTheftDeviceInstalled'          => $is_antitheft == 'Y' ? 'Yes' :'No',
                'automobileAssociationMember'       => 'No',
                'bodystyleDescription'              => 'COUPE',
                'dateOfFirstPurchaseOrRegistration' => date('Y-m-d', strtotime($vehicleDate)),
                'dateOfBirth'                       => $dateOfBirth,
                'policyHolderGender'                => $proposal->gender,
                'policyholderOccupation'            => 'Medium to High',//'Medium to High',
                'typeOfGrid'                        => 'Grid 1',
                'contractDetails'                   => $contractDetails
            ];
            $get_response = getWsData(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_BIKE_QUOTE_GENERATION'),$quote_request_data, 'edelweiss',
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
                                if($sections['salesProductTemplateId'] == 'Own Damage Contract')
                                {
                                    if(isset($sections['coverage']['subCoverage']['allowableDiscount']))
                                    {
                                    $allowableDiscount = $sections['coverage']['subCoverage']['allowableDiscount'];
                                    }
                                    else
                                    {
                                        foreach ($sections['coverage']['subCoverage'] as $key => $value)
                                        {
                                            if($value['salesProductTemplateId'] == 'Own Damage Contract')
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
                            if($sections['salesProductTemplateId'] == 'Own Damage Contract')
                                {
                                    if(isset($sections['coverage']['subCoverage']['allowableDiscount']))
                                    {
                                    $allowableDiscount = $sections['coverage']['subCoverage']['allowableDiscount'];
                                    }
                                    else
                                    {
                                        foreach ($sections['coverage']['subCoverage'] as $key => $value)
                                        {
                                            if($value['salesProductTemplateId'] == 'Own Damage Contract')
                                            {
                                            $allowableDiscount = $value['allowableDiscount'];
                                            }
                                        }
                                    }
                                }
                        }
                        }

                if($allowableDiscount < 300){
                    foreach($contractDetails as $key => $value)
                        {                            
                            if($value['contract'] == 'Own Damage Contract')
                            {
                                
                                
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
                                }
                            }
                            else if($value['contract'] == 'PA Compulsary Contract')
                            {
                               $contractDetails[$key] = [
                                    "contract" => "PA Compulsary Contract",
                                    "coverage" => [
                                        "coverage" => "PA Owner Driver Coverage",
                                        "subCoverage" => [
                                            "subCoverage" => "PA Owner Driver",
                                            "limit" => "PA Owner Driver Limit",
                                            "sumInsured" => "1500000"
                                        ]
                                    ]
                                ];
                            }                            
                        }

                        if($proposal->owner_type == 'I' && ($proposal->last_name === null || $proposal->last_name == ''))
                        {
                            $proposal->last_name = '.';
                        }

                        $ncbData = (($transferOfNcb == 'Yes') && (!in_array($requestData->previous_policy_type, ['Third-party']))) ? 'Y' : 'N';

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

                        $rtoLocationName = $veh_rto_data->rto_code; 
                        $rtoLocationArray = explode('-',$rtoLocationName);
                        if(isset($rtoLocationArray[1]) && strlen($rtoLocationArray[1]) == 1) { // checking if rtoLocationArray[1] is set and it's length is equal to 1
                            $rtoLocationName = $rtoLocationArray[0].'-0'.($rtoLocationArray[1]); // appending 0 if rtoLocationArray[1]'s length is equal to 1
                        }

                        $proposal_request_data = [
                            'commissionContractID'            => config('constants.IcConstants.edelweiss.EDELWEISS_CONTRACT_COMMISION_ID'),
                            'branch'                          => config('constants.IcConstants.edelweiss.EDELWEISS_BRANCH'),
                            'agentEmail'                      => config('constants.IcConstants.edelweiss.EDELWEISS_AGENT_EMAIL'),//'shivakumar.bale@qualitykiosk.com',
                            'saleManagerCode'                 => config('constants.IcConstants.edelweiss.EDELWEISS_SALES_MANAGER_CODE'),
                            'saleManagerName'                 => config('constants.IcConstants.edelweiss.EDELWEISS_SALES_MANGER_NAME'),
                            'mainApplicantField'              => $partnerType,//company or Indivisual
                            'typeOfBusiness'                  => $typeOfBusiness,//'Rollover',
                            'policyType'                      =>  $policyType,
                            'policyStartDate'                 => $policyStartDate,
                            'policyStartTime'                 => '120100',
                            'policyEndDay'                    => $policyEndDay,
                            'policyEndTime'                   => '235900',
                            'previousInsuranceCompanyName'    => ($new_business || $no_prev_data) ? '' :$proposal->previous_insurance_company,
                            'previousInsuranceCompanyAddress' => ($new_business || $no_prev_data) ? '' : ($insurer->Address_line_1 ?? ''),
                            'previousPolicyStartDate'         => ($new_business || $no_prev_data) ? '' :$previousPolicyStartDate,
                            'previousPolicyEndDate'           => ($new_business || $no_prev_data) ? '' :$previousPolicyEndDate,
                            'previousPolicyNo'                => ($new_business || $no_prev_data) ? '' :$proposal->previous_policy_number,
                            'natureOfLoss'                    => 'NA',
                            'policyTenure'                    => ($new_business) ? '5' :'1',
                            'caTenure'                        => $tenure,
                            'previousTPTenure'                => ($new_business || $no_prev_data) ? '5' :'1',
                            'previousInsurancePolicy'         => ($new_business || $no_prev_data) ? '0' :'1',
                            'kindOfPolicy'                    => ($new_business) ? '':'Package WithOut AddOn',
                            'make'                            =>  $mmv_data->make,
                            'model'                           => $mmv_data->model,
                            'variant'                         => $mmv_data->variant,
                            'idvCity'                         => $veh_rto_data->city,
                            'cubicCapacity'                   => $mmv_data->capacity,
                            //'licencedSeatingCapacity'         => $mmv_data->s_cap,
                            'licencedCarryingCapacity'        => $mmv_data->s_cap,
                            'fuelType'                        => $mmv_data->fuel,               
                            'newOrUsed'                       => $newOrUsed,
                            'yearOfManufacture'               => $maf_year[1],
                            'registrationDate'                => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            'vehicalAge'                      => $vehicleAge,//'2',
                            'engineNumber'                   => $proposal->engine_number,
                            'chassisNumber'                   => $proposal->chassis_number,
                            'fibreGlassFuelTank'              => 'N',
                            'bodystyleDescription'            => '',
                            'bodyType'                                  => '',
                            'transmissionType'                          => '',
                            'automobileAssociationMember'               => 'N',//Y
                            'automobileAssociationMembershipNumber'     => '',//454545
                            'automobileAssociationMembershipExpiryDate' => '',//2020-10-15
                            'antiTheftDeviceInstalled'                  => $is_antitheft == 'Y' ? 'Y' : 'N',
                            'typeOfDeviceInstalled'                     => ($is_antitheft == 'Y' ? 'Burglary Alarm' : ''),
                            'stateCode'                                 =>strlen((string) $veh_rto_data->state_code) > 1 ? $veh_rto_data->state_code : sprintf('%02s', $veh_rto_data->state_code),
                            'districtCode'                              => $reg[1],
                            'rtoLocationName'                            => $veh_rto_data->rto_code,
                            'vehicleSeriesNumber'                       => ($requestData->vehicle_registration_no == 'NEW') ? '' :$reg[2],
                            'registrationNumber'                        => ($requestData->vehicle_registration_no == 'NEW') ? '' :$reg[3],
                            'vehicleRegistrationNumber'                 => ($requestData->vehicle_registration_no == 'NEW') ? $vehicle_registration_no :$reg[0].' '.$reg[1].' '.$reg[2].' '.$reg[3],
                            'rtoState'                                  => $veh_rto_data->state_code,
                            'rtoCityOrDistrict'                         => $veh_rto_data->district_name != '' ? $veh_rto_data->district_name : $veh_rto_data->city,
                            'clusterZone'                               => $veh_rto_data->cluster,
                            'carZone'                                   => $veh_rto_data->car_zone,
                            'rtoZone'                                   => (strlen($veh_rto_data->state_code) < 2) ? '0'.$veh_rto_data->state_code : $veh_rto_data->state_code,
                            'protectionofNcbValue'                      => $applicable_ncb_rate,
                            'transferOfNCB'                             => $ncbData,
                            'previousClaimMade'                         => $requestData->is_claim == 'N' ? 'N' : 'Y',
                            //'exshowroomPrice'                           => $mmv_data['EX_PRICE'],
                            'originalIDVValue'                          => $quote->idv,
                            'requiredDiscountOrLoadingPercentage'       => $allowableDiscount,//'-40.0',
                            'financeType'                               => !is_null($proposal->financer_agreement_type) ? $proposal->financer_agreement_type : "",
                            'financierName'                             => !is_null($proposal->name_of_financer) ? $proposal->name_of_financer : '',
                            'branchNameAndAddress'                      => !is_null($proposal->financer_location) ? $proposal->financer_location: '' ,
                            'salutation'                                => $salutation,//'Mr.',
                            'firstName'                                 => $proposal->first_name,
                            'lastName'                                  => $proposal->last_name ?? '',//'Ujala',
                            'gender'                                    => $proposal->gender,//'Male',
                            'policyHolderGender'                        => $proposal->gender,//'Male',
                            'maritalStatus'                             => $proposal->marital_status,//'SINGLE',
                            'dateOfBirth'                               => date('Y-m-d',strtotime($proposal->dob)),//'1983-05-06',
                            'street'                                    => trim($getAddress['address_1']),//'A28, Ak Compound',
                            'area'                                      => trim($getAddress['address_2']),//'Juhu Road',
                            'location'                                  => trim($getAddress['address_3']),//'Juhu Road',
                            'currentCountry'                            => 'IN',
                            'pincode'                                   => $proposal->pincode,//'400058',
                            'currentCity'                               => $proposal->city,//'Mumbai',
                            'currentState'                              => $proposal->state_id,//'Mumbai',
                            'mobileNumber'                              => $proposal->mobile_number,//'9819714534',
                            'emailId'                                   => $proposal->email,//'Amar@gmail.com',
                            'occupation'                                => $proposal->occupation,//'Salaried',
                            'policyHolderOccupation'                    => 'Low to Medium',
                            'gstNo'                                     => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
                            'pan'                                       => !is_null($proposal->pan_number) ? $proposal->pan_number : '',
                            //nominee details pass  hardcoded if details not available sample send by ic
                            'nomineeName'                               => !is_null($proposal->nominee_name) ? $proposal->nominee_name : 'NA',//'Mishal',
                            'relationshipWithApplicant'                 => !is_null($proposal->nominee_relationship) ? $proposal->nominee_relationship: 'NA',//'Brother',
                            'isNomineeMinor'                            => 'N',
                            'nomineeAge'                                => !empty($proposal->nominee_age) ? $proposal->nominee_age : '22',
                            'nomineeDOB'                                => !empty($proposal->nominee_dob) ? date('Y-m-d',strtotime($proposal->nominee_dob)) :'2000-01-01',
                            'overrideAllowableDiscount'                 => 'N',
                            "liabilityStartDate"                        => '',
                            "liablilityEndDate"                        => '',
                            "nationality"                              => 'IN',
                            'renewalstatus'                             => 'New Policy',
                            'annualmileageofthecar'                     => '10000',
                            'breakininsurance'                          => 'No Break',
                            'typeofGrid'                                => 'Grid 1',//'Grid 1',
                            'staffCode'                                 => 'ww223',
                            'validLicenceNo'                            => $proposal->owner_type == 'I' ? 'Y' :'',
                            'validDrivingLicense'                       => $proposal->owner_type == 'I' ? 'Y' :'',
                            'driverDetails' => 
                                [
                                    'nameofDriver'              => $proposal->first_name.' '.$proposal->last_name,
                                    'middleName'                => '',
                                    'lastName'                  => '',
                                    'dateofBirth'               => $proposal->owner_type == 'I' ?date('Y-m-d',strtotime($proposal->dob)) : '1970-01-01',
                                    'genderofTheDriver'         => $proposal->owner_type == 'I' ?strtoupper($proposal->gender) : 'MALE',
                                    'ageOfDriver'               => $proposal->owner_type == 'I' ? $driver_age : '18',
                                    'relationshipwithProposer'  => $proposal->owner_type == 'I' ?'Self' : 'SELF',
                                    'driverExperienceinyears'  => '1',
                                  
                                ],
                            'ContractDetails'                           => $contractDetails
                        ];

                        if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                            unset($proposal_request_data['previousTPTenure']);
                            $proposal_request_data['previousInsuranceCompanyName'] = '';
                            $proposal_request_data['previousInsuranceCompanyAddress'] = '';
                            $proposal_request_data['previousPolicyStartDate'] = '';
                            $proposal_request_data['previousPolicyEndDate'] = '';
                            $proposal_request_data['previousPolicyNo'] = '';
                        }

                        if($ncbData == 'Y')
                        {
                            $proposal_request_data['transferOfNCBPercentage']  = $current_ncb_rate;
                            $proposal_request_data['proofDocumentDate']        = '2020-05-19';//same 
                            $proposal_request_data['proofProvidedForNCB']      = (in_array($requestData->business_type, ['rollover', 'breakin'])) ? 'NCB Declaration' : 'NCB Reserving Letter';
                            $proposal_request_data['applicableNCB']            = $applicable_ncb_rate;
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
                        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
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
                            }
                        }
                        else if($is_testing_pos_enabled == 'Y')
                            {
                                $proposal_request_data['SubIntermediaryCategory'] = 'POSP';
                                //$proposal_request_data['SubIntermediaryCode'] = $pos_data->agent_name;
                                $proposal_request_data['SubIntermediaryName'] = 'Test';
                                $proposal_request_data['SubIntermediaryPhoneorEmail'] = '8099999999';
                                $proposal_request_data['POSPPANorAadharNo'] = '569278616999';
                            }
                        $get_response = getWsData(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_BIKE_PROPOSAL_GENERATION'),$proposal_request_data, 'edelweiss',
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
                        if(isset($proposal_data['policyLevelDetails']['quoteNo']) && $proposal_data['policyLevelDetails']['quoteNo'] != '')
                        {
                            $od_premium = $total_add_ons_premium = $tp_premium = $cpa_premium =$Auto_Mobile_Association_Discount = $AntiTheft_Discount = $No_Claim_Bonus_Discount = $Total_Discounts=$add_ons_premium= $Own_Damage_Basic = 0;
                            $Third_Party_Basic_Sub_Coverage = $Non_Electrical_Accessories = $Electrical_Accessories = $Legal_Liability_to_Paid_Drivers = $PA_Unnamed_Passenger = $PA_Owner_Driver = 0;
                            $Zero_Depreciation = $Engine_Protect = $Return_To_Invoice = $Key_Replacement = $Loss_of_Personal_Belongings = $Protection_of_NCB = $Basic_Road_Assistance = $Consumable_Cover = 0;
                            $Tyre_Safeguard = $VoluntaryDeductibleDiscount = $No_Claim_Bonus_Discount = 0;
                            if(isset($proposal_data['contractDetails'][0]))
                            {
                                foreach ($proposal_data['contractDetails'] as $sections) 
                                {
                                    if($sections['salesProductTemplateId'] == 'Own Damage Contract')
                                    {
                                        $od_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                                        $od_section_array = $sections['coveragePackage'];
                                        if (isset($od_section_array['coverage']['subCoverage']['salesProductTemplateId'])) {
                                            if ($od_section_array['coverage']['subCoverage']['salesProductTemplateId'] == 'Own Damage Basic') {
                                                $Own_Damage_Basic = ($od_section_array['coverage']['subCoverage']['totalPremium']);
                                            }
                                        } else {
                                            foreach (($od_section_array['coverage']['subCoverage'] ?? []) as $subCoverage) {
                                                if ($subCoverage['salesProductTemplateId'] == 'Own Damage Basic') {
                                                    $Own_Damage_Basic = ($subCoverage['totalPremium']);
                                                }

                                                if ($subCoverage['salesProductTemplateId'] == 'Non Electrical Accessories') {
                                                    $Non_Electrical_Accessories = ($subCoverage['totalPremium']);
                                                }

                                                if ($subCoverage['salesProductTemplateId'] == 'Electrical Electronic Accessories') {
                                                    $Electrical_Accessories = ($subCoverage['totalPremium']);
                                                }
                                            }
                                        }

                                        //Discount Section
                                        if (isset($od_section_array['coverage']['coverageSurchargesOrDiscounts'])) {
                                            $response_discount_array  = is_array($od_section_array['coverage']['coverageSurchargesOrDiscounts']) ? $od_section_array['coverage']['coverageSurchargesOrDiscounts'] : [];

                                            if (isset($response_discount_array['salesProductTemplateId'])) {
                                                if ($response_discount_array['salesProductTemplateId'] == 'No Claim Bonus Discount') {
                                                    $No_Claim_Bonus_Discount =  $response_discount_array['totalSurchargeandDiscounts'];
                                                }
                                                if ($response_discount_array['salesProductTemplateId'] == 'Voluntary Deductible Discount') {
                                                    $VoluntaryDeductibleDiscount =  $response_discount_array['totalSurchargeandDiscounts'];
                                                }
                                            } else {
                                                foreach ($response_discount_array as $subCoverage) {
                                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'No Claim Bonus Discount') {
                                                        $No_Claim_Bonus_Discount =  $subCoverage['totalSurchargeandDiscounts'];
                                                    }
                                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'AntiTheft Discount') {
                                                        $AntiTheft_Discount =  $subCoverage['totalSurchargeandDiscounts'];
                                                    }
                                                    if (isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'Voluntary Deductible Discount') {
                                                        $VoluntaryDeductibleDiscount = $subCoverage['totalSurchargeandDiscounts'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    else if($sections['salesProductTemplateId'] == 'Addon Contract')
                                    { 
                                        $add_ons_premium = $sections['contractPremium']['contractPremiumBeforeTax'];
                                        $add_ons_section_array = $sections['coveragePackage'];
                                        if (isset($add_ons_section_array['coverage']['subCoverage'])) {
                                            if (isset($add_ons_section_array['coverage']['subCoverage'][0])) {
                                                foreach ($add_ons_section_array['coverage']['subCoverage'] as $subCoverage) {
                                                    if ($subCoverage['salesProductTemplateId'] == 'Zero Depreciation') {
                                                        $Zero_Depreciation = $subCoverage['totalPremium'];
                                                    } else if ($subCoverage['salesProductTemplateId'] == 'Consumable Cover') {
                                                        $Consumable_Cover = $subCoverage['totalPremium'];
                                                    } else if ($subCoverage['salesProductTemplateId'] == 'Return To Invoice') {
                                                        $Return_To_Invoice = $subCoverage['totalPremium'];
                                                    }
                                                }
                                            } else {
                                                $subCoverage = $add_ons_section_array['coverage']['subCoverage'];
                                                if ($subCoverage['salesProductTemplateId'] == 'Zero Depreciation') {
                                                    $Zero_Depreciation = $subCoverage['totalPremium'];
                                                } else if ($subCoverage['salesProductTemplateId'] == 'Consumable Cover') {
                                                    $Consumable_Cover = $subCoverage['totalPremium'];
                                                } else if ($subCoverage['salesProductTemplateId'] == 'Return To Invoice') {
                                                    $Return_To_Invoice = $subCoverage['totalPremium'];
                                                }
                                            }
                                        }
                                    } 
                                    else if($sections['salesProductTemplateId'] == 'Third Party Multiyear Contract')
                                    {
                                        $TP_section_array = $sections['coveragePackage'];
                                        if (isset($TP_section_array['coverage']['subCoverage'][0])) {
                                            foreach (($TP_section_array['coverage']['subCoverage'] ?? []) as $subCoverage) {
                                                if ($subCoverage['salesProductTemplateId'] == 'Third Party Basic Sub Coverage') {
                                                    $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                                } else if ($subCoverage['salesProductTemplateId'] == 'Legal Liability to Paid Drivers') {
                                                    $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium'];
                                                } else if ($subCoverage['salesProductTemplateId'] == 'PA Unnamed Passenger') {
                                                    $PA_Unnamed_Passenger =  $subCoverage['totalPremium'];
                                                }
                                            }
                                        } else {
                                            $subCoverage = $TP_section_array['coverage']['subCoverage'];
                                            if ($subCoverage['salesProductTemplateId'] == 'Third Party Basic Sub Coverage') {
                                                $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                            } else if ($subCoverage['salesProductTemplateId'] == 'PA Unnamed Passenger') {
                                                $PA_Unnamed_Passenger =  $subCoverage['totalPremium'];
                                            }
                                        }
                                        $tp_premium =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                                    }                                
                                    else if($sections['salesProductTemplateId'] == 'PA Compulsary Contract')
                                    {
                                        //PA Owner Driver
                                        $cpa_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                                        $PA_Owner_Driver = $sections['coveragePackage']['coverage']['subCoverage']['totalPremium'] ?? $cpa_premium;
                                    }
                                    
                                }
                            }
                            else{
                                $sections = $proposal_data['contractDetails'];
                                if($sections['salesProductTemplateId'] == 'Own Damage Contract')
                                    {  
                                        $od_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];
                                    }
                                    else if($sections['salesProductTemplateId'] == 'Addon Contract')
                                    { 
                                        $add_ons_premium =  $sections['contractPremium']['contractPremiumBeforeTax'];                                     
                                    } 
                                    else if($sections['salesProductTemplateId'] == 'Third Party Multiyear Contract')
                                    {
                                        $tp_premium =  $sections['contractPremium']['contractPremiumBeforeTax']; 
                                    }                                
                                    else if($sections['salesProductTemplateId'] == 'PA Compulsary Contract')
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
                                   
                               
                                    if($sections['salesProductTemplateId'] == 'Voluntary Deductible Discount')
                                    {
                                       
                                        $Total_Discounts = $Auto_Mobile_Association_Discount =  $sections['amount'];
                                        
                                    }
                                    else if($sections['salesProductTemplateId'] == 'No Claim Bonus Discount')
                                    {
                                      
                                       $Total_Discounts += $No_Claim_Bonus_Discount =  $sections['amount']; 
                                    }else if($sections['salesProductTemplateId'] == 'AntiTheft Discount')
                                    {
                                      
                                       $Total_Discounts += $AntiTheft_Discount =  $sections['amount']; 
                                    }    

                                }

                            }
                            else
                            {
                                if(!empty($proposal_data['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts']))
                                {
                                    $section = $proposal_data['contractDetails'][0]['coveragePackage']['coverage']['coverageSurchargesOrDiscounts'];
                                    
                                        if($section['salesProductTemplateId'] == 'Voluntary Deductible Discount')
                                        {
                                           
                                            $Total_Discounts = $Auto_Mobile_Association_Discount =  $section['amount'];
                                            
                                        }
                                      else  if($section['salesProductTemplateId'] == 'No Claim Bonus Discount')
                                        {
                                         
                                           $Total_Discounts += $No_Claim_Bonus_Discount =  $section['amount']; 
                                        }else if($sections['salesProductTemplateId'] == 'AntiTheft Discount')
                                        {
                                          
                                           $Total_Discounts += $AntiTheft_Discount =  $sections['amount']; 
                                        }  
    
                                }    
    
                            }
                        }
                        $applicable_dis = $No_Claim_Bonus_Discount ;
                            $tp_premium += $cpa_premium;
                            $net_premium = round($od_premium + $add_ons_premium + $tp_premium);
                            $final_gst_amount   = round($net_premium * 0.18);
                            $final_payable_amount  = round($net_premium + $final_gst_amount);
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

                            $save = UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                                'od_premium' => $od_premium,
                                'tp_premium' => $tp_premium,
                                'ncb_discount' => $No_Claim_Bonus_Discount,
                                'addon_premium' => $add_ons_premium,
                                'total_premium' => $net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'final_payable_amount' => $final_payable_amount,
                                'cpa_premium' => $cpa_premium,
                                //'policy_no' => $proposal_response->policyNumber,
                                 'proposal_no' => $proposal_data['policyLevelDetails']['quoteNo'],
                                /*'unique_proposal_id' => $proposal_response->applicationId,*/
                                'is_policy_issued' => $proposal_data['policyLevelDetails']['quoteStatus'],
                                'unique_quote'  => $proposal_data['policyLevelDetails']['quoteOptionNo'],
                                'policy_start_date' =>  date('d-m-Y',strtotime($policyStartDate)),
                                'policy_end_date' =>  date('d-m-Y',strtotime($policyEndDay)),
                                'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policyStartDate))),
                                'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/','-',$policyStartDate)))) : date('d-m-Y',strtotime(str_replace('/','-',$policyEndDay)))),
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
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => "Proposal Submitted Successfully!",
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
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    }else{
                        return [
                            'premium_amount' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'status' => false,
                            'message' => 'Loading More than 300 Is Not Allowed'
                        ];
                    }
                }else
                {
                    if(!isset($quote_data['message']))
                    {
                        return [
                            'premium_amount' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'status' => false,
                            'message' => 'Insurer not reachable'
                        ];
                    }
                    return [
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => $quote_data['message'],
                    ];
                }

            }else
            {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => 'Internel Server Error',
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