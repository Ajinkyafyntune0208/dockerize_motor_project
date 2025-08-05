<?php

use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use App\Quotes\Car;
use App\Models\AgentIcRelationship;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    // dd($requestData->vehicle_owner_type);

    $refer_webservice = $productData->db_config['quote_db_cache'];

    // if (($requestData->ownership_changed ?? '') == 'Y') 
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

    $cvCategory = '';

    if ($productData->parent_id == 4) 
    {
        $cvCategory = 'GCV';
    } 
    else if ($productData->parent_id == 8) 
    {
        $cvCategory = 'PCV';
    }

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
        $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date)) / (60 * 60 * 24);
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package         = (($premium_type == 'comprehensive') ? true : false);
        $is_liability       = (($premium_type == 'third_party') ? true : false);
        $is_od              = (($premium_type == 'own_damage') ? true : false);
        if ($premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure')) 
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
        // $discount_percent = 20;
        if (($date_diff < 15 && $premium_type != 'third_party') || $premium_type == 'third_party' || $requestData->business_type == 'newbusiness') {
            // $mmv = get_mmv_details($productData, $requestData->version_id, 'new_india');

            // pcv
            $mmv = array(
                "status" => true,
                "data" => array(
                    "SI_No" => "879925",
                    "vehicle_model_code" => "1080011",
                    "motor_make" => "MARUTI",
                    "motor_model" => "DZIRE",
                    "motor_cc" => "1197",
                    "motor_gvw" => "0",
                    "motor_carrying_capacity" => "5",
                    "motor_variant" => "1.2 LXI OPTION",
                    "motor_product_code" => "CV",
                    "motor_fule" => "PETROL",
                    "motor_zone" => "MUMBAI",
                    "motor_invoice" => "556000",
                    "idv_upto_6_months" => "528200",
                    "idv_upto_1_year" => "472600",
                    "idv_upto_2_years" => "444800",
                    "idv_upto_3_years" => "389200",
                    "idv_upto_4_years" => "333600",
                    "idv_upto_5_years" => "278000",
                    "idv_upto_6_years" => "250200",
                    "idv_upto_7_years" => "222400",
                    "idv_upto_8_years" => "194600",
                    "idv_upto_9_years" => "166800",
                    "idv_upto_15_years" => "139000",
                    "ic_version_code" => "1080011",
                    "no_of_wheels" => "0",
                )
            );

            // gcv
            // $mmv = array(
            //     "status" => true,
            //     "data" => array(
            //         "SI_No" => "879925",
            //         "vehicle_model_code" => "1080011",
            //         "motor_make" => "MAHINDRA",
            //         "motor_model" => "BOLERO",
            //         "motor_cc" => "2523",
            //         "motor_gvw" => "2960",
            //         "motor_carrying_capacity" => "3",
            //         "motor_variant" => "PICK UP",
            //         "motor_product_code" => "CV",
            //         "motor_fule" => "Diesel",
            //         "motor_zone" => "MUMBAI",
            //         "motor_invoice" => "721456",
            //         "idv_upto_6_months" => "685384",
            //         "idv_upto_1_year" => "613238",
            //         "idv_upto_2_years" => "577165",
            //         "idv_upto_3_years" => "505020",
            //         "idv_upto_4_years" => "432874",
            //         "idv_upto_5_years" => "360728",
            //         "idv_upto_6_years" => "324656",
            //         "idv_upto_7_years" => "288583",
            //         "idv_upto_8_years" => "252510",
            //         "idv_upto_9_years" => "216437",
            //         "idv_upto_15_years" => "180364",
            //         "ic_version_code" => "1080011",
            //         "no_of_wheels" => "0",
            //     )
            // );

            // misc-d
            // $mmv = array(
            //     "status" => true,
            //     "data" => array(
            //         "SI_No" => "879925",
            //         "vehicle_model_code" => "1080011",
            //         "motor_make" => "PERFECT ENGINEERING WORKS",
            //         "motor_model" => "TRAILER",
            //         "motor_cc" => "1200",
            //         "motor_gvw" => "4000",
            //         "motor_carrying_capacity" => "2",
            //         "motor_variant" => "GVW 4000",
            //         "motor_product_code" => "CV",
            //         "motor_fule" => "DIESEL",
            //         "motor_zone" => "MUMBAI",
            //         "motor_invoice" => "2194661",
            //         "idv_upto_6_months" => "2084927",
            //         "idv_upto_1_year" => "1865461",
            //         "idv_upto_2_years" => "1755726",
            //         "idv_upto_3_years" => "1536260",
            //         "idv_upto_4_years" => "1316794",
            //         "idv_upto_5_years" => "1097328",
            //         "idv_upto_6_years" => "987597",
            //         "idv_upto_7_years" => "877863",
            //         "idv_upto_8_years" => "768132",
            //         "idv_upto_9_years" => "658397",
            //         "idv_upto_15_years" => "548666",
            //         "ic_version_code" => "1080011",
            //         "no_of_wheels" => "12",
            //     )
            // );

            if ($mmv['status'] == 1) 
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
            $veh_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

            $mmv_details = [
                'manf_name' => $veh_data->motor_make,
                'model_name' => $veh_data->motor_model,
                'version_name' => $veh_data->motor_variant,
                'seating_capacity' => $veh_data->motor_carrying_capacity,
                'carrying_capacity' => $veh_data->motor_carrying_capacity,
                'cubic_capacity' => $veh_data->motor_cc,
                'fuel_type' =>  $veh_data->motor_fule,
                'vehicle_type' => $productData->product_sub_type_code,
                'version_id' => $veh_data->ic_version_code,
            ];

            if (empty($veh_data->ic_version_code) || $veh_data->ic_version_code == '') 
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle Not Mapped',
                    'request' => [
                        'mmv_data' => $veh_data,
                        'message' => 'Vehicle Not Mapped',
                    ]
                ];
            } 
            else if ($veh_data->ic_version_code == 'DNE') 
            {
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
                    // $date1 = new DateTime($requestData->vehicle_register_date);
                    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
                    $date1 = new DateTime($vehicleDate);
                    $date2 = new DateTime($requestData->previous_policy_expiry_date);
                    $interval = $date1->diff($date2);
                    $age = (($interval->y * 12) + $interval->m) + 1;

                    $age = ceil($age / 12);
                    $total_idv = $mmv['idv_upto_' . (($age > 9) ? 15 : $age) . '_year' . (($age == 1) ? '' : 's')];
                }
                if ($premium_type == 'third_party') 
                {
                    $total_idv = '0';
                }
                $reg_no = explode('-', strtoupper($requestData->rto_code . '-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2) . '-' . substr(str_shuffle('1234567890'), 1, 4)));
                if (($reg_no[0] == 'DL') && (intval($reg_no[1]) < 10)) 
                {
                    $requestData->vehicle_registration_no = $reg_no[0] . '-0' . $reg_no[1];
                }
                $company_rto_data = DB::table('new_india_rto_master')
                    ->where('rto_code', strtr($requestData->rto_code, ['-' => '']))
                    ->first();
                // $pincode_data = DB::table('new_india_pincode_master')
                //     ->where('geo_area_code_1', $reg_no[0])
                //     ->orderBy('pin_code', 'DESC')
                //     ->first();

                $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                    ->first();
                $voluntary = 0;
                $is_antitheft = 'N';
                if (!empty($additional['discounts'])) 
                {
                    foreach ($additional['discounts'] as $data) 
                    {
                        if ($data['name'] == 'anti-theft device') 
                        {
                            $is_antitheft = 'Y';
                        }
                        if ($data['name'] == 'voluntary_insurer_discounts') 
                        {
                            $voluntary = $data['sumInsured'];
                        }
                    }
                }
                if (!empty($additional['discounts'])) 
                {
                    foreach ($additional['discounts'] as $data) 
                    {
                        if ($data['name'] == 'automobile assiociation') 
                        {
                            $automobile_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime(strtr($requestData->automobile_association_expiry_date, '/', '-'))))));
                            $aai_name = 'Automobile Association of Eastern India';
                        }
                    }
                }

                if ($company_rto_data) 
                {
                    if ($requestData->business_type == 'newbusiness') 
                    {
                        $policy_start_date = date('d/m/Y');
                        $reg_1 = 'NEW';
                        $reg_2 = '0';
                        $reg_3 = 'none';
                        $reg_4 = '0001';
                        $new_vehicle = 'Y';
                    } 
                    else 
                    {
                        if ($date_diff > 0) 
                        {
                            $policy_start_date = date('d/m/Y', strtotime('+3 day', time()));
                        } 
                        else 
                        {
                            $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                        }

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
                    $tyre_secure_cover = 'No';
                    $engine_protect_cover = 'No';
                    $key_protect_cover = 'No';
                    $personal_belongings_cover = 'No';
                    $rsa_cover = 'No';
                    $ncb_protect_cover = 'No';
                    $rti_cover = 'No';
                    $llpd_flag = 'N';
                    $bi_fuel_system_value = '0.00';
                    $no_of_paid_drivers = '0';
                    $no_of_paid_cleaners = '0';
                    $no_of_paid_conductors = '0';
                    $capital_si_for_unnamed_persons = '0';
                    $electrical_amt = '0';
                    $non_electrical_amt = '0';
                    $cover_pa_paid_driver = '0';
                    $cover_pa_unnamed_passenger = '0';
                    $no_of_unnamed_persons = '0';
                    $is_cng = 'N';
                    $type_of_fuel = $veh_data->motor_fule;

                    // POS
                    $is_pos = 'No';
                    $pos_name = null;
                    $pos_name_uiic = null;
                    $partyCode = null;
                    $partyStakeCode = null;
                    $pos_partyCode = null;
                    $pos_partyStakeCode = null;
                    $is_pos_testing_mode = config('IC.NEWINDIA.CV.TESTING_MODE') === 'Y';

                    $pos_data = DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id', $requestData->user_product_journey_id)
                        ->where('seller_type', 'P')
                        ->first();

                    if ($pos_data && !$is_pos_testing_mode) {
                        //Properties
                        $is_pos = 'YES';
                        $pos_name = $pos_data->agent_name;
                        $pos_name_uiic = 'uiic';

                        //party
                        $partyCode = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                        ->pluck('new_india_code')
                        ->first();
                        $pos_partyStakeCode = $pos_name;
                        if (empty($partyCode) || is_null($partyCode)) {
                            return [
                                'status' => false,
                                'premium_amount' => 0,
                                'message' => 'POS details Not Available'
                            ];
                        }
                        $partyStakeCode = 'POS';
                    }
                    if ($is_pos_testing_mode == 'Y') {
                        //properties
                        $is_pos = 'YES';
                        $pos_name = 'POS Applicable';
                        $pos_name_uiic = 'uiic';
                        //properties
                        //party
                        $partyCode = config('IC.NEWINDIA.CV.POS_PARTY_CODE'); //PP00000015
                        $partyStakeCode = 'POS';
                        //party
                    }
                    // dd($additional['accessories']);

                    if (!empty($additional['accessories'])) 
                    {
                        foreach ($additional['accessories'] as $key => $data) 
                        {
                            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') 
                            {
                                $bi_fuel_system_value = $data['sumInsured'];
                                $type_of_fuel = 'CNG'; /* .$veh_data->motor_fule; */
                                $is_cng = 'Y';
                            }
                            else if ($data['name'] == 'Non-Electrical Accessories') 
                            {
                                $non_electrical_accessories_flag = 'Y';
                                $non_electrical_amt = $data['sumInsured'];
                            }
                            else if ($data['name'] == 'Electrical Accessories') 
                            {
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
                    if ($premium_type == 'third_party') {
                        $total_accessories_idv = 0;
                    }

                    // dd($additional['additional_covers']);

                    if (!empty($additional['additional_covers'])) 
                    {
                        foreach ($additional['additional_covers'] as $key => $data) 
                        {
                            if ($data['name'] == 'PA paid driver/conductor/cleaner' && isset($data['sumInsured'])) 
                            {
                                $cover_pa_paid_driver = $data['sumInsured'];
                                $no_of_paid_drivers = '1';
                                $no_of_paid_conductors = '1';
                                $no_of_paid_cleaners = '1';
                                $include_pa_cover_for_paid_driver = 'Y';
                            }
                            else if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) 
                            {
                                $cover_pa_unnamed_passenger = $data['sumInsured'];
                                $include_pa_cover_for_unnamed_person = 'Y';
                                $no_of_unnamed_persons = $veh_data->motor_carrying_capacity;
                                $capital_si_for_unnamed_persons = $data['sumInsured'];
                            }

                            if ($data['name'] == 'Geographical Extension' && $productData->zero_dep != 0) 
                            {
                                $is_geo_ext = true;
                                $is_geo_code = 1;
                                $countries = $data['countries'];
                                if (in_array('Sri Lanka', $countries)) 
                                {
                                    $srilanka = 1;
                                }
                                if (in_array('Bangladesh', $countries)) 
                                {
                                    $bang = 1;
                                }
                                if (in_array('Bhutan', $countries)) 
                                {
                                    $bhutan = 1;
                                }
                                if (in_array('Nepal', $countries)) 
                                {
                                    $nepal = 1;
                                }
                                if (in_array('Pakistan', $countries)) 
                                {
                                    $pak = 1;
                                }
                                if (in_array('Maldives', $countries)) 
                                {
                                    $maldive = 1;
                                }
                            }
                        }
                    }
                    $tppd_flag = 'N';
                    $tppd_amount = '';
                    $ll_no_cleaner = '';
                    $ll_no_conductor = '';
                    $ll_no_driver = '';
                    $own_premises_limited = 'N';
                    if (!empty($additional['discounts'])) 
                    {
                        foreach ($additional['discounts'] as $key => $data) 
                        {
                            if ($data['name'] == 'Vehicle Limited to Own Premises') 
                            {
                                $own_premises_limited = 'Y';
                            }
                            else if ($data['name'] == 'TPPD Cover') 
                            {
                                $tppd_flag = 'Y';
                                $tppd_amount = '6000';
                            }
                        }
                    }

                    $applicable_addons = [
                        'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb', 'engineProtector', 'consumables', 'tyreSecure', 'ncbProtection', 'returnToInvoice'
                    ];

                    if ($premium_type !== 'third_party') 
                    {
                        if ($car_age_in_month <= 58) 
                        {
                            $cost_of_consumable_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                            $cost_of_consumable_cover = 'No';
                        }
                        if ($car_age_in_month <= 34) 
                        {
                            $tyre_secure_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                            $tyre_secure_cover = 'No';
                        }
                        if ($car_age_in_month <= 58) 
                        {
                            $engine_protect_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                            $engine_protect_cover = 'No';
                        }
                        if ($car_age_in_month <= 58) 
                        {
                            $key_protect_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                            $key_protect_cover = 'No';
                        }
                        if ($car_age_in_month <= 58) 
                        {
                            $personal_belongings_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                            $personal_belongings_cover = 'No';
                        }
                        if ($car_age_in_month <= 58) 
                        {
                            $rsa_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                            $rsa_cover = 'No';
                        }
                        if ($car_age_in_month <= 58 && $requestData->is_claim == 'N') 
                        {
                            $ncb_protect_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                            $ncb_protect_cover = 'No';
                        }
                        if ($car_age_in_month <= 34) 
                        {
                            $rti_cover = 'Yes';
                        } 
                        else 
                        {
                            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                            $rti_cover = 'No';
                        }
                    }

                    if ($car_age_in_month > 34) 
                    {
                        array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                        array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                    }


                    if (!empty($additional['additional_covers'])) 
                    {
                        foreach ($additional['additional_covers'] as $data) 
                        {
                            if ($data['name'] == 'LL paid driver/conductor/cleaner') 
                            {
                                $llpd_flag = 'Y';
                                in_array("DriverLL", $data['selectedLLpaidItmes']) ? $ll_no_driver = $data['LLNumberDriver'] : '0';
                                in_array("ConductorLL", $data['selectedLLpaidItmes']) ? $ll_no_conductor = $data['LLNumberConductor'] : '0';
                                in_array("CleanerLL", $data['selectedLLpaidItmes']) ? $ll_no_cleaner = $data['LLNumberCleaner'] : '0';
                            }

                            else if ($data['name'] == 'PA cover for additional paid driver') 
                            {
                                $cover_pa_paid_driver = $data['sumInsured'];
                                $no_of_paid_drivers = '1';
                                $include_pa_cover_for_paid_driver = 'Y';
                            }

                            else if ($data['name'] == 'LL paid driver') 
                            {
                                $llpd_flag = 'Y';
                                $ll_no_driver = '1';
                            }
                        }
                    }

                    // $discount_percent = DB::table('new_india_motor_discount_grid')
                    //     ->select("{$discount_column_selector} as discount_col")
                    //     ->where('section', 'car')
                    //     ->first();

                    switch ($premium_type) 
                    {
                        case 'comprehensive':
                            $coverCode = 'PACKAGE';
                            $product_id = '194731914122007';
                            $product_code = 'CV';
                            $product_name = 'Commercial Vehicle';
                            break;
                        case 'third_party':
                            $coverCode =  'LIABILITY';
                            $product_id = '194398713122007';
                            $product_code = 'CV';
                            $product_name = 'Commercial Vehicle';
                            break;
                        case 'own_damage':
                            $coverCode =  'ODWTOTADON'; //'ODWTHADDON';
                            $product_id = '120087123072019';
                            $product_code = 'SS';
                            $product_name = 'Standalone OD policy for PC';
                            break;
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
                        'typ:productCode' => '',
                        'typ:policyDetailId' => '',
                        'typ:coverCode' => ($productData->zero_dep == 1) ? $coverCode : (($premium_type== 'own_damage') ? 'ODWTHADDON' : 'EHMNTCOVER'),
                        'typ:productId' => '',
                        'typ:coverExpiryDate' => '',
                        'typ:properties' => [
                            [
                                'typ:value' => $tppd_flag,
                                'typ:name' => 'Do You want to reduce TPPD cover to the statutory limit of Rs.6000',
                            ],
                            [
                                'typ:value' => $tppd_amount,
                                'typ:name' => 'Sum Insured for TPPD',
                            ],
                            [
                                'typ:value' => '',
                                'typ:name' => 'Sum Insured for PA cover',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name' => 'LL under WCA,for carriage of more than six employees(excluding the Driver)',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name' => 'Number of Additional LL Employees',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name' => 'LL to persons employed for opn and/or maint.and/or loading and/or unloading',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name' => 'Number Of LL Employees',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name' => 'LL to Non-fare Paying Passengers,Owner of goods(Not Employees of the Insured)',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name' => 'Number of LL Non fare Paying Passengers(Excluding Employee)',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name' => 'LL to Non-fare Paying Passengers (Employee of Insurd but not Workmen under WCA)',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name' => 'Number of LL Non fare Paying Passengers(Including Employee)',
                            ],
                            [
                                'typ:value' => $include_pa_cover_for_paid_driver,
                                'typ:name' => 'Do you wish to include PA Cover for Paid Drivers, Cleaner, Conductor',
                            ],
                            [
                                'typ:value' => $no_of_paid_drivers,
                                'typ:name' => 'No of Paid Drivers',
                            ],
                            [
                                'typ:value' => $no_of_paid_cleaners,
                                'typ:name' => 'No of Cleaners',
                            ],
                            [
                                'typ:value' => $no_of_paid_conductors,
                                'typ:name' => 'No of Conductors',
                            ],
                            [
                                'typ:value' => (string) $cover_pa_paid_driver,
                                'typ:name' => 'Capital SI for Driver,Cleaner,Conductor per Person',
                            ],
                            [
                                'typ:value' => $include_pa_cover_for_unnamed_person,
                                'typ:name' => 'Do you want to include PA cover for unnamed person',
                            ],
                            [
                                'typ:value' => $no_of_unnamed_persons,
                                'typ:name' => 'No of unnamed Persons',
                            ],
                            [
                                'typ:value' => $capital_si_for_unnamed_persons,
                                'typ:name' => 'Capital SI for unnamed Persons',
                            ],
                            [
                                'typ:value' => $llpd_flag,
                                'typ:name' => 'LL to paid driver and/or conductor and/or cleaner employed for operation',
                            ],
                            [
                                'typ:value' => $ll_no_driver,
                                'typ:name' => 'Number of LL paid driver',
                            ],
                            [
                                'typ:value' => $ll_no_conductor,
                                'typ:name' => 'Number of LL conductor',
                            ],
                            [
                                'typ:value' => $ll_no_cleaner,
                                'typ:name' => 'Number of LL cleaner',
                            ],
                            [
                                'typ:value' => 'N',
                                'typ:name' => 'LL to Non-fare Paying Passengers (Not Employees of the Insured and not Workmen under WCA)',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name' => 'Number of LL Non-fare Paying Passengers(Not Employees)',
                            ],
                            [
                                'typ:value' => '0',
                                'typ:name' => 'Capital SI for Drivers',
                            ],
                            [
                                'typ:value' => 'A',
                                'typ:name' => 'Type of Liability Coverage',
                            ],
                            [
                                'typ:value' => $cvCategory == 'MISC' ? 'A' : 'B',
                                'typ:name' => 'Type of Enhancement cover',
                            ],

                            // [
                            //     'typ:value' => 'Yes',
                            //     'typ:name' => 'Nil depreciation',
                            // ],

                            // addons 
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
                                'typ:value' => ($rsa_cover == 'Yes') ? '10000' : '0',
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
                                'typ:value' => ($rti_cover == 'Yes') ? $ex_showroom_price : '0',
                                'typ:name'  => 'Total Ex-Showroom Price',
                            ],
                            [
                                'typ:value' => ($rti_cover == 'Yes') ? '1' : '0',
                                'typ:name'  => 'First Year Insurance Premium',
                            ],
                            [
                                'typ:value' => ($rti_cover == 'Yes') ? '20' : '0',
                                'typ:name'  => 'Registration Charges',
                            ],

                        ],
                    ];

                    $properties = [
                        [
                            'typ:value' => $is_pos,
                            'typ:name'  => $pos_name,
                        ],
                        [
                            'typ:value' => 'SKIInsurance',
                            'typ:name' => 'channelcode',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Fire explosion self ignition or lightning peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Burglary housebreaking or theft peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Riot and strike peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Earthquake damage peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Flood typhoon hurricane storm tempest inundation cyclone hailstorm frost peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Accidental external means peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Malicious act peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Terrorist activity peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Whilst in transit by road rail inland-waterway lift elevator or air peril required',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Landslide rockslide peril required',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name' => 'Is it declaration type policy',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name' => 'Is Service Tax Exempted',
                        ],
                        [
                            'typ:value' => 'NO',
                            'typ:name' => 'Co-Insurance Applicable',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name' => 'Individual agent Commission for OD',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name' => 'Corporate Agent Commission for OD',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name' => 'Is Business Sourced from Tie Up',
                        ],
                        [
                            'typ:value' => 'Non-Dealer',
                            'typ:name' => 'Auto Tie Up Type',
                        ],
                        [
                            'typ:value' => '0',
                            'typ:name' => 'Broker Commission for OD',
                        ],
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
                            'typ:value' => $requestData->vehicle_register_date,
                            'typ:name' => 'Date of Purchase of Vehicle by Proposer',
                        ],
                        [
                            'typ:value' => 'New',
                            'typ:name' => 'Vehicle New or Second hand at the time of purchase',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name' => 'Vehicle Used for Private,Social,domestic,pleasure,professional purpose',
                        ],
                        [
                            'typ:value' => 'Y',
                            'typ:name' => 'Is vehicle in good condition',
                        ],
                        [
                            'typ:value' => 'NA',
                            'typ:name' => 'Give Vehicle Details',
                        ],
                        [
                            'typ:value' => 'NO',
                            'typ:name' => 'Whether the Vehicle is Government Vehicle',
                        ],
                        [
                            'typ:value' => $cvCategory == 'GCV' ? 'A' : ($cvCategory == 'MISC' ? 'D' : 'C'),
                            'typ:name' => 'Type of Commercial Vehicles',
                        ],
                        [
                            'typ:value' => $cvCategory == 'GCV' ? 'OTH-PBC' : 'NA',
                            'typ:name' => 'Type of Goods Carrying',
                        ],
                        [
                            'typ:value' => 'NA',
                            'typ:name' => 'Goods Carrying vehicle description',
                        ],
                        [
                            'typ:value' => $cvCategory == 'GCV' ? 'C' : 'NA',
                            'typ:name' => 'Type of Body ( Goods carrying )',
                        ],
                        [
                            'typ:value' => $cvCategory == 'PCV' ? '4WH-' . $veh_data->motor_carrying_capacity : 'NA',
                            'typ:name' => 'Type of Passenger Carrying',
                        ],
                        [
                            'typ:value' => $cvCategory == 'PCV' ? 'C' : 'NA',
                            'typ:name' => 'Type of Body ( Passenger Carrying )',
                        ],
                        [
                            'typ:value' => $cvCategory == 'MISC' ? 'AMB' : 'NA',
                            'typ:name' => 'Type of Misc & Special Type',
                        ],
                        [
                            'typ:value' => $cvCategory == 'MISC' ? 'C' : 'NA',
                            'typ:name' => 'Type of Body ( Misc & Special Type )',
                        ],
                        [
                            'typ:value' => $cvCategory == 'MISC' ? 'OTHERS' : 'NA',
                            'typ:name' => 'Type of Body ( Others )',
                        ],
                        [
                            'typ:value' => 'NA',
                            'typ:name' => 'Type of Road Risk',
                        ],
                        [
                            'typ:value' => 'N',
                            'typ:name' => 'Is Vehicle AC?',
                        ],
                        [
                            'typ:value' => 'BLACK',
                            'typ:name' => 'Color of Vehicle',
                        ],
                        [
                            'typ:value' => 'Black',
                            'typ:name' => 'Color as per RC book',
                        ],
                        [
                            'typ:value' => (in_array(strtolower($city_name->rto_name), $zone_cities)) ? 'A' : 'B',
                            'typ:name' => 'Vehicle Zone for CV',
                        ],
                        [
                            'typ:value' => 'NA',
                            'typ:name' => 'Vehicle zone for CV(C1-C4)',
                        ],
                        [
                            'typ:value' => $new_vehicle,
                            'typ:name' => 'New Vehicle',
                        ],
                        [
                            'typ:value' => $motor_manf_year,
                            'typ:name' => "Year of Manufacture"
                        ],
                        [
                            'typ:value' => $vehicleDate,
                            'typ:name' => "Date of Sale"
                        ],
                        [
                            'typ:value' => $reg_1,
                            'typ:name' => "Registration No (1)"
                        ],
                        [
                            'typ:value' => $reg_2,
                            'typ:name' => "Registration No (2)"
                        ],
                        [
                            'typ:value' => $reg_3,
                            'typ:name' => "Registration No (3)"
                        ],
                        [
                            'typ:value' => $reg_4,
                            'typ:name' => "Registration No (4)"
                        ],
                        [
                            'typ:value' => $requestData->vehicle_register_date,
                            'typ:name' => "Registration Date"
                        ],
                        [
                            'typ:value' => "31/12/2030",
                            'typ:name' => "Registration Validity Date"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Purpose of Using Passenger Vehicle(C1)"
                        ],
                        [
                            'typ:value' => $cvCategory == 'PCV' ? 'PUB' : 'NA',
                            'typ:name' => "Purpose of Using Passenger Vehicle(C2,C3,C4)"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Other Purpose of Using Vehicle"
                        ],
                        [
                            'typ:value' => "Y",
                            'typ:name' => "Vehicle in roadworthy condition and free from damage"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Loading amount for not Roadworthy Condition"
                        ],
                        [
                            'typ:value' => ($premium_type != 'third_party') ? $ex_showroom_price : 0,
                            'typ:name' => "Vehicle Invoice Value"
                        ],
                        [
                            'typ:value' => $car_age_in_month,
                            'typ:name' => "Vehicle Age"
                        ],
                        [
                            'typ:value' => ($premium_type != 'third_party') ? $total_idv : 0,
                            'typ:name' => "Insureds declared Value (IDV)"
                        ],
                        [
                            'typ:value' => "Yes",
                            'typ:name' => "Vehicle Used for Carriage of Own Goods (IMT-42)"
                        ],
                        [
                            'typ:value' => $requestData->business_type == 'newbusiness' ? 'none' : $company_rto_data->rta_name . ' ' . $company_rto_data->rta_address,
                            'typ:name' => "Name and Address of Registration Authority"
                        ],
                        [
                            'typ:value' => "GHF1A12268",
                            'typ:name' => "(*)Engine No"
                        ],
                        [
                            'typ:value' => "F1A17721",
                            'typ:name' => "(*)Chassis No"
                        ],
                        [
                            'typ:value' => substr($veh_data->motor_make, 0, 10),
                            'typ:name' => "Make"
                        ],
                        [
                            'typ:value' => $veh_data->motor_model,
                            'typ:name' => "Model"
                        ],
                        [
                            'typ:value' => $veh_data->motor_variant,
                            'typ:name' => "Variant"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Transit From"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Transit To"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Distance Covered"
                        ],
                        [
                            'typ:value' => $electrical_accessories_flag,
                            'typ:name' => "Extra Electrical/ Electronic fittings"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Value of Music System"
                        ],
                        [
                            'typ:value' => "0.00",
                            'typ:name' => "Value of AC/Fan"
                        ],
                        [
                            'typ:value' => "0.00",
                            'typ:name' => "Value of Lights"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Value of Other Fittings"
                        ],
                        [
                            'typ:value' => $electrical_amt,
                            'typ:name' => "Total Value of Extra Electrical/ Electronic fittings"
                        ],
                        [
                            'typ:value' => $non_electrical_accessories_flag,
                            'typ:name' => "Non-Electrical/ Electronic fittings"
                        ],
                        [
                            'typ:value' => $non_electrical_amt,
                            'typ:name' => "Value of Non- Electrical/ Electronic fittings"
                        ],
                        [
                            'typ:value' => $type_of_fuel,
                            'typ:name' => "Type of Fuel"
                        ],
                        [
                            'typ:value' => in_array($type_of_fuel, ['CNG', 'CNGPetrol', 'LPG']) ? 'Y' : 'N',
                            'typ:name' => "In Built Bi-fuel System fitted"
                        ],
                        [
                            'typ:value' => $bi_fuel_system_value,
                            'typ:name' => "Bi-fuel System Value"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Fibre glass fuel tanks"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Value of Fibre glass fuel tanks"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether it is a Two wheeler"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Is side car attached with a two wheeler"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Indemnity to Hirers"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Additional Towing Coverage Required"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Additional Towing Coverage Amount"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Is IMT 23 to be deleted"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Number of Trailers"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Type of Trailer"
                        ],
                        [
                            'typ:value' => ($premium_type != 'third_party') ? $total_idv + $total_accessories_idv : 0,
                            'typ:name' => "Total IDV"
                        ],
                        [
                            'typ:value' => $veh_data->motor_carrying_capacity,
                            'typ:name' => "(*)Seating Capacity"
                        ],
                        [
                            'typ:value' => $veh_data->motor_carrying_capacity,
                            'typ:name' => "(*)Carrying Capacity"
                        ],
                        [
                            'typ:value' => $veh_data->motor_cc,
                            'typ:name' => "(*)Cubic Capacity"
                        ],
                        [
                            'typ:value' => $veh_data->motor_gvw,
                            'typ:name' => "(*)Gross Vehicle Weight(GVW)"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether vehicle is used for driving tuition"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether vehicle belongs to foreign embassy or consulate"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether vehicle belongs to foreign embassys"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether vehicle designed/modified for visually impaired /physically challenged"
                        ],
                        [
                            'typ:value' => ($is_antitheft == 'Y') ? 'Y' : 'N',
                            'typ:name' => "Is the vehicle fitted with Anti-theft device"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether Vehicle designed as commercial vehicle and used for commercial and private purpose"
                        ],
                        [
                            'typ:value' => $own_premises_limited,
                            'typ:name' => "Vehicle use is limited to own premises"
                        ],
                        [
                            'typ:value' => ($premium_type != 'third_party') ? $requestData->applicable_ncb : "0",
                            'typ:name' => "NCB Applicable Percentage"
                        ],
                        [
                            'typ:value' => "NA", // "Cholomandalam",
                            'typ:name' => "Name of Previous Insurer"
                        ],
                        [
                            'typ:value' => "", // "CHOLO1223323",
                            'typ:name' => "Previous Policy No"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Address of the Previous Insurer"
                        ],
                        [
                            'typ:value' => ($requestData->business_type == 'newbusiness' ? '01/01/0001' : strtr($requestData->previous_policy_expiry_date, ['-' => '/'])),
                            'typ:name' => "Expiry date of previous Policy"
                        ],
                        [
                            'typ:value' => "GOODS",
                            'typ:name' => "Vehicle Permit Details"
                        ],
                        [
                            'typ:value' => "Y",
                            'typ:name' => "Do You Hold Valid Driving License"
                        ],
                        [
                            'typ:value' => $cvCategory == 'PCV' ? 'MPMOTORVEH' : 'MGOODSVEH',
                            'typ:name' => "License Type of Owner Driver"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Age of Owner Driver"
                        ],
                        [
                            'typ:value' => "none",
                            'typ:name' => "Owner Driver Driving License No"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Owner Driver License Issue Date"
                        ],
                        [
                            'typ:value' => "01/01/0001",
                            'typ:name' => "Owner Driver License Expiry Date"
                        ],
                        [
                            'typ:value' => "abcd",
                            'typ:name' => "License Issuing Authority for Owner Driver"
                        ],
                        [
                            'typ:value' => "G JENA",
                            'typ:name' => "Name of Nominee"
                        ],
                        [
                            'typ:value' => "55",
                            'typ:name' => "Age of Nominee"
                        ],
                        [
                            'typ:value' => "FATHER",
                            'typ:name' => "Relationship with the Insured"
                        ],
                        [
                            'typ:value' => "Male",
                            'typ:name' => "Gender of the Nominee"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Name of the Appointee (if Nominee is a minor)"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Relationship to the Nominee"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Any of the driver ever convicted or any prosecution pending"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Details of Conviction"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "In last 3 years any driver involved in any accident or loss"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Do you Have Any other Driver"
                        ],
                        [
                            'typ:value' => "OTHERS",
                            'typ:name' => "Driver Type"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Driver Name"
                        ],
                        [
                            'typ:value' => ($premium_type == 'own_damage' || $requestData->vehicle_owner_type == "C") ? 'N' : 'Y',
                            'typ:name' => "Do you hold a valid license No."
                        ],
                        [
                            'typ:value' => "01/01/0001",
                            'typ:name' => "Issue Date"
                        ],
                        [
                            'typ:value' => "01/01/0001",
                            'typ:name' => "Date of birth"
                        ],
                        [
                            'typ:value' => "M",
                            'typ:name' => "Sex"
                        ],
                        [
                            'typ:value' => "NA",
                            'typ:name' => "Address"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "License Number"
                        ],
                        [
                            'typ:value' => "01/01/0001",
                            'typ:name' => "Expiry Date"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Age"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Experience"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Whether eligible for special discount"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Loading amount for OD"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Loading amount for TP"
                        ],
                        [
                            'typ:value' => ($is_geo_ext == 1) ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area required"
                        ],
                        [
                            'typ:value' => $bang == 1 ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area to Bangladesh"
                        ],
                        [
                            'typ:value' => $bhutan == 1 ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area to Bhutan"
                        ],
                        [
                            'typ:value' => $nepal == 1 ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area to Nepal"
                        ],
                        [
                            'typ:value' => $pak == 1 ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area to Pakistan"
                        ],
                        [
                            'typ:value' => $srilanka == 1 ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area to Sri Lanka"
                        ],
                        [
                            'typ:value' => $maldive == 1 ? 'Y' : 'N',
                            'typ:name' => "Extension of Geographical Area to Maldives"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Vehicle Requisitioned by Government"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Policy Excess (Rs)"
                        ],
                        [
                            'typ:value' => "",
                            'typ:name' => "Imposed Excess (Rs)"
                        ],
                        [
                            'typ:value' => ($premium_type == 'third_party') ? '0' : '0',
                            'typ:name' => "OD discount (%)"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Commercial Vehicle Type G"
                        ],
                        [
                            'typ:value' => "N",
                            'typ:name' => "Commercial Vehicle Type G or F"
                        ],
                        [
                            'typ:value' => "0",
                            'typ:name' => "Additional Loading for vehicles"
                        ]
                    ];

                    $party = null;
                    if ($pos_data) {
                        $party = [
                            [
                                'typ:partyCode' =>  $partyCode,
                                'typ:partyStakeCode' =>  'POS',
                            ],
                        ];
                    }
                    if ($is_pos_testing_mode == 'Y') {
                        $party = [
                            [
                                'typ:partyCode' =>  $partyCode,
                                'typ:partyStakeCode'  =>  $partyStakeCode,
                            ],
                        ];
                    }


                    $premium_array = [
                        'typ:userCode'        => config('constants.IcConstants.new_india.USERCODE_NEW_INDIA'),#USRPB
                        'typ:rolecode'        => config('constants.IcConstants.new_india.ROLE_CODE_NEW_INDIA'),#'SUPERUSER',
                        'typ:PRetCode' => '1',
                        'typ:userId' => '',
                        'typ:roleId' => '',
                        'typ:userroleId' => '',
                        'typ:branchcode' => '',
                        'typ:PRetErr' => '',
                        'typ:polBranchCode' => '',
                        'typ:productName' => $product_name,
                        'typ:policyHoldercode' => $reg_no[0],
                        'typ:eventDate' => $policy_start_date,
                        'typ:party'     => $party,
                        'typ:netPremium' => '',
                        'typ:termUnit' => 'G',
                        'typ:grossPremium' => '',
                        'typ:policyType' => '',
                        'typ:branchCode' => '',
                        'typ:polInceptiondate' => $policy_start_date,
                        'typ:term' => '1',
                        'typ:polEventEffectiveEndDate' => '',
                        'typ:polStartdate' => $policy_start_date,
                        'typ:productCode' => $product_code,
                        'typ:policyHolderName' => 'SANJAY RANA',
                        'typ:productId' => $product_id,
                        'typ:serviceTax' => '',
                        'typ:status' => '01',
                        'typ:sumInsured' => '0',
                        'typ:updateDate' => '',
                        'typ:polExpirydate' => date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))))),
                        'typ:policyId' => '',
                        'typ:polDetailLastUpdateDate' => '',
                        'typ:quoteNo' => '',
                        'typ:policyNo' => '',
                        'typ:policyDetailid' => '',
                        'typ:stakeCode' => 'POLICY-HOL',
                        'typ:documentLink' => '',
                        'typ:polLastUpdateDate' => '',
                        'typ:properties' => $properties,
                        'typ:risks' => [
                            'typ:riskCode' => 'VEHICLE',
                            'typ:riskSuminsured' => '568186',
                            'typ:covers' => $covers,
                            'typ:properties' => $risk_properties,
                        ],
                    ];

                    // if ($requestData->business_type == 'newbusiness') {
                    //     $premium_array['typ:term'] = '3';
                    //     $premium_array['typ:polExpirydate'] = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
                    // }

                    unset($properties, $covers, $risk_properties);
                    $checksum_data = checksum_encrypt($premium_array);
                    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'new_india', $checksum_data, 'CAR');
                    if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) 
                    {
                        $quote_data = $is_data_exits_for_checksum;
                    } 
                    else 
                    {
                        $quote_data = getWsData(
                            config('constants.IcConstants.new_india.END_POINT_URL_NEW_INDIA'),
                            $premium_array,
                            'new_india',
                            [
                                'root_tag'      => 'typ:calculatePremiumMasterElement',
                                'container'     => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
                                <soapenv:Body xmlns:typ="http://iims.services/types/">#replace</soapenv:Body></soapenv:Envelope>',
                                'authorization' => [config('constants.IcConstants.new_india.AUTH_NAME_NEW_INDIA'), config('constants.IcConstants.new_india.AUTH_PASS_NEW_INDIA')],
                                'enquiryId' => $enquiryId,
                                'requestMethod' => 'post',
                                'productName'  => $productData->product_name,
                                'company'  => 'new_india',
                                'section' => $productData->product_sub_type_code,
                                'checksum' => $checksum_data,
                                'method' => 'Premium Calculation - Quote',
                                'transaction_type' => 'quote',
                            ]
                        );
                    }
                    //dd($quote_data['response']);
                    if ($quote_data['response']) 
                    {
                        $premium_resp = XmlToArray::convert((string)remove_xml_namespace($quote_data['response']));                        
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
                            $loading_inclusion_imt23 = 0;
                            $other_tp = 0;
                            $tppd_discount_amt = 0;
                            $premises_od_discount = 0;

                            foreach ($premium_resp['Body']['calculatePremiumMasterResponseElement']['properties'] as $key => $cover) {
                                if (in_array($cover['name'], ['Additional Premium for Electrical fitting'])) {
                                    $electrical_key = $cover['value'];
                                } elseif (in_array($cover['name'], ['Additional Premium for Non-Electrical fitting'])) {
                                    $nonelectrical_key = $cover['value'];
                                } elseif ($cover['name'] === 'Basic OD Premium') {
                                    $basic_od_key = $cover['value'];
                                }
                                //elseif ($cover['name'] === 'Basic OD Premium_IMT')
                                elseif ($cover['name'] === 'Basic OD Premium_IMT') {
                                    $basic_od_key = $cover['value'];
                                } elseif ($cover['name'] === 'Basic TP Premium') {
                                    $basic_tp_key = $cover['value'];
                                }
                                //additional amounts
                                elseif ($cover['name'] === 'Loading for Inclusion of IMT 23') {
                                    $loading_inclusion_imt23 = $cover['value'];
                                }
                                //new business tp premium
                                // elseif (in_array($cover['name'], ['(#)Total TP Premium', '(#)Total TP Premium for 2nd Year', '(#)Total TP Premium for 3rd Year']) && $requestData->business_type == 'newbusiness') {
                                //     $basic_tp_key += $cover['value'];
                                // }
                                elseif ($cover['name'] === 'Calculated NCB Discount') {
                                    $calculated_ncb = $cover['value'];
                                } elseif ($cover['name'] === 'OD Premium Discount Amount') {
                                    $od_discount = $cover['value'];
                                } elseif ($cover['name'] === 'Compulsory PA Premium for Owner Driver') {
                                    $pa_owner_driver = $cover['value'];
                                } elseif ($cover['name'] === 'Net Total Premium') {
                                    $net_total_premium = $cover['value'];
                                } 
                                // elseif ($cover['name'] === 'Additional premium for LL to paid driver') {
                                //     $ll_paid_driver = $cover['value'];

                                elseif ($cover['name'] === 'Add LL to paid driver conductor cleaner employed for oprn') {
                                        $ll_paid_driver = $cover['value'];

                                } elseif ($cover['name'] === 'PA premium for paid Drivers, Cleaners, Conductors') {
                                    $pa_paid_driver = $cover['value'];
                                } elseif ($cover['name'] === 'PA premium for Unnamed Persons for CV') {
                                    $pa_unnamed_person = $cover['value'];
                                } elseif ($cover['name'] == 'Additional OD Premium for CNG/LPG') {
                                    $additional_od_prem_cnglpg = $cover['value'];
                                } elseif ($cover['name'] == 'Additional TP Premium for CNG/LPG') {
                                    $additional_tp_prem_cnglpg = $cover['value'];
                                } elseif ($cover['name'] == 'Calculated Discount for Anti-Theft Devices') {
                                    $anti_theft_discount_key = $cover['value'];
                                } elseif ($cover['name'] == 'Calculated Discount for Membership of recognized Automobile Association') {
                                    $aai_discount_key = $cover['value'];
                                } elseif ($cover['name'] == 'Discount over Base TP Premium for Restricting TPPD cover to Rs6000') {
                                    $tppd_discount_amt = $cover['value'];
                                } elseif ($cover['name'] == 'Calculated Discount on Used within own Premises') {
                                    $premises_od_discount = $cover['value'];
                                }

                                //geo extension
                                elseif ($cover['name'] == 'Loading for Extension of Geographical area') {
                                    $geog_Extension_OD_Premium = $cover['value'];
                                } elseif ($cover['name'] == 'Extension of Geographical Area Premium') {
                                    $geog_Extension_TP_Premium = $cover['value'];
                                }

                                // tp section
                                elseif ($cover['name'] == 'Calculated TP Premium') {
                                    $calculated_tp = $cover['value'];
                                }
                                // elseif ($cover['name'] == 'Add Legal liability to paid driver/Cleaner or Employee') {
                                //     $other_tp = $cover['value'];
                                // }

                                //addons

                                elseif (($productData->zero_dep == '0') && ($cover['name'] == 'Premium for enhancement cover'))
                                {
                                    $zero_dep_key = $cover['value'];
                                }  

                                // elseif (($productData->zero_dep == '0') && ($cover['name'] === 'Premium for nil depreciation cover')) {
                                //     $zero_dep_key = $cover['value'];
                                // } 

                                elseif ($cover['name'] == 'Engine Protect Cover Premium') {
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
                                }
                            }

                            $final_od_premium = ($premium_type == 'Third Party')  ? '0' : ($basic_od_key + $electrical_key + $nonelectrical_key + $additional_od_prem_cnglpg + $geog_Extension_OD_Premium - $premises_od_discount); //+ $non_electrical_accessories
                            // $final_tp_premium = $basic_tp_key + $pa_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $ll_paid_driver + $geog_Extension_TP_Premium + $other_tp - $tppd_discount_amt;
                            $final_tp_premium = $calculated_tp - $pa_owner_driver + $tppd_discount_amt;

                            $final_total_discount = $calculated_ncb + $od_discount + $aai_discount_key + $anti_theft_discount_key + $voluntary_Discount + $tppd_discount_amt;

                            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);

                            if ($cvCategory == 'GCV') {
                                $final_gst_amount = round(($basic_tp_key * 0.12) + (($final_net_premium - $basic_tp_key) * 0.18));
                            } else {
                                $final_gst_amount = round($final_net_premium * 0.18);
                            }

                            $final_payable_amount = $final_net_premium + $final_gst_amount;

                            $add_ons_data = [
                                'in_built' => [],
                                'additional' => []
                            ];

                            if ($cvCategory == 'GCV') {
                                if ($productData->zero_dep == 0) {
                                    $add_ons_data['in_built'] = ['zeroDepreciation' => (int) round($zero_dep_key), 'imt23' => $loading_inclusion_imt23];
                                    array_push($applicable_addons, "zeroDepreciation", "imt23");
                                } else {
                                    $add_ons_data['in_built'] = ['imt23' => $loading_inclusion_imt23];
                                    array_push($applicable_addons, "imt23");
                                }

                                $add_ons_data['additional'] = [
                                    'road_side_assistance'        => (int) round($rsa_key),
                                    'engineProtector'             => (int) round($engine_protector_key),
                                    'ncbProtection'               => (int) round($ncb_protecter_key),
                                    'keyReplace'                  => (int) round($key_protect_key),
                                    'consumables'                 => (int) round($consumable_key),
                                    'tyreSecure'                  => (int) $tyre_secure_key,
                                    'returnToInvoice'             => (int) round($rti_cover_key),
                                    'lopb'                        => (int) round($personal_belongings_key),
                                ];
                            } else if ($cvCategory == 'PCV') {
                                if ($productData->zero_dep == 0) {
                                    $add_ons_data['in_built'] = ['zeroDepreciation' => (int) round($zero_dep_key) ?? null, 'imt23' => $loading_inclusion_imt23];
                                    array_push($applicable_addons, "zeroDepreciation", "imt23");
                                } else {
                                    $add_ons_data['in_built'] = ['imt23' => $loading_inclusion_imt23];
                                    array_push($applicable_addons, "imt23");
                                }

                                $add_ons_data['additional'] = [
                                    'road_side_assistance'        => (int) round($rsa_key),
                                    'engineProtector'             => (int) round($engine_protector_key),
                                    'ncbProtection'               => (int) round($ncb_protecter_key),
                                    'keyReplace'                  => (int) round($key_protect_key),
                                    'consumables'                 => (int) round($consumable_key),
                                    'tyreSecure'                  => (int) $tyre_secure_key,
                                    'returnToInvoice'             => (int) round($rti_cover_key),
                                    'lopb'                        => (int) round($personal_belongings_key),
                                ];
                            } else {
                                $add_ons_data['additional'] = [
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
                                ];
                            }

                            if ($cvCategory == 'PCV') {
                                array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                            }

                            if ($requestData->business_type != 'newbusiness') {
                                if ($interval->y >= 5) {
                                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                                }
                            }

                            // $total_premium_amount = $net_total_premium;
                            // $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));
                            // foreach ($add_ons_data as $add_on_key => $add_on_value) {
                            //     if (count($add_on_value) > 0) {
                            //         foreach ($add_on_value as $add_on_value_key => $add_on_value_value) {

                            //             if (isset($add_on_value[$add_on_value_key])) {
                            //                 $value = $add_on_value[$add_on_value_key]; // * (1 + (SERVICE_TAX / 100)));
                            //                 $base_premium_amount -= $value;
                            //             } else {
                            //                 $value = $add_on_value_value;
                            //             }
                            //             $add_ons[$add_on_key][$add_on_value_key] = $value;
                            //         }
                            //     } else {
                            //         $add_ons[$add_on_key] = $add_on_value;
                            //     }
                            // }
                            // $base_premium_amount = $base_premium_amount * (1 + (18.0 / 100));
                            // array_walk_recursive($add_ons, function (&$item, $key) {
                            //     if ($item == '' || $item == '0') {
                            //         $item = 'NA';
                            //     }
                            // });
                            // $total_od = ($premium_type == 'Third Party')  ? '0' : ($basic_od_key + $electrical_key + $nonelectrical_key + $additional_od_prem_cnglpg + $geog_Extension_OD_Premium);

                            // $total_tp = $basic_tp_key + $ll_paid_driver + $pa_unnamed_person + $additional_tp_prem_cnglpg + $pa_paid_driver + $geog_Extension_TP_Premium;

                            // $total_discount = $calculated_ncb + $od_discount + $aai_discount_key + $anti_theft_discount_key + $voluntary_Discount;

                            // $basePremium = $total_od + $total_tp - $total_discount;

                            // dd($basePremium . " " . $total_od . " " . $total_tp . " " . $basePremium * 0.18);

                            // $totalTax = $basePremium * 0.18;

                            // $final_premium = $basePremium + $totalTax;

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
                                    'idv' => $premium_type == 'third_party' ? 0 : round($total_idv),
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
                                    'tppd_discount' => $tppd_discount_amt,
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
                                        'ic_vehicle_discount' =>  round($od_discount),
                                    ],
                                    'basic_premium' => $basic_od_key,
                                    'deduction_of_ncb' => (int)$calculated_ncb,
                                    'tppd_premium_amount' => $basic_tp_key,
                                    'motor_electric_accessories_value' => (int)$electrical_key,
                                    'motor_non_electric_accessories_value' => (int)$nonelectrical_key,
                                    'motor_lpg_cng_kit_value' => (int)$additional_od_prem_cnglpg,
                                    'cover_unnamed_passenger_value' => (int)$pa_unnamed_person,
                                    'seating_capacity' => $veh_data->motor_carrying_capacity,
                                    'default_paid_driver' => $ll_paid_driver,
                                    'motor_additional_paid_driver' => $pa_paid_driver,
                                    'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                                    'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                                    'LimitedtoOwnPremises_OD' => round($premises_od_discount),
                                    'LimitedtoOwnPremises_TP' => 0,
                                    // 'compulsory_pa_own_driver' => ($is_od ? 0 : (int)$pa_owner_driver),
                                    'compulsory_pa_own_driver' => $pa_owner_driver,
                                    'total_accessories_amount(net_od_premium)' => $electrical_key  + $additional_od_prem_cnglpg,
                                    'total_own_damage' => $final_od_premium,
                                    'cng_lpg_tp' => $additional_tp_prem_cnglpg,
                                    'total_liability_premium' => $final_tp_premium,
                                    'net_premium' => $final_net_premium,
                                    '18.0_amount' => 0,
                                    '18.0' => 18,
                                    'total_discount_od' => 0,
                                    'add_on_premium_total' => 0,
                                    'addon_premium' => 0,
                                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                    'quotation_no' => '',
                                    'premium_amount' => $final_payable_amount,
                                    'antitheft_discount' => $anti_theft_discount_key,
                                    'final_od_premium' => $final_od_premium,
                                    'final_tp_premium' => $final_tp_premium,
                                    'final_total_discount' => $final_total_discount,
                                    'final_net_premium' => $final_net_premium,
                                    'final_gst_amount' => round($final_gst_amount),
                                    'final_payable_amount' => round($final_payable_amount),
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
                                    'ic_vehicle_discount' => round($od_discount),
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

                            if ($premises_od_discount == 0) {
                                unset($data_response['Data']['LimitedtoOwnPremises_OD']);
                            }

                            if ($premium_type == 'third_party') {
                                unset($data_response['Data']['add_ons_data']['other']['imt23']);
                            }
                            if ($productData->zero_dep == 0) {
                                unset($data_response['Data']['GeogExtension_ODPremium']);
                                unset($data_response['Data']['GeogExtension_TPPremium']);
                            }
                            if ($is_cng == 'N') {
                                unset($data_response['Data']['cng_lpg_tp']);
                                unset($data_response['Data']['motor_lpg_cng_kit_value']);
                            }
                            return camelCase($data_response);
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