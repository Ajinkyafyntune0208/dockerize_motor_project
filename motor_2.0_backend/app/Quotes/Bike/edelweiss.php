<?php
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Quotes/Bike/V1/edelweiss.php';
function getQuote($enquiryId, $requestData, $productData)
{
    if (config('IC.EDELWEISS.V1.BIKE.ENABLE') == 'Y') {
     
        return getV1Quote($enquiryId, $requestData, $productData);
    } 
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
    $no_prev_data = false;
    if($requestData->business_type != 'newbusiness')
    {
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $no_prev_data   = ($requestData->previous_policy_type == 'Not sure') ? true : false;
        if($no_prev_data)
        {
            $date2 = new DateTime(date('Y-m-d'));
        }
        else
        {
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        }
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) +1;
        $bike_age = ceil($age / 12);
    }
    else
    {
        $bike_age = 0;
    }
    $premium_type = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    if (($bike_age >= 15) && ($tp_check == 'true')){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
        ];
    }
    if ($bike_age > 10)
    {
        return ['premium_amount' => 0, 'status' => false ,'message' => 'Bike Age should not be greater than 10 years','request'=>['bike age'=>$bike_age]  ];
    }    
    else
    {
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
                'request'=>[
                    'mmv'=> $mmv,
                    'version_id'=>$requestData->version_id
                 ]
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
        $mmv_details = [
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
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id'=>$requestData->version_id
                 ]
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id'=>$requestData->version_id
                 ]
            ];
        }else
        {
            $reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
            if (count($reg_no) < 2) {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Invalid vehicle registration number or RTO code',
                    'request' => [
                        'message' => 'Invalid vehicle registration number or RTO code',
                        'rto_code' => $requestData->rto_code,
                        'vehicle_registration_no'=>$requestData->vehicle_registration_no
                    ]
                ];
            }
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
            $idv = 0;

            if($requestData->business_type == 'newbusiness')
            {
                $idv = $mmv_data->upto6_months;  
            }
            else if($bike_age == 1)
            {
                $idv = $mmv_data->after_6_months_to_1yrs;
            }
            else if($bike_age == 2)
            {
                $idv = $mmv_data->upto_2yrs;
            }
            else if($bike_age == 3)
            {
                $idv = $mmv_data->upto_3yrs;
            }
            else if($bike_age == 4)
            {
                $idv = $mmv_data->upto_4yrs;
            }
            else if($bike_age == 5)
            {
                $idv = $mmv_data->upto_5yrs;
            }
            else if($bike_age == 6)
            {
                $idv = $mmv_data->upto_6yrs;
            }
            else if($bike_age == 7)
            {
                $idv = $mmv_data->upto_7yrs;
            }
            else if($bike_age == 8)
            {
                $idv = $mmv_data->upto_8yrs;
            }
            else if($bike_age == 9)
            {
                $idv = $mmv_data->upto_9yrs;
            }
            else if($bike_age == 10)
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
                            'age' => $bike_age
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
                    $policyTenure='5';
                    $contractTenure ='';
                    $transferOfNcb = 'N';
                    $new_vehicle = true;
                    $previousInsurancePolicy = '0';
                    $applicable_ncb_rate = $requestData->applicable_ncb;
                    $policy_end_date = date('d-m-Y', strtotime('+5 year -1 day', strtotime($policyStartDate)));
                }
                else
                {
                    $typeOfBusiness    = 'Rollover'; 
                    $policyType        = 'Package Policy';
                    $policyTenure      = '1';
                    $contractTenure    = '1.0';
                    $previousInsurancePolicy = '1';
                    $policyStartDate = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    if ($no_prev_data || get_date_diff('day', $requestData->previous_policy_expiry_date) > 0)
                    {
                        $policyStartDate = date('Y-m-d', strtotime('+2 day'));
                    }
                    else
                    {
                        $policyStartDate = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                    }
                    if ($requestData->is_claim == 'N')
                    {
                        $applicable_ncb_rate = $requestData->applicable_ncb;
                        $current_ncb_rate = $requestData->previous_ncb;
                        $transferOfNcb = 'Y';
                        
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
                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policyStartDate)));

            $token_cache_name = 'constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION' . $enquiryId;  
            $get_response = Cache::get($token_cache_name); 

            if(empty($get_response)){
                $get_response = cache()->remember($token_cache_name, 60 * 45, function () use ($enquiryId, $productData) {
                    $token_data = getWsData(
                        config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_TOKEN_GENERATION'),
                        '',
                        'edelweiss',
                        [
                            'enquiryId' => $enquiryId,
                            'requestMethod' => 'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Token genration',
                            'userId' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME'),
                            'password' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD'),
                            'type' => 'Token genration',
                            'transaction_type' => 'quote',
                        ]
                    );
                    return $token_data;
                });
            }
            
            $token_data = json_decode($get_response["response"], true);
            
            if(empty($token_data) || !isset($token_data['access_token']) ) {
                return [
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => 'Access Token Not received from IC'
                ];
            }
                $voluntary_excess_value = [
                    500 => 500,
                    750 =>750,
                    1000=>1000,
                    1500=>1500,
                    3000=>3000
                    ];
                if($requestData->voluntary_excess_value == 0 ||!empty($voluntary_excess_value[$requestData->voluntary_excess_value]))
                {
                    $requestData->voluntary_excess_value;
                }else{
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'voluntary deductable amount not allowed',
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table']
                    ];
                }
                //discount section start
                $discount_array = [];
                if($requestData->is_claim == 'N' && !$is_liability && $requestData->business_type != 'newbusiness')
                {
                    $discount_array[] = "No Claim Bonus Discount";
                }
                if (isset($requestData->voluntary_excess_value) && ($requestData->voluntary_excess_value !=0)) {
                $discount_array[] = "Voluntary Deductible Discount";
                }
                if ($requestData->anti_theft_device =='Y' && $requestData->business_type != 'newbusiness') {
                    $discount_array[] = "AntiTheft Discount";
                }
                $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                ->first();
                //discount section end
                $tp_coverage_array = [];
                $additional_covers  = [];
                $additional_covers[] =  [
                    "subCoverage" => "Own Damage Basic",
                    "limit" => "Own Damage Basic Limit"
                ];
                if (!empty($additional['accessories'])) {
                    foreach ($additional['accessories'] as $key => $data) {
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
                        if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']))
                        {
                            $cover_pa_unnamed_passenger = $data['sumInsured'];
                            $tp_coverage_array[] = [
                                "subCoverage" => "PA Unnamed Passenger",
                                "limit" => "PA Unnamed Passenger Limit",
                                "sumInsuredPerPerson" => $cover_pa_unnamed_passenger,
                                "noofPersons"         => '1'
                            ];
                        }
                    }
                }
                $applicable_addons = [
                    'zeroDepreciation','returnToInvoice','consumables'
                ];

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
                if ($zero_addon_age == false && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC'))))
                {
                    return [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                        'request'=>[
                            'bike age'=>$bike_age,
                            'productData'=>$productData->zero_dep
                            ]
                        ];
                }
                $add_on_array = [];
               
                if($zero_addon_age && $productData->zero_dep == 0)
                {
                  $add_on_array[] = [ "subCoverage" => "Zero Depreciation" ];

                }else{
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                }
                if($rti_addon_age)
                {
                  $add_on_array[] = [ "subCoverage" => "Return To Invoice" ];

                }
                else{
                    array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                }
                if($bike_age <= 10)
                {
                  $add_on_array[] = [ "subCoverage" => "Consumable Cover" ];

                }else{
                    array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
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

                 $add_on = (!$is_liability ? [
                    "contract" => "Addon Contract",
                    "coverage" => [
                        "coverage" => "Add On Coverage",
                        //"deductible" => "Key Replacement Deductible",
                        "underwriterDiscount" => "0.0",
                        "subCoverage" => $add_on_array
                    ]
                    ] : []);
                if(isset($token_data['access_token']))
                {
                    $service_request_data = [
                            'channelCode'       => '002',
                            'branch'            => config('constants.IcConstants.edelweiss.EDELWEISS_BRANCH'),
                            'make'              => $mmv_data->make,
                            'model'             => $mmv_data->model,
                            'variant'           => $mmv_data->variant,
                            'idvCity'           => $veh_rto_data->city,
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
                            'policyType'        => $policyType,//($is_package ? 'Package Policy' : ($is_liability ? 'Liability Only' : 'standalone OD')),
                            'policyStartDate'   => $policyStartDate,
                            'policyTenure'      => $policyTenure,
                            'claimDeclaration'  => '',
                            'previousNCB'       => $requestData->is_claim == 'Y' ? $requestData->previous_ncb : $current_ncb_rate,
                            'annualMileage'     => '10000',
                            'fuelType'          => $mmv_data->fuel,//'CNG (Inbuilt)',
                            'transmissionType'  => '',//Pass only if Engine Cover
                            'dateOfTransaction' => date('Y-m-d'),
                            'subPolicyType'     => '',
                            'validLicenceNo'    => 'Y',
                            'transferOfNcb'     => $transferOfNcb,//'Yes',
                            'transferOfNcbPercentage' => $applicable_ncb_rate,
                            'proofOfNcb' => 'NCBRESRV',
                            'protectionOfNcbValue' => $applicable_ncb_rate,
                            'breakinInsurance' => 'NBK',
                            'contractTenure' => $contractTenure,
                            'overrideAllowableDiscount' => 'N',
                            'fibreGlassFuelTank' => 'N',
                            'antiTheftDeviceInstalled' =>  "No",
                            'automobileAssociationMember' => "No",
                            'bodystyleDescription' => 'COUPE',
                            'dateOfFirstPurchaseOrRegistration' => date('Y-m-d', strtotime($vehicleDate)),
                            'dateOfBirth' => '1981-04-03',
                            'policyHolderGender' => 'Male',
                            'policyholderOccupation' => 'Medium to High',
                            'typeOfGrid' => 'Grid 1',
                            'contractDetails' => [
                            ]
                    ];

                    if($no_prev_data)
                    {
                        $service_request_data['previousInsurancePolicy'] = '0';
                        $service_request_data['previousPolicyExpiryDate'] = null;
                    }

                    $vol=
                        [
                            "contract" => "Own Damage Contract",
                            "coverage" => [
                                "coverage" => "Own Damage Coverage",
                                "deductible" => "Own Damage Basis Deductible",
                                "discount" => $discount_array,
                                "subCoverage" => $additional_covers
                            ]
                            ];
                    if(!$is_liability){
                        if (isset($requestData->voluntary_excess_value) && $requestData->voluntary_excess_value !=0) {
                            $vol['coverage']['voluntaryDeductible']= $requestData->voluntary_excess_value;
                        }
                       array_push($service_request_data['contractDetails'],$vol);
                       array_push($service_request_data['contractDetails'], $add_on);

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
                                    "sumInsuredPerPerson" => "1500000"
                                ]
                            ]
                        ]);
                        array_push($service_request_data['contractDetails'], $tp_block);
                    }

                    
                $checksum_data = checksum_encrypt($service_request_data);
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'edelweiss',$checksum_data,'BIKE');
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                {
                    $get_response = $is_data_exits_for_checksum;
                }
                else
                {
                    $get_response = getWsData(config('constants.IcConstants.edelweiss.END_POINT_URL_EDELWEISS_BIKE_QUOTE_GENERATION'),$service_request_data, 'edelweiss',
                        [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'productName'  => $productData->product_name,
                            'company'  => 'edelweiss',
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Premium Calculation',
                            'checksum' => $checksum_data,
                            'authorization'  => $token_data['access_token'],
                            'userId' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME'),
                            'password' => config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD'),
                            'transaction_type' => 'quote',
                        ]);
                }
                        $quote_data = $get_response['response'];
                    if($quote_data)
                    {
                        
                            $quote_data = json_decode($quote_data,TRUE);
                        if(isset($quote_data['policyData']))
                        {
                            $allowableDiscount = 0;
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
                                           if($value['salesProductTemplateId'] == 'Own Damage Basic')
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
                                $Total_Discounts = $Auto_Mobile_Association_Discount = $AntiTheft_Discount = $No_Claim_Bonus_Discount = $VoluntaryDeductibleDiscount= 0;
                                $Tyre_Safeguard = $Zero_Depreciation = $Engine_Protect = $Return_To_Invoice = $Key_Replacement = 
                                $Loss_of_Personal_Belongings = $Protection_of_NCB = $Basic_Road_Assistance = 
                                $Consumable_Cover =  $Waiver_of_Policy = 0;
                                $total_TP_Amount = $Third_Party_Basic_Sub_Coverage = $CNG_LPG_Kit_Liability = $Legal_Liability_to_Paid_Drivers = $PA_Unnamed_Passenger = 0;
                                $PA_Owner_Driver = 0;
                                $total_add_ons_premium = 0;
                        foreach ($quote_data['contractDetails'] as $sections) 
                        {
                            $templateid =$sections['salesProductTemplateId'];

                            switch($templateid)
                            {
                                case 'Own Damage Contract'://od Section
                                    $od_section_array = $sections;
                                    $exshowroomPrice = $sections['insuredObject']['exshowroomPrice'];
                                    if(isset($od_section_array['coverage']['subCoverage']['salesProductTemplateId']))
                                    {
                                        if($od_section_array['coverage']['subCoverage']['salesProductTemplateId'] == 'Own Damage Basic')
                                        {
                                            $Total_Own_Damage_Amount = $Own_Damage_Basic =  ($od_section_array['coverage']['subCoverage']['totalPremium']);
                                        } 
                                    }
                                    else
                                    {
                                        foreach ($od_section_array['coverage']['subCoverage'] as $subCoverage) 
                                        {
                                            if($subCoverage['salesProductTemplateId'] == 'Own Damage Basic')
                                            {
                                                $Total_Own_Damage_Amount += $Own_Damage_Basic =  ($subCoverage['totalPremium']);
                                            }

                                            if($subCoverage['salesProductTemplateId'] == 'Non Electrical Accessories')
                                            {
                                                $Total_Own_Damage_Amount += $Non_Electrical_Accessories =  ($subCoverage['totalPremium']);
                                            }

                                            if($subCoverage['salesProductTemplateId'] == 'Electrical Electronic Accessories')
                                            {
                                                $Total_Own_Damage_Amount += $Electrical_Accessories =  ($subCoverage['totalPremium']);
                                            }
                                                                             
                                        }                                        
                                    } 
                                    
                                    //Discount Section  
                                    
                                    if(isset($od_section_array['coverage']['coverageSurchargesOrDiscounts']))
                                    {
                                        $response_discount_array  = $od_section_array['coverage']['coverageSurchargesOrDiscounts'];
                                       // dd($response_discount_array);
                                        if(isset($response_discount_array['salesProductTemplateId']))
                                        {
                                            if($response_discount_array['salesProductTemplateId'] == 'No Claim Bonus Discount')
                                            {
                                               $Total_Discounts += $No_Claim_Bonus_Discount =  $response_discount_array['totalSurchargeandDiscounts']; 
                                            }
                                            if($response_discount_array['salesProductTemplateId'] == 'Voluntary Deductible Discount')
                                            {
                                               $Total_Discounts += $VoluntaryDeductibleDiscount =  $response_discount_array['totalSurchargeandDiscounts']; 
                                            }
                                                                                    
                                        }
                                        else
                                        {                                    
                                            foreach ($od_section_array['coverage']['coverageSurchargesOrDiscounts'] as $subCoverage) 
                                            {
                                                if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'No Claim Bonus Discount')
                                                {
                                                   $Total_Discounts += $No_Claim_Bonus_Discount =  $subCoverage['totalSurchargeandDiscounts']; 
                                                }
                                                if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'AntiTheft Discount')
                                                {
                                                   $Total_Discounts += $AntiTheft_Discount =  $subCoverage['totalSurchargeandDiscounts']; 
                                                }
                                                if(isset($subCoverage['salesProductTemplateId']) && $subCoverage['salesProductTemplateId'] == 'Voluntary Deductible Discount')
                                                {
                                                   $Total_Discounts += $VoluntaryDeductibleDiscount
                                                   =  $subCoverage['totalSurchargeandDiscounts']; 
                                                }
                                            }                                          
                                        }
                                    }

                                break;

                                 case 'Addon Contract' ://addon Section
                                    $add_ons_section_array = $sections;
                                if(isset($add_ons_section_array['coverage']['subCoverage']))
                                {
                                if(isset($add_ons_section_array['coverage']['subCoverage'][0]))
                                {  
                                    foreach ($add_ons_section_array['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if($subCoverage['salesProductTemplateId'] == 'Zero Depreciation')
                                        {
                                            $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'Consumable Cover')
                                        {
                                            $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium'];
                                        }                                
                                        else if($subCoverage['salesProductTemplateId'] == 'Return To Invoice')
                                        {
                                            $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium'];
                                        }                                
                                    }
                                }else
                                {
                                    
                                    $subCoverage = $sections['coverage']['subCoverage'];
                                
                                    if($subCoverage['salesProductTemplateId'] == 'Zero Depreciation')
                                        {
                                            $total_add_ons_premium += $Zero_Depreciation =  $subCoverage['totalPremium'];
                                        }
                                    else if($subCoverage['salesProductTemplateId'] == 'Consumable Cover')
                                    {
                                        $total_add_ons_premium += $Consumable_Cover =  $subCoverage['totalPremium'];
                                    }                                
                                    else if($subCoverage['salesProductTemplateId'] == 'Return To Invoice')
                                    {
                                        $total_add_ons_premium += $Return_To_Invoice =  $subCoverage['totalPremium'];
                                    }

                                }
                                }
                                break;

                                case 'Third Party Multiyear Contract' ://Third Party Section
                                    $TP_section_array = $sections;
                                    if(isset($sections['coverage']['subCoverage'][0]))
                                    {  
                                    foreach ($TP_section_array['coverage']['subCoverage'] as $subCoverage) 
                                    {
                                        if($subCoverage['salesProductTemplateId'] == 'Third Party Basic Sub Coverage')
                                            {
                                                $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                            }
                                            else if($subCoverage['salesProductTemplateId'] == 'Legal Liability to Paid Drivers')
                                         {
                                             $total_TP_Amount += $Legal_Liability_to_Paid_Drivers =  $subCoverage['totalPremium']; 
                                         }
                                           
                                            else if($subCoverage['salesProductTemplateId'] == 'PA Unnamed Passenger')
                                            {
                                                $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium']; 
                                            }
                                    }
                                }else{
                                    $subCoverage = $sections['coverage']['subCoverage'];
                                    if($subCoverage['salesProductTemplateId'] == 'Third Party Basic Sub Coverage')
                                    {
                                        $total_TP_Amount += $Third_Party_Basic_Sub_Coverage =  $subCoverage['totalPremium'];
                                    }
                                    else if($subCoverage['salesProductTemplateId'] == 'PA Unnamed Passenger')
                                    {
                                        $total_TP_Amount += $PA_Unnamed_Passenger =  $subCoverage['totalPremium']; 
                                    }
                                    }
                                break;

                                case 'PA Compulsary Contract' ://PA Owner Driver
                                    if($sections['coverage']['subCoverage']['salesProductTemplateId'] == 'PA Owner Driver')
                                    {
                                        $PA_Owner_Driver = $sections['coverage']['subCoverage']['totalPremium'];
                                    }
                                break;
                            }

                        }
                        $geog_Extension_OD_Premium = 0;
                        $geog_Extension_TP_Premium = 0;
                        $aai_discount = $AntiTheft_Discount;
                        $antitheft_discount = 0;
                        $final_od_premium = $Total_Own_Damage_Amount ;
                        $final_tp_premium = $total_TP_Amount;
                        $final_total_discount = $Total_Discounts;
                        $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount + $total_add_ons_premium);
                        $final_gst_amount   = round($final_net_premium * 0.18);
                        $final_payable_amount  = round($final_net_premium + $final_gst_amount);
                        $data_response = [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'status' => true,
                            'msg' => 'Found',
                            'Data' => [
                                'idv' => $vehicle_idv,
                                'min_idv' => round($min_idv),
                                'max_idv' => round($max_idv),
                                'default_idv' => $idv,
                                'vehicle_idv' => $vehicle_idv,
                                'qdata' => null,
                                'pp_enddate' => ($no_prev_data ? 'New' : $requestData->previous_policy_expiry_date),
                                'addonCover' => null,
                                'addon_cover_data_get' => '',
                                'rto_decline' => null,
                                'rto_decline_number' => null,
                                'mmv_decline' => null,
                                'mmv_decline_name' => null,
                                'policy_type' => $is_liability ? 'Third Party' :(($is_od) ? 'Own Damage' : 'Comprehensive'),
                                'cover_type' => '1YC',
                                'hypothecation' => '',
                                'hypothecation_name' => '',
                                'vehicle_registration_no' => $requestData->rto_code,
                                'voluntary_excess' => $VoluntaryDeductibleDiscount,
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
                                    'bike_age' => $bike_age,
                                    'ic_vehicle_discount' => '',
                                ],
                                'basic_premium' =>(int) $Own_Damage_Basic,
                                'deduction_of_ncb' => (int)$No_Claim_Bonus_Discount,
                                'tppd_premium_amount' => (int)$Third_Party_Basic_Sub_Coverage,
                                'tppd_discount' => 0,
                                'motor_electric_accessories_value' =>(int) $Electrical_Accessories,
                                'motor_non_electric_accessories_value' => (int)$Non_Electrical_Accessories,
                                'motor_lpg_cng_kit_value' => (int)$CNG_LPG_Kit_Own_Damage,
                                'cover_unnamed_passenger_value' => (int) $PA_Unnamed_Passenger,
                                'seating_capacity' => $mmv_data->s_cap,
                                'default_paid_driver' => (int)$Legal_Liability_to_Paid_Drivers,
                                'motor_additional_paid_driver' => 0,
                                'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                                'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                                'compulsory_pa_own_driver' => (int)$PA_Owner_Driver,
                                'total_accessories_amount(net_od_premium)' => 0,
                                'total_own_damage' => $is_liability ? 0 :$final_od_premium,
                                'cng_lpg_tp' => (int)$CNG_LPG_Kit_Liability,
                                'total_liability_premium' => $final_tp_premium,
                                'net_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'service_tax' => 18,
                                'total_discount_od' => 0,
                                'add_on_premium_total' => $total_add_ons_premium,
                                'addon_premium' => $total_add_ons_premium,
                                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                'quotation_no' => '',
                                'premium_amount'  => $final_payable_amount,
                                'antitheft_discount' => $aai_discount,
                                'final_od_premium' =>$is_liability ? 0 : $final_od_premium,
                                'final_tp_premium' => $final_tp_premium,
                                'final_total_discount' => $final_total_discount,
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
                                "applicable_addons"=> $applicable_addons,
                                'add_ons_data' =>   [
                                    'in_built'   => [],
                                    'additional' => [
                                        'zeroDepreciation' => (int)$Zero_Depreciation,
                                        'road_side_assistance' => (int) $Basic_Road_Assistance,
                                        'engineProtector' => (int) $Engine_Protect,
                                        'ncbProtection' => (int) $Protection_of_NCB,
                                        'keyReplace' => (int) $Key_Replacement,
                                        'consumables' => (int) $Consumable_Cover,
                                        'tyreSecure' => (int) $Tyre_Safeguard,
                                        'returnToInvoice' => (int) $Return_To_Invoice,
                                        'lopb' => (int) $Loss_of_Personal_Belongings
                                    ]
                                ],
                            ],
                        ];
            
                        return camelCase($data_response);
                    }//allowable discount
                    else{
                        return [
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'Loading More than 300 Is Not Allowed'
                        ];
                    }
                }else
                {
                    return [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'status' => false,
                        'premium_amount' => 0,
                        'message'        => $quote_data['message'] ?? ($quote_data['msg'] ?? json_encode($quote_data))
                    ];   
                }    
                }//quote service
                else{
                    return [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'Endpoint request timed out'
                    ];
                }
        }//token
        else{
            return [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Access Token Not received from IC'
            ];
        }
    
    
    
            }//mmv
        
    }//carage



}//main


















