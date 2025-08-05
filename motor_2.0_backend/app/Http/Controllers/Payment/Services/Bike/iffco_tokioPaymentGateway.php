<?php

namespace App\Http\Controllers\Payment\Services\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';

use DateTime;
use Exception;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Jobs\GeneratePDFJob;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\iffco_tokioFinancierMaster;
use Carbon\Carbon;
use App\Models\WebServiceRequestResponse;

class iffco_tokioPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        $proposal_data = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        if($proposal_data) {
            $quote_log_id = QuoteLog::where('user_product_journey_id', $proposal_data->user_product_journey_id)
                ->pluck('quote_id')
                ->first();
            $icId = MasterPolicy::where('policy_id', $request['policyId'])
                ->pluck('insurance_company_id')
                ->first();

            DB::table('payment_request_response')
                ->where('user_product_journey_id', $proposal_data->user_product_journey_id)
                ->update(['active' => 0]);

            $additional_details_data = json_decode($proposal_data->additional_details_data);
            $enquiryId = customDecrypt($request['userProductJourneyId']);
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
            $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

            $requestData = getQuotation($enquiryId);
            $productData = getProductDataByIc($request['policyId']);

            $reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));
            $tppd_cover = 100000;
            $is_tppd_cover = false;
            foreach($discounts as $key => $data){
                if ($data['name'] == 'TPPD Cover' && $requestData->business_type != 'newbusiness') {
                    $is_tppd_cover = true;
                    $tppd_cover = 6000;
                }
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
            $city = DB::table('iffco_tokio_address_city_master as city_code')
                ->where('CITY_DESC',Str::upper($proposal_data->city))
                ->select('city_code.*')->first();
            $city = keysToLower($city);

            if(empty($city)) {
                return [
                    'status' => false,
                    'message' => 'City Code not found for city : ' . $proposal_data->city,
                ];
            }

            // $city = array_change_key_case((array) $city, CASE_UPPER);
            // $city = json_decode(json_encode($city));

            $mmv_data = get_mmv_details($productData,$requestData->version_id,'iffco_tokio');
            if ($mmv_data['status'] == 1) {
                // $mmv_data = (object) array_change_key_case((array) $mmv_data, CASE_LOWER); //original
                $mmv_data = (object) array_change_key_case((array) $mmv_data['data'], CASE_LOWER);
            } else {
                return  [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => $mmv_data['message']
                ];
            }

            $isNewVehicle = 'N';
            
            if ($requestData->business_type == 'newbusiness') {
                $businessType = 'New Business';
                $isNewVehicle = 'Y';
            } else if ($requestData->business_type == 'rollover') {
                $businessType = 'Rollover';
            } else if ($requestData->business_type == 'breakin') {
                // $businessType = 'Break-In';
                // $policy_start_date = date('Y-m-d', strtotime('+2 day', strtotime($requestData->previous_policy_expiry_date)));
                // $product_name = 'BreakinTwoWheeler';
            }

            $payment_url = config('constants.IcConstants.iffco_tokio.PAYMENT_GATEWAY_LINK_IFFCO_TOKIO_BIKE');
            $partnerType = '';
            $pospPanNumber = '';
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('seller_type','P')
                ->first();
            
            if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                $partnerType = 'POS';
                $pospPanNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
                if (!empty(config('IFFCO_TOKIO_TEST_POS'))) {
                    $pospPanNumber = config('IFFCO_TOKIO_TEST_POS');
                }
            }else if (config('constants.motorConstant.IS_POS_ENABLED_CAR_IFFCO_TESTING') == 'Y') {
                $partnerType = 'POS';
                $pospPanNumber = 'ALXPB1224DF';
            }

            if(config('IS_IFFCO_BIKE_NON_POS') == 'Y') {
                $partnerType = '';
                $pospPanNumber = '';
            }
            $is_pa_cover_owner_driver = 'N';
            $tenure = '1';
            $ncb_RenewalPolicy = 'N';
            $premium_type = DB::table('master_premium_type')
                ->where('id',$productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
            $tp_only = (in_array($premium_type, ['third_party', 'third_party_breakin'])) ? true : false;
            $od_only = (in_array($premium_type, ['own_damage', 'own_damage_breakin'])) ? true : false;

            if(!empty($additional['compulsory_personal_accident']))
            {
                foreach ($additional['compulsory_personal_accident'] as $key => $data)
                {
                    if (isset($data['name']) && $data['name']  == 'Compulsory Personal Accident')
                    {
                        $is_pa_cover_owner_driver = 'Y';
                        $tenure = $data['tenure'] ?? '1';
                    }
                    elseif (isset($data['reason']) && $data['reason'] != "")
                    {
                        if ($data['reason'] == 'I do not have a valid driving license.') {
                            $cpa_reason = 'true';
                        }
                    }
                }
            }
            if($proposal_data->ncb_discount != null ){
                $ncb_RenewalPolicy = 'Y';
                $ncb_discount = "-".$proposal_data->ncb_discount;
            }
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new DateTime($vehicleDate);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $bike_age = floor($age / 12);
            $is_zero_dep = 'N';$towing_related = 'N';
            if($bike_age <= 5) {
                foreach ($addons as $key => $value) {
                    if (in_array('Zero Depreciation', $value)) {
                        $is_zero_dep = 'Y';
                    }

                    if (in_array('Road Side Assistance', $value)) {
                        $towing_related = "Y"; //road side assistance
                        // $is_zero_dep = 'Y';
                    }
                }
            }

            $proposal_data->cpa_premium == 0 ? $is_pa_cover_owner_driver = 'N' : $is_pa_cover_owner_driver = 'Y';
            $un_named = '';
            $unnamed_passenger = 'N';
            foreach($additional_covers as $key => $value) {
                if ($value['name'] == 'Unnamed Passenger PA Cover') {
                    $unnamed_passenger = 'Y';
                    $un_named = $value['sumInsured'];
                }
            }

            $Coverages = [
                [
                    'Code' => 'IDV Basic',
                    'SumInsured' => (!$tp_only) ? $proposal_data->idv : '1',
                    'Number' => '',
                    'ODPremium' => (!$tp_only) ? $proposal_data->od_premium : '0',
                    'TPPremium' => $additional_details_data->tp_premium,
                ]
                // , [
                //     'Code' => 'PA Owner / Driver',
                //     'SumInsured' => $is_pa_cover_owner_driver,
                //     'Number' => '',
                //     'ODPremium' => '0.0',
                //     'TPPremium' => $additional_details_data->cpa_premium
                // ], [
                //     'Code' => 'No Claim Bonus',
                //     'SumInsured' => $additional_details_data->ncb_renewal_policy == "0" ? "" : $additional_details_data->ncb_renewal_policy,
                //     'Number' => '',
                //     'ODPremium' => $ncb_discount == "-0" ? 0 : $ncb_discount,
                //     'TPPremium' => 0
                // ], [
                //     'Code' => 'PA to Passenger',
                //     'SumInsured' => $un_named,
                //     'Number' => '',
                //     'ODPremium' => 0,
                //     'TPPremium' => $additional_details_data->pa_unnamed
                // ], [
                //     'Code' => 'TPPD',
                //     'SumInsured' => $tppd_cover,
                //     'Number' => '',
                //     'ODPremium' => 0,
                //     'TPPremium' => $tppd_cover == 100000  ? 0 : ((int) $additional_details_data->tppd_discount)*(-1)
                // ], [
                //     'Code' => 'Depreciation Waiver',
                //     'SumInsured' => $is_zero_dep,
                //     'Number' => '',
                //     'ODPremium' => $additional_details_data->zero_dep_value,
                //     'TPPremium' => 0
                // ], [
                //     'Code' => 'Towing & Related',
                //     'SumInsured' => $towing_related,
                //     'Number' => '',
                //     'ODPremium' => $additional_details_data->towing == '' ? '0' : $additional_details_data->towing,
                //     'TPPremium' => 0
                // ]
            ];
            if($is_pa_cover_owner_driver == 'Y'){
                $Coverages = array_merge($Coverages, [
                    [
                        'Code' => 'PA Owner / Driver',
                        'SumInsured' => $is_pa_cover_owner_driver,
                        'Number' => $tenure,
                        'ODPremium' => '0.0',
                        'TPPremium' => $additional_details_data->cpa_premium
                    ]
                ]);
            }
            if ($isNewVehicle != 'Y') {
                $Coverages = array_merge($Coverages, [
                    [
                        'Code' => 'No Claim Bonus',
                        'SumInsured' => $additional_details_data->ncb_renewal_policy == "0" ? "" : $additional_details_data->ncb_renewal_policy,
                        'Number' => '',
                        'ODPremium' => $ncb_discount == "-0" ? 0 : $ncb_discount,
                        'TPPremium' => 0
                    ]
                ]);
            }
            if ($unnamed_passenger == 'Y') {
                $Coverages = array_merge($Coverages, [
                    [
                        'Code' => 'PA to Passenger',
                        'SumInsured' => $un_named,
                        'Number' => '',
                        'ODPremium' => 0,
                        'TPPremium' => $additional_details_data->pa_unnamed
                    ]
                ]);
            }
            if ($is_tppd_cover == true) {
                $Coverages = array_merge($Coverages, [
                    [
                        'Code' => 'TPPD',
                        'SumInsured' => $tppd_cover,
                        'Number' => '',
                        'ODPremium' => 0,
                        'TPPremium' => $tppd_cover == 100000  ? 0 : ((int) $additional_details_data->tppd_discount)*(-1)
                    ]
                ]);
            }
            if ($is_zero_dep == "Y") {
                $Coverages = array_merge($Coverages, [
                    [
                        'Code' => 'Depreciation Waiver',
                        'SumInsured' => $is_zero_dep,
                        'Number' => '',
                        'ODPremium' => $additional_details_data->zero_dep_value,
                        'TPPremium' => 0
                    ]
                ]);
            }
            if ($towing_related == "Y") {
                $Coverages = array_merge($Coverages, [
                    [
                        'Code' => 'Towing & Related',
                        'SumInsured' => $towing_related,
                        'Number' => '',
                        'ODPremium' => $additional_details_data->towing == '' ? '0' : $additional_details_data->towing,
                        'TPPremium' => 0
                    ]
                ]);
            }
            if((int)$additional_details_data->voluntary_excess_od > 0){
                array_push($Coverages, [
                    'Code' => 'Voluntary Excess',
                    'SumInsured' => $additional_details_data->voluntary_excess_amt,
                    'Number' => '',
                    'ODPremium' => empty($additional_details_data->voluntary_excess_amt) ? '0' : $additional_details_data->voluntary_excess_od,
                    'TPPremium' => empty($additional_details_data->voluntary_excess_amt) ? '0' : $additional_details_data->voluntary_excess_tp,
                ]);
            }

            foreach($Coverages as $k=>$v){
                if ($proposal_data->owner_type != 'I') {
                    if($v['Code'] == 'PA Owner / Driver'){
                        unset($Coverages[$k]);
                    }
                }
                if($od_only) {
                    if($v['Code'] == 'PA Owner / Driver'){
                        unset($Coverages[$k]);
                    }
                    else if($v['Code'] == 'PA to Passenger'){
                        unset($Coverages[$k]);
                    }
                }
                else if($tp_only) {
                    if($v['Code'] == 'No Claim Bonus'){
                        unset($Coverages[$k]);
                    }
                    if($v['Code'] == 'Depreciation Waiver'){
                        unset($Coverages[$k]);
                    }
                    if($v['Code'] == 'Towing & Related'){
                        unset($Coverages[$k]);
                    }
                    if($v['Code'] == 'Voluntary Excess'){
                        unset($Coverages[$k]);
                    }
                }
            }

            $Coverages = array_values($Coverages);
            $policy_created_date = date('m/d/Y H:i:s',strtotime($proposal_data->created_date));
            $inception_date = date("m/d/Y 00:00:00",strtotime($proposal_data->policy_start_date));
            $policy_expiry_date = date('m/d/Y 23:59:59', strtotime($proposal_data->policy_end_date));
            if ($isNewVehicle == 'Y') {
                $policy_expiry_date = date('m/d/Y 23:59:59', strtotime('+5 years -1 day'));
            }
            $ppstartdate = ($isNewVehicle == 'N') ? date("m/d/Y 00:00:00", strtotime('-1 year', strtotime($requestData->previous_policy_expiry_date))) : "";
            $ppenddate = ($isNewVehicle == 'N') ? date("m/d/Y 23:59:59", strtotime($requestData->previous_policy_expiry_date)) : "";
            $date_diff = get_date_diff('day', strtolower($requestData->previous_policy_expiry_date) == 'new' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $BreakInofMorethan90days = 'N';
            if ($date_diff > 90 || (strtolower($requestData->previous_policy_expiry_date) == 'new') && $isNewVehicle == 'N') {
                $BreakInofMorethan90days = 'Y';
                $ppenddate = $ppstartdate = '';
            }else {
                $ppenddate = date("m/d/Y 23:59:59", strtotime($requestData->previous_policy_expiry_date));
                $ppstartdate = date('m/d/Y 00:00:00', strtotime('-1 year +1 day', strtotime($ppenddate)));
            }
            $length = 13;
            $unique_quote = mt_rand(pow(10,($length-1)),pow(10,$length)-1);
            UserProposal::where('user_proposal_id' , $proposal_data->user_proposal_id)->update([
                'proposal_no' => $unique_quote,
                'unique_proposal_id' => $unique_quote,
                'unique_quote' => $unique_quote,
            ]);
            //$unique_quote = $additional_details_data->unique_quote;

            if($tp_only){
                $total_sum_insured = $additional_details_data->total_ci + 1;
            } else {
                $total_sum_insured = $additional_details_data->total_sum_insured;
            }

            $Policy = [
                'CreatedDate' => $policy_created_date,
                'InceptionDate' => $inception_date,
                'ExpiryDate' => $policy_expiry_date,
                'ExternalBranch' => config('constants.IcConstants.iffco_tokio.partnerBranchBike'),
                'ExternalServiceConsumer' => config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
                'ExternalSubBranch' => config('constants.IcConstants.iffco_tokio.partnerSubBranchBike'),
                'GeneralPage' => '',
                'GrossPremium' => $proposal_data->total_premium,
                'NetPremiumPayable' => $proposal_data->final_payable_amount,
                'OdDiscountAmt' => $additional_details_data->od_discount_amt,
                'OdDiscountLoading' => $additional_details_data->od_discount_loading,
                'OdSumDisLoad' => $additional_details_data->od_sum_dis_loading,
                //'BreakInofMorethan90days' => $additional_details_data->vehicle_in_90_days,
                'BreakInofMorethan90days' => $BreakInofMorethan90days,
                'PreviousPolicyEnddate' => $ppenddate,
                'PreviousPolicyInsurer' => $proposal_data->previous_insurance_company,
                'PreviousPolicyNo' => $proposal_data->previous_policy_number,
                'PreviousPolicyStartdate' => $ppstartdate,
                'Product' => config('constants.IcConstants.iffco_tokio.contractType_Bike'),
                'ServiceTax' => $proposal_data->service_tax_amount,
                'TotalSumInsured' => $total_sum_insured,
                'TpSumDisLoad' => $additional_details_data->total_tp_premium,
                'UniqueQuoteId' => $unique_quote,
                'PartnerType' => $partnerType,
                'POSPanNumber' => $pospPanNumber,
            ];
 
            if($requestData->business_type == 'newbusiness')
            {
                unset($Policy['BreakInofMorethan90days']);
                unset($Policy['PreviousPolicyEnddate']);
                unset($Policy['PreviousPolicyInsurer']);
                unset($Policy['PreviousPolicyNo']);
                unset($Policy['PreviousPolicyStartdate']);
            }
            if($proposal_data->owner_type == 'I') {
                if($is_pa_cover_owner_driver == 'Y'){
                    $Policy['Nominee']              = $proposal_data->nominee_name;
                    $Policy['NomineeRelationship']  = $proposal_data->nominee_relationship;
                }else{
                    unset($Policy['Nominee']);
                    unset($Policy['NomineeRelationship']);
                }
            } else {
                $Policy['CorporateClient']  = 'Y';
            }

            if($premium_type == 'comprehensive') {
                $policy_type = 'CP';
            } else if($tp_only){
                $policy_type = 'TP';
            } else if($premium_type == 'breakin') {
                $policy_type = 'CP';
            } else if($od_only) {
                $policy_type = 'OD';
                if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                    $Policy['TpInceptionDate'] = date('m/d/Y', strtotime($proposal_data->tp_start_date)); //$tp_start_date;
                    $Policy['TpExpiryDate'] = date('m/d/Y', strtotime($proposal_data->tp_end_date)); //$tp_end_date;
                    $Policy['TpInsurerName'] = $proposal_data->tp_insurance_company; //$tp_insurance_company;
                    $Policy['TpPolicyNo'] = $proposal_data->tp_insurance_number; //$tp_insurance_number ;
                }
            }

            if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                unset($Policy['PreviousPolicyInsurer']);
                unset($Policy['PreviousPolicyNo']);
            }

            $year = explode('-', $requestData->manufacture_year);
            $yearOfManufacture = trim(end($year));
            $reg_no = explode('-', $proposal_data->rto_location);
            
            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                $reg_no = explode('-', $proposal_data->vehicale_registration_number);
                if($reg_no[0] == 'DL') {
                    if(strlen($reg_no[2]) == 3) {
                        $reg_no[1] = ltrim($reg_no[1], '0').substr($reg_no[2],0,1);
                        $reg_no[2] = substr($reg_no[2],1);
                    }
                }
            }

            $ValidDrivingLicence = $AlternatePACover = '';
            $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
            
            if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
                if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
                    // User has taken CPA
                    $ValidDrivingLicence = 'Y';
                } else {
                    if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
                        $ValidDrivingLicence = 'N';
                    } else {
                        $ValidDrivingLicence = $AlternatePACover = 'Y';
                    }
                }
            } elseif ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
                $ValidDrivingLicence = $AlternatePACover = ''; // Pass blank in case of company owned vehicle
            }

            $Vehicle = [
                'Capacity' => $mmv_data->seating_capacity,
                'ChassisNumber' => $proposal_data->chassis_number,
                'EngineCapacity' => $mmv_data->cc,//$mmv_data->data['cubic_capacity'],
                'EngineNumber' => $proposal_data->engine_number,
                'GrossVehicleWeight' => 0.0,
                'ImposedExcessPartialLoss' => '',
                'ImposedExcessTotalLoss' => '',
                'Make' => $mmv_data->make_code,//$mmv_data->data['make_code'],
                'ManufacturingYear' => $yearOfManufacture,
                'PolicyType' => ($isNewVehicle == 'Y') ? 'BP' : $policy_type,
                'RegistrationDate' => $reg_date,
                'RegistrationNumber1' => ($isNewVehicle == 'N') ? $reg_no[0] : 'NEW',
                'RegistrationNumber2' => ($isNewVehicle == 'N') ? $reg_no[1] : '',
                'RegistrationNumber3' => ($isNewVehicle == 'N') ? (count($reg_no) == 3 ? "" : $reg_no[2]) : '',
                'RegistrationNumber4' => ($isNewVehicle == 'N') ? (count($reg_no) == 3 ? $reg_no[2] : $reg_no[3]) : '',
                'RiskOccupationCode' => '',
                'RTOCity' => $rto_data->rto_city_code,
                'SeatingCapacity' => $mmv_data->seating_capacity,//$mmv_data->data['seating_capacity'],
                'VehicleBody' => '',
                'Zcover' => (!$tp_only) ? config('constants.IcConstants.iffco_tokio.zcover_bike_co') : config('constants.IcConstants.iffco_tokio.zcover_bike_tp'),
                'Zone' => $rto_data->irda_zone,
                'NewVehicleFlag' => $isNewVehicle,
                'ValidDrivingLicence' => $ValidDrivingLicence,
                'AlternatePACover' => $AlternatePACover,
            ];

            //$salutation = (isset($gender) ? (($gender) == 'M'  ? 'MR' : 'MRS') : '');
            $salutation =   (isset($proposal_data->gender) ? (($proposal_data->gender) == 'M' ? 'MR' : 'MRS')  : '');
            $dob = date("m/d/Y", strtotime($proposal_data->dob));
            $address_data = [
                'address' => $proposal_data->address_line1,
                'address_1_limit'   => 30,
                'address_2_limit'   => 30,
                'address_3_limit'   => 30,
                'address_4_limit'   => 30
            ];
            $getAddress = getAddress($address_data);
            $Contact = [
                'AddressLine1' => $getAddress['address_1'],//$proposal_data->address_line1 == null ? '' : $proposal_data->address_line1,
                'AddressLine2' => empty($getAddress['address_2']) ? '.' : $getAddress['address_2'],//$proposal_data->address_line2 == null ? '' : $proposal_data->address_line2,
                'AddressLine3' => $getAddress['address_3'],
                'AddressLine4' => $getAddress['address_4'],
                'AddressType' => 'R',
                'City' => $city->city_code,
                'Country' => 'IND',
                'CountryOrigin' => 'IND',
                'DOB' => $dob,
                'ExternalClientNo' => ($proposal_data->owner_type == 'I') ? $unique_quote : '',
                'FaxNo' => '',
                'FirstName' => $proposal_data->first_name,
                'GSTIN' => $proposal_data->gst_number,
                'HomePhone' => '',
                'ItgiClientNumber' => '',
                'LastName' => !empty($proposal_data->last_name) ? $proposal_data->last_name : '.',
                'MailId' => $proposal_data->email,
                'Married' => $proposal_data->marital_status == 'Married' ? 'M' : 'S',
                'MobilePhone' => $proposal_data->mobile_number,
                'Nationality' => 'IND',
                'Occupation' => $proposal_data->occupation,
                'OfficePhone' => '',
                'Pager' => '',
                'PAN' => '',
                'PassPort' => '',
                'PinCode' => $proposal_data->pincode,
                'Salutation' => $salutation,
                'Sex' => $proposal_data->gender,
                'SiebelContactNumber' => '',
                'Source' => '',
                'StafFlag' => '',
                'State' => $rto_data->state_code,
                'TaxId' => '',
                'OTP' => 'N',
                'InsuredPAN' => '',
                'InsuredAadhar' => ''
            ];

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $Contact['ItgiKYCReferenceNo'] = $proposal_data->ckyc_reference_id;
            }
            $Account = [
                'AccountNumber' => '',
                'ClientNumber' => '',
                'DOB' => $dob,
                'EconomicActivity' => '',
                'ExternalAccountId' => ($proposal_data->owner_type == 'I') ? '' : $unique_quote,
                'Licence' => '',
                'MailId' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->email,
                'MainFaxNumber' => '',
                'MainPhoneNumber' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->mobile_number,
                'Number' => '',
                'Name' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->first_name,
                'PaidCapital' => '',
                'PAN' => '',
                'PrimaryAccountCity' => $proposal_data->owner_type == 'I' ? '' : $city->city_code,
                'PrimaryAccountCountry' => $proposal_data->owner_type == 'I' ? '' : 'IND',
                'PrimaryAccountPostalCode' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->pincode,
                'PrimaryAccountState' => $proposal_data->owner_type == 'I' ? '' : $rto_data->state_code,
                'PrimaryAccountStreetAddress' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_1'],
                'PrimaryAccountStreetAddress2' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_2'],
                'PrimaryAccountStreetAddress3' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_3'],
                'PrimaryAccountStreetAddress4' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_4'],
                'AccountGSTIN' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->gst_number
            ];

            if ($proposal_data->owner_type == 'C') {
                $Account['DOB'] = date("m/d/Y", strtotime($proposal_data->dob));
            }

            $VehicleThirdParty = [
                'InterestedParty' => '',
                'interestedPartyName' => '',
                'Relation' => 'HY'
            ];

            if (!empty($proposal_data->name_of_financer) && $proposal_data->name_of_financer != 'undefined') {
                $financierDetails = iffco_tokioFinancierMaster::whereRaw('UPPER(name) = ?', [strtoupper($proposal_data->name_of_financer)])
                    ->first();

                $VehicleThirdParty['interestedPartyName'] = $proposal_data->name_of_financer;
                $VehicleThirdParty['InterestedParty'] = $financierDetails->code ?? '';
            }

            $proposal_xml_request = [
                'Policy' => $Policy,
                'Coverage' => $Coverages,
                'Vehicle' => $Vehicle,
                'Contact' => $Contact,
                'Account' => $Account,
                'VehicleThirdParty' => $VehicleThirdParty
            ];

            $proposal_xml_request = ArrayToXML::convert($proposal_xml_request, 'Request');
            $proposal_xml_request = str_replace('<?xml version="1.0" encoding="UTF-8"?>','',$proposal_xml_request);
            $proposal_xml_request = htmlspecialchars_decode($proposal_xml_request);
            $data = [
                'xml_data' => trim($proposal_xml_request),
                'proposal_no' => $unique_quote,
                'QuoteId' => $unique_quote
            ];
            $proposal_data->unique_quote = $proposal_data->proposal_no = $proposal_data->unique_proposal_id = $unique_quote;
            $proposal_data->save();
            //return $data['xml_data'];
            // return [
            //     'status' => 'true',
            //     'XML_DATA' => $proposal_xml_request,
            //     'quote_id'=>    $unique_quote
            // ];

            DB::table('payment_request_response')->insert([
                'quote_id' => $quote_log_id,
                'user_product_journey_id' => $enquiryId,
                'user_proposal_id' => $proposal_data->user_proposal_id,
                'ic_id' => $icId,
                'order_id' => $proposal_data->proposal_no,
                'proposal_no' => $proposal_data->proposal_no,
                'amount' => $proposal_data->final_payable_amount,
                'payment_url' => $payment_url,
                'return_url' => route('bike.payment-confirm', ['iffco_tokio', 'user_proposal_id' => $proposal_data->user_proposal_id, 'policy_id' => $request->policyId]),
                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                'xml_data' => $data['xml_data'],
                'active' => 1
            ]);

            $headers = [
                'SOAPAction' => 'http://schemas.xmlsoap.org/soap/envelope/',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ];
            $startTime = new DateTime(date('Y-m-d H:i:s'));
            $endTime = new DateTime(date('Y-m-d H:i:s'));
            $wsLogdata = [
                'enquiry_id'        => $enquiryId,
                'product'           => $productData->product_name,
                'section'           => 'BIKE',
                'method_name'       => 'Proposal Submit for Payment',
                'company'           => 'iffco_tokio',
                'method'            => 'post',
                'transaction_type'  => 'proposal',
                'request'           => $data['xml_data'],
                'response'          => NULL,
                'endpoint_url'      => $payment_url,
                'ip_address'        => request()->ip(),
                'start_time'        => $startTime->format('Y-m-d H:i:s'),
                'end_time'          => $endTime->format('Y-m-d H:i:s'),
                'response_time'	    => $endTime->getTimestamp() - $startTime->getTimestamp(),
                'created_at'        => Carbon::now(),
                'headers'           => json_encode($headers)
            ];
            WebServiceRequestResponse::create($wsLogdata);

            $testing_url = config('constants.IcConstants.iffco_tokio.testing.return_url_bike');
            $return_data = [
                'form_action' => $payment_url,
                'form_method' => 'POST',
                'payment_type' => 0, // form-submit
                'form_data' => [
                    'RESPONSE_URL' => !empty(trim($testing_url)) ? $testing_url :route('bike.payment-confirm', ['iffco_tokio']),
                    'PARTNER_CODE' => config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
                    'UNIQUE_QUOTEID' => $data['QuoteId'],
                    'XML_DATA' => $data['xml_data'],
                    'enquiry_id' => $enquiryId,
                ],
            ];

            updateJourneyStage([
                'user_product_journey_id' => $proposal_data->user_product_journey_id,
                'ic_id' => $proposal_data->company_id,
                'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            ]);
            // $data_u['user_product_journey_id'] = $proposal_data->user_product_journey_id;
            // $data_u['ic_id'] = $proposal_data->ic_id;
            // $data_u['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
            // updateJourneyStage($data_u);

            return response()->json([
                'status' => true,
                'msg' => "Payment Redirection",
                'data' => $return_data,
            ]);
        }
        else
        {
            return [
                'status' => false,
                'msg' => 'Proposal data not found'
            ];
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function confirm($request) {
        $response = explode('|', $request->ITGIResponse);
        $product = $response[0];
        $TraceNumber = $response[1];
        $policy = $response[2];
        $NetPremiumPayable = $response[3];
        $message = strtoupper($response[4]);
        $QuoteId = $response[5];
        $status = !in_array(strtolower($message), ['transaction_declined', 'portal_payment_invocation_problem', 'response_mismatch', 'transaction_underprocessing']) ? true : false;
        $pg_status = $status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];
        $msg = $status ? STAGE_NAMES['PAYMENT_SUCCESS'] : STAGE_NAMES['PAYMENT_FAILED'];

        DB::table('payment_request_response')
            ->where('order_id', $QuoteId)
            ->where('active', 1)
            ->update([
                'response' => $request->All(),
                'status' => $pg_status
            ]);

        $user_proposal = UserProposal::where('unique_quote', $QuoteId)
            ->orderBy('user_proposal_id', 'desc')
            ->select('*')
            ->first();

        $return_url = config('constants.motorConstant.BIKE_PAYMENT_FAILURE_CALLBACK_URL');

        if(!empty($user_proposal) && !empty($policy) && $status) {
            $user_proposal->policy_no = $policy;
            UserProposal::where('user_proposal_id' , $user_proposal->user_proposal_id)->update([
                'policy_no' => $policy
            ]);

            PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id ], [
                'policy_number' => $policy,
                'status' => 'SUCCESS',
                'ncb' => $user_proposal->ncb_discount,
                'idv' => $user_proposal->idv,
                'policy_start_date' => $user_proposal->policy_start_date,
                'premium' => $user_proposal->total_premium,
            ]);

            $msg = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
            GeneratePDFJob::dispatch('iffco_tokio','bike',$user_proposal)->delay(now()->addMinutes(5));
            // added generate PDF service in queue after 5 mins for IFFCO
            // $generate_pdf = self::generate_pdf($user_proposal)->getOriginalContent();
            // $status = $generate_pdf['status'];
            // $msg = $generate_pdf['msg'];
            $return_url = config('constants.motorConstant.BIKE_PAYMENT_SUCCESS_CALLBACK_URL');
        }

        updateJourneyStage([
            'user_product_journey_id' => $user_proposal->user_product_journey_id,
            'stage' => $msg
        ]);
        
        if(!empty($policy) && $status)
        {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','SUCCESS'));
        }
        else
        {
            return redirect(paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'BIKE','FAILURE'));
        }

        //return redirect($return_url.'?'.http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
    }

    public static function generatePdf($request) {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $generate_pdf = self::generate_pdf($proposal, 'Y')->getOriginalContent();
        $pdf_name = $generate_pdf['pdf_name'];
        $data = [
            'data' => [
                'policy_number' => $proposal->policy_no,
                'pdf_link' => !empty($pdf_name) ? file_url($pdf_name) : null,
            ]
        ];
        $return_data = [
            'status' => $generate_pdf['status'],
            'msg' => $generate_pdf['msg'],
            'line' => $generate_pdf['line'] ?? null,   
        ];
        // 'data' key should only be passed when 'status' key is boolean true, 
        // Error message pop-up will be only shown 'status' is boolean false - @Amit : 08-08-2022
        if($generate_pdf['status']) $return_data = array_merge($return_data, $data);
        
        return response()->json($return_data);
    }

    public static function generate_pdf($user_proposal, $is_rehit = 'N') {
        $status = false;
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        if($is_rehit === 'Y') {
            try{
                $payment_status = self::updatePaymentStatus($user_proposal->user_product_journey_id);
                // Get updated user proposal object
                $user_proposal = UserProposal::where('user_product_journey_id', $user_proposal->user_product_journey_id)->first();
                if(!$payment_status) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Payment is still Pending',
                        'pdf_name' => null
                    ]);
                }
                $status = true;
            } catch(\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'pdf_name' => null
                ]);
            }
        }
        $request_array = [
            'uniqueReferenceNo' => mt_rand(100000, 999999),
            'contractType' => 'TWP',
            'policyDownloadNo' => $user_proposal->policy_no,
            'partnerDetail' => [
                'partnerCode' => config('constants.IcConstants.iffco_tokio.partnerCodeBike')
            ]
        ];

        if ($is_rehit === 'N') { sleep(5); } //Need to apply delay for PDF generation

        $get_response = getWsData(
            config('constants.IcConstants.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_MOTOR_PDF'),
            $request_array,
            'iffco_tokio',
            [
                'requestMethod' =>'post',
                'company'  => 'iffco_tokio',
                'productName'  => '',
                'section' => 'bike',
                'method' => 'PDF Generation',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'transaction_type' => 'proposal',
                'username' => config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
                'password' => config('constants.IcConstants.iffco_tokio.AUTH_PASSWORD_BIKE')
            ]
        );
        $data = $get_response['response'];

        if (!empty($data)) {
            $data = (array) json_decode($data);
            if (isset($data['statusMessage']) && strtoupper($data['statusMessage']) == 'SUCCESS') {
                $pdf_url = $data['policyDownloadLink'];
                $pdf_name = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL').'iffco_tokio/' .  md5($user_proposal->user_proposal_id). '.pdf';
                PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id ], [
                    'ic_pdf_url' => $pdf_url,
                ]);
                try {

                    $pdfGenerationResponse = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false)['response'];

                    if(!preg_match("/^%PDF-/", $pdfGenerationResponse))
                    {
                        //GeneratePDFJob::dispatch('iffco_tokio','bike', $user_proposal)->delay(now()->addMinutes(7));
                        
                        $PdfUrlValid = isHTMLUrlValid($pdf_url);
                        if($PdfUrlValid)
                        {
                            $$pdf_url = '';
                            $readHTMLResponse = readHTMLResponse($pdf_url);
                            if(!is_null($readHTMLResponse))
                            {
                                $pdfFound = false;
                                $response = json_decode($readHTMLResponse->getContent()) ;
                                if (is_array($response)) {
                                    foreach ($response as $pdf) {
                                        if (isset($pdf->base_url, $pdf->href, $pdf->href_params)) {
                                            $pdf_url = $pdf->base_url.$pdf->href."?".$pdf->href_params;
                                            $pdfGenerationResponse = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false)['response'];
                                            if (!preg_match("/^%PDF-/", $pdfGenerationResponse)) {
                                                continue;
                                            }
                                            $pdfFound = true;
                                            break;
                                        }
                                    }
                                }

                                if (!$pdfFound) {
                                    return response()->json([
                                        'status' => false,
                                        'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                    ]);
                                }
                    
                            }else
                            {
                                return response()->json([
                                    'status' => false,
                                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                    'pdf_name' => $pdf_name ?? null
                                ]);
                            }
                        }else
                        {
                            return response()->json([
                                'status' => false,
                                'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                                'pdf_name' => $pdf_name ?? null
                            ]);  
                        }
                    }

                    if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($pdf_name))
                    {
                       Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($pdf_name);
                    }
                    Storage::put($pdf_name, $pdfGenerationResponse);

                    PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id ], [
                        'pdf_url' => $pdf_name,
                    ]);
                    $status = true;
                    $message = STAGE_NAMES['POLICY_ISSUED'];

                } catch (\Throwable $e) {
                    // Log PDF generation fail error
                }
            }
        }
        if($is_rehit === 'Y') {
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => $message
            ]);
        }

        return response()->json([
            'status' => $status,
            'msg' => $message,
            'pdf_name' => $pdf_name ?? null
        ]);
    }
    public static function updatePaymentStatus($user_product_journey_id) {
        $payment_records = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->get();
        foreach($payment_records as $k => $row) {
            if(empty($row->order_id)) {
                continue;
            }
            $payment_status_check = [
                'input' =>   [
                    'attribute1' =>  '',
                    'attribute2' =>  '',
                    'attribute3' =>  '',
                    'contractType' => 'TWP',
                    'messageId' => '',
                    'partnerCode' => config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
                    'uniqueQuoteId' => $row->order_id
                ]
            ];
        
            $get_response = getWsData(config('constants.IcConstants.iffco_tokio.ENDPOINT_PAYMENT_STATUS_CHECK'),
                $payment_status_check,
                'iffco_tokio',
                [
                    'root_tag' => 'getPolicyStatus',
                    'enquiryId' => $user_product_journey_id,
                    'requestMethod' => 'post',
                    'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:prem="http://premiumwrapper.motor.itgi.com"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                    'section' => 'Bike',
                    'method' => 'Payment Status',
                    'company' => 'iffco_tokio',
                    'productName' => 'Bike Insurance',
                    'transaction_type' => 'proposal',
                ]
            );
            $data = $get_response['response'];
            if($data) {
                $payment_response = XmlToArray::convert($data);
                if(!isset($payment_response['soapenv:Body']['getPolicyStatusResponse']['ns1:getPolicyStatusReturn'])) {
                    throw new Exception('Iffco Payment Status : Incorrect data received. Unable to parse the API response.');
                }
                $payment_response = $payment_response['soapenv:Body']['getPolicyStatusResponse']['ns1:getPolicyStatusReturn'];
                // Payment is done only when authFlag is 'Y'
                if($payment_response['ns1:authFlag'] == 'Y') {
                    // Mark all the Transactions as Payment Initiated
                    PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->update([
                        'active'  => 0
                    ]);
                    // Then Mark single Transaction as Payment Success
                    PaymentRequestResponse::where([
                        'user_product_journey_id' => $user_product_journey_id,
                        'id' => $row->id
                    ])->update([
                        'active'  => 1,
                        'status' => STAGE_NAMES['PAYMENT_SUCCESS']
                    ]);
                    if(!is_array($payment_response['ns1:policyNo'])) {    
                        UserProposal::where('user_product_journey_id', $user_product_journey_id)->update([
                            'policy_no' => $payment_response['ns1:policyNo']
                        ]);
                        PolicyDetails::updateOrCreate(['proposal_id' => $row->user_proposal_id ], 
                        [
                            'policy_number' => $payment_response['ns1:policyNo'],
                            'status' => 'SUCCESS',
                            'premium' => $row->amount,
                        ]);
                        $arr = [
                            'TWP', //Product Type
                            $payment_response['ns1:traceNo'],
                            $payment_response['ns1:policyNo'],
                            $payment_response['ns1:amount'],
                            is_array($payment_response['ns1:status']) ? "Status Manually added by system" : $payment_response['ns1:status'],
                            $row->order_id
                        ];
                        $custom_payment_response = [
                            'ITGIResponse' => implode('|', $arr),
                            'message' => 'Payment response fetched via policy status API'
                        ];
                        PaymentRequestResponse::where([
                                'user_product_journey_id' => $user_product_journey_id,
                                'id' => $row->id
                            ])->update([
                                'active'  => 1,
                                'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'response' => json_encode($custom_payment_response)
                        ]);
                        return true;
                    } else {
                        throw new Exception('Iffco Payment Status : Payment is success, but Policy number is blank');
                    }
                }
            } else {
                throw new Exception('Iffco Payment Status API is not working as expected. Please try again.');
            }
        }
        return false;
    }
}