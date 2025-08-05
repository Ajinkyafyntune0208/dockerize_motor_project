<?php

namespace App\Http\Controllers\Proposal\Services\Car;
include_once app_path().'/Helpers/CarWebServiceHelper.php';

use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\UserProposal;
use App\Http\Controllers\LiveCheck\LivechekBreakinController;
use App\Http\Controllers\SyncPremiumDetail\Car\IffcoTokioPremiumDetailController;
use App\Models\CvBreakinStatus;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;

class iffco_tokioSubmitProposal {
    public static function submit($proposal, $request) {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $zero_dep = $master_policy->zero_dep;

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        
        /* if ($requestData->business_type != 'newbusiness' && $premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure')) {
            return [
                
                'premium_amount' =>0,
                'status' => false,
                'message' => 'Break-In Quotes Not Allowed',
                'request' => [
                    'message' => 'Break-In Quotes Not Allowed',
                    'previous_policy_typ' => $requestData->previous_policy_type
                ]
            ];
        } */

        if (strlen($proposal->chassis_number) > 20) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Chassis No. length can not be greater than 20 characters',
            ];
        }

        $mmv_data = get_mmv_details($productData,$requestData->version_id, 'iffco_tokio');
        if ($mmv_data['status'] == 1) {
            $mmv_data = (object) array_change_key_case((array) $mmv_data['data'],CASE_LOWER);
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv_data['message'],
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id' => $requestData->version_id
                ]
            ];
        }
    
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id'=>$requestData->version_id
                ]
            ];
        } elseif ($mmv_data->ic_version_code == 'DNE') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request'=>[
                    'mmv'=> $mmv_data,
                    'version_id'=>$requestData->version_id
                ]
            ];
        }

        $rto_code = $requestData->rto_code;
        $city_name = DB::table('master_rto as mr')
            ->where('mr.rto_number',$rto_code)
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

        $first_reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $vehicle_age = ceil($age / 12);
        // $year = explode('-', $requestData->manufacture_year);
        // $yearOfManufacture = trim(end($year));

        $root_tag = 'getMotorPremium';
        $isNewVehicle = 'N';
        $prev_policy_end_date = date('m/d/Y 23:59:59', strtotime($requestData->previous_policy_expiry_date));
        $is_previous_claim = $requestData->is_claim == 'Y' ? true : false;
        $premium_url = config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_PREMIUM_VA');
        $tenure = '1';

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $is_breakin = false;
        if ($requestData->business_type == 'newbusiness') {
            $businessType = 'New Business';
            $tenure = '3';
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
            $is_breakin = true;
            $businessType = 'Break-In';
            $policy_start_date = date('m/d/Y', strtotime('+3 day'));
        }

        $vehicle_in_90_days = 'N';
        if ($isNewVehicle == 'N' && !empty($requestData->previous_policy_expiry_date)) {
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            if ($date_difference > 0) {
                $policy_start_date = date('m/d/Y 00:00:00',strtotime('+3 day'));
            }

            if ($date_difference > 90) {
                $vehicle_in_90_days = 'Y';
                $requestData->applicable_ncb = 0;
            }
        }

        $previous_policy_type = $requestData->previous_policy_type;
        if(in_array($previous_policy_type, ['Not sure', 'Third-party']) && !in_array($premium_type, ['third_party' , 'third_party_breakin'])){
            $policy_start_date = date('m/d/Y 00:00:00', strtotime('+3 day'));
            $requestData->applicable_ncb = 0;
            $is_breakin = true;
        }
        if(in_array($previous_policy_type, ['Not sure'])){
            
            $prev_policy_end_date = '';
            $vehicle_in_90_days = 'Y';
        }
        $policy_start_date = ($premium_type == 'third_party_breakin') ? date('m/d/Y 00:00:00',strtotime('+3 day')) : $policy_start_date;
        $policy_end_date = date('m/d/Y 23:59:59', strtotime("+$tenure year -1 day", strtotime($policy_start_date)));

        if(in_array($premium_type, ['own_damage_breakin'])) {
            $policy_start_date = date('m/d/Y 00:00:00', strtotime('+3 day'));
            $policy_end_date = date('m/d/Y 23:59:59', strtotime("+1 year -1 day", strtotime($policy_start_date)));
        }
        
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $cpa_cover = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $is_pa_cover_owner_driver = 'N';
        $tenure = '';
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

        $isConsumable = 'N';
        $isNCBProtection = 'N';
        $is_zero_dep = $road_side_assistance = 'N';
        if($vehicle_age <= 5 && $productData->zero_dep == '0') {
            $is_zero_dep = 'Y';
            foreach ($addons as $key => $value) {
                if (in_array('Road Side Assistance', $value)) {
                    $road_side_assistance = "Y";
                }
                if (in_array('NCB Protection', $value) && $vehicle_age <= 3) {
                    $isNCBProtection = 'Y';
                }
                if (in_array('Consumable', $value)) {
                    $isConsumable = 'Y';
                }
            }
        }

        $motor_electric_accessories = '0';
        $motor_non_electric_accessories = '0';
        $motor_lpg_cng_kit = '0';
        $motor_anti_theft = 'N';
        $motor_automobile_association = 'N';
        $motor_acc_cover_unnamed_passenger = '';
        $legal_liability='N';

        if (!empty($accessories)) {
            foreach ($accessories as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $motor_lpg_cng_kit = $data['sumInsured'];
                }
    
                if ($data['name'] == 'Non-Electrical Accessories') {
                    $motor_non_electric_accessories = $data['sumInsured'];
                }
    
                if ($data['name'] == 'Electrical Accessories') {
                    $motor_electric_accessories = $data['sumInsured'];
                }
            }
        }

        $voluntary_insurer_discounts = 'N';
        $pa_paid_driver = 'N';
        $pa_paid_driver_amt = '0';
        $pa_unnamed = '0';
        $unnamed_passenger = 'N';
        $tppd_cover = '';
        $is_tppd = 'N';
        foreach ($discounts as $key => $value) {
            if ($value['name'] == 'anti-theft device') {
                $motor_anti_theft = 'Y';
            }
            if (!empty($value['name']) && !empty($value['sumInsured']) && $value['name'] == 'voluntary_insurer_discounts' && $value['sumInsured'] > 0) {
                $voluntary_insurer_discounts = $value['sumInsured'];
            }

            if ($value['name'] == 'TPPD Cover') {
                $is_tppd = 'Y';
                if($requestData->business_type != 'newbusiness'){
                    $tppd_cover = '6000';
                }else{
                    $tppd_cover = '750000';
                }
            }
        }

        if (!empty($additional_covers)) {
            foreach($additional_covers as $key => $value) {
                if ($value['name'] == 'LL paid driver') {
                    $legal_liability = 'Y';
                }
                if ($value['name'] == 'PA cover for additional paid driver') {
                    $pa_paid_driver = 'Y';
                    $pa_paid_driver_amt = $value['sumInsured'];
                }
                if ($value['name'] == 'Unnamed Passenger PA Cover') {
                    $unnamed_passenger = 'Y';
                    $pa_unnamed = $value['sumInsured'];
                }
            }
        }
        
        if($tp_only){
            $requestData->applicable_ncb = 0;
        }

        $requestData->applicable_ncb = $is_previous_claim ? 0 : $requestData->applicable_ncb;

        $year = explode('-', $requestData->manufacture_year);
        $yearOfManufacture = trim(end($year));

        $VehicleCoverages_arr = [
            [
                'coverageId' => 'IDV Basic',
                'number' => '',
                'sumInsured' => (!$tp_only) ? $proposal->idv : 1,
            ]
        ];
        if (in_array($requestData->fuel_type, ['CNG', 'LPG', 'PETROL+CNG']) && empty($motor_lpg_cng_kit)) {
            array_push($VehicleCoverages_arr, [
                'coverageId' => 'CNG Kit Company Fit',
                'number' => '',
                'sumInsured' =>  'Y'
            ]);
        }else if(!empty($motor_lpg_cng_kit)){
            array_push($VehicleCoverages_arr, [
                'coverageId' => 'CNG Kit',
                'number' => '',
                'sumInsured' => !empty($motor_lpg_cng_kit) ? $motor_lpg_cng_kit : '0'
            ]);
        }
    
        if(!in_array($premium_type, ['own_damage', 'own_damage_breakin'])) {
            if($is_tppd == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'TPPD',
                        'number' => '',
                        'sumInsured' => $tppd_cover,
                    ]
                ]);
            }
            if($is_pa_cover_owner_driver == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'PA Owner / Driver',
                        'number' => $tenure,
                        'sumInsured' => ($requestData->vehicle_owner_type == 'I') ? $is_pa_cover_owner_driver : 'N',
                    ]
                ]);
            }
            if($legal_liability == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Legal Liability to Driver',
                        'number' => '',
                        'sumInsured' => $legal_liability,
                    ]
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
            if($motor_anti_theft == 'Y'){
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Anti-Theft',
                        'number' => '',
                        'sumInsured' => ($motor_anti_theft == 'Y' ) ? 'Y' : 'N',
                    ]
                ]);
            }

            if ($road_side_assistance == "Y") {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Towing & Related',
                        'number' => '',
                        'sumInsured' => $road_side_assistance,
                    ]
                ]);
            }
            if ($isConsumable == "Y") {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Consumable',
                        'number' => '',
                        'sumInsured' =>  $isConsumable
                    ]
                ]);
            }
            if ($isNCBProtection == "Y") {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'NCB Protection',
                        'number' => '',
                        'sumInsured' =>  $isNCBProtection
                    ]
                ]);
            }
            if ($motor_electric_accessories != 0 && $motor_electric_accessories != '0') {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Electrical Accessories',
                        'number' =>  '',
                        'sumInsured' => (($motor_electric_accessories != '') ? $motor_electric_accessories : 0),
                    ],
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

            if($is_zero_dep == 'Y')
            {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Depreciation Waiver',
                        'number' => '',
                        'sumInsured' => $is_zero_dep,
                    ],
                ]);
            }

            if($voluntary_insurer_discounts != 'N')
            {
                $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
                    [
                        'coverageId' => 'Voluntary Excess',
                        'number' => '',
                        'sumInsured' => $voluntary_insurer_discounts,
                    ]
                ]);
            }
            // $VehicleCoverages_arr = array_merge($VehicleCoverages_arr, [
            //     // [
            //     //     'coverageId' => 'Electrical Accessories',
            //     //     'number' =>  '',
            //     //     'sumInsured' => (($motor_electric_accessories != '') ? $motor_electric_accessories : 0),
            //     // ],
            //     // [
            //     //     'coverageId' => 'Cost of Accessories',
            //     //     'number' => '',
            //     //     'sumInsured' => (($motor_non_electric_accessories != '') ? $motor_non_electric_accessories : 0),
            //     // ],
            //     // [
            //     //     'coverageId' => 'Depreciation Waiver',
            //     //     'number' => '',
            //     //     'sumInsured' => $is_zero_dep,
            //     // ],
            //     // [
            //     //     'coverageId' => 'AAI Discount',
            //     //     'number' =>  '',
            //     //     'sumInsured' => 'N',
            //     // ],
            //     [
            //         'coverageId' => 'Voluntary Excess',
            //         'number' => '',
            //         'sumInsured' => $voluntary_insurer_discounts,
            //     ]
            // ]);
        }
       
        $VehicleCoverages_arr = array_values($VehicleCoverages_arr);
        $voluntary_deductible_od_premium = 0; $voluntary_deductible_tp_premium = 0;
        $model_config_premium = [
            'policyHeader' => [
                'messageId' => '1964',
            ],
            'policy' => [
                'contractType' => Config('constants.IcConstants.iffco_tokio.contractType_Car'),
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
                    'make' => $mmv_data->make_code,
                    'regictrationCity' => $rto_data->rto_city_code,
                    'registrationDate' => $first_reg_date,
                    'seatingCapacity' => $mmv_data->seating_capacity,
                    'type' => ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? 'OD' : '',
                    'vehicleBody' => '',
                    'vehicleClass' => Config('constants.IcConstants.iffco_tokio.contractType_Car'),
                    'vehicleCoverage' => [
                        'item' => $VehicleCoverages_arr
                    ],
                    'vehicleInsuranceCost' => '',
                    'vehicleSubclass' => Config('constants.IcConstants.iffco_tokio.contractType_Car'),
                    'yearOfManufacture' => $yearOfManufacture,
                    'zcover' => ($tp_only) ? config('constants.IcConstants.iffco_tokio.zcover_bike_tp') : config('constants.IcConstants.iffco_tokio.zcover_bike_co'),
                ],
            ],
            'partner' =>
            [
                'partnerBranch' => Config('constants.IcConstants.iffco_tokio.partnerBranchCar'),
                'partnerCode' => Config('constants.IcConstants.iffco_tokio.partnerCodeCar'),
                'partnerSubBranch' => Config('constants.IcConstants.iffco_tokio.partnerSubBranchCar'),
            ],
        ];

        $ncb_amount = 0; $pa_unnamed = 0; $voluntary_excess = 0;
        $aai_discount = 0; $anti_theft = 0; $electric_accessories = 0; $non_electric_accessories = 0; $cng_od_internal = 0; $cng_tp_internal = 0;
        $cng_od_premium = 0; $cng_tp_premium = 0; $tppd_discount = 0; $pa_owner_driver = 0; $legal_liability_paid_driver = 0;
        $dep_value  = 0;
        $towing = 0;
        $addon_total = 0;
        $consumable_value = 0;
        $ncb_protection_value = 0;
        
        $get_response = getWsData(
            $premium_url,
            $model_config_premium,
            'iffco_tokio', [
                'root_tag' => $root_tag,
                'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:prem="http://premiumwrapper.motor.itgi.com"><soapenv:Header/><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                'requestMethod' => 'post',
                'section' => 'CAR',
                'method' => 'Premium Calculation',
                'company' => $productData->company_name,
                'productName' => $productData->product_name. " ($businessType)",
                'enquiryId'	=> $enquiryId,
                'transaction_type' => 'proposal'
            ]
        );
        $data = $get_response['response'];
        if($data) {
            $premium_data = XmlToArray::convert((string)$data);
            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                $ns = ($is_zero_dep == 'Y') ? 'ns2:' : 'ns1:';
                $premium_data = $premium_data['soapenv:Body']['getMotorPremiumResponse'][$ns.'getMotorPremiumReturn'];
                if(isset($premium_data[$ns.'error'][$ns.'errorCode']) && !empty($premium_data[$ns.'error'][$ns.'errorCode'])){
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $premium_data[$ns.'error'][$ns.'errorMessage']
                    ];
                }

                if (empty($premium_data[$ns.'coveragePremiumDetail'])) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Something went wrong'
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
                        $tp_premium = $v[$ns.'tpPremium'];
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
                        $voluntary_excess = $v[$ns.'odPremium'] + $v[$ns.'tpPremium'];
                        $voluntary_deductible_od_premium = $v[$ns.'odPremium'];
                        $voluntary_deductible_tp_premium = $v[$ns.'tpPremium'];
                    } else if ($coverage_name == "TPPD") {
                        $tppd_discount = intval($v[$ns.'tpPremium']) == 1 ? 0 : $v[$ns.'tpPremium'];
                    } else if($coverage_name == 'Legal Liability to Driver') {
                        $legal_liability_paid_driver = (abs($v[$ns.'tpPremium']));
                    } else if($coverage_name == 'Electrical Accessories') {
                        $electric_accessories = ($v[$ns.'odPremium']);
                    } else if($coverage_name == 'Cost of Accessories') {
                        $non_electric_accessories = ($v[$ns.'odPremium']);
                    } else if($coverage_name == 'CNG Kit') {
                        $cng_od_premium = ($v[$ns.'odPremium']);
                        $cng_tp_premium = ($v[$ns.'tpPremium']);
                    } else if($coverage_name == 'AAI Discount') {
                        $aai_discount = ($v[$ns.'odPremium']);
                    } else if($coverage_name == 'Anti-Theft') {
                        $anti_theft = ($v[$ns.'odPremium']);
                    } else if($coverage_name == 'CNG Kit Company Fit') {
                        $cng_od_premium = ($v[$ns.'odPremium']);
                        $cng_tp_premium = ($v[$ns.'tpPremium']);
                    }
                    else if ($coverage_name == 'Consumable') {
                        $consumable_value = $v[$ns.'coveragePremium'];
                    }
                    else if ($coverage_name == 'NCB Protection') {
                        //On UAT Getting premium in 'coveragePremium' tag but in production getting in 'odPremium' Tag - @Amit
                        if(!is_array($v[$ns.'coveragePremium'])) {
                            $ncb_protection_value = ($v[$ns.'coveragePremium']);
                        }elseif(!is_array($v[$ns.'odPremium'])) {
                            $ncb_protection_value = ($v[$ns.'odPremium']);
                        }
                        //$ncb_protection_value = $v[$ns.'coveragePremium'];
                    }
                }

                $total_od_premium = $premium_data[$ns.'totalODPremium'];
                $total_tp_premium = $premium_data[$ns.'totalTPPremium'];
                $addon_total = $dep_value + $towing + $consumable_value + $ncb_protection_value;
                $voluntary_excess = $voluntary_deductible_od_premium + $voluntary_deductible_tp_premium;
                $discount_amount = abs($premium_data[$ns.'discountLoadingAmt']);
                $service_tax = $premium_data[$ns.'serviceTax'];
                $od_discount_amt = $premium_data[$ns.'discountLoadingAmt'];
                $od_discount_loading = $premium_data[$ns.'discountLoading'];
                $od_sum_dis_loading = $premium_data[$ns.'totalODPremium'];
                $od_premium = $od_premium + $discount_amount;
                $pa_unnamed = intval($pa_unnamed) == 1 ? 0 : $pa_unnamed;
                $ncb_amount = intval($ncb_amount) == 1 ? 0 : $ncb_amount;
                $pa_owner_driver = intval($pa_owner_driver) == 1 ? 0 : $pa_owner_driver;
                $od_discount_amount = abs($ncb_amount) + abs($discount_amount) + abs($voluntary_excess) + abs($anti_theft);
                $total_discount_amount = $od_discount_amount + abs($tppd_discount);
            } else if ($requestData->business_type == 'newbusiness') {
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
                        $tp_premium = $prm['tp'];
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
                        $voluntary_excess = $prm['od'] + $prm['tp'];
                        $voluntary_deductible_od_premium = $prm['od'];
                        $voluntary_deductible_tp_premium = $prm['tp'];
                    } else if ($coverage_name == "TPPD") {
                        $prm = self::calculateNBPremium(($v));
                        $tppd_discount = $prm['tp'];
                    }  else if($coverage_name == 'Legal Liability to Driver') {
                        $prm = self::calculateNBPremium(($v));
                        $legal_liability_paid_driver = $prm['tp'];
                    } else if($coverage_name == 'Electrical Accessories') {
                        $prm = self::calculateNBPremium(($v));
                        $electric_accessories = $prm['od'];
                    } else if($coverage_name == 'Cost of Accessories') {
                        $prm = self::calculateNBPremium(($v));
                        $non_electric_accessories = $prm['od'];
                    } else if($coverage_name == 'CNG Kit') {
                        $prm = self::calculateNBPremium(($v));
                        $cng_od_premium = $prm['od'];
                        $cng_tp_premium = $prm['tp'];
                    } else if($coverage_name == 'AAI Discount') {
                        $prm = self::calculateNBPremium(($v));
                        $aai_discount = $prm['od'];
                    } else if($coverage_name == 'Anti-Theft') {
                        $prm = self::calculateNBPremium(($v));
                        $anti_theft = $prm['od'];
                    } else if($coverage_name == 'CNG Kit Company Fit') {
                        $prm = self::calculateNBPremium(($v));
                        $cng_od_internal = $prm['od'];
                        $cng_tp_internal = $prm['tp'];
                    }
                    else if ($coverage_name == 'Consumable') {
                        $prm = self::calculateNBPremium(($v));
                        $consumable_value = $prm['od'];
                    }
                    else if ($coverage_name == 'NCB Protection') {
                        $prm = self::calculateNBPremium(($v));
                        $ncb_protection_value = $prm['od'];
                    }
                }

                $total_od_premium = $premium_data['totalODPremium'];
                $total_tp_premium = $premium_data['totalTPPremium'];
                $addon_total = $dep_value + $towing + $consumable_value + $ncb_protection_value;
                $voluntary_excess = $voluntary_deductible_od_premium + $voluntary_deductible_tp_premium;
                $discount_amount = abs($premium_data['discountLoadingAmt']);
                $service_tax = $premium_data['gstAmount'];
                $od_discount_amt = $premium_data['discountLoadingAmt'];
                $od_discount_loading = $premium_data['discountLoading'];
                $od_sum_dis_loading = $premium_data['totalODPremium'];
                $od_premium = $od_premium + $discount_amount;
                $pa_unnamed = intval($pa_unnamed) == 1 ? 0 : $pa_unnamed;
                $ncb_amount = intval($ncb_amount) == 1 ? 0 : $ncb_amount;
                $pa_owner_driver = intval($pa_owner_driver) == 1 ? 0 : $pa_owner_driver;
                $od_discount_amount = abs($ncb_amount) + abs($discount_amount) + abs($voluntary_excess) + abs($anti_theft);
                $total_discount_amount = $od_discount_amount + abs($tppd_discount);
            }

            unset($data);
            $od_premium_amount = $od_premium + $electric_accessories + $non_electric_accessories +  $cng_od_premium + $cng_od_internal;
            $total_od_amount = $od_premium_amount - $od_discount_amount;
            $tp_premium_amount = $tp_premium + $pa_owner_driver + $pa_unnamed + $legal_liability_paid_driver + $cng_tp_premium + $cng_tp_internal;
            // $net_premium = $od_premium_amount + $tp_premium - $total_discount_amount + $addon_total;
            $net_premium = $od_premium_amount + $tp_premium_amount + $addon_total - $total_discount_amount;
            $service_tax = ($net_premium * 18 / 100);
            $total_amount_payable = $net_premium + $service_tax;

            $vehicleDetails = [
                'manufacture_name' => $mmv_data->manufacture,
                'model_name' => $mmv_data->model,
                'version' => $mmv_data->variant,
                'fuel_type' => $mmv_data->fuel_type,
                'seating_capacity' => $mmv_data->seating_capacity,
                'carrying_capacity' => $mmv_data->seating_capacity - 1,
                'cubic_capacity' => $mmv_data->cc,
                'gross_vehicle_weight' => '',
                'vehicle_type' => '4w'
            ];

            $length = 13;
            $unique_quote = mt_rand(pow(10,($length-1)),pow(10,$length)-1);
            $total_sum_insured = $motor_electric_accessories + $motor_non_electric_accessories + $motor_lpg_cng_kit;
            $total_sum_insured += $tp_only ? 1 : (int) $proposal->idv;

            $additional_details_data = array(
                "unique_quote" => $unique_quote,
                "od_discount_amt" => $od_discount_amt,
                "tp_premium" => $tp_premium,
                "pa_unnamed" => $pa_unnamed,
                "cpa_premium" => $pa_owner_driver,
                "total_tp_premium" => $total_tp_premium,
                "od_discount_loading" => gettype($od_discount_loading) == 'array' ? 0 : $od_discount_loading,
                "od_sum_dis_loading" => $od_sum_dis_loading,
                "ncb_renewal_policy" => $requestData->applicable_ncb,
                "towing" => $towing,
                "total_od" => $total_od_premium,
                "zero_dep_value" => $dep_value,
                "consumable_value" => $consumable_value,
                "ncb_protection_value" => $ncb_protection_value,
                "voluntary_excess_od" => abs($voluntary_deductible_od_premium),
                "voluntary_excess_tp" => $voluntary_deductible_tp_premium,
                "voluntary_excess_amt" => $voluntary_insurer_discounts,
                "legal_liability_paid_driver" => $legal_liability_paid_driver,
                "anti_theft" => $anti_theft,
                "electrical_accessories" => $electric_accessories,
                "non_electrical_accessories" => $non_electric_accessories,
                "cng_od_premium" => $cng_od_premium,
                "cng_tp_premium" => $cng_tp_premium,
                "cng_od_internal" => $cng_od_internal,
                "cng_tp_internal" => $cng_tp_internal,
                "tppd_discount" => abs($tppd_discount),
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'total_sum_insured' => $total_sum_insured,
                'total_ci' => 0,
                'od_premium' => $od_premium,
            );

            $NewBusinessTpEndDate = date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));

            $policyEndDate = ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') 
                ? $NewBusinessTpEndDate 
                :  date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                
            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policyEndDate)),
                    'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) : date('d-m-Y', strtotime($policy_start_date)),
                    'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime($policy_start_date))) : date('d-m-Y', strtotime($policy_end_date))),
                    'proposal_no' => $unique_quote,
                    'unique_proposal_id' => $unique_quote,
                    'od_premium' => $total_od_amount,
                    'tp_premium' => $total_tp_premium,
                    'addon_premium' => $addon_total + $electric_accessories + $non_electric_accessories + $cng_od_premium + $cng_od_internal,
                    'cpa_premium' => $pa_owner_driver,
                    'pa_unnamed' => $pa_unnamed,
                    'final_premium' => $total_amount_payable,
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
            if ($is_breakin && !$tp_only) {
                $breakinExists = CvBreakinStatus::where('user_proposal_id', $proposal->user_proposal_id)->first();
                if ($breakinExists) {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "BreakIn already exists. Inspection No. : " . $breakinExists->breakin_number . " Inspection status : " . $breakinExists->breakin_status,
                    ]);
                }
                $payload = [
                    'user_name' => $proposal->first_name . ' ' . $proposal->last_name,
                    'user_email' => $proposal->email,
                    'reg_number' => $proposal->vehicale_registration_number,
                    'veh_manuf' => $mmv_data->manufacture,
                    'veh_model' => $mmv_data->model,
                    'mobile_name' => $proposal->mobile_number,
                    'fuel_type' => $mmv_data->fuel_type,
                    'veh_variant' => $mmv_data->variant,
                    'vehicle_category' => 'car', // Should be as per Documentation
                    'enquiry_id' => $enquiryId,
                    'address' => implode(', ', [$proposal->address_line1, $proposal->address_line2, $proposal->address_line3, $proposal->state]),
                    'city' => $proposal->city,
                    'model_year' => $requestData->manufacture_year,
                    'section' => 'car',
                    'ic_name' => 'iffco_tokio'
                ];
                $BREAKIN_SERVICE = config('IC.IFFCOTOKIO.CAR.BREAKIN.WIMWISURE.ENABLE');
                if ($BREAKIN_SERVICE == 'Y') 
                {
                    $payload['mobile_number'] = $proposal->mobile_number;
                    $payload['chassis_number'] = $proposal->chassis_number;
                    $payload['engine_number'] = $proposal->engine_number;
                    $payload['api_key'] = config('IC.IFFCOTOKIO.CAR.BREAKIN.WIMWISURE.API');
                    $wimwisure = new WimwisureBreakinController();
                    $breakin_data = $wimwisure->WimwisureBreakinIdGen($payload);
                    if ($breakin_data) {
                        if ($breakin_data->original['status'] == true && ! is_null($breakin_data->original['data']))
                        {
                            $wimwisure_case_number = $breakin_data->original['data'];
                            $wimwisure_case_number = json_decode($wimwisure_case_number)->ID;
                            DB::table('cv_breakin_status')->insert([
                                'user_proposal_id' => $proposal->user_proposal_id,
                                'ic_id' => $productData->company_id,
                                'wimwisure_case_number' => $wimwisure_case_number,
                                'breakin_number' => (isset($wimwisure_case_number)) ? $wimwisure_case_number : '',
                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                            $inspection_no  = $wimwisure_case_number;
                            $is_breakin = 'Y';
                            $proposal->is_breakin_case = 'Y';
                            $proposal->save();
                            updateJourneyStage([
                                'user_product_journey_id' => $enquiryId,
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        }
                        else
                        {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $breakin_data->original['data']['message'] ?? 'An error occurred while sending data to wimwisure '
                            ];
                        }

                    }
                    else
                    {

                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Error in wimwisure breakin service'
                        ];
                    }
                }
                else
                {
                
                $obj = new LivechekBreakinController();
                $create_breakin = $obj->LiveChekBreakin($payload);
                if ($create_breakin['status']) { // If the status is true then LiveChek API is success
                    $inspection_no = isset($create_breakin['data']['data']) ? $create_breakin['data']['data']['refId'] : $create_breakin['data']['refId'];
                    $proposal->is_breakin_case = 'Y';
                    $proposal->save();
                    $cvBreakinStatus = [
                        'ic_id' => $master_policy->insurance_company_id,
                        'user_proposal_id' => $proposal->user_proposal_id,
                        'breakin_number' => $inspection_no,// Get inspection no. from LiveChek
                        'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                        'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                        'breakin_response' => json_encode($create_breakin['data']),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    DB::table('cv_breakin_status')->updateOrInsert(['user_proposal_id' => $proposal->user_proposal_id], $cvBreakinStatus);
                    
                    $is_breakin = 'Y';
                    $data['stage'] = STAGE_NAMES['INSPECTION_PENDING'];
                }else{
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Error while generating vehicle inspection. Please try after sometime.",
                        'livechek_error' => $create_breakin
                    ];
                }
            }
            } else {
                $is_breakin = '';
                $inspection_no = '';
                $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            }

            IffcoTokioPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
            updateJourneyStage($data);

            return response()->json([
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal->user_proposal_id,
                    'userProductJourneyId' => $data['user_product_journey_id'],
                    'proposalNo' => $unique_quote,
                    'finalPayableAmount' => $total_amount_payable,
                    'is_breakin' => $is_breakin,
                    'inspection_number' => $inspection_no
                ]
            ]);
        } else {
            $return_data =  [
                'status'  => false,
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