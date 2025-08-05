<?php

namespace App\Http\Controllers\Proposal\Services;
include_once app_path().'/Helpers/CvWebServiceHelper.php';

use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use DateTime;
use function Composer\Autoload\includeFile;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use App\Http\Controllers\LiveCheck\LivechekBreakinController;
use App\Models\CvBreakinStatus;
use App\Http\Controllers\Proposal\Services\IffcoTokioshortTermSubmitProposal as ITSHORTTERM;
use App\Http\Controllers\SyncPremiumDetail\Services\IffcoTokioPremiumDetailController;

// includeFile(app_path('Helpers/CvWebServiceHelper.php')); //Beacuse of some issues thisn't working on some brokers
class iffco_tokioSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        if(config("IFFCO_PCV_GCV_ONLY_UAT") == 'Y')
        {
            return self::PcvGcvsubmit($proposal, $request);
        }

        $enquiryId = customDecrypt($request['enquiryId']);

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        if(in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']))
        {
            return ITSHORTTERM::submit($proposal, $request);
        }

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
            ];
        }

        $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);

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

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
        // zero depriciation validation
        $is_zero_dep_product = false;
        if ($productData->zero_dep == '0') {
            $is_zero_dep_product = true;
        }

        $is_pa_cover_owner_driver = 'N';
        $validDrivingLicence = 'Y';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                    $is_pa_cover_owner_driver = 'Y';
                } elseif (isset($data['reason']) && $data['reason'] != "") {
                    if ($data['reason'] == 'I do not have a valid driving license.') {
                        $validDrivingLicence = 'N';
                    }
                }
            }
        }

        if ($proposal->owner_type == 'I') {
            if ($requestData->business_type == 'newbusiness') {
                $txt_cust_type = $proposal->owner_type;
                $Date_of_Birth = date('m/d/Y', strtotime($proposal->dob));
            } else {
                $txt_cust_type = $proposal->owner_type;
                $Date_of_Birth = date('m/d/Y', strtotime($proposal->dob));
            }
        } else {
            $txt_cust_type = 'O';
            $emergency_assistance_cover = '0';
            $Date_of_Birth = '01/01/1970';
            $Gender = '';

        }
        $is_breakin = false;
        if ($requestData->business_type == 'newbusiness') {
            $typeofbusiness = 'New Business';
            $product_ncb_discount_rate = '0';
        } else {
            $product_ncb_discount_rate = $requestData->applicable_ncb;
            $typeofbusiness = 'Rollover';
            if ($requestData->business_type == 'breakin') {
                $is_breakin = true;
            }
        }
        $policyDates = self::getPolicyDates($requestData);
        if ($is_pa_cover_owner_driver == 'Y') {
            $proposal->is_cpa = 'Y';
            $proposal->cpa_policy_fm_dt = $policyDates->policyStartDate;
            $proposal->cpa_policy_to_dt = $policyDates->policyEndDate;
        }
        $proposal->tp_start_date = $policyDates->policyStartDate;
        $proposal->tp_end_date = $policyDates->policyEndDate;
        if (strlen($proposal->chassis_number) > 20) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Chassis No. length can not be greater than 20 characters',
            ];
        }
        $first_reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));
        $purchaseregndate = date('m/d/Y', strtotime($first_reg_date));
        $regDate = date("m/d/Y 00:00:00", strtotime($first_reg_date));

        $year = explode('-', $requestData->manufacture_year);
        $motor_electric_accessories = '0';
        $motor_non_electric_accessories = '0';
        $motor_lpg_cng_kit = '0';

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($typeofbusiness == 'New Business' || (strtolower($requestData->previous_policy_expiry_date) == 'new') ? '' : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $include_consumable = false;
        if ($vehicle_age < 7) { // Less than 7 i.e. upto 6 and including 6
            foreach ($addons as $key => $name) {
                if (in_array('Zero Depreciation', $name) && $name['premium'] > 0) {
                    $is_zero_dep_product = true;
                }
                if (in_array('Consumable', $name) && $name['premium'] > 0) {
                    $include_consumable = true;
                    $is_zero_dep_product = true;
                }
            }
        }
        $voluntary_deductible_amount = '0';
        $pa_unnamed = 0;
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $pa_unnamed = $data['sumInsured'];
                }
                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $IsLegalLiabilityDriver = 'Y';
                }
            }
        }
        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
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
        if ($tp_only) {
            $product_ncb_discount_rate = 0;
        }
        $vehicle_coverages['IDV'] = [
            'coverageId' => 'IDV Basic',
            'number' => $tp_only ? 1 : $proposal->idv,
            'sumInsured' => $tp_only ? 1 : $proposal->idv,
        ];
        $vehicle_coverages['CPA'] = [
            'coverageId' => 'PA Owner / Driver',
            'number' => '',
            'sumInsured' => (($proposal->owner_type == 'I') ? $is_pa_cover_owner_driver : 'N'),
        ];
        /* $vehicle_coverages[] = [
        'coverageId' => 'Electrical Accessories',
        'number' => '',
        'sumInsured' => (($motor_electric_accessories != '') ? $motor_electric_accessories : 0),
        ];
        $vehicle_coverages[] = [
        'coverageId' => 'Cost of Accessories',
        'number' => '',
        'sumInsured' => (($motor_non_electric_accessories != '') ? $motor_non_electric_accessories : 0),
        ]; */
        if (in_array($requestData->fuel_type,["CNG", "LPG"]) && (int) $motor_lpg_cng_kit == 0) {
            $vehicle_coverages['CNG Kit'] = [
                'coverageId' => 'CNG Kit Company Fit',
                'number' => '',
                'sumInsured' => "Y",
            ];
        } elseif ((int) $motor_lpg_cng_kit > 0) {
            $vehicle_coverages['CNG KIT'] = [
                'coverageId' => 'CNG Kit',
                'number' => '',
                'sumInsured' => $motor_lpg_cng_kit,
            ];
        }
        $vehicle_coverages['NCB'] = [
            'coverageId' => 'No Claim Bonus',
            'number' => '',
            'sumInsured' => $product_ncb_discount_rate,
        ];
        if ($include_consumable) {
            $vehicle_coverages['consumable'] = [
                'coverageId' => 'Consumable',
                'number' => '',
                'sumInsured' => 'Y',
            ];
        }

        foreach ($vehicle_coverages as $k => $v) {
            if ($premium_type == 'own_damage') {
                if ($v['coverageId'] == 'PA Owner / Driver') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'TPPD') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'Legal Liability to Driver') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'PA to Passenger') {
                    unset($vehicle_coverages[$k]);
                }
            } else if ($tp_only) {
                if ($v['coverageId'] == 'Electrical Accessories') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'Cost of Accessories') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'No Claim Bonus') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'Consumable') {
                    unset($vehicle_coverages[$k]);
                }
            } else if ($requestData->business_type == 'newbusiness') {
                if ($v['coverageId'] == 'No Claim Bonus') {
                    unset($vehicle_coverages[$k]);
                }
            }
        }

        $inceptionDate = date('m/d/Y 00:00:00', strtotime($policyDates->policyStartDate));
        $expiryDate = date('m/d/Y 23:59:59', strtotime($policyDates->policyEndDate));

        $voluntary_deductible_od_premium = $voluntary_deductible_tp_premium = 0;
        $model_config_premium = [
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
                        'previousPolicyEndDate' => ($typeofbusiness == 'New Business' || (strtolower($requestData->previous_policy_expiry_date) == 'new')) ? '' : date('m/d/Y', strtotime($requestData->previous_policy_expiry_date)),
                        'vehicle' => [
                            'capacity' => $mmv_data['cc'] ?? null,
                            'engineCpacity' => $mmv_data['cc'] ?? null,
                            'make' => $mmv_data['make_code'],
                            'registrationDate' => date('m/d/Y', strtotime($requestData->vehicle_register_date)),
                            'seatingCapacity' => $mmv_data['seating_capacity'],
                            'regictrationCity' => $rto_data->rto_city_code,
                            'yearOfManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'zcover' => $tp_only ? 'AC' : 'CO',
                            'type' => [],
                            'itgiRiskOccupationCode' => [],
                            'grossVehicleWeight' => $mmv_data['grossVehicleWt'] ?? null,
                            'validDrivingLicence' => $validDrivingLicence,
                            'nofOfCarTrailers' => 0,
                            'noOfLuggageTrailers' => 0,
                            'luggageAverageIDV' => 0,
                            'vehicleCoverage' => ['item' => array_values($vehicle_coverages)],
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
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'SOAPAction' => 'http://schemas.xmlsoap.org/soap/envelope/',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'PCV',
            'method' => 'Quote Calculation - Proposal Submit',
            'transaction_type' => 'proposal',
            'productName' => $productData->product_sub_type_name,
        ];
        $root = [
            'rootElementName' => 'soapenv:Envelope',
            '_attributes' => [
                "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
                "xmlns:prem" => "http://premiumwrapper.motor.itgi.com",
            ],
        ];
        $input_array = ArrayToXml::convert($model_config_premium, $root, false, 'utf-8');
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL'), $input_array, 'iffco_tokio', $additional_data);
        $data = $get_response['response'];
        if (empty($data)) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $premium_data = XmlToArray::convert((string) $data);
        if (!isset($premium_data['soapenv:Body'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $ncb_amount = $pa_unnamed = $voluntary_excess = $elecAccSumInsured = $nonelecAccSumInsured = $aai_discount = $consumable = $towing_related_cover = $anti_theft_amt = $elecAccValue = $nonelecAccValue = $cngOdPremium = $cngTpPremium = $tppd_discount = $pa_owner_driver = $legalLiability_to_driver = $dep_value = $cng_internal_TpPremium = $cng_internal_OdPremium = $idv_basic_od = 0;

        $premium_data_ns1 = $premium_data['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'][0];
        $premium_data_ns2 = $premium_data['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'][1];

        $response_body = $is_zero_dep_product && !$tp_only ? $premium_data_ns2 : $premium_data_ns1;

        unset($premium_data_ns2, $premium_data_ns1);

        if (isset($response_body['error']) && !empty($response_body['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body['error']['errorMessage'],
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

        IffcoTokioPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

        $electric_accessories = intval($motor_electric_accessories);
        $non_electric_accessories = intval($motor_non_electric_accessories);
        $lpg_cng_kit = intval($motor_lpg_cng_kit);

        $length = 13;
        $unique_quote = mt_rand(pow(10, ($length - 1)), pow(10, $length) - 1);

        $coveragepremiumdetail = $response_body['coveragePremiumDetail'];
        foreach ($coveragepremiumdetail as $k => $v) {
            $coverage_name = $v['coverageName'];
            if ($coverage_name == 'IDV Basic') {
                $od_premium = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
                $idv_basic_od = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
                $tp_premium = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'Voluntary Excess') {
                $voluntary_excess = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'No Claim Bonus') {
                $ncb_amount = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'PA Owner / Driver') {
                $pa_owner_driver = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'PA to Passenger') {
                $pa_unnamed = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'Legal Liability to Driver') {
                $legalLiability_to_driver = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'Electrical Accessories') {
                $elecAccValue = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'Cost of Accessories') { //non electrical accessories
                $nonelecAccValue = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'CNG Kit') {
                $cngOdPremium = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
                $cngTpPremium = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'CNG Kit Company Fit') {
                $cng_internal_OdPremium = intval($v['odPremium']) == 1 ? 0 : round(is_array($v['odPremium']) ? 0 : $v['odPremium']);
                $cng_internal_TpPremium = intval($v['tpPremium']) == 1 ? 0 : round(is_array($v['tpPremium']) ? 0 : $v['tpPremium']);
            } else if ($coverage_name == 'AAI Discount') {
                $aai_discount = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'Anti-Theft') {
                $anti_theft_amt = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'Depreciation Waiver') {
                $dep_value = intval($v['coveragePremium']) == 1 ? 0 : round($v['coveragePremium']);
            } else if ($coverage_name == 'Consumable') {
                $consumable = intval($v['coveragePremium']) == 1 ? 0 : round($v['coveragePremium']);
            } else if ($coverage_name == 'Towing & Related') {
                $towing_related_cover = intval($v['coveragePremium']) == 1 ? 0 : round($v['coveragePremium']);
            } else if ($coverage_name == 'TPPD') {
                $tppd_discount = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            }
        }

        $totalodpremium = $response_body['totalODPremium'];
        $totaltppremium = $response_body['totalTPPremium'];
        $total_premium_after_discount = $response_body['totalPremimAfterDiscLoad'];
        $discount_amount = ($response_body['discountLoadingAmt']);
        $service_tax = $response_body['serviceTax'];

        $total_amount_payable = $response_body['premiumPayable'];
        $OdDiscountAmt = $response_body['discountLoadingAmt'];
        $OdDiscountLoading = $response_body['discountLoading'];
        $OdSumDisLoad = $response_body['totalPremimAfterDiscLoad'];
        $VoluntaryDeductiblevalue = $voluntary_excess;
        $IsZeroDept_Cover = 'Y';
        $IsZeroDept_RollOver = "Y";
        $towing = $towing_related_cover;
        $total_od_amount = ($od_premium + $elecAccValue + $nonelecAccValue + $cngOdPremium + abs($discount_amount));
        $total_discount_amount = abs($OdDiscountAmt) + abs($ncb_amount) + abs($aai_discount) + abs($VoluntaryDeductiblevalue) + round(abs($anti_theft_amt)) + abs($tppd_discount);
        $addon_total = round($dep_value + $towing + $consumable);
        //$total_sum_insured = round((($electric_accessories!='') ? $electric_accessories : 0) + (($non_electric_accessories !='') ? $non_electric_accessories :0) + (($lpg_cng_kit !='') ? $lpg_cng_kit : 0) + $idv);
        $od_premium_amount = $od_premium;
        $od_premium = $od_premium + abs($OdDiscountAmt);

        $total_discount = round(abs($ncb_amount)) + abs($VoluntaryDeductiblevalue) + abs($anti_theft_amt) + abs($aai_discount) + round(abs($discount_amount));

        $net_premimum = round($total_od_amount) + $totaltppremium - round(abs($total_discount)) + $addon_total;
        unset($data);
        $vehicleDetails = [
            'manufacture_name' => $mmv_data['manufacturer'],
            'model_name' => $mmv_data['model'],
            'version' => $mmv_data['variant'],
            'fuel_type' => $mmv_data['fuel_type'],
            'seating_capacity' => $mmv_data['seating_capacity'],
            'carrying_capacity' => $mmv_data['seating_capacity'] - 1,
            'cubic_capacity' => $mmv_data['cc'] ?? null,
            'gross_vehicle_weight' => $mmv_data['grossVehicleWt'] ?? null,
            'vehicle_type' => 'Taxi',
        ];

        //$total_sum_insured = (($motor_electric_accessories != '') ? $motor_electric_accessories : 0) + (($motor_lpg_cng_kit != '') ? $motor_lpg_cng_kit : '0') + (($motor_non_electric_accessories != '') ? $motor_non_electric_accessories : 0) + (($proposal->idv != '') ? $proposal->idv : '0');
        $total_sum_insured = (($motor_lpg_cng_kit != '') ? $motor_lpg_cng_kit : '0') + (($proposal->idv != '') ? $proposal->idv : '0');
        $total_ci = (($motor_lpg_cng_kit != '') ? $motor_lpg_cng_kit : '0');
        $additional_details_data = [
            "unique_quote" => $unique_quote,
            "idv_basic_od" => $idv_basic_od,
            "OdDiscountAmt" => $OdDiscountAmt,
            "pa_unnamed" => $pa_unnamed,
            "totalTpPremium" => $totaltppremium,
            "OdDiscountLoading" => $OdDiscountLoading,
            "OdSumDisLoad" => $OdSumDisLoad,
            "NCB_RenewalPolicy" => $requestData->applicable_ncb,
            "towing" => $towing,
            "total_od" => $totalodpremium,
            "cpa_premium" => $pa_owner_driver,
            "zero_dep_value" => round($dep_value),
            "VoluntaryDeductible" => $voluntary_excess,
            "VoluntaryDeductiblevalue" => $voluntary_deductible_amount,
            "tp_premium" => $tp_premium,
            "LPG_CNG_KIT_TP" => ((isset($cngTpPremium) && $cngTpPremium != '') ? $cngTpPremium : '0'),
            "LPG_CNG_Kit" => ((isset($cngOdPremium) && $cngOdPremium != '') ? $cngOdPremium : '0'),
            "cng_internal_OdPremium" => $cng_internal_OdPremium,
            "cng_internal_TpPremium" => $cng_internal_TpPremium,
            "electrical_accessories" => $elecAccValue,
            "non_electrical_accessories" => $nonelecAccValue,
            "legalLiability_to_driver" => $legalLiability_to_driver,
            "consumable" => $consumable,
            "anti_theft_amt" => $anti_theft_amt,
            "total_sum_insured" => $total_sum_insured,
            "tppd_discount" => $tppd_discount,
            "total_ci" => $total_ci,
        ];
        if ($tp_only) {
            $total_sum_insured = $total_ci + 1;
        }
        $proposal->policy_start_date = $policyDates->policyStartDate;
        $proposal->policy_end_date = $policyDates->policyEndDate;
        $proposal->proposal_no = $unique_quote;
        $proposal->unique_proposal_id = $unique_quote;
        $proposal->od_premium = round($total_od_amount);
        $proposal->tp_premium = $tp_premium;
        $proposal->totalTpPremium = $totaltppremium;
        $proposal->addon_premium = $addon_total;
        $proposal->cpa_premium = $pa_owner_driver;
        $proposal->final_premium = round($net_premimum);
        $proposal->total_premium = round($net_premimum);
        $proposal->service_tax_amount = round($service_tax);
        $proposal->final_payable_amount = round($total_amount_payable);
        $proposal->ic_vehicle_details = $vehicleDetails;
        $proposal->ncb_discount = abs($ncb_amount);
        $proposal->total_discount = $total_discount_amount;
        $proposal->electrical_accessories = $elecAccValue;
        $proposal->unique_quote = $unique_quote;
        $proposal->non_electrical_accessories = $nonelecAccValue;
        $proposal->additional_details_data = $additional_details_data;
        $data['user_product_journey_id'] = $enquiryId;
        $data['ic_id'] = $master_policy->insurance_company_id;
        $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        $data['proposal_id'] = $proposal->user_proposal_id;
        $proposal->save();
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

            if($proposal->owner_type == 'I' && ($proposal->last_name === null || $proposal->last_name == ''))
            {
                $proposal->last_name = '.';
            }

            $payload = [
                'user_name' => $proposal->first_name . ' ' . $proposal->last_name,
                'user_email' => $proposal->email,
                'reg_number' => $proposal->vehicale_registration_number,
                'veh_manuf' => $mmv_data['manufacturer'],
                'veh_model' => $mmv_data['model'],
                'mobile_name' => $proposal->mobile_number,
                'fuel_type' => $mmv_data['fuel_type'],
                'veh_variant' => $mmv_data['variant'],
                'vehicle_category' => 'car', // Should be as per Documentation
                'enquiry_id' => $enquiryId,
                'address' => implode(', ', [$proposal->address_line1, $proposal->address_line2, $proposal->address_line3, $proposal->state]),
                'city' => $proposal->city,
                'model_year' => $requestData->manufacture_year,
                'section' => 'cv',
                'ic_name' => 'iffco_tokio'
            ];
            
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
                ];
            }
        } else {
            $is_breakin = '';
            $inspection_no = '';
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        }
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
                'finalPayableAmount' => $proposal->final_payable_amount,
                'is_breakin' => $is_breakin,
                'inspection_number' => $inspection_no,
            ],
        ]);

    }

    public static function getPolicyDates($requestData)
    {
        if ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y 00:00:00');
            $policy_end_date = date('d-m-Y 23:59:59', strtotime($policy_start_date . ' +1 Year -1 day'));
        } else {
            if ($requestData->business_type == 'breakin') {
                $policy_start_date = date('d-m-Y 00:00:00', strtotime('+3 day'));
                $policy_end_date = date('d-m-Y 23:59:59', strtotime($policy_start_date . ' +1 Year -1 day'));
            } else {
                $policy_start_date = date('d-m-Y 00:00:00', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
                $policy_end_date = date('d-m-Y 23:59:59', strtotime($policy_start_date . ' +1 Year -1 day'));
            }
        }

        return (object)[
            'policyStartDate' => $policy_start_date,
            'policyEndDate' => $policy_end_date,
        ];
    }
    
    public static function PcvGcvsubmit($proposal, $request)
    {        
        $enquiryId = customDecrypt($request['enquiryId']);

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        if(in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']))
        {
            return ITSHORTTERM::submit($proposal, $request);
        }

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
            ];
        }

        $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);

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

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $isNewVehicle = 'N';
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
        // zero depriciation validation
