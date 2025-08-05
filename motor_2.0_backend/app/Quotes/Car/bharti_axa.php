<?php

use App\Models\IcVersionMapping;
use App\Models\MasterPremiumType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Support\Str;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    // dd($enquiryId, $requestData, $productData);

    $car_age = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);

    // $mmv_data = IcVersionMapping::leftjoin('bharti_axa_model_master', function ($join) {
    //     $join->on('bharti_axa_model_master.vehicle_model_code', '=', 'ic_version_mapping.ic_version_code');
    // })
    //     ->where([
    //         'ic_version_mapping.fyn_version_id' => $requestData->version_id,
    //         'ic_version_mapping.ic_id' => $productData->company_id
    //     ])
    //     ->select('ic_version_mapping.*', 'bharti_axa_model_master.*')
    //     ->first();

    $mmv = get_mmv_details($productData,$requestData->version_id,'bharti_axa');

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
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

    // dd($requestData->version_id, $productData->company_id, $mmv_data);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv_data
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv_data
            ]
        ];
    }

    $statecode = explode('-', $requestData->rto_code);

    $idv_request = [
        '@attributes' => ['xmlns' => 'http://schemas.cordys.com/default'],
        'UserName' => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_USERNAME'),
        'Password' => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_PASSWORD'),
        'Make' => $mmv_data->manufacture,
        'Model' => $mmv_data->model,
        'Producttype' => 'FPV',
        'Variant' => $mmv_data->variant,
        'StateCode' => Arr::first($statecode),
        'Manfaturingyear' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
    ];

    $additionalData = [
        'root_tag'          => 'getIDV',
        'container'         => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body>#replace</Body></Envelope>',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'IDV Calculation',
        'requestMethod'     => 'post',
        'enquiryId'         => $enquiryId,
        'productName'       => $productData->product_sub_type_name,
        'transaction_type'  => 'quote'
    ];

    $data = getWsData(config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_END_POINT_URL'), $idv_request, 'bharti_axa', $additionalData);

    if ($data) {
        $idv_response = XmlToArray::convert($data['response']);

        $filtered_idv = array_search_key('getIDVResponse', $idv_response);

        if (!isset($filtered_idv)) {
            $filtered_idv = array_search_key('getIDV', $idv_response);
        }

        if (isset($filtered_idv['IDV']) && $filtered_idv['IDV'] > 0) {
            $idv = $filtered_idv['IDV'];
            $min_idv = $filtered_idv['IDVMinRange'];
            $max_idv = $filtered_idv['IDVMaxRange'];
            $ex_showroom_price = $filtered_idv['Ex_Showromm_Price'];

            // set minimum idv for quote
            $idv = $min_idv ;

            // idv change condition
            if ($requestData->is_idv_changed == 'Y') {
                if ($max_idv != "" && $requestData->edit_idv >= ($max_idv)) {
                    $idv = ($max_idv);
                } elseif ($min_idv != "" && $requestData->edit_idv <= ($min_idv)) {
                    $idv = ($min_idv);
                } else {
                    $idv = $requestData->edit_idv;
                }
            }



            // Quote | Premium Calculation

            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();

            $today_date = date('Y-m-d');
            if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
            } else {
                $policy_start_date = date('d/m/Y', strtotime("+1 day"));
            }

            $policystartdatetime = DateTime::createFromFormat('d/m/Y', $policy_start_date);
            $policy_start_date = $policystartdatetime->format('Y-m-d');

            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));

            $policyenddatetime = DateTime::createFromFormat('d/m/Y', $policy_end_date);
            $policy_end_date = $policyenddatetime->format('Y-m-d');

            date_default_timezone_set('GMT');
            $InitTime = date('D, d M Y H:i:s e', time());

            if ($requestData->fuel_type == 'PETROL') {
                $fuel_type = 'P';
            } elseif ($requestData->fuel_type == 'DIESEL') {
                $fuel_type = 'D';
            }

            $rto_details = DB::table('bharti_axa_rto_location')
                ->where('rta_code', str_replace('-', '', $requestData->rto_code))
                ->first();

            $quote_req_array = [
                'SessionData' => [
                    '@attributes' => [
                        'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/bpm/1.0',
                    ],
                    'Index' => '1',
                    'InitTime' =>  $InitTime,
                    'UserName' => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_USERNAME'),
                    'Password' => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_PASSWORD'),
                    'OrderNo' => 'NA',
                    'QuoteNo' => 'NA',
                    'Route' => 'INT',
                    'Contract' => 'MTR',
                    'Channel' => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_CHANNEL'),
                    'TransactionType' => 'Quote',
                    'TransactionStatus' => 'Fresh',
                    'ID' => '',
                    'UserAgentID' => '',
                    'Source' => '',
                ],
                'Vehicle' => [
                    '@attributes' => [
                        'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/2.0',
                    ],
                    'TypeOfBusiness' => 'TR',
                    'NonElecAccessoryInsured' => 'N',
                    'NonElecAccessoryValue' => '0',
                    'AccessoryInsured' => 'N',
                    'AccessoryValue' => '0',
                    'ARAIMember' => 'N',
                    'AntiTheftDevice' => 'N',
                    'PAOwnerDriverTenure' => '',
                    'RateType' => '',
                    'BiFuelKit' => [
                        'IsBiFuelKit' => 'N',
                        'ExternallyFitted' => 'N',
                        'BiFuelKitValue' => '0',
                    ],
                    'DateOfRegistration' => date('Y-m-d', strtotime($requestData->vehicle_register_date)) . 'T00:00:00.000',
                    'DateOfManufacture' => date('Y-m-d', strtotime('01-' . $requestData->manufacture_year)) . 'T00:00:00.000',
                    'RiskType' => $mmv_data->product_type,
                    'Make' => $mmv_data->manufacture,
                    'Model' => $mmv_data->model,
                    'FuelType' => $fuel_type,
                    'Variant' => $mmv_data->variant,
                    'IDV' => $idv,
                    'EngineNo' => 'DERF12015',
                    'ChasisNo' => 'DERF12234874FGRF1',
                    'VehicleAge' => $car_age,
                    'CC' => $mmv_data->cc,
                    'PlaceOfRegistration' => $rto_details->city,
                    'SeatingCapacity' => $mmv_data->seating_capacity,
                    'VehicleExtraTag01' => '',
                    'RegistrationNo' => str_replace('-', '', $requestData->vehicle_registration_no),
                    'ExShowroomPrice' => $mmv_data->ex_showroom_price,
                ],
                'Quote' => [
                    '@attributes' => [
                        'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/2.0',
                    ],
                    'ExistingPolicy' => [
                        'Claims' => 0,
                        'PolicyType' => $requestData->previous_policy_type,
                        'EndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) . 'T00:00:00.000',
                        'NCB' => $requestData->previous_ncb,
                    ],
                    'PolicyStartDate' => date('Y-m-d', strtotime($policy_start_date)) . 'T00:00:00.000',
                    'Deductible' => '0',
                    'PAFamilySI' => '0',
                    'AgentNumber' => '',
                    'DealerId' => '',
                    'InspectionRefNo' => '',
                    'BreakIn' => '',
                    'Stage' => '',
                    'Premium' => [
                        'Discount' => '',
                    ],
                    'SelectedCovers' => [
                        'CarDamageSelected' => 'True',
                        'TPLiabilitySelected' => 'True',
                        'PADriverSelected' => 'False',
                        'RoadsideAssistanceSelected' => 'True',
                        'KeyReplacementSelected' => 'True',
                        'NoClaimBonusSameSlabSelected' => 'True',
                        'EngineGearBoxProtectionSelected' => 'True',
                        'CosumableCoverSelected' => 'True',
                        'InvoicePriceSelected' => 'True',
                        'PAFamilyPremiumSelected' => 'False',
                        'ZeroDepriciationSelected' => 'False',
                    ],
                    'PolicyEndDate' => date('Y-m-d', strtotime($policy_end_date)) . 'T00:00:00.000',
                    'IsExistingPA' => 'False',
                    'PADeclaration' => 'No',
                ],
                'Client' => [
                    '@attributes' => [
                        'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/2.0',
                    ],
                    'ClientType' => 'Individual',
                    'CltDOB' => (date('Y', strtotime('-18 year'))) . '0101',
                    'FinancierDetails' => [
                        'IsFinanced' => '0',
                    ],
                    'GivName' => 'ABC' . Str::random(10),
                    'SurName' => 'ABCD',
                    'ClientExtraTag01' => $rto_details->state,
                    'CityOfResidence' =>  $rto_details->city,
                    'EmailID' => 'abc@def.com',
                    'MobileNo' => '9999999999',
                    'RegistrationZone' =>  $rto_details->zone,
                ],
                'Payment' => [
                    'PaymentMode' => '',
                    'PaymentType' => '',
                    'TxnReferenceNo' => '',
                    'TxnAmount' => '',
                    'TxnDate' => '',
                    'BankCode' => '',
                    'InstrumentAmount' => '',
                ],
            ];

            $additionalData = [
                'root_tag'          => 'Session',
                'container'         => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><serve xmlns="http://schemas.cordys.com/gateway/Provider"><SessionDoc> #replace </SessionDoc></serve></Body></Envelope>',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Premium Calculation',
                'requestMethod'     => 'post',
                'enquiryId'         => $enquiryId,
                'productName'       => $productData->product_sub_type_name,
                'transaction_type'  => 'quote'
            ];

            $data = getWsData(config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_END_POINT_URL'), $quote_req_array, 'bharti_axa', $additionalData);

            if ($data['response']) {
                $quote_resp_array = XmlToArray::convert($data['response']);
                $filter_response = array_search_key('response', $quote_resp_array);
                if (isset($filter_response['StatusCode']) && $filter_response['StatusCode'] == '200') {
                    $covers = $filter_response['PremiumSet']['Cover'];
                    $cover_amount = '';
                    $OD_amount = 0;
                    $NCB = 0;
                    $electrical = 0;
                    $bifuel = 0;
                    $TP = 0;
                    $LL = 0;
                    $TPbifuel = 0;
                    $paowner_amount = 0;
                    $pafamily = 0;
                    $ncb_prot = '';
                    $key_repl = '';
                    $rsa = '';
                    $eng_grbx_prot = '';
                    $consumable = '';
                    $return_to_invoice = '';
                    $other_discount = 0;
                    $tppd = 0;
                    $voluntary_deductible = 0;
                    $PAFamilyPremiumSelected = 'false';
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;

                    foreach ($covers as $key => $cover) {
                        if (($productData->zero_dep === '0') && ($cover['Name'] === 'DEPC')) {
                            $cover_amount = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'CarDamage') {
                            $OD_amount = ($cover['ExtraDetails']['BreakUp']['BasicOD']);
                            $NCB = $cover['ExtraDetails']['BreakUp']['NCB'];
                            $electrical = $cover['ExtraDetails']['BreakUp']['Accessory'];
                            $bifuel = $cover['ExtraDetails']['BreakUp']['BiFuel'];
                            $NonElecAccessory = $cover['ExtraDetails']['BreakUp']['NonElecAccessory'];
                            $AntiTheft = $cover['ExtraDetails']['BreakUp']['AntiTheft'];
                            $voluntary_deductible = $cover['ExtraDetails']['BreakUp']['ODDeductible'];
                        } elseif ($cover['Name'] === 'ThirdPartyLiability') {
                            $TP = $cover['ExtraDetails']['BreakUp']['TP'];
                            $LL = $cover['ExtraDetails']['BreakUp']['LLDriver'];
                            $TPbifuel = $cover['ExtraDetails']['BreakUp']['TPBiFuel'];
                            $tppd += $TP + $LL + $TPbifuel + $cover['ExtraDetails']['BreakUp']['TPPD'];
                        } elseif ($cover['Name'] === 'PAOwnerDriver') {
                            $paowner_amount = ($cover['Premium']);
                            $tppd += $paowner_amount;
                        } elseif (($PAFamilyPremiumSelected == 'True') && ($cover['Name'] === 'PAFamily')) {
                            $pafamily = ($cover['Premium']);
                            $tppd += $pafamily;
                        } elseif ($cover['Name'] === 'KEYC') {
                            $key_repl = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'NCBS') {
                            $ncb_prot = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'RSAP') {
                            $rsa = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'EGBP') {
                            $eng_grbx_prot = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'CONC') {
                            $consumable = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'INPC') {
                            $return_to_invoice = ($cover['Premium']);
                        }
                    }

                    $totalODPremium = $OD_amount + $bifuel + $electrical + $NonElecAccessory;

                    $other_discount =  (($OD_amount * abs($filter_response['PremiumSet']['Discount'])) / 100);

                    $totalDiscount = $other_discount + $AntiTheft + $NCB + $voluntary_deductible;
                    $totalTPPremium = $TP + $LL  + $pafamily + $TPbifuel;
                    $addons = 0;

                    $totalBasePremium = $totalODPremium + $totalTPPremium + $addons - $totalDiscount;

                    $final_premium = $totalBasePremium * 0.18;

                    $data_response = [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
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
                            'policy_type' => $premium_type == 'third_party' ? 'Third Party' : 'Comprehensive',
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
                            'product_name' => $productData->product_sub_type_name,
                            'mmv_detail' => [
                                'manf_name'             => $mmv_data->manufacture,
                                'model_name'            => $mmv_data->model,
                                'version_name'          => $mmv_data->variant,
                                'fuel_type'             => $mmv_data->fuel == 'P' ? 'Petrol' : 'Diesel',
                                'seating_capacity'      => $mmv_data->seating_capacity,
                                'cubic_capacity'        => $mmv_data->cc
                            ],
                            'master_policy_id' => [
                                'policy_id' => $productData->policy_id,
                                'policy_no' => $productData->policy_no,
                                'policy_start_date' => '',
                                'policy_end_date' =>   '',
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
                                'ic_vehicle_discount' =>  $other_discount,
                            ],
                            'basic_premium' => $OD_amount,
                            'deduction_of_ncb' => $NCB,
                            'tppd_premium_amount' => $TP,
                            'motor_electric_accessories_value' =>$electrical,
                            'motor_non_electric_accessories_value' => $NonElecAccessory,
                            'motor_lpg_cng_kit_value' => 0,
                            'cover_unnamed_passenger_value' => 0,
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'default_paid_driver' => 0,
                            'motor_additional_paid_driver' => 0,
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                            'compulsory_pa_own_driver' => $paowner_amount,
                            'total_accessories_amount(net_od_premium)' => 0,
                            'total_own_damage' =>  $totalODPremium,
                            'cng_lpg_tp' => $TPbifuel,
                            'total_liability_premium' => $totalTPPremium,
                            'net_premium' => $totalBasePremium,
                            'service_tax_amount' => 0,
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'voluntary_excess' => 0,
                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                            'quotation_no' => '',
                            'premium_amount' => ($final_premium),
                            'antitheft_discount' => '',
                            'final_od_premium' => $totalODPremium,
                            'final_tp_premium' => $totalTPPremium,
                            'final_total_discount' => $totalDiscount,
                            'final_net_premium' => ($final_premium),
                            'final_payable_amount' => ($final_premium),
                            'service_data_responseerr_msg' => 'true',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => $requestData->policy_type,
                            'service_err_code' => NULL,
                            'service_err_msg' => NULL,
                            'policyStartDate' =>$policystartdatetime->format('d-m-Y'),
                            'policyEndDate' => $policy_end_date,
                            'ic_of' => $productData->company_id,
                            'ic_vehicle_discount' => $other_discount,
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
                            'add_ons_data' =>   [
                                'in_built'   => [],
                                'additional' => [
                                    'zero_depreciation' => '',
                                    'road_side_assistance' => (int)$rsa,
                                    'imt23' => ''
                                ]
                            ]
                        ]
                    ];

                    return camelCase($data_response);

                }
            }

        }
    }
}
