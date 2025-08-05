<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use App\Http\Controllers\SyncPremiumDetail\Bike\IffcoTokioPremiumDetailController;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\UserProposal;
include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class iffco_tokioSubmitProposal {
    public static function submit($proposal, $request) {
        $enquiryId = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        {
            return  response()->json([
                'status' => false,
                'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            ]);
        } */
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $mmv_data = get_mmv_details($productData,$requestData->version_id,'iffco_tokio');
        if ($mmv_data['status'] == 1) {
            // $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER); //original
            $mmv_data = (object) array_change_key_case((array) $mmv_data['data'],CASE_LOWER);
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv_data['message']
            ];
        }

        $rto_code = $requestData->rto_code;
        $city_name = DB::table('master_rto as mr')
            ->where('mr.rto_number', $rto_code)
            ->select('mr.*')
            ->first();
        if (empty($city_name->iffco_city_code)) {
            return [
                'status' => false,
                'premium' => '0',
                'message' => 'RTO City Code not Found'
            ];
        }
        $rto_data = DB::table('iffco_tokio_city_master as ift')
            ->where('rto_city_code',$city_name->iffco_city_code)
            ->select('ift.*')->first();
        
        /* $rto_cities = explode('/',  $city_name->rto_name);
        foreach($rto_cities as $rto_city)
        {
            $rto_city = strtoupper($rto_city);
            $rto_data = DB::table('iffco_tokio_city_master as ift')
            ->where('rto_city_name',$rto_city)
            ->select('ift.*')->first();
    
            if(!empty($rto_data))
            {
                break;
            }
        } */

        if (empty($rto_data) || empty($rto_data->rto_city_code)) {
            return [
                'status' => false,
                'premium' => '0',
                'message' => 'RTO not available'
            ];
        }
        if (strlen($proposal->chassis_number) > 20) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Chassis No. length can not be greater than 20 characters',
            ];
        }

        $master_policy = MasterPolicy::find($request['policyId']);
        $zero_dep = $master_policy->zero_dep;

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $first_reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));

        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $bike_age = floor($age / 12);

        $year = explode('-', $requestData->manufacture_year);
        $yearOfManufacture = trim(end($year));
        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $cpa_cover = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $is_zero_dep = $road_side_assistance = $consumable_si = 'N';
        $acc_cover_unnamed_passenger = $requestData->unnamed_person_cover_si;
        if($acc_cover_unnamed_passenger > 100000) {
            $acc_cover_unnamed_passenger = '0';
        }

        $tp_only = (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? true : false;
        $od_only = (in_array($premium_type, ['own_damage', 'own_damage_breakin'])) ? true : false;
        $root_tag = 'getMotorPremium';
        $isNewVehicle = 'N';
        $prev_policy_end_date = date('m/d/Y 23:59:59', strtotime($requestData->previous_policy_expiry_date));
        $is_previous_claim = $requestData->is_claim == 'Y' ? true : false;
        $premium_url = config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_PREMIUM_VA');
        $tenure = '1';

        if ($requestData->business_type == 'newbusiness') {
            $businessType = 'New Business';
            $tenure = '5';
            $policy_start_date = date('m/d/Y 00:00:00');
            $prev_policy_end_date = '';
            $root_tag = 'prem:getNewVehiclePremium';
            $isNewVehicle = 'Y';
            $requestData->applicable_ncb_rate = 0;
            $is_previous_claim = false;
            $premium_url = config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_PREMIUM_NB_VA');
        } else if ($requestData->business_type == 'rollover') {
            $businessType = 'Roll Over';
            $policy_start_date = date('m/d/Y 00:00:00', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        } else if ($requestData->business_type == 'breakin') {
            $businessType = 'Break-In';
            $policy_start_date = date('m/d/Y', strtotime('+3 day'));
        }

        $vehicle_in_90_days = 'N';
        if ($isNewVehicle == 'N' && strtolower($requestData->previous_policy_expiry_date) != 'new') {
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_difference > 0) {
                $policy_start_date = date('m/d/Y 00:00:00',strtotime('+3 day'));
            }

            if ($date_difference > 90) {
                $vehicle_in_90_days = 'Y';
                $requestData->applicable_ncb = 0;
            }
        }

        $previous_policy_type = $requestData->previous_policy_type;
        if (in_array($previous_policy_type, ['Not sure'])) {//PR NO 9911
            $policy_start_date = date('m/d/Y 00:00:00', strtotime('+3 day'));
            $requestData->applicable_ncb = 0;
        }

        $policy_start_date = ($premium_type == 'third_party_breakin') ? date('m/d/Y 00:00:00', strtotime('+3 day')) : $policy_start_date;
        $policy_end_date = date('m/d/Y 23:59:59', strtotime("+$tenure year -1 day", strtotime($policy_start_date)));
        $tenure = '';
        $is_pa_cover_owner_driver = 'N';
        if(!empty($cpa_cover)) {
            foreach ($cpa_cover as $key => $data) {
                if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident') {
                    $is_pa_cover_owner_driver = 'Y';
                    $tenure = isset($data['tenure'])? $data['tenure'] : '1';
                } elseif (isset($data['reason']) && $data['reason'] != "") {
                    if ($data['reason'] == 'I do not have a valid driving license.') {
                        $cpa_reason = 'true';
                    }
                }
            }
        }

        if($bike_age <= 5 && $productData->zero_dep == '0') {
            foreach ($addons as $key => $value) {
                if (in_array('Zero Depreciation', $value)) {
                    $is_zero_dep = 'Y';
                }

                if (in_array('Road Side Assistance', $value)) {
                    $road_side_assistance = "Y"; //road side assistance
                    $is_zero_dep = 'Y';
                }
            }
        }

        $motor_non_electric_accessories = 0;
        if (!empty($accessories)) {
            foreach ($accessories as $key => $data) {
                if ($data['name'] == 'Non-Electrical Accessories') {
                    $motor_non_electric_accessories = $data['sumInsured'];
                }
            }
        }
        $voluntary_insurer_discounts = '';
        $pa_unnamed = 0;
        $tppd_cover = '750000';
        $is_tppd_cover = false;
        foreach ($discounts as $key => $value) {
            if (!empty($value['name']) && !empty($value['sumInsured']) && $value['name'] == 'voluntary_insurer_discounts' && $value['sumInsured'] > 0) {
                $voluntary_insurer_discounts = $value['sumInsured'];
            }

            if ($value['name'] == 'TPPD Cover' && ($requestData->business_type != 'newbusiness')) {
                $tppd_cover = '6000';
                $is_tppd_cover = true;
            }
        }
        $unnamed_passenger = 'N';
        foreach($additional_covers as $key => $value) {
            if ($value['name'] == 'Unnamed Passenger PA Cover') {
                $unnamed_passenger = 'Y';
                $pa_unnamed = $value['sumInsured'];
            }
        }
        
        if($tp_only){
            $requestData->applicable_ncb = 0;
        }

        $requestData->applicable_ncb = $is_previous_claim ? 0 : $requestData->applicable_ncb;
        //$proposal->owner_type  == 'I' ? $is_pa_cover_owner_driver = 'Y' : $is_pa_cover_owner_driver = 0;

        $VehicleCoverages_arr = [
            [
                'coverageId' => 'IDV Basic',
                'number' => (!$tp_only) ? $proposal->idv : 1,
                'sumInsured' => (!$tp_only) ? $proposal->idv : 1,
            ]
        ];

        if(!$od_only) {
            if($is_pa_cover_owner_driver == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'PA Owner / Driver',
                        'number' => $tenure,
                        'sumInsured' => ($requestData->vehicle_owner_type == 'I') ? $is_pa_cover_owner_driver : 'N',
                    ]
                ]);
            }
            if($is_tppd_cover == true){
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'TPPD',
                    'number' => '',
                    'sumInsured' => $tppd_cover,
                ],
            ]);
        }
            if($unnamed_passenger == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'PA to Passenger',
                        'number'  => '',
                        'sumInsured' => $pa_unnamed,
                    ]
                ]);
            }
        }

        if(!$tp_only) {
            if ($isNewVehicle != 'Y') {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'No Claim Bonus',
                        'number' => '',
                        'sumInsured' => $requestData->applicable_ncb,
                    ]
                ]);
            }
    
            $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                [
                    'coverageId' => 'Depreciation Waiver',
                    'number' => '',
                    'sumInsured' => $is_zero_dep,
                ],
                [
                    'coverageId' => 'Voluntary Excess',
                    'number' => '',
                    'sumInsured' => $voluntary_insurer_discounts,
                ]
            ]);
            if($road_side_assistance == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Towing & Related',
                        'number' => '',
                        'sumInsured' => $road_side_assistance,
                    ]
                ]);
            }
            if ($motor_non_electric_accessories != 0 && $motor_non_electric_accessories != '0') {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Cost of Accessories',
                        'number' => '',
                        'sumInsured' => (($motor_non_electric_accessories != '') ? $motor_non_electric_accessories : 0),
                    ],
                ]);
            }
        }

        $VehicleCoverages_arr = array_values($VehicleCoverages_arr);
        $voluntary_deductible_od_premium = 0; $voluntary_deductible_tp_premium = 0;

        $request_array = [
            'policyHeader' => [
                'messageId' => '1964',
            ],
            'policy' => [
                'contractType' => Config('constants.IcConstants.iffco_tokio.contractType_Bike'),
                'inceptionDate' => $policy_start_date,
                'expiryDate' => $policy_end_date,
                'previousPolicyEndDate' => $prev_policy_end_date,
                'vehicle' => [
                    'newVehicleFlag'=> $isNewVehicle,
                    'aaiExpiryDate' => '',
                    'aaiNo' => '',
                    'capacity' => $mmv_data->seating_capacity,
                    'engineCpacity' => $mmv_data->cc,
                    'exShPurPrice' => '',
                    'grossVehicleWeight' => '',
                    'grossVehicleWt' => '',
                    'itgiRiskOccupationCode' => '',
                    'itgiZone' => $rto_data->irda_zone,
                    'make' =>   $mmv_data->make_code,
                    'regictrationCity' => $rto_data->rto_city_code,
                    'registrationDate' => $first_reg_date,
                    'seatingCapacity' => $mmv_data->seating_capacity,
                    'type' => ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? 'OD' : '',
                    'vehicleBody' => '',
                    'vehicleClass' => Config('constants.IcConstants.iffco_tokio.contractType_Bike'),
                    'vehicleCoverage' => [
                        'item' => $VehicleCoverages_arr
                    ],
                    'vehicleInsuranceCost' => '',
                    'vehicleSubclass' => Config('constants.IcConstants.iffco_tokio.contractType_Bike'),
                    'yearOfManufacture' => $yearOfManufacture,
                    'zcover' => ($premium_type == "third_party" || $premium_type == "third_party_breakin") ? config('constants.IcConstants.iffco_tokio.zcover_bike_tp') : config('constants.IcConstants.iffco_tokio.zcover_bike_co'),
                ],
            ],
            'partner' => [
                'partnerBranch' => Config('constants.IcConstants.iffco_tokio.partnerBranchBike'),
                'partnerCode' => Config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
                'partnerSubBranch' => Config('constants.IcConstants.iffco_tokio.partnerSubBranchBike'),
            ],
        ];

        $request_array = trim_array($request_array);
        $ncb_amount = 0; $pa_unnamed = 0;$voluntary_excess = 0; $elecAccSumInsured = 0;
        $nonelecAccSumInsured = 0; $aai_discount = 0;$consumable = 0;$towing_related_cover=0;$tppd_discount=0;
        $pa_owner_driver=0;
        $get_response = getWsData(
            $premium_url,
            $request_array,
            'iffco_tokio', [
                'root_tag' => $root_tag,
                'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:prem="http://premiumwrapper.motor.itgi.com"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'requestMethod' => 'post',
                'section' => $productData->product_sub_type_name,
                'method' => 'Premium Calculation',
                'company' => $productData->company_name,
                'productName' => $productData->product_name. " ($businessType)",
                'enquiryId'	=> $enquiryId,
                'transaction_type' => 'proposal'
            ]
        );
        $data = $get_response['response'];

        unset($request_array);

        if($data) {
            $data = XmlToArray::convert((string)$data);
            $premium_data = $data;
            $ncb_amount = $tppd_discount = $pa_owner_driver = $pa_unnamed = $non_electric_accessories = 0;
            $dep_value  = 0;
            $towing = 0;
            $addon_total = 0;

            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                $ns = ($is_zero_dep == 'Y') ? 'ns2:' : 'ns1:';
                $premium_data = $premium_data['soapenv:Body']['getMotorPremiumResponse'][$ns.'getMotorPremiumReturn'];
                if(isset($premium_data[$ns.'error'][$ns.'errorCode']) && !empty($premium_data[$ns.'error'][$ns.'errorCode'])){
                    return [
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => $premium_data[$ns.'error'][$ns.'errorMessage']
                    ];
                }
                if (empty($premium_data[$ns . 'coveragePremiumDetail'])) {
                    $ns = $ns == 'ns1:' ? 'ns2:' : 'ns1:';
                    $errorMessage = $data['soapenv:Body']['getMotorPremiumResponse'][$ns . 'getMotorPremiumReturn'][$ns . 'error'][$ns . 'errorMessage'] ?? 'Error in proposal service';
                    if (empty($errorMessage)) {
                        $errorMessage = 'Error in proposal service';
                    }
                    return [
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'message' => $errorMessage
                    ];
                }

                $coveragepremiumdetail = $premium_data[$ns.'coveragePremiumDetail'];
                foreach($coveragepremiumdetail as $k => $v) {
                    $coverage_name = $v[$ns.'coverageName'];
    
                    if(is_array($v[$ns.'odPremium'])) {
                        $v[$ns.'odPremium'] = (!empty($v[$ns.'odPremium']['@value']) ? $v[$ns.'odPremium']['@value'] : '0' );
                    }
    
                    if(is_array($v[$ns.'tpPremium'])) {
                        $v[$ns.'tpPremium'] = (!empty($v[$ns.'tpPremium']['@value']) ? $v[$ns.'tpPremium']['@value'] : '0' );
                    }
    
                    if($coverage_name == 'IDV Basic') {
                        $od_premium = $v[$ns.'odPremium'];
                        $idv_tp_premium = $tp_premium = $v[$ns.'tpPremium'];
                    } else if ($coverage_name == 'No Claim Bonus') {
                        $ncb_amount = $v[$ns.'odPremium'];
                    } else if ($coverage_name == 'PA Owner / Driver') {
                        $pa_owner_driver = $v[$ns.'tpPremium'];
                    } else if ($coverage_name == 'Depreciation Waiver') {
                        $dep_value = $v[$ns.'coveragePremium'];
                    } else if ($coverage_name == 'Towing & Related') {
                        $towing = $v[$ns.'coveragePremium'];
                    } else if ($coverage_name == 'PA to Passenger') {
                        $pa_unnamed = $v[$ns.'tpPremium'];
                    } else if ($coverage_name == 'Voluntary Excess') {
                        $voluntary_deductible_od_premium = $v[$ns.'odPremium'];
                        $voluntary_deductible_tp_premium = $v[$ns.'tpPremium'];
                    } else if ($coverage_name == "TPPD") {
                        $tppd_discount = (intval($v[$ns.'tpPremium']) == 1) ? 0 : $v[$ns.'tpPremium'];
                    } else if($coverage_name == 'Cost of Accessories') {
                        $non_electric_accessories = ($v[$ns.'odPremium']);
                    }
                }

                $total_od_premium = $premium_data[$ns.'totalODPremium'];
                $total_tp_premium = $premium_data[$ns.'totalTPPremium'];
                $addon_total = $dep_value + $towing;
                $voluntary_excess = $voluntary_deductible_od_premium + $voluntary_deductible_tp_premium;
                $discount_amount = abs($premium_data[$ns.'discountLoadingAmt']);
                $service_tax = $premium_data[$ns.'serviceTax'];
                $total_amount_payable = $premium_data[$ns.'premiumPayable'];
                $od_discount_amt = $premium_data[$ns.'discountLoadingAmt'];
                $od_premium = $od_premium + $discount_amount;
                $od_discount_loading = $premium_data[$ns.'discountLoading'];
                $od_sum_dis_loading = $premium_data[$ns.'totalODPremium'];
                $voluntary_excess = intval($voluntary_excess) == 1 ? 0 : $voluntary_excess;
                $pa_unnamed = intval($pa_unnamed) == 1 ? 0 : $pa_unnamed;
                $ncb_amount = intval($ncb_amount) == 1 ? 0 : $ncb_amount;
                $pa_owner_driver = intval($pa_owner_driver) == 1 ? 0 : $pa_owner_driver;
                $total_discount_amount = abs($ncb_amount) + abs($discount_amount) + abs($tppd_discount) + abs($voluntary_excess);
            } else if($requestData->business_type == 'newbusiness') {
                $premium_data = $premium_data['soapenv:Body']['getNewVehiclePremiumResponse']['getNewVehiclePremiumReturn'];
                $premium_data = ($is_zero_dep == 'Y') ? $premium_data[1] : $premium_data[0];
                if(isset($premium_data['error']['errorCode']) && !empty($premium_data['error']['errorCode'])){
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $premium_data['error']['errorMessage']
                    ];
                }

                $coveragepremiumdetail = $premium_data['inscoverageResponse']['coverageResponse']['coverageResponse'];

                foreach($coveragepremiumdetail as $k => $v) {
                    $coverage_name = $v['coverageCode'];
                    if($coverage_name == 'IDV Basic') {
                        $prm = self::calculateNBPremium(($v));
                        $od_premium = $prm['od'];
                        $idv_tp_premium = $tp_premium = $prm['tp'];
                    } else if ($coverage_name == 'PA Owner / Driver') {
                        $prm = self::calculateNBPremium(($v));
                        $pa_owner_driver = $prm['tp'];
                    } else if ($coverage_name == 'Depreciation Waiver') {
                        $prm = self::calculateNBPremium(($v));
                        $dep_value = $prm['od'];
                    } else if ($coverage_name == 'Towing & Related') {
                        $prm = self::calculateNBPremium(($v));
                        $towing = $prm['od'];
                    } else if ($coverage_name == 'PA to Passenger') {
                        $prm = self::calculateNBPremium(($v));
                        $pa_unnamed = $prm['tp'];
                    } else if ($coverage_name == 'Voluntary Excess') {
                        $prm = self::calculateNBPremium(($v));
                        $voluntary_deductible_od_premium = $prm['od'];
                        $voluntary_deductible_tp_premium = $prm['tp'];
                    } else if($coverage_name == 'Cost of Accessories') {
                        $prm = self::calculateNBPremium(($v));
                        $non_electric_accessories = $prm['od'];
                    } /* else if ($coverage_name == "TPPD") {
                        $prm = self::calculateNBPremium(($v));
                        $tppd_discount = $prm['tp'];
                    } */
                }

                $voluntary_excess = $voluntary_deductible_od_premium + $voluntary_deductible_tp_premium;
                $pa_unnamed = intval($pa_unnamed) == 1 ? 0 : $pa_unnamed;
                $ncb_amount = intval($ncb_amount) == 1 ? 0 : $ncb_amount;
                $pa_owner_driver = intval($pa_owner_driver) == 1 ? 0 : $pa_owner_driver;
                $total_od_premium = $premium_data['totalODPremium'];
                $total_tp_premium = $premium_data['totalTPPremium'];
                $addon_total = $dep_value + $towing;
                $discount_amount = abs($premium_data['discountLoadingAmt']);
                $od_premium = $od_premium + $discount_amount;
                $service_tax = $premium_data['gstAmount'];
                $od_discount_amt = $premium_data['discountLoadingAmt'];
                $od_discount_loading = $premium_data['discountLoading'];
                $od_sum_dis_loading = $premium_data['totalODPremium'];
                $total_discount_amount = abs($ncb_amount) + abs($discount_amount) + abs($tppd_discount) + abs($voluntary_excess);
            }
            unset($data);
            $od_premium_amount = $od_premium + $non_electric_accessories;
            $tp_premium = $tp_premium + $pa_owner_driver + $pa_unnamed;
            $net_premium = $od_premium_amount + $tp_premium - $total_discount_amount + $addon_total;
            $service_tax = $net_premium * 18 / 100;
            $total_amount_payable = $net_premium + $service_tax;
            $total_od_amount = $od_premium_amount - $total_discount_amount;

            $vehicleDetails = [
                'manufacture_name' => $mmv_data->manufacture,
                'model_name' => $mmv_data->model,
                'version' => $mmv_data->variant,
                'fuel_type' => $mmv_data->fuel_type,
                'seating_capacity' => $mmv_data->seating_capacity,
                'carrying_capacity' => $mmv_data->seating_capacity - 1,
                'cubic_capacity' => $mmv_data->cc,
                'gross_vehicle_weight' => '',
                'vehicle_type' => '2w'
            ];
            $length = 13;
            $unique_quote = mt_rand(pow(10,($length-1)),pow(10,$length)-1);
            $total_sum_insured = $proposal->idv != '' ? $proposal->idv : '0';
            $additional_details_data = array(
                "unique_quote" => $unique_quote,
                "od_discount_amt" => $od_discount_amt,
                "pa_unnamed" => $pa_unnamed,
                "total_tp_premium" => $total_tp_premium,
                "od_discount_loading" => gettype($od_discount_loading) == 'array' ? 0 : $od_discount_loading,
                "od_sum_dis_loading" => $od_sum_dis_loading,
                "ncb_renewal_policy" => $requestData->applicable_ncb,
                "towing" => $towing,
                "total_od" => $total_od_premium,
                "cpa_premium" => $pa_owner_driver,
                "zero_dep_value" => $dep_value,
                "voluntary_excess_od" => abs($voluntary_deductible_od_premium),
                "voluntary_excess_tp" => $voluntary_deductible_tp_premium,
                "voluntary_excess_amt" => $voluntary_insurer_discounts,
                "tp_premium" => $idv_tp_premium,
                "tppd_discount" => abs($tppd_discount),
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'total_sum_insured' => $total_sum_insured,
                'total_ci' => 0
            );

            $NewBusinessTpEndDate = date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date)));

            $policyEndDate = ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') 
                ? $NewBusinessTpEndDate 
                :  date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));

            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policyEndDate)),
                    'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                    'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                    'proposal_no' => $unique_quote,
                    'unique_proposal_id' => $unique_quote,
                    'od_premium' => $total_od_premium,
                    'tp_premium' => $total_tp_premium,
                    'addon_premium' => $addon_total,
                    'cpa_premium' => $pa_owner_driver,
                    'pa_unnamed' => $pa_unnamed,
                    'final_premium' => $net_premium,
                    'total_premium' => $net_premium,
                    'service_tax_amount' => $service_tax,
                    'final_payable_amount' => $total_amount_payable,
                    'ic_vehicle_details' => $vehicleDetails,
                    'ncb_discount' => abs($ncb_amount),
                    'total_discount' => $total_discount_amount,
                    //'financer_location' =>
                    'unique_quote' => $unique_quote,
                    'additional_details_data' => json_encode($additional_details_data),
                    'electrical_accessories' => '0',
                    'non_electrical_accessories' => '0',
            ]);
           
            $data['user_product_journey_id'] = $enquiryId;
            $data['ic_id'] = $master_policy->insurance_company_id;
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $data['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($data);

            IffcoTokioPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            return response()->json([
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $data['user_product_journey_id'],
                    'proposalNo' => $unique_quote,
                    'finalPayableAmount' => $proposal->final_payable_amount,
                    'is_breakin' => '',
                    'inspection_number' => ''
                ]
            ]);
        }
        else
        {
            $return_data =  [
                'status'  => 'false',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Unable to calculate premium amount. Please check the provided details'
            ];

            return $return_data;
        }
    }

    static function calculateNBPremium($cov) {
        $od_premium = $tp_premium = 0;
        for ($i=1; $i <= 5; $i++) {
            if (!empty($cov['OD'.$i]) && !is_array($cov['OD'.$i])) {
                $od_premium += (float) $cov['OD'.$i];
            }
            if (!empty($cov['TP'.$i]) && !is_array($cov['TP'.$i])) {
                $tp_premium += (float) $cov['TP'.$i];
            }
        }

        return ['od' => $od_premium, 'tp' => $tp_premium];
    }
}