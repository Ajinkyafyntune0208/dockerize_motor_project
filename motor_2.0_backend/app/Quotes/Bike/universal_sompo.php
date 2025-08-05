<?php

use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Carbon\Carbon;
use App\Models\UniversalSompoBikeAddonConfiguration;
use App\Quotes\Bike\V2\universal_sompo;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
// include_once app_path('Quotes/Bike/V2/universal_sampo.php');

function getQuote($enquiryId, $requestData, $productData)
{        
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if (config('IC.UNIVERSAL_SOMPO.V2.BIKE.ENABLE') == 'Y'){
    return  universal_sompo::getQuote($enquiryId, $requestData, $productData);
    }
    // if previous policy type is not sure then return false
    if ($requestData->previous_policy_type == "Not sure") {
        return [
            'status' => false,
            'message' => 'Quotes not allowed for Not Sure case',
        ];
    }

    if ((empty($requestData->applicable_ncb) && in_array($requestData->business_type ,['rollover', 'breakin']))) {
        return [
            'status' => false,
            'message' => 'Quotes are not provided by Insurance Company ',
            'request' => [
                'message' => 'Quotes are not provided by Insurance Company ',
                'applicable_ncb' => $requestData->applicable_ncb,
                'business_type' => $requestData->business_type
            ]
        ];
    }
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
    $mmv = get_mmv_details($productData, $requestData->version_id, $productData->company_alias);

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
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
    }

    $premium_type = MasterPremiumType::where('id',$productData->premium_type_id)->pluck('premium_type_code')->first();
    
     // car age calculation
    /* $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $bike_age = $age / 12;
    $bike_age = round($bike_age,2); */
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $bike_age = car_age($vehicleDate, $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date,'ceil');
    $interval = car_age_intervals($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $intval =json_decode($interval,true);
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();

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

    // RTO validation for DL
    $rto_code_check = explode('-', $requestData->rto_code);
    /* if (isset($rto_code_check) && $rto_code_check[0] == 'DL' && $rto_code_check[1] < 10) {
        $rto_code = $rto_code_check[0] . '0' . $rto_code_check[1];
    } else { */
        $rto_code = $rto_code_check[0] . $rto_code_check[1];
    /* } */

    $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
    $rto_payload = keysToLower($rto_payload);
    if(!$rto_payload && $rto_code_check[0] == 'DL')
    {
        $getRegisterNumberWithHyphen = explode('-',getRegisterNumberWithHyphen(str_replace('-', '', $requestData->vehicle_registration_no)));
        if(str_starts_with($getRegisterNumberWithHyphen[2],'S'))
        {
            $rto_code = $rto_code_check[0] . ($rto_code_check[1]*1).'S';
            $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
            $rto_payload = keysToLower($rto_payload);
        }
    }

    if (!$rto_payload) {
        return [
            'status' => false,
            'message' => 'Premium is not available for this RTO - ' . $rto_code,
            'request'=>[
                'rto_code'=>$rto_code
            ]
        ];
    }
    
    // default values
    $PolicyStatus = 'UnExpired';
    $covers_payload_zero = 'True';
    $covers_payload_first = 'True';
    $electrical_accessories_flag = 'True';
    $discount_flag_in_tp = 'True';
    $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime(($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date))))));
    $request_ex_showroom_Price = $mmv_data->ex_showroom_price;

    switch ($premium_type) {

        case 'comprehensive':
            $document_type = 'Quotation';
            $productname = ($requestData->business_type == 'newbusiness') ? 'MOTOR - MOTOR TWO WHEELER  - BUNDLED' : 'Two Wheeler Package Policy'; //
            $product_code = ($requestData->business_type == 'newbusiness') ? '2369' : '2312';
            $ncb_declaration = 'True';
            $vehicleBodyColor = 'Black';
            $policy_type = 'Comprehensive';
            
            break;

        case 'third_party':
            $document_type = 'Quotation';
            $productname = 'TWO WHEELER LIABILITY POLICY';
            $product_code = '2320';
            $covers_payload_zero = 'False';
            $ncb_declaration = 'False';
            $electrical_accessories_flag = 'False';
            $discount_flag_in_tp = 'False';
            $vehicleBodyColor = '';
            $policy_type = 'Third Party';
            
            break;

        case 'own_damage':
            $document_type = 'Quotation';
            $productname = 'TWO WHEELER - OD';
            $product_code = '2397';
            $vehicleBodyColor = '';
            $ncb_declaration = 'True';
            $covers_payload_first = 'False';
            $policy_type = 'Own Damage';

            // $request_ex_showroom_Price = DB::table('universal_sompo_ex_showroom_price')
            //     ->where('make_code', $mmv_data->make_id)
            //     ->where('model_code', $mmv_data->model_code)
            //     ->pluck('ex_showroom_price')
            //     ->first();

            // if(!$request_ex_showroom_Price){
            //     return [
            //         'status' => false,
            //         'message' => 'Error occured because of showroom Price',
            //         'request'=>[
            //             'request_ex_showroom_Price'=>$request_ex_showroom_Price
            //         ]
            //     ];
            // }

            break;
    }


    switch ($requestData->business_type) {

        case 'rollover':
            $policyType = 'Roll Over';
            $business_type = 'Rollover';
            $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $RegistrationNumber = $requestData->rto_code . '-GA-8819';
            $veh_type = 'Rollover';
            break;

        case 'newbusiness':
            $policyType = 'New Business';
            $business_type = 'New Vehicle';
            if($premium_type == 'third_party')
            {
                $policy_start_date = date('d/m/Y',strtotime('+1 day'));
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));

            }else
            {
                $policy_start_date = date('d/m/Y');
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            }
            $RegistrationNumber = 'New';
            $veh_type = 'New';
            break;

        case 'breakin':
            $policyType = 'Breakin';
            $business_type = 'Rollover';
            $policy_start_date = date('d/m/Y', strtotime("+2 day"));
            $PolicyStatus = $premium_type !== 'third_party' ? 'Expired' : 'UnExpired';
            $RegistrationNumber = $requestData->rto_code . '-GA-8819';
            $veh_type = '';
            $vehicleBodyColor = '';
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $previousPolicyExpiryDate = Carbon::createFromDate(($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date));
            $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
            if ($date_diff_in_prev_policy < 90) {
                $ncb_declaration = 'True';
            }else{
                $ncb_declaration = 'False';
            }
            break;

    }

    try {

        $PreviousPolExpDt = date('d/m/Y', strtotime(($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date)));

        $cpa = 'True';
        $elect_accessories = 'False';
        $elect_accessories_sumInsured = 0;
        $non_elect_accessories = 'False';
        $non_elect_accessories_sumInsured = 0;
        $external_lpg_cng_accessories = 'False';
        $external_lpg_cng_accessories_sumInsured = 0;
        $pa_add_paid_driver_cover = 'False';
        $pa_add_paid_driver_cover_sumInsured = 0;
        $unnamed_psenger_cover = 'False';
        $unnamed_psenger_cover_sumInsured = 0;
        $llpd = 'False';
        $anti_theft_device = 'False';
        $voluntry_discount = 'False';
        $voluntry_discount_sumInsured = 0;
        $tppd_cover_flag = 'False';

        $addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

        if ($addons) {
            if ($addons->compulsory_personal_accident) {
                foreach ($addons->compulsory_personal_accident as $key => $cpaVal) {
                    if (isset($cpaVal['name']) && $cpaVal['name'] == 'Compulsory Personal Accident') {
                        $cpa = 'True';
                    }
                }
            }

            if ($addons->accessories) {
                foreach ($addons->accessories as $key => $accessories) {
                    if ($accessories['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $external_lpg_cng_accessories = 'True';
                        $external_lpg_cng_accessories_sumInsured = $accessories['sumInsured'];
                    }
                    if($electrical_accessories_flag !== 'False'){
                        if ($accessories['name'] == 'Non-Electrical Accessories') {
                            $non_elect_accessories = 'True';
                            $non_elect_accessories_sumInsured = $accessories['sumInsured'];
                        }
                        if ($accessories['name'] == 'Electrical Accessories') {
                            $elect_accessories = 'True';
                            $elect_accessories_sumInsured = $accessories['sumInsured'];
                        }
                    }
                }
            }

            if($elect_accessories_sumInsured>50000){
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Electrical Accessories can not be greater than 50000',
                    'request' => [
                        'message' => 'Electrical Accessories can not be greater than 50000',
                    ]
                ];
            }
            if($non_elect_accessories_sumInsured>50000){
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Non Electrical Accessories can not be greater than 50000',
                    'request' => [
                        'message' => 'Non Electrical Accessories can not be greater than 50000',
                    ]
                ];
            }

            if ($addons->additional_covers) {
                foreach ($addons->additional_covers as $key => $additional_covers) {
                    if ($additional_covers['name'] == 'PA cover for additional paid driver') {
                        $pa_add_paid_driver_cover = 'True';
                        $pa_add_paid_driver_cover_sumInsured = $additional_covers['sumInsured'];
                    }
                    if ($additional_covers['name'] == 'Unnamed Passenger PA Cover') {
                        $unnamed_psenger_cover = 'True';
                        $unnamed_psenger_cover_sumInsured = $additional_covers['sumInsured'];
                    }
                    if ($additional_covers['name'] == 'LL paid driver') {
                        $llpd = 'True';
                    }
                }
            }

            if ($addons->discounts) {
                foreach ($addons->discounts as $key => $discounts) {
                    if($discount_flag_in_tp !== 'False'){
                        if ($discounts['name'] == 'anti-theft device') {
                            $anti_theft_device = 'True';
                        }
                        if ($discounts['name'] == 'voluntary_insurer_discounts') {
                            $voluntry_discount = 'True';
                            $voluntry_discount_sumInsured = $discounts['sumInsured'];
                        }
                    }
                    if ($discounts['name'] == 'TPPD Cover') {
                        $tppd_cover_flag = 'True';
                    }
                }
            } 
        }

        switch ($mmv_data->fuel_type) {

            case 'Petrol':
                $engine_protector_cover_diesel = 'False';
                $engine_protector_cover_petrol = 'False';
                break;

            case 'Diesel':
                $engine_protector_cover_diesel = 'False';
                $engine_protector_cover_petrol = 'False';
                break;

            default:
                $engine_protector_cover_diesel = 'False';
                $engine_protector_cover_petrol = 'False';
                break;
        }
        
        $vehicle_make = "bike";
        $enable_tyre_secure = "false";
        $key_replacement_flag = 'False';
        $usgi_applicable_addons = get_usgi_applicable_addons($bike_age,$vehicle_make);
        $usgi_applicable_addons['zero_dep'] = 'false';
        # Zero dep product ->>
        if ($masterProduct->product_identifier == 'zero_dep' && $bike_age <= 5 && $premium_type != 'third_party') {
            $usgi_applicable_addons['zero_dep'] = 'true';
        }
        if($premium_type != 'third_party')
        {
            $usgi_applicable_addons['return_to_invoice'];
            $usgi_applicable_addons['engine_protector'];
            $usgi_applicable_addons['consumable'];
        }

        $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_TESTING_UNIVERSAL');
        $pos_name = $pos_pan = $pos_aadhar_no = $pos_email = $pos_mobile_no = '';
        $branchCode_value = config('constants.motorConstant.BRANCH_CODE_VALUE_UNIVERSAL');
        $branchCode_name = config('constants.motorConstant.BRANCH_CODE_NAME_UNIVERSAL');
        $branchName_name = config('constants.motorConstant.BRANCH_NAME_NAME_UNIVERSAL');
        $branchName_value = config('constants.motorConstant.BRANCH_NAME_VALUE_UNIVERSAL');

        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 5;
        }
        else
        {
                $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 1;
        }
        if($requestData->vehicle_owner_type != 'I')
        {
            $cpa = 'False';
            $cpa_tenure = '0';
        }
        if(in_array($premium_type ,['own_damage','third_party']))
        {
            $cpa = 'False';
            $cpa_tenure = '0';
        }
        /* $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id',$requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
        {
            if($pos_data->agent_mobile != '' && $pos_data->agent_name != '' && $pos_data->pan_no != '')
            {
                $pos_name = $pos_data->agent_name;
                $pos_pan = $pos_data->pan_no;
                $pos_aadhar_no = $pos_data->aadhar_no;
                $pos_email = $pos_data->agent_email;
                $pos_mobile_no = $pos_data->agent_mobile;
            }
            else
            {
                return 
                [
                    'status' => false,
                    'message' => 'POS details are missing'
                ];
            }

        }else if($is_pos_enabled_testing == 'Y')
        {
            $pos_name = 'Asd';
            $pos_pan = 'ABGTY8890Z';
            $pos_aadhar_no = '569278616999';
            $pos_email = 'asd@gmail.com';
            $pos_mobile_no = '8765434567';
        }
        else
        {
            $pos_name = '';
            $pos_pan = '';
            $pos_aadhar_no = '';
            $pos_email = '';
            $pos_mobile_no = '';
            
        } */

        $previous_policy_details_section = [
            'PreviousPolDtlGroup' => [
                'PreviousPolDtlGroupData' => [
                    'ProductCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Product Code',
                            'Value' => ''
                        ],
                    ],
                    'ClaimSettled' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Claim Settled',
                            'Value' => ''
                        ],
                    ],
                    'ClaimPremium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Claim Premium',
                            'Value' => ''
                        ],
                    ],
                    'ClaimAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Claim Amount',
                            'Value' => ''
                        ],
                    ],
                    'DateofLoss' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Date Of Loss',
                            'Value' => ''
                        ],
                    ],
                    'NatureofLoss' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Nature Of Loss',
                            'Value' => ''
                        ],
                    ],

                    'ClaimNo' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Claim No',
                            'Value' => '9999999999'
                        ],
                    ],
                    'PolicyEffectiveTo' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Policy Effective To',
                            'Value' => $PreviousPolExpDt
                        ],
                    ],
                    'PolicyEffectiveFrom' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Policy Effective From',
                            'Value' => $previous_policy_start_date
                        ],
                    ],
                    'DateOfInspection' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'DateOfInspection',
                            'Value' => ''
                        ],
                    ],
                    'PolicyPremium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'PolicyPremium',
                            'Value' => '1000.00'
                        ],
                    ],
                    'PolicyNo' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Policy No',
                            'Value' => '231/5464'
                        ],
                    ],
                    'PolicyYear' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Policy Year',
                            'Value' => ''
                        ],
                    ],
                    'OfficeCode' => [
                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Office Name',
                            'Value' => ''
                        ],
                    ],
                    'PolicyStatus' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Policy Status',
                            'Value' => $PolicyStatus
                        ],
                    ],
                    'CorporateCustomerId' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Corporate Customer Id',
                            'Value' => '',
                        ],
                    ],
                    'InsurerName' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'InsurerName',
                            'Value' => 'Bajaj Allianz General Insurance Co. Ltd.'
                        ],
                    ],
                    'InsurerAddress' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'InsurerAddress',
                            'Value' => 'PUNE'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'Group',
                    'Name' => 'Previous Pol Dtl Group'
                ],

            ],
            'PreviousPolicyType' => [
                '@value' => '',
                '@attributes' => [
                    'Name' => 'Previous Policy Type',
                    'Value' => 'Package Policy'
                ],
            ],
            'OfficeAddress' => [
                '@value' => '',
                '@attributes' => [
                    'Name' => 'Office Address',
                    'Value' => ''
                ],

            ],
            '@attributes' => [
                'Name' => 'Previous Policy Details'
            ],

        ];

        $covers_data_section = [
            '0' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $covers_payload_zero,
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'Basic OD'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '1' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $covers_payload_first,
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'Basic TP'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '2' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => $elect_accessories_sumInsured,
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $elect_accessories
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'ELECTRICAL ACCESSORY OD'
                    ],
                ],

                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],

            '3' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => $non_elect_accessories_sumInsured
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $non_elect_accessories
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'NON ELECTRICAL ACCESSORY OD'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '4' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ($requestData->fuel_type == "CNG" && $external_lpg_cng_accessories_sumInsured == "") ?
                            "10000" : (($external_lpg_cng_accessories_sumInsured != '') ? $external_lpg_cng_accessories_sumInsured : '0')
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $premium_type == 'third_party' ? 'False' : $external_lpg_cng_accessories
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'CNGLPG KIT OD'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '5' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ($requestData->fuel_type == "CNG" && $external_lpg_cng_accessories_sumInsured == "") ?
                            "10000" : (($external_lpg_cng_accessories_sumInsured != '') ? $external_lpg_cng_accessories_sumInsured : '0')
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $external_lpg_cng_accessories, //($car_data['premium_type'] == 'O') ? 'False' : $lpg_cng_od
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'CNGLPG KIT TP'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '6' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $requestData->fuel_type == 'CNG' ? 'True' : 'False'
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'BUILTIN CNG KIT / LPG KIT OD'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '7' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'FIBRE TANK - OD'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '8' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'Other OD'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '9' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '100000'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $llpd
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'LEGAL LIABILITY TO PAID DRIVER'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '10' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => $unnamed_psenger_cover_sumInsured
                    ],
                ],

                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $unnamed_psenger_cover
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'UNNAMED PA COVER TO PASSENGERS'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '11' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => $pa_add_paid_driver_cover_sumInsured
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $pa_add_paid_driver_cover
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'PA COVER TO PAID DRIVER'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '12' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => $cpa_tenure
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '0'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $requestData->policy_type == 'own_damage' ? 'False' : $cpa
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'PA COVER TO OWNER DRIVER'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
        ];

        $add_on_cover_data_section = [
            '0' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => ' Applicable',
                        'Value' => $usgi_applicable_addons['zero_dep'], //$nil_depreciation
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' =>
                        'Nil Depreciation Waiver cover'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '1' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '0'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'DAILY CASH ALLOWANCE'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '2' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => '',
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $key_replacement_flag,
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'KEY REPLACEMENT'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '3' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $usgi_applicable_addons['return_to_invoice'],
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'RETURN TO INVOICE'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '4' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '0'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'ACCIDENTAL HOSPITALIZATION'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '5' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $premium_type == 'third_party' ? 'False' : 'True',
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'Road side Assistance'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '6' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '0'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'HYDROSTATIC LOCK COVER'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '7' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $usgi_applicable_addons['consumable'],
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'COST OF CONSUMABLES'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '8' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => ' Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [

                        'Name' => 'Rate',
                        'Value' =>
                        ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '0'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'SECURE TOWING'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '9' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $premium_type == 'third_party' ? 'False' : $usgi_applicable_addons['engine_protector']
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'ENGINE PROTECTOR - PETROL'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '10' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $premium_type == 'third_party' ? 'False' : $engine_protector_cover_diesel,
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'ENGINE PROTECTOR - DIESEL'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '11' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '1000.00'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $enable_tyre_secure,
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'TYRE AND RIM SECURE'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '12' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '0'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'AddonCoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'AddonCoverGroups',
                        'Value' => 'WRONG FUEL COVER'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
        ];

        $detariff_discount_section = [
            'De-tariffDiscountGroup' => [
                'De-tariffDiscountGroupData' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => '0.0'
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000',
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => '0'
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'True'
                        ],
                    ],

                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Description',
                            'Value' => 'De-tariff Discount'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '@attributes' => [
                    'Name' => 'De-tariff Discount Group',
                    'Type' => 'Group'
                ],
            ],
            '@attributes' => [
                'Name' => 'De-tariffDiscounts'
            ],
        ];

        $other_discount_section = [
            '0' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => ''
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Description',
                        'Value' => 'Detariff discount'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '1' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => ''
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $anti_theft_device, //$anti_theft
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => ' Description',
                        'Value' => 'Antitheft device discount'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '2' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => ''
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False', // $automobile_association
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Description',
                        'Value' => 'Automobile Association discount'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '3' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => ''
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'False'
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Description',
                        'Value' => 'Handicap discount'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '4' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => $premium_type == 'third_party' ? '0' : $voluntry_discount_sumInsured,
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => ''
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => ''
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => ''
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => ' Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $premium_type == 'third_party' ? 'False' : $voluntry_discount,
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Description',
                        'Value' => 'Voluntary deductable'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '5' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => ''
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => ((int) $requestData->applicable_ncb) / 100
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '600000.00'
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => '0.0000'
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $ncb_declaration
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Description',
                        'Value' => 'No claim bonus'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
            '6' => [
                'DiscountAmount' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Amount',
                        'Value' => ''
                    ],
                ],
                'DiscountRate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Discount Rate',
                        'Value' => '0'
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '6000.00'
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => '0.0000'
                    ],
                ],
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => ''
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => $tppd_cover_flag
                    ],
                ],
                'Description' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Description',
                        'Value' => 'TPPD Discount'
                    ],
                ],
                '@attributes' => [
                    'Type' => 'GroupData'
                ],
            ],
        ];

        $quote_array = [
            'Authentication' =>
            [
                'WACode' =>  config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MOTOR'),
                'WAAppCode' => config('constants.IcConstants.universal_sompo.AUTH_APPCODE_SOMPO_MOTOR'), //AUTH_APPCODE_SOMPO_MOTOR,
                'WAUserID' => config('constants.IcConstants.universal_sompo.WEB_USER_ID_SOMPO_MOTOR'),   //WEB_USER_ID_SOMPO_MOTOR,
                'WAUserPwd' => config('constants.IcConstants.universal_sompo.WEB_USER_PASSWORD_SOMPO_MOTOR'), // WEB_USER_PASSWORD_SOMPO_MOTOR,
                'WAType'  => '0',
                'DocumentType' => $document_type,
                'Versionid' => '1.1',
                'GUID' => config('constants.IcConstants.universal_sompo.GUID_UNIVERSAL_SOMPO_MOTOR'), //GUID_UNIVERSAL_SOMPO_MOTOR
            ],
            'Customer' =>
            [
                'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "Individual" : "Corporate",
                'CustomerName' => 'Shabbir Bapu',
                'DOB' => '21/10/1997',
                'Gender' => 'M',
                'CanBeParent' => '0',
                'ContactTelephoneSTD' => '',
                'MobileNo' => '9874561231',
                'Emailid' => 'rajam@gmail.com',
                'PresentAddressLine1' => 'panvel road ,shiva complex',
                'PresentAddressLine2' => 'dfdvvh',
                'PresentStateCode' => 'Maharashtra',
                'PresentCityDistCode' => 'Raigarh',
                'PresentPinCode' => '410206',
                'PermanentAddressLine1' => 'panvel road ,shiva complex',
                'PermanentAddressLine2' => 'dfdvvh',
                'PermanentStateCode' => 'Maharashtra',
                'PermanentCityDistCode' => 'Raigarh',
                'PermanentPinCode' => '410206',
                'CustGSTNo' => '',
                'ProductName' => $productname,
                'ProductCode' => $product_code,
                'InstrumentNo' => 'NULL',
                'InstrumentDate' => 'NULL',
                'BankID' => 'NULL',
                'PosPolicyNo' => '',
                'WAURN' => '',
                'NomineeName' => '',
                'NomineeRelation' => '',
                'PANNo' => '',
                'AadhaarNo' => '',
            ],
            'POSAGENT' =>
            [
                'Name' => $pos_name,
                'PAN'=> $pos_pan,
                'Aadhar' => $pos_aadhar_no,//TestAadhar',
                'Email' => $pos_email,
                'MobileNo' => $pos_mobile_no,
                'Location' => '', 
                'Information1' => '',
                'Information2' => ''
            ],
            'Product' => [
                'GeneralProposal' => [
                    'GeneralProposalGroup' => [
                        'DistributionChannel' => [
                            'BranchDetails' => [
                                'IMDBranchName' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => $branchName_name,
                                        'Value' => $branchName_value
                                    ],
                                ],
                                'IMDBranchCode' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => $branchCode_name,
                                        'Value' => $branchCode_value
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'Branch Details'
                                ],
                            ],
                            'SPDetails' => [
                                'SPName' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'SP Name',
                                        'Value' => ''
                                    ],
                                ],
                                'SPCode' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'SP Code',
                                        'Value' => ''
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'SP Details'
                                ],
                            ],
                            '@attributes' => [
                                'Name' => 'Distribution Channel'
                            ],
                        ],
                        'GeneralProposalInformation' => [
                            'TypeOfBusiness' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Type Of Business',
                                    'Value' => 'FROM INTERMEDIARY'
                                ],
                            ],
                            'ServiceTaxExemptionCategory' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Service Tax Exemption Category',
                                    'Value' => 'No Exemption'
                                ],
                            ],
                            'BusinessType' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Transaction Type',
                                    'Value' => $business_type
                                ],
                            ],
                            'Sector' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Sector',
                                    'Value' => 'Others'
                                ],
                            ],
                            'ProposalDate' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Proposal Date',
                                    'Value' => date('d/m/Y')
                                ],
                            ],
                            'DealId' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Deal Id',
                                    'Value' => '',
                                ],
                            ],
                            'PolicyNumberChar' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'PolicyNumberChar',
                                    'Value' => ''
                                ],
                            ],
                            'VehicleLaidUpFrom' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'VehicleLaidUpFrom',
                                    'Value' => ''
                                ],
                            ],
                            'VehicleLaidUpTo' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'VehicleLaidUpTo',
                                    'Value' => ''
                                ]
                            ],
                            'PolicyEffectiveDate' => [
                                'Fromdate' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'From Date',
                                        'Value' => $policy_start_date
                                    ],
                                ],
                                'Todate' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'To Date',
                                        'Value' => $policy_end_date
                                    ],
                                ],
                                'Fromhour' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'From Hour',
                                        'Value' => '18:04'
                                    ],
                                ],
                                'Tohour' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'To Hour',
                                        'Value' => '23:59'
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'Policy Effective Date'
                                ],
                            ],
                            '@attributes' => [
                                'Name' => 'General Proposal Information'
                            ],
                        ],
                        '@attributes' => [
                            'Name' => 'General Proposal Group'
                        ],
                    ],
                    'FinancierDetails' => [
                        'FinancierDtlGrp' => [
                            'FinancierDtlGrpData' => [
                                'FinancierCode' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'Financier Code',
                                        'Value' => '1'
                                    ],
                                ],
                                'AgreementType' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'Agreement Type',
                                        'Value' => 'None'
                                    ],
                                ],
                                'BranchName' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'Branch Name',
                                        'Value' => ''
                                    ],
                                ],
                                'FinancierName' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'Financier Name',
                                        'Value' => ''
                                    ],
                                ],
                                'SrNo' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'Sr No',
                                        'Value' => '1'
                                    ],
                                ],
                                '@attributes' => [
                                    'Type' => 'GroupData'
                                ],
                            ],
                            '@attributes' => [
                                'Type' => 'Group',
                                'Name' => 'Financier Dtl Group'
                            ],
                        ],
                        '@attributes' => [
                            'Name' => 'Financier Details'
                        ],
                    ],
                    'PreviousPolicyDetails' => $previous_policy_details_section,
                    '@attributes' => [
                        'Name' => 'General Proposal'
                    ],
                ],
                'PremiumCalculation' => [
                    'NetPremium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Net Premium',
                            'Value' => '0'
                        ],
                    ],
                    'ServiceTax' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Service Tax',
                            'Value' => '0'
                        ],
                    ],
                    'StampDuty2' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Stamp Duty',
                            'Value' => '0'
                        ],
                    ],
                    'CGST' => [
                        '@value' => '0',
                        '@attributes' => [
                            'Name' => 'CGST',
                            'Value' => '0'
                        ],
                    ],
                    'SGST' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SGST',
                            'Value' => '0'
                        ],
                    ],
                    'UGST' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'UGST',
                            'Value' => '0'
                        ],
                    ],
                    'IGST' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'IGST',
                            'Value' => '0'
                        ],
                    ],
                    'TotalPremium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Total Premium',
                            'Value' => '0'
                        ],
                    ],
                    '@attributes' => [
                        'Name' => 'Premium Calculation'
                    ],
                ],
                'Risks' => [
                    'Risk' => [
                        'RisksData' => [
                            'De-tariffLoadings' => [
                                'De-tariffLoadingGroup' => [
                                    'De-tariffLoadingGroupData' => [
                                        'LoadingAmount' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'Loading Amount',
                                                'Value' => ''
                                            ],
                                        ],
                                        'LoadingRate' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'Loading Rate',
                                                'Value' => ''
                                            ],
                                        ],
                                        'SumInsured' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'SumInsured',
                                                'Value' => ''
                                            ],
                                        ],
                                        'Rate' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'Rate',
                                                'Value' => ''
                                            ],
                                        ],
                                        'Premium' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'Premium',
                                                'Value' => ''
                                            ],
                                        ],
                                        'Applicable' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'Applicable',
                                                'Value' => 'True'
                                            ],
                                        ],
                                        'Description' => [
                                            '@value' => '',
                                            '@attributes' => [
                                                'Name' => 'Description',
                                                'Value' => 'De-tariff Loading'
                                            ],
                                        ],
                                        '@attributes' => [
                                            'Type' => 'GroupData'
                                        ],
                                    ],
                                    '@attributes' => [
                                        'Type' => 'Group',
                                        'Name' => 'De-tariff Loading Group'
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'De-tariffLoadings'
                                ],
                            ],
                            'De-tariffDiscounts' => $detariff_discount_section,
                            'CoverDetails' => [
                                'Covers' => [
                                    'CoversData' =>  $covers_data_section,
                                    '@attributes' => [
                                        'Type' => 'Group',
                                        'Name' => 'Covers'
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'CoverDetails'
                                ],
                            ],
                            'AddonCoverDetails' => [
                                'AddonCovers' => [
                                    'AddonCoversData' =>
                                    $add_on_cover_data_section,
                                    '@attributes' => [
                                        'Type' => 'Group',
                                        'Name' => 'AddonCovers'
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'AddonCoverDetails'
                                ],
                            ],
                            'OtherLoadings' => [
                                'OtherLoadingGroup' => [
                                    'OtherLoadingGroupData' => [
                                        '0' => [
                                            'LoadingAmount' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Loading Amount',
                                                    'Value' => ''
                                                ],
                                            ],
                                            'LoadingRate' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Loading Rate',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'SumInsured' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'SumInsured',
                                                    'Value' => ''
                                                ],
                                            ],
                                            'Rate' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Rate',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'Premium' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => ' Premium',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'Applicable' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Applicable',
                                                    'Value' => 'false'
                                                ],
                                            ],
                                            'Description' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Description',
                                                    'Value' => 'Adverse Claim Loading'
                                                ],
                                            ],
                                            '@attributes' => [
                                                'Type' => 'GroupData'
                                            ],
                                        ],
                                        '1' => [
                                            'LoadingAmount' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Loading Amount',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'LoadingRate' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Loading Rate',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'SumInsured' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'SumInsured',
                                                    'Value' => ''
                                                ],
                                            ],
                                            'Rate' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Rate',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'Premium' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Premium',
                                                    'Value' =>
                                                    ''
                                                ],
                                            ],
                                            'Applicable' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Applicable',
                                                    'Value' => 'false'
                                                ],
                                            ],
                                            'Description' => [
                                                '@value' => '',
                                                '@attributes' => [
                                                    'Name' => 'Description',
                                                    'Value' => 'BreakIn Loading'
                                                ],
                                            ],
                                            '@attributes' => [
                                                'Type' => 'GroupData'
                                            ],
                                        ],
                                    ],
                                    '@attributes' => [
                                        'Type' => 'Group',
                                        'Name' => 'Other Loading Group'
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'OtherLoadings'
                                ],
                            ],
                            'OtherDiscounts' => [
                                'OtherDiscountGroup' => [
                                    'OtherDiscountGroupData' => $other_discount_section,
                                    '@attributes' => [
                                        'Type' => 'Group',
                                        'Name' => 'Other Discount Group'
                                    ],
                                ],
                                '@attributes' => [
                                    'Name' => 'OtherDiscounts'
                                ],
                            ],
                            '@attributes' => [
                                'Type' => 'GroupData'
                            ],
                        ],
                        '@attributes' => [
                            'Type' => 'Group',
                            'Name' => 'Risks'
                        ],
                    ],
                    'VehicleClassCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleClassCode',
                            'Value' => '37', //$mmv_data['USGI_CLASS_CODE']
                        ],
                    ],
                    'VehicleMakeCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleMakeCode',
                            'Value' => $mmv_data->make_id
                        ],
                    ],
                    'VehicleModelCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleModelCode',
                            'Value' => $mmv_data->model_code
                        ],
                    ],
                    'RTOLocationCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'RTOLocationCode',
                            'Value' => $rto_payload->rto_location_code
                        ],
                    ],
                    'NoOfClaimsOnPreviousPolicy' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'No Of Claims On Previous Policy',
                            'Value' => ''
                        ],
                    ],
                    'RegistrationNumber' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Registration Number',
                            'Value' => $RegistrationNumber,
                        ],
                    ],
                    'BodyTypeCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'BodyTypeCode',
                            'Value' => $mmv_data->body_type_code
                        ],
                    ],
                    'ModelStatus' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'ModelStatus',
                            'Value' => ''
                        ],
                    ],
                    'GrossVehicleWeight' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'GrossVehicleWeight',
                            'Value' => '0'
                        ],
                    ],
                    'CarryingCapacity' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CarryingCapacity',
                            'Value' => $mmv_data->seating_capacity - 1//'5'
                        ],
                    ],
                    'VehicleType' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleType',
                            'Value' => $veh_type
                        ],
                    ],
                    'PlaceOfRegistration' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Place Of Registration',
                            'Value' => $rto_payload->rto_location
                        ],
                    ],
                    'VehicleModel' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleModel',
                            'Value' => $mmv_data->model . ' ' . $mmv_data->variant
                        ],
                    ],
                    'VehicleExShowroomPrice' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleExShowroomPrice',
                            'Value' => $request_ex_showroom_Price
                        ],
                    ],
                    'DateOfDeliveryOrRegistration' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'DateOfDeliveryOrRegistration',
                            'Value' => $vehicleDate
                        ],
                    ],
                    'YearOfManufacture' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Year Of Manufacture',
                            'Value' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                        ],
                    ],
                    'DateOfFirstRegistration' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'DateOfFirstRegistration',
                            'Value' => $requestData->vehicle_register_date
                        ],
                    ],
                    'RegistrationNumberSection1' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Regn No. Section 1',
                            'Value' => ''
                        ],
                    ],
                    'RegistrationNumberSection2' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Regn No. Section 2',
                            'Value' => '',
                        ],
                    ],
                    'RegistrationNumberSection3' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Regn No. Section 3',
                            'Value' => ''
                        ],
                    ],
                    'RegistrationNumberSection4' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Regn No. Section 4',
                            'Value' => '',
                        ],
                    ],
                    'EngineNumber' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Engine Number',
                            'Value' => '456889757575656'
                        ],
                    ],
                    'ChassisNumber' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Chassis Number',
                            'Value' => 'AYU881963'
                        ],
                    ],
                    'BodyColour' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Body Colour',
                            'Value' =>  $vehicleBodyColor,
                        ],
                    ],
                    'FuelType' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Fuel Type',
                            'Value' => strtoupper($mmv_data->fuel_type) == 'ELECTRIC BATTERY' ? 'Electrical' : $mmv_data->fuel_type///'Petrol'
                        ],
                    ],
                    'ExtensionCountryName' => [
                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Extension Country Name',
                            'Value' => ''
                        ],
                    ],
                    'RegistrationAuthorityName' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Registration Authority Name',
                            'Value' => 'Mumbai'
                        ],
                    ],
                    'AutomobileAssocnFlag' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AutomobileAssocnFlag',
                            'Value' => 'False'
                        ],
                    ],
                    'AutomobileAssociationNumber' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Automobile Association Number',
                            'Value' => '1234'
                        ],
                    ],
                    'VoluntaryExcess' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Voluntary Access',
                            'Value' => ''
                        ],

                    ],

                    'TPPDLimit' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'TPPDLimit',
                            'Value' => '6000'
                        ],
                    ],

                    'AntiTheftDiscFlag' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AntiTheftDiscFlag',
                            'Value' => 'True'
                        ],
                    ],

                    'HandicapDiscFlag' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'HandicapDiscFlag',
                            'Value' => ''
                        ],
                    ],

                    'NumberOfDrivers' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'NumberOfDrivers',
                            'Value' => '0'
                        ],
                    ],
                    'NumberOfEmployees' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'NumberOfEmployees',
                            'Value' => ''
                        ],

                    ],

                    'TransferOfNCB' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'TransferOfNCB',
                            'Value' => ''
                        ],

                    ],

                    'TransferOfNCBPercent' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'TransferOfNCBPercent',
                            'Value' => ''
                        ],

                    ],

                    'NCBDeclaration' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'NCBDeclaration',
                            'Value' => ''
                        ],

                    ],

                    'PreviousVehicleSaleDate' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'PreviousVehicleSaleDate',
                            'Value' => ''
                        ],

                    ],

                    'BonusOnPreviousPolicy' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'BonusOnPreviousPolicy',
                            'Value' => ''
                        ],

                    ],

                    'VehicleClass' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'VehicleClass',
                            'Value' => 'Two Wheeler'
                        ],

                    ],

                    'VehicleMake' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'VehicleMake',
                            'Value' => $mmv_data->make
                        ],

                    ],

                    'BodyTypeDescription' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'BodyTypeDescription',
                            'Value' => ''
                        ],

                    ],

                    'NumberOfWheels' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'NumberOfWheels',
                            'Value' => '2'
                        ],

                    ],

                    'CubicCapacity' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'CubicCapacity',
                            'Value' => $mmv_data->cubic_capacity
                        ],

                    ],

                    'SeatingCapacity' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'SeatingCapacity',
                            'Value' => $mmv_data->seating_capacity
                        ],

                    ],

                    'RegistrationZone' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'RegistrationZone',
                            'Value' => $rto_payload->zone
                        ],

                    ],

                    'VehiclesDrivenBy' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Vehicles Driven By',
                            'Value' => ''
                        ],

                    ],

                    'DriversAge' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Drivers Age',
                            'Value' => ''
                        ],

                    ],

                    'DriversExperience' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Drivers Experience',
                            'Value' => ''
                        ],

                    ],

                    'DriversQualification' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Drivers Qualification',
                            'Value' => '',
                        ],

                    ],

                    'VehicleModelCluster' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'VehicleModelCluster',
                            'Value' => ''
                        ],

                    ],

                    'OpenCoverNoteFlag' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'OpenCoverNote',
                            'Value' => ''
                        ],

                    ],

                    'LegalLiability' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'LegalLiability',
                            'Value' => ''
                        ],

                    ],

                    'PaidDriver' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'PaidDriver',
                            'Value' => ''
                        ],

                    ],

                    'NCBConfirmation' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'NCBConfirmation',
                            'Value' => ''
                        ],

                    ],

                    'RegistrationDate' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'RegistrationDate',
                            'Value' => $requestData->vehicle_register_date
                        ],

                    ],

                    'TPLoadingRate' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'TPLoadingRate',
                            'Value' => '0'
                        ],

                    ],

                    'ExtensionCountry' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'ExtensionCountry',
                            'Value' => ''
                        ],

                    ],

                    'VehicleAge' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'VehicleAge',
                            'Value' => $bike_age
                        ],

                    ],

                    'LocationCode' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'LocationCode',
                            'Value' => ''
                        ],

                    ],

                    'RegistrationZoneDescription' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'RegistrationZoneDescription',
                            'Value' => ''
                        ],

                    ],

                    'NumberOfWorkmen' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'NumberOfWorkmen',
                            'Value' => ''
                        ],

                    ],

                    'VehicCd' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'VehicCd',
                            'Value' => ''
                        ],

                    ],

                    'SalesTax' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Sales Tax',
                            'Value' => ''
                        ],

                    ],

                    'ModelOfVehicle' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Model Of Vehicle',
                            'Value' => ''
                        ],

                    ],

                    'PopulateDetails' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Populate details',
                            'Value' => ''
                        ],

                    ],
                    'VehicleIDV' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleInsuredDeclaredValue',
                            'Value' => '' // pass idv here...
                        ],
                    ],
                    'ShowroomPriceDeviation' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'ShowroomPriceDeviation',
                            'Value' => ''
                        ],
                    ],
                    'NewVehicle' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'New Vehicle',
                            'Value' => $business_type
                        ],
                    ],
                    'PUCDeclaration' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'PUCDeclaration',
                            'Value' => 'YES'
                        ],
                    ],
                    '@attributes' => [
                        'Name' => 'Risks'
                    ],
                ],
                '@attributes' => [
                    'Name' => $productname
                ],
            ],
            'PaymentDetails' => [
                'PaymentEntry' => [
                    [
                        'PaymentId' => '',
                        'MICRCheque' => '',
                        'InstrumentDate' => '',
                        'DraweeBankName' => '',
                        'HOUSEBANKNAME' => '',
                        'AmountPaid' => '',
                        'PaymentType' => '',
                        'PaymentDate' => '',
                        'PaymentMode' => '',
                        'InstrumentNo' => '',
                        'Status' => '',
                        'DepositSlipNo' => '',
                        'PayerType' => ''
                    ],
                ],
            ],
            'Errors' => [
                'ErrorCode' => '',
                'ErrDescription' => ''
            ],
        ];

        $checksum_data = checksum_encrypt($quote_array);
        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'universal_sompo', $checksum_data, 'BIKE');

        $xmlQuoteRequest = ArrayToXml::convert($quote_array, 'Root');
        $additionData = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'commBRIDGEFusionMOTOR',
            'container'   => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header /><soapenv:Body><tem:commBRIDGEFusionMOTOR><tem:strXdoc>#replace</tem:strXdoc></tem:commBRIDGEFusionMOTOR></soapenv:Body></soapenv:Envelope>',
            'method' => 'Quote Generation',
            'checksum' => $checksum_data,
            'section' => $productData->product_sub_type_code,
            'productName'   => $productData->product_name,
            'transaction_type' => 'quote',
        ];

        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
            $get_response = $is_data_exist_for_checksum;
        }else{
            $get_response = getWsData(config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_CAR'), $xmlQuoteRequest, 'universal_sompo', $additionData);
        }

        $response=$get_response['response'];
        if ($response) {
            $response = html_entity_decode($response);
            $response = XmlToArray::convert($response);

            $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];

            $condition_1 = isset($filter_response['Root']['Authentication']['DocumentType']) && $filter_response['Root']['Authentication']['DocumentType'] == 'Quotation' && $filter_response['Root']['Errors']['ErrorCode'] != '0';
            $condition_2 = isset($filter_response['Root']['Authentication']['DocumentType']) && $filter_response['Root']['Authentication']['DocumentType'] == 'Proposal' && !empty($filter_response['Root']['Errors']['ErrorCode']);

            if ($condition_1 || $condition_2) {
                return [
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'status' => false,
                    'message' => $filter_response['Root']['Errors']['ErrDescription']
                ];
            }

           update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "{$additionData['method']} Success", "Success" );

            if(!isset($filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value']))
            {
                return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => 'Error coming from service  =>' . $filter_response,
                    ];
            }


            if ($filter_response['Root']['Product']['Risks']['VehicleIDV']['@attributes']['Value'] == '')
                $idv =  '0';
            else
                $idv = $filter_response['Root']['Product']['Risks']['VehicleIDV']['@attributes']['Value'];

            $idv = $premium_type == 'third_party' ? 0 : $idv;
            $deviation = 0;

            if($bike_age >= 0 && $bike_age < 6)
            {
                $deviation = 0.1;
            }
            elseif($bike_age >=6 && $bike_age <=10)
            {
                $deviation = 0.05;
            }
            elseif($bike_age > 10 && $bike_age <=15)
            {
                $deviation = 0.05;
            }

            $min_idv = ceil($idv - ($idv*$deviation));
            $max_idv = floor($idv + ($idv*$deviation));
            
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
                #$idv = $min_idv;
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $idv = ($idv);
                        break;
                    case 'min_idv':
                       $idv = $min_idv;
                        break;
                    case 'max_idv':
                        $idv = $max_idv;
                        break;
                    default:
                       $idv = $min_idv;
                        break;
                }
            }
            // print_r($quote_array['Product']['Risks']['Risk']['RisksData']['CoverDetails']['Covers']['CoversData'][0]['SumInsured']['@attributes']['Value']); exit;
            $quote_array['Product']['Risks']['VehicleIDV']['@attributes']['Value'] = $idv;
            // for OD SumInsured
            $quote_array['Product']['Risks']['Risk']['RisksData']['CoverDetails']['Covers']['CoversData'][0]['SumInsured']['@attributes']['Value'] = $idv;
            // for TP SumInsured
            // print_r($quote_array); exit;
            $checksum_data = checksum_encrypt($quote_array);
            $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'universal_sompo', $checksum_data, 'BIKE');
            $additionData['checksum'] = $checksum_data;
            $idvChangedQuoteXmlRequest = ArrayToXml::convert($quote_array, 'Root');

            if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                $get_response = $is_data_exist_for_checksum;
            }else{
                $get_response = getWsData(config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_CAR'), $idvChangedQuoteXmlRequest, 'universal_sompo', $additionData);
            }

            $newresponse=$get_response['response'];
        }else{
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }

        if ($newresponse) {
            $newresponse = html_entity_decode($newresponse);
            $newresponse = XmlToArray::convert($newresponse);

            $filter_response = $newresponse['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];

            $condition_1 = isset($filter_response['Root']['Authentication']['DocumentType']) && $filter_response['Root']['Authentication']['DocumentType'] == 'Quotation' && $filter_response['Root']['Errors']['ErrorCode'] != '0';
            $condition_2 = isset($filter_response['Root']['Authentication']['DocumentType']) && $filter_response['Root']['Authentication']['DocumentType'] == 'Proposal' && !empty($filter_response['Root']['Errors']['ErrorCode']);

            if ($condition_1 || $condition_2) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => $filter_response['Root']['Errors']['ErrDescription']
                ];
            }

            /* if ($filter_response['Root']['Product']['Risks']['VehicleIDV']['@attributes']['Value'] == '')
                $idv =  '0';
            else
                $idv = $filter_response['Root']['Product']['Risks']['VehicleIDV']['@attributes']['Value'];

            $min_idv = ceil($idv * 0.95);
            $max_idv = floor($idv * 1.1); */

            $tppd_discount_amount = $detariff_loading_value = $detariff_loading_rate = 0;
            $discountData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['OtherDiscounts']['OtherDiscountGroup']['OtherDiscountGroupData'] ?? [];

            foreach ($discountData as $key => $discount) {
                if ($discount['Description']['@attributes']['Value'] == 'Automobile Association discount') {
                    $automobile_association_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Antitheft device discount') {
                    $anti_theft_device_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Handicap discount') {
                    $handicap_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'De-tariff discount') {
                    $detariff_discount_value = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'No claim bonus') {
                    $ncb_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'TPPD Discount') {
                    $tppd_discount_amount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Voluntary deductable') {
                    $voluntary_deductable_amount = $discount['Premium']['@attributes']['Value'];
                }
            }

            $total_premium_amount   = $filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value'];
            $discount_amount        = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Premium']['@attributes']['Value'];
            $discount_rate          = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Rate']['@attributes']['Value'];

            //loading
            $detariff_loading_rate          = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffLoadings']['De-tariffLoadingGroup']['De-tariffLoadingGroupData']['Rate']['@attributes']['Value'];
            $detariff_loading_value          = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffLoadings']['De-tariffLoadingGroup']['De-tariffLoadingGroupData']['Premium']['@attributes']['Value'];
            //end loading
            $ex_showroom_Price      = $filter_response['Root']['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'];

            $uw_loading_amount = $detariff_loading_value;
            $tp_premium = 0;
            $coversdata = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['CoverDetails']['Covers']['CoversData'];

            foreach ($coversdata as $key => $cover) {
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic OD') {
                    $od_premium = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic TP') {
                    $tp_premium = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'BUILTIN CNG KIT / LPG KIT OD') {
                    $builtin_lpg_cng_kit_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT OD') {
                    $lpg_cng_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT TP') {
                    $lpg_cng_tp_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'ELECTRICAL ACCESSORY OD') {
                    $electrical_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'FIBRE TANK - OD') {
                    $fibre_tank_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'NON ELECTRICAL ACCESSORY OD') {
                    $non_electrical_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Other OD') {
                    $other_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO OWNER DRIVER') {
                    $pa_owner = $cover['Premium']['@attributes']['Value'];
                }

                if ($cover['CoverGroups']['@attributes']['Value'] == 'UNNAMED PA COVER TO PASSENGERS') {
                    $pa_unnamed = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'LEGAL LIABILITY TO PAID DRIVER') {
                    $liability = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO PAID DRIVER') {
                    $pa_cover_driver = $cover['Premium']['@attributes']['Value'];
                }
            }

            $zero_dep_amount = 0;
            $rsa = 0;
            $key_replacement = 0;
            $eng_prot = 0;
            $consumable = 0;
            $tyre_secure = 0;
            $return_to_invoice = 0;
            $addonsData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['AddonCoverDetails']['AddonCovers']['AddonCoversData'];

            foreach ($addonsData as $key => $add_on_cover) {
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Road side Assistance') {
                    $rsa = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'COST OF CONSUMABLES') {
                    $consumable = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - DIESEL') {
                    $eng_prot_diesel = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - PETROL') {
                    $eng_prot_petrol = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'KEY REPLACEMENT') {
                    $key_replacement = $add_on_cover['Premium']['@attributes']['Value'];
                }

                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Nil Depreciation Waiver cover') {
                    $zero_dep_amount = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'RETURN TO INVOICE') {
                    $return_to_invoice = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'TYRE AND RIM SECURE') {
                    $tyre_secure = $add_on_cover['Premium']['@attributes']['Value'];
                }
            }

            if ($mmv_data->fuel_type == 'Petrol') {
                $eng_prot = $eng_prot_petrol;
            } elseif ($mmv_data->fuel_type == 'Diesel') {
                $eng_prot = $eng_prot_diesel;
            }

            $tp_premium = !empty($tp_premium) ? $tp_premium : 0;
            $total_od = (float) $od_premium + (float) $electrical_amount + (float) $non_electrical_amount + (float) $lpg_cng_amount;
            $total_tp = (float) $tp_premium + (float) $liability + (float) $pa_unnamed + (float) $lpg_cng_tp_amount + (float) $pa_cover_driver;
            $total_discount =  (float)($ncb_discount) + (float)($discount_amount) + (float)($anti_theft_device_discount) + (float)($voluntary_deductable_amount) + (float)($tppd_discount_amount);
            $basePremium = $total_od + $total_tp - $total_discount;

            $total_addons = (float) $rsa + (float) $consumable + (float) $eng_prot + (float) $key_replacement + (float) $zero_dep_amount + (float) $return_to_invoice + (float) $tyre_secure;

            $final_net_premium = $total_od + $total_tp - $total_discount + $total_addons;
            $final_tax = $final_net_premium * 0.18;
            $final_payable_amount = $final_net_premium + $final_tax;

            $applicable_addons = [
                'zeroDepreciation','engineProtector','consumables','returnToInvoice'
            ];//Key replacement and tyre secure is not applicable for bike according to kit

            if($zero_dep_amount == 0)
            {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }
            if($intval['y'] > 5)
            {
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                #array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                #array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
            }
           
            if($premium_type !== 'third_party')
            {
                $addons_data = [
                    // 'in_built'   => [
                    //     'roadSideAssistance' => 0
                    // ],
                    'additional' => [
                        // 'zeroDepreciation' => (int) $zero_dep_amount,
                        'keyReplace' => (int) $key_replacement,
                        'engineProtector' => (int) $eng_prot,
                        'consumables' => (int) $consumable,
                        'tyreSecure' => (int) $tyre_secure,
                        'returnToInvoice' => (int) $return_to_invoice
                    ]
                ];
                if ($masterProduct->product_identifier == 'zero_dep' && $zero_dep_amount > 0) {
                    $addons_data['in_built']['zeroDepreciation'] = (int) $zero_dep_amount;
                    // unset($addons_data['additional']['zeroDepreciation']);
                } else if ($zero_dep_amount > 0) {
                    $addons_data['additional']['zeroDepreciation'] = (int) $zero_dep_amount;
                }
            }else{
                $addons_data = [
                    'in_built'   => [],
                    'additional' => []
                ];
                $applicable_addons = [];
            }

            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;

            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : ($idv),
                    'vehicle_idv' => $idv,
                    'min_idv' => $min_idv,
                    'max_idv' => $max_idv,
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => $policy_type, //$premium_type == 'third_party' ? 'Third Party' : 'Comprehensive',
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $requestData->rto_code,
                    'voluntary_excess' => $requestData->voluntary_excess_value,
                    'version_id' => $mmv_data->ic_version_code,
                    'showroom_price' => ((int) $ex_showroom_Price),
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => [
                        'manf_name'             => $mmv_data->make,
                        'model_name'            => $mmv_data->model,
                        'version_name'          => '',//$mmv_data->variant, model has been displayed only once
                        'fuel_type'             => $mmv_data->fuel_type,
                        'seating_capacity'      => $mmv_data->seating_capacity,
                        'cubic_capacity'        => $mmv_data->cubic_capacity
                    ],
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => $policy_start_date,
                        'policy_end_date' =>   $policy_end_date,
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => '',
                        'company_name' => $productData->company_name,
                        'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'bike_age' => $bike_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => (int) $discount_amount
                    ],
                    'basic_premium' => (int) $od_premium,
                    'deduction_of_ncb' => (int) $ncb_discount,
                    'tppd_premium_amount' => (int) $tp_premium,
                    'tppd_discount' => $tppd_discount_amount,
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    'motor_electric_accessories_value' => (int) $electrical_amount,
                    'motor_non_electric_accessories_value' => (int) $non_electrical_amount,
                    'motor_lpg_cng_kit_value' => $lpg_cng_amount,
                    'cover_unnamed_passenger_value' => (int) $pa_unnamed,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'default_paid_driver' => (int) $liability,
                    'motor_additional_paid_driver' => (int) $pa_cover_driver,
                    'compulsory_pa_own_driver' => (int) $pa_owner,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => (int) $total_od,
                    'cng_lpg_tp' => (int) $lpg_cng_tp_amount,
                    'total_liability_premium' => (int) $total_tp,
                    'net_premium' => 0,
                    'service_tax_amount' => 0,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => (int) $total_addons,
                    'voluntary_excess' => (int) $voluntary_deductable_amount,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => (0),
                    'antitheft_discount' => ($anti_theft_device_discount),
                    'final_od_premium' => ($total_od),
                    'final_tp_premium' => ($total_tp),
                    'final_total_discount' => ($total_discount),
                    'final_net_premium' => ($final_net_premium),
                    'final_payable_amount' => ($final_payable_amount),
                    'service_data_responseerr_msg' => 'true',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => $policyType, //$requestData->policy_type,
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => str_replace('/', '-', $policy_start_date),
                    'policyEndDate' => str_replace('/', '-', $policy_end_date),
                    'ic_of' => $productData->company_id,
                    'ic_vehicle_discount' => $discount_amount,
                    'vehicle_in_90_days' => 0,
                    'get_policy_expiry_date' => NULL,
                    'underwriting_loading_amount' => $uw_loading_amount ?? 0,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => NULL,
                        'discount_rate' => $discount_rate,
                        'De_tariff_Loading' => $detariff_loading_value,
                        'De_tariff_loading_rate' => $detariff_loading_rate,
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    "max_addons_selection" => NULL,
                    'applicable_addons' => $applicable_addons,
                    'add_ons_data' => $addons_data,
                ]
            ];
            if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure  == '5')
            {
                $data_response['Data']['multi_Year_Cpa'] =  $pa_owner;
            }

            return camelCase($data_response);

        } else {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }
    } catch (\Exception $e) {
        return [
            'status' => false,
            'message' => $e->getMessage() . 'at Line no. -> ' . $e->getLine()
        ];
    }
}
function get_usgi_applicable_addons($age,$vehicle_make)
{

    
    /*switch ($vehicle_make) 
    {
        case 'honda':

            if($age <= 5)
            {
                $is_rti_applicable = 'True';
                $is_key_replacement_applicable = 'True';
                $is_engine_protector_applicable = 'True';
                $is_tyre_secure_applicable = 'True';
            }
            else
            {
                $is_rti_applicable = 'False';
                $is_key_replacement_applicable = 'False';
                $is_engine_protector_applicable = 'False';
                $is_tyre_secure_applicable = 'False';
            }

            if($age <= 7)
            {
                $is_zero_dep_applicable = 'True';
            }
            else
            {
                $is_zero_dep_applicable = 'False';
            }
            $is_lob_applicable = 'False';
            $is_consumable_applicable = 'False';
            $is_ncb_protector_applicable = 'False';
            $is_rsa_applicable = 'False';
            break;
        
        case 'maruti':

            if($age <= 5)
            {
                $is_rti_applicable = 'True';
                #$is_engine_protector_applicable = 'True';
                $is_key_replacement_applicable = 'True';
            }
            else
            {
                $is_rti_applicable = 'False';
                #$is_engine_protector_applicable = 'False';
                $is_key_replacement_applicable = 'False';
            }
            if($age <= 7)
            {
                 $is_engine_protector_applicable = 'True';
            }
            else
            {
                $is_engine_protector_applicable = 'False';
            }
            if($age <= 10)
            {
                $is_rsa_applicable = 'True';
                $is_consumable_applicable = 'True';
                $is_zero_dep_applicable = 'True';
            }
            else
            {
                $is_rsa_applicable = 'False';
                $is_consumable_applicable = 'False';
                $is_zero_dep_applicable = 'False';
            }
            $is_lob_applicable = 'False';
            $is_ncb_protector_applicable = 'False';
            $is_tyre_secure_applicable = 'False';
            

            break;

        case 'tata':
            
            if($age <= 5)
            {
                $is_rti_applicable = 'True';
            }
            else
            {
                $is_rti_applicable = 'False';
            }
            if($age <= 7)
            {
               $is_engine_protector_applicable = 'True';
               $is_key_replacement_applicable = 'True';
               $is_consumable_applicable = 'True';
               $is_ncb_protector_applicable = 'True';
               $is_zero_dep_applicable = 'True';
            }
            else
            {
               $is_engine_protector_applicable = 'False';
               $is_key_replacement_applicable = 'False';
               $is_consumable_applicable = 'False';
               $is_ncb_protector_applicable = 'False';
               $is_zero_dep_applicable = 'False';
            }

            if($age <= 10)
            {
                $is_rsa_applicable = 'True';
            }
            else
            {
                $is_rsa_applicable = 'False';
            }
            $is_lob_applicable = 'False';
            $is_tyre_secure_applicable = 'False';
            break;

        case 'make_other_than_oem':

            if($age <= 5)
            {
                $is_rti_applicable = 'True';
                $is_key_replacement_applicable = 'True';
                $is_tyre_secure_applicable = 'True';
            }
            else
            {
                $is_rti_applicable = 'False';
                $is_key_replacement_applicable = 'False';
                $is_tyre_secure_applicable = 'False';
            }
            if($age <= 7)
            {
               $is_lob_applicable = 'True';
               $is_engine_protector_applicable = 'True';
               $is_consumable_applicable = 'True';
               $is_ncb_protector_applicable = 'True';
               $is_zero_dep_applicable = 'True';
            }
            else
            {
               $is_lob_applicable = 'False';
               $is_engine_protector_applicable = 'False';
               $is_consumable_applicable = 'False';
               $is_ncb_protector_applicable = 'False';
               $is_zero_dep_applicable = 'False';
            }

            if($age <= 10)
            {
                $is_rsa_applicable = 'True';
            }
            else
            {
                $is_rsa_applicable = 'False';
            }
            break;

        default:
            $is_zero_dep_applicable = $is_key_replacement_applicable = $is_engine_protector_applicable = $is_consumable_applicable =  $is_tyre_secure_applicable = $is_rti_applicable = $is_lob_applicable =
            $is_ncb_protector_applicable = $is_rsa_applicable = 'False';


            break;
    }*/

    if ($age <= 1) 
    {
        $age_range = '0-1';
    } 
    elseif ($age > 1 && $age <= 2) 
    {
        $age_range = '1-2';
    } 
    elseif ($age > 2 && $age <= 3) 
    {
        $age_range = '2-3';
    }
    elseif ($age > 3 && $age <= 4) 
    {
        $age_range = '3-4';
    } 
    elseif ($age > 4 && $age <= 5) 
    {
        $age_range = '4-5';
    } 
    elseif ($age > 5 && $age <= 6) 
    {
        $age_range = '5-6';
    } 
    elseif ($age > 6 && $age <= 7) 
    {
        $age_range = '6-7';
    } 
    elseif ($age > 7 && $age <= 10) 
    {
        $age_range = '7-10';
    } 
    elseif ($age > 10 && $age <= 15) 
    {
        $age_range = '10-15';
    }
    $is_zero_dep_applicable  = $is_engine_protector_applicable = $is_consumable_applicable = $is_rti_applicable = $is_rsa_applicable  = 'False';
    if($age <=15)
    {
        $addon_data = UniversalSompoBikeAddonConfiguration::
        select('zero_dep', 'engine_protector', 'return_to_invoice', 'road_side_assistance', 'consumable')
        ->where('vehicle_make', $vehicle_make)
        ->where('age_range', $age_range)
        ->get()
        ->toArray();
        if(!empty($addon_data))
        {
            $addon_data = $addon_data[0];
            $is_zero_dep_applicable = $addon_data['zero_dep'];
            $is_engine_protector_applicable = $addon_data['engine_protector'];
            $is_consumable_applicable = $addon_data['consumable'];
            $is_rti_applicable = $addon_data['return_to_invoice'];
            $is_rsa_applicable = $addon_data['road_side_assistance'];
        }
    }
    else 
    {
        $is_zero_dep_applicable  = $is_engine_protector_applicable = $is_consumable_applicable = $is_rti_applicable = $is_rsa_applicable  = 'False';
    }
     
    $usgi_applicable_addons =
    [
        'zero_dep' => $is_zero_dep_applicable,
        'engine_protector' => $is_engine_protector_applicable,
        'consumable' => $is_consumable_applicable,
        'return_to_invoice' => $is_rti_applicable,
        'rsa' => $is_rsa_applicable
    ];

    return $usgi_applicable_addons;
}
