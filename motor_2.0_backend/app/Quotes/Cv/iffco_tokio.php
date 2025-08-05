<?php

use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/iffco_tokio_short_term.php';
function getQuote($enquiryId, $requestData, $productData)
{
    if(config("IFFCO_PCV_GCV_ONLY_UAT") == 'Y')
    {
        return getPcvGcvQuote($enquiryId, $requestData, $productData);
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
   
    $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
    if ($is_GCV) {
        $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio', $requestData->gcv_carrier_type);
    }else{
        $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
    
    }
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);
    $rto_code = $requestData->rto_code;
    
    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available',
            'request'=> $requestData->rto_code
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

    if (empty($rto_data) || empty($rto_data->rto_city_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available'
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        
    $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false;
    if(!$tp_only && $requestData->previous_policy_type == "Third-party") {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quote not available when previous policy type is Third Party',
            'request'=> [
                'previous_policy_type' => $requestData->previous_policy_type,
                'premium_type'=>$premium_type
            ]
        ];
    }

    if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
        return shortTermPremium($enquiryId, $requestData, $productData, $rto_data, $mmv_data, $city_name);
    }
    
    $is_breakin = false;
    if ($requestData->business_type == 'newbusiness') {
        $policy_start_date = today();
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
        $vehicle_in_90_days = 'N';
        $businesstype = 'New Business';
    } else {
        if ($requestData->business_type == 'breakin') {
            $policy_start_date = Carbon::parse(date('d-m-Y'))->addDay(3);
            $is_breakin = true;
            $businesstype = 'Break-in';
            /* return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quote not available when business type is break-in', // break-in not allowed for PCV/GCV
                'request'=> [
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'business_type' => $requestData->business_type
                ]
            ]; */
        }else{
            $policy_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1);
            $businesstype = 'Rollover';
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
        $vehicle_in_90_days = 'N';
    }

    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $car_age = ceil($age / 12);
    if($car_age > 90){
        $vehicle_in_90_days = 'Y';
    }
    // zero depriciation validation
    $is_zero_dep_product = false;
    if ($productData->zero_dep == '0') {
        $is_zero_dep_product = true;
    }
    if ($car_age > 6 && $is_zero_dep_product && in_array($productData->company_alias, explode(',', config('CV_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero dep is not allowed for vehicle age greater than 6 years',
            'request' => [
                'productData' => $productData,
                'message' => 'Zero dep is not allowed for vehicle age greater than 6 years',
                'car_age' => $car_age
            ]
        ];
    }
    $applicable_addons = [];


    $idv_request = [
        "soapenv:Header" => [],
        "soapenv:Body" => [
            "prem:getVehicleCVIIdv" => [
                "prem:idvWebServiceRequest" => [
                    "prem:attribute1" => [],
                    "prem:attribute2" => [],
                    "prem:attribute3" => [],
                    "prem:attribute4" => [],
                    "prem:attribute5" => [],
                    "prem:contractType" => "CVI",
                    "prem:dateOfRegistration" => date('m/d/Y', strtotime($requestData->vehicle_register_date)),
                    "prem:inceptionDate" => date('m/d/Y 00:00:00', strtotime($policy_start_date)),
                    "prem:makeCode" => $mmv_data['make_code'],
                    "prem:model" => [],
                    "prem:rtoCity" => $rto_data->rto_city_code,
                    "prem:vehicleClass" =>  $is_GCV ? $mmv_data['class'] : "C",
                    "prem:vehicleSubClass" =>  $is_GCV ? $mmv_data['sub_class'] : config('constants.cv.iffco.IFFCO_TOKIO_PCV_VEHICLE_SUBCLASS'),
                    "prem:yearOfMake" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                ],
            ],
        ],
    ];

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            'SOAPAction' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'Content-Type' => 'text/xml; charset="utf-8"',
        ],
        'requestMethod' => 'post',
        'requestType' => 'xml',
        'section' =>  $is_GCV ? 'GCV' : 'PCV',
        'method' => 'IDV Calculation',
        'transaction_type' => 'quote',
        'productName' => $productData->product_sub_type_name,
    ];
    $root = [
        'rootElementName' => 'soapenv:Envelope',
        '_attributes' => [
            "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
            "xmlns:prem" => "http://premiumwrapper.motor.itgi.com",
        ],
    ];
    $input_array = ArrayToXml::convert($idv_request, $root, false, 'utf-8');

    // Dont't hit IDV service incase of Third Party
    if (!$tp_only) {
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_IDV_URL'), $input_array, 'iffco_tokio', $additional_data);
        $idv_data = $get_response['response'];
    } else {
        $idv_data = true;
    }
    if ($idv_data) {

        $idv = $min_idv = $max_idv = $ElectricalaccessSI = $NonElectricalaccessSI = $externalCNGKITSI = $PAforUnnamedPassengerSI = 0;
        if (!$tp_only) {
            $idv_data = XmlToArray::convert($idv_data);
            $error_msg = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:erorMessage'];
            if (is_string($error_msg)) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $error_msg ?? 'Insurer not reachable',
                ];
            }
            $idv = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:idv'];
            $max_idv = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:maximumIdvAllowed'];
            $min_idv = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:minimumIdvAllowed'];
        }
        if ($requestData->is_idv_changed == 'Y' && $tp_only == false) {
            if ($requestData->edit_idv >= floor($max_idv)) {
                $idv = floor($max_idv);
            } elseif ($requestData->edit_idv <= ceil($min_idv)) {
                $idv = ceil($min_idv);
            } else {
                $idv = $requestData->edit_idv;
            }
        } else {
            #$idv = $min_idv;
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $idv = $idv;
                    $skip_second_call = true;
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
        foreach ($accessories as $key => $access) {
            if (in_array('Electrical Accessories', $access) && $tp_only == false) {
                $ElectricalaccessSI = $access['sumInsured'];
            }
            
            if (in_array('Non-Electrical Accessories', $access) && $tp_only == false) {
                $NonElectricalaccessSI = $access['sumInsured'];
            }
            
            if ($access['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                $externalCNGKITSI = $access['sumInsured'];
            }
        }

        foreach ($additional_covers as $key => $value) {
            if ('PA cover for additional paid driver' == $value['name']) {
                $additional_PA_coverSI = $value['sumInsured'];
            }
            if ('LL paid driver' == $value['name']) {
                $LLPaidDriverSI = $value['sumInsured'];
            }
            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
        }
        $vehicle_coverage = [];
        $vehicle_coverage[] = [
            'coverageId' => 'IDV Basic',
            'number' => [],
            'sumInsured' => $tp_only ? 1 : $idv,
        ];
        $vehicle_coverage[] = [
            'coverageId' => 'No Claim Bonus',
            'number' => [],
            'sumInsured' => $tp_only ? 0 : $requestData->applicable_ncb,
        ];
        $vehicle_coverage[] = [
            'coverageId' => 'PA Owner / Driver',
            'number' => '',
            'sumInsured' => $requestData->vehicle_owner_type == 'I' ? 'Y' : 'N',
        ];
        if ($is_zero_dep_product) {   
            $vehicle_coverage[] = [
                'coverageId' => 'Consumable',
                'number' => '',
                'sumInsured' => 'Y',
            ];
        }
        /* $vehicle_coverage[] = [
        'coverageId' => 'Electrical Accessories',
        'number' => '',
        'sumInsured' => $ElectricalaccessSI
        ]; */
        /* $vehicle_coverage[] = [
        'coverageId' => 'Cost of Accessories',
        'number' => '',
        'sumInsured' => $NonElectricalaccessSI
        ]; */
        if (in_array($requestData->fuel_type, ["CNG", "LPG"]) && (int) $externalCNGKITSI == 0) {
            $vehicle_coverage[] = [
                'coverageId' => 'CNG Kit Company Fit',
                'number' => '',
                'sumInsured' => "Y",
            ];
        } elseif ((int) $externalCNGKITSI > 0) {
            $vehicle_coverage[] = [
                'coverageId' => 'CNG Kit',
                'number' => '',
                'sumInsured' => $externalCNGKITSI,
            ];
        }
        
        $inceptionDate = date('m/d/Y 00:00:00', strtotime($policy_start_date));
        $expiryDate = date('m/d/Y 23:59:59', strtotime($policy_end_date));
 /*
        "MAKE_CODE" => "A1A0303"
        "MANUFACTURER" => "ASHOK LEYLAND"
        "MODEL" => "DOST"
        "VARIANT" => "LE - TIPPER CLOSED BODY POWER PACK OPERATED"
        "CC" => "1"
        "SEATING_CAPACITY" => "3"
        "FUEL_TYPE" => "Diesel"
        "EXSHOWROOM_PRICE" => "689500"
        "SUB_CLASS" => "A1"
        "CLASS" => "A"
        "MODEL_CODE" => "C.1AC"
        "MAKE_CODE_NAME" => "ICV CLASS C.1A CC1500 CARRY 5"
        "SUB_CLASS_NAME" => "FOUR WHEELED VEHICLES WITH CARRYING CAPACITY NOT EXCEEDING 6 PASSENGERS"
        "ic_version_code" => "HCY3L"
        "no_of_wheels" => "0"
       */ 
        $service_request = [
            "soapenv:Header" => [],
            'soapenv:Body' => [
                'getMotorPremium' => [
                    '_attributes' => [
                        "xmlns" => "http://premiumwrapper.motor.itgi.com",
                    ],
                    'policy' => [
                        'contractType' => 'CVI',
                        'inceptionDate' => $inceptionDate,
                        'expiryDate' => $expiryDate,
                        'previousPolicyEndDate' => date('m/d/Y', strtotime($requestData->previous_policy_expiry_date)),
                        'vehicle' => [
                            'capacity' => $mmv_data['cc'] ?? null,
                            'engineCpacity' =>$mmv_data['cc'] ?? null,
                            'make' => $mmv_data['make_code'],
                            'registrationDate' => date('m/d/Y', strtotime($requestData->vehicle_register_date)),
                            'seatingCapacity' => $mmv_data['seating_capacity'],
                            'regictrationCity' => $rto_data->rto_city_code,
                            'yearOfManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'zcover' => $tp_only ? 'AC' : 'CO',
                            'type' => [],
                            'itgiRiskOccupationCode' => [],
                            'grossVehicleWeight' =>  $mmv_data['grossVehicleWt'] ?? null,
                            'validDrivingLicence' => 'Y',
                            'nofOfCarTrailers' => 0,
                            'noOfLuggageTrailers' => 0,
                            'luggageAverageIDV' => 0,
                            'vehicleCoverage' => ['item' => $vehicle_coverage],
                        ],

                    ],
                    'partner' => [
                        'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
                        'partnerBranch' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_BRANCH'),
                        'partnerSubBranch' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_SUB_BRANCH'),
                    ],
                ],
            ],
        ];

        $additional_data['method'] = ($tp_only ? 'TP - ' : '') . 'Premium Calculation';
        $additional_data['transaction_type'] = 'quote';

        $input_array = ArrayToXml::convert($service_request, $root, false, 'utf-8');

        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL'), $input_array, 'iffco_tokio', $additional_data);
        $service_response = $get_response['response'];
        if (empty($service_response)) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $service_response = XmlToArray::convert($service_response);
        if (!isset($service_response['soapenv:Body'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        if(!empty($service_response['soapenv:Body']['soapenv:Fault']['faultstring'] ?? '')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $service_response['soapenv:Body']['soapenv:Fault']['faultstring'],
            ];
        }
        $anti_theft = $aai_discount = $pa_unnamed = $pa_owner_driver = $legalLiability_to_driver = $cngOdPremium = $cngTpPremium = $ncb_amount = $elecAccSumInsured = $nonelecAccSumInsured = $voluntary_deductible_od_premium = $voluntary_deductible_tp_premium = $tppd_discount = $dep_value = $towing_related_cover = $consumable = 0;

        $response_body = $service_response['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'];

        $response_body1 = $response_body[0];
        $response_body2 = $response_body[1];
        $response_body = $is_zero_dep_product && !$tp_only ? $response_body[1] : $response_body[0];

        if (isset($response_body['error']) && !empty($response_body['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body['error']['errorMessage'],
            ];
        }
        if (isset($response_body1['error']) && !empty($response_body1['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body1['error']['errorMessage'],
            ];
        }
        if (isset($response_body2['error']) && !empty($response_body2['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body2['error']['errorMessage'],
            ];
        }
        if (is_array($response_body) && count($response_body) < 2) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Error while fetching quote.',
            ];
        }

        $coveragepremiumdetail = $response_body['coveragePremiumDetail'];

        foreach ($coveragepremiumdetail as $k => $v) {
            $coverage_name = $v['coverageName'];
            if (is_array($v['odPremium'])) {
                $v['odPremium'] = (!empty($v['odPremium']['@value']) ? $v['odPremium']['@value'] : '0');
            }
            if (is_array($v['tpPremium'])) {
                $v['tpPremium'] = (!empty($v['tpPremium']['@value']) ? $v['tpPremium']['@value'] : '0');
            }
            if ($coverage_name == 'IDV Basic') {
                $od_premium = ($v['odPremium']);
                $od_amt = ($v['odPremium']);
                $tp_premium = ($v['tpPremium']);
            } else if ($coverage_name == 'No Claim Bonus') {
                $ncb_amount = (abs($v['odPremium']));
            } else if ($coverage_name == 'PA Owner / Driver') {
                $pa_owner_driver = (abs($v['tpPremium']));
            } else if ($coverage_name == 'PA to Passenger') {
                $pa_unnamed = (abs($v['tpPremium']));
            } else if ($coverage_name == 'Legal Liability to Driver') {
                $legalLiability_to_driver = (abs($v['tpPremium']));
            } else if ($coverage_name == 'Electrical Accessories') {
                $elecAccSumInsured = ($v['odPremium']);
            } else if ($coverage_name == 'Cost of Accessories') { //non electrical accessories
                $nonelecAccSumInsured = ($v['odPremium']);
            } else if (in_array($coverage_name, ['CNG Kit', 'CNG Kit Company Fit'])){
                $cngOdPremium += ($v['odPremium']);
                $cngTpPremium += ($v['tpPremium']);
            } else if ($coverage_name == 'AAI Discount') {
                $aai_discount = ($v['odPremium']);
            } else if ($coverage_name == 'Anti-Theft') {
                $anti_theft = ($v['odPremium']);
            } else if ($coverage_name == 'Depreciation Waiver') {
                $dep_value = ($v['coveragePremium']);
            } else if ($coverage_name == 'Consumable') {
                $consumable = ($v['coveragePremium']);
            } else if ($coverage_name == 'Towing & Related') {
                $towing_related_cover = ($v['coveragePremium']);
            } else if ($coverage_name == 'Voluntary Excess') {
                $voluntary_deductible_od_premium = ($v['odPremium']);
                $voluntary_deductible_tp_premium = ($v['tpPremium']);
            } else if ($coverage_name == 'TPPD') { //non electrical accessories
                $tppd_discount = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            }
        }
        // IMT - 23 Calculation - Start
        $imt_23 = 0;
        /* if (!$tp_only) {
        $imt_23 = round(($od_amt + $ncb_amount) * 0.15);
        } */
        // IMT - 23 Calculation - End
        $total_premium_after_discount = $response_body['totalPremimAfterDiscLoad'];
        $other_discount = (abs($response_body['discountLoadingAmt']));
        $service_tax = $response_body['serviceTax'];
        $base_premium_amount = $total_amount_payable = $response_body['premiumPayable'];
        //$od_premium = $od_premium + $other_discount;

        if ($is_zero_dep_product) {
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => round($dep_value),
                ],
                'additional' => [
                    'consumables' => round($consumable),
                ],
                'other' => [],
            ];
        }else{
            $add_ons_data = [
                'in_built' => [],
                'additional' => [],
                'other' => [],
            ];
        }
        if($car_age < 7) { // Less than 7 i.e. upto 6 and including 6
            array_push($applicable_addons, "zeroDepreciation");
            array_push($applicable_addons, "consumables");
        }
        $add_ons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
        $add_ons_data['additional_premium'] = array_sum($add_ons_data['additional']);
        $add_ons_data['other_premium'] = array_sum($add_ons_data['other']);

        $voluntary_deductible = 0;
        $voluntary_deductible_od_premium == '0' ? $voluntary_deductible_od_premium = '0' : $voluntary_deductible = $voluntary_deductible_od_premium;

        $voluntary_deductible_tp_premium == '0' ? $voluntary_deductible_tp_premium = '0' : $voluntary_deductible = $voluntary_deductible_tp_premium;

        $total_od_premium = round($od_premium) + round($nonelecAccSumInsured) + round($elecAccSumInsured) + round($cngOdPremium);
        //$tp_premium                 = $tp_premium  - abs($tppd_discount);
        $total_tp_premium = $tp_premium + $legalLiability_to_driver + $pa_unnamed + $cngTpPremium;

        $total_discount_premium = round($ncb_amount) + round(abs($aai_discount)) + abs($anti_theft) + round(abs($voluntary_deductible)) + round(abs($other_discount)) + round(abs($tppd_discount));

        $total_base_premium = round($total_od_premium) + $total_tp_premium - $total_discount_premium;

        $data_response = [
            'status' => true,
            'msg' => 'Found',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'Data' => [
                'idv' => $idv,
                'min_idv' => $min_idv,
                'max_idv' => $max_idv,
                'vehicle_idv' => $min_idv,
                'qdata' => null,
                'pp_enddate' => $requestData->previous_policy_expiry_date,
                'addonCover' => null,
                'addon_cover_data_get' => '',
                'rto_decline' => null,
                'rto_decline_number' => null,
                'mmv_decline' => null,
                'mmv_decline_name' => null,
                'policy_type' => $tp_only ? "Third Party" : (($premium_type == 'own_damage') ? "Own Damage" : "Comprehensive"),
                'business_type' => $businesstype,
                'cover_type' => '1YC',
                'hypothecation' => '',
                'hypothecation_name' => '',
                'vehicle_registration_no' => $requestData->rto_code,
                'rto_no' => $rto_code,
                'version_id' => $requestData->version_id,
                'selected_addon' => [],
                'showroom_price' => 0,
                'fuel_type' => $requestData->fuel_type,
                'ncb_discount' => $requestData->applicable_ncb,
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                'product_name' => $productData->product_sub_type_name,
                'mmv_detail' => $mmv_data,
                'master_policy_id' => [
                    'policy_id' => $productData->policy_id,
                    'policy_no' => $productData->policy_no,
                    'policy_start_date' => $policy_start_date,
                    'policy_end_date' => $policy_end_date,
                    'sum_insured' => $productData->sum_insured,
                    'corp_client_id' => $productData->corp_client_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'insurance_company_id' => $productData->company_id,
                    'status' => $productData->status,
                    'corp_name' => '',
                    'company_name' => $productData->company_name,
                    'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_sub_type_name' => $productData->product_sub_type_name,
                    'flat_discount' => $productData->default_discount,
                    'predefine_series' => '',
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                ],
                'motor_manf_date' => $requestData->vehicle_register_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => [
                    'master_policy_id' => $productData->policy_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'segment_id' => 0,
                    'rto_cluster_id' => 0,
                    'car_age' => $car_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' => round(abs($other_discount)),
                ],
                'ic_vehicle_discount' => round(abs($other_discount)),
                'basic_premium' => round($od_premium),
                'motor_electric_accessories_value' => $elecAccSumInsured,
                'motor_non_electric_accessories_value' => $nonelecAccSumInsured,
                'motor_lpg_cng_kit_value' => $cngOdPremium,
                'total_accessories_amount(net_od_premium)' => 0,
                'total_own_damage' => round($total_od_premium),
                'tppd_premium_amount' => round($tp_premium),
                'compulsory_pa_own_driver' => $pa_owner_driver, // Not added in Total TP Premium
                'cover_unnamed_passenger_value' => round($pa_unnamed), //$pa_unnamed,
                'default_paid_driver' => $legalLiability_to_driver,
                'll_paid_driver_premium' => $legalLiability_to_driver,
                'll_paid_conductor_premium' => 0,
                'll_paid_cleaner_premium' => 0,
                'motor_additional_paid_driver' => 0,
                'cng_lpg_tp' => $cngTpPremium,
                'seating_capacity' => $mmv_data['seating_capacity'],
                'deduction_of_ncb' => round(abs($ncb_amount)),
                'antitheft_discount' => abs($anti_theft),
                'aai_discount' => round(abs($aai_discount)), //$automobile_association,
                'voluntary_excess' => round(abs($voluntary_deductible)), //$voluntary_excess,
                'other_discount' => round(abs($other_discount)),
                'total_liability_premium' => round($tp_premium),
                'tppd_discount' => round(abs($tppd_discount)),
                'net_premium' => round($base_premium_amount),
                'service_tax_amount' => round($service_tax),
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => round($total_amount_payable),
                'service_data_responseerr_msg' => 'success',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'service_err_code' => null,
                'service_err_msg' => null,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                'ic_of' => $productData->company_id,
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'get_policy_expiry_date' => null,
                'get_changed_discount_quoteid' => 0,
                'GeogExtension_ODPremium' => 0,
                'GeogExtension_TPPremium' => 0,
                'LimitedtoOwnPremises_OD' => 0,
                'LimitedtoOwnPremises_TP' => 0,
                'vehicle_discount_detail' => [
                    'discount_id' => null,
                    'discount_rate' => null,
                ],
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
                'policy_id' => $productData->policy_id,
                'insurane_company_id' => $productData->company_id,
                'max_addons_selection' => null,
                'add_ons_data' => $add_ons_data,
                'applicable_addons' => $applicable_addons,
                'final_od_premium' => round($total_od_premium),
                'final_tp_premium' => round($total_tp_premium),
                'final_total_discount' => round(abs($total_discount_premium)),
                'final_net_premium' => round($total_base_premium),
                'final_gst_amount' => round($service_tax),
                'final_payable_amount' => round($total_amount_payable),
                'mmv_detail' => [
                    'manf_name' => $mmv_data['manufacturer'],
                    'model_name' => $mmv_data['model'],
                    'version_name' => $mmv_data['variant'],
                    'fuel_type' => config('constants.motorConstant.SMS_FOLDER') == 'ace' ? $requestData->fuel_type : $mmv_data['fuel_type'], 
                    'seating_capacity' => $mmv_data['seating_capacity'],
                    'carrying_capacity' => $mmv_data['seating_capacity'],
                    'cubic_capacity' => $mmv_data['cc'],
                    'gross_vehicle_weight' => '',
                    'vehicle_type' => 'Taxi',
                ],
            ],
        ];
        return camelCase($data_response);

    } else {
        return camelCase(
            [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ]
        );
    }
}

function getPcvGcvQuote($enquiryId, $requestData, $productData)
{
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
   
    $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
    if ($is_GCV) {
        $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio', $requestData->gcv_carrier_type);  
    }else{
        $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
    
    }
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);
    $rto_code = $requestData->rto_code;
    
    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available',
            'request'=> $requestData->rto_code
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

    if (empty($rto_data) || empty($rto_data->rto_city_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available'
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        
    $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false;
    $is_imt_23 = $productData->product_identifier == 'IMT-23' ? true : false;
    if(!$tp_only && $requestData->previous_policy_type == "Third-party") {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quote not available when previous policy type is Third Party',
            'request'=> [
                'previous_policy_type' => $requestData->previous_policy_type,
                'premium_type'=>$premium_type
            ]
        ];
    }

    if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
        return shortTermPremium($enquiryId, $requestData, $productData, $rto_data, $mmv_data, $city_name);
    }
    
    $is_breakin = false;
    if ($requestData->business_type == 'newbusiness') {
        $policy_start_date = today();
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
        $vehicle_in_90_days = 'N';
        $businesstype = 'New Business';
    } else {
        if ($requestData->business_type == 'breakin') {
            $policy_start_date = Carbon::parse(date('d-m-Y'))->addDay(3);
            $is_breakin = true;
            $businesstype = 'Break-in';
            /* return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quote not available when business type is break-in', // break-in not allowed for PCV/GCV
                'request'=> [
                    'previous_policy_type' => $requestData->previous_policy_type,
                    'business_type' => $requestData->business_type
                ]
            ]; */
        }else{
            $policy_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1);
            $businesstype = 'Rollover';
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-M-Y');
        $vehicle_in_90_days = 'N';
    }

    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);    
    
    $llpaidDriver = $llpaidCleaner =  $IsLiabilityToPaidCleanerCovered = $llpaidConductor= '';
    $LLNumberCleaner = $LLNumberDriver = $LLNumberConductor = 0;

    $isPACoverPaidDriverSelected ='false';
    $isPACoverPaidDriverAmount='0';
      
    if (!empty($accessories)) {
        foreach ($accessories as $key => $data) {
            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                $motor_lpg_cng_kit = $data['sumInsured'];
                $is_lpg_cng = true;
            }

            if ($data['name'] == 'Non-Electrical Accessories') {
                $motor_non_electric_accessories = $data['sumInsured'];
            }

            if ($data['name'] == 'Electrical Accessories') {
                $motor_electric_accessories = $data['sumInsured'];
            }
        }
    }

    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $vehicle_age=ceil((int) $age / 12);
    $car_age = ceil($age / 12);
    if($car_age > 90){
        $vehicle_in_90_days = 'Y';
    }
    #$applicable_addons =['zeroDepreciation', 'roadSideAssistance', 'IMT-23'];
    $applicable_addons=[];
    $isConsumable = 'Y';
    $isNCBProtection = 'Y';
    if ($vehicle_age > 3) {
        array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
        $isNCBProtection = 'N';
    }

    if ($vehicle_age > 5) {
        $applicable_addons = [];
        $isConsumable = 'N';
        $isNCBProtection = 'N';
    }

    $isConsumable = 'Y';
    $isNCBProtection = 'Y';

    if ($vehicle_age > 3) {
        array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
        $isNCBProtection = 'N';
    } 
    if ($vehicle_age > 5) {
        $applicable_addons = [];
        $isConsumable = 'N';
        $isNCBProtection = 'N';
    }

    // zero depriciation validation
    $is_zero_dep_product = false;
    if ($productData->zero_dep == '0') {
        $is_zero_dep_product = true;
    }
    
    if ($car_age > 5 && $is_zero_dep_product && in_array($productData->company_alias, explode(',', config('CV_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request' => [
                'productData' => $productData,
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                'car_age' => $car_age
            ]
        ];
    }
    if ($car_age > 5 && $is_imt_23) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'IMT 23 is not allowed for vehicle age greater than 5 years',
            'request' => [
                'productData' => $productData,
                'message' => 'IMT 23 is not allowed for vehicle age greater than 5 years',
                'car_age' => $car_age
            ]
        ];
    }
    $applicable_addons = [];

