<?php
include_once app_path().'/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/godigit_Miscd.php';

use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\MasterPolicy;

function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];              
    // owenership_change case is already handled in GODIGIT,  inspection required for owenership_change case
    $is_MISC = policyProductType($productData->policy_id)->parent_id;
    
    if($is_MISC == 3){
        return getQuoteMisd($enquiryId, $requestData, $productData);
    }

    $premium_type_array = DB::table('master_premium_type')
    ->where('id', $productData->premium_type_id)
        ->select('premium_type_code', 'premium_type')
        ->first();
    $isInspectionApplicable = 'N';
    if (($requestData->ownership_changed ?? '') == 'Y') 
    {
        if (!in_array($premium_type_array->premium_type_code, ['third_party', 'third_party_breakin'])) 
        {
            $isInspectionApplicable = 'Y';
            $premium_type_id = null;
            if (in_array($productData->premium_type_id, [1, 4])) {
                $premium_type_id = 4;
            } else if (in_array($productData->premium_type_id, [3, 6])) {
                $premium_type_id = 6;
            } else if (in_array($productData->premium_type_id, [5, 9])) {
                $premium_type_id = 9;
            } else if (in_array($productData->premium_type_id, [8, 10])) {
                $premium_type_id = 10;
            }
            $MasterPolicy = MasterPolicy::where('product_sub_type_id', $productData->product_sub_type_id)
            ->where('insurance_company_id', 36)
            ->where('premium_type_id', $premium_type_id)
                ->where('status', 'Active')
                ->get()
                ->first();
            if ($MasterPolicy == false) {
                return [
                    'premium_amount'    => 0,
                    'status'            => false,
                    'message'           => 'Breakin Product is Required Enable For OwnershipChange Inspection',
                    'request' => [
                        'message'       => 'Breakin Product is Required Enable For OwnershipChange Inspection',
                        'requestData'   => $requestData
                    ]
                ];
            }
        }
    }


    if(empty($requestData->rto_code))
    {
        return  [  'premium_amount' => 0,
                    'status' => false,
                    'message' => 'RTO not available',
                    'request' => [
                        'message' => 'RTO not available',
                        'rto_code' => $requestData->rto_code
                    ]
                ]; 
    }

    // if (get_parent_code($productData->product_sub_type_id) == 'PCV' && $productData->product_sub_type_code == 'AUTO-RICKSHAW') {
    //     $mmv = DB::table('ic_version_mapping AS icvm')
    //         ->leftJoin('cv_godigit_model_master AS cgmm', 'icvm.ic_version_code', '=', 'cgmm.vehicle_code')
    //         ->where('icvm.fyn_version_id', $requestData->version_id)
    //         ->where('icvm.ic_id', $productData->company_id)
    //         ->first();
    // } else {
        if (get_parent_code($productData->product_sub_type_id) == 'MISCELLANEOUS-CLASS')
        {
            $mmv = [
                'id' => '1234',
                'vehicle_code' => '1600100109',
                'make' => 'ACE',
                'model' => 'AF 30D FORK LIFT',
                'variant' => 'FORKLIFT',
                'body_type' => 'FORKLIFT',
                'seating_capacity' => '1',
                'power' => '56.6',
                'cubic_capacity' => '1',
                'gross_vehicle_weight' => '4410',
                'fuel_type' => 'Diesel',
                'no_of_wheels' => '4',
                'abs' => 'N',
                'air_bags' => '0',
                'length' => '0',
                'ex_showroom_price' => '1018625',
                'price_year' => '2017',
                'production' => 'Under Production',
                'manufacturing' => 'IND',
                'vehicle_type' => 'Miscellaneous',
                'created_at' => '0000-00-00 00:00:00',
                'updated_at' => '0000-00-00 00:00:00',
                'ic_version_code' => '1600100109'
            ];
        }
        else
        {
            $mmv = get_mmv_details($productData,$requestData->version_id,'godigit');

            if (isset($mmv['status']) && $mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [   
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message'] ?? 'Something went wrong',
                    'request' => [
                        'mmv' => $mmv
                    ],
                ];          
            }
        }

        $mmv = (object) array_change_key_case((array) $mmv,CASE_LOWER);
    // }

    if(empty($mmv->ic_version_code) || $mmv->ic_version_code == '')
    {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];        
    }
    else if($mmv->ic_version_code == 'DNE')
    {
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

    if ($requestData->gcv_carrier_type == 'PRIVATE' && get_parent_code($productData->product_sub_type_id) == 'GCV') {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Private Carrier is not allowed',
            'request' => [
                'message' => 'Private Carrier is not allowed',
                'carrier_type' => $requestData->gcv_carrier_type,
                'product_type' => get_parent_code($productData->product_sub_type_id)
            ]
        ]; 
    }

    if(get_parent_code($productData->product_sub_type_id) == 'PCV' && $mmv->seating_capacity > 5)
    {
        return  [   
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Policy issuance for vehicles having seating capacity greater than 5 is not allowed in PCV',
            'request' => [
                'message' => 'Policy issuance for vehicles having seating capacity greater than 5 is not allowed in PCV',
                'seating_capacity' => $mmv->seating_capacity,
                'product_type' => get_parent_code($productData->product_sub_type_id)
            ]
        ];
    }

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = floor($age / 12);

    $mmv_data = 
    [   'manf_name' => $mmv->make,
        'model_name' => $mmv->model,
        'version_name' => !empty($mmv->variant) ? $mmv->variant : '-',
        'seating_capacity' => $mmv->seating_capacity,
        'carrying_capacity' => $mmv->seating_capacity - 1,
        'cubic_capacity' => $mmv->cubic_capacity,
        'fuel_type' =>  $mmv->fuel_type,
        'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
        'vehicle_type' => ($mmv->vehicle_type == 'Passenger Carrying') ? 'PCV' : 'GCV',
        'version_id' => $mmv->ic_version_code,
    ];
    $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();

    $is_satp = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin');

    if($premium_type == 'third_party' || $premium_type == 'third_party_breakin') 
    {
        $insurance_product_code = '20302';
        $previousNoClaimBonus = 'ZERO';
    }
    else
    {
        $insurance_product_code = '20301';
        $ncb_percent = str_replace("%", "", $requestData->previous_ncb);
        $no_claim_bonus = [
                                '0'  => 'ZERO',
                                '20' => 'TWENTY',
                                '25' => 'TWENTY_FIVE',
                                '35' => 'THIRTY_FIVE',
                                '45' => 'FORTY_FIVE',
                                '50' => 'FIFTY',
                                '55' => 'FIFTY_FIVE',
                                '65' => 'SIXTY_FIVE',
                        ];
        $previousNoClaimBonus = $no_claim_bonus[$ncb_percent] ?? 'ZERO';
    }

    $voluntary_deductible = [
        '0' => 'ZERO',
        '2500' => 'TWENTYFIVE_HUNDRED',
        '5000' => 'FIVE_THOUSAND',
        '7500' => 'SEVENTYFIVE_HUNDRED',
        '15000' => 'FIFTEEN_THOUSAND'
    ];

    $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
    $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
    $motor_manf_year = $motor_manf_year_arr[1];
    $motor_manf_date = '01-'.$requestData->manufacture_year;
    $current_date = Carbon::now()->format('Y-m-d');

    if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') 
    {
        $is_vehicle_new = 'false';
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $sub_insurance_product_code = 'PB';
        $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
        if($requestData->business_type == 'breakin')
        {
            $breakin_make_time = strtotime('18:00:00');
            if($breakin_make_time > time())
            {
               $policy_start_date = date('Y-m-d', strtotime('+1 day', time())); 
            }
            else
            {
              $policy_start_date = date('Y-m-d', strtotime('+2 day', time())); 
            } 
        }
    }
    else if ($requestData->business_type == 'newbusiness') 
    {
        $requestData->vehicle_register_date = date('d-m-Y');
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        if ($date_difference > 0)
        {  
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
        $sub_insurance_product_code = 'PB';
        $previousNoClaimBonus = 'ZERO';
        if($requestData->vehicle_registration_no == 'NEW')
        {
            $vehicle_registration_no  = str_replace("-", "", godigitRtoCode($requestData->rto_code)) . "-NEW";
        }
        else
        {
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
        }
    }

    if ($premium_type == 'short_term_3' || $premium_type == 'short_term_3_breakin') {
        $sub_insurance_product_code = 'ST';
        $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));
    } elseif ($premium_type == 'short_term_6' || $premium_type == 'short_term_6_breakin') {
        $sub_insurance_product_code = 'ST';
        $policy_end_date = date('Y-m-d', strtotime('+6 month -2 day', strtotime($policy_start_date)));
    } else {
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }
    
    if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
        $vehicle_in_90_days = $date_difference = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);

        if ($date_difference > 90 || $requestData->previous_policy_expiry_date == 'New') {  
            $previousNoClaimBonus = 'ZERO';
        }
    }

    if($requestData->is_claim == 'Y')
    {
    //    $previousNoClaimBonus = 'ZERO';
    //    $previousNoClaimBonus = $no_claim_bonus[$ncb_percent] ?? 'ZERO';
    }

    $voluntary_deductible_amount = 'ZERO';

    $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;
    $is_tppd = false;

    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                                    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                    ->first();

    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $data) {
            if ($data['name'] == 'TPPD Cover') {
                $is_tppd = true;
            }
        }
    }

    if(!empty($additional['accessories']))
    {
        foreach ($additional['accessories'] as $key => $data) 
        {
            if($data['name'] == 'External Bi-Fuel Kit CNG/LPG')
            {
                $cng_lpg_amt = $data['sumInsured'];
            }

            if($data['name'] == 'Non-Electrical Accessories')
            {
                $non_electrical_amt = $data['sumInsured'];
            }

            if($data['name'] == 'Electrical Accessories')
            {
                $electrical_amt = $data['sumInsured'];
            }
        }
    }
    
    if(isset($cng_lpg_amt) && ($cng_lpg_amt < 15000 || $cng_lpg_amt > 80000)) // min amount changed as per #23730
    {
        return  [   'premium_amount' => 0,
                    'status' => false,
                    'message' => 'CNG/LPG Insured amount, min = Rs.15000 and max = Rs.80000',
                    'request' => [
                        'message' => 'CNG/LPG Insured amount, min = Rs.15000 and max = Rs.80000',
                        'cng_lpg_amt' => $cng_lpg_amt
                    ]
                ];
    }

    if(isset($non_electrical_amt) && ($non_electrical_amt < 412 || $non_electrical_amt > 82423))
    {
        return  [   'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Non-Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                    'request' => [
                        'message' => 'Non-Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                        'non_electrical_amt' => $non_electrical_amt
                    ]
                ];
    }

    if(isset($electrical_amt) && ($electrical_amt < 412 || $electrical_amt > 82423))
    {
        return  [   'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                    'request' => [
                        'message' => 'Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                        'electrical_amt' => $electrical_amt
                    ]
                ];
    }

    $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
    $driverLL = false;
    $cleanerLL = false;
    $no_of_cleanerLL = NULL;
    $no_of_driverLL = 1;

    if(!empty($additional['additional_covers']))
    {
        foreach ($additional['additional_covers'] as $key => $data) 
        {
            if($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']))
            {
                $cover_pa_paid_driver = $data['sumInsured'];
            }

            if($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']))
            {
                $cover_pa_unnamed_passenger = $data['sumInsured'];
            }

            if ($data['name'] == 'LL paid driver') {
                $driverLL = true;
            }

            if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberCleaner']) && $data['LLNumberCleaner'] > 0) {
                $cleanerLL = true;
                $no_of_cleanerLL = $data['LLNumberCleaner'];
            }

            if ($data['name'] == 'LL paid driver/conductor/cleaner' && isset($data['LLNumberDriver']) && $data['LLNumberDriver'] > 0) {
                $driverLL = true;
                $no_of_driverLL = $data['LLNumberDriver'];
            }

            if ($data['name'] == 'PA paid driver/conductor/cleaner' && isset($data['sumInsured'])) {
                $cover_pa_paid_driver = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = $data['sumInsured'];
            }
        }
    }
    //commenting 
    // if(!empty($cover_pa_paid_driver)){
    //     if(!(($cover_pa_paid_driver== 100000) || ($cover_pa_paid_driver == 200000))){
    //         return[
    //         'status' => false,
    //         'message' => 'PA cover for additional paid driver only allowed for 100000 or 200000'
    //         ];
    //     }
    // }
    
    // if(!empty($cover_pa_unnamed_passenger)){
    //     if(!(($cover_pa_unnamed_passenger== 100000) || ($cover_pa_unnamed_passenger == 200000))){
    //         return[
    //         'status' => false,
    //         'message' => 'PA cover for additional paid driver only allowed for 100000 or 200000'
    //         ];
    //     }
    // }
    
    $is_pos = 'false';
    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    $is_pos_testing_mode = config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_GODIGIT');
    $posp_name = '';
    $posp_unique_number = '';
    $posp_pan_number = '';
    $posp_aadhar_number = '';
    $posp_contact_number = '';
    $posp_location = '';



    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
    $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');

    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if($pos_data) {

            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $pos_data->agent_id,
                'productSubTypeId' => $productData->product_sub_type_id,
                'ic_integration_type' => 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $webUserId = $credentials['data']['web_user_id'];
                $password = $credentials['data']['password'];
            }

            $is_pos = 'true';
            $posp_name = $pos_data->agent_name;
            $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
            $posp_pan_number = $pos_data->pan_no;
            $posp_aadhar_number = $pos_data->aadhar_no;
            $posp_contact_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
            $posp_location = $pos_data->region_name;
        }
        if($is_pos_testing_mode == 'Y')
        {
            $is_pos = 'true';
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = 'ABGTY8890Z';
            $posp_aadhar_number = '569278616999';
            $posp_contact_number = '9768574564';
        }
    }
    else if($is_pos_testing_mode == 'Y')
    {
        $is_pos = 'true';
        $posp_name = 'test';
        $posp_unique_number = '9768574564';
        $posp_pan_number = 'ABGTY8890Z';
        $posp_aadhar_number = '569278616999';
        $posp_contact_number = '9768574564';
    }

    $isPartsDepreciation = ($productData->zero_dep == 0);
    $isConsumables = true;

    // if($is_satp || $interval->y > 2 || ($interval->y == 2 && $interval->m > 10) || ($interval->y == 2 && $interval->m == 10 && $interval->d > 28))
    // {
    //     if($productData->zero_dep == 0)
    //     {
    //         return [
    //             'premium_amount' => 0,
    //             'status' => false,
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 2 years 10 months and 28 days',
    //             'request' => [
    //                 'message' => 'Zero dep is not allowed for vehicle age greater than 2 years 10 months and 28 days',
    //                 'interval' => $interval
    //             ]
    //         ];
    //     }
    //     $isPartsDepreciation = false;
    //     $isConsumables = false;
    // }

    if($vehicle_registration_no == 'NEW')
    {
        $vehicle_registration_no = str_replace('-', '', godigitRtoCode($requestData->rto_code));
    }

    $premium_req_array = 
    [   'enquiryId' => 'GODIGIT_QQ_CV_PACKAGE_01',
        'contract' =>
        [   'insuranceProductCode' => $insurance_product_code,
            'subInsuranceProductCode' => $sub_insurance_product_code,
            'startDate' => $policy_start_date,
            'endDate' => $policy_end_date,
            'policyHolderType' => $policy_holder_type,
            'externalPolicyNumber' => NULL,
            'isNCBTransfer' => NULL,
            'coverages' =>
                [
                'voluntaryDeductible' => $voluntary_deductible_amount,
                'thirdPartyLiability' =>
                    [
                    'isTPPD' => $is_tppd,
                ],
                'ownDamage' =>
                    [
                    'discount' =>
                        [
                        'userSpecialDiscountPercent' => 0,
                        'discounts' =>
                        [
                        ],
                    ],
                    'surcharge' =>
                        [
                        'loadings' =>
                        [
                        ],
                    ],
                ],
                'personalAccident' =>
                    [
                    'selection' => $requestData->vehicle_owner_type == "I" ? "true" : "false",
                    'insuredAmount' => 1500000,
                    'coverTerm' => null,
                ],
                'accessories' =>
                    [
                    'cng' =>
                        [
                        'selection' => !empty($cng_lpg_amt) ? 'true' : 'false',
                        'insuredAmount' => !empty($cng_lpg_amt) ? $cng_lpg_amt : 0,
                    ],
                    'electrical' =>
                        [
                        'selection' => !empty($electrical_amt) ? 'true' : 'false',
                        'insuredAmount' => !empty($electrical_amt) ? $electrical_amt : 0,
                    ],
                    'nonElectrical' =>
                        [
                        'selection' => !empty($non_electrical_amt) ? 'true' : 'false',
                        'insuredAmount' => !empty($non_electrical_amt) ? $non_electrical_amt : 0,
                    ],
                ],
                'addons' =>
                    [
                    'partsDepreciation' =>
                        [
                        'claimsCovered' => ($isPartsDepreciation ? "TWO" : NULL),
                        'selection' => ($isPartsDepreciation ? "true" : "false"),
                    ],
                    'roadSideAssistance' =>
                        [
                        'selection' => "false",
                    ],
                    'personalBelonging' =>
                        [
                        'selection' => "false",
                    ],
                    'keyAndLockProtect' =>
                        [
                        'selection' => "false",
                    ],
                    'engineProtection' =>
                        [
                        'selection' => "false",
                    ],
                    'tyreProtection' =>
                        [
                        'selection' => "false",
                    ],
                    'rimProtection' =>
                        [
                        'selection' => "false",
                    ],
                    'returnToInvoice' =>
                        [
                        'selection' => "false",
                    ],
                    'consumables' =>
                        [
                        'selection' => ($isConsumables ? "true" : "false"),
                    ],
                ],
                'legalLiability' =>
                    [
                    'paidDriverLL' =>
                        [
                        'selection' => $driverLL,
                        'insuredCount' => $no_of_driverLL,
                    ],
                    'employeesLL' =>
                        [
                        'selection' =>"false",
                        'insuredCount' => NULL,
                    ],
                    'unnamedPaxLL' =>
                        [
                        'selection' => "false",
                        'insuredCount' => NULL,
                    ],
                    'cleanersLL' =>
                        [
                        'selection' => $cleanerLL ? "true" : "false",
                        'insuredCount' => $no_of_cleanerLL,
                    ],
                    'nonFarePaxLL' =>
                        [
                        'selection' => "false",
                        'insuredCount' => NULL,
                    ],
                    'workersCompensationLL' =>
                        [
                        'selection' => "false",
                        'insuredCount' => NULL,
                    ],
                ],
                'unnamedPA' =>
                [
                    'unnamedPax' =>
                    [
                        'selection' => !empty($cover_pa_unnamed_passenger) ? 'true' : 'false',
                        'insuredAmount' => !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0,
                        'insuredCount' => $mmv->seating_capacity,
                    ],
                    'unnamedPaidDriver' =>
                        [
                        'selection' => !empty($cover_pa_paid_driver) ? 'true' : 'false',
                        'insuredAmount' => !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0,
                        'insuredCount' => NULL,
                    ],
                    'unnamedHirer' =>
                        [
                        'selection' => "false",
                        'insuredAmount' => NULL,
                        'insuredCount' => NULL,
                    ],
                    'unnamedPillionRider' =>
                        [
                        'selection' => "false",
                        'insuredAmount' => NULL,
                        'insuredCount' => NULL,
                    ],
                    'unnamedCleaner' =>
                        [
                        'selection' => !empty($cover_pa_paid_cleaner) ? 'true' : 'false',
                        'insuredAmount' => !empty($cover_pa_paid_cleaner) ? $cover_pa_paid_cleaner : 0,
                        'insuredCount' => NULL,
                    ],
                    'unnamedConductor' =>
                        [
                        'selection' => !empty($cover_pa_paid_conductor) ? 'true' : 'false',
                        'insuredAmount' => !empty($cover_pa_paid_conductor) ? $cover_pa_paid_conductor : 0,
                        'insuredCount' => NULL,
                    ],
                ],
            ],
        ],
        'vehicle' =>
        [   'isVehicleNew' => $is_vehicle_new ,
            'vehicleMaincode' => $mmv->vehicle_code,
            'licensePlateNumber' => $vehicle_registration_no != "" ? $vehicle_registration_no : str_replace('-', '', godigitRtoCode($requestData->rto_code)),
            'vehicleIdentificationNumber' => NULL,
            'engineNumber' => NULL,
            'manufactureDate' => date('Y-m-d', strtotime($motor_manf_date)),
            'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
            'vehicleIDV' => [
                'idv' =>  NULL,
            ],
            'usageType' => "KFZOT",
            'permitType' => 'PUBLIC',
            'motorType' => NULL,
            'vehicleType' => $mmv->vehicle_type == 'Passenger Carrying' ? 'PASSENGER' : ($mmv->vehicle_type == 'Miscellaneous' ? 'MISC' : 'GOODS') //Misc. vehicle: 1600100109 - ACE - AF 30D FORK LIFT - FORKLIFT
        ],
        'previousInsurer' =>
        [
            'isPreviousInsurerKnown' => ($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New' ? 'false' : 'true',
            'previousInsurerCode' => $requestData->previous_insurer_code == 'godigit' ? 158 : '102',
            'previousPolicyNumber' => null,
            'previousPolicyExpiryDate' => (($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || $requestData->ownership_changed == 'Y' || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d', strtotime('-91 days', time())) : (!empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null),
            'isClaimInLastYear' => ($requestData->is_claim == 'Y') ? 'true' : 'false',
            'originalPreviousPolicyType' => ($requestData->prev_short_term ? 'SHORTERM' : ($requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP')),
            'previousPolicyType' => ($requestData->prev_short_term ? '0OD_1TP' : ($requestData->previous_policy_type == 'Third-party' ? '0OD_1TP' : '1OD_1TP')),
            'previousNoClaimBonus' => $previousNoClaimBonus,
            'currentThirdPartyPolicy' => NULL,
        ],
        'pospInfo' => [
            'isPOSP' => $is_pos,
            'pospName' => $posp_name,
            'pospUniqueNumber' => $posp_unique_number,
            'pospLocation' => $posp_location,
            'pospPanNumber' => $posp_pan_number,
            'pospAadhaarNumber' => $posp_aadhar_number,
            'pospContactNumber' => $posp_contact_number
        ],
        'pincode' => '421201',
    ];

    if ($mmv->vehicle_type != 'Passenger Carrying') {
        $premium_req_array['contract']['coverages']['isIMT23'] = $productData->product_identifier == 'IMT23' ? 'true' : 'false';
    }

    $checksum_data = checksum_encrypt($premium_req_array);
    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'godigit',$checksum_data,'CV');
    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
        $get_response = $is_data_exist_for_checksum;
    }else{
        $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_QUICK_QUOTE_PREMIUM'),$premium_req_array, 'godigit',
        [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'godigit',
            'section' => $productData->product_sub_type_code,
            'checksum' => $checksum_data,
            'method' =>'Premium Calculation' . ($productData->product_identifier == 'IMT23' ? ' - IMT-23' : ''),
            'webUserId' => $webUserId,
            'password' => $password,
            'transaction_type' => 'quote',
        ]);
    }
    $data = $get_response['response'];
    if (!empty($data)) 
    {
        if (is_string($get_response['response']) && str_contains($get_response['response'], '503 Service Temporarily Unavailable'))
        {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => '503 Service Temporarily Unavailable'
            ];
        }
        $response = json_decode($data);
        $skip_second_call = false;
        if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
        {
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Premium Calculation Success", "Success" );
            $vehicle_idv = round($response->vehicle->vehicleIDV->idv);
            $min_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $response->vehicle->vehicleIDV->minimumIdv;
            $max_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : $response->vehicle->vehicleIDV->maximumIdv;
            $default_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : round($response->vehicle->vehicleIDV->defaultIdv);

            if ($requestData->is_idv_changed == 'Y')
            {                       	
                if ($requestData->edit_idv >= $max_idv)
                {
                    $premium_req_array['vehicle']['vehicleIDV']['idv'] = $max_idv;
                }
                elseif ($requestData->edit_idv <= $min_idv)
                {
                    $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                }
                else
                {
                    $premium_req_array['vehicle']['vehicleIDV']['idv'] = $requestData->edit_idv;
                }
            } else {

                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $vehicle_idv;
                        $skip_second_call = true;
                        $vehicle_idv =  $vehicle_idv;
                        break;
                    case 'min_idv':
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                        $vehicle_idv =  $min_idv;
                        break;
                    case 'max_idv':
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $max_idv;
                        $vehicle_idv =  $max_idv;
                        break;
                    default:
                        $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                        $vehicle_idv =  $min_idv;
                        break;
                }
                /* $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv; */
            }
            
            if(!$skip_second_call) {
                $checksum_data = checksum_encrypt($premium_req_array);
                $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'godigit',$checksum_data,'CV');
                if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                    $get_response = $is_data_exist_for_checksum;
                }else{
                    $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_QUICK_QUOTE_PREMIUM'),$premium_req_array, 'godigit',
                    [
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'godigit',
                        'section' => $productData->product_sub_type_code,
                        'checksum' => $checksum_data,
                        'method' =>'Premium Recalculation' . ($productData->product_identifier == 'IMT23' ? ' - IMT-23' : ''),
                        'webUserId' => $webUserId,
                        'password' => $password,
                        'transaction_type' => 'quote',
                    ]);
                }
            }

            $data = $get_response['response'];
            if (!empty($data)) 
            {
                $response = json_decode($data);
                if ($response->error->errorCode == '0') 
                {
                    $vehicle_idv = round($response->vehicle->vehicleIDV->idv);
                    $default_idv = $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 0 : round($response->vehicle->vehicleIDV->defaultIdv);
                }
                elseif(!empty($response->error->validationMessages[0]))
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => str_replace(",","",$response->error->validationMessages[0])
                    ];
                } 
                elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => str_replace(",","",$response->error->validationMessages[0])
                    ];
                }  
            }
            else 
            {
                return
                [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Insurer not reachable'
                ];
            }

            $contract = $response->contract;
            $llpaiddriver_premium = 0;
            $llcleaner_premium = 0;
            $cover_pa_owner_driver_premium = 0;
            $cover_pa_paid_driver_premium = 0;
            $cover_pa_unnamed_passenger_premium = 0;
            $cover_pa_paid_cleaner_premium = 0;
            $cover_pa_paid_conductor_premium = 0;
            $voluntary_excess = 0;
            $ic_vehicle_discount = 0;
            $ncb_discount_amt = 0;
            $tppd_discount_amt = $is_tppd ? (get_parent_code($productData->product_sub_type_id) == 'PCV' ? 150 : 200) : 0;

            $od = 0;
            $cng_lpg_tp = 0;
            $ncb_discount_percentage = 0;
            
            $zero_depreciation = 0;
            $consumables = 0;
            $road_side_assistance = 0;

            $own_premises_od = 0;
            $own_premises_tp = 0;

            foreach ($contract->coverages as $key => $value) 
            {
                switch ($key) 
                {
                    case 'thirdPartyLiability':
                        if (isset($value->netPremium)) 
                        {
                            $tppd = round(str_replace("INR ", "", $value->netPremium));
                        }
                        
                    break;
        
                    case 'addons':
                        foreach ($value as $key => $addon) {
                            switch ($key) {
                                case 'partsDepreciation':
                                    if ($addon->selection == 'true' && isset($addon->coverAvailability) && $addon->coverAvailability == 'AVAILABLE') {
                                        $zero_depreciation = round(str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                case 'consumables':
                                    if ($addon->selection == 'true' && isset($addon->coverAvailability) && $addon->coverAvailability == 'AVAILABLE') {
                                        $consumables = round(str_replace('INR ', '', $addon->netPremium));
                                    }
                                    break;

                                }
                            }
                    break;

                    case 'ownDamage':
                       
                       if(isset($value->netPremium))
                       {
                            $od = round(str_replace("INR ", "", $value->netPremium));
                            foreach ($value->discount->discounts as $key => $type) 
                            {
                                if ($type->discountType == "NCB_DISCOUNT") 
                                {
                                    $ncb_discount_percentage = $type->discountPercent;
                                    $ncb_discount_amt = round(str_replace("INR ", "", $type->discountAmount));
                                }
                            }
                       } 
                    break;

                    case 'legalLiability' :
                        foreach ($value as $cover => $subcover) 
                        {
                            if ($cover == "paidDriverLL") 
                            {
                                if($subcover->selection == 1)
                                {  //Not a cover : default 
                                    $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                }
                            }

                            if ($cover == "cleanersLL") {
                                if ($subcover->selection == 1) {
                                    $llcleaner_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                }
                            }
                        }
                    break;
                
                    case 'personalAccident':
                        // By default Complusory PA Cover for Owner Driver
                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                        {
                            $cover_pa_owner_driver_premium = round(str_replace("INR ", "", $value->netPremium));
                        } 
                    break;

                    case 'accessories' :    
                    break;

                    case 'unnamedPA':
                        
                        foreach ($value as $cover => $subcover) 
                        {
                            if ($cover == 'unnamedPaidDriver') 
                            {
                                if (isset($subcover->selection) && $subcover->selection == 1) 
                                {
                                    if (isset($subcover->netPremium)) 
                                    {
                                        $cover_pa_paid_driver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }

                            if ($cover == 'unnamedPax') 
                            {
                                if (isset($subcover->selection) && $subcover->selection == 1) 
                                {
                                    if (isset($subcover->netPremium)) 
                                    {
                                        $cover_pa_unnamed_passenger_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }
                            
                            if ($cover == 'unnamedCleaner') {
                                if (isset($subcover->selection) && $subcover->selection == 1) {
                                    if (isset($subcover->netPremium)) {
                                        $cover_pa_paid_cleaner_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }

                            if ($cover == 'unnamedConductor') {
                                if (isset($subcover->selection) && $subcover->selection == 1) {
                                    if (isset($subcover->netPremium)) {
                                        $cover_pa_paid_conductor_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }
                        }
                    break;
                }
            }
       
            if ((isset($cng_lpg_amt) && !empty($cng_lpg_amt)) || in_array($mmv->fuel_type, ['CNG', 'PETROL+CNG', 'DIESEL+CNG', 'LPG'])) {
                $cng_lpg_tp = 60;
                $tppd = $tppd - 60;
            }

            $imt23 = $mmv->vehicle_type == 'Passenger Carrying' || $productData->product_identifier != 'IMT23' ? 0 : round($od - ($od * (100/115)));
            $final_od_premium = $od - $imt23;
            $ncb_discount = $mmv->vehicle_type == 'Passenger Carrying' ? $ncb_discount_amt : $final_od_premium * ($ncb_discount_percentage/100);
            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium + $tppd_discount_amt;
            $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $tppd_discount_amt;
            $final_net_premium   = round($final_od_premium + $final_tp_premium - $final_total_discount);

            if ($mmv->vehicle_type == 'Passenger Carrying') {
                $final_gst_amount   = round($final_net_premium * 0.18);
            } else {
                if (in_array($premium_type, ['third_party', 'third_party_breakin']))
                {
                    $final_gst_amount = round(($tppd + $cng_lpg_tp) * 0.12 + ($final_net_premium - $tppd - $cng_lpg_tp) * 0.18);
                }
                else
                {
                    $final_gst_amount = round(($tppd * 0.12) + (($final_net_premium - $tppd) * 0.18));
                }
            }
            // $final_gst_amount = round(str_replace("INR ", "", $response->serviceTax->totalTax)); // 18% IC 
            $final_payable_amount  = $final_net_premium + $final_gst_amount;

            $additional_addons = ['imt23', 'zeroDepreciation', 'consumables'];

            if($zero_depreciation === 0)
            {
                array_splice($additional_addons, array_search('zeroDepreciation', $additional_addons), 1);
            }

            if($consumables === 0)
            {
                array_splice($additional_addons, array_search('consumables', $additional_addons), 1);
            }

            if ($mmv->vehicle_type == 'Passenger Carrying') {
                array_splice($additional_addons, array_search('imt23', $additional_addons), 1);
            }

            if($imt23 === 0)
            {
                array_splice($additional_addons, array_search('imt23', $additional_addons), 1);
            }

            $business_types = [
                'rollover' => 'Rollover',
                'newbusiness' => 'New Business',
                'breakin' => 'Break-in'
            ];

            $data_response =
            [
                'status' => true,
                'msg' => 'Found',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Data' =>
                [   'idv' => $vehicle_idv,
                    'min_idv' => round($min_idv),
                    'max_idv' => round($max_idv),
                    'default_idv' => $default_idv,
                    'vehicle_idv' => $vehicle_idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 'Third Party' : (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']) ? 'Short Term' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => 0,
                    'version_id' => $mmv->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $vehicle_idv,
                    'fuel_type' => $mmv->fuel_type,
                    'ncb_discount' => (int)$ncb_discount > 0 ? $requestData->applicable_ncb : 0,
                    'tppd_discount' => $tppd_discount_amt,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => $mmv_data,
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'master_policy_id' =>
                    [   'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y', strtotime($contract->startDate)),
                        'policy_end_date' => date('d-m-Y', strtotime($contract->endDate)),
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
                    'vehicleDiscountValues' =>
                    [   'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'basic_premium' => $od - $imt23,
                    'deduction_of_ncb' => (int) $ncb_discount,
                    'tppd_premium_amount' => $tppd + $tppd_discount_amt,
                    'motor_electric_accessories_value' => 0,
                    'motor_non_electric_accessories_value' => 0,
                    /* 'motor_lpg_cng_kit_value' => '0', */
                    'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                    'seating_capacity' => $mmv->seating_capacity,
                    'default_paid_driver' => $llpaiddriver_premium + $llcleaner_premium,
                    'll_paid_driver_premium'    => $llpaiddriver_premium,
                    'll_paid_conductor_premium' => 0,
                    'll_paid_cleaner_premium'   => $llcleaner_premium,
                    'motor_additional_paid_driver' => $cover_pa_paid_driver_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium,
                    'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $final_od_premium,
                    /* 'cng_lpg_tp' => $cng_lpg_tp, */
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount'  => $final_payable_amount,
                    'antitheft_discount' => 0,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => (int) $final_total_discount,
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type],
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($contract->startDate)),
                    'policyEndDate' => date('d-m-Y', strtotime($contract->endDate)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => NULL,
                    'get_policy_expiry_date' => NULL,
                    'get_changed_discount_quoteid' => 0,
                    'GeogExtension_ODPremium' => 0,
                    'GeogExtension_TPPremium' => 0,
                    'LimitedtoOwnPremises_OD' => round($own_premises_od),
                    'LimitedtoOwnPremises_TP' => round($own_premises_tp),
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
                    'add_ons_data' =>   [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation' => $zero_depreciation,
                            'consumables' => $consumables,
                            'road_side_assistance' => $road_side_assistance,
                            'imt23' => $imt23
                        ],
                        'in_built_premium' => 0,
                        'additional_premium' => $imt23,
                        'other_premium' => 0
                    ],
                    'isInspectionApplicable' => $isInspectionApplicable,
                    'applicable_addons' => $additional_addons
                ],
            ];

            if ($productData->product_identifier == "IMT23") {
                $data_response['Data']['add_ons_data']['in_built'] = ['imt23' => $imt23];
                unset($data_response['Data']['add_ons_data']['additional']['imt23']);
            }
            if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                $data_response['Data']['premiumTypeCode'] = $premium_type;
            }
            if(!empty($cng_lpg_amt) || in_array($mmv->fuel_type, ['CNG', 'PETROL+CNG', 'DIESEL+CNG', 'LPG']))
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = '0';
                $data_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
            }
        }
        elseif(!empty($response->error->validationMessages[0]))
        {
            return 
            [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => str_replace(",","",$response->error->validationMessages[0])
            ];
        } 
        elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
        {
            return 
            [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => str_replace(",","",$response->error->validationMessages[0])
            ];
        } else {
            return [
                'status' => false,
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Something went wrong'
            ];
        }
    }
    else 
    {
        return
        [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => 'Insurer not reachable'
        ];
    }
    return camelCase($data_response);
}