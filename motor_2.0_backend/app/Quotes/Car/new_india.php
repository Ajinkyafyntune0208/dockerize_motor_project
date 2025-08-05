<?php
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\AgentIcRelationship;
use App\Models\NewIndiaDiscountGridV2;
use Spatie\ArrayToXml\ArrayToXml;
use App\Quotes\Car;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

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
    //dd($requestData);
    // if ($requestData->policy_type == 'newbusiness') {
    //     return [
    //         'status' => false,
    //         'premium' => 0,
    //         'message' => 'New Business Not Allowed.',
    //         'request' => [
    //             'policy_type' => $requestData->policy_type,
    //             'message' => 'New Business Not Allowed.',
    //         ]
    //     ];
    // }else
    // {
        // $car_age_in_month = get_date_diff('month', $requestData->vehicle_register_date);
        $car_age_in_month = get_date_diff('month', ($requestData->business_type == 'newbusiness' ? date('d-m-Y') : $requestData->vehicle_register_date));
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $car_age = car_age($vehicleDate, $requestData->previous_policy_expiry_date);
        
        if (($car_age_in_month > 58) && ($productData->zero_dep == 0))
        {
            return [
                'status' => false,
                'premium' => 0,
                'message'     => 'Zero dep is not allowed for vehicle age greater than 58 months'
            ];
        }
        else
        {
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date))/(60*60*24);
            $premium_type = DB::table('master_premium_type')
                        ->where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
            $is_package         = (($premium_type == 'comprehensive') ? true : false);
            $is_liability       = (($premium_type == 'third_party') ? true : false);
            $is_od              = (($premium_type == 'own_damage') ? true : false);
            if($premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Breakin Quotes Not Allowed',
                    'request' => [
                        'policy_type' => $requestData->policy_type,
                        'message' => 'Breakin Quotes Not Allowed',
                        'previous_policy_type' => $requestData->previous_policy_type
                    ]
                ];
            }
            $discount_percent = 20 ;
            if (($date_diff < 15 && $premium_type != 'third_party') || $premium_type == 'third_party' || $requestData->business_type == 'newbusiness')
            {
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
                        'message' => $mmv['message']
                    ];
                }
                $veh_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
                //dd($veh_data);
                $mmv_details = [
                    'manf_name' => $veh_data->motor_make,
                    'model_name' => $veh_data->motor_model,
                    'version_name' => $veh_data->motor_variant,
                    'seating_capacity' => $veh_data->motor_carrying_capacity,
                    'carrying_capacity' => $veh_data->motor_carrying_capacity,
                    'cubic_capacity' => $veh_data->motor_cc,
                    'fuel_type' =>  $veh_data->motor_fule,
                    'vehicle_type' => 'CAR',
                    'version_id' => $veh_data->ic_version_code ,
                ];
                if (empty($veh_data->ic_version_code) || $veh_data->ic_version_code == '') {
                    return [   
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle Not Mapped',
                        'request' => [
                            'mmv_data' => $veh_data,
                            'message' => 'Vehicle Not Mapped',
                        ]
                    ];        
                } elseif ($veh_data->ic_version_code == 'DNE') {
                    return [   
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle code does not exist with Insurance company',
                        'request' => [
                            'mmv_data' => $veh_data,
                            'message' => 'Vehicle code does not exist with Insurance company',
                        ]
                    ];        
                }
                else
                {
                    $city_name =  DB::table('master_rto')
                    ->select('rto_name')
                    ->where('rto_code', $requestData->rto_code)
                    ->where('status', 'Active')
                    ->first();
                    $ex_showroom_price = $veh_data->motor_invoice;
                    if ($requestData->business_type == 'newbusiness')
                        {
                            $total_idv = $veh_data->idv_upto_6_months;
                        }
                        else
                        {
                            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                            $date1 = new DateTime($vehicleDate);
                            $date2 = new DateTime($requestData->previous_policy_expiry_date);
                            $interval = $date1->diff($date2);
                            $age = (($interval->y * 12) + $interval->m) + 1;

                            $age = ceil($age / 12);
                            $total_idv = $mmv['idv_upto_' . (($age > 9) ? 15 : $age) . '_year' . (($age == 1) ? '' : 's')];
                        }
                        if($premium_type == 'third_party')
                        {
                            $total_idv = '0';
                        }
                        $reg_no = explode('-', strtoupper($requestData->rto_code.'-'.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2).'-'.substr(str_shuffle('1234567890'), 1, 4)));
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

                        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                            ->first();
                        $voluntary = 0;
                        $is_antitheft = 'N';
                        if (!empty($additional['discounts'])) {
                            foreach ($additional['discounts'] as $data) {
                                if ($data['name'] == 'anti-theft device') {
                                    $is_antitheft = 'Y';
                                }
                                if ($data['name'] == 'voluntary_insurer_discounts') {
                                    $voluntary = $data['sumInsured'];
                                }
                            }
                        }
                        if (!empty($additional['discounts'])) {
                            foreach ($additional['discounts'] as $data) {
                                if ($data['name'] == 'automobile assiociation') {
                                    $automobile_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime(strtr($requestData->automobile_association_expiry_date, '/', '-'))))));
                                    $aai_name = 'Automobile Association of Eastern India';
                                }
                            }
                        }
                        if ($company_rto_data)
                        {
                            if($requestData->business_type == 'newbusiness')
                            {
                                // $policy_start_date = new DateTime('d/m/Y');
                                $policy_start_date = Date('d/m/Y');

                                $reg_1 = 'NEW';
                                $reg_2 = '0';
                                $reg_3 = 'none';
                                $reg_4 = '0001';

                                $new_vehicle = 'Y';
                            }else
                            {
                                if ($date_diff > 0)
                                {
                                    $policy_start_date = date('d/m/Y', strtotime('+3 day', time()));
                                }
                                else
                                {
                                   $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
 
                                }
                                $automobile_start_date = '';
                                $aai_name = '';
                                
                                $reg_1 = $reg_no[0];
                                $reg_2 = $reg_no[1];
                                $reg_3 = 'XX';
                                $reg_4 = 'XXXX';

                                $new_vehicle = 'N';
                            }
                            $tp_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year', strtotime($requestData->previous_policy_expiry_date)))));
                            $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year', strtotime(strtr($tp_start_date, '/', '-'))))));
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
                            $is_cng ='N';
                            $type_of_fuel = $veh_data->motor_fule;
                            

                            //pos details
                            $is_pos = 'No';
                            $pos_name = null;
                            $pos_name_uiic = null;
                            $partyCode = null;
                            $pos_partyCode = null;
                            $partyStakeCode = null ;
                            $is_pos_testing_mode = config('IC.NEWINDIA.CAR.TESTING_MODE') === 'Y';
                            
                            $pos_data = DB::table('cv_agent_mappings')
                            ->where('user_product_journey_id', $requestData->user_product_journey_id)
                            ->where('seller_type','P')
                            ->first();

                            if($pos_data && !$is_pos_testing_mode)
                            {
                                //properties
                                $is_pos = 'YES';
                                $pos_name = $pos_data->agent_name;
                                $pos_name_uiic = 'uiic';
                                //properties

                                //party
                                $partyCode =  AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                                ->pluck('new_india_code')
                                ->first();
                                if(empty($partyCode) || is_null($partyCode))
                                {
                                    return [
                                        'status' => false,
                                        'premium_amount' => 0,
                                        'message' => 'POS details Not Available'
                                    ];
                                }                
                                $partyStakeCode = 'POS';
                                //party
                            }
                            if($is_pos_testing_mode)
                            {
                                //properties
                                $is_pos = 'YES';
                                $pos_name = 'POS Applicable';
                                $pos_name_uiic = 'uiic';
                                //properties
                                //party
                                $partyCode = config('IC.NEWINDIA.CAR.POS_PARTY_CODE');//PP00000015
                                $partyStakeCode = 'POS';
                                //party
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
                                    $is_geo_ext = false;
                                    $is_geo_code = 0;
                                    $srilanka = 0;
                                    $pak = 0;
                                    $bang = 0;
                                    $bhutan = 0;
                                    $nepal = 0;
                                    $maldive = 0;
                                    if($premium_type == 'third_party')
                                    {
                                        $total_accessories_idv = 0;
                                    }
                                    if (!empty($additional['additional_covers'])) {
                                        foreach ($additional['additional_covers'] as $key => $data) {
                                            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                                                $cover_pa_paid_driver = $data['sumInsured'];
                                               $no_of_paid_drivers = '1';
                                               $include_pa_cover_for_paid_driver = 'Y';
                                            }
                                
                                            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                                                $cover_pa_unnamed_passenger = $data['sumInsured'];
                                                $include_pa_cover_for_unnamed_person = 'Y';
                                                $no_of_unnamed_persons = $veh_data->motor_carrying_capacity;
                                                $capital_si_for_unnamed_persons = ($veh_data->motor_carrying_capacity) *$data['sumInsured'];
                                            }

                                            if ($data['name'] == 'Geographical Extension') {
                                                $is_geo_ext = true;
                                                $is_geo_code = 1;
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
                                    $applicable_addons = [
                                        'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb','engineProtector','consumables','tyreSecure','ncbProtection','returnToInvoice'
                                    ];
                                    if($productData->zero_dep == 0 && $premium_type !== 'third_party')
                                    {
                                        if($car_age_in_month <= 58)
                                        {
                                            $cost_of_consumable_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                                            $cost_of_consumable_cover = 'No';
                                        }
                                        if($car_age_in_month <=34)
                                        {
                                            $tyre_secure_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                                            $tyre_secure_cover = 'No';
                                        }
                                        if($car_age_in_month <=58)
                                        {
                                            $engine_protect_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                                            $engine_protect_cover = 'No';
                                        }
                                        if($car_age_in_month <=58)
                                        {
                                            $key_protect_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                                            $key_protect_cover = 'No';
                                        }
                                        if($car_age_in_month <=58)
                                        {
                                            $personal_belongings_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                                            $personal_belongings_cover = 'No';
                                        } 
                                        if($car_age_in_month <=58)
                                        {
                                            $rsa_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                                            $rsa_cover = 'No';
                                        } 
                                        if($car_age_in_month <=58 && $requestData->is_claim == 'N')
                                        {
                                            $ncb_protect_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                                            $ncb_protect_cover = 'No';
                                        }  
                                        if($car_age_in_month <=34)
                                        {
                                            $rti_cover = 'Yes';
                                        }
                                        else
                                        {
                                            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                                            $rti_cover = 'No';
                                        }
                                        if($car_age_in_month < 36)
                                        {
                                            $discount_column_selector = 'discount_percent_with_addons_0_to_36_months';
                                        }
                                        elseif ($car_age_in_month>=36 && $car_age_in_month <=58) 
                                        {
                                            $discount_column_selector = 'discount_percent_with_addons_37_to_58_months';
                                        }else{
                                            return [
                                                'premium_amount' => 0,
                                                'status' => false,
                                                'message' => 'Zero Dept. - Quotes Not Available for vehicle age greater than 58 months.'

                                            ];
                                        }                           

                                    }
                                    else
                                    {
                                        $cost_of_consumable_cover = 'No';
                                        $engine_protect_cover = 'No';
                                        $tyre_secure_cover = 'No';
                                        $key_protect_cover = 'No';
                                        $personal_belongings_cover = 'No';
                                        $rsa_cover = 'No';
                                        $ncb_protect_cover = 'No';
                                        $rti_cover = 'No';

                                        if($car_age_in_month <= 58) {
                                            $applicable_addons = [
                                                'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb','engineProtector','consumables','tyreSecure','ncbProtection','returnToInvoice'
                                            ];
                                            if($car_age_in_month > 34) {
                                                array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                                                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);                                                
                                            }
                                        }

                                        if($car_age_in_month < 120)
                                        {
                                            $discount_column_selector = 'discount_percent_without_addons_0_to_120_months';
                                        }
                                        elseif ($car_age_in_month>=120 && $car_age_in_month <=178) 
                                        {
                                            $discount_column_selector = 'discount_percent_without_addons_121_to_178_months';
                                        }
                                        else{
                                            return [
                                                'premium_amount' => 0,
                                                'status' => false,
                                                'message' => 'Quotes Not Available for vehicle age greater than 178 months'

                                            ];
                                        }
             

                                    }
                                    if (!empty($additional['additional_covers'])) {
                                        foreach ($additional['additional_covers'] as $data) {
                                            if ($data['name'] == 'LL paid driver') {
                                                $llpd_flag = '1';
                                            }
                                        }
                                    }
                                    $discount_percent = DB::table('new_india_motor_discount_grid')
                                                            ->select("{$discount_column_selector} as discount_col")
                                                            ->where('section','car')
                                                            ->first();
                                    switch ($premium_type) 
                                    {
                                        case 'comprehensive':
                                            $coverCode = 'PACKAGE';
                                            $product_id = '194398713122007';
                                            $product_code = 'PC';
                                            $product_name = 'PRIVATE CAR';
                                            $plan_type = 'NBPL';
                                            break;
                                        case 'third_party' :
                                            $coverCode =  'LIABILITY';
                                            $product_id = '194398713122007';
                                            $product_code = 'PC';
                                            $product_name = 'PRIVATE CAR';
                                            $plan_type = 'NLTL';
                                            break;
                                        case 'own_damage':
                                            $coverCode =  'ODWTOTADON';//'ODWTHADDON';
                                            $product_id = '120087123072019';
                                            $product_code = 'SS';
                                            $product_name = 'Standalone OD policy for PC';
                                            break;
                                        
                                    }

                                    //New Discount Grid #28557
                                    if(config('IC_NEW_INDIA_CAR_DISCOUNT_GRID_V2') == 'Y'){
                                        $discount_percent = NewIndiaDiscountGridV2::select("{$discount_column_selector} as discount_col")
                                                            ->where('section', $product_code)
                                                            ->first();
                                    }

                                    $min_idv = ceil($total_idv * 0.9);
                                    $max_idv = floor($total_idv * 1.1);

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
                                        'typ:coverCode'       => ($productData->zero_dep == '1') ? $coverCode : (($premium_type == 'own_damage') ? 'ODWTHADDON' : 'ENHANCEMENTCOVER'),
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
                                                'typ:value' => $llpd_flag == '1' ? 'Y': 'N',
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
                                            [
                                                'typ:value' => $engine_protect_cover,
                                                'typ:name'  => 'Engine protect cover',
                                            ],
                                            [
                                                'typ:value' => $cost_of_consumable_cover,
                                                'typ:name'  => 'Consumable Items Cover',
                                            ],
                                            [
                                                'typ:value' => $tyre_secure_cover,
                                                'typ:name'  => 'Tyre and Alloy Cover',
                                            ],
                                            [
                                                'typ:value' => $key_protect_cover,
                                                'typ:name'  => 'Key Protect Cover',
                                            ],
                                            [
                                                'typ:value' => $personal_belongings_cover,
                                                'typ:name'  => 'Personal Belongings Cover',
                                            ],
                                            [
                                                'typ:value' => $rsa_cover,
                                                'typ:name'  => 'Additional towing charges cover',
                                            ],
                                            [
                                                'typ:value' => ($rsa_cover == 'Yes') ? '10000' :'0',
                                                'typ:name'  => 'Additional towing charges Amount',
                                            ],
                                            [
                                                'typ:value' => $ncb_protect_cover,
                                                'typ:name'  => 'NCB Protection Cover',
                                            ],
                                            [
                                                'typ:value' => $rti_cover,
                                                'typ:name'  => 'Return to invoice cover',
                                            ],
                                            [
                                                'typ:value' => ($rti_cover == 'Yes') ? $ex_showroom_price :'0',
                                                'typ:name'  => 'Total Ex-Showroom Price',
                                            ],
                                            [
                                                'typ:value' => ($rti_cover == 'Yes') ? '1' :'0',
                                                'typ:name'  => 'First Year Insurance Premium',
                                            ],
                                            [
                                                'typ:value' => ($rti_cover == 'Yes') ? '20' :'0',
                                                'typ:name'  => 'Registration Charges',
                                            ],
                                            


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
                                            'typ:value' => $is_pos,
                                            'typ:name'  => $pos_name_uiic,
                                        ],
                                        [
                                            'typ:value' => 'Private',
                                            'typ:name'  => 'Type of Private Car',
                                        ],
                                        [
                                            'typ:value' => 'New',
                                            'typ:name'  => 'Current Ownership',
                                        ],
                                        [
                                            'typ:value' => 'WHITE',
                                            'typ:name'  => 'Color of Vehicle',
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
                                            'typ:value' => 'N',
                                            'typ:name'  => 'Whether trailer attached to the vehicle',
                                        ],
                                        [
                                            'typ:value' => '0',
                                            'typ:name'  => 'Number of Trailers Attached',
                                        ],
                                        [
                                            'typ:value' => '0',
                                            'typ:name'  => 'Total IDV of the Trailer Attached',
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
                                            'typ:name'  => 'Additional Towing Coverage Amount',
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
                                            'typ:value' => $bang == 1 ? 'Y' : 'N',
                                            'typ:name'  => 'Extension of Geographical Area to Bangladesh',
                                        ],
                                        [
                                            'typ:value' => $bhutan == 1 ? 'Y' : 'N',
                                            'typ:name'  => 'Extension of Geographical Area to Bhutan',
                                        ],
                                        [
                                            'typ:value' => $nepal == 1 ? 'Y' : 'N',
                                            'typ:name'  => 'Extension of Geographical Area to Nepal',
                                        ],
                                        [
                                            'typ:value' => $pak == 1 ? 'Y' : 'N',
                                            'typ:name'  => 'Extension of Geographical Area to Pakistan',
                                        ],
                                        [
                                            'typ:value' => $srilanka == 1 ? 'Y' : 'N',
                                            'typ:name'  => 'Extension of Geographical Area to Sri Lanka',
                                        ],
                                        [
                                            'typ:value' => $maldive == 1 ? 'Y' : 'N',
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
                                            'typ:value' => in_array($type_of_fuel, ['CNG', 'CNGPetrol', 'LPG']) ? 'Y' : 'N',
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
                                            'typ:value' => substr($veh_data->motor_make, 0, 10),
                                            'typ:name'  => 'Make',
                                        ],
                                        [
                                            'typ:value' => $veh_data->motor_model,
                                            'typ:name'  => 'Model',
                                        ],
                                        [
                                            'typ:value' => 'Y',
                                            'typ:name'  => 'Car in roadworthy condition and free from damage',
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
                                            'typ:name'  => 'Whether vehicle is certified as Vintage car by Vintage and Classic Car Club',
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
                                            'typ:value' =>  ($is_geo_ext == 1) ? 'Y' : 'N',
                                            'typ:name'  => 'Extension of Geographical Area required',
                                        ],
                                        [
                                            'typ:value' => 'N',
                                            'typ:name'  => 'Whether Vehicle belong to Embassies or imported without Custom Duty',
                                        ],
                                        [
                                            'typ:value' => (in_array(strtolower($city_name->rto_name), $zone_cities)) ? 'A' : 'B',
                                            'typ:name'  => 'Vehicle Zone for Private Car',
                                        ],
                                        [
                                            'typ:value' => $veh_data->motor_variant,
                                            'typ:name'  => 'Variant',
                                        ],
                                        [
                                            // 'typ:value' => $company_rto_data->rta_name . ' ' . $company_rto_data->rta_address,
                                            'typ:value' => $requestData->business_type == 'newbusiness' ? 'none' : $company_rto_data->rta_name . ' ' . $company_rto_data->rta_address,
                                            'typ:name'  => 'Name and Address of Registration Authority',
                                        ],
                                        [
                                            'typ:value' => $veh_data->motor_cc,
                                            'typ:name'  => '(*)Cubic Capacity',
                                        ],
                                        [
                                            'typ:value' => $veh_data->motor_carrying_capacity,
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
                                            'typ:value' => $car_age_in_month,
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
                                            'typ:value' => ($premium_type == 'own_damage' || $requestData->vehicle_owner_type == "C") ? 'N':'Y',
                                            'typ:name'  => 'Do You Hold Valid Driving License',
                                        ],

                                        [
                                            'typ:value' => ($premium_type == 'own_damage' || $requestData->vehicle_owner_type == "C") ? 'Yes':'No',
                                            'typ:name'  => 'Do you have a general PA cover with motor accident coverage for CSI of atleast 15 lacs?',
                                        ],
                                        [
                                            'typ:value' => ($premium_type == 'own_damage' || $requestData->vehicle_owner_type == "C") ?'Yes':'No',
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
                                            'typ:value' => ($premium_type != 'third_party' ) ? $total_accessories_idv : 0,
                                            'typ:name'  => 'IDV of Accessories',
                                        ],
                                        [
                                            'typ:value' => ($premium_type != 'third_party' ) ? $total_idv + $total_accessories_idv : 0,
                                            'typ:name'  => 'Total IDV',
                                        ],
                                        [
                                            'typ:value' => ($premium_type != 'third_party') ? $requestData->applicable_ncb : 0,
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
                                            // 'typ:value' => strtr($requestData->previous_policy_expiry_date, ['-' => '/']),
                                            'typ:value' => ($requestData->business_type == 'newbusiness' ? '' : strtr($requestData->previous_policy_expiry_date, ['-' => '/'])),
                                            'typ:name'  => 'Expiry date of previous Policy',
                                        ],
                                        // [
                                        //     'typ:value' => $premium_type == 'third_party' ? 'NLTL' : 'NBPL',
                                        //     'typ:name'  => 'Policy Cover Plan Type',
                                        // ],
                                        [
                                            'typ:value' => '0.00',
                                            'typ:name'  => 'Policy Excess (Rs)',
                                        ],
                                        [
                                            'typ:value' => $voluntary,
                                            'typ:name'  => 'Voluntary Excess for PC',
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
                                    if($pos_data)
                                    {
                                        $party  =   [
                                            [
                                                'typ:partyCode'     =>  $partyCode,
                                                'typ:partyStakeCode' =>  'POS',
                                            ],
                                        ];
                                    }
                                    if($is_pos_testing_mode)
                                    {
                                        $party  =   [
                                            [
                                                'typ:partyCode'       =>  $partyCode,
                                                'typ:partyStakeCode'  =>  'POS'
                                            ]
                                        ];
                                    }
                                    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
                                    if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
                                        $addons = $selected_CPA->compulsory_personal_accident;
                                        foreach ($addons as $value) {
                                            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                                                    $cpa_tenure = isset($value['tenure']) ? '3' : '1';
                                                
                                            }
                                        }
                                    }
                                    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage") {
                                        if ($requestData->business_type == 'newbusiness') {
                                            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3';
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
                                        // $premium_array['typ:term'] = '3';
                                        $premium_array['typ:term'] = isset($cpa_tenure) ? $cpa_tenure : 3;
                                        $premium_array['typ:polExpirydate'] = isset($cpa_tenure) && $cpa_tenure == '1' ? date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))):date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))); 
                                    }

                                    unset($properties, $covers, $risk_properties);
                                    $checksum_data = checksum_encrypt($premium_array);
                                    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'new_india',$checksum_data,'CAR');
                                    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                                        $quote_data = $is_data_exits_for_checksum;
                                    }else{
                                        $quote_data = getWsData(config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),$premium_array, 'new_india',
                                        [
                                            'root_tag'      => 'typ:calculatePremiumMasterElement',
                                            'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="http://iims.services/types/"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                                            'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                                            'enquiryId' => $enquiryId,
                                            'requestMethod' =>'post',
                                            'productName'  => $productData->product_name,
                                            'company'  => 'new_india',
                                            'section' => $productData->product_sub_type_code,
                                            'checksum' => $checksum_data,
                                            'method' =>'Premium Calculation - Quote',
                                            'transaction_type' => 'quote',
                                        ]);

                                    }
                                    if ($quote_data['response']) 
                                    {
                                        $premium_resp = XmlToArray::convert((string) remove_xml_namespace($quote_data['response']));
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
                                            $basic_tp_key = 0;
                                            $electrical_key = 0;
                                            $nonelectrical_key = 0;
                                            $basic_od_key = 0;
                                            $calculated_ncb = 0;
                                            $ll_paid_driver = 0;
                                            $pa_paid_driver = 0;
                                            $pa_unnamed_person = 0;
                                            $additional_od_prem_cnglpg = 0;
                                            $additional_tp_prem_cnglpg = 0;
                                            $anti_theft_discount_key = 0;
                                            $aai_discount_key = 0;
                                            $od_discount = 0;
                                            $basePremium = 0;
                                            $total_od = 0;
                                            $total_tp = 0;
                                            $total_discount = 0;
                                            $geog_Extension_OD_Premium = 0;
                                            $geog_Extension_TP_Premium = 0;
                                            $voluntary_Discount = 0;
            
                                            foreach ($premium_resp['Body']['calculatePremiumMasterResponseElement']['properties'] as $key => $cover) 
                                            {
                                                if (in_array($cover['name'], ['Additional Premium for Electrical fitting'])) 
                                                {
                                                    $electrical_key = $cover['value'];
                                                } 
                                                elseif (in_array($cover['name'], ['Additional Premium for Non-Electrical fitting'])) 
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
                                                elseif ($cover['name'] === 'Basic TP Premium' && $requestData->business_type != 'newbusiness') 
                                                {
                                                    $basic_tp_key = $cover['value'];
                                                }
                                                //new business tp premium
                                                elseif (in_array($cover['name'], ['(#)Total TP Premium', '(#)Total TP Premium for 2nd Year', '(#)Total TP Premium for 3rd Year']) && $requestData->business_type == 'newbusiness') 
                                                {
                                                    $basic_tp_key += $cover['value'];
                                                } 
                                                elseif ($cover['name'] === 'Calculated NCB Discount') 
                                                {
                                                    $calculated_ncb = $cover['value'];
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
                                                // elseif (($car_data['zero_dep'] == '0') && ($cover['name'] === 'Premium for enhancement cover'))
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
                                                elseif (($productData->zero_dep == '0') && ($cover['name'] === 'Premium for nil depreciation cover')) {
                                                    $zero_dep_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Engine Protect Cover Premium') {
                                                    $engine_protector_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Consumable Items Cover Premium') {
                                                    $consumable_key = $cover['value'];
                                                } elseif ($cover['name'] == 'NCB Protection Cover Premium') {
                                                    $ncb_protecter_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Tyre and Alloy Cover Premium') {
                                                    $tyre_secure_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Personal Belongings Cover Premium') {
                                                    $personal_belongings_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Additional Towing Charges Cover Premium') {
                                                    $rsa_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Key Protect Cover Premium') {
                                                    $key_protect_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Return to Invoice Cover Premium') {
                                                    $rti_cover_key = $cover['value'];
                                                } elseif ($cover['name'] == 'Calculated Voluntary Deductible Discount') {
                                                    $voluntary_Discount = $cover['value'];
                                                } elseif ($cover['name'] == 'Loading for Extension of Geographical area') {
                                                    $geog_Extension_OD_Premium = $cover['value'];
                                                } elseif ($cover['name'] == 'Extension of Geographical Area Premium') {
                                                    $geog_Extension_TP_Premium = $cover['value'];
                                                }
                                            }
                                            if ($productData->zero_dep == 0) 
                                            {
                                                $add_ons_data = [
                                                    'in_built'   => ['zeroDepreciation'            => (int) ($zero_dep_key)],
                                                    'additional' => [
                                                        'road_side_assistance'        => (int) ($rsa_key),
                                                        'engineProtector'             => (int) ($engine_protector_key),
                                                        'ncbProtection'               => (int) ($ncb_protecter_key),
                                                        'keyReplace'                  => (int) ($key_protect_key),
                                                        'consumables'                 => (int) ($consumable_key),
                                                        'tyreSecure'                  => (int) $tyre_secure_key,
                                                        'returnToInvoice'             => (int) ($rti_cover_key),
                                                        'lopb'                        => (int) ($personal_belongings_key),
                                                        // 'cpa_cover'                   => (int)  $pa_owner_driver
                                                    ],
                                                    'other'      => [],
                                                ];
                                                array_push($applicable_addons, "zeroDepreciation");
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
                                            $total_premium_amount = $net_total_premium;
                                            $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));
                                            foreach ($add_ons_data as $add_on_key => $add_on_value) 
                                            {
                                                if (count($add_on_value) > 0) 
                                                {
                                                    foreach ($add_on_value as $add_on_value_key => $add_on_value_value) 
                                                    {
            
                                                        if (isset($add_on_value[$add_on_value_key])) 
                                                        {
                                                            $value = $add_on_value[$add_on_value_key]; // * (1 + (SERVICE_TAX / 100)));
                                                            $base_premium_amount -= $value;
                                                        } 
                                                        else 
                                                        {
                                                            $value = $add_on_value_value;
                                                        }
                                                        $add_ons[$add_on_key][$add_on_value_key] = $value;
                                                    }
                                                } 
                                                else 
                                                {
                                                    $add_ons[$add_on_key] = $add_on_value;
                                                }
                                            }
                                            $base_premium_amount = $base_premium_amount * (1 + (18.0 / 100));
                                            array_walk_recursive($add_ons, function (&$item, $key) {
                                                if ($item == '' || $item == '0') {
                                                    $item = 'NA';
                                                }
                                            });
                                            $total_od = $basic_od_key + $electrical_key + $nonelectrical_key + $additional_od_prem_cnglpg + $geog_Extension_OD_Premium;
            
                                            $total_tp = $basic_tp_key + $ll_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $pa_paid_driver + $geog_Extension_TP_Premium;
            
                                            $total_discount = $calculated_ncb + $od_discount + $aai_discount_key + $anti_theft_discount_key + $voluntary_Discount;
            
                                            $basePremium = $total_od + $total_tp - $total_discount;
                                            $totalTax = $basePremium * 0.18;
            
                                            $final_premium = $basePremium + $totalTax;
                                            if (array_search('returnToInvoice', $applicable_addons)) {
                                                if ($rti_cover_key == 0) {
                                                    array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                                                }
                                            }
                                            if (array_search('tyreSecure', $applicable_addons)) {
                                                if ($tyre_secure_key == 0) {
                                                    array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                                                }
                                            }
                                            if (empty($add_ons_data['additional']['engineProtector'])) {
                                                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                                            }
                                            if (empty($add_ons_data['additional']['tyreSecure'])) {
                                                array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                                            }
                                            if (empty($add_ons_data['additional']['consumables'])) {
                                                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                                            }
                                            if (empty($add_ons_data['additional']['keyReplace'])) {
                                                array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                                            }
                                            if (empty($add_ons_data['additional']['lopb'])) {
                                                array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                                            }
                                            if (empty($add_ons_data['additional']['road_side_assistance'])) {
                                                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                                            }
                                            if (empty($add_ons_data['additional']['ncbProtection'])) {
                                                array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                                            }
                                            foreach ($add_ons_data['additional'] as $k => $v) {
                                                if (empty($v)) {
                                                    unset($add_ons_data['additional'][$k]);
                                                }
                                            }
                                            $data_response = [
                                                'webservice_id' => $quote_data['webservice_id'],
                                                'table' => $quote_data['table'],
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
                                                    'policy_type' => ($premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive')),
                                                    'cover_type' => '1YC',
                                                    'hypothecation' => '',
                                                    'hypothecation_name' => '',
                                                    'vehicle_registration_no' => $requestData->rto_code,
                                                    'rto_no' => $requestData->rto_code,
                                                    'voluntary_excess' => $voluntary_Discount,
                                                    'version_id' => $veh_data->ic_version_code,
                                                    'showroom_price' => 0,
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
                                                        'policy_end_date' => '',
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
                                                        'car_age' => $car_age,
                                                        'aai_discount' => 0,
                                                        'ic_vehicle_discount' =>  ($od_discount),
                                                    ],
                                                    'basic_premium' => ($basic_od_key),
                                                    'deduction_of_ncb' => (int)$calculated_ncb,
                                                    'tppd_premium_amount' => ($requestData->business_type == 'newbusiness') ? ((int)$basic_tp_key - $pa_owner_driver - $ll_paid_driver - $pa_unnamed_person - $additional_tp_prem_cnglpg - $pa_paid_driver - $geog_Extension_TP_Premium) : (int)$basic_tp_key,
                                                    'motor_electric_accessories_value' => (int)$electrical_key,
                                                    'motor_non_electric_accessories_value' => (int)$nonelectrical_key,
                                                    'motor_lpg_cng_kit_value' => (int)$additional_od_prem_cnglpg,
                                                    'cover_unnamed_passenger_value' => (int)$pa_unnamed_person,
                                                    'seating_capacity' => $veh_data->motor_carrying_capacity,
                                                    'default_paid_driver' => $ll_paid_driver,
                                                    'motor_additional_paid_driver' => $pa_paid_driver,
                                                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                                                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                                                    'compulsory_pa_own_driver' => ($is_od ? 0 : (int)$pa_owner_driver),
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
                                                    'final_tp_premium' => ($requestData->business_type == 'newbusiness') ? ($total_tp - $pa_owner_driver - $ll_paid_driver - $pa_unnamed_person - $additional_tp_prem_cnglpg - $pa_paid_driver - $geog_Extension_TP_Premium) : ($total_tp),
                                                    'final_total_discount' => ($total_discount),
                                                    'final_net_premium' => ($base_premium_amount),
                                                    'final_payable_amount' => ($base_premium_amount),
                                                    'service_data_responseerr_msg' => 'true',
                                                    'user_id' => $requestData->user_id,
                                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                                    'user_product_journey_id' => $requestData->user_product_journey_id,
                                                    'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : (($requestData->business_type == 'rollover') ? 'Roll Over' : $requestData->business_type),
                                                    'service_err_code' => NULL,
                                                    'service_err_msg' => NULL,
                                                    'policyStartDate' => str_replace('/', '-', $policy_start_date),
                                                    'policyEndDate' => '',
                                                    'ic_of' => $productData->company_id,
                                                    'ic_vehicle_discount' => ($od_discount),
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
                                                    'add_ons_data' => $add_ons_data,
                                                    'applicable_addons' => $applicable_addons
                                                ]
                                            ];
                                            // if($productData->zero_dep == 0)
                                            // {
                                            //     unset($data_response['Data']['GeogExtension_ODPremium']);
                                            //     unset($data_response['Data']['GeogExtension_TPPremium']);
                                            // }
                                            if ($is_cng == 'N') {
                                                unset($data_response['Data']['cng_lpg_tp']);
                                                unset($data_response['Data']['motor_lpg_cng_kit_value']);
                                            }
                                            if(isset($cpa_tenure ) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
                                            {
                                                
                                                $data_response['Data']['multi_Year_Cpa'] = ($is_od ? 0 : (int)$pa_owner_driver);
                                            }
                                        } 
                                        else 
                                        {
                                            $PRetErr = array_search_key('PRetErr', $premium_resp);
                                            return [
                                                'webservice_id' => $quote_data['webservice_id'],
                                                'table' => $quote_data['table'],
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
                                            'webservice_id' => $quote_data['webservice_id'],
                                            'table' => $quote_data['table'],
                                            'premium_amount' => 0,
                                            'status'         => 'false',
                                            'message'        => 'Car Insurer Not found'
                                        ];
                                    }
            }
            else
                        {
                            return [
                                'premium_amount' => 0,
                                'status'         => 'false',
                                'message'        => 'This  RTO not available',
                                'request' => [
                                    'rto_code' => $requestData->rto_code,
                                    'message' => 'This  RTO not available',
                                ]
                            ];
                        }
        }
    }
    else
    {
        return [
            'premium_amount' => 0,
            'status'         => 'false',
            'message'        => ''
        ];
    }
  //}
}
}