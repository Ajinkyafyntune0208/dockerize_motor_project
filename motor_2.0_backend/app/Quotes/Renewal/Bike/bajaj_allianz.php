<?php

use Carbon\Carbon;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

function getRenewalQuote($enquiryId, $requestData, $productData)
{
    if (config("ENABLE_BAJAJ_ALLIANZ_RENEWAL_API") === 'Y') {
        include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
        $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');
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

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'message' => 'Vehicle Not Mapped',
                    'mmv' => $mmv
                ]
            ];
        } elseif ($mmv->ic_version_code == 'DNE') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'message' => 'Vehicle code does not exist with Insurance company',
                    'mmv' => $mmv
                ]
            ];
        }

        if ($requestData->business_type == 'breakin') 
        {
            return
            [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Your Policy has already been expired,Breakin renewals not allowed'
            ];              
        }


        $mmv_data =
            [
                'manf_name' => $mmv->vehicle_make ?? '',
                'model_name' => $mmv->vehicle_model ?? '',
                'version_name' => $mmv->variant_name ?? '',
                'seating_capacity' => 2,
                'carrying_capacity' => $mmv->carrying_capacity ?? '2',
                'cubic_capacity' => $mmv->cubic_capacity ?? '',
                'fuel_type' =>  $mmv->fuel_type ?? '',
                'gross_vehicle_weight' => $mmv->vehicle_weight ?? '',
                'vehicle_type' => $mmv->vehicle_type ?? '',
                'version_id' => $mmv->ic_version_code ?? '',
                'kw' => $mmv->cubic_capacity ?? '',
            ];
      
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if ($premium_type == 'own_damage_breakin') {
            return
                [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => "SAOD Break-in not allowed"
                ];
        }
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
        }

        $prev_policy_end_date = $requestData->previous_policy_expiry_date;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);

        $businessType = 'Roll Over';
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        
        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type', 'P')
        ->first();

        $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME");
        if ($is_pos_enabled == 'Y') {
            if (isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_POS");
            }
        }
        
        $policy_data = 
        [
            "userid" => $pUserId,
            "password"=> config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_RENEWAL_PASSWORD'),
            "weomotpolicyin" => [
                "registrationno"=> str_replace('-','',$user_proposal['vehicale_registration_number']),
                "prvpolicyref"=> $user_proposal['previous_policy_number']
            ],
            "motextracover"=> [],
            "custdetails"=> []

        ];
       
        $fetch_url = config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_FETCH_RENEWAL'); //. $user_proposal['previous_policy_number'];

        $get_response = getWsData($fetch_url, $policy_data, 'bajaj_allianz', [
            'section' => $productData->product_sub_type_code,
            'method' => 'get_renewal_data',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'quote'
        ]);
        $data = $get_response['response'];
        $response_data = json_decode($data);
       // print_r($response_data);

        if (isset($response_data->errorcode) && $response_data->errorcode == 0) 
        {

          
            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == "own_damage") 
            {
                $is_vehicle_new = 'false';
                $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($response_data->weomotpolicyinout->termenddate)));
                $vehicle_registration_no  = str_replace('-', '', $user_proposal['vehicale_registration_number']);
                
            }
            $policy_end_date =
            date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
           

            $idv = $response_data->weomotpolicyinout->vehicleidv;
            $contract = $response_data->weomotpolicyinout->contractid;

            $tppd = false;
            $zero_depreciation = false;
            $road_side_assistance = false;
            $engine_protection = false;
            $return_to_invoice = false;
            $consumables = false;
            $llpaiddriver = false;
            $cover_pa_owner_driver = false;
            $cover_pa_paid_drive = false;
            $zero_depreciation_claimsCovered = null;
            $discountPercent = 0;


            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $car_age = ceil($age / 12);
            $rsa = 'true';
            $consum = 'true';
            $engineProtector = 'true';
            $returnToInvoice = 'true';
            $date_difference = get_date_diff('year', $requestData->vehicle_register_date);
            $applicable_addons = [
                'zeroDepreciation', 'roadSideAssistance', 'engineProtector', 'returnToInvoice', 'consumables'
            ];
            if ($interval->y >= 6) {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                $consum = 'false';
            }
            if ($interval->y >= 10) {
                array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                $engineProtector = 'false';
            }
            if ($date_difference > 3) {
                array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                $returnToInvoice = 'false';
            }
            if ($requestData->business_type == 'breakin' || $requestData->previous_policy_type == 'Third-party') {
                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
            }
            if ($interval->y >= 19) {
                array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                $rsa = 'false';
            }
            if ($interval->y >= 6 && $productData->zero_dep == 0) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => 'Zero dep is not allowed for vehicle age greater than 6 years'
                ];
            }

            $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                ->first();
            if (!empty($additional['discounts'])) {
                foreach ($additional['discounts'] as $data) {
                    if (!($premium_type == "own_damage") && $data['name'] == 'TPPD Cover') {
                        $is_tppd = true;
                    }
                }
            }
            $is_electrical_selected = false;
            $is_non_electrical_selected = false;
            if (!empty($additional['accessories'])) {
                foreach ($additional['accessories'] as $key => $data) {
                    if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $cng_lpg_amt = $data['sumInsured'];
                        $is_lpg_cng = true;
                    }

                    if ($data['name'] == 'Non-Electrical Accessories') {
                        $non_electrical_amt = $data['sumInsured'];
                        $is_non_electrical_selected = true;
                    }

                    if ($data['name'] == 'Electrical Accessories') {
                        $electrical_amt = $data['sumInsured'];
                        $is_electrical_selected = true;
                    }
                }
            }

            if (isset($cng_lpg_amt) && ($cng_lpg_amt < 15000 || $cng_lpg_amt > 80000)) {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => 'CNG/LPG Insured amount, min = Rs.15000  & max = Rs.80000',
                ];
            }

            if (isset($non_electrical_amt) && ($non_electrical_amt < 412 || $non_electrical_amt > 82423)) {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => 'Non-Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                ];
            }

            if (isset($electrical_amt) && ($electrical_amt < 412 || $electrical_amt > 82423)) {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'message' => 'Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                ];
            }

            $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_pa_paid_cleaner = $cover_pa_paid_conductor = null;
            $cleanerLL = false;
            $no_of_cleanerLL = NULL;
            $no_of_driverLL = 0;
            $paidDriverLL = "false";
            if (!empty($additional['additional_covers'])) {
                foreach ($additional['additional_covers'] as $data) {
                    if ($data['name'] == 'LL paid driver') {
                        $no_of_driverLL = 1;
                        $paidDriverLL = "true";
                    }
                }
            }

            if ($premium_type != 'third_party') 
            {
                $vehicle_idv = round($response_data->weomotpolicyinout->vehicleidv);
                $min_idv = $response_data->weomotpolicyinout->vehicleidv; #ceil($vehicle_idv * 0.8);
                $max_idv = $response_data->weomotpolicyinout->vehicleidv; #floor($vehicle_idv * 1.2);
                $default_idv = round($response_data->weomotpolicyinout->vehicleidv);
            } else {
                $vehicle_idv = 0;
                $min_idv = 0;
                $max_idv = 0;
                $default_idv = 0;
            }
            
            $llpaiddriver_premium = 0;
            $llcleaner_premium = 0;
            $cover_pa_owner_driver_premium = 0;
            $cover_pa_paid_driver_premium = 0;
            $cover_pa_unnamed_passenger_premium = 0;
            $cover_pa_paid_cleaner_premium = 0;
            $cover_pa_paid_conductor_premium = 0;
            $voluntary_excess = 0;
            $ic_vehicle_discount = 0;
            $cng_lpg_selected = 'N';
            $electrical_selected = 'N';
            $non_electrical_selected = 'N';
            $ncb_discount_amt = 0;
            $od = 0;
            $cng_lpg_tp = 0;
            $zero_depreciation = 0;
            $road_side_assistance = 0;
            $engine_protection = 0;
            $return_to_invoice = 0;
            $consumables = 0;
            $tppd = 0;
            $tppd_discount = 0;
            

            $addons_list = [
                'zeroDepreciation'     => round($zero_depreciation),
                'engineProtector'      => round($engine_protection),
                'returnToInvoice'     => round($return_to_invoice),
                'consumables'           => round($consumables),
                'roadSideAssistance'  => round($road_side_assistance),
            ];
            $in_bult = [];
            $additional = [];
            $add_on_premium_total = 0;
            foreach ($addons_list as $key => $value) {
                if ($value > 0) {
                    $in_bult[$key] =  $value;
                    $add_on_premium_total += $value;
                }
            }
            $addons_data = [
                'in_built'   => [],
                'additional' => [],
                'other' => []
            ];

           
            //$applicable_addons = array_keys($in_bult);
            $applicable_addons = [];
            
            if ((isset($cng_lpg_amt) && !empty($cng_lpg_amt)) || $mmv->fyntune_version['fuel_type'] == 'CNG' || $mmv->fyntune_version['fuel_type'] == 'LPG') {
                $cng_lpg_tp = 60;
                $tppd = $tppd - 60;
            }
            $ncb_discount = $ncb_discount_amt;
            $final_od_premium = $od;
            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium + $tppd_discount;
            $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $tppd_discount;
            // $final_net_premium   = round($final_od_premium + $final_tp_premium - $final_total_discount + $add_on_premium_total);
            $final_net_premium = $response_data->custdetailsout->status1 ?? 0;
            $final_payable_amount  = $response_data->custdetailsout->status3 ?? 0;
            $final_gst_amount   = $final_payable_amount - $final_net_premium;

            $data_response =
                [
                    'status' => true,
                    'msg' => 'Found',
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'Data' =>
                    [
                        'isRenewal' => 'Y',
                        'idv' => $vehicle_idv,
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
                        'rto_no' => $requestData->rto_code,
                        'mmv_decline' => null,
                        'mmv_decline_name' => null,
                        'policy_type' => $premium_type == 'third_party' ? 'Third Party' : (($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code, //$requestData->vehicle_registration_no,
                        'voluntary_excess' => 0,
                        'version_id' => $mmv->ic_version_code,
                        'selected_addon' => [],
                        'showroom_price' => $vehicle_idv,
                        'fuel_type' => $mmv->fyntune_version['fuel_type'],
                        'ncb_discount' => $response_data->weomotpolicyinout->prvncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $productData->product_sub_type_name,
                        'mmv_detail' => $mmv_data,
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'master_policy_id' =>
                        [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' => $policy_start_date,
                            'policy_end_date' => $policy_end_date,
                            'sum_insured' => $productData->sum_insured,
                            'corp_client_id' => $productData->corp_client_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'insurance_company_id' => $productData->company_id,
                            'status' => $productData->status,
                            'corp_name' => "Ola Cab",
                            'company_name' => $productData->company_name,
                            'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            'predefine_series' => "",
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online
                        ],
                        'motor_manf_date' => date('d-m-Y',strtotime($response_data->weomotpolicyinout->registrationdate)),
                        'vehicleDiscountValues' =>
                        [
                            'master_policy_id' => $productData->policy_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'segment_id' => 0,
                            'rto_cluster_id' => 0,
                            'car_age' => $vehicle_age,
                            'ic_vehicle_discount' => $ic_vehicle_discount,
                        ],
                        'basic_premium' => $od,
                        'deduction_of_ncb' => $ncb_discount,
                        'tppd_premium_amount' => $tppd + $tppd_discount,
                        'tppd_discount' => 0,
                        'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                        'seating_capacity' => $mmv->fyntune_version['seating_capacity'],
                        'default_paid_driver' => $llpaiddriver_premium,
                        'default_paid_cleaner' => $llcleaner_premium,
                        'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                        'motor_additional_paid_cleaner' => $cover_pa_paid_cleaner_premium,
                        'motor_additional_paid_conductor' => $cover_pa_paid_conductor_premium,
                        'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' => $final_od_premium,
                        'total_liability_premium' => $final_tp_premium,
                        'net_premium' => $final_net_premium,
                        'service_tax_amount' => $final_gst_amount,
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => $add_on_premium_total,
                        'addon_premium' => 0,
                        'quotation_no' => '',
                        'premium_amount'  => (int)$final_payable_amount,
                        'antitheft_discount' => 0,
                        'final_od_premium' =>  (int)$final_od_premium,
                        'final_tp_premium' =>  (int)$final_tp_premium,
                        'final_total_discount' => $final_total_discount,
                        'final_net_premium' =>  (int)$final_net_premium,
                        'final_gst_amount' =>  (int)$final_gst_amount,
                        'final_payable_amount' =>  (int)$final_payable_amount,
                        'service_data_responseerr_msg' => 'success',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => 'renewal', //($requestData->business_type =='newbusiness') ? 'New Business' : (($requestData->business_type == "breakin") ? 'Breakin' : 'Roll over'),
                        'service_err_code' => NULL,
                        'service_err_msg' => NULL,
                        'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)), //date('d-m-Y', strtotime($contract->startDate)),
                        'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)), //date('d-m-Y', strtotime($contract->endDate)),
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
                        "max_addons_selection" => NULL,
                        'add_ons_data'              => $addons_data,
                        'applicable_addons'         => $applicable_addons,
                        'hide_breakup'=> 'Y',
                        'no_calculation' => 'Y'
                    ],
                ];

            
            return camelCase($data_response);
        
        
            
        } else {
            return [
                'status' => false,
                'premium' => '0',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => !empty($response_data->error->validationMessages) ?? 'Insurer not reachable.'
            ];
        }
    } else {
        include_once app_path() . '/Quotes/Bike/' . $productData->company_alias . '.php';
        $quoteData = getQuote($enquiryId, $requestData, $productData);
        if (isset($quoteData['data'])) {
            $quoteData['data']['isRenewal'] = 'Y';
        }
        return $quoteData;
    }
}