$vehicle_class = ($mmv_data['class'] == null && $is_GCV) ? 'A' : $mmv_data['class'];

    $idv_request = [
        "soapenv:Header" => [],
        "soapenv:Body" => [
            "prem:getVehicleCVIIdv" => [
                "prem:idvWebServiceRequest" => [
                    "prem:attribute1" => [],
                    "prem:attribute2" => [],
                    "prem:attribute3" => [],
                    "prem:attribute4" => [],
                    "prem:attribute5" => [],
                    "prem:contractType" => "CVI",
                    "prem:dateOfRegistration" => date('m/d/Y', strtotime($requestData->vehicle_register_date)),
                    "prem:inceptionDate" => date('m/d/Y 00:00:00', strtotime($policy_start_date)),
                    "prem:makeCode" => $mmv_data['make_code'],
                    "prem:model" => [],
                    "prem:rtoCity" => $rto_data->rto_city_code,
                    "prem:vehicleClass" => $vehicle_class,
                    "prem:vehicleSubClass" =>  $is_GCV ? $mmv_data['sub_class'] : config('constants.cv.iffco.IFFCO_TOKIO_PCV_VEHICLE_SUBCLASS'),
                    "prem:yearOfMake" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                ],
            ],
        ],
    ];

    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            'SOAPAction' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'Content-Type' => 'text/xml; charset="utf-8"',
        ],
        'requestMethod' => 'post',
        'requestType' => 'xml',
        'section' =>  $is_GCV ? 'GCV' : 'PCV',
        'method' => 'IDV Calculation',
        'transaction_type' => 'quote',
        'productName' => $productData->product_name,
    ];
    $root = [
        'rootElementName' => 'soapenv:Envelope',
        '_attributes' => [
            "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
            "xmlns:prem" => "http://premiumwrapper.motor.itgi.com",
        ],
    ];
    $input_array = ArrayToXml::convert($idv_request, $root, false, 'utf-8');

    // Dont't hit IDV service incase of Third Party
    if (!$tp_only) {
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_IDV_URL'), $input_array, 'iffco_tokio', $additional_data);
        $idv_data = $get_response['response'];
    } else {
        $idv_data = true;
    }
    if ($idv_data) {

        $idv = $min_idv = $max_idv = $ElectricalaccessSI = $NonElectricalaccessSI = $externalCNGKITSI = $PAforUnnamedPassengerSI = 0;
        $isPACoverPaidDriverAmount = $legalLiability_to_driver = 0;
        if (!$tp_only) {
            $idv_data = XmlToArray::convert($idv_data);
            $error_msg = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:erorMessage'];
            if (is_string($error_msg)) {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $error_msg ?? 'Insurer not reachable',
                ];
            }
            $idv = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:idv'];
            $max_idv = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:maximumIdvAllowed'];
            $min_idv = $idv_data['soapenv:Body']['getVehicleCVIIdvResponse']['ns1:getVehicleCVIIdvReturn']['ns1:minimumIdvAllowed'];
        }
        if ($requestData->is_idv_changed == 'Y' && $tp_only == false) {
            if ($requestData->edit_idv >= floor($max_idv)) {
                $idv = floor($max_idv);
            } elseif ($requestData->edit_idv <= ceil($min_idv)) {
                $idv = ceil($min_idv);
            } else {
                $idv = $requestData->edit_idv;
            }
        } else {
            #$idv = $min_idv;
            $getIdvSetting = getCommonConfig('idv_settings');
            switch ($getIdvSetting) {
                case 'default':
                    $idv = $idv;
                    $skip_second_call = true;
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
        foreach ($accessories as $key => $access) {
            if (in_array('Electrical Accessories', $access) && $tp_only == false) {
                $ElectricalaccessSI = $access['sumInsured'];
            }
            
            if (in_array('Non-Electrical Accessories', $access) && $tp_only == false) {
                $NonElectricalaccessSI = $access['sumInsured'];
            }
            
            if ($access['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                $externalCNGKITSI = $access['sumInsured'];
            }
        }
        
        $LLNumberSum = 0;
        foreach ($additional_covers as $key => $value) {
            if ('PA cover for additional paid driver' == $value['name']) {
                $additional_PA_coverSI = $value['sumInsured'];
            }
            if ('LL paid driver' == $value['name']) {
                $LLPaidDriverSI = $value['sumInsured'];
            }
            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
            if (in_array('PA cover for additional paid driver', $value)) {
                $isPACoverPaidDriverSelected = 'true';
                $isPACoverPaidDriverAmount = $value['sumInsured'];
            }
            if (!$is_GCV && in_array('Unnamed Passenger PA Cover', $value)) {
                $motor_acc_cover_unnamed_passenger = $value['sumInsured'];
            }
            if (!$is_GCV && $value['name'] == 'Unnamed Passenger PA Cover') {
                $pa_unnamed = $value['sumInsured'];
                if(empty($pa_unnamed) || $pa_unnamed > 100000) {
                    $pa_unnamed = '0';
                }
            }

            if ($is_GCV && $value['name'] == 'LL paid driver/conductor/cleaner') {
                $llpaidDriver = in_array('DriverLL', $value['selectedLLpaidItmes']) ? 'Y' : 'N';
                $llpaidConductor = in_array('ConductorLL', $value['selectedLLpaidItmes']) ? 'Y' : 'N';
                $llpaidCleaner = in_array('CleanerLL', $value['selectedLLpaidItmes']) ? 'Y' : 'N';
                // $IsLiabilityToPaidCleanerCovered = in_array('CleanerLL', $value['selectedLLpaidItmes']) ? 'Yes' : 'No';
                $LLNumberCleaner = $value['LLNumberCleaner'] ?? 0;
                $LLNumberDriver = $value['LLNumberDriver'] ?? 0;
                $LLNumberConductor = $value['LLNumberConductor'] ?? 0;
               
            }
        }


        
        $LLNumberSum = $LLNumberCleaner + $LLNumberDriver + $LLNumberConductor;
        $LLSumInsured = ($llpaidDriver == 'Y' ? 'Y' : ($llpaidConductor == 'Y' ? 'Y' :($llpaidCleaner=='Y' ? 'Y':'N')));            
        $vehicle_coverage = [];
        $vehicle_coverage[] = [
            'coverageId' => 'IDV Basic',
            'number' => $tp_only ? 1 : $idv,
            'sumInsured' => $tp_only ? 1 : $idv,
        ];
        $vehicle_coverage[] = [
            'coverageId' => 'No Claim Bonus',
            'number' => [],
            'sumInsured' => $tp_only ? 0 : $requestData->applicable_ncb,
        ];
        $vehicle_coverage[] = [
            'coverageId' => 'PA Owner / Driver',
            'number' => '',
            'sumInsured' => $requestData->vehicle_owner_type == 'I' ? 'Y' : 'N',
        ];
        if($is_GCV){
            $vehicle_coverage[] = [
                'coverageId' => 'LL Paid Driv/Cleaner/Conductor',
                'number' => $LLNumberSum,
                'sumInsured' => $LLSumInsured,
            ];
        }
        if ($is_zero_dep_product) { 
            
            $vehicle_coverage[] = [
                'coverageId' => 'Consumable',
                'number' => '',
                'sumInsured' => 'Y',
            ];
            $vehicle_coverage[] = 
            [
                'coverageId' => 'Towing & Related',
                'number' => '',
                'sumInsured' => 'Y',   
            ];
            if($is_imt_23)
            {
                $vehicle_coverage[] =
                [
                     'coverageId' => 'IMT 23',
                     'number' => '',
                     'sumInsured' => 'Y',
                ];
            }
            $vehicle_coverage[] = 
            [
                'coverageId' => 'Depreciation Waiver',
                'number' => '',
                'sumInsured' => 'Y' 
            ];     
        }
        if($is_GCV){
            if($ElectricalaccessSI > 0)
            {
                $vehicle_coverage[] =
                [
                    'coverageId' => 'Electrical Accessories',
                    'number' => '',
                    'sumInsured' => (($ElectricalaccessSI != '') ? $ElectricalaccessSI : 0)
                ];                
            }          
        }
        if (in_array($requestData->fuel_type, ["CNG", "LPG"]) && (int) $externalCNGKITSI == 0) {
            $vehicle_coverage[] = [
                'coverageId' => 'CNG Kit Company Fit',
                'number' => '',
                'sumInsured' => "Y",
            ];
        } elseif ((int) $externalCNGKITSI > 0) {
            $vehicle_coverage[] = [
                'coverageId' => 'CNG Kit',
                'number' => '',
                'sumInsured' => $externalCNGKITSI,
            ];
        }
        
        
        $inceptionDate = date('m/d/Y 00:00:00', strtotime($policy_start_date));
        $expiryDate = date('m/d/Y 23:59:59', strtotime($policy_end_date));
 /*
        "MAKE_CODE" => "A1A0303"
        "MANUFACTURER" => "ASHOK LEYLAND"
        "MODEL" => "DOST"
        "VARIANT" => "LE - TIPPER CLOSED BODY POWER PACK OPERATED"
        "CC" => "1"
        "SEATING_CAPACITY" => "3"
        "FUEL_TYPE" => "Diesel"
        "EXSHOWROOM_PRICE" => "689500"
        "SUB_CLASS" => "A1"
        "CLASS" => "A"
        "MODEL_CODE" => "C.1AC"
        "MAKE_CODE_NAME" => "ICV CLASS C.1A CC1500 CARRY 5"
        "SUB_CLASS_NAME" => "FOUR WHEELED VEHICLES WITH CARRYING CAPACITY NOT EXCEEDING 6 PASSENGERS"
        "ic_version_code" => "HCY3L"
        "no_of_wheels" => "0"
       */ 
        if($requestData->business_type == 'newbusiness' || in_array($requestData->previous_policy_type, ['Not sure']))
        {
            $previousPolicyEndDate = '';
        }
        else
        {
            $previousPolicyEndDate = date('m/d/Y', strtotime($requestData->previous_policy_expiry_date));
        }

        $service_request = [
            "soapenv:Header" => [],
            'soapenv:Body' => [
                'getMotorPremium' => [
                    '_attributes' => [
                        "xmlns" => "http://premiumwrapper.motor.itgi.com",
                    ],
                    'policy' => [
                        'contractType' => 'CVI',
                        'inceptionDate' => $inceptionDate,
                        'expiryDate' => $expiryDate,
                        'previousPolicyEndDate' => $previousPolicyEndDate,//date('m/d/Y', strtotime($requestData->previous_policy_expiry_date)),
                        'vehicle' => [
                            'capacity' => $mmv_data['cc'] ?? null,
                            'engineCpacity' =>$mmv_data['cc'] ?? null,
                            'make' => $mmv_data['make_code'],
                            'registrationDate' => date('m/d/Y', strtotime($requestData->vehicle_register_date)),
                            'seatingCapacity' => $mmv_data['seating_capacity'] ?? NULL,
                            'regictrationCity' => $rto_data->rto_city_code,
                            'yearOfManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'zcover' => $tp_only ? 'AC' : 'CO',
                            'type' => [],
                            'itgiRiskOccupationCode' => [],
                            'grossVehicleWeight' =>  $mmv_data['gvw'] ?? null,
                            'validDrivingLicence' => 'Y',
                            'nofOfCarTrailers' => 0,
                            'noOfLuggageTrailers' => 0,
                            'luggageAverageIDV' => 0,
                            'vehicleCoverage' => ['item' => $vehicle_coverage],
                        ],

                    ],
                    'partner' => [
                        'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
                        'partnerBranch' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_BRANCH'),
                        'partnerSubBranch' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_SUB_BRANCH'),
                    ],
                ],
            ],
        ];

        $additional_data['method'] = ($tp_only ? 'TP - ' : '') . 'Premium Calculation';
        $additional_data['transaction_type'] = 'quote';

        $input_array = ArrayToXml::convert($service_request, $root, false, 'utf-8');

        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL'), $input_array, 'iffco_tokio', $additional_data);
        $service_response = $get_response['response'];
        if (empty($service_response)) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $service_response = XmlToArray::convert($service_response);
        if (!isset($service_response['soapenv:Body'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        if(!empty($service_response['soapenv:Body']['soapenv:Fault']['faultstring'] ?? '')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $service_response['soapenv:Body']['soapenv:Fault']['faultstring'],
            ];
        }
        $anti_theft = $aai_discount = $pa_unnamed = $pa_owner_driver = $legalLiability_to_driver = $cngOdPremium = $cngTpPremium = $ncb_amount = $elecAccSumInsured = $nonelecAccSumInsured = $voluntary_deductible_od_premium = $voluntary_deductible_tp_premium = $tppd_discount = $dep_value = $towing_related_cover = $consumable =$imt_23 = 0;
        $isPACoverPaidDriverAmount = 0;   
        $response_body = $service_response['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'];

        $response_body1 = $response_body[0];
        $response_body2 = $response_body[1];
        $response_body = $is_zero_dep_product && !$tp_only ? $response_body[1] : $response_body[0];

        if (isset($response_body['error']) && !empty($response_body['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body['error']['errorMessage'],
            ];
        }
        if (isset($response_body1['error']) && !empty($response_body1['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body1['error']['errorMessage'],
            ];
        }
        if (isset($response_body2['error']) && !empty($response_body2['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body2['error']['errorMessage'],
            ];
        }
        if (is_array($response_body) && count($response_body) < 2) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Error while fetching quote.',
            ];
        }
        
        $coveragepremiumdetail = $response_body['coveragePremiumDetail'];
        foreach ($coveragepremiumdetail as $k => $v){    
            $coverage_name = $v['coverageName'];
            if (is_array($v['odPremium'])) {
                $v['odPremium'] = (!empty($v['odPremium']['@value']) ? $v['odPremium']['@value'] : '0');
            }
            if (is_array($v['tpPremium'])) {
                $v['tpPremium'] = (!empty($v['tpPremium']['@value']) ? $v['tpPremium']['@value'] : '0');
            }
            if ($coverage_name == 'IDV Basic') {
                $od_premium = ($v['odPremium']);
                $od_amt = ($v['odPremium']);
                $tp_premium = ($v['tpPremium']);
            } else if ($coverage_name == 'No Claim Bonus') {
                $ncb_amount = (abs($v['odPremium']));
            } else if ($coverage_name == 'PA Owner / Driver') {
                $pa_owner_driver = (abs($v['tpPremium']));
            } else if ($coverage_name == 'PA to Passenger') {
                $pa_unnamed = (abs($v['tpPremium']));
            } else if ($coverage_name == 'Legal Liability to Driver') {
                $legalLiability_to_driver = (abs($v['tpPremium']));
            } else if ($coverage_name == 'LL Paid Driv/Cleaner/Conductor') {
                $legalLiability_to_driver = (abs($v['tpPremium']));
            } else if ($coverage_name == 'Electrical Accessories') {
                $elecAccSumInsured = ($v['odPremium']);
            } else if ($coverage_name == 'Cost of Accessories') { //non electrical accessories
                $nonelecAccSumInsured = ($v['odPremium']);
            } else if (in_array($coverage_name, ['CNG Kit', 'CNG Kit Company Fit'])){
                $cngOdPremium += ($v['odPremium']);
                $cngTpPremium += ($v['tpPremium']);
            } else if ($coverage_name == 'AAI Discount') {
                $aai_discount = ($v['odPremium']);
            } else if ($coverage_name == 'Anti-Theft') {
                $anti_theft = ($v['odPremium']);
            } 
            else if ($coverage_name == 'Depreciation Waiver') {
                $dep_value = ($v['coveragePremium']);
            } else if ($coverage_name == 'Consumable') {
                $consumable = ($v['coveragePremium']);
            } else if ($coverage_name == 'Towing & Related') {
                $towing_related_cover = ($v['coveragePremium']);
                
            } else if ($coverage_name == 'Voluntary Excess') {
                $voluntary_deductible_od_premium = ($v['odPremium']);
                $voluntary_deductible_tp_premium = ($v['tpPremium']);
            } else if ($coverage_name == 'TPPD') { //non electrical accessories
                $tppd_discount = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);  
            } else if ($coverage_name == 'IMT 23') {   
                $imt_23 = ($v['odPremium']);
            }
            else if ($coverage_name == 'PA paid driver/conductor/cleaner') { //PA paid driver/conductor/cleaner     
                $isPACoverPaidDriverAmount = ($v['odPremium']);
            }
        }
        // IMT - 23 Calculation - Start
       
        /* if (!$tp_only) {
        $imt_23 = round(($od_amt + $ncb_amount) * 0.15);
        } */
        // IMT - 23 Calculation - End
        $total_premium_after_discount = $response_body['totalPremimAfterDiscLoad'];
        $other_discount = (abs($response_body['discountLoadingAmt']));
        $service_tax = $response_body['serviceTax'];
        $base_premium_amount = $total_amount_payable = $response_body['premiumPayable'];
        //$od_premium = $od_premium + $other_discount;
        if ($is_zero_dep_product) {
            if($is_imt_23)
            {
                $add_ons_data = [
                    'in_built' => [
                        'zero_depreciation' => round($dep_value),
                        'imt23'             => round($imt_23),
                    ],
                    'additional' => [
                        'road_side_assistance'=>round($towing_related_cover)
                    ],
                    'other' => [],
                ];
                array_push($applicable_addons, "roadSideAssistance");
            }
            else
            {
                $add_ons_data = [
                    'in_built' => [
                        'zero_depreciation'     => round($dep_value),
                    ],
                    'additional' => [
                        'road_side_assistance'  => round($towing_related_cover)
                    ],
                    'other' => [],
                ];
                array_push($applicable_addons, "roadSideAssistance");
            }

        }else{
            $add_ons_data = [
                'in_built' => [],
                'additional' => [],
                'other' => [],
            ];
        }
        if($car_age <= 5)  //Upto 5 Years
        {
            array_push($applicable_addons, "zeroDepreciation");
            array_push($applicable_addons, "consumables");
            if($is_imt_23)
            {
                array_push($applicable_addons, "imt23");
            }
        }
        $add_ons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
        $add_ons_data['additional_premium'] = array_sum($add_ons_data['additional']);
        $add_ons_data['other_premium'] = array_sum($add_ons_data['other']);
        $add_ons_data['additional_premium']=array_sum($add_ons_data['additional']);

        $voluntary_deductible = 0;
        $voluntary_deductible_od_premium == '0' ? $voluntary_deductible_od_premium = '0' : $voluntary_deductible = $voluntary_deductible_od_premium;

        $voluntary_deductible_tp_premium == '0' ? $voluntary_deductible_tp_premium = '0' : $voluntary_deductible = $voluntary_deductible_tp_premium;

        $total_od_premium = round($od_premium) + round($nonelecAccSumInsured) + round($elecAccSumInsured) + round($cngOdPremium);
        //$tp_premium                 = $tp_premium  - abs($tppd_discount);
        $total_tp_premium = $tp_premium + $legalLiability_to_driver + $pa_unnamed + $cngTpPremium;

        $total_discount_premium = round($ncb_amount) + round(abs($aai_discount)) + abs($anti_theft) + round(abs($voluntary_deductible)) + round(abs($other_discount)) + round(abs($tppd_discount));

        $total_base_premium = round($total_od_premium) + $total_tp_premium - $total_discount_premium;
         
        $LLConductorPrermium = $LLCleanerPremium = 0;
        $data_response = [
            'status' => true,
            'msg' => 'Found',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'Data' => [
                'idv' => $idv,
                'min_idv' => $min_idv,
                'max_idv' => $max_idv,
                'vehicle_idv' => $min_idv,
                'qdata' => null,
                'pp_enddate' => $requestData->previous_policy_expiry_date,
                'addonCover' => null,
                'addon_cover_data_get' => '',
                'rto_decline' => null,
                'rto_decline_number' => null,
                'mmv_decline' => null,
                'mmv_decline_name' => null,
                'policy_type' => $tp_only ? "Third Party" : (($premium_type == 'own_damage') ? "Own Damage" : "Comprehensive"),
                'business_type' => $businesstype,
                'cover_type' => '1YC',
                'hypothecation' => '',
                'hypothecation_name' => '',
                'vehicle_registration_no' => $requestData->rto_code,
                'rto_no' => $rto_code,

                'version_id' => $requestData->version_id,
                'selected_addon' => [],
                'showroom_price' => 0,
                'fuel_type' => $requestData->fuel_type,
                'ncb_discount' => $requestData->applicable_ncb,
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                'product_name' => $productData->product_sub_type_name,
                'mmv_detail' => $mmv_data,
                'master_policy_id' => [
                    'policy_id' => $productData->policy_id,
                    'policy_no' => $productData->policy_no,
                    'policy_start_date' => $policy_start_date,
                    'policy_end_date' => $policy_end_date,
                    'sum_insured' => $productData->sum_insured,
                    'corp_client_id' => $productData->corp_client_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'insurance_company_id' => $productData->company_id,
                    'status' => $productData->status,
                    'corp_name' => '',
                    'company_name' => $productData->company_name,
                    'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                    'product_sub_type_name' => $productData->product_sub_type_name,
                    'flat_discount' => $productData->default_discount,
                    'predefine_series' => '',
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                ],
                'motor_manf_date' => $requestData->vehicle_register_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => [
                    'master_policy_id' => $productData->policy_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'segment_id' => '0',
                    'rto_cluster_id' => '0',
                    'car_age' => $car_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' => round(abs($other_discount)),
                ],
                'ic_vehicle_discount' => round(abs($other_discount)),
                'basic_premium' => round($od_premium),
                'motor_electric_accessories_value' => $elecAccSumInsured,
                'motor_non_electric_accessories_value' => $nonelecAccSumInsured,
                'motor_lpg_cng_kit_value' => $cngOdPremium,
                'total_accessories_amount(net_od_premium)' => 0,
                'total_own_damage' => round($total_od_premium),
                'tppd_premium_amount' => round($tp_premium),
                'compulsory_pa_own_driver' => $pa_owner_driver, // Not added in Total TP Premium
                'cover_unnamed_passenger_value' => round($pa_unnamed), //$pa_unnamed,
                'default_paid_driver' => $legalLiability_to_driver,
                'll_paid_driver_premium' => $legalLiability_to_driver,
                'll_paid_conductor_premium' => 0,
                'll_paid_cleaner_premium' => 0,
                'motor_additional_paid_driver' => 0,
                // 'cng_lpg_tp' => $cngTpPremium,
                'seating_capacity' => $mmv_data['seating_capacity'],
                'deduction_of_ncb' => round(abs($ncb_amount)),  
                'antitheft_discount' => abs($anti_theft),
                'aai_discount' => round(abs($aai_discount)), //$automobile_association,
                'voluntary_excess' => round(abs($voluntary_deductible)), //$voluntary_excess,
                'other_discount' => round(abs($other_discount)),
                'total_liability_premium' => round($tp_premium),
                'tppd_discount' => round(abs($tppd_discount)),
                'net_premium' => round($base_premium_amount),
                'service_tax_amount' => round($service_tax),
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => round($total_amount_payable),

                'service_data_responseerr_msg' => 'success',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'service_err_code' => null,
                'service_err_msg' => null,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                'ic_of' => $productData->company_id,
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'get_policy_expiry_date' => null,
                'get_changed_discount_quoteid' => 0,
                'GeogExtension_ODPremium' => 0,
                'GeogExtension_TPPremium' => 0,
                'LimitedtoOwnPremises_OD' => 0,
                'LimitedtoOwnPremises_TP' => 0,
                'vehicle_discount_detail' => [
                    'discount_id' => null,
                    'discount_rate' => null,
                ],
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
                'policy_id' => $productData->policy_id,
                'insurane_company_id' => $productData->company_id,
                'max_addons_selection' => null,
                'add_ons_data' => $add_ons_data,
                'applicable_addons' => $applicable_addons,
                'final_od_premium' => round($total_od_premium),
                'final_tp_premium' => round($total_tp_premium),
                'final_total_discount' => round(abs($total_discount_premium)),
                'final_net_premium' => round($total_base_premium),
                'final_gst_amount' => round($service_tax),
                'final_payable_amount' => round($total_amount_payable),
                'mmv_detail' => [
                    'manf_name' => $mmv_data['manufacturer'],
                    'model_name' => $mmv_data['model'],
                    'version_name' => $mmv_data['variant'],
                    'fuel_type' => $mmv_data['fuel_type'],
                    'seating_capacity' => $mmv_data['seating_capacity'],
                    'carrying_capacity' => $mmv_data['seating_capacity'],
                    'cubic_capacity' => $mmv_data['cc'],
                    'gross_vehicle_weight' => $mmv_data['gvw']?? null,
                    'vehicle_type' => 'Taxi',
                ],
            ],
            'request' => $service_request
        ];
        if(!empty($cngTpPremium)){
            $data_response['Data']['cng_lpg_tp'] =$cngTpPremium;
        }

        if($is_GCV)
        {
            unset($data_response['Data']['ll_paid_driver_premium']);
            unset($data_response['Data']['ll_paid_conductor_premium']);
            unset($data_response['Data']['ll_paid_cleaner_premium']); 
        }
        return camelCase($data_response);

    } else {
        return camelCase(
            [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ]
        );
    }
}