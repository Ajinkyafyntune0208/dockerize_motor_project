<?php

namespace App\Quotes\Cv\V1;

use App\Models\MasterRto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
include_once app_path() . '/Helpers/CvWebServiceHelper.php';
class hdfc_ergo
{
    public static function getQuote($enquiryId, $requestData, $productData)
{
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
    if (empty($requestData->rto_code)) {
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

    $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ],
        ];          
    }

    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];        
    } elseif ($mmv->ic_version_code == 'DNE') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];        
    }

    $parent_id = get_parent_code($productData->product_sub_type_id);

    $premium_type = DB::table('master_premium_type')
        ->where('id',$productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    // $mmv_data = [
    //     'manf_name' => $mmv->manufacturer,
    //     'model_name' => $mmv->vehicle_model,
    //     'version_name' => $mmv->txt_variant,
    //     'seating_capacity' => $mmv->seating_capacity,
    //     'carrying_capacity' => $mmv->carrying_capacity,
    //     'cubic_capacity' => $mmv->cubic_capacity,
    //     'fuel_type' =>  $mmv->txt_fuel,
    //     'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
    //     'vehicle_type' => $parent_id,
    //     'version_id' => $mmv->version_id,
    // ];

    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();

    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO code does not exist',
            'request' => [
                'message' => 'RTO code does not exist',
                'rto_code' => $requestData->rto_code
            ]
        ];
    }

    $vehicle_class_code = [
        'TAXI' => [
            'vehicle_class_code' => 41,
            'vehicle_sub_class_code' => 1
        ],
        'AUTO-RICKSHAW' => [
            'vehicle_class_code' => 41,
            'vehicle_sub_class_code' => 5
        ],
        'ELECTRIC-RICKSHAW' => [
            'vehicle_class_code' => 41,
            'vehicle_sub_class_code' => 5
        ],
        'PICK UP/DELIVERY/REFRIGERATED VAN' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 2
        ],
        'DUMPER/TIPPER' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 3
        ],
        'TRUCK' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 7
        ],
        'TRACTOR' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 5
        ],
        'TANKER/BULKER' => [
            'vehicle_class_code' => 24,
            'vehicle_sub_class_code' => 4 #6
        ]
    ];

    // $rto_location = DB::table('hdfc_ergo_rto_master')
    //     ->where('txt_rto_location_desc', $rto_data->rto_name)
    //     ->where('num_vehicle_class_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_class_code'])
    //     ->where('num_vehicle_subclass_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_sub_class_code'])
    //     ->first();

    $rto_cities = explode('/',  $rto_data->rto_name);
    foreach ($rto_cities as $rto_city)
    {
        $rto_city = strtoupper($rto_city);
        $rto_location = DB::table('hdfc_ergo_rto_master')
            ->where('txt_rto_location_desc', 'like', '%' . $rto_city . '%')
            ->where('num_vehicle_class_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_class_code'])
            ->where('num_vehicle_subclass_code', $vehicle_class_code[$productData->product_sub_type_code]['vehicle_sub_class_code'])
            ->first();
        $rto_location = keysToLower($rto_location);
        if (!empty($rto_location))
        {
            break;
        }
    }

    if (empty($rto_location)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request' => [ 
                'rto_code' => $requestData->rto_code,
                'message' => 'RTO details does not exist with insurance company',
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    if ($parent_id == 'PCV') {
        $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2313 : 2314;
    } elseif ($parent_id == 'GCV') {
        $product_code = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 2317 : 2315;
    }
    else
    {
        $product_code = 2316;
    }

    $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];
    $motor_manf_date = '01-'.$requestData->manufacture_year;
    $current_date = Carbon::now()->format('Y-m-d');

    $car_age = 0;
    $date1 = new \DateTime($requestData->vehicle_register_date);
    $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    if ($interval->y >= 15)
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes are not available for vehicles having age greater than or equal to 15 years.'
        ];
    }

    // if (
    //     $productData->zero_dep == 0 &&
    //     in_array($parent_id, ['PCV', 'GCV']) &&
    //     $interval->y >= 5
    // ) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero depreciation is not available for vehicles having age more than 5 years'
    //     ];
    // }

    if ($productData->product_identifier == 'RSA' && $interval->y >= 15) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Road side assistance is not available for vehicles having age more than 15 years'
        ];
    }

    $isRsa = $productData->product_identifier == 'RSA' && in_array($parent_id, ['PCV']);

    // if ($productData->zero_dep == 0 && $interval->y >= 3)
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Zero depreciation is not available for vehicles having age more than 3 years'
    //     ];
    // }

    if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
        $is_vehicle_new = 'false';
        $policy_start_date = date('Y-m-d', strtotime('+1 day', ! is_null($requestData->previous_policy_expiry_date) && $requestData->previous_policy_expiry_date != 'New' ? strtotime($requestData->previous_policy_expiry_date) : time()));

        if ($requestData->business_type == 'breakin' && $tp_only) {
            $today = date('Y-m-d');
            $policy_start_date = date('Y-m-d', strtotime($today . ' + 1 days'));
        }

        if ($requestData->vehicle_registration_no != '') {
            $registration_number = getRegisterNumberWithHyphen($requestData->vehicle_registration_no);
        } else {
            $reg_no_3 = chr(rand(65, 90));
            $registration_number = $requestData->rto_code.'-'.$reg_no_3.$reg_no_3.'-1234';
        }
    } elseif ($requestData->business_type == 'newbusiness')  {
        $requestData->vehicle_register_date = date('d-m-Y');
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        $registration_number = 'NEW';

        if ($date_difference > 0) {  
            return [
                'status' => false,
                'message' => 'Please Select Current Date for New Business',
                'request' => [
                    'message' => 'Please Select Current Date for New Business',
                    'business_type' => $requestData->business_type,
                    'vehicle_register_date' => $requestData->vehicle_register_date
                ]
            ];
        }

        $is_vehicle_new = 'true';
        $policy_start_date = Carbon::today()->format('Y-m-d');
        $previousNoClaimBonus = 'ZERO';

        if ($requestData->vehicle_registration_no == 'NEW') {
            $vehicle_registration_no  = str_replace("-", "", $requestData->rto_code) . "-NEW";
        } else {
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
        }
    }

    $transaction_id = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);

    if ($tp_only)
    {
        $cache_name = 'HDFC_ERGO_CV_JSON_TP_' . $product_code;
    }
    elseif ($productData->zero_dep == 0)
    {
        $cache_name = 'HDFC_ERGO_CV_JSON_ZERO_DEP_' . $product_code;
    }
    else
    {
        $cache_name = 'HDFC_ERGO_CV_JSON_BASIC_' . $product_code;
    }

    $get_response = getWsData(
        config('IC.HDFC_ERGO.V1.CV.AUTHENTICATE_URL'),
        [],
        'hdfc_ergo',
        [
            'section' => $productData->product_sub_type_code,
            'method' => 'Token Generation',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'quote',
            'product_code' => $product_code,
            'transaction_id' => $transaction_id,
            'headers' => [
                'Content-type' => 'application/json',
                'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                'PRODUCT_CODE' => $product_code,
                'TransactionID' => $transaction_id,
                'Accept' => 'application/json',
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CV.CREDENTIAL')
            ]
        ]
    );

    $token_data = $get_response['response'];
    if ($token_data) {
        $token_data = json_decode($token_data, TRUE);

        if (isset($token_data['StatusCode']) && $token_data['StatusCode'] == 200) {
            $idv_calculation_array = [
                'TransactionID' => $transaction_id,
                'Customer_Details' => [],
                'Policy_Details' => [],
                'Req_GCV' => [],
                'Req_MISD' => [],
                'Req_PCV' => [],
                'Payment_Details' => [],
                'IDV_DETAILS' => [
                    'ModelCode' => $mmv->vehicle_model_code,
                    'RTOCode' => $rto_location->txt_rto_location_code,
                    'Vehicle_Registration_Date' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'Policy_Start_Date' => date('d/m/Y', strtotime($policy_start_date))
                ]
            ];

            if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.CALCULATE_IDV_URL'), $idv_calculation_array, 'hdfc_ergo', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'IDV Calculation',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'quote',
                    'requestMethod' => 'post',
                    'product_code' => $product_code,
                    'transaction_id' => $transaction_id,
                    'token' => $token_data['Authentication']['Token'],
                    'headers' => [
                        'Content-type' => 'application/json',
                        'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                        'PRODUCT_CODE' => $product_code,
                        'TransactionID' => $transaction_id,
                        'Accept' => 'application/json',
                        'Token' => $token_data['Authentication']['Token']
                    ]
                ]);
                $idv_data = $get_response['response'];
                $idv_data = json_decode($idv_data, TRUE);
            }

            $skip_second_call = false;
            if (isset($idv_data) && $idv_data || in_array($premium_type, ['third_party', 'third_party_breakin'])) {

                if (isset($idv_data['StatusCode']) && $idv_data['StatusCode'] == 200 || in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $vehicle_idv = $min_idv = $max_idv = $default_idv = 0;
                    // $min_idv = $idv_data['CalculatedIDV']['MIN_IDV_AMOUNT'];
                    // $max_idv = $idv_data['CalculatedIDV']['MAX_IDV_AMOUNT'];
                    // $vehicle_idv = $idv_data['CalculatedIDV']['IDV_AMOUNT'];

                    if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                        $vehicle_idv = $idv_data['CalculatedIDV']['IDV_AMOUNT'];
                        $min_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $idv_data['CalculatedIDV']['MIN_IDV_AMOUNT'];
                        $max_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $idv_data['CalculatedIDV']['MAX_IDV_AMOUNT'];
                        $default_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $idv_data['CalculatedIDV']['IDV_AMOUNT'];
                    }
                    if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                        if ($requestData->edit_idv >= $max_idv) {
                            $vehicle_idv = floor($max_idv);
                        } elseif ($requestData->edit_idv <= $min_idv) {
                            $vehicle_idv = floor($min_idv);
                        } else {
                            $vehicle_idv = floor($requestData->edit_idv);
                        }
                    } else {
                        $getIdvSetting = getCommonConfig('idv_settings');
                        switch ($getIdvSetting) {
                            case 'default':
                                $data_idv['CalculatedIDV']['IDV_AMOUNT'] = $default_idv;
                                $skip_second_call = true;
                                $idv =  $default_idv;
                                break;
                            case 'min_idv':
                                $data_idv['CalculatedIDV']['MIN_IDV_AMOUNT'] = $min_idv;
                                $idv =  $min_idv;
                                break;
                            case 'max_idv':
                                $data_idv['CalculatedIDV']['MAX_IDV_AMOUNT'] = $max_idv;
                                $idv =  $max_idv;
                                break;
                            default:
                            $idv = $data_idv['CalculatedIDV']['IDV_AMOUNT'] = $default_idv;
                                $idv =  $min_idv;
                                break;
                        }
                        $vehicle_idv = $min_idv;
                    }
                    $business_types = [
                        'rollover' => 'Roll Over',
                        'newbusiness' => 'New Vehicle',
                        'breakin' => 'Breakin'
                    ];

                    $selected_addons = DB::table('selected_addons')
                        ->where('user_product_journey_id', $enquiryId)
                        ->first();

                    $is_electrical_accessories = NULL;
                    $is_non_electrical_accessories = NULL;
                    $external_kit_type = NULL;
                    $external_kit_value = 0;
                    $electrical_accessories_value = 0;
                    $non_electrical_accessories_value = 0;
                    $pa_paid_driver_sum_insured = 0;
                    $no_of_unnamed_passenger = 0;
                    $unnamed_passenger_sum_insured = 0;
                    $no_of_ll_paid_drivers = 0;
                    $no_of_ll_paid_conductors = 0;
                    $no_of_ll_paid_cleaners = 0;
                    $is_anti_theft = false;
                    $voluntary_excess_value = NULL;
                    $is_tppd_cover = 0;
                    $is_vehicle_limited_to_own_premises = 0;
                    $no_of_ll_paid = 0;

                    if ($selected_addons && !empty($selected_addons)) {
                        if ($selected_addons->accessories != NULL && $selected_addons->accessories != '') {
                            $accessories = json_decode($selected_addons->accessories, TRUE);

                            foreach ($accessories as $accessory) {
                                if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                                    $external_kit_type = 'CNG';
                                    $external_kit_value = $accessory['sumInsured'];
                                } elseif ($accessory['name'] == 'Electrical Accessories') {
                                    $is_electrical_accessories = 'Y';
                                    $electrical_accessories_value = $accessory['sumInsured'];
                                } elseif ($accessory['name'] == 'Non-Electrical Accessories') {
                                    $is_non_electrical_accessories = 'Y';
                                    $non_electrical_accessories_value = $accessory['sumInsured'];
                                }
                            }
                        }

                        if ($selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
                            $additional_covers = json_decode($selected_addons->additional_covers, TRUE);

                            foreach ($additional_covers as $additional_cover) {
                                if ($additional_cover['name'] == 'PA cover for additional paid driver') {
                                    $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                                } elseif ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                                    $no_of_unnamed_passenger = $mmv->seating_capacity;
                                    $unnamed_passenger_sum_insured = $additional_cover['sumInsured'];
                                } elseif ($additional_cover['name'] == 'LL paid driver') {
                                    $no_of_ll_paid_drivers = 1;
                                } elseif ($additional_cover['name'] == 'LL paid driver/conductor/cleaner') {
                                    if (isset($additional_cover['LLNumberCleaner']) && $additional_cover['LLNumberCleaner'] > 0) {
                                        $no_of_ll_paid_cleaners = $additional_cover['LLNumberCleaner'];
                                    }

                                    if (isset($additional_cover['LLNumberDriver']) && $additional_cover['LLNumberDriver'] > 0) {
                                        $no_of_ll_paid_drivers = $additional_cover['LLNumberDriver'];
                                    }

                                    if (isset($additional_cover['LLNumberConductor']) && $additional_cover['LLNumberConductor'] > 0) {
                                        $no_of_ll_paid_conductors = $additional_cover['LLNumberConductor'];
                                    }

                                    $no_of_ll_paid = $no_of_ll_paid_cleaners + $no_of_ll_paid_drivers + $no_of_ll_paid_conductors;                                    
                                } elseif ($additional_cover['name'] == 'PA paid driver/conductor/cleaner' && isset($additional_cover['sumInsured'])) {
                                    $pa_paid_driver_sum_insured = $additional_cover['sumInsured'];
                                }
                            }
                        }

                        if ($selected_addons->discounts != NULL && $selected_addons->discounts != '') {
                            $discounts = json_decode($selected_addons->discounts, TRUE);

                            foreach ($discounts as $discount) {
                                if ($discount['name'] == 'anti-theft device') {
                                    $is_anti_theft = true;
                                } elseif ($discount['name'] == 'voluntary_insurer_discounts') {
                                    $voluntary_excess_value = $discount['sumInsured'];
                                } elseif ($discount['name'] == 'TPPD Cover') {
                                    $is_tppd_cover = 1;
                                }
                                elseif ($discount['name'] == 'Vehicle Limited to Own Premises' && $parent_id != 'GCV') // #9062 [20-09-2022]
                                {
                                    $is_vehicle_limited_to_own_premises = 1;
                                }
                            }
                        }
                    }

                    $premium_calculation_request = [
                        'TransactionID' => $transaction_id,
                        'Customer_Details' => [
                            'Customer_Type' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Corporate'
                        ],
                        'Policy_Details' => [
                            'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                            'ProposalDate' => date('d/m/Y', time()),
                            'AgreementType' => NULL,
                            'FinancierCode' => NULL,
                            'BranchName' => NULL,
                            'PreviousPolicy_CorporateCustomerId_Mandatary' => NULL,
                            'PreviousPolicy_NCBPercentage' => $requestData->previous_ncb,
                            'PreviousPolicy_PolicyEndDate' => $requestData->business_type == 'newbusiness' ? NULL : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)),
                            'PreviousPolicy_PolicyNo' => NULL,
                            'PreviousPolicy_PolicyClaim' => $requestData->is_claim == 'N' ? 'NO' : 'YES',
                            'BusinessType_Mandatary' => $business_types[$requestData->business_type],
                            'VehicleModelCode' => $mmv->vehicle_model_code,
                            'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                            'YearOfManufacture' => $motor_manf_year,
                            'Registration_No' => $registration_number,
                            'EngineNumber' => 'dwdwad34343',
                            'ChassisNumber' => 'grgrgrg444',
                            'RTOLocationCode' => $rto_location->txt_rto_location_code,
                            'Vehicle_IDV'=> $vehicle_idv
                        ]
                    ];

                    if ($requestData->prev_short_term == '1') {
                        //if previous policy is short term, then pass pyp start date is for 3 month range
                        $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)
                            ->subMonth(3)
                            ->addDay(1)
                            ->format('Y-m-d');

                        $premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyStartDate'] = date(
                            'd/m/Y',
                            strtotime($prevPolyStartDate)
                        );
                    }

                    if ($requestData->previous_policy_type == 'Not sure' || $premium_type == 'third_party_breakin')
                    {
                        if ($requestData->previous_policy_type == 'Not sure')
                        {
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_NCBPercentage']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyEndDate']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyNo']);
                            unset($premium_calculation_request['Policy_Details']['PreviousPolicy_PolicyClaim']);
                        }

                        $premium_calculation_request['Policy_Details']['BusinessType_Mandatary'] = 'Roll Over';
                    }

                    // if ($requestData->is_idv_changed == 'Y') {                       	
                    //     if ($requestData->edit_idv >= $max_idv) {
                    //         $premium_calculation_request['Policy_Details']['Vehicle_IDV'] = $max_idv;
                    //     } elseif ($requestData->edit_idv <= $min_idv) {
                    //         $premium_calculation_request['Policy_Details']['Vehicle_IDV'] = $min_idv;
                    //     } else {
                    //         $premium_calculation_request['Policy_Details']['Vehicle_IDV'] = $requestData->edit_idv;
                    //     }
                    // } else {
                    //     $premium_calculation_request['Policy_Details']['Vehicle_IDV'];
                    // }

                    if ($requestData->is_idv_changed == 'Y') {                       	
                        if ($requestData->edit_idv >= $max_idv) {
                            $vehicle_idv = $max_idv;
                        } elseif ($requestData->edit_idv <= $min_idv) {
                            $vehicle_idv = $min_idv;
                        } else {
                            $vehicle_idv = $requestData->edit_idv;
                        }
                    } else {
                        $vehicle_idv = $min_idv;
                    }
                    
                    if($premium_type != 'third_party' && $premium_type != 'third_party_breakin'){
                        $totalAcessoriesIDV = 0;
                        $totalAcessoriesIDV += (($requestData->electrical_acessories_value != '') ? (int)($requestData->electrical_acessories_value) : 0);
                        $totalAcessoriesIDV += (($requestData->nonelectrical_acessories_value != '') ? (int)($requestData->nonelectrical_acessories_value) : 0);
                        $totalAcessoriesIDV += (($requestData->bifuel_kit_value != '') ? (int)($requestData->bifuel_kit_value) : '0');
                        if($totalAcessoriesIDV > ((int)($vehicle_idv) * 30 / 100) )
                        {
                            return [
                                'status' => false,
                                'message' => 'Total of Accessories (Electriccal, Non Electriccal, LPG-CNG KIT) cannot be grater than 30% of the vehicle si',
                                'request' => [
                                    'message' => 'Total of Accessories (Electriccal, Non Electriccal, LPG-CNG KIT) cannot be grater than 30% of the vehicle si',
                                    'totalAcessoriesIDV' => $totalAcessoriesIDV,
                                    'idv_amount' => $vehicle_idv
                                ]
                            ];
                        }
                    }

                    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id', $requestData->user_product_journey_id)
                        ->where('seller_type','P')
                        ->first();

                    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                    {
                        if($pos_data)
                        {
                            $premium_calculation_request['Req_POSP'] = [
                                'VC_EMAILID' => $pos_data->agent_email,
                                'VC_NAME' => $pos_data->agent_name,
                                'VC_unique_CODE' => '',
                                'VC_STATE' => '',
                                'VC_Pan_CARD' => $pos_data->pan_no,
                                'VC_ADHAAR_CARD' => $pos_data->aadhar_no,
                                'NUM_MOBILE_NO' => $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '',
                                "DAT_START_DATE" => '',
                                "DAT_END_DATE" => '',
                                "REGISTRATION_NO" => "",
                                "VC_INTERMEDIARY_CODE" => ''
                            ];
                        }

                        if (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
                        {
                            $premium_calculation_request['Req_POSP'] = [
                                'VC_EMAILID' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_EMAIL_ID'),
                                'VC_NAME' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_NAME'),
                                'VC_UNIQUE_CODE' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_UNIQUE_CODE'),
                                'VC_STATE' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_STATE'),
                                'VC_Pan_CARD' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_PAN_CARD'),
                                'VC_ADHAAR_CARD' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_ADHAAR_CARD'),
                                'NUM_MOBILE_NO' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_MOBILE_NO'),
                                "DAT_START_DATE" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_START_DATE'),
                                "DAT_END_DATE" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_END_DATE'),
                                "REGISTRATION_NO" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_UNIQUE_CODE'),
                                "VC_INTERMEDIARY_CODE" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_INTERMEDIARY_CODE')
                            ];
                        }
                    } 
                    elseif (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
                    {
                        $premium_calculation_request['Req_POSP'] = [
                            'VC_EMAILID' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_EMAIL_ID'),
                            'VC_NAME' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_NAME'),
                            'VC_UNIQUE_CODE' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_UNIQUE_CODE'),
                            'VC_STATE' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_STATE'),
                            'VC_Pan_CARD' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_PAN_CARD'),
                            'VC_ADHAAR_CARD' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_ADHAAR_CARD'),
                            'NUM_MOBILE_NO' => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_MOBILE_NO'),
                            "DAT_START_DATE" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_START_DATE'),
                            "DAT_END_DATE" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_END_DATE'),
                            "REGISTRATION_NO" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_UNIQUE_CODE'),
                            "VC_INTERMEDIARY_CODE" => config('IC.HDFC_ERGO.V1.CV.POSP_TEST_INTERMEDIARY_CODE')
                        ];
                    }

                    $premium_calculation_request['Req_GCV'] = "";
                    $premium_calculation_request['Req_MISD'] = "";
                    $premium_calculation_request['Req_PCV'] = "";
                    $premium_calculation_request['Payment_Details'] ="";
                    $premium_calculation_request['IDV_DETAILS'] = "";

                    if($parent_id == 'GCV' && $no_of_ll_paid > $mmv->seating_capacity){
                        return  [   
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'Sum of the selected value with other related covers should not be greater than seating capacity.',
                            'request' => [
                                'message' => 'Sum of the selected value with other related covers should not be greater than seating capacity.',
                                'seating_capacity' => $mmv->seating_capacity,
                                'product_type' => get_parent_code($productData->product_sub_type_id)
                            ]
                        ];
                    }

                    if ($parent_id == 'PCV') {
                        $premium_calculation_request['Req_PCV'] = [
                            'POSP_CODE' => ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $pos_data) || config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('IC.HDFC_ERGO.V1.CV.POSP_TEST_UNIQUE_CODE') : '',
                            'ExtensionCountryCode' => '0',
                            'ExtensionCountryName' => '',
                            'Effectivedrivinglicense' => $requestData->vehicle_owner_type == 'I' ? false : true,
                            'NumberOfDrivers' => $no_of_ll_paid_drivers,
                            'NumberOfEmployees' => '0',
                            'NoOfCleanerConductorCoolies' =>  $pa_paid_driver_sum_insured > 0 ? 1 : 0,
                            'BiFuelType' => $external_kit_type,
                            'BiFuel_Kit_Value' => $external_kit_value,
                            'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                            'Owner_Driver_Nominee_Name' => NULL,
                            'Owner_Driver_Nominee_Age' => NULL,
                            'Owner_Driver_Nominee_Relationship' => NULL,
                            'Owner_Driver_Appointee_Name' => NULL,
                            'Owner_Driver_Appointee_Relationship' => NULL,
                            'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                            'ElecticalAccessoryIDV' => $electrical_accessories_value,
                            'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                            'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                            'IsPrivateUseLoading' => 0,
                            'IsInclusionofIMT23' => 0,
                            'OtherLoadDiscRate' => 0,
                            'AntiTheftDiscFlag' => false,
                            'HandicapDiscFlag' => false,
                            'Voluntary_Excess_Discount' => $voluntary_excess_value,
                            'UnnamedPersonSI' => $unnamed_passenger_sum_insured,
                            // 'TPPDLimit' => $is_tppd_cover,as per #23856
                            'IsRTI_Cover' => 1,
                            'IsCOC_Cover' => 1,
                            'Bus_Type' => "",
                            'NoOfFPP' => 0,
                            'NoOfNFPP' => 0,
                            'IsCOC_Cover' => 0,
                            'IsTowing_Cover' => 0,
                            'Towing_Limit' => "",
                            'IsEngGearBox_Cover' => 0,
                            'IsNCBProtection_Cover' => 0,
                            'IsRTI_Cover' => 0,
                            'IsEA_Cover' => $isRsa ? 1 : 0,
                            'IsEAW_Cover' => 0
                        ];
                    } 
                    elseif ($parent_id == 'GCV')
                    {
                        $premium_calculation_request['Req_GCV'] = [
                            'POSP_CODE' => ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $pos_data) || config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('IC.HDFC_ERGO.V1.CV.POSP_TEST_UNIQUE_CODE') : '',
                            'ExtensionCountryCode' => '0',
                            'ExtensionCountryName' => '',
                            'Effectivedrivinglicense' => $requestData->vehicle_owner_type == 'I' ? false : true,
                            'NumberOfDrivers' => $no_of_ll_paid > $mmv->seating_capacity ? $mmv->seating_capacity : $no_of_ll_paid,
                            'NumberOfEmployees' => '0',
                            'NoOfCleanerConductorCoolies' => $pa_paid_driver_sum_insured > 0 ? $mmv->seating_capacity : 0,
                            'BiFuelType' => $external_kit_type,
                            'BiFuel_Kit_Value' => $external_kit_value,
                            'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                            'Owner_Driver_Nominee_Name' => NULL,
                            'Owner_Driver_Nominee_Age' => NULL,
                            'Owner_Driver_Nominee_Relationship' => NULL,
                            'Owner_Driver_Appointee_Name' => NULL,
                            'Owner_Driver_Appointee_Relationship' => NULL,
                            'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                            'NoOfTrailers' => 0,
                            'TrailerChassisNo' => "",
                            'TrailerIDV' => 0,
                            'ElecticalAccessoryIDV' => $electrical_accessories_value,
                            'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                            'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                            'IsPrivateUseLoading' => 0,
                            'IsInclusionofIMT23' => 1,
                            'IsOverTurningLoading' => 0,
                            'OtherLoadDiscRate' => 0,
                            'AntiTheftDiscFlag' => false,
                            'HandicapDiscFlag' => false,
                            'PrivateCarrier' => $requestData->gcv_carrier_type == 'PRIVATE' ? true : false,
                            'Voluntary_Excess_Discount' => NULL,
                            // 'TPPDLimit' => $is_tppd_cover,as per #23856
                            'IsRTI_Cover' => 1,
                            'IsCOC_Cover' => 1,
                            'Bus_Type' => "",
                            'NoOfFPP' => 0,
                            'NoOfNFPP' => 0,
                            'IsCOC_Cover' => 0,
                            'IsTowing_Cover' => 0,
                            'Towing_Limit' => "",
                            'IsEngGearBox_Cover' => 0,
                            'IsNCBProtection_Cover' => 0,
                            'IsRTI_Cover' => 0,
                            'IsEA_Cover' => 0,
                            'IsEAW_Cover' => 0
                        ];
                    }
                    elseif ($parent_id == 'MISCELLANEOUS')
                    {
                        $premium_calculation_request['Req_MISD'] = [
                            'POSP_CODE' => [],
                            'ExtensionCountryCode' => '0',
                            'ExtensionCountryName' => '',
                            'Effectivedrivinglicense' => $requestData->vehicle_owner_type == 'I' ? false : true,
                            'NumberOfDrivers' => $no_of_ll_paid_drivers > $mmv->seating_capacity ? $mmv->seating_capacity : $no_of_ll_paid_drivers,
                            'NumberOfEmployees' => '0',
                            'NoOfCleanerConductorCoolies' =>  $pa_paid_driver_sum_insured > 0 ? $mmv->seating_capacity : 0,
                            'BiFuelType' => $external_kit_type,
                            'BiFuel_Kit_Value' => $external_kit_value,
                            'Paiddriver_Si' => $pa_paid_driver_sum_insured,
                            'Owner_Driver_Nominee_Name' => NULL,
                            'Owner_Driver_Nominee_Age' => NULL,
                            'Owner_Driver_Nominee_Relationship' => NULL,
                            'Owner_Driver_Appointee_Name' => NULL,
                            'Owner_Driver_Appointee_Relationship' => NULL,
                            'IsZeroDept_Cover' => $productData->zero_dep == 0 ? 1 : 0,
                            'NoOfTrailers' => 0,
                            'TrailerChassisNo' => "",
                            'TrailerIDV' => 0,
                            'ElecticalAccessoryIDV' => $electrical_accessories_value,
                            'NonElecticalAccessoryIDV' => $non_electrical_accessories_value,
                            'IsLimitedtoOwnPremises' => $is_vehicle_limited_to_own_premises,
                            'IsPrivateUseLoading' => 0,
                            'IsInclusionofIMT23' => 1,
                            'IsOverTurningLoading' => 0,
                            'OtherLoadDiscRate' => 0,
                            'AntiTheftDiscFlag' => false,
                            'HandicapDiscFlag' => false,
                            'Voluntary_Excess_Discount' => NULL,
                            // 'TPPDLimit' => $is_tppd_cover,as per #23856
                            'IsRTI_Cover' => 1,
                            'IsCOC_Cover' => 1,
                            'Bus_Type' => "",
                            'NoOfFPP' => 0,
                            'NoOfNFPP' => 0,
                            'IsCOC_Cover' => 0,
                            'IsTowing_Cover' => 0,
                            'Towing_Limit' => "",
                            'IsEngGearBox_Cover' => 0,
                            'IsNCBProtection_Cover' => 0,
                            'IsRTI_Cover' => 0,
                            'IsEA_Cover' => 0,
                            'IsEAW_Cover' => 0
                        ];
                    }
                    // if(!$skip_second_call) {
                        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CV.CALCULATE_PREMIUM_URL'), $premium_calculation_request, 'hdfc_ergo', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Premium Calculation',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'quote',
                            'requestMethod' => 'post',
                            'product_code' => $product_code,
                            'transaction_id' => $transaction_id,
                            'token' => $token_data['Authentication']['Token'],
                            'headers' => [
                                'Content-type' => 'application/json',
                                'SOURCE' => config('IC.HDFC_ERGO.V1.CV.SOURCE'),
                                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CV.CHANNEL_ID'),
                                'PRODUCT_CODE' => $product_code,
                                'TransactionID' => $transaction_id,
                                'Accept' => 'application/json',
                                'Token' => $token_data['Authentication']['Token']
                            ]
                        ]);
                    // }

                    $premium_data = $get_response['response'];
                    if ($premium_data) {
                        $premium_data = json_decode($premium_data, TRUE);

                        if (isset($premium_data['StatusCode']) && $premium_data['StatusCode'] == 200) {
                            if ($parent_id == 'PCV')
                            {
                                $premium = $premium_data['Resp_PCV'];
                            }
                            else
                            {
                                $premium = $premium_data['Resp_GCV'];
                            }

                            $basic_od = $premium['Basic_OD_Premium'] + ($premium['HighTonnageLoading_Premium'] ?? 0);
                            $tppd = $premium['Basic_TP_Premium'];
                            $pa_owner = (float) $premium['PAOwnerDriver_Premium'];
                            $pa_unnamed = 0;
                            $pa_paid_driver = $premium['PAPaidDriverCleaCondCool_Premium'];
                            $electrical_accessories = $premium['Electical_Acc_Premium'];
                            $non_electrical_accessories = $premium['NonElectical_Acc_Premium'];
                            $zero_dep_amount = $premium['Vehicle_Base_ZD_Premium'];
                            $roadSideAssistance = $premium['EA_premium'] ?? 0;
                            $ncb_discount = $premium['NCBBonusDisc_Premium'];
                            $lpg_cng = $premium['BiFuel_Kit_OD_Premium'];
                            $lpg_cng_tp = isset($premium['BiFuel_Kit_TP_Premium']) && $premium['BiFuel_Kit_TP_Premium'] > 0 ? $premium['BiFuel_Kit_TP_Premium'] : (isset($premium['InBuilt_BiFuel_Kit_Premium']) && $premium['InBuilt_BiFuel_Kit_Premium'] > 0 ? $premium['InBuilt_BiFuel_Kit_Premium'] : 0);
                            $automobile_association = 0;
                            $anti_theft = $parent_id == 'PCV' ? $premium['AntiTheftDisc_Premium'] : 0;
                            $tppd_discount_amt = $premium['TPPD_premium'] ?? 0;
                            $other_addon_amount = 0;
                            $liabilities = 0;
                            $ll_paid_cleaner = $premium['NumberOfDrivers_Premium'];
                            $imt_23 = $parent_id == 'GCV' ? $premium['VB_InclusionofIMT23_Premium'] : 0;
                            $geog_extension_od = $premium['GeogExtension_ODPremium'] ?? 0;
                            $geog_extension_tp = $premium['GeogExtension_TPPremium'] ?? 0;
                            $own_premises_od = $premium['LimitedtoOwnPremises_OD_Premium'] ?? 0;
                            $own_premises_tp = $premium['LimitedtoOwnPremises_TP_Premium'] ?? 0;

                            $idv = $premium['IDV'];

                            $ic_vehicle_discount = 0;
                            $voluntary_excess = 0;
                            $other_discount = 0;

                            if ($electrical_accessories > 0) {
                                $zero_dep_amount += (int)$premium['Elec_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $premium['Elec_InclusionofIMT23_Premium'] : 0;
                            }
    
                            if ($non_electrical_accessories > 0) {
                                $zero_dep_amount += (int)$premium['NonElec_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $premium['NonElec_InclusionofIMT23_Premium'] : 0;
                            }
    
                            if ($lpg_cng > 0) {
                                $zero_dep_amount += (int)$premium['Bifuel_ZD_Premium'];
                                $imt_23 += $parent_id == 'GCV' ? (int) $premium['BiFuel_InclusionofIMT23_Premium'] : 0;
                            }

                            $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng - $own_premises_od;
                            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_cleaner - $own_premises_tp;

                            $ncb_discount = ($final_od_premium - $anti_theft) * ($requestData->applicable_ncb/100);

                            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;

                            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);

                            if ($parent_id == 'GCV') {
                                $final_gst_amount = round(($tppd * 0.12) + (($final_net_premium - $tppd) * 0.18));
                            } else {
                                $final_gst_amount = round($final_net_premium * 0.18);
                            }

                            $final_payable_amount = $final_net_premium + $final_gst_amount;

                            $applicable_addons = ['zeroDepreciation', 'imt23', 'roadSideAssistance'];

                            if ($parent_id == 'PCV') {
                                array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                            }

                            if ($interval->y >= 3)
                            {
                                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                            }

                            $business_types = [
                                'rollover' => 'Rollover',
                                'newbusiness' => 'New Business',
                                'breakin' => 'Break-in'
                            ];

                            $data_response = [
                                'status' => true,
                                'msg' => 'Found',
                                'webservice_id'=> $get_response['webservice_id'],
                                'table'=> $get_response['table'],
                                'Data' => [
                                    'idv' => round($idv),
                                    'min_idv' => $min_idv != "" ? round($min_idv) : 0,
                                    'max_idv' => $max_idv != "" ? round($max_idv) : 0,
                                    'vehicle_idv' => round($idv),
                                    'qdata' => NULL,
                                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                                    'addonCover' => NULL,
                                    'addon_cover_data_get' => '',
                                    'rto_decline' => NULL,
                                    'rto_decline_number' => NULL,
                                    'mmv_decline' => NULL,
                                    'mmv_decline_name' => NULL,
                                    'policy_type' => $tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                                    'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type],
                                    'cover_type' => '1YC',
                                    'hypothecation' => '',
                                    'hypothecation_name' => '',
                                    'vehicle_registration_no' => $requestData->rto_code,
                                    'rto_no' => $requestData->rto_code,
                                    'version_id' => $requestData->version_id,
                                    'selected_addon' => [],
                                    'showroom_price' => 0,
                                    'fuel_type' => $requestData->fuel_type,
                                    'ncb_discount' => (int)$ncb_discount > 0 ? $requestData->applicable_ncb : 0,
                                    'tppd_discount' => $tppd_discount_amt,
                                    'company_name' => $productData->company_name,
                                    'company_logo' => url(config('constants.motorConstant.logos')).'/'.$productData->logo,
                                    'product_name' => $productData->product_sub_type_name,
                                    'mmv_detail' => $mmv,
                                    'master_policy_id' => [
                                        'policy_id' => $productData->policy_id,
                                        'policy_no' => $productData->policy_no,
                                        'policy_start_date' => $policy_start_date,
                                        'policy_end_date' => date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))),
                                        'sum_insured' => $productData->sum_insured,
                                        'corp_client_id' => $productData->corp_client_id,
                                        'product_sub_type_id' => $productData->product_sub_type_id,
                                        'insurance_company_id' => $productData->company_id,
                                        'status' => $productData->status,
                                        'corp_name' => '',
                                        'company_name' => $productData->company_name,
                                        'logo' => env('APP_URL').config('constants.motorConstant.logos').$productData->logo,
                                        'product_sub_type_name' => $productData->product_sub_type_name,
                                        'flat_discount' => $productData->default_discount,
                                        'predefine_series' => '',
                                        'is_premium_online' => $productData->is_premium_online,
                                        'is_proposal_online' => $productData->is_proposal_online,
                                        'is_payment_online' => $productData->is_payment_online
                                    ],
                                    'motor_manf_date' => '01-'.$requestData->manufacture_year,
                                    'vehicle_register_date' => $requestData->vehicle_register_date,
                                    'vehicleDiscountValues' => [
                                        'master_policy_id' => $productData->policy_id,
                                        'product_sub_type_id' => $productData->product_sub_type_id,
                                        'segment_id' => 0,
                                        'rto_cluster_id' => 0,
                                        'car_age' => $car_age,
                                        'aai_discount' => 0,
                                        'ic_vehicle_discount' => 0//round($insurer_discount)
                                    ],
                                    'basic_premium' => $basic_od,
                                    'motor_electric_accessories_value' => $electrical_accessories,
                                    'motor_non_electric_accessories_value' => $non_electrical_accessories,
                                    'motor_lpg_cng_kit_value' => $lpg_cng,
                                    'total_accessories_amount(net_od_premium)' => $electrical_accessories + $non_electrical_accessories + $lpg_cng,
                                    'total_own_damage' => $final_od_premium,
                                    'tppd_premium_amount' => $tppd,
                                    'compulsory_pa_own_driver' => $pa_owner,  // Not added in Total TP Premium
                                    'cpa_allowed' => !empty($pa_owner),
                                    'GeogExtension_ODPremium' => round($geog_extension_od),
                                    'GeogExtension_TPPremium' => round($geog_extension_tp),
                                    'LimitedtoOwnPremises_OD' => round($own_premises_od),
                                    'LimitedtoOwnPremises_TP' => round($own_premises_tp),
                                    'cover_unnamed_passenger_value' => $pa_unnamed,
                                    'default_paid_driver' => $liabilities + $ll_paid_cleaner,
                                    'motor_additional_paid_driver' => $pa_paid_driver,
                                    'cng_lpg_tp' => $lpg_cng_tp,
                                    'seating_capacity' => $mmv->seating_capacity,
                                    'deduction_of_ncb' => $ncb_discount,
                                    'antitheft_discount' => $anti_theft,
                                    'aai_discount' => $automobile_association,
                                    'voluntary_excess' => $voluntary_excess,
                                    'other_discount' => $other_discount,
                                    'ic_vehicle_discount' => $other_discount,
                                    'total_liability_premium' => $final_tp_premium,
                                    'net_premium' => $final_net_premium,
                                    'service_tax_amount' => $final_gst_amount,
                                    'service_tax' => 18,
                                    'total_discount_od' => 0,
                                    'add_on_premium_total' => 0,
                                    'addon_premium' => 0,
                                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                    'quotation_no' => '',
                                    'premium_amount' => $final_payable_amount,
                                    'service_data_responseerr_msg' => 'success',
                                    'user_id' => $requestData->user_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'user_product_journey_id' => $requestData->user_product_journey_id,
                                    'service_err_code' => NULL,
                                    'service_err_msg' => NULL,
                                    'policyStartDate' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($policy_start_date)),
                                    'policyEndDate' => date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date))),
                                    'ic_of' => $productData->company_id,
                                    'vehicle_in_90_days' => 'N',
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
                                    'max_addons_selection' => NULL,
                                    'add_ons_data' => [
                                        'in_built' => [
                                            'zero_depreciation' => (int)$zero_dep_amount
                                        ],
                                        'additional' => [
                                            'road_side_assistance' => $roadSideAssistance,
                                            'imt23' => $imt_23
                                        ]
                                    ],
                                    'final_od_premium' => $final_od_premium,
                                    'final_tp_premium' => $final_tp_premium,
                                    'final_total_discount' => $final_total_discount,
                                    'final_net_premium' => $final_net_premium,
                                    'final_gst_amount' => round($final_gst_amount),
                                    'final_payable_amount' => round($final_payable_amount),
                                    'applicable_addons' => $applicable_addons,
                                    'mmv_detail' => [
                                        'manf_name' => $mmv->manufacturer,
                                        'model_name' => $mmv->vehicle_model,
                                        'version_name' => $mmv->txt_variant,
                                        'fuel_type' => $mmv->txt_fuel,
                                        'seating_capacity' => $mmv->seating_capacity,
                                        'carrying_capacity' => $mmv->carrying_capacity,
                                        'cubic_capacity' => $mmv->cubic_capacity,
                                        'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
                                        'vehicle_type' => '',
                                    ]
                                ]
                            ];

                            if ((int) $zero_dep_amount == 0)
                            {
                                unset($data_response['Data']['add_ons_data']['in_built']['zero_depreciation']);
                            }

                            if ($own_premises_od == 0) {
                                unset($data_response['Data']['LimitedtoOwnPremises_OD']);
                            }

                            if ($own_premises_tp == 0) {
                                unset($data_response['Data']['LimitedtoOwnPremises_TP']);
                            }

                            if ($lpg_cng == 0) {
                                unset($data_response['Data']['motor_lpg_cng_kit_value']);
                            }

                            if ($lpg_cng_tp == 0) {
                                unset($data_response['Data']['cng_lpg_tp']);
                            }

                            if (in_array($premium_type, [
                                'short_term_3',
                                'short_term_6',
                                'short_term_3_breakin',
                                'short_term_6_breakin'
                            ])) {
                                $data_response['Data']['premium_type_code'] = $premium_type;
                            }

                            return camelCase($data_response);
                        } else {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => isset($premium_data['Error']) ? $premium_data['Error'] : 'Service Error'
                            ];
                        }
                    } else {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Something went wrong while calculating premium'
                        ];
                    }
                } else {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        
                        'message' => isset($idv_data['Error']) ? $idv_data['Error'] : 'Service Error'
                    ];
                }
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message' => 'Something went wrong while calculating IDV'
                ];
            }
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => isset($token_data['Error']) ? $token_data['Error'] : 'Service Error'
            ];
        }
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Something went wrong while generating token'
        ];
    }
}
}