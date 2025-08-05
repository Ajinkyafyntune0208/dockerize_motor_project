<?php

use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Carbon\Carbon;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/universal_sompo_Miscd.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $is_MISC = policyProductType($productData->policy_id)->parent_id;
    if($is_MISC == 3){
        return getMiscQuote($enquiryId, $requestData, $productData);
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
    //Removing age validation for all iC's  #31637 
    
    // if ($requestData->previous_policy_type == 'Not sure')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not available at insurer if previous policy details are not sure'
    //     ];
    // }


    $mmv = get_mmv_details($productData, $requestData->version_id, $productData->company_alias);
   
     /*$mmv = [
            'status' => '1',
            'data' => 
            [
                //ic master labels
                'VEHICLECLASSCODE' => '24',
                'MANUFACTURER' => 'HONDA',
                'VEHICLEMODELCODE' => '50629',
                'VEHICLEMODEL' => 'CITY 1.5 EXI CITY 1.5 EXI',
                'NUMBEROFWHEELS' => '4',
                'CUBICCAPACITY' => '1493',
                'GROSSVEHICLEWEIGHT' => '14393',
                'SEATINGCAPACITY' => '5',
                'CARRYINGCAPACITY' => '5',
                'BODYTYPECODE'=>'30',
                'VEHICLEMODELSTATUS' => 'Active',
                'TXT_OBSOLETE_FLAG' => '0',
                'NUM_VEHICLE_SUBCLASS_CODE' => '1',
                'CNVM_FUEL_TYPE'=> 'PETROL',
                 //our master lables
                'ic_version_code' => '64833',
                'usgi_make_code' => '113',
                'no_of_wheels' => '4',
                'fyntune_version' => [
                    'id' => '1296',
                    'version_id' => 'CRPFYN2906211299',
                    'sequence_id' => '1299',
                    'model_id' => '164',
                    'version_name' => '1.3 LXI',
                    'cubic_capacity' => '1343',
                    'is_discontinued' => '0',
                    'discontinued_reason' => '0',
                    'fuel_type' => 'PETROL',
                    'seating_capacity' => '5',
                    'is_active' => 'Y',
                    'mmv_apollo_munich' => 'null',
                    'mmv_coco' => '43965',
                    'mmv_acko' => '10326',
                    'mmv_raheja' => 'RHQC1199',
                    'mmv_edelweiss_tokio' => 'null',
                    'mmv_edelweiss' => 'HOND1321',
                    'mmv_universal_sompo' => '13189',
                    'mmv_united_india' => 'IBB116016003',
                    'mmv_aviva_life' => 'null',
                    'mmv_bharthi_axa_life' => 'null',
                    'mmv_fastlane_car' => '1140105050198012',
                    'version_priority' => '0'
                ]]]; */    

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ]
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv_data,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv_data,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    }

    $premium_type = MasterPremiumType::where('id',$productData->premium_type_id)->pluck('premium_type_code')->first();

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
     if($requestData->business_type == 'newbusiness')
            {
                $premium_type = 'Comprehensive';
            }
    
    // car age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = $age / 12;
    $car_age = round($car_age,2);



    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();

   
    // if(strtolower($mmv_data->make) != 'honda' && strtolower($mmv_data->make) != 'tata' && strtolower($mmv_data->make) != 'maruti')
    // {
    //     $vehicle_make_name = 'make_other_than_oem';
    // }
    // else
    // {
    //     $vehicle_make_name = strtolower($mmv_data->make);
    // }
    $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
    $vehicle_make_name = strtolower($mmv_data->manufacturer);
    $usgi_applicable_addons = get_usgi_applicable_addons($car_age,$vehicle_make_name,$interval,$is_GCV);

   
    if ($usgi_applicable_addons['zero_dep'] == 'False' && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('CV_AGE_VALIDASTRION_ALLOWED_IC')))) 
    {
        return [
            'premium_amount' => 0,
            'status' => true,
            'message' => 'Zero dep is not allowed for selected vehicle make and age combination',
            'request' => [
                'message' => 'Zero dep is not allowed for selected vehicle make and age combination',
                'vehicle_age' => $car_age
            ]
        ];
    }
    

    // RTO validation for DL
    $rto_code_check = explode('-', $requestData->rto_code);
    if (isset($rto_code_check) && $rto_code_check[0] == 'DL' && $rto_code_check[1] < 10) {
        $rto_code = $rto_code_check[0] . '0' . $rto_code_check[1];
    } else {
        $rto_code = $rto_code_check[0] . $rto_code_check[1];
    }

    $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
    $rto_payload = keysToLower($rto_payload);

    if (!$rto_payload) {
        return [
            'status' => false,
            'message' => 'Premium is not available for this RTO - ' . $rto_code,
            'request' => [
                'rto_no' => $rto_code,
                'message' => 'Premium is not available for this RTO - ' . $rto_code,
            ]
        ];
    }

    // default values
    $PolicyStatus = 'Unexpired';
    $covers_payload_zero = 'True';
    $covers_payload_first = 'True';
    $electrical_accessories_flag = 'True';
    $discount_flag_in_tp = 'True';
    $ncb_declaration = 'False';

   
    



    //FOR CALLIND COMPREHENSIVE PRODUCT FIRST TO GET EX-SHOWROOM PRICE
    $document_type = 'Quotation';
    $vehical_class_code = '';
    switch(get_parent_code($productData->product_sub_type_id))
    {
        case 'GCV':

        if($premium_type == 'comprehensive')
        {
          $productname = 'GOODS CARRYING VEHICLE';
          $product_code = '2315';
        }
        elseif($premium_type == 'third_party')
        {
          $productname = 'GOODS CARRYING LIABILITY POLICY';
          $product_code = '2317';
        }
        $vehical_class_code = '24';
        break;

        case 'PCV':
            if ($premium_type == 'comprehensive') {
                $productname = 'PASSENGER CARRYING VEHICLE';
                $product_code = '2314';
            } elseif ($premium_type == 'third_party') {
                $productname = 'PASSENGER CARRYING LIABILITY POLICY';
                $product_code = '2313';
            }
            if(in_array($mmv_data->num_vehicle_subclass_code, ['1','2','3','4','5','6','7','8','9']))
            {
                $vehical_class_code = '41';
            }
        break;

    }

    
    $vehicleBodyColor = 'Black';
    $policy_type = 'Comprehensive';
    $request_ex_showroom_Price = '';

    switch ($requestData->business_type) 
    {

        case 'rollover':
            $policyType = 'Roll Over';
            $business_type = 'Rollover';
            $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $RegistrationNumber = $requestData->rto_code . '-GA-8819';
            $veh_type = 'Rollover';
            $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
            $previousPolicyExpiryDate = Carbon::createFromDate($requestData->previous_policy_expiry_date);
            $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
            if ($date_diff_in_prev_policy > 90) 
            {
                $motor_expired_more_than_90_days = 'Y';
            }
            else
            {
                $motor_expired_more_than_90_days = 'N';
            }


            if ($requestData->is_claim == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party') 
            {

                $ncb_declaration = 'True';
                $motor_applicable_ncb = ((int) $requestData->applicable_ncb) / 100;

            }
            else
            {
                $ncb_declaration = 'False';
                $motor_applicable_ncb = 0;
            }

            if($requestData->previous_policy_type == 'Third-party')
            {
                $ncb_declaration = 'False';
                $motor_applicable_ncb = 0;
            }
            break;

        case 'newbusiness':
            $policyType = 'New Business';
            $business_type = 'New Vehicle';
            $policy_start_date = date('d/m/Y');
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $RegistrationNumber = 'New';
            $veh_type = 'New';
            break;

        case 'breakin':
            $policyType = 'Breakin';
            $business_type = 'Rollover';
            $policy_start_date = date('d/m/Y', strtotime("+2 day"));
            $PolicyStatus = $premium_type !== 'third_party' ? 'Expired' : 'Unexpired';
            $RegistrationNumber = $requestData->rto_code . '-GA-8819';
            $veh_type = '';
            $vehicleBodyColor = '';
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
            $previousPolicyExpiryDate = Carbon::createFromDate($requestData->previous_policy_expiry_date);
            $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
            if ($date_diff_in_prev_policy > 90) 
            {
                $motor_expired_more_than_90_days = 'Y';
            }
            else
            {
                $motor_expired_more_than_90_days = 'N';
            }


            if ($requestData->is_claim == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party') 
            {

                $ncb_declaration = 'True';
                $motor_applicable_ncb = ((int) $requestData->applicable_ncb) / 100;

            }
            else
            {
                $ncb_declaration = 'False';
                $motor_applicable_ncb = 0;
            }

            if($requestData->previous_policy_type == 'Third-party')
            {
                $ncb_declaration = 'False';
                $motor_applicable_ncb = 0;
            }
            
            
            break;

    }

    try {
        
        $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
        
        $cpa = 'True';
        $elect_accessories = 'False';
        $elect_accessories_sumInsured = 0;
        $non_elect_accessories = 'False';
        $non_elect_accessories_sumInsured = 0;
        $external_lpg_cng_accessories = 'False';
        $external_lpg_cng_accessories_sumInsured = "";
        $pa_add_paid_driver_cover = 'False';
        $pa_add_paid_driver_cover_sumInsured = 0;
        $unnamed_psenger_cover = 'False';
        $unnamed_psenger_cover_sumInsured = 0;
        $llpd = 'False';
        $anti_theft_device = 'False';
        $voluntry_discount = 'False';
        $voluntry_discount_sumInsured = 0;
        $tppd_cover_flag = 'False';
        $no_of_driverLL = 0;
        $no_of_cleanerLL = 0;

        $addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $electrical_accessories_flag = 'True';
        $discount_flag_in_tp = 'True';

        if ($requestData->fuel_type == "CNG" || $requestData->fuel_type == "LPG") 
        {
            $external_lpg_cng_accessories = 'True';
        }

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

                    if ($additional_covers['name'] == 'LL paid driver/conductor/cleaner' && isset($additional_covers['LLNumberCleaner']) && $additional_covers['LLNumberCleaner'] > 0) {
                        $llpd = 'True';
                        $no_of_cleanerLL = $additional_covers['LLNumberCleaner'];
                    }

                    if ($additional_covers['name'] == 'LL paid driver/conductor/cleaner' && isset($additional_covers['LLNumberDriver']) && $additional_covers['LLNumberDriver'] > 0) {
                        $llpd = 'True';
                        $no_of_driverLL = $additional_covers['LLNumberDriver'];
                    }

                    if ($additional_covers['name'] == 'PA paid driver/conductor/cleaner' && isset($additional_covers['sumInsured'])) {
                        $pa_add_paid_driver_cover = 'True';
                        $pa_add_paid_driver_cover_sumInsured = $additional_covers['sumInsured'];
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
        
        switch ($mmv_data->cnvm_fuel_type) {

            case 'Petrol':
                $engine_protector_cover_diesel = 'False';
                $engine_protector_cover_petrol = $usgi_applicable_addons['engine_protector'];
                break;

            case 'Diesel':
                $engine_protector_cover_diesel = $usgi_applicable_addons['engine_protector'];
                $engine_protector_cover_petrol = 'False';
                break;

            default:
                $engine_protector_cover_diesel = 'False';
                $engine_protector_cover_petrol = 'False';
                break;
        }

        # Zero dep product 
        $enable_zero_dep = 'False';
        $consumable_flag = 'False';
        
        if ($masterProduct->product_identifier == 'zero_dep' && $usgi_applicable_addons['zero_dep'] == 'True') 
        {
            $enable_zero_dep = 'True';
        }
        
        

        
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_TESTING_UNIVERSAL');
        $pos_name = $pos_pan = $pos_aadhar_no = $pos_email = $pos_mobile_no = '';
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

        }
        else if($is_pos_enabled_testing == 'Y')
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
                            'Value' => '2315'
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
                            'Value' => ($requestData->business_type == 'newbusiness') ? '' : $PreviousPolExpDt
                        ],
                    ],
                    'PolicyEffectiveFrom' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Policy Effective From',
                            'Value' => ($requestData->business_type == 'newbusiness') ? '' : $previous_policy_start_date
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
                            'Value' => ($requestData->business_type == 'newbusiness') ? 'Unexpired' : $PolicyStatus
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
                        'Value' =>  ($premium_type == 'third_party') ?'False':'True',
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
                        'Value' => ($premium_type == 'own_damage') ?'False':'True',
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $elect_accessories
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $non_elect_accessories
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
                        'Value' => ($premium_type == 'own_damage') ? 'False' : $external_lpg_cng_accessories, //($car_data['premium_type'] == 'O') ? 'False' : $lpg_cng_od
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
                        'Value' => $premium_type == 'own_damage' ? 'False' : $cpa
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
            '13' => [
                'Premium' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Premium',
                        'Value' => '0'
                    ],
                ],
                'Rate' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Rate',
                        'Value' => '0'
                    ],
                ],
                'SumInsured' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'SumInsured',
                        'Value' => '100'
                    ],
                ],
                'Applicable' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Applicable',
                        'Value' => 'True'
                    ],
                ],
                'CoverGroups' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'CoverGroups',
                        'Value' => 'INCLUSION OF IMT23'
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
                        'Value' => $enable_zero_dep, //$nil_depreciation
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $usgi_applicable_addons['key_replacement'],
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
                        'Value' =>( $premium_type == 'third_party') ? 'False' : $usgi_applicable_addons['return_to_invoice'],
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
                        'Value' => $premium_type == 'third_party' ? 'False' : $usgi_applicable_addons['rsa'],
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $usgi_applicable_addons['consumable'],
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $engine_protector_cover_petrol
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $engine_protector_cover_diesel,
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $usgi_applicable_addons['tyre_secure'],
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $anti_theft_device, //$anti_theft
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
                        'Value' =>($requestData->business_type == 'newbusiness') ? 0 :  $motor_applicable_ncb
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
                        'Value' => ($premium_type == 'third_party') ? 'False' : $ncb_declaration
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
                'WACode' =>  config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_CV'),
                'WAAppCode' => config('constants.IcConstants.universal_sompo.AUTH_APPCODE_SOMPO_CV'), //AUTH_APPCODE_SOMPO_MOTOR,
                'WAUserID' => config('constants.IcConstants.universal_sompo.WEB_USER_ID_SOMPO_CV'),   //WEB_USER_ID_SOMPO_MOTOR,
                'WAUserPwd' => config('constants.IcConstants.universal_sompo.WEB_USER_PASSWORD_SOMPO_CV'), // WEB_USER_PASSWORD_SOMPO_MOTOR,
                'WAType'  => '0',
                'DocumentType' => $document_type,
                'Versionid' => '1.1',
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
                                        'Name' => 'IMDBranchName',
                                        'Value' => ''
                                    ],
                                ],
                                'IMDBranchCode' => [
                                    '@value' => '',
                                    '@attributes' => [
                                        'Name' => 'IMDBranch Code',
                                        'Value' => ''
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
                                                    'Value' => 'True'
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
                                                    'Value' => 'True'
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
                            'Value' => $vehical_class_code,//'24', //$mmv_data['USGI_CLASS_CODE']
                        ],
                    ],
                    'VehicleMakeCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleMakeCode',
                            'Value' => $mmv_data->usgi_make_code
                        ],
                    ],
                    'VehicleModelCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleModelCode',
                            'Value' => $mmv_data->vehiclemodelcode,//$mmv_data->model_code
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
                            'Value' => $mmv_data->bodytypecode,//$mmv_data->body_type_code
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
                            'Value' => $mmv_data->grossvehicleweight
                        ],
                    ],
                    'CarryingCapacity' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CarryingCapacity',
                            'Value' => $mmv_data->carryingcapacity
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
                            'Value' => $mmv_data->vehiclemodel,//$mmv_data->model . ' ' . $mmv_data->variant
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
                            'Value' =>  ($requestData->business_type == 'breakin' && $premium_type != 'third_party') ? 'Black' : '',
                        ],
                    ],
                    'FuelType' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Fuel Type',
                            'Value' => $mmv_data->cnvm_fuel_type,//$mmv_data->fuel_type ///'Petrol'
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
                            'Value' => $no_of_driverLL
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
                            'Value' => 'Private Car'
                        ],

                    ],

                    'VehicleMake' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'VehicleMake',
                            'Value' => $mmv_data->manufacturer,//$mmv_data->make
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
                            'Value' => $mmv_data->numberofwheels
                        ],

                    ],

                    'CubicCapacity' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'CubicCapacity',
                            'Value' => $mmv_data->cubiccapacity,//$mmv_data->cubic_capacity
                        ],

                    ],

                    'SeatingCapacity' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'SeatingCapacity',
                            'Value' => $mmv_data->seatingcapacity,//$mmv_data->seating_capacity
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
                            'Value' => $car_age
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
                    'VehicleSubClassCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'VehicleSubClassCode',
                            'Value' => $mmv_data->num_vehicle_subclass_code
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

        /*$document_type = 'Quotation';
        $policy_type = 'Comprehensive';
        if($requestData->business_type == 'newbusiness')
        {
            $productname = 'MOTOR - MOTOR PRIVATE CAR - BUNDLED';
            $product_code = '2367';
            // $document_type = 'Quotation';
        }
        else
        {
            // $document_type = 'Quotation';
            $productname = 'Private Car Package Policy';
            $product_code = '2311';

            if(in_array($premium_type, ['own_damage', 'own_damage_breakin']))
            {
                // $document_type = 'Proposal';
                $productname = 'Private Car - OD';
                $product_code = '2398';
                $policy_type = 'Own Damage';
            }
            else if(in_array($premium_type, ['third_party', 'third_party_breakin']))
            {
                // $document_type = 'Proposal';
                $productname = 'PRIVATE CAR LIABILITY POLICY';
                $product_code = '2319';
                $policy_type = 'Third Party';
            }
        }

        $quote_array['Product']['@attributes']['Name'] = $productname;
        $quote_array['Customer']['ProductName'] = $productname;
        $quote_array['Customer']['ProductCode'] = $product_code;
        $quote_array['Authentication']['DocumentType'] = $document_type;*/

        $xmlQuoteRequest = ArrayToXml::convert($quote_array, 'Root');
        
        $additionData = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'commBRIDGEFusionMOTOR',
            'container'   => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header /><soapenv:Body><tem:commBRIDGEFusionMOTOR><tem:strXdoc>#replace</tem:strXdoc></tem:commBRIDGEFusionMOTOR></soapenv:Body></soapenv:Envelope>',
            'method' => 'Quote Generation-1',
            'section' => $productData->product_sub_type_code,
            'productName'   => $productData->product_name,
            'transaction_type' => 'quote',
        ];
        
        $get_response = getWsData(config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_CV'), $xmlQuoteRequest, 'universal_sompo', $additionData);
        $comprehensive_product_response = $get_response['response'];
        $total_addons = $rsa = $consumable = $eng_prot = $key_replacement = $zero_dep_amount = $return_to_invoice = $tyre_secure = 0;
        $total_od = $od_premium = $electrical_amount = $non_electrical_amount = $lpg_cng_amount = 0;
        $total_tp = $tp_premium = $liability = $pa_unnamed = $lpg_cng_tp_amount = $pa_cover_driver = 0;
        $total_discount = $ncb_discount = $discount_amount = $anti_theft_device_discount = $voluntary_deductable_amount = $tppd_discount_amount =0;
        $imt23_amount = 0;
        $geog_Extension_OD_Premium = 0;
        $geog_Extension_TP_Premium = 0;
        $ex_showroom_Price = 0;
        
       /* if($comprehensive_product_response)
        {
            $response = html_entity_decode($comprehensive_product_response);
            $response = XmlToArray::convert($response);
          
           
            $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];
            if(!isset($filter_response['Root']))
            {
                return [
                    'status'=> false,
                    'message'=> $filter_response 
                ];
            }

            $condition_1 = $filter_response['Root']['Authentication']['DocumentType'] == 'Quotation' && $filter_response['Root']['Errors']['ErrorCode'] != '0';

            if ($condition_1) {
                return [
                    'status' => false,
                    'message' => $filter_response['Root']['Errors']['ErrDescription']
                ];
            }
            if($premium_type != 'third_party')
            {
                $ex_showroom_Price = (int) $filter_response['Root']['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'];

                $quote_array['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'] = $ex_showroom_Price;
                $xmlQuoteRequest = ArrayToXml::convert($quote_array, 'Root');
               //print_r($xmlQuoteRequest);die;
                
                $additionData = [
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'soap_action' => 'commBRIDGEFusionMOTOR',
                'container'   => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header /><soapenv:Body><tem:commBRIDGEFusionMOTOR><tem:strXdoc>#replace</tem:strXdoc></tem:commBRIDGEFusionMOTOR></soapenv:Body></soapenv:Envelope>',
                'method' => 'Quote Generation-2',
                'section' => $productData->product_sub_type_code,
                'productName'   => $productData->product_name,
                'transaction_type' => 'quote',
                ];
                $response2 = getWsData(config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_CV'), $xmlQuoteRequest, 'universal_sompo', $additionData);
                if($response2)
                {
                    $response = html_entity_decode($response2);
                  
                    $response = XmlToArray::convert($response);
                    
                    
                    $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];
                    if(!isset($filter_response['Root']))
                    {
                        return [
                            'status'=> false,
                            'message'=> $filter_response 
                        ];
                    }

                    $condition_1 = $filter_response['Root']['Authentication']['DocumentType'] == 'Quotation' && $filter_response['Root']['Errors']['ErrorCode'] != '0';

                    if ($condition_1) {
                        return [
                            'status' => false,
                            'message' => $filter_response['Root']['Errors']['ErrDescription']
                        ];
                    }
                }
                else
                {
                    return [
                    'status' => false,
                    'message' => 'Insurer not reachable2'
                      ];
                }
            }
            else
            {
               $response2 = $comprehensive_product_response;
            }

           
            
        }
        else
        {
            return [
                'status' => false,
                'message' => 'Insurer not reachable1'
            ];
        } */

        if ($comprehensive_product_response) 
        {
            $response = html_entity_decode($comprehensive_product_response);
            $response = XmlToArray::convert($response);
            
            $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];
            if(!isset($filter_response['Root']))
            {
                return [
                    'status'=> false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message'=> $filter_response 
                ];
            }

            $condition_1 = $filter_response['Root']['Authentication']['DocumentType'] == 'Quotation' && $filter_response['Root']['Errors']['ErrorCode'] != '0';
            if ($condition_1) 
            {
                return [
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => $filter_response['Root']['Errors']['ErrDescription']
                ];
            }

            if(!isset($filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value']))
            {
                return [
                        'status' => false,
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'message' => 'Error coming from service  =>' . $filter_response,
                    ];
            }

            if ($filter_response['Root']['Product']['Risks']['VehicleIDV']['@attributes']['Value'] == '')
                $idv =  '0';
            else
                $idv = $filter_response['Root']['Product']['Risks']['VehicleIDV']['@attributes']['Value'];


            $idv = $premium_type == 'third_party' ? 0 : (int)$idv;
            $ex_showroom_Price = (int) $filter_response['Root']['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'];

            $quote_array['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'] = $ex_showroom_Price;

            $deviation = 0;

            
            if($car_age >= 0 && $car_age < 6)
            {
                $deviation = 0.1;
            }
            elseif($car_age >=6 && $car_age <=10)
            {
                $deviation = 0.05;
            }
            elseif($car_age > 10 && $car_age <=15)
            {
                $deviation = 0.05;
            }

            
            $min_idv = ceil($idv - ($idv*$deviation));
            $max_idv = floor($idv + ($idv*$deviation));

            // idv change condition
            if ($requestData->is_idv_changed == 'Y') 
            {
                if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                    $idv = floor($max_idv);
                } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                    $idv = ceil($min_idv);
                } else {
                    $idv = $requestData->edit_idv;
                }
            }
            else
            {
                $newresponse = $comprehensive_product_response;
                /* $idv = $min_idv; */
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $idv = $idv;
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
        }
        else{
            return [
                'status' => false,
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'message' => 'Insurer not reachable - basic service'
            ];
        }
        $quote_array['Product']['Risks']['VehicleIDV']['@attributes']['Value'] = $idv;

        $idvChangedQuoteXmlRequest = ArrayToXml::convert($quote_array, 'Root');
        
        $additionData['method'] = 'Quote Generation - IDV change';

        $get_response = getWsData(config('constants.IcConstants.universal_sompo.END_POINT_URL_UNIVERSAL_SOMPO_CAR'), $idvChangedQuoteXmlRequest, 'universal_sompo', $additionData);

        $newresponse = $get_response['response'];
        if ($newresponse) 
        {

            $newresponse = html_entity_decode($newresponse);
            //print_r($newresponse);
            //die;
            $newresponse = XmlToArray::convert($newresponse);


            $filter_response = $newresponse['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];

            $condition_1 = $filter_response['Root']['Authentication']['DocumentType'] == 'Quotation' && $filter_response['Root']['Errors']['ErrorCode'] != '0';

            if ($condition_1) {
                return [
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => $filter_response['Root']['Errors']['ErrDescription']
                ];
            }

            if(!isset($filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value']))
            {
                return [
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => 'Error coming from service  =>' . $filter_response,
                ];
            }

            $tppd_discount_amount = 0;
            $discountData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['OtherDiscounts']['OtherDiscountGroup']['OtherDiscountGroupData'];

            foreach ($discountData as $key => $discount) {
                if ($discount['Description']['@attributes']['Value'] == 'Automobile Association discount') {
                    $automobile_association_discount = (int)$discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Antitheft device discount') {
                    $anti_theft_device_discount = (int)$discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Handicap discount') {
                    $handicap_discount = (int)$discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'De-tariff discount') {
                    $detariff_discount_value = (int)$discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'No claim bonus') {
                    $ncb_discount = (int)$discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'TPPD Discount') {
                    $tppd_discount_amount = (int)$discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Voluntary deductable') {
                    $voluntary_deductable_amount = (int)$discount['Premium']['@attributes']['Value'];
                }
            }

            //print_r($filter_response);die;
            $total_premium_amount   = (int)$filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value'];
            $discount_amount        = (int)$filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Premium']['@attributes']['Value'];
            $discount_rate          = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Rate']['@attributes']['Value'];
            $ex_showroom_Price      = (int)$filter_response['Root']['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'];
            
            $tp_premium = 0;
            $coversdata = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['CoverDetails']['Covers']['CoversData'];
            
            foreach ($coversdata as $key => $cover) {
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic OD') {
                    $od_premium = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic TP') {
                    $tp_premium =(int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'BUILTIN CNG KIT / LPG KIT OD') {
                    $builtin_lpg_cng_kit_od = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT OD') {
                    $lpg_cng_amount = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT TP') {
                    $lpg_cng_tp_amount = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'ELECTRICAL ACCESSORY OD') {
                    $electrical_amount = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'FIBRE TANK - OD') {
                    $fibre_tank_od = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'NON ELECTRICAL ACCESSORY OD') {
                    $non_electrical_amount = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Other OD') {
                    $other_od = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO OWNER DRIVER') {
                    $pa_owner = (int)$cover['Premium']['@attributes']['Value'];
                }

                if ($cover['CoverGroups']['@attributes']['Value'] == 'UNNAMED PA COVER TO PASSENGERS') {
                    $pa_unnamed = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'LEGAL LIABILITY TO PAID DRIVER') {
                    $liability = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO PAID DRIVER') {
                    $pa_cover_driver = (int)$cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'INCLUSION OF IMT23') {
                    $imt23_amount = (int)$cover['Premium']['@attributes']['Value'];
                }
            }
           
            $addonsData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['AddonCoverDetails']['AddonCovers']['AddonCoversData'];

            foreach ($addonsData as $key => $add_on_cover) {
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Road side Assistance') {
                    $rsa = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'COST OF CONSUMABLES') {
                    $consumable = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - DIESEL') {
                    $eng_prot_diesel = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - PETROL') {
                    $eng_prot_petrol = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'KEY REPLACEMENT') {
                    $key_replacement = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Nil Depreciation Waiver cover') {
                    $zero_dep_amount = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'RETURN TO INVOICE') {
                    $return_to_invoice = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'TYRE AND RIM SECURE') {
                    $tyre_secure = (int)$add_on_cover['Premium']['@attributes']['Value'];
                }
            }

            if ($mmv_data->cnvm_fuel_type == 'Petrol') {
                $eng_prot = $eng_prot_petrol;
            } elseif ($mmv_data->cnvm_fuel_type == 'Diesel') {
                $eng_prot = $eng_prot_diesel;
            }
           
            
            $tp_premium = !empty($tp_premium) ? $tp_premium : 0;
            $discount_amount = get_value_without_imt($discount_amount);
            $ncb_discount = get_value_without_imt($ncb_discount);
            $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
            $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $pa_cover_driver;
            $total_discount = $ncb_discount + $discount_amount + $anti_theft_device_discount + $voluntary_deductable_amount + $tppd_discount_amount;
            $basePremium = $total_od + $total_tp - $total_discount;
            $total_addons = $rsa + $consumable + $eng_prot + $key_replacement + $zero_dep_amount + $return_to_invoice + $tyre_secure + $imt23_amount;
            $final_net_premium = $total_od + $total_tp - $total_discount + $total_addons;
            $final_tax = ($total_od + $total_addons - ($ncb_discount + $discount_amount + $anti_theft_device_discount + $voluntary_deductable_amount) + $liability + $lpg_cng_tp_amount + $pa_cover_driver + $pa_owner) * 0.18 + ($tp_premium*0.12 - $tppd_discount_amount*0.18);
           
        //$final_tax = ($total_od + $total_addons - ($ncb_discount + $discount_amount + $anti_theft_device_discount + $voluntary_deductable_amount) + $liability + $lpg_cng_tp_amount + $pa_cover_driver+ $pa_owner) * 0.18 + ($tp_premium - $tppd_discount_amount) * 0.12;
            // echo $final_tax;
            // echo "<pre>";
            // die;
            //$final_tax = round(($tp_premium - $tppd_discount_amount * 0.12) + (($final_net_premium - $tp_premium) * 0.18));
            //echo $final_tax;die;
            /*if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $final_tax = round(($tp_premium + $lpg_cng_tp_amount) * 0.12 + ($final_net_premium - $tp_premium - $lpg_cng_tp_amount) * 0.18);
            } else {
                $final_tax = round(($tp_premium - $tppd_discount_amount * 0.12) + (($final_net_premium - $tp_premium) * 0.18));
            }*/
            $final_payable_amount = $final_net_premium + $final_tax;

            $applicable_addons = [
                'zeroDepreciation', 'imt23'
            ];

            
            /*if($usgi_applicable_addons['rsa'] == 'False')
            {
                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
            }*/
            if ($imt23_amount == '0' || $imt23_amount == 0) 
            {
                array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
            }
            if($premium_type !== 'third_party')
            {
                if($productData->zero_dep == '0')
                {
                    $addons_data = [
                        'in_built'   => [
                            'zeroDepreciation' => (int) $zero_dep_amount,
                            'imt23' => (int) $imt23_amount,
                            'road_side_assistance' => ($interval->y < 10 ) ? $rsa : 0,
                            
                        ],
                        'additional' => [
                            //'road_side_assistance' => 0
                        ]
                    ];
                    if(empty($zero_dep_amount ?? 0)){
                        return [
                            'status' => false,
                            'message' => 'Zero dep is not available..!',
                            'request' => [
                                'message' => 'Zero dep is not available..!',
                                'vehicle_age' => $car_age,
                                'request' => $quote_array,
                                'response' => $newresponse,
                            ]
                        ];
                    }
                }
                else
                {
                    $addons_data = [
                        'in_built'   => [
                            'road_side_assistance' => ($interval->y < 10 ) ? $rsa : 0,
                        ],
                        'additional' => [
                            'imt23' => (int) $imt23_amount,    
                        ]
                    ];
                }
            }
            else
            {
                $addons_data = [
                    'in_built'   => [],
                    'additional' => []
                ];
               
            }
            
            if($interval->y >= 10){
                unset($addons_data['in_built']['road_side_assistance']);
            }

            $data_response = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'Data' => [
                    'idv' => $premium_type == 'third_party' ? 0 : round($idv),
                    'vehicle_idv' => $idv,
                    'min_idv' => $min_idv,
                    'max_idv' => $max_idv,
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' : 'Comprehensive',
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $requestData->rto_code,
                    'voluntary_excess' => $requestData->voluntary_excess_value,
                    'version_id' => $mmv_data->ic_version_code,
                    'showroom_price' => round((int) $ex_showroom_Price),
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => [
                        'manf_name'             => $mmv_data->manufacturer,
                        'model_name'            => $mmv_data->vehiclemodel,
                        'version_name'          => $mmv_data->vehiclemodel,
                        'fuel_type'             => $mmv_data->cnvm_fuel_type,
                        'seating_capacity'      => $mmv_data->seatingcapacity,
                        'cubic_capacity'        => $mmv_data->cubiccapacity
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
                        'car_age' => $car_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => (int) $discount_amount
                    ],
                    'basic_premium' => (int) $od_premium,
                    'deduction_of_ncb' => (int) $ncb_discount,
                    'tppd_discount' => (int) $tppd_discount_amount,
                    'tppd_premium_amount' => (int) $tp_premium ,
                    'seating_capacity' => $mmv_data->seatingcapacity,
                    'compulsory_pa_own_driver' => (int) $pa_owner,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => (int) $total_od,
                    'total_liability_premium' => (int) $total_tp,
                    'net_premium' => (int) $final_net_premium,
                    'service_tax_amount' => (int) $final_tax,
                    'final_gst_amount' =>  (int) $final_tax,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => (int) $total_addons,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => round(0),
                    'final_od_premium' => round($total_od),
                    'final_tp_premium' => round($total_tp),
                    'final_total_discount' => round($total_discount),
                    'final_net_premium' => round($final_net_premium),
                    'final_payable_amount' => round($final_payable_amount),
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
                    'ic_vehicle_discount' => (int)$discount_amount,
                    'vehicle_in_90_days' => '0',
                    'get_policy_expiry_date' => NULL,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => NULL,
                        'discount_rate' => $discount_rate
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    "max_addons_selection" => NULL,
                    'applicable_addons' => $applicable_addons,
                    'add_ons_data' => $addons_data,
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    'is_cv_json_kit' => TRUE
                ]
            ];
            if($elect_accessories == 'True')
            {
                $data_response['Data']['motor_electric_accessories_value'] = (int) $electrical_amount;
            }
            if($non_elect_accessories == 'True')
            {
                $data_response['Data']['motor_non_electric_accessories_value'] = (int) $non_electrical_amount;
            }
            if($external_lpg_cng_accessories == 'True')
            {
                $data_response['Data']['motor_lpg_cng_kit_value'] = (int) $lpg_cng_amount;
            }

            if($voluntry_discount == 'True')
            {
                $data_response['Data']['voluntary_excess'] = (int) $voluntary_deductable_amount;
            }
            if($anti_theft_device == 'True')
            {
                $data_response['Data']['antitheft_discount'] = round($anti_theft_device_discount);
            }

            if($tppd_cover_flag == 'True')
            {
                $data_response['Data']['tppd_discount'] = round($tppd_discount_amount);
            }

            if($unnamed_psenger_cover == 'True')
            {
                $data_response['Data']['cover_unnamed_passenger_value'] = round($pa_unnamed);
            }
            if($pa_add_paid_driver_cover == 'True')
            {
                $data_response['Data']['motor_additional_paid_driver'] = (int) $pa_cover_driver;
            }
            if($llpd == 'True')
            {
                $data_response['Data']['default_paid_driver'] = (int) $liability;
                
                $data_response['Data']['ll_paid_driver_premium'] = (int) $liability;
                $data_response['Data']['ll_paid_conductor_premium'] = 0;
                $data_response['Data']['ll_paid_cleaner_premium'] = 0;             
                
            }
            if($external_lpg_cng_accessories == 'True')
            {
                $data_response['Data']['cng_lpg_tp'] = (int) $lpg_cng_tp_amount;
            }

            return camelCase($data_response);
        } 
        else 
        {
            return [
                'status' => false,
                'webservice_id'=>$get_response['webservice_id'],
                'table'=>$get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    } catch (\Exception $e) {
        return [
            'status' => false,
            'webservice_id'=>$get_response['webservice_id'],
            'table'=>$get_response['table'],
            'message' => $e->getMessage() . 'at Line no. -> ' . $e->getLine(),
            'request' => [
                'mmv_data' => $mmv_data,
                'requestData' => $requestData
            ]
        ];
    }
}


function get_usgi_applicable_addons($age,$vehicle_make,$interval,$is_GCV = false)
{

    switch ($vehicle_make)
    {

        default:
            $is_zero_dep_applicable = $is_key_replacement_applicable = $is_engine_protector_applicable = $is_consumable_applicable =  $is_tyre_secure_applicable = $is_rti_applicable = $is_lob_applicable =
            $is_ncb_protector_applicable = $is_rsa_applicable = 'False';


            break;
    }

    $is_rsa_applicable = 'False';
    if($interval->y < 10)
    {
        $is_rsa_applicable = 'True';
    }
    if($age < 5)
    {
        $is_zero_dep_applicable = 'True';
    }
    else 
    {
        $is_zero_dep_applicable = 'False';
    }
    if($is_GCV && $age >= 3){
        $is_zero_dep_applicable = 'False';
    } 
    $usgi_applicable_addons =
    [
        'zero_dep' => $is_zero_dep_applicable,
        'key_replacement' => $is_key_replacement_applicable,
        'engine_protector' => $is_engine_protector_applicable,
        'consumable' => $is_consumable_applicable,
        'tyre_secure' => $is_tyre_secure_applicable,
        'return_to_invoice' => $is_rti_applicable,
        'loss_of_belongings' => $is_lob_applicable,
        'ncb_protector'=> $is_ncb_protector_applicable,
        'rsa' => $is_rsa_applicable
    ];

    return $usgi_applicable_addons;
}