//        $is_zero_dep_product = false;
//        if ($productData->zero_dep == '0') {
//            $is_zero_dep_product = true;
//        }
        $is_zero_dep_product = $productData->zero_dep == '0' ? true : false;
        
        $is_pa_cover_owner_driver = 'N';
        $pa_paid_driver = 'false';
        $tppd_cover='750000';
        $validDrivingLicence = 'Y';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                    $is_pa_cover_owner_driver = 'Y';
                } elseif (isset($data['reason']) && $data['reason'] != "") {
                    if ($data['reason'] == 'I do not have a valid driving license.') {
                        $validDrivingLicence = 'N';
                    }
                }
            }
        }

        if ($proposal->owner_type == 'I') {
            if ($requestData->business_type == 'newbusiness') {
                $txt_cust_type = $proposal->owner_type;
                $Date_of_Birth = date('m/d/Y', strtotime($proposal->dob));
            } else {
                $txt_cust_type = $proposal->owner_type;
                $Date_of_Birth = date('m/d/Y', strtotime($proposal->dob));
            }
        } else {
            $txt_cust_type = 'O';
            $emergency_assistance_cover = '0';
            $Date_of_Birth = '01/01/1970';
            $Gender = '';

        }
        $is_breakin = false;
        if ($requestData->business_type == 'newbusiness') {
            $typeofbusiness = 'New Business';
            $product_ncb_discount_rate = '0';
        } else {
            $product_ncb_discount_rate = $requestData->applicable_ncb;
            $typeofbusiness = 'Rollover';
            if ($requestData->business_type == 'breakin') {
                $is_breakin = true;
            }
        }
        $policyDates = self::getPolicyDates($requestData);
        if ($is_pa_cover_owner_driver == 'Y') {
            $proposal->is_cpa = 'Y';
            $proposal->cpa_policy_fm_dt = $policyDates->policyStartDate;
            $proposal->cpa_policy_to_dt = $policyDates->policyEndDate;
        }
        $proposal->tp_start_date = $policyDates->policyStartDate;
        $proposal->tp_end_date = $policyDates->policyEndDate;
        if (strlen($proposal->chassis_number) > 20) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Chassis No. length can not be greater than 20 characters',
            ];
        }
        $first_reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));
        $purchaseregndate = date('m/d/Y', strtotime($first_reg_date));
        $regDate = date("m/d/Y 00:00:00", strtotime($first_reg_date));

        $year = explode('-', $requestData->manufacture_year);
        $motor_electric_accessories = '0';
        $motor_non_electric_accessories = '0';
        $motor_lpg_cng_kit = '0';
        $legal_liability='N';

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($typeofbusiness == 'New Business' || (strtolower($requestData->previous_policy_expiry_date) == 'new') ? '' : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        
        $tenure=1;
        $is_zero_dep = $road_side_assistance = false;
        $include_IMT = false;
        $include_consumable = false;
        
        if (!empty($addons) && $vehicle_age <= 5) { //Upto 5
            foreach ($addons as $key => $name)
            {
                if (in_array('Zero Depreciation', $name) && $name['premium'] > 0) {
                    $is_zero_dep_product = true;
                }
                else if (in_array('IMT - 23', $name) && $name['premium'] > 0) {
                    $include_IMT = true;
                    $is_zero_dep_product = true;
                }                
                else if (in_array('Consumable', $name) && $name['premium'] > 0) {
                    $include_consumable = true;
                }
                else if (in_array('Road Side Assistance', $name) && $name['premium'] > 0) {
                    $road_side_assistance = true;
                    $is_zero_dep_product = true;
                }
            }
        }
        $llpaidDriver = $llpaidCleaner =  $IsLiabilityToPaidCleanerCovered = $llpaidConductor= '';
        $LLNumberCleaner = $LLNumberDriver = $LLNumberConductor = 0;
        $voluntary_deductible_amount = '0';
        $IsLegalLiabilityDriver = "N";
        $pa_unnamed = 0;
        $LLNumberSum = 0;
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $pa_unnamed = $data['sumInsured'];
                }
               
                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $IsLegalLiabilityDriver = 'Y';
                }
                if ($is_GCV && $data['name'] == 'LL Paid Driv/Cleaner/Conductor' && isset($data['sumInsured'])) {
                    $IsLegalLiabilityDriver = 'Y';
                }

                if ($is_GCV && $data['name'] == 'LL paid driver/conductor/cleaner') {
                    $llpaidDriver = in_array('DriverLL', $data['selectedLLpaidItmes']) ? 'Y' : 'N';
                    $llpaidConductor = in_array('ConductorLL', $data['selectedLLpaidItmes']) ? 'Y' : 'N';
                    $llpaidCleaner = in_array('CleanerLL', $data['selectedLLpaidItmes']) ? 'Y' : 'N';
                    // $IsLiabilityToPaidCleanerCovered = in_array('CleanerLL', $value['selectedLLpaidItmes']) ? 'Yes' : 'No';
                    $LLNumberCleaner = $data['LLNumberCleaner'] ?? 0;
                    $LLNumberDriver = $data['LLNumberDriver'] ?? 0;
                    $LLNumberConductor = $data['LLNumberConductor'] ?? 0;
                    $IsLegalLiabilityDriver = 'Y';
                   
                }      

            }
        }

        $LLNumberSum = $LLNumberCleaner + $LLNumberDriver + $LLNumberConductor;
        $LLSumInsured = ($llpaidDriver == 'Y' ? 'Y' : ($llpaidConductor == 'Y' ? 'Y' :($llpaidCleaner=='Y' ? 'Y':'N'))); 
         
        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $motor_lpg_cng_kit = $data['sumInsured'];
                }

//                if ($data['name'] == 'Non-Electrical Accessories') {
//                    $motor_non_electric_accessories = $data['sumInsured'];
//                }

                if ($is_GCV && $data['name'] == 'Electrical Accessories') {
                    $motor_electric_accessories = $data['sumInsured'];
                }
            }
        }
        if ($tp_only) {
            $product_ncb_discount_rate = 0;
        }
        $vehicle_coverages['IDV'] = [
            'coverageId' => 'IDV Basic',
            'number' => $tp_only ? 1 : $proposal->idv,
            'sumInsured' => $tp_only ? 1 : $proposal->idv,
        ];
        $vehicle_coverages['CPA'] = [
            'coverageId' => 'PA Owner / Driver',
            'number' => '',
            'sumInsured' => (($proposal->owner_type == 'I') ? $is_pa_cover_owner_driver : 'N'),
        ];
        if (in_array($requestData->fuel_type ,["CNG", "LPG"]) && (int) $motor_lpg_cng_kit == 0) {
            $vehicle_coverages['CNG Kit'] = [
                'coverageId' => 'CNG Kit Company Fit',
                'number' => '',
                'sumInsured' => "Y",
            ];
        } elseif ((int) $motor_lpg_cng_kit > 0) {
            $vehicle_coverages['CNG KIT'] = [
                'coverageId' => 'CNG Kit',
                'number' => '',
                'sumInsured' => $motor_lpg_cng_kit,
            ];
        }
        $vehicle_coverages['NCB'] = [
            'coverageId' => 'No Claim Bonus',
            'number' => '',
            'sumInsured' => $product_ncb_discount_rate,
        ];
        
        if($is_GCV)
        {
            if($IsLegalLiabilityDriver == 'Y')
            {
                $vehicle_coverages[] = [
                    'coverageId' => 'LL Paid Driv/Cleaner/Conductor',
                    'number' => $LLNumberSum,
                    'sumInsured' => $LLSumInsured,
                ];                
            }        
        }
        $is_imt_added = 'N';       
        if ($is_zero_dep_product) 
        {  
            $vehicle_coverages['Depreciation Waiver'] = 
            [
                'coverageId' => 'Depreciation Waiver',
                'number' => '',
                'sumInsured' => 'Y' 
            ];
            if($include_IMT == true)
            {
                $is_imt_added = 'Y';
                $vehicle_coverages['IMT 23'] = 
                [
                    'coverageId' => 'IMT 23',
                    'number' => '',
                    'sumInsured' => 'Y',   
                ];
            }            
            if ($include_consumable) 
            {
                $vehicle_coverages['consumable'] = [
                    'coverageId' => 'Consumable',
                    'number' => '',
                    'sumInsured' => 'Y',
                ];
            }
        }
        if ($is_GCV) 
        {
            if($road_side_assistance)
            {
                $vehicle_coverages[] = 
                [
                    'coverageId' => 'Towing & Related',
                    'number' => '',
                    'sumInsured' => 'Y',   
                ];                
            }
            
            if($motor_electric_accessories > 0)
            {
                $vehicle_coverages[] =
                [
                    'coverageId' => 'Electrical Accessories',
                    'number' => '',
                    'sumInsured' => (($motor_electric_accessories != '') ? $motor_electric_accessories : 0)
                ];                
            }
            
        }
         
       
         if (in_array($requestData->fuel_type,["CNG", "LPG"]) && (int) $motor_lpg_cng_kit == 0) {
            $vehicle_coverages['CNG Kit'] = [
                'coverageId' => 'CNG Kit Company Fit',
                'number' => '',
                'sumInsured' => "Y",
            ];
        } elseif ((int) $motor_lpg_cng_kit > 0) {
            $vehicle_coverages['CNG KIT'] = [
                'coverageId' => 'CNG Kit',
                'number' => '',
                'sumInsured' => $motor_lpg_cng_kit,
            ];
        }
        
       
        foreach ($vehicle_coverages as $k => $v) {
//            if ($premium_type == 'own_damage') {
//                if ($v['coverageId'] == 'PA Owner / Driver') {
//                    unset($vehicle_coverages[$k]);
//                } else if ($v['coverageId'] == 'TPPD') {
//                    unset($vehicle_coverages[$k]);
//                } else if (in_array($v['coverageId'],['LL Paid Driv/Cleaner/Conductor','Legal Liability to Driver'])) {
//                    unset($vehicle_coverages[$k]);
//                } else if ($v['coverageId'] == 'PA to Passenger') {
//                    unset($vehicle_coverages[$k]);
//                }
//            } else 
            if ($tp_only) {
                if ($v['coverageId'] == 'Electrical Accessories') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'Cost of Accessories') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'No Claim Bonus') {
                    unset($vehicle_coverages[$k]);
                } else if ($v['coverageId'] == 'Consumable') {
                    unset($vehicle_coverages[$k]);
                }
            } else if ($requestData->business_type == 'newbusiness') {
                if ($v['coverageId'] == 'No Claim Bonus') {
                    unset($vehicle_coverages[$k]);
                }
            }
        }

        $inceptionDate = date('m/d/Y 00:00:00', strtotime($policyDates->policyStartDate));
        $expiryDate = date('m/d/Y 23:59:59', strtotime($policyDates->policyEndDate));

        $voluntary_deductible_od_premium = $voluntary_deductible_tp_premium = 0;

        $model_config_premium = [
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
                        'previousPolicyEndDate' =>($typeofbusiness == 'New Business' || (strtolower($requestData->previous_policy_expiry_date) == 'new')) ? '' : date('m/d/Y', strtotime($requestData->previous_policy_expiry_date)),
                        'vehicle' => [
                            'capacity' => $mmv_data['cc'] ?? null,
                            'engineCpacity' => $mmv_data['cc'] ?? null,
                            'make' => $mmv_data['make_code'],
                            'registrationDate' => date('m/d/Y', strtotime($requestData->vehicle_register_date)),
                            'seatingCapacity' => $mmv_data['seating_capacity'],
                            'regictrationCity' => $rto_data->rto_city_code,
                            'yearOfManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            'zcover' => $tp_only ? 'AC' : 'CO',
                            'type' => [],
                            'itgiRiskOccupationCode' => [],
                            'grossVehicleWeight' => $mmv_data['gvw']?? null,
                            'validDrivingLicence' => $validDrivingLicence,
                            'nofOfCarTrailers' => 0,
                            'noOfLuggageTrailers' => 0,
                            'luggageAverageIDV' => 0,
                            'vehicleCoverage' => ['item' => array_values($vehicle_coverages)],
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

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'SOAPAction' => 'http://schemas.xmlsoap.org/soap/envelope/',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'PCV',
            'method' => 'Quote Calculation - Proposal Submit',
            'transaction_type' => 'proposal',
            'productName' => $productData->product_name,
        ];
        $root = [
            'rootElementName' => 'soapenv:Envelope',
            '_attributes' => [
                "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
                "xmlns:prem" => "http://premiumwrapper.motor.itgi.com",
            ],
        ];
        $input_array = ArrayToXml::convert($model_config_premium, $root, false, 'utf-8');
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL'), $input_array, 'iffco_tokio', $additional_data);
        $data = $get_response['response'];
        if (empty($data)) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $premium_data = XmlToArray::convert((string) $data);
        if (!isset($premium_data['soapenv:Body'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $ncb_amount = $pa_unnamed = $voluntary_excess = $elecAccSumInsured = $nonelecAccSumInsured = $aai_discount = $consumable = $towing_related_cover = $anti_theft_amt = $elecAccValue = $nonelecAccValue = $cngOdPremium = $cngTpPremium = $tppd_discount = $pa_owner_driver = $legalLiability_to_driver = $dep_value = $cng_internal_TpPremium = $cng_internal_OdPremium = $idv_basic_od  = 0;
        $imt_23=0;
        $premium_data_ns1 = $premium_data['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'][0];
        $premium_data_ns2 = $premium_data['soapenv:Body']['getMotorPremiumResponse']['getMotorPremiumReturn'][1];

        $response_body = $is_zero_dep_product && !$tp_only ? $premium_data_ns2 : $premium_data_ns1;
         
      
        unset($premium_data_ns2, $premium_data_ns1);

        if (isset($response_body['error']) && !empty($response_body['error']['errorMessage'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $response_body['error']['errorMessage'],
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

        $electric_accessories = intval($motor_electric_accessories);
        $non_electric_accessories = intval($motor_non_electric_accessories);
        $lpg_cng_kit = intval($motor_lpg_cng_kit);

        $length = 13;
        $unique_quote = mt_rand(pow(10, ($length - 1)), pow(10, $length) - 1);

        $coveragepremiumdetail = $response_body['coveragePremiumDetail'];

        IffcoTokioPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
      
        foreach ($coveragepremiumdetail as $k => $v) {
            $coverage_name = $v['coverageName'];
            if ($coverage_name == 'IDV Basic') {
                $od_premium = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
                $idv_basic_od = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
                $tp_premium = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'Voluntary Excess') {
                $voluntary_excess = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'No Claim Bonus') {
                $ncb_amount = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'PA Owner / Driver') {
                $pa_owner_driver = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'PA to Passenger') {
                $pa_unnamed = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'Legal Liability to Driver') {
                $legalLiability_to_driver = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'LL Paid Driv/Cleaner/Conductor') {
                $legalLiability_to_driver = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'Electrical Accessories') {
                $elecAccValue = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'Cost of Accessories') { //non electrical accessories
                $nonelecAccValue = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'CNG Kit') {
                $cngOdPremium = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
                $cngTpPremium = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            } else if ($coverage_name == 'CNG Kit Company Fit') {
                $cng_internal_OdPremium = intval($v['odPremium']) == 1 ? 0 : round(is_array($v['odPremium']) ? 0 : $v['odPremium']);
                $cng_internal_TpPremium = intval($v['tpPremium']) == 1 ? 0 : round(is_array($v['tpPremium']) ? 0 : $v['tpPremium']);
            } else if ($coverage_name == 'AAI Discount') {
                $aai_discount = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'Anti-Theft') {
                $anti_theft_amt = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);
            } else if ($coverage_name == 'Depreciation Waiver') {
                $dep_value = intval($v['coveragePremium']) == 1 ? 0 : round($v['coveragePremium']);
            } else if ($coverage_name == 'Consumable') {
                $consumable = intval($v['coveragePremium']) == 1 ? 0 : round($v['coveragePremium']);
            }
             else if ($coverage_name == 'Towing & Related') {
                $towing_related_cover = intval($v['coveragePremium']) == 1 ? 0 : round($v['coveragePremium']);
                
            } else if ($coverage_name == 'TPPD') {
                $tppd_discount = intval($v['tpPremium']) == 1 ? 0 : round($v['tpPremium']);
            }
            else if ($coverage_name == 'IMT 23'){    
                $imt_23 = intval($v['odPremium']) == 1 ? 0 : round($v['odPremium']);  
             }
        }

        $totalodpremium = $response_body['totalODPremium'];
        $totaltppremium = $response_body['totalTPPremium'];
        $total_premium_after_discount = $response_body['totalPremimAfterDiscLoad'];
        $discount_amount = ($response_body['discountLoadingAmt']);
        $service_tax = $response_body['serviceTax'];

        $total_amount_payable = $response_body['premiumPayable'];
        $OdDiscountAmt = $response_body['discountLoadingAmt'];
        $OdDiscountLoading = $response_body['discountLoading'];
        $OdSumDisLoad = $response_body['totalPremimAfterDiscLoad'];
        $VoluntaryDeductiblevalue = $voluntary_excess;
        $IsZeroDept_Cover = 'Y';
        $IsZeroDept_RollOver = "Y";
        $towing = $towing_related_cover;
        $total_od_amount = ($od_premium + $elecAccValue + $nonelecAccValue + $cngOdPremium + abs($discount_amount));
        $total_discount_amount = abs($OdDiscountAmt) + abs($ncb_amount) + abs($aai_discount) + abs($VoluntaryDeductiblevalue) + round(abs($anti_theft_amt)) + abs($tppd_discount);
        $addon_total = round($dep_value + $towing + $consumable + $imt_23);
    
        //$total_sum_insured = $motor_electric_accessories + $motor_non_electric_accessories + $lpg_cng_kit;
        //$total_sum_insured += $tp_only ? 1 : (int) $proposal->idv;

        //$total_sum_insured = round((($electric_accessories!='') ? $electric_accessories : 0) + (($non_electric_accessories !='') ? $non_electric_accessories :0) + (($lpg_cng_kit !='') ? $lpg_cng_kit : 0) + $proposal->idv );
  
        $od_premium_amount = $od_premium;
        $od_premium = $od_premium + abs($OdDiscountAmt);

        $total_discount = round(abs($ncb_amount)) + abs($VoluntaryDeductiblevalue) + abs($anti_theft_amt) + abs($aai_discount) + round(abs($discount_amount));

        $net_premimum = round($total_od_amount) + $totaltppremium - round(abs($total_discount)) + $addon_total;
        unset($data);
        $vehicleDetails = [
            'manufacture_name' => $mmv_data['manufacturer'],
            'model_name' => $mmv_data['model'],
            'version' => $mmv_data['variant'],
            'fuel_type' => $mmv_data['fuel_type'],
            'seating_capacity' => $mmv_data['seating_capacity'],
            'carrying_capacity' => $mmv_data['seating_capacity'] - 1,
            'cubic_capacity' => $mmv_data['cc'] ?? null,
            'gross_vehicle_weight' => $mmv_data['grossVehicleWt'] ?? null,
            'vehicle_type' => 'Taxi',
        ];

        $total_sum_insured = (($motor_electric_accessories != '') ? $motor_electric_accessories : 0) + (($motor_lpg_cng_kit != '') ? $motor_lpg_cng_kit : '0') + (($motor_non_electric_accessories != '') ? $motor_non_electric_accessories : 0) + (($proposal->idv != '') ? $proposal->idv : '0');
        //$total_sum_insured = (($motor_lpg_cng_kit != '') ? $motor_lpg_cng_kit : '0') + (($proposal->idv != '') ? $proposal->idv : '0');
        $total_ci = (($motor_lpg_cng_kit != '') ? $motor_lpg_cng_kit : '0');
        
        $additional_details_data = [
            "unique_quote" => $unique_quote,
            "idv_basic_od" => $idv_basic_od,
            "OdDiscountAmt" => $OdDiscountAmt,
            "pa_unnamed" => $pa_unnamed,
            "totalTpPremium" => $totaltppremium,
            "OdDiscountLoading" => $OdDiscountLoading,
            "OdSumDisLoad" => $OdSumDisLoad,
            "NCB_RenewalPolicy" => $requestData->applicable_ncb,
            "towing" => $towing,
            "total_od" => $totalodpremium,
            "cpa_premium" => $pa_owner_driver,
            "zero_dep_value" => round($dep_value),
            "VoluntaryDeductible" => $voluntary_excess,
            "VoluntaryDeductiblevalue" => $voluntary_deductible_amount,
            "tp_premium" => $tp_premium,
            "LPG_CNG_KIT_TP" => ((isset($cngTpPremium) && $cngTpPremium != '') ? $cngTpPremium : '0'),
            "LPG_CNG_Kit" => ((isset($cngOdPremium) && $cngOdPremium != '') ? $cngOdPremium : '0'),
            "cng_internal_OdPremium" => $cng_internal_OdPremium,
            "cng_internal_TpPremium" => $cng_internal_TpPremium,
            "electrical_accessories" => $elecAccValue,
            "non_electrical_accessories" => $nonelecAccValue,
            "legalLiability_to_driver" => $legalLiability_to_driver,
            "consumable" => $consumable,
            "anti_theft_amt" => $anti_theft_amt,
            "total_sum_insured" => $total_sum_insured,
            "tppd_discount" => $tppd_discount,
            "total_ci" => $total_ci,
            "is_imt_23"=> $is_imt_added,
            "imt_23_premium"=> $imt_23
        ];
        
        if ($tp_only) {
            $total_sum_insured = $total_ci + 1;
        }
        $proposal->policy_start_date = $policyDates->policyStartDate;
        $proposal->policy_end_date = $policyDates->policyEndDate;
        $proposal->proposal_no = $unique_quote;
        $proposal->unique_proposal_id = $unique_quote;
        $proposal->od_premium = round($total_od_amount);
        $proposal->tp_premium = $tp_premium;
        $proposal->totalTpPremium = $totaltppremium;
        $proposal->addon_premium = $addon_total;
        $proposal->cpa_premium = $pa_owner_driver;
        $proposal->final_premium = round($net_premimum);
        $proposal->total_premium = round($net_premimum);
        $proposal->service_tax_amount = round($service_tax);

        $proposal->final_payable_amount = round($total_amount_payable);
        $proposal->ic_vehicle_details = $vehicleDetails;
        $proposal->ncb_discount = abs($ncb_amount);
        $proposal->total_discount = $total_discount_amount;
        $proposal->electrical_accessories = $elecAccValue;
        $proposal->unique_quote = $unique_quote;
        $proposal->non_electrical_accessories = $nonelecAccValue;
        $proposal->additional_details_data = $additional_details_data;
        $data['user_product_journey_id'] = $enquiryId;
        $data['ic_id'] = $master_policy->insurance_company_id;
        $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        $data['proposal_id'] = $proposal->user_proposal_id;
        $proposal->save();
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

            if($proposal->owner_type == 'I' && ($proposal->last_name === null || $proposal->last_name == ''))
            {
                $proposal->last_name = '.';
            }

            $payload = [
                'user_name' => $proposal->first_name . ' ' . $proposal->last_name,
                'user_email' => $proposal->email,
                'reg_number' => $proposal->vehicale_registration_number,
                'veh_manuf' => $mmv_data['manufacturer'],
                'veh_model' => $mmv_data['model'],
                'mobile_name' => $proposal->mobile_number,
                'fuel_type' => $mmv_data['fuel_type'],
                'veh_variant' => $mmv_data['variant'],
                'vehicle_category' => 'car', // Should be as per Documentation
                'enquiry_id' => $enquiryId,
                'address' => implode(', ', [$proposal->address_line1, $proposal->address_line2, $proposal->address_line3, $proposal->state]),
                'city' => $proposal->city,
                'model_year' => $requestData->manufacture_year,
                'section' => 'cv',
                'ic_name' => 'iffco_tokio'
            ];
            
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
                ];
            }
        } else {
            $is_breakin = '';
            $inspection_no = '';
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        }
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
                'finalPayableAmount' => $proposal->final_payable_amount,
                'is_breakin' => $is_breakin,
                'inspection_number' => $inspection_no,
            ],
            'additional_details_data' => $additional_details_data,
            'coveragepremiumdetail' => $coveragepremiumdetail,
            'request' => [
                'addons' => $addons,
                'is_imt_added' => $is_imt_added,
                'is_zero_dep_product' => $is_zero_dep_product,
                'vehicle_coverage' => $vehicle_coverages
            ]
        ]);
    }
}
