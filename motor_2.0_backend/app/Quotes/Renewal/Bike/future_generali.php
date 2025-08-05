<?php

use App\Models\MotorModel;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\IcVersionMapping;
use App\Models\MasterPremiumType;
use App\Models\MotorManufacturer;
use App\Models\MotorModelVersion;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

function getRenewalQuote($enquiryId, $requestData, $productData)
{
    $vehicle_block_data = DB::table('vehicle_block_data')
        ->where('registration_no', str_replace("-", "", $requestData->vehicle_registration_no))
        ->where('status', 'Active')
        ->select('ic_identifier')
        ->get()
        ->toArray();
    if (isset($vehicle_block_data[0])) {
        $block_bool = false;
        $block_array = explode(',', $vehicle_block_data[0]->ic_identifier);
        if (in_array('ALL', $block_array)) {
            $block_bool = true;
        } else if (in_array($productData->company_alias, $block_array)) {
            $block_bool = true;
        }
        if ($block_bool == true) {
            return  [
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => $requestData->vehicle_registration_no . " Vehicle Number is Declined",
                'request'           => [
                    'message'           => $requestData->vehicle_registration_no . " Vehicle Number is Declined",
                ]
            ];
        }
    }

    $mmv = get_mmv_details($productData, $requestData->version_id, 'future_generali');


    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    if ($premium_type == 'breakin') {
        $premium_type = 'comprehensive';
    }
    if ($premium_type == 'third_party_breakin') {
        $premium_type = 'third_party';
    }
    if ($premium_type == 'own_damage_breakin') {
        $premium_type = 'own_damage';
    }
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
    }


    $mmv_data->manf_name = $mmv_data->make;
    $mmv_data->model_name = $mmv_data->model;
    $mmv_data->version_name =  '';//$mmv_data->model;
    $mmv_data->seating_capacity = $mmv_data->seating_capacity;
    $mmv_data->cubic_capacity = $mmv_data->cc;
    $mmv_data->fuel_type = $mmv_data->fuel_code;

    // bike age calculation
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $car_age = ceil($age / 12);

    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    //renewal getQuote service 
    $policy_data = [
        "PolicyNo" => $user_proposal['previous_policy_number'],
        "ExpiryDate" => '',
        "RegistrationNo" => '',
        "VendorCode" => config('constants.IcConstants.future_generali.FG_RENEWAL_VENDOR_CODE'),
    ];
    $url = config('constants.IcConstants.future_generali.FG_RENEWAL_BIKE_FETCH_POLICY_DETAILS');
    $get_response = getWsData($url, $policy_data, 'future_generali', [
        'section' => $productData->product_sub_type_code,
        'method' => 'Renewal Fetch Policy Details',
        'requestMethod' => 'post',
        'enquiryId' => $enquiryId,
        'productName' => $productData->product_name,
        'transaction_type' => 'quote',
        'headers' => [
            'Content-Type' => 'application/json',
        ]
    ]);
    $policy_data_response = $get_response['response'];

    if ($policy_data_response) {
        $quote_policy_output = XmlToArray::convert($policy_data_response);
        if (($quote_policy_output['Policy']['Status'] ?? '') == 'Fail') {
            if ($quote_policy_output['Policy']['Status'] == 'Fail') {
                if (isset($quote_policy_output['Error'])) {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => $quote_policy_output['Error']
                    ];
                }elseif (isset($quote_policy_output['ErrorMessage'])) {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => $quote_policy_output['ErrorMessage']
                    ];
                } 
            }
        } else {
            $quote_output = $quote_policy_output['PremiumBreakup']['NewDataSet']['Table'];
            $total_od_premium = 0;
            $total_tp_premium = 0;
            $od_premium = 0;
            $tp_premium = 0;
            $liability = 0;
            $pa_owner = 0;
            $pa_unnamed = 0;
            $lpg_cng_amount = 0;
            $lpg_cng_tp_amount = 0;
            $electrical_amount = 0;
            $non_electrical_amount = 0;
            $ncb_discount = 0;
            $discount_amount = 0;
            $discperc = 0;
            $pa_paidDriver = 0;
            $zero_dep_amount = 0;
            $eng_prot = 0;
            $ncb_prot = 0;
            $rsa = 0;
            $tyre_secure = 0;
            $return_to_invoice = 0;
            $consumable = 0;
            $basePremium = 0;
            $total_od = 0;
            $total_tp = 0;
            $total_discount = 0;
            $geog_Extension_OD_Premium = 0;
            $geog_Extension_TP_Premium = 0;
            $selected_addons_data = [
                'in_built'   => [],
                'additional' => [],
                'other_premium' => 0
            ];
            $addncbProtectionInAddons = false;
            $ncbProptectionValue = 0;
            $applicable_addons = [];
            $newselected_addons_data = [];

            foreach ($quote_output as $key => $cover) {

                $cover = array_map('trim', $cover);
                $value = $cover['BOValue'];

                if (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'OD')) {
                    $total_od_premium = $value;
                } elseif (($cover['Code'] == 'PrmDue') && ($cover['Type'] == 'TP')) {
                    $total_tp_premium = $value;
                } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'OD')) {
                    $od_premium = $value;
                } elseif (($cover['Code'] == 'IDV') && ($cover['Type'] == 'TP')) {
                    $tp_premium = $value;
                }elseif (($cover['Code'] == 'VehicaleIDV') && ($cover['Type'] == 'OD')) {
                    $idv = $value;
                }elseif ((in_array($cover['Code'],['LLDE','LLDC'])) && ($cover['Type'] == 'TP')) {
                    $liability = $value;
                } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                    $pa_owner = $value;
                } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                    $pa_unnamed = $value;
                } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'OD')) {
                    $lpg_cng_amount = $value;
                } elseif (($cover['Code'] == 'CNG') && ($cover['Type'] == 'TP')) {
                    $lpg_cng_tp_amount = $value;
                } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                    $electrical_amount = $value;
                } elseif (($cover['Code'] == 'NEA') && ($cover['Type'] == 'OD')) {
                    $non_electrical_amount = $value;
                } elseif (($cover['Code'] == 'NCB') && ($cover['Type'] == 'OD')) {
                    $ncb_discount = abs($value);
                } elseif (($cover['Code'] == 'LOADDISC') && ($cover['Type'] == 'OD')) {
                    $discount_amount = round(str_replace('-', '', $value));
                } elseif (($cover['Code'] == 'PAPD') && ($cover['Type'] == 'TP')) {
                    $pa_paidDriver = round($value);
                }  elseif (($cover['Code'] == 'DISCPERC') && ($cover['Type'] == 'OD')) {
                    $discperc = $value;
                } elseif (($cover['Code'] == 'ZDCNS') && ($cover['Type'] == 'OD')) {

                    if ((int)$value == 0) {
                        
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'ZDCNS Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }

                    $selected_addons_data = [
                        'in_built'   => [
                            'keyReplace' => 0,
                            'lopb' => 0,
                            'consumables' => 0,
                            'zero_depreciation' =>  (int)$value,
                            'roadSideAssistance' => 0,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    array_push($applicable_addons, 'zeroDepreciation');
                    array_push($applicable_addons, 'consumables');
                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                } elseif (($cover['Code'] == 'ZDCNE') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                        
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'ZDCNE Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }
                    $selected_addons_data = [
                        'in_built'   => [
                            'keyReplace' => 0,
                            'lopb' => 0,
                            'consumables' => 0,
                            'engineProtector' => 0,
                            'zero_depreciation' =>  (int)$value,
                            'roadSideAssistance' => 0,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    array_push($applicable_addons, 'zeroDepreciation');
                    array_push($applicable_addons, 'consumables');
                    array_push($applicable_addons, 'engineProtector');
                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                } elseif (($cover['Code'] == 'ZDCNT') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                       
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'ZDCNT Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }
                    $selected_addons_data = [
                        'in_built'   => [
                            'keyReplace' => 0,
                            'lopb' => 0,
                            'consumables' => 0,
                            'tyreSecure' => 0,
                            'zero_depreciation' =>  (int)$value,
                            'roadSideAssistance' => 0,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    array_push($applicable_addons, 'zeroDepreciation');
                    array_push($applicable_addons, 'consumables');
                    array_push($applicable_addons, 'tyreSecure');
                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                } elseif (($cover['Code'] == 'ZDCET') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                        
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'ZDCET Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }
                    $selected_addons_data = [
                        'in_built'   => [
                            'keyReplace' => 0,
                            'lopb' => 0,
                            'consumables' => 0,
                            'tyreSecure' => 0,
                            'engineProtector' => 0,
                            'zero_depreciation' =>  (int)$value,
                            'roadSideAssistance' => 0,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    array_push($applicable_addons, 'zeroDepreciation');
                    array_push($applicable_addons, 'consumables');
                    array_push($applicable_addons, 'engineProtector');
                    array_push($applicable_addons, 'tyreSecure');
                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                } elseif (($cover['Code'] == 'ZCETR') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                    
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'ZCETR Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }
                    $selected_addons_data = [
                        'in_built'   => [
                            'keyReplace' => 0,
                            'lopb' => 0,
                            'consumables' => 0,
                            'tyreSecure' => 0,
                            'engineProtector' => 0,
                            'returnToInvoice' => 0,
                            'zero_depreciation' =>  (int)$value,
                            'roadSideAssistance' => 0,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    array_push($applicable_addons, 'zeroDepreciation');
                    array_push($applicable_addons, 'consumables');
                    array_push($applicable_addons, 'engineProtector');
                    array_push($applicable_addons, 'tyreSecure');
                    array_push($applicable_addons, 'returnToInvoice');
                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                } elseif (($cover['Code'] == 'STRSA') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {

                        /*return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'STRSA Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];*/
                        $selected_addons_data = [
                            'in_built'   => [],
                            'additional' => [
                                'roadSideAssistance' => 0,
                            ],
                            'other_premium' => 0
                        ];
                    } else {
                        $selected_addons_data = [
                            'in_built'   => [],
                            'additional' => [
                                'roadSideAssistance' => (int)$value,
                            ],
                            'other_premium' => 0
                        ];
                        array_push($applicable_addons, 'roadSideAssistance');
                    }
                } elseif (($cover['Code'] == 'RSPBK') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                        
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'RSPBK Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }
                    $selected_addons_data = [
                        'in_built'   => [
                            'keyReplace' => 0,
                            'lopb' => 0,
                            'roadSideAssistance' => (int)$value,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];

                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                } elseif (($cover['Code'] == 'STZDP') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                       
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'STZDP Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                        ];
                    }
                    $selected_addons_data = [
                        'in_built'   => [
                            'zero_depreciation' => (int)$value,
                        ],
                        'additional' => [],
                        'other_premium' => 0
                    ];
                    array_push($applicable_addons, 'zeroDepreciation');
                    array_push($applicable_addons, 'roadSideAssistance');
                    array_push($applicable_addons, 'keyReplace');
                    array_push($applicable_addons, 'lopb');
                }
                
                if (($cover['Code'] == 'STNCB') && ($cover['Type'] == 'OD')) {
                    if ((int)$value == 0) {
                    } else {
                        $addncbProtectionInAddons = true;
                        $ncbProptectionValue = (int)$value;
                        array_push($applicable_addons, 'ncbProtection');
                    }
                }
            }
            $total_idv = $idv ?? 0;

            if ($addncbProtectionInAddons) {
                $selected_addons_data['additional']['ncbProtection'] = (int)$ncbProptectionValue;
            }


            if (isset($selected_addons_data['in_built']) && $selected_addons_data['in_built'] != null) {
                $newselected_addons_data = array_merge($newselected_addons_data, $selected_addons_data['in_built']);
            }
            if (isset($selected_addons_data['additional']) && $selected_addons_data['additional'] != null) {
                $newselected_addons_data = array_merge($newselected_addons_data, $selected_addons_data['additional']);
            }
            if ($discperc > 0) {
                $od_premium = $od_premium + $discount_amount;
                $discount_amount = 0;
            }


            $total_od = $od_premium + $electrical_amount + $non_electrical_amount + $lpg_cng_amount;
            $total_tp = $tp_premium + $liability + $pa_unnamed + $lpg_cng_tp_amount + $pa_paidDriver;
            $total_discount = $ncb_discount + $discount_amount;
            $basePremium = $total_od + $total_tp - $total_discount;
            $total_addons = $zero_dep_amount;

            $final_tp = (int)$total_tp + (int)$pa_owner;

            $od_base_premium = $total_od;

            $total_premium_amount = $total_od_premium + $total_tp_premium + $total_addons;
            $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));

            $totalTax = $basePremium * 0.18;

            $final_premium = $basePremium + $totalTax;
            //other data
            $today_date =date('Y-m-d');
            if(new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date))
            {
                $policy_start_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            }
            $policy_end_date = date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            if ($productData->zero_dep == '0') {
                if (isset($selected_addons_data['additional']['zero_depreciation']) && $selected_addons_data['additional']['zero_depreciation'] > 0) {
                    $selected_addons_data['in_built']['zero_depreciation'] = $selected_addons_data['additional']['zero_depreciation'];
                    unset($selected_addons_data['additional']['zero_depreciation']);
                }
            }
            $selected_addons_data['in_built_premium'] = array_sum($selected_addons_data['in_built']);
            $selected_addons_data['additional_premium'] = array_sum($selected_addons_data['additional']);

            $data_response = [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'isRenewal' => 'Y',
                    'idv' =>  round($total_idv),
                    'vehicle_idv' => $total_idv,
                    'min_idv' => $total_idv,
                    'max_idv' => $total_idv,
                    'rto_decline' => NULL,
                    'rto_decline_number' => NULL,
                    'mmv_decline' => NULL,
                    'mmv_decline_name' => NULL,
                    'policy_type' => $premium_type == 'third_party' ? 'Third Party' : (($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $requestData->rto_code,
                    'voluntary_excess' => $requestData->voluntary_excess_value,
                    'version_id' => $mmv_data->ic_version_code,
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_name' => $productData->product_sub_type_name . ' - ' .   $productData->product_identifier,
                    'mmv_detail' => $mmv_data,
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
                        'ic_vehicle_discount' =>  round($discount_amount),
                    ],
                    'basic_premium' => round($od_premium),
                    'deduction_of_ncb' => round($ncb_discount),
                    'tppd_premium_amount' => round($tp_premium),
                    'motor_electric_accessories_value' => round($electrical_amount),
                    'motor_non_electric_accessories_value' => round($non_electrical_amount),
                    'motor_lpg_cng_kit_value' => round($lpg_cng_amount),
                    'vehicle_lpg_cng_kit_value' => (int)$requestData->bifuel_kit_value,
                    'cng_lpg_tp' => round($lpg_cng_tp_amount),
                    'cover_unnamed_passenger_value' => round($pa_unnamed),
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'default_paid_driver' => $liability,
                    'motor_additional_paid_driver' => $pa_paidDriver,
                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                    'compulsory_pa_own_driver' =>(int) $pa_owner,
                    'cpa_allowed' => (int) $pa_owner > 0 ? true : false,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' =>  round($total_od),
                    'total_liability_premium' => round($total_tp),
                    'net_premium' => round($basePremium),
                    'service_tax_amount' => 0,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'voluntary_excess' => 0,
                    'quotation_no' => '',
                    'premium_amount' => round($final_premium),
                    'antitheft_discount' => '',
                    'final_od_premium' => round($total_od),
                    'final_tp_premium' => round($total_tp),
                    'final_total_discount' => round($total_discount),
                    'final_net_premium' => round($final_premium),
                    'final_payable_amount' => round($final_premium),
                    'service_data_responseerr_msg' => 'true',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : (($requestData->business_type == "breakin") ? 'Breakin' : 'Roll over'),
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => $policy_start_date,
                    'policyEndDate' => $policy_end_date,# DateTime::createFromFormat('d/m/Y h:i:s', $policy_end_date)->format('d-m-Y'),
                    'ic_of' => $productData->company_id,
                    'ic_vehicle_discount' => round($discount_amount),
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
                    'add_ons_data' => $selected_addons_data,
                    'tppd_discount' => 0,
                    'applicable_addons' => $applicable_addons,
                ]
            ];
           
            if($requestData->vehicle_owner_type != "I"){
                $data_response['Data']['cpa_allowed'] = false;
            }

            return camelCase($data_response);
        }
    }
}