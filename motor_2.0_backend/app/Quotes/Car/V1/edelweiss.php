<?php
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
function getV1Quote($enquiryId, $requestData, $productData)
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
    $premium_type = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $car_age = car_age($vehicleDate, $requestData->previous_policy_expiry_date,'ceil');

    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
            // if (( $car_age >= 15) && ($tp_check == 'true')){
            //     return [
            //         'premium_amount' => 0,
            //         'status' => false,
            //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
            //     ];
            // }
    // if ($car_age > 10)
    // {
    //     return ['premium_amount' => 0, 'status' => false,'message' => 'Car Age should not be greater than 10 years'];
    // }   
    // else
    // {
        $mmv = get_mmv_details($productData,$requestData->version_id,'edelweiss');
   
    
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
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
        $mmv_details = [
            'manf_name' => $mmv_data->make,
            'model_name' => $mmv_data->model,
            'version_name' => $mmv_data->variant,
            'seating_capacity' => $mmv_data->s_cap,
            'carrying_capacity' => (int) $mmv_data->s_cap - 1,
            'cubic_capacity' => $mmv_data->capacity,
            'fuel_type' =>  $mmv_data->fuel,
            'vehicle_type' => 'CAR',
            'version_id' => $mmv_data->ic_version_code ,
        ];
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'message' => 'Vehicle Not Mapped',
                    'mmv' => $mmv
                ]
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'message' => 'Vehicle code does not exist with Insurance company',
                    'mmv' => $mmv
                ]
            ];
        }else
        {
            $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no && $requestData->vehicle_registration_no != "None" && strtoupper($requestData->vehicle_registration_no) != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
            if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10)) {
                $permitAgency = $reg_no[0] . '0' . $reg_no[1];
            } else {
                $permitAgency = $reg_no[0] . '' . $reg_no[1];
            } 
            $veh_rto_data = DB::table('edelweiss_rto_master')
                        ->where('rto_code', RtoCodeWithOrWithoutZero($requestData->rto_code, true))
                        ->first();
            $veh_rto_data = keysToLower($veh_rto_data);
            if(empty($veh_rto_data))
            {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'RTO not available',
                    'request' => [
                        'message' => 'RTO not available',
                        'rto_code' => $requestData->rto_code
                    ]
                ];
            }

            $is_package         = (($premium_type == 'comprehensive'|| $premium_type == 'breakin') ? true : false);
            $is_liability       = (($premium_type == 'third_party'|| $premium_type == 'third_party_breakin') ? true : false);
            $is_od              = (($premium_type == 'own_damage'|| $premium_type == 'own_damage_breakin') ? true : false);
            $new_business       = (($requestData->business_type == 'newbusiness') ? true : false);
            $no_prev_data   = ($requestData->previous_policy_type == 'Not sure') ? true : false;
            
            if($premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure') && $requestData->business_type != 'newbusiness')
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Breakin Quotes Not Allowed',
                    'request' => [
                        'message' => 'Breakin Quotes Not Allowed',
                        'previous_policy_type' => $requestData->previous_policy_type
                    ]
                ];
            }
            $idv = 0;
            if($requestData->business_type == 'newbusiness')
            {
                $idv = $mmv_data->upto6_months;  
            }
            else if($car_age == 1)
            {
                $idv = $mmv_data->after_6_months_to_1yrs;
            }
            else if($car_age == 2)
            {
                $idv = $mmv_data->upto_2yrs;
            }
            else if($car_age == 3)
            {
                $idv = $mmv_data->upto_3yrs;
            }
            else if($car_age == 4)
            {
                $idv = $mmv_data->upto_4yrs;
            }
            else if($car_age == 5)
            {
                $idv = $mmv_data->upto_5yrs;
            }
            else if($car_age == 6)
            {
                $idv = $mmv_data->upto_6yrs;
            }
            else if($car_age == 7)
            {
                $idv = $mmv_data->upto_7yrs;
            }
            else if($car_age == 8)
            {
                $idv = $mmv_data->upto_8yrs;
            }
            else if($car_age == 9)
            {
                $idv = $mmv_data->upto_9yrs;
            }
            else if($car_age == 10)
            {
                $idv = $mmv_data->upto_10yrs;
            }
            if(!$is_liability){
                if(empty($idv)) {
                    return  [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'IDV value not found in MMV master',
                        'request' => [
                            'message' => 'IDV value not found in MMV master',
                            'mmv_data' => $mmv_data,
                            'idv' => $idv,
                            'age' => $car_age
                        ]
                    ];
                }
            $min_idv = ceil($idv * 0.85);                   
            $max_idv = floor($idv * 1.15);
            //$vehicle_idv=$min_idv;
            //new idv code
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $vehicle_idv = $idv;
                    break;
                case 'min_idv':
                    $vehicle_idv = $min_idv;
                    break;
                case 'max_idv':
                    $vehicle_idv = $max_idv;
                    break;
                default:
                    $vehicle_idv = $min_idv;
                    break;
            }
            }else{
            $min_idv = 0;
            $max_idv = 0;
            $vehicle_idv=$min_idv;
            }
            if ($requestData->is_idv_changed == 'Y')
            {
                $requested_idv = $requestData->edit_idv;
                   
                   if ($requestData->edit_idv >= $max_idv)
                   {
                       $idv = $max_idv;
                       $vehicle_idv=$max_idv;
                   }
                   else if($requestData->edit_idv <= $min_idv)
                   {
                       $idv = $min_idv;
                       $vehicle_idv=$min_idv;
                   }
                   else
                   {
                       $idv = (string)$requestData->edit_idv;
                       $vehicle_idv = (string)$requestData->edit_idv;
                   }
            }
            $current_ncb_rate = 0;
            $applicable_ncb_rate = 0;
            $motor_manf_date = '01-'.$requestData->manufacture_year;
            if($is_package)
                {
                    $policyType = 'Package Policy';
                }
            if ($requestData->business_type == 'newbusiness')
                {
                    $policyStartDate = date('Y-m-d');
                    $typeOfBusiness = 'New';
                    $policyType = 'Bundled Insurance';
                    $new_vehicle = true;
                    $policy_end_date = date('d-m-Y', strtotime('+3 year -1 day', strtotime($policyStartDate)));
                    $current_ncb_rate = '';
                    $applicable_ncb_rate = $requestData->applicable_ncb;
                    $transferOfNcb = 'N';
                    $contractTenure ='3.0';
                    $policyTenure='3';
                    $previousInsurancePolicy = '0';
                }
                else
                {
                    $typeOfBusiness    = 'Rollover'; 
                    $policyType        = 'Package Policy';
                    if($no_prev_data || get_date_diff('day', $requestData->previous_policy_expiry_date) > 0)
                    {
                        $policyStartDate = date('Y-m-d', strtotime('+2 day'));
                    }else{
                        $policyStartDate = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    }
                    $contractTenure ='1.0';
                    $policyTenure ='1';
                    $previousInsurancePolicy = '1';
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
                    if($is_liability){
                        $current_ncb_rate = '';
                        $applicable_ncb_rate = '';
                        $transferOfNcb = 'N';
                    }
                    $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policyStartDate)));
                }
                if($is_od)
                {
                    $policyType = 'Standard Alone';
                }
                if($is_liability)
                {
                    $policyType = 'Liability Only';
                }
                //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION
            $token_cache_name = 'IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION' . $enquiryId;  
            $get_token_data = Cache::get($token_cache_name); 


            if(empty($get_token_data)){
                $get_token_data = cache()->remember($token_cache_name, 60 * 45, function() use ($enquiryId, $productData){   
                    //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION  
                    $token_data = getWsData(config('IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION'),'', 'edelweiss',
                    [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'edelweiss',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Token genration',
                        'userId' => config('IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME
                        'password' => config('IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD
                        'type' => 'Token genration',
                        'transaction_type' => 'quote',
                    ]);   
                    return $token_data;
                });
            }
                $token_data = json_decode($get_token_data['response'],TRUE);
                //discount section start
                $discount_array = [];
                if($requestData->is_claim == 'N' && !$is_liability && !$new_business)
                {
                $discount_array[] = "No Claim Bonus Discount";
                }
                
                $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                ->first();

                $tp_coverage_array = [];
                $additional_covers  = [];
                $tppd_selection = false;
                $thirdPartyPropertyDamageLimit = "750000";
                $is_lpg_cng = false;

                $is_antitheft = 'N';
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
                //discount section end
                $additional_covers[] =  [
                    "subCoverage" => "Own Damage Basic",
                    "limit" => "Own Damage Basic Limit"
                ];
                if($mmv_data->fuel == 'CNG' && !$is_liability)
                {
                    $additional_covers[] =  [
                        "subCoverage" => "In built CNG LPG Kit Own Damage",
                        "valueofKit"  => "0"
                    ];
                }
                if($mmv_data->fuel == 'CNG' && !$is_od)
                {
                    $is_lpg_cng = true;
                    $tp_coverage_array[] = [ "subCoverage" => "CNG LPG Kit Liability" ];
                }
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
                        if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                            $is_lpg_cng = true;
                            $cng_lpg_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "CNG LPG Kit Own Damage",
                                "limit" => "CNG LPG Kit Own Damage Limit",
                                "valueofKit" => $cng_lpg_amt
                            ];
                            $tp_coverage_array[] = [ "subCoverage" => "CNG LPG Kit Liability" ]; 
                        }
                        if ($data['name'] == 'Non-Electrical Accessories' && !$is_liability ) {
                            $non_electrical_amt = $data['sumInsured'];
                            $additional_covers[] = [
                                "subCoverage" => "Non Electrical Accessories",
                                "accessoryDescription" => "",
                                "valueOfAccessory" => $non_electrical_amt,
                                "limit" => "Non Electrical Accessories Limit"
                            ]; 
                        }
            
                        if ($data['name'] == 'Electrical Accessories' && !$is_liability) {
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
                
                if(!empty($additional['additional_covers']))
                {
                    foreach ($additional['additional_covers'] as $key => $data) 
                    {
                        if($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']))
                        {
                            $tp_coverage_array[] = [
                                "subCoverage" => "PA to Paid Driver Cleaner Conductor",
                                "limit"  => "PA to Paid Driver Cleaner Conductor Limit",
                                "sumInsuredperperson"  => "100000",
                                "numberofPaidDrivers"  => "1"
                            ];
                        }

                        if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']))
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
                // $zerodep_addon_age_limit = date('Y-m-d', strtotime($policyStartDate . ' - 28 days - 11 months - 7 year'));
                // $rti_addon_age_limit = date('Y-m-d', strtotime($policyStartDate . ' - 28 days - 11 months - 3 year'));
                // if(strtotime($zerodep_addon_age_limit) > strtotime($requestData->vehicle_register_date))
                // {
                //     $zero_addon_age= false;
                // }else{
                //     $zero_addon_age= true;
                // }
                // if(strtotime($rti_addon_age_limit) > strtotime($requestData->vehicle_register_date))
                // {
                //     $rti_addon_age= false;
                // }else{
                //     $rti_addon_age= true;
                // }
                
                // if ($zero_addon_age == false && $productData->zero_dep == 0)
                // {
                //     return ['premium_amount' => 0, 'status' => false,'message' => 'Zero dep is not allowed for vehicle age greater than 7 years'];
                // }
                $applicable_addons = [
                    'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb','engineProtector','consumables','tyreSecure','ncbProtection','returnToInvoice'
                ];
                $add_on_array = [];
                // if($rti_addon_age)
                // {
                //   $add_on_array[] =  [ "subCoverage" => "Return To Invoice" ];

                // }
                
                if($productData->zero_dep == 0) 
                {
                  $add_on_array[] = [ "subCoverage" => "Zero Depreciation" ];

                }
                $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                $vehicle_register_date = new DateTime($vehicleDate);
                // $vehicle_register_date = new DateTime($requestData->vehicle_register_date);
                $previous_policy_expiry_date = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
                $age_interval = $vehicle_register_date->diff($previous_policy_expiry_date);

                // if(!(($age_interval->y > 2) || ($age_interval->y == 2 && $age_interval->m > 11) || ($age_interval->y == 2 && $age_interval->m == 11 && $age_interval->d > 29)))
                // {
                    $add_on_array[] = [ "subCoverage" => "Tyre Safeguard" ];
                // }
                // if($car_age <= 10)
                // {
                    $add_on_array[] = [ "subCoverage" => "Key Replacement" ];  
                    $add_on_array[] = [ "subCoverage" => "Engine Protect" ];
                    $add_on_array[] = [ "subCoverage" => "Consumable Cover" ];
                    $add_on_array[] = [ "subCoverage" => "Basic Road Assistance" ];
                    $add_on_array[] = [ "subCoverage" => "Loss of Personal Belongings" ];

                    if($requestData->is_claim == 'N' && $applicable_ncb_rate >= 20)
                    {
                      $add_on_array[] = [ "subCoverage" => "Protection of NCB" ];
                      
                    }
                // }
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

                 $add_on = (!$is_liability ? [
                    "contract" => "Addon Contract",
                    "coverage" => [
                        "coverage" => "Add On Coverage",
                        //"deductible" => "Key Replacement Deductible",
                        "underwriterDiscount" => "0.0",
                        "subCoverage" => $add_on_array
                    ]
                    ] : []);
                    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
                    if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
                        $addons = $selected_CPA->compulsory_personal_accident;
                        foreach ($addons as $value) {
                            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                                    $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                                
                            }
                        }
                    }
                
                if(isset($token_data['access_token']))
                {
                    $service_request_data = [
                            'commissionContractId' => config('IC.EDELWEISS.V1.CAR.CONTRACT_COMMISION_ID'), //constants.IcConstants.edelweiss.EDELWEISS_CONTRACT_COMMISION_ID
                            'channelCode'       => '002',
                            'branch'            => 'Mumbai',
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
                            'idv'               => $vehicle_idv,//'610847.0',
                            'registrationDate'  => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            'previousInsurancePolicy' => $previousInsurancePolicy,
                            'previousPolicyExpiryDate' => !empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null,
                            'typeOfBusiness'    => $typeOfBusiness,
                            'renewalStatus'     => 'New Policy',
                            'policyType'        => $policyType,
                            'policyStartDate'   => $policyStartDate,
                            'policyTenure'      => $policyTenure,
                            'claimDeclaration'  => '',
                            'previousNcb'       => $requestData->is_claim == 'Y' ? $requestData->previous_ncb : $current_ncb_rate,
                            'annualMileage'     => '10000',
                            'fuelType'          => $mmv_data->fuel,//'CNG (Inbuilt)',
                            'transmissionType'  => 'Automatic',//Pass only if Engine Cover
                            'dateOfTransaction' => date('Y-m-d'),
                            'subPolicyType'     => '',
                            'validLicenceNo'    => 'Y',
                            'transferOfNcb'     => $transferOfNcb,//'Yes',
                            'transferOfNcbPercentage' => $applicable_ncb_rate,
                            'proofProvidedForNcb' => 'NCB Reserving Letter',
                            'protectionofNcbValue' => $applicable_ncb_rate,
                            'breakinInsurance' => 'NBK',
                            'licencedCarryingCapacity' => $mmv_data->s_cap,
                            'contractTenure' => $contractTenure,
                            'overrideAllowableDiscount' => 'N',
                            'fibreGlassFuelTank' => 'N',
                            'antiTheftDeviceInstalled' => $is_antitheft,
                            'automobileAssociationMember' => 'N',
                            'bodystyleDescription' => 'HATCHBACK',
                            'dateOfFirstPurchaseOrRegistration' => date('Y-m-d', strtotime($vehicleDate)),
                            'dateOfBirth' => '1981-04-03',
                            'policyHolderGender' => 'Male',
                            'policyholderOccupation' => 'Medium to High',
                            'typeOfGrid' => config('IC.EDELWEISS.V1.CAR.GRID') ,#Grid 1 //constants.IcConstants.edelweiss.EDELWEISS_GRID
                            'contractDetails' => [
                            ]
                    ];
                    if($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage"){
                        // $service_request_data['caTenure'] = '3';
                        if($requestData->business_type == 'newbusiness')
                        {
                            $service_request_data['caTenure'] = isset($cpa_tenure) ? $cpa_tenure : '3';
                        }
                        else{
                            $service_request_data['caTenure'] = isset($cpa_tenure) ? $cpa_tenure : '1';
                        }

                    }
                    if(!$is_liability){
                        array_push($service_request_data['contractDetails'],
                        [
                            "contract" => "Own Damage Contract",
                            "coverage" => [
                                "coverage" => "Own Damage Coverage",
                                "deductible" => "Own Damage Basis Deductible",
                                "discount" => $discount_array,
                                "subCoverage" => $additional_covers
                            ]
                        ]);
                        if($productData->product_identifier != 'without_addon')
                        {
                        array_push($service_request_data['contractDetails'], $add_on);
                        }

                    }
                    if(!$is_od){
                        array_push($service_request_data['contractDetails'],

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
                        ]);
                        array_push($service_request_data['contractDetails'], $tp_block);
                    }
                    //echo json_encode($service_request_data,true);die;
                    $checksum_data = checksum_encrypt($service_request_data);
                    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'edelweiss',$checksum_data,"CAR");
                    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                        $get_quote_data = $is_data_exits_for_checksum;
                    }else{
                        //constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_QUOTE_GENERATION
                        $get_quote_data = getWsData(config('IC.EDELWEISS.V1.CAR.END_POINT_URL_QUOTE_GENERATION'),$service_request_data, 'edelweiss',
                        [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Premium Calculation',
                            'checksum' => $checksum_data,
                            'authorization'  => $token_data['access_token'],
                            'userId' => config('IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME
                            'password' => config('IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD'), //constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD
                            'transaction_type' => 'quote',
                        ]);
                    }
                        if($get_quote_data)
                    {
                        
                            $quote_data = json_decode($get_quote_data['response'],TRUE);
                        if(isset($quote_data['policyData']))
                        {
                            $allowableDiscount = 0;
                        if(isset($quote_data['contractDetails'][0]['coverage']['subCoverage']))
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
                                    if(isset($sections['coverage']['subCoverage']['allowableDiscount']))
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
                        }
                        else
                        {
                            $sections = $quote_data['contractDetails'];
                            if(isset($sections['salesProductTemplateId']) && $sections['salesProductTemplateId']== 'MOCNMF00')
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

                            if($allowableDiscount < 300)
                            {
                                $exshowroomPrice = 0;
                                $Total_Own_Damage_Amount = $Own_Damage_Basic = $Non_Electrical_Accessories = $Electrical_Accessories = $CNG_LPG_Kit_Own_Damage = 0;
                                $Total_Discounts = $Auto_Mobile_Association_Discount = $AntiTheft_Discount = $No_Claim_Bonus_Discount = 0;
                                $Tyre_Safeguard = $Zero_Depreciation = $Engine_Protect = $Return_To_Invoice = $Key_Replacement = 
                                $Loss_of_Personal_Belongings = $Protection_of_NCB = $Basic_Road_Assistance = 
                                $Consumable_Cover =  $Waiver_of_Policy = 0;
                                $total_TP_Amount = $Third_Party_Basic_Sub_Coverage = $CNG_LPG_Kit_Liability = $Legal_Liability_to_Paid_Drivers = $PA_Unnamed_Passenger = $motor_additional_paid_driver = $tppd_discount =  0;
                                $PA_Owner_Driver = 0;
                                $total_add_ons_premium = 0;
                if(isset($quote_data['contractDetails'][0]))
                {
                    foreach ($quote_data['contractDetails'] as $sections) 
                        {
                            $templateid =$sections['salesProductTemplateId'];

                            switch($templateid)
                            {
                                case 'MOCNMF00'://od Section
                                    $od_section_array = $sections;
                                    $exshowroomPrice = $sections['insuredObject']['exshowroomPrice'];
                                    if(isset($od_section_array['coverage']['subCoverage']['salesProductTemplateId']))
                                    {
                                        if($od_section_array['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF00')
                                        {
                                            $Total_Own_Damage_Amount = $Own_Damage_Basic =  ($od_section_array['coverage']['subCoverage']['totalPremium']);
                                        } 
                                    }
                                    else
                                    {
                                        foreach ($od_section_array['coverage']['subCoverage'] as $subCoverage) 
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
                                    
                                    if(isset($od_section_array['coverage']['coverageSurchargesOrDiscounts']))
                                    {
                                        $response_discount_array  = $od_section_array['coverage']['coverageSurchargesOrDiscounts'];
                                        
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
                                            foreach ($od_section_array['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) 
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
                                    foreach ($add_ons_section_array['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if($subCoverage['salesProductTemplateId'] == 'MOSCMF06')
                                        {
                                            $total_add_ons_premium += $Tyre_Safeguard =  $subCoverage['totalPremium'];
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF07')
                                        {
                                            $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF08')
                                        {
                                            $total_add_ons_premium += $Engine_Protect =  $subCoverage['totalPremium']; 
                                        }
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF09')
                                        {
                                           $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF10')
                                        {
                                           $total_add_ons_premium += $Key_Replacement =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF11')
                                        {
                                           $total_add_ons_premium += $Loss_of_Personal_Belongings =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF12')
                                        {
                                           $total_add_ons_premium += $Protection_of_NCB =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF13')
                                        {
                                           $total_add_ons_premium += $Basic_Road_Assistance =  $subCoverage['totalPremium']; 
                                        }                              
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF15')
                                        {
                                           $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium']; 
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'MOSCMF16')
                                        {
                                           $Waiver_of_Policy =  $subCoverage['totalPremium']; 
                                        }                                
                                    }
                                break;

                                case 'MOCNMF02' ://Third Party Section
                                    $TP_section_array = $sections;
                                if(isset($sections['coverage']['subCoverage'][0]))
                                {  
                                    foreach ($TP_section_array['coverage']['subCoverage'] as $subCoverage) 
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
                                    }
                                }else{
                                        $subCoverage = $sections['coverage']['subCoverage'];
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

                                    if(isset($TP_section_array['coverage']['coverageSurchargesOrDiscounts']))
                                    {
                                        if(isset($TP_section_array['coverage']['coverageSurchargesOrDiscounts'][0]))
                                        {
                                            foreach ($TP_section_array['coverage']['coverageSurchargesOrDiscounts'] as $subCoverageDiscount) 
                                            {
                                                // print_pre($subCoverageDiscount);
                                                if($subCoverageDiscount['salesProductTemplateId'] == 'MOSDMFB9')
                                                {
                                                    $Total_Discounts += $tppd_discount =  ($new_business ? $subCoverageDiscount['totalSurchargeandDiscounts'] : $subCoverageDiscount['amount']); 
                                                } 
                                            }
                                        }else
                                        {
                                            // print_pre($TP_section_array['coverage']['coverageSurchargesOrDiscounts']); 
                                            if($TP_section_array['coverage']['coverageSurchargesOrDiscounts']['salesProductTemplateId'] == 'MOSDMFB9')
                                            {
                                                $Total_Discounts += $tppd_discount =  ($new_business ? $TP_section_array['coverage']['coverageSurchargesOrDiscounts']['totalSurchargeandDiscounts'] : $TP_section_array['coverage']['coverageSurchargesOrDiscounts']['amount']); 
                                            }
                                        }
                                    }
                                break;

                                case 'MOCNMF03' ://PA Owner Driver
                                    if(isset($sections['coverage']['subCoverage']['salesProductTemplateId']) && $sections['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF26')
                                    {
                                        $PA_Owner_Driver = $sections['coverage']['subCoverage']['totalPremium'] ?? 0;
                                    }
                                break;
                            }

                        }
                }
                else
                {
                    if(isset($quote_data['contractDetails']['salesProductTemplateId']) && $quote_data['contractDetails']['salesProductTemplateId'] == 'MOCNMF00')
                    {
                        $od_section_array = $quote_data['contractDetails'];
                        if(isset($od_section_array['coverage']['subCoverage']['salesProductTemplateId']))
                        {
                        if($od_section_array['coverage']['subCoverage']['salesProductTemplateId'] == 'MOSCMF00')
                        {
                        $Total_Own_Damage_Amount = $Own_Damage_Basic =  ($od_section_array['coverage']['subCoverage']['totalPremium']);
                        } 
                        }
                        else
                        {
                        foreach ($od_section_array['coverage']['subCoverage'] as $subCoverage) 
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

                        if(isset($od_section_array['coverage']['coverageSurchargesOrDiscounts']))
                        {
                        $response_discount_array  = $od_section_array['coverage']['coverageSurchargesOrDiscounts'];

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
                            foreach ($od_section_array['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) 
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
                    }
                    
                }
                        
                        if(isset($quote_data['contractDetails'][0]['coverage']['subCoverage'][1]['salesProductTemplateId']) && $quote_data['contractDetails'][0]['coverage']['subCoverage'][1]['salesProductTemplateId'] == 'MOSCMF04')
                        {
                            $Total_Own_Damage_Amount += $CNG_LPG_Kit_Own_Damage = isset($quote_data['contractDetails'][0]['coverage']['subCoverage'][1]['totalPremium']) ? $quote_data['contractDetails'][0]['coverage']['subCoverage'][1]['totalPremium'] : 0; 
                        }else if(isset($quote_data['contractDetails']['coverage']['subCoverage'][1]['salesProductTemplateId']) && $quote_data['contractDetails']['coverage']['subCoverage'][1]['salesProductTemplateId'] == 'MOSCMF04')
                        {
                            $Total_Own_Damage_Amount += $CNG_LPG_Kit_Own_Damage = isset($quote_data['contractDetails']['coverage']['subCoverage'][1]['totalPremium']) ? $quote_data['contractDetails']['coverage']['subCoverage'][1]['totalPremium'] : 0; 
                        }

                        if($Return_To_Invoice == 0)
                        {
                            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                        }
                        // if($car_age >= 2)
                        // {
                            // array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                        // }
                        // if($zero_addon_age == false)
                        // {
                        //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                        // }
                        // if($car_age >= 11)
                        // {
                            // array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                            // array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                            // array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                            // array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                            
                        // }
                        if(!($requestData->is_claim == 'N' && $applicable_ncb_rate >= 20) || ($requestData->business_type == 'newbusiness' ))
                        {
                            array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                        }
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
                            if($Zero_Depreciation <= 0) {
                                return [
                                    'status'=>false,
                                    'msg'=>'Zero Depreciation amount cannot be zero'
                                ];
                            }
                            $add_ons = [
                                'in_built' => [
                                    'zeroDepreciation' => (int)round($Zero_Depreciation),
                                ],
                                'additional' => [                                               
                                    'road_side_assistance' => (int) $Basic_Road_Assistance,
                                        'engineProtector' => (int) round($Engine_Protect),
                                        'ncbProtection' => (int) $Protection_of_NCB,
                                        'keyReplace' => (int) round($Key_Replacement),
                                        'consumables' => (int) $Consumable_Cover,
                                        'tyreSecure' => (int) round($Tyre_Safeguard),
                                        'returnToInvoice' => (int) round($Return_To_Invoice),
                                        'lopb' => (int) round($Loss_of_Personal_Belongings)
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
                                    // 'zeroDepreciation' => (int)round($Zero_Depreciation),
                                        'road_side_assistance' => (int) $Basic_Road_Assistance,
                                        'engineProtector' => (int) round($Engine_Protect),
                                        'ncbProtection' => (int) $Protection_of_NCB,
                                        'keyReplace' => (int) round($Key_Replacement),
                                        'consumables' => (int) $Consumable_Cover,
                                        'tyreSecure' => (int) round($Tyre_Safeguard),
                                        'returnToInvoice' => (int) round($Return_To_Invoice),
                                        'lopb' => (int) round($Loss_of_Personal_Belongings)
                                ],
                                'other' => []
                            ];
                             $applicable_addons = [ "roadSideAssistance"];
                        }
                      
                        if ($Basic_Road_Assistance == 0) {
            
                            unset($add_ons['additional']['road_side_assistance']);
                        }
                        
                        // if($is_new)
                        // {
                        //     array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                        //     array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                        // }
                       
                        $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
                        $add_ons['additional_premium'] = array_sum($add_ons['additional']);
                        $add_ons['other_premium'] = array_sum($add_ons['other']);
                        $aai_discount = 0;
                        $geog_Extension_OD_Premium = 0;
                        $geog_Extension_TP_Premium = 0;
                        $final_od_premium = $Total_Own_Damage_Amount ;
                        $final_tp_premium = $total_TP_Amount;
                        $final_total_discount = $Total_Discounts;
                        $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount + $total_add_ons_premium);
                        $final_gst_amount   = round($final_net_premium * 0.18);
                        $final_payable_amount  = round($final_net_premium + $final_gst_amount);
                        $data_response = [
                            'webservice_id' => $get_quote_data['webservice_id'],
                            'table' => $get_quote_data['table'],
                            'status' => true,
                            'msg' => 'Found',
                            'Data' => [
                                'idv' => $vehicle_idv,
                                'min_idv' => round($min_idv),
                                'max_idv' => round($max_idv),
                                'default_idv' => $idv,
                                'vehicle_idv' => $vehicle_idv,
                                'qdata' => null,
                                'pp_enddate' => $requestData->previous_policy_expiry_date,
                                'addonCover' => null,
                                'addon_cover_data_get' => '',
                                'rto_decline' => null,
                                'rto_decline_number' => null,
                                'mmv_decline' => null,
                                'mmv_decline_name' => null,
                                'policy_type' => $is_liability ? 'Third Party' :(($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                                'cover_type' => '1YC',
                                'hypothecation' => '',
                                'hypothecation_name' => '',
                                'vehicle_registration_no' => $requestData->rto_code,
                                'voluntary_excess' => 0,
                                'version_id' => '',//$mmv->ic_version_code,
                                'selected_addon' => [],
                                'showroom_price' => $vehicle_idv,
                                'fuel_type' => $mmv_data->fuel,
                                'ncb_discount' => $requestData->applicable_ncb,
                                'company_name' => $productData->company_name,
                                'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                                'product_name' => $productData->product_name,
                                'mmv_detail' => $mmv_details,
                                'vehicle_register_date' => $requestData->vehicle_register_date,
                                'master_policy_id' => [
                                    'policy_id' => $productData->policy_id,
                                    'policy_no' => $productData->policy_no,
                                    'policy_start_date' => date('d-m-Y', strtotime($policyStartDate)),
                                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                    'sum_insured' => $productData->sum_insured,
                                    'corp_client_id' => $productData->corp_client_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'insurance_company_id' => $productData->company_id,
                                    'status' => $productData->status,
                                    'corp_name' => "Ola Cab",
                                    'company_name' => $productData->company_name,
                                    'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                                    'product_sub_type_name' => $productData->product_sub_type_name,
                                    'flat_discount' => $productData->default_discount,
                                    'predefine_series' => "",
                                    'is_premium_online' => $productData->is_premium_online,
                                    'is_proposal_online' => $productData->is_proposal_online,
                                    'is_payment_online' => $productData->is_payment_online
                                ],
                                'motor_manf_date' => $motor_manf_date,
                                'vehicleDiscountValues' => [
                                    'master_policy_id' => $productData->policy_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'segment_id' => 0,
                                    'rto_cluster_id' => 0,
                                    'car_age' => $car_age,
                                    'ic_vehicle_discount' => '',
                                ],
                                'basic_premium' =>(int) $Own_Damage_Basic,
                                'deduction_of_ncb' => (int)$No_Claim_Bonus_Discount,
                                'tppd_premium_amount' => (int)$Third_Party_Basic_Sub_Coverage,
                                'tppd_discount' => $tppd_discount,
                                'motor_electric_accessories_value' =>(int) $Electrical_Accessories,
                                'motor_non_electric_accessories_value' => (int)$Non_Electrical_Accessories,
                                //'motor_lpg_cng_kit_value' => (int)$CNG_LPG_Kit_Own_Damage,
                                'cover_unnamed_passenger_value' => isset($PA_Unnamed_Passenger) ? $PA_Unnamed_Passenger : 0,
                                'seating_capacity' => $mmv_data->s_cap,
                                'default_paid_driver' => (int)$Legal_Liability_to_Paid_Drivers,
                                'motor_additional_paid_driver' => $motor_additional_paid_driver,
                                'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                                'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                                'compulsory_pa_own_driver' => (int)$PA_Owner_Driver,
                                'total_accessories_amount(net_od_premium)' => 0,
                                'total_own_damage' => $is_liability? 0 :(int)$Own_Damage_Basic,
                                //'cng_lpg_tp' => (int)$CNG_LPG_Kit_Liability,
                                'total_liability_premium' => $final_tp_premium,
                                'net_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'service_tax' => 18,
                                'total_discount_od' => 0,
                                'add_on_premium_total' => (int)$total_add_ons_premium,
                                'addon_premium' => (int)$total_add_ons_premium,
                                //'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                'quotation_no' => '',
                                'premium_amount'  => $final_payable_amount,
                                'antitheft_discount' => (int)$AntiTheft_Discount,
                                'final_od_premium' =>$is_liability ? 0 : (int)$final_od_premium,
                                'final_tp_premium' => (int)$final_tp_premium,
                                'final_total_discount' =>(int) $final_total_discount,
                                'final_net_premium' => $final_net_premium,
                                'final_gst_amount' => $final_gst_amount,
                                'final_payable_amount' => $final_payable_amount,
                                'service_data_responseerr_msg' => 'success',
                                'user_id' => $requestData->user_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'user_product_journey_id' => $requestData->user_product_journey_id,
                                'business_type' => ($requestData->business_type =='newbusiness') ? 'New Business' :(($requestData->business_type =='rollover') ? 'Roll Over' : $requestData->business_type),
                                'service_err_code' => NULL,
                                'service_err_msg' => NULL,
                                'policyStartDate' => date('d-m-Y', strtotime($policyStartDate)),
                                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                                'ic_of' => $productData->company_id,
                                'vehicle_in_90_days' => NULL,
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
                                "max_addons_selection"=> NULL,
                                "applicable_addons"=> $is_liability ? [] :$applicable_addons,
                                'add_ons_data' =>  $add_ons
                            ],
                        ];
                        if($is_lpg_cng){
                            $data_response['Data']['cng_lpg_tp'] = (int)$CNG_LPG_Kit_Liability;
                            $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                            $data_response['Data']['motor_lpg_cng_kit_value'] = (int)$CNG_LPG_Kit_Own_Damage;
                        }
                        if(!empty($cpa_tenure)&&$requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
                        {
                            $data_response['Data']['multi_Year_Cpa'] = $PA_Owner_Driver;
                        }
                        return camelCase($data_response);
                    }//allowable discount
                    else{
                        return [
                            'webservice_id' => $get_quote_data['webservice_id'],
                            'table' => $get_quote_data['table'],
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'Loading More than 300 Is Not Allowed'
                        ];
                    }
                }else
                {
                    return [
                        'webservice_id' => $get_quote_data['webservice_id'],
                        'table' => $get_quote_data['table'],
                        'status' => false,
                        'premium_amount' => 0,
                        'message'        => $quote_data['message'] ?? ($quote_data['msg'] ?? json_encode($quote_data))
                    ];   
                }    
                }//quote service
                else{
                    return [
                        'webservice_id' => $get_quote_data['webservice_id'],
                        'table' => $get_quote_data['table'],
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'Endpoint request timed out'
                    ];
                }
        }//token
        else{
            return [
                'webservice_id' => $get_token_data['webservice_id'],
                'table' => $get_token_data['table'],
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Something went wrong'
            ];
        }
    
    
    
            }//mmv
        
    }//carage
// }//main


















