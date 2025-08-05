<?php
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
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
    $mmv = get_mmv_details($productData,$requestData->version_id,'new_india');
   
    
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



    


    $mmv_details 
    = [
                    'manf_name' => $mmv_data->motor_make,
                    'model_name' => $mmv_data->motor_model,
                    'version_name' => $mmv_data->motor_variant,
                    'seating_capacity' => $mmv_data->motor_carrying_capacity,
                    'carrying_capacity' => $mmv_data->motor_carrying_capacity,
                    'cubic_capacity' => $mmv_data->motor_cc,
                    'fuel_type' =>  $mmv_data->motor_fule,
                    'vehicle_type' => 'BIKE',
                    'version_id' => $mmv_data->ic_version_code ,
                    'kw'=>  $mmv_data->motor_cc
                ];

    // bike age calculation
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $bike_age_in_mnth =  (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $bike_age = ceil($bike_age_in_mnth / 12);


    

    if (($bike_age_in_mnth > 58) && ($productData->zero_dep == '0'))
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Zero dep is not allowed for vehicle age greater than 58 months',
            'request'=> [
                'bike_age_in_month'=>$bike_age_in_mnth,
                'product_data'=>$productData->zero_dep
            ]
        ];
    }
    $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);

    $premium_type_array = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->select('premium_type_code','premium_type')
        ->first();
    $premium_type = $premium_type_array->premium_type_code;

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


    $policy_type = $premium_type_array->premium_type;
    
    try
    {

        $discount_percent = 20 ;
        $city_name =  DB::table('master_rto')
                    ->select('rto_name')
                    ->where('rto_code', $requestData->rto_code)
                    ->where('status', 'Active')
                    ->first();
        $ex_showroom_price = $mmv_data->motor_invoice;
        if ($requestData->business_type == 'newbusiness')
        {
            $bike_no_claim_bonus = '0';
            $bike_applicable_ncb = '0';
            $claimMadeinPreviousPolicy = 'N';
            $ncb_declaration = 'N';
            
            $total_idv = $mmv_data->idv_upto_6_months;

        }
        else
        {

            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
           
            $age = ceil($age / 12);
            $total_idv = $mmv['IDV_UPTO_' . (($age > 9) ? 15 : $age) . '_YEAR' . (($age == 1) ? '' : 'S')];
        }


        if($premium_type == 'third_party')
        {
            $total_idv = '0';
        }

        $reg_no = explode('-', strtoupper($requestData->vehicle_registration_no));

        if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10))
        {
            $requestData->vehicle_registration_no = $reg_no[0] . '-0' . $reg_no[1];
        }

        $company_rto_data = DB::table('new_india_rto_master')
                    ->where('rto_code', strtr($requestData->rto_code, ['-' => '']))
                    ->first();
                    $pincode_data = DB::table('new_india_pincode_master')
                    ->where('geo_area_code_1', $reg_no[0])
                    ->orderBy('pin_code', 'DESC')
                    ->first();

        if ($company_rto_data)
        {
            $voluntary = 0;
            $srilanka = 0;
            $pak = 0;
            $bang = 0;
            $bhutan = 0;
            $nepal = 0;
            $maldive = 0;
            $is_geo_ext = 0;
            $is_antitheft = 'N';

            if($requestData->business_type == 'newbusiness')
            {
                $policy_start_date = Date('d/m/Y');
                $policy_end_date = date('d-m-Y', strtotime('+5 years -1 day', strtotime($policy_start_date)));
                $reg_1 = 'NEW';
                $reg_2 = '0';
                $reg_3 = 'none';
                $reg_4 = '0001';
                $reg_no[0] = 'MH';

                $new_vehicle = 'Y';
            }
            else
            {
                if ($date_diff > 0)
                {
                    $policy_start_date = date('d/m/Y', strtotime('+4 day', time()));
                }
                else
                {
                   $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));

                }
                
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                
                $claimMadeinPreviousPolicy = $requestData->is_claim;
                if($date_diff > 90)
                {
                  $bike_expired_more_than_90_days = 'Y';
                }
                else
                {
                    $bike_expired_more_than_90_days = 'N';
                 
                }

                if ($claimMadeinPreviousPolicy == 'N' && $bike_expired_more_than_90_days == 'N' && $premium_type != 'third_party') 
                {
                    $bike_applicable_ncb = $requestData->applicable_ncb;

                }
                else
                {
                    $bike_applicable_ncb = '0';
                }

                if($requestData->previous_policy_type == 'Third-party')
                {
                    $bike_applicable_ncb = '0';
                }
                
               
                $reg_no = explode('-', strtoupper($requestData->rto_code));
               
                $reg_1 = $reg_no[0];
                $reg_2 = $reg_no[1];
                $reg_3 = 'XX';
                $reg_4 = 'XXXX';

                $new_vehicle = 'N';
            }
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('applicable_addons', 'compulsory_personal_accident','accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
            
            if (!empty($additional['discounts'])) {
                foreach ($additional['discounts'] as $data) {
                    if ($data['name'] == 'voluntary_insurer_discounts') {
                        $voluntary = $data['sumInsured'];
                    }
                    if ($data['name'] == 'anti-theft device') {
                        $is_antitheft = 'Y';
                    }
                }
            }
            
            if (!empty($additional['additional_covers'])) {
                foreach ($additional['additional_covers'] as $key => $data) {
                    if ($data['name'] == 'Geographical Extension') {
                        $is_geo_ext = 1;
                        $countries = $data['countries'];
                        if(in_array('Sri Lanka',$countries))
                        {
                            $srilanka = 1;
                        }
                        if(in_array('Bangladesh',$countries))
                        {
                            $bang = 1; 
                        }
                        if(in_array('Bhutan',$countries))
                        {
                            $bhutan = 1; 
                        }
                        if(in_array('Nepal',$countries))
                        {
                            $nepal = 1; 
                        }
                        if(in_array('Pakistan',$countries))
                        {
                            $pak = 1; 
                        }
                        if(in_array('Maldives',$countries))
                        {
                            $maldive = 1; 
                        }
                    }
                }
            }
            //cpa section
            $cpa_type = 'false';
            $cpa_with_greater_15 = 'No';
            if ($requestData->vehicle_owner_type == 'I') {
                if (!empty($additional['compulsory_personal_accident'])) { 
                    foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                        if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                            $cpa_with_greater_15 = 'No';
                            $cpa_type = "true";
                        }
                    }
                }
            } else {
                $cpa_type = 'false';
                $cpa_with_greater_15 = 'Yes';
            }
            $tp_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year', strtotime($requestData->previous_policy_expiry_date)))));
            $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year', strtotime(strtr($tp_start_date, '/', '-'))))));
            $electrical_accessories_flag = 'N';
            $non_electrical_accessories_flag = 'N';
            $include_pa_cover_for_paid_driver = 'N';
            $include_pa_cover_for_unnamed_person = 'N';
            $cost_of_consumable_cover = 'No';
            $engine_protect_cover = 'No';
            $key_protect_cover = 'No';
            $personal_belongings_cover = 'No';
            $rsa_cover = 'No';
            $rti_cover = 'No';
            $llpd_flag = '0';
            $bi_fuel_system_value = '0.00';
            $no_of_paid_drivers = '0';
            $capital_si_for_unnamed_persons = '0';
            $electrical_amt = '0';
            $non_electrical_amt = '0';
            $cover_pa_paid_driver = '0';
            $cover_pa_unnamed_passenger = '0';
            $no_of_unnamed_persons = '0';
            $is_cng = 'N';
            $total_accessories_idv = '0';
            $type_of_fuel = $mmv_data->motor_fule;

            // POS
            $is_pos = 'No';
            $pos_name = null;
            $pos_name_uiic = null;
            $partyCode = null;
            $partyStakeCode = null;
            $pos_partyCode = null ;
            $pos_partyStakeCode = null ;
            $is_pos_testing_mode = config('IC.NEWINDIA.BIKE.TESTING_MODE') === 'Y';

            $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

            if($pos_data && !$is_pos_testing_mode){
                //Properties
                $is_pos = 'YES';
                $pos_name = $pos_data->agent_name;
                $pos_name_uiic = 'uiic';

                //party
                $partyCode = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                ->pluck('new_india_code')
                ->first();
                $pos_partyStakeCode = $pos_name;
                if(empty($partyCode) || is_null($partyCode))
                {
                    return [
                        'status' => false,
                        'premium_amount' => 0,
                        'message' => 'POS details Not Available'
                    ];
                }
                $partyStakeCode = 'POS';
            }
            if($is_pos_testing_mode == 'Y')
            {
                 //properties
                 $is_pos = 'YES';
                 $pos_name = 'POS Applicable';
                 $pos_name_uiic = 'uiic';
                 //properties
                 //party
                 $partyCode = config('IC.NEWINDIA.BIKE.POS_PARTY_CODE');//PP00000015
                 $partyStakeCode = 'POS';
                 //party
            }
            
            #$total_accessories_idv = $electrical_amt + $non_electrical_amt + $bi_fuel_system_value;

            if($productData->zero_dep == 0 && $premium_type !== 'third_party')
            {
                
                if($bike_age_in_mnth <= 36)
                {
                    $discount_column_selector = 'discount_percent_with_addons_0_to_36_months';
                }
                elseif ($bike_age_in_mnth >36 && $bike_age_in_mnth <=58) 
                {
                    $discount_column_selector = 'discount_percent_with_addons_37_to_58_months';
                }                        

            }
            else
            {
               
                if($bike_age_in_mnth <= 120)
                {
                    $discount_column_selector = 'discount_percent_without_addons_0_to_120_months';
                }
                elseif ($bike_age_in_mnth >120 && $bike_age_in_mnth <= 178) 
                {
                    $discount_column_selector = 'discount_percent_without_addons_121_to_178_months';
                }


            }

            $discount_percent = DB::table('new_india_motor_discount_grid')
                                    ->select("{$discount_column_selector} as discount_col")
                                    ->where('section','bike')
                                    ->first();


            if($discount_percent == 'third_party')
            {
                $discount_percent = '0';
            }

            if($premium_type == 'own_damage' && $bike_age_in_mnth <= 120) {
                $discount_percent->discount_col  = '0';
            }

            $additional = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

            if ($additional && $additional->additional_covers != NULL && $additional->additional_covers != '') 
            {
                $additional_covers = $additional->additional_covers;
               
                foreach ($additional_covers as $value) 
                {
                    if($value['name'] == 'Unnamed Passenger PA Cover' && $premium_type != 'own_damage') 
                    {  
                        $no_of_unnamed_persons = $mmv_data->motor_carrying_capacity;
                        $capital_si_for_unnamed_persons = ($mmv_data->motor_carrying_capacity) * $value['sumInsured'];
                        $cover_pa_unnamed_passenger = $value['sumInsured'];
                        $include_pa_cover_for_unnamed_person = 'Y';
                    }
                    if($value['name'] == 'LL paid driver' && $premium_type != 'own_damage')
                    {
                        $llpd_flag = '1';
                    }
                    if ($value['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                        $cover_pa_paid_driver = $data['sumInsured'];
                       $no_of_paid_drivers = '1';
                       $include_pa_cover_for_paid_driver = 'Y';
                    }
                    
                }
           }
           if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $bi_fuel_system_value = $data['sumInsured'];
                    $type_of_fuel = 'CNG'; /* .$veh_data->motor_fule; */
                    $is_cng = 'Y';
                }
    
                if ($data['name'] == 'Non-Electrical Accessories') {
                    $non_electrical_accessories_flag = 'Y';
                    $non_electrical_amt = $data['sumInsured'];
                }
    
                if ($data['name'] == 'Electrical Accessories') {
                    $electrical_accessories_flag = 'Y';
                    $electrical_amt = $data['sumInsured'];
                    }
                }
            }
            $total_accessories_idv = $electrical_amt + $non_electrical_amt + $bi_fuel_system_value;
            switch ($premium_type) 
            {
                
                case 'comprehensive':
                    $coverCode = 'PACKAGE';
                    $product_id = '194731914122007';
                    $product_code = 'TW';
                    $product_name = 'TWO WHEELER';
                    $plan_type = 'NBPL';
                    break;
                case 'third_party' :
                    $coverCode =  'LIABILITY';
                    $product_id = '194731914122007';
                    $product_code = 'TW';
                    $product_name = 'TWO WHEELER';
                    $plan_type = 'NLTL';
                    break;
                case 'own_damage':
                    $coverCode =  'ODWTOTADON';//'ODWTHADDON';
                    $product_id = '121625924072019';
                    $product_code = 'SQ';
                    $product_name = 'Standalone OD policy for TW';

                    $no_of_unnamed_persons = '0';
                    $cover_pa_unnamed_passenger = '0';
                    $capital_si_for_unnamed_persons = '0';
                    $include_pa_cover_for_unnamed_person = 'N';
                    $llpd_flag = '0';
                    break;                
            }

            $min_idv = ($total_idv * 0.9);
            $max_idv = ($total_idv * 1.1);
            if ($requestData->is_idv_changed == 'Y') 
            {                          
                if ($requestData->edit_idv >= $max_idv) 
                {
                    $total_idv = $max_idv;
                } 
                elseif ($requestData->edit_idv <= $min_idv) 
                {
                    $total_idv = $min_idv;
                } 
                else 
                {
                    $total_idv = $requestData->edit_idv;
                }                        
            }

            $covers = [
                'typ:productCode'     => $product_code,
                'typ:policyDetailid'  => '',
                'typ:coverCode'       => ($productData->zero_dep == '1') ? $coverCode : (($premium_type == 'own_damage') ? 'ODWTHADDON' : 'EHMNTCOVER'),
                'typ:productId'       => $product_id,
                'typ:coverExpiryDate' => '',
                'typ:properties'      => [
                    [
                        'typ:value' => 'N',
                        'typ:name'  => 'Do You want to reduce TPPD cover to the statutory limit of Rs.6000',
                    ],
                    [
                        'typ:value' => '100000',
                        'typ:name'  => 'Sum Insured for PA to Owner Driver',
                    ],
                    [
                        'typ:value' => 'N',
                        'typ:name'  => 'Do you want to include PA cover for Named Person',
                    ],
                    [
                        'typ:value' => '0',
                        'typ:name'  => 'Number of Named Persons',
                    ],
                    [
                        'typ:value' => 'none',
                        'typ:name'  => 'Names of Named person',
                    ],
                    [
                        'typ:value' => '0',
                        'typ:name'  => 'Individual SI for Named Person',
                    ],
                    [
                        'typ:value' => '0',
                        'typ:name'  => 'Capital SI for All Named Persons',
                    ],
                    [
                        'typ:value' => $no_of_paid_drivers,
                        'typ:name'  => 'No of Paid Drivers',
                    ],
                    [
                        'typ:value' => (string) $cover_pa_paid_driver,
                        'typ:name'  => 'Individual SI for Paid Driver',
                    ],
                    [
                        'typ:value' => (string) $cover_pa_paid_driver,
                        'typ:name'  => 'Capital SI for Drivers',
                    ],
                    [
                        'typ:value' => $no_of_unnamed_persons,
                        'typ:name'  => 'No of unnamed Persons',
                    ],
                    [
                        'typ:value' => (string) $cover_pa_unnamed_passenger,
                        'typ:name'  => 'Individual SI for unnamed Person',
                    ],
                    [
                        'typ:value' => $capital_si_for_unnamed_persons,
                        'typ:name'  => 'Capital SI for unnamed Persons',
                    ],
                    [
                        'typ:value' => '0',
                        'typ:name'  => 'Number of LL to Soldiers/Sailors/Airmen',
                    ],
                    [
                        'typ:value' => '0',
                        'typ:name'  => 'Number Of Legal Liable Employees',
                    ],
                    [
                        'typ:value' => $llpd_flag,
                        'typ:name'  => 'Number of Legal Liable Drivers',
                    ],
                    [
                        'typ:value' => $include_pa_cover_for_paid_driver,
                        'typ:name'  => 'Do you wish to include PA Cover for Paid Drivers',
                    ],
                    [
                        'typ:value' => $include_pa_cover_for_unnamed_person,
                        'typ:name'  => 'Do you want to include PA cover for unnamed person/hirer/pillion passangers',
                    ],
                    [
                        'typ:value' => $llpd_flag == '1'? 'Y' : 'N',
                        'typ:name'  => 'LL to paid drivers,cleaner employed  for opn. and/or maint. of vehicle under WCA',
                    ],
                    [
                        'typ:value' => 'N',
                        'typ:name'  => 'LL to Employees of Insured traveling and / or driving the Vehicle',
                    ],
                    [
                        'typ:value' => 'N',
                        'typ:name'  => 'LL to Soldiers/Sailors/Airmen employed as Drivers',
                    ],
                    [
                        'typ:value' => '0',
                        'typ:name'  => 'Sum Insured for TPPD',
                    ],
                    [
                        'typ:value' => 'A',
                        'typ:name'  => 'Type of Liability Coverage',
                    ],
                    //addons start
                    


                ],
            ];

            $properties = [
                [
                    'typ:value' => 'Model1TieUp',
                    'typ:name'  => 'channelcode',
                ],
                [
                    'typ:value' => 'NONINSPEC',
                    'typ:name'  => 'Break-In Indicator',
                ],
                [
                    'typ:value' => '',
                    'typ:name'  => 'Break-in Approval Number(Inspection Number)',
                ],
                [
                    'typ:value' => '',
                    'typ:name'  => 'Break-in Approval Date',
                ],
                [
                    'typ:value' => $is_pos,
                    'typ:name'  => $pos_name,
                ]
            ];

            $zone_cities = [
                'ahmedabad',
                'bangalore',
                'bengaluru',
                'chennai',
                'delhi',
                'hyderabad',
                'kolkata',
                'mumbai',
                'new delhi',
                'pune'
            ];

            $requestData->vehicle_register_date = strtr($requestData->vehicle_register_date, ['-' => '/']);
            $motor_manf_year_arr = explode('-', $requestData->manufacture_year);
            $motor_manf_year = $motor_manf_year_arr[1];
            $motor_manf_month = $motor_manf_year_arr[0];
            $risk_properties = [
                [

                    'typ:value' => 'STDTWWHL',
                    'typ:name'  => 'Type of Two Wheeler',

                ],
                [
                    'typ:value' => 'New',
                    'typ:name'  => 'Current Ownership',
                ],
                [

                    'typ:value' => 'OTHER',
                    'typ:name'  => 'Color of Vehicle',
                ],
                [
                    'typ:value' => 'BLACK',
                    'typ:name'  => 'Color as per RC book',
                ],
                [

                    'typ:value' => $motor_manf_year,
                    'typ:name'  => 'Year of Manufacture',
                ],
                [
                    'typ:value' => $new_vehicle,
                    'typ:name'  => 'New Vehicle',
                ],
                [
                    'typ:value' => $reg_1,
                    'typ:name'  => 'Registration No (1)',
                ],
                [
                    'typ:value' => $reg_2,
                    'typ:name'  => 'Registration No (2)',
                ],
                [
                    'typ:value' => $reg_3,
                    'typ:name'  => 'Registration No (3)',
                ],
                [
                    'typ:value' => $reg_4,
                    'typ:name'  => 'Registration No (4)',
                ],
                [
                    'typ:value' => $vehicleDate,
                    'typ:name'  => 'Date of Sale',
                ],
                [
                    'typ:value' => $requestData->vehicle_register_date,
                    'typ:name'  => 'Date of Registration',
                ],
                [
                    'typ:value' => '01/01/2029',
                    'typ:name'  => 'Registration Validity Date',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'Give Vehicle Details',
                ],
                [

                    'typ:value' => '0',
                    'typ:name'  => 'Value of Music System',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Value of AC/Fan',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Value of Lights',
                ],
                [
                    'typ:value' => $non_electrical_amt,
                    'typ:name'  => 'Value of Other Fittings',
                ],
                [
                    'typ:value' => $electrical_amt,
                    'typ:name'  => 'Total Value of Extra Electrical/ Electronic fittings',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Previous Policy Number',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'Name of Association',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'Membership No',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Is Life Member',
                ],
                [
                    'typ:value' => '01/01/0001',
                    'typ:name'  => 'Date of Membership Expiry',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'Details of Vehicle Condition',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Discretion to RO',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'Approval No',
                ],
                [
                    'typ:value' => '01/01/0001',
                    'typ:name'  => 'Approval Date',
                ],
                [
                    'typ:value' =>  $bang == 1 ? 'Y':'N' ,
                    'typ:name'  => 'Extension of Geographical Area to Bangladesh',
                ],
                [
                    'typ:value' =>  $bhutan == 1 ? 'Y':'N' ,
                    'typ:name'  => 'Extension of Geographical Area to Bhutan',
                ],
                [
                    'typ:value' =>  $nepal == 1 ? 'Y':'N' ,
                    'typ:name'  => 'Extension of Geographical Area to Nepal',
                ],
                [
                    'typ:value' =>  $pak == 1 ? 'Y':'N' ,
                    'typ:name'  => 'Extension of Geographical Area to Pakistan',
                ],
                [
                    'typ:value' =>  $srilanka == 1 ? 'Y':'N' ,
                    'typ:name'  => 'Extension of Geographical Area to Sri Lanka',
                ],
                [
                    'typ:value' =>  $maldive == 1 ? 'Y':'N' ,
                    'typ:name'  => 'Extension of Geographical Area to Maldives',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Value of Fibre glass fuel tanks',
                ],
                [
                    'typ:value' => $bi_fuel_system_value,
                    'typ:name'  => 'Bi-fuel System Value',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'In Built Bi-fuel System fitted',
                ],
                [
                    'typ:value' => 'SALOON',
                    'typ:name'  => 'Type of Body',
                ],
                [
                    'typ:value' => '',
                    'typ:name'  => '(*)Engine No',
                ],
                [
                    'typ:value' => '',
                    'typ:name'  => '(*)Chassis No',
                ],
                [
                    'typ:value' => substr($mmv_data->motor_make, 0, 10),
                    'typ:name'  => 'Make',
                ],
                [
                    'typ:value' => $mmv_data->motor_model,
                    'typ:name'  => 'Model',
                ],
                [
                    'typ:value' => 'Y',
                    'typ:name'  => 'Bike in roadworthy condition and free from damage',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Vehicle Requisitioned by Government',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Whether vehicle is used for driving tuition',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Vehicle use is limited to own premises',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Whether vehicle belongs to foreign embassy or consulate',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Whether vehicle is certified as Vintage bike by Vintage and Classic Bike Club',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Vehicle designed for Blind/Handicapped/Mentally Challenged persons and endorsed by RTA',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Are you a member of Automobile Association of India',
                ],
                [
                    'typ:value' => ($is_antitheft == 'Y') ?'Y':'N',
                    'typ:name'  => 'Is the vehicle fitted with Anti-theft device',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Obsolete Vehicle',
                ],
                [
                    'typ:value' => ($is_geo_ext == 1) ? 'Y' : 'N',
                    'typ:name'  => 'Extension of Geographical Area required',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Whether Vehicle belong to Embassies or imported without Custom Duty',
                ],
                [
                    'typ:value' => (in_array(strtolower($city_name->rto_name), $zone_cities)) ? 'A' : 'B',
                    'typ:name'  => 'Vehicle Zone for Private Bike',
                ],
                [
                    'typ:value' => $mmv_data->motor_variant,
                    'typ:name'  => 'Variant',
                ],
                [
                    'typ:value' => $company_rto_data->rta_name . ' ' . $company_rto_data->rta_address,
                    'typ:name'  => 'Name and Address of Registration Authority',
                ],
                [
                    'typ:value' => $mmv_data->motor_cc,
                    'typ:name'  => '(*)Cubic Capacity',
                ],
                [
                    'typ:value' => $mmv_data->motor_carrying_capacity,
                    'typ:name'  => '(*)Seating Capacity',
                ],
                [
                    'typ:value' => $type_of_fuel,
                    'typ:name'  => 'Type of Fuel',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Fibre Glass Tank Fitted',
                ],
                [
                    'typ:value' => ($premium_type != 'third_party' ) ? $ex_showroom_price : 0,
                    'typ:name'  => 'Vehicle Invoice Value',
                ],
                [
                    'typ:value' => $bike_age_in_mnth,
                    'typ:name'  => 'Vehicle Age in Months',
                ],
                [
                    'typ:value' => $electrical_accessories_flag,
                    'typ:name'  => 'Extra Electrical/ Electronic fittings',
                ],
                [
                    'typ:value' => $non_electrical_accessories_flag,
                    'typ:name'  => 'Non-Electrical/ Electronic fittings',
                ],
                [
                    'typ:value' => $non_electrical_amt,
                    'typ:name'  => 'Value of Non- Electrical/ Electronic fittings',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Additional Towing Coverage Required',
                ],
                

                //cpa section start
                [
                    'typ:value' => ($cpa_type == 'true') ?'N':'Y',
                    'typ:name'  => 'Do You Hold Valid Driving License',
                ],

                [
                    'typ:value' => $cpa_with_greater_15,
                    'typ:name'  => 'Do you have a general PA cover with motor accident coverage for CSI of atleast 15 lacs?',
                ],
                [
                    'typ:value' => ($cpa_type == 'true') ? 'No':'Yes',
                    'typ:name'  => 'Do you have a standalone CPA cover product for Owner Driver?',
                ],
                
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'License Type of Owner Driver',
                ],
                //cpa section end
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Age of Owner Driver',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'Owner Driver Driving License No',
                ],
                [
                    'typ:value' => '01/01/0001',
                    'typ:name'  => 'Owner Driver License Expiry Date',
                ],
                [
                    'typ:value' => 'none',
                    'typ:name'  => 'License Issuing Authority for Owner Driver',
                ],
                [
                    'typ:value' => 'asdasd',
                    'typ:name'  => 'Name of Nominee',
                ],
                [
                    'typ:value' => '25',
                    'typ:name'  => 'Age of Nominee',
                ],
                [
                    'typ:value' => 'Sibling',
                    'typ:name'  => 'Relationship with the Insured',
                ],
                [
                    'typ:value' => 'MALE',
                    'typ:name'  => 'Gender of the Nominee',
                ],
                [
                    'typ:value' => 'N',
                    'typ:name'  => 'Do you Have Any other Driver',
                ],
                [
                    'typ:value' => ($premium_type != 'third_party' ) ? $total_idv : 0,
                    'typ:name'  => 'Insureds declared Value (IDV)',
                ],
                [
                    'typ:value' => $total_accessories_idv,
                    'typ:name'  => 'IDV of Accessories',
                ],
                [
                    'typ:value' => ($premium_type != 'third_party' ) ? $total_idv + $total_accessories_idv  : 0,
                    'typ:name'  => 'Total IDV',
                ],
                [
                    'typ:value' => ($premium_type != 'third_party') ? $bike_applicable_ncb: 0,
                    'typ:name'  => 'NCB Applicable Percentage',
                ],
                [
                    'typ:value' => 'NA',
                    'typ:name'  => 'Name of Previous Insurer',
                ],
                [
                    'typ:value' => 'NA',
                    'typ:name'  => 'Address of the Previous Insurer',
                ],
                [
                    'typ:value' => $requestData->vehicle_register_date,
                    'typ:name'  => 'Date of Purchase of Vehicle by Proposer',
                ],
                [
                    'typ:value' => 'Y',
                    'typ:name'  => 'Vehicle New or Second hand at the time of purchase',
                ],
                [
                    'typ:value' => 'Y',
                    'typ:name'  => 'Vehicle Used for Private,Social,domestic,pleasure,professional purpose',
                ],
                [
                    'typ:value' => 'Y',
                    'typ:name'  => 'Is vehicle in good condition',
                ],
                [
                    'typ:value' => strtr($requestData->previous_policy_expiry_date, ['-' => '/']),
                    'typ:name'  => 'Expiry date of previous Policy',
                ],
                [
                    'typ:value' => '0.00',
                    'typ:name'  => 'Policy Excess (Rs)',
                ],
                [
                    'typ:value' => $voluntary,
                    'typ:name'  => 'Voluntary Excess for TW',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Imposed Excess (Rs)',
                ],
                [
                    'typ:value' => '0',
                    'typ:name'  => 'Loading amount for OD',
                ],
                [
                    'typ:value' => ($premium_type == 'third_party') ? '0' : $discount_percent->discount_col,
                    'typ:name'  => 'OD discount (%)',
                ],
                [
                    'typ:value' => '',
                    'typ:name'  => 'Owner Driver License Issue Date',
                ],
                [
                    'typ:value' => 'As per RC book',
                    'typ:name'  => 'Color as per RC book',
                ],
                [
                    'typ:value' => $is_pos,
                    'typ:name'  => $pos_name_uiic,
                ],
                //tp details
                // [
                //     'typ:value' => ($premium_type == 'own_damage') ? 'ICICI LOMBARD GENERAL INSURANCE CO. LTD.' :'',
                //     'typ:name'  => 'Name (Bundled/Long Term Liability policy Insurer)',
                // ],
                // [
                //     'typ:value' => ($premium_type == 'own_damage') ? 'ICL12435' :'',
                //     'typ:name'  => 'Bundled/Long Term Liability Policy No.',
                // ],
                // [
                //     'typ:value' => ($premium_type == 'own_damage') ? $tp_start_date :'',
                //     'typ:name'  => 'Bundled/Long Term Policy Start Date',
                // ],
                // [
                //     'typ:value' => ($premium_type == 'own_damage') ? $tp_end_date : '',
                //     'typ:name'  => 'Bundled/Long Term Policy Expiry Date',
                // ],

            ];

            if($productData->zero_dep == '0'){
                $plan_type = 'NBEL';
            }

            if($requestData->business_type == 'newbusiness'){
                array_push($risk_properties, [
                    'typ:value' => $plan_type,
                    'typ:name'  => 'Policy Cover Plan Type',
                ]);
            };

            if($requestData->business_type != 'newbusiness' || $premium_type == 'third_party'){
                $tp_details =  [
                [
                    'typ:value' => ($premium_type == 'own_damage') ? 'DHFL General Insurance Ltd.' :'',
                    'typ:name'  => 'Name (Bundled/Long Term Liability policy Insurer)',
                ],
                [
                    'typ:value' => ($premium_type == 'own_damage') ? 'ICL12435' :'',
                    'typ:name'  => 'Bundled/Long Term Liability Policy No.',
                ],
                [
                    'typ:value' => ($premium_type == 'own_damage') ? $tp_start_date :'',
                    'typ:name'  => 'Bundled/Long Term Policy Start Date',
                ],
                [
                    'typ:value' => ($premium_type == 'own_damage') ? $tp_end_date : '',
                    'typ:name'  => 'Bundled/Long Term Policy Expiry Date',
                ],
                ];
                $risk_properties = array_merge($risk_properties, $tp_details);
            }
            $party= null;
            if($pos_data){
                $party=[
                    [
                        'typ:partyCode' =>  $partyCode,
                        'typ:partyStakeCode' =>  'POS',
                    ],
                ];
            }
            if($is_pos_testing_mode == 'Y'){
                $party=[
                    [
                        'typ:partyCode' =>  $partyCode,
                        'typ:partyStakeCode'  =>  $partyStakeCode,
                    ],
                ];
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
            if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage") {
                if ($requestData->business_type == 'newbusiness') {
                    $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '5';
                } else {
                    $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
                }
            }                      
            $premium_array = [
                'typ:userCode'                 => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),//'MAHINDRA',
                'typ:rolecode'                 => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                'typ:PRetCode'                 => '0',
                'typ:userId'                   => '',
                'typ:stakeCode'                => 'BROKER',
                'typ:roleId'                   => '',
                'typ:userroleId'               => '',
                'typ:branchcode'               => '',
                'typ:PRetErr'                  => '',
                'typ:polBranchCode'            => '',
                'typ:productName'              => $product_name,
                'typ:policyHoldercode'         => $reg_no[0],
                'typ:eventDate'                => $policy_start_date,
                'typ:party'                    => $party,
                'typ:netPremium'               => '',
                'typ:termUnit'                 => 'G',
                'typ:grossPremium'             => '',
                'typ:policyType'               => '',
                'typ:polInceptiondate'         => $policy_start_date,
                'typ:polStartdate'             => $policy_start_date,
                'typ:polExpirydate'            => date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))),
                'typ:branchCode'               => '',
                // 'typ:term'                     => '1',
                'typ:term'                     => isset($cpa_tenure) ? $cpa_tenure : '1',
                'typ:polEventEffectiveEndDate' => '',
                'typ:productCode'              => $product_code,
                'typ:policyHolderName'         => 'POLICY USER',
                'typ:productId'                => $product_id,
                'typ:serviceTax'               => '',
                'typ:status'                   => '',
                'typ:cbDetails'                => '',
                'typ:sumInsured'               => '',
                'typ:updateDate'               => '',
                'typ:policyId'                 => '',
                'typ:polDetailLastUpdateDate'  => '',
                'typ:quoteNo'                  => '',
                'typ:policyNo'                 => '',
                'typ:policyDetailid'           => '',
                'typ:documentLink'             => '',
                'typ:polLastUpdateDate'        => '',
                'typ:properties'               => $properties,
                'typ:risks'                    => [
                    'typ:riskCode'       => 'VEHICLE',
                    'typ:riskSuminsured' => '0',
                    'typ:covers'         => $covers,
                    'typ:properties'     => $risk_properties,
                ],
            ];
            if($requestData->business_type == 'newbusiness'){
                // $premium_array['typ:term'] = '5';
                $premium_array['typ:term'] = isset($cpa_tenure) ? $cpa_tenure : '5';
                $premium_array['typ:polExpirydate'] = isset($cpa_tenure)?date('d/m/Y', strtotime(date('Y-m-d', strtotime("+$cpa_tenure year -1 day", strtotime(strtr($policy_start_date, ['/' => '-'])))))) : date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))); 
            }
            unset($properties, $covers, $risk_properties);
            
           $checksum_data = checksum_encrypt($premium_array);
           $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'new_india', $checksum_data, 'BIKE');
           if($is_data_exist_for_checksum['found'] && $refer_webservice &&$is_data_exist_for_checksum['status']){
               $get_response = $is_data_exist_for_checksum;
           }else{
               $get_response = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA_BIKE'),$premium_array, 'new_india',
               [
                   'root_tag'      => 'typ:calculatePremiumMasterElement',
                   'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                   'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                   'enquiryId' => $enquiryId,
                   'requestMethod' =>'post',
                   'productName'  => $productData->product_name,
                   'company'  => 'new_india',
                   'checksum' =>$checksum_data,
                   'section' => $productData->product_sub_type_code,
                   'method' =>'Premium Calculation - Quote',
                   'transaction_type' => 'quote',
                ]);
            }

        if ($get_response['response'])
        {
            $premium_resp =XmlToArray::convert((string)remove_xml_namespace($get_response['response']));            
            if($premium_resp['Body']['calculatePremiumMasterResponseElement']['PRetCode'] == 0) 
            {
                $zero_dep_key = 0;
                $engine_protector_key = 0;
                $consumable_key = 0;
                $ncb_protecter_key = 0;
                $tyre_secure_key = 0;
                $personal_belongings_key = 0;
                $rsa_key = 0;
                $key_protect_key = 0;
                $rti_cover_key = 0;
                $pa_owner_driver = 0;
                $basic_tp_key= 0;
                $electrical_key = 0;
                $nonelectrical_key = 0;
                $basic_od_key = 0;
                $calculated_ncb= 0;
                $ll_paid_driver = 0;
                $pa_paid_driver= 0;
                $pa_unnamed_person = 0;
                $additional_od_prem_cnglpg = 0;
                $additional_tp_prem_cnglpg = 0;
                $anti_theft_discount_key = 0;
                $aai_discount_key = 0;
                $od_discount = 0;
                $basePremium =0;
                $total_od = 0;
                $total_tp = 0;
                $total_discount = 0;
                $geog_Extension_OD_Premium = 0;
                $geog_Extension_TP_Premium = 0;
                $voluntary_Deductible_Discount = 0;
                foreach ($premium_resp['Body']['calculatePremiumMasterResponseElement']['properties'] as $key => $cover)
                {
                    if ($cover['name'] === 'Additional Premium for Electrical fitting')
                    {
                        $electrical_key = $cover['value'];
                    }
                    elseif ($cover['name'] === 'Additional Premium for Non-Electrical fitting')
                    {
                        $nonelectrical_key = $cover['value'];
                    }
                    elseif ($cover['name'] === 'Basic OD Premium')
                    {
                        $basic_od_key = $cover['value'];
                    }
                    //elseif ($cover['name'] === 'Basic OD Premium_IMT')
                    elseif ($cover['name'] === 'IMT Rate Basic OD Premium')
                    {
                        $basic_od_key = $cover['value'];
                    }
                    elseif ($cover['name'] === 'Basic TP Premium' && $requestData->business_type !='newbusiness')
                    {
                        $basic_tp_key = $cover['value'];
                    }
                    //new business tp calculation
                    elseif (in_array($cover['name'], ['(#)Total TP Premium' , '(#)Total TP Premium for 2nd Year', '(#)Total TP Premium for 3rd Year', '(#)Total TP Premium for 4th Year', '(#)Total TP Premium for 5th Year'])  && $requestData->business_type =='newbusiness')
                    {
                        $basic_tp_key += $cover['value'];
                    }
                    elseif ($cover['name'] === 'Calculated NCB Discount')
                    {
                        $calculated_ncb = $cover['value'];
                    }
                    elseif ($cover['name'] === 'Calculated Voluntary Deductible Discount')
                    {
                        $voluntary_Deductible_Discount = $cover['value'];
                    }
                    elseif ($cover['name'] === 'OD Premium Discount Amount')
                    {
                        $od_discount = $cover['value'];
                    }
                    
                    elseif ($cover['name'] === 'Compulsory PA Premium for Owner Driver')
                    {
                        $pa_owner_driver = $cover['value'];
                    }
                    elseif ($cover['name'] === 'Net Total Premium')
                    {
                        $net_total_premium = $cover['value'];
                    }
                    elseif ($cover['name'] === 'Legal Liability Premium for Paid Driver')
                    {
                        $ll_paid_driver = $cover['value'];
                    }
                    elseif ($cover['name'] === 'PA premium for Paid Drivers And Others')
                    {
                        $pa_paid_driver = $cover['value'];
                    }
                    elseif ($cover['name'] === 'PA premium for UnNamed/Hirer/Pillion Persons')
                    {
                        $pa_unnamed_person = $cover['value'];
                    }
                    // elseif (($bike_data['zero_dep'] == '0') && ($cover['name'] === 'Premium for enhancement cover'))
                    // {
                    //     $zero_dep_key = $key;
                    // }                                    
                    elseif ($cover['name'] == 'Additional OD Premium for CNG/LPG')
                    {
                        $additional_od_prem_cnglpg = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Additional TP Premium for CNG/LPG')
                    {
                        $additional_tp_prem_cnglpg = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Calculated Discount for Anti-Theft Devices')
                    {
                        $anti_theft_discount_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Calculated Discount for Membership of recognized Automobile Association')
                    {
                        $aai_discount_key = $cover['value'];
                    }

                    //addons
                    elseif (($productData->zero_dep == '0') && ($cover['name'] === 'Premium for nil depreciation cover'))
                    {
                        $zero_dep_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Engine Protect Cover Premium')
                    {
                        $engine_protector_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Consumable Items Cover Premium')
                    {
                        $consumable_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'NCB Protection Cover Premium')
                    {
                        $ncb_protecter_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Tyre and Alloy Cover Premium')
                    {
                        $tyre_secure_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Personal Belongings Cover Premium')
                    {
                        $personal_belongings_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Additional Towing Charges Cover Premium')
                    {
                        $rsa_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Key Protect Cover Premium')
                    {
                        $key_protect_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Return to Invoice Cover Premium')
                    {
                        $rti_cover_key = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Loading for Extension of Geographical area')
                    {
                        $geog_Extension_OD_Premium = $cover['value'];
                    }
                    elseif ($cover['name'] == 'Extension of Geographical Area Premium')
                    {
                        $geog_Extension_TP_Premium = $cover['value'];
                    }
                }


                if ($productData->zero_dep == 0)
                {
                    $add_ons_data = [
                        'in_built'   => [
                            'zeroDepreciation'            => (int) $zero_dep_key,
                        ],
                        'additional' => [
                            // 'zeroDepreciation'            => (int) $zero_dep_key,
                            'road_side_assistance'        => (int) $rsa_key,
                            // 'cpa_cover'                   => (int)  $pa_owner_driver
                        ],
                        'other'      => [],
                    ];
                }
                else
                {
                    $add_ons_data = [
                        'in_built'   => [],
                        'additional' => [
                            'zeroDepreciation'           => 0,
                            'road_side_assistance'       => 0,
                            'engineProtector'            => 0,
                            'ncbProtection'              => 0,
                            'keyReplace'                 => 0,
                            'consumables'                => 0,
                            'tyreSecure'                 => 0,
                            'returnToInvoice'            => 0,
                            'lopb'                       => 0,
                            // 'cpa_cover'                  =>  $pa_owner_driver
                        ],
                        'other'      => [],
                    ];
                }
            

                $applicable_addons = [
                    'zeroDepreciation',
                    'roadSideAssistance',
                ];
                
                if ($rsa_key == 0) 
                {
                    array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                    
                }
                if ($zero_dep_key == 0) 
                {
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    
                }
            
                $total_od= $basic_od_key + $electrical_key + $nonelectrical_key + $additional_od_prem_cnglpg + $geog_Extension_OD_Premium;

                $total_tp = $basic_tp_key + $ll_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $pa_paid_driver + $geog_Extension_TP_Premium;

                $total_discount = $calculated_ncb + $od_discount + $aai_discount_key + $anti_theft_discount_key + $voluntary_Deductible_Discount;

                $basePremium = $base_premium_amount = $total_od + $total_tp - $total_discount;
                $totalTax = $basePremium * 0.18;

                $final_premium = $basePremium + $totalTax;
                $policy_start_date = strtr($policy_start_date,'/','-');
                $policy_end_date = strtr($policy_end_date, '/', '-');
                foreach($add_ons_data['additional'] as $k=>$v){
                    if(empty($v)){
                        unset($add_ons_data['additional'][$k]);
                    }
                }
                $data_response = [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => true,
                    'msg' => 'Found',
                    'Data' => [
                        'idv' => $premium_type == 'third_party' ? 0 : ($total_idv),
                        'vehicle_idv' => $total_idv,
                        'min_idv' => $min_idv,
                        'max_idv' => $max_idv,
                        'rto_decline' => NULL,
                        'rto_decline_number' => NULL,
                        'mmv_decline' => NULL,
                        'mmv_decline_name' => NULL,
                        'policy_type' => ($premium_type=='third_party' ? 'Third Party' : ($premium_type=='own_damage' ?'Own Damage':'Comprehensive')),
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'rto_no' => $requestData->rto_code,
                        'voluntary_excess' => $voluntary_Deductible_Discount,
                        'version_id' => $mmv_data->ic_version_code,
                        'showroom_price' => '0',
                        'fuel_type' => $requestData->fuel_type,
                        'ncb_discount' => (int)$requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $productData->product_sub_type_name,
                        'mmv_detail' => $mmv_details,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' =>  $policy_start_date,
                            'policy_end_date' => $policy_end_date,
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
                        'vehicle_register_date' =>  date('d-m-Y', strtotime(strtr($requestData->vehicle_register_date, ['/' => '-']))),
                        'vehicleDiscountValues' => [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => 0,
                            'rto_cluster_id' => 0,
                            'bike_age' => $bike_age,
                            'aai_discount' => 0,
                            'ic_vehicle_discount' =>  $od_discount,
                        ],
                        'basic_premium' => (int)$basic_od_key,
                        'deduction_of_ncb' => (int)$calculated_ncb,
                        'tppd_premium_amount' => ($requestData->business_type =='newbusiness') ?((int)$basic_tp_key - ($ll_paid_driver * 5) - ($pa_unnamed_person * 5) - $additional_tp_prem_cnglpg - $pa_paid_driver - ($geog_Extension_TP_Premium * 5) - $pa_owner_driver) : (int)$basic_tp_key,
                        'motor_electric_accessories_value' => (int)$electrical_key,
                        'motor_non_electric_accessories_value' => (int)$nonelectrical_key,
                        'motor_lpg_cng_kit_value' => (int)$additional_od_prem_cnglpg,
                        'cover_unnamed_passenger_value' => ($requestData->business_type =='newbusiness') ? ($pa_unnamed_person * 5) : $pa_unnamed_person,
                        'seating_capacity' => $mmv_data->motor_carrying_capacity,
                        'default_paid_driver' => ($requestData->business_type =='newbusiness') ? ($ll_paid_driver * 5) : $ll_paid_driver,
                        'motor_additional_paid_driver' => $pa_paid_driver,
                        'compulsory_pa_own_driver' => ($premium_type == 'own_damage') ? 0 : (int)$pa_owner_driver,
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' => ($total_od),
                        'cng_lpg_tp' => $additional_tp_prem_cnglpg,
                        'total_liability_premium' => ($total_tp),
                        'net_premium' => ($basePremium),
                        '18.0_amount' => 0,
                        '18.0' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => '',
                        'premium_amount' => ($base_premium_amount),
                        'antitheft_discount' => $anti_theft_discount_key,
                        'final_od_premium' => ($total_od),
                        'final_tp_premium' => ($requestData->business_type =='newbusiness') ?($total_tp - $pa_owner_driver -$ll_paid_driver - $pa_unnamed_person - $additional_tp_prem_cnglpg - $pa_paid_driver - $geog_Extension_TP_Premium) : ($total_tp),
                        'final_total_discount' => ($total_discount),
                        'final_net_premium' => ($base_premium_amount),
                        'final_payable_amount' => ($base_premium_amount),
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
                        'ic_vehicle_discount' => $od_discount,
                        'vehicle_in_90_days' => '0',
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
                        'add_ons_data' => $add_ons_data,
                        'applicable_addons' => $applicable_addons,
                        'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                        'GeogExtension_TPPremium' => ($requestData->business_type =='newbusiness') ? ($geog_Extension_TP_Premium * 5) : $geog_Extension_TP_Premium,
                    ]
                ];
                // if($productData->zero_dep == 0)
                // {
                //     unset($data_response['Data']['GeogExtension_ODPremium']);
                //     unset($data_response['Data']['GeogExtension_TPPremium']);
                // }
                if($is_cng == 'N')
                {
                    unset($data_response['Data']['cng_lpg_tp']);
                    unset($data_response['Data']['motor_lpg_cng_kit_value']);
                }
                if(isset($cpa_tenure ) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
                {
                                                
                    $data_response['Data']['multi_Year_Cpa'] = ($premium_type == 'own_damage') ? 0 : (int)$pa_owner_driver;
                }                           
            }
            else
            {
                $PRetErr = array_search_key('PRetErr', $premium_resp);
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message'        =>  $PRetErr
                ];
            }
            return camelCase($data_response);
        }
        else
        {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status'         => false,
                'message'        => 'Bike Insurer Not found'

            ];
        }
    }
    else
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'This  RTO not available'
        ];
    }
     
    }
    catch (\Exception $e) 
    {
       return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'bike Insurer Not found' . $e->getMessage() . ' line ' . $e->getLine()
        ];
    }
}
