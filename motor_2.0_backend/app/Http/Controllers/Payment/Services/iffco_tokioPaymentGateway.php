<?php

namespace App\Http\Controllers\Payment\Services;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use Illuminate\Support\Str;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Http\Controllers\Payment\Services\IffcoTokioshortTermPaymentGateway as ITSHORTTERM;
use Exception;
use App\Jobs\GeneratePDFJob;
use App\Models\iffco_tokioFinancierMaster;
use App\Models\WebServiceRequestResponse;

class iffco_tokioPaymentGateway
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function make($request)
    {
        if(config("IFFCO_PCV_GCV_ONLY_UAT") == 'Y')
        {
            return self::makeGcv($request);
        }
        $proposal_data = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        if (!$proposal_data) {
            return [
                'status' => false,
                'msg' => 'Proposal data not found',
            ];
        }
        $quote_log_id = QuoteLog::where('user_product_journey_id', $proposal_data->user_product_journey_id)
            ->pluck('quote_id')
            ->first();
        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal_data->user_product_journey_id)
            ->update(['active' => 0]);
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        //$additional_covers = json_decode($selected_addons->additional_covers)
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        //$is_zero_dep_product = $productData->zero_dep == '0';

        $reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));

        $rto_code = $requestData->rto_code;
        $city_name = DB::table('master_rto as mr')
            ->where('mr.rto_number', $rto_code)
            ->select('mr.*')
            ->first();

        /* $rto_data = DB::table('iffco_tokio_city_master as ift')
            ->where('rto_city_name', $city_name->rto_name)
            ->select('ift.*')->first(); */

        $rto_cities = explode('/',  $city_name->rto_name);
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
        }
        $city = DB::table('iffco_tokio_address_city_master as city_code')
            ->where('CITY_DESC', Str::upper($proposal_data->city))
            ->select('city_code.*')->first();
        $city = keysToLower($city);

        if(empty($city)) {
            return [
                'status' => false,
                'message' => 'City Code not found for city : ' . $proposal_data->city,
            ];
        }

        $city = array_change_key_case((array) $city, CASE_LOWER);
        $city = json_decode(json_encode($city));

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        if(in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']))
        {
            return ITSHORTTERM::make($request);
        }

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false;

        #-------------tempaory adding hardcored mmv for testing until GCV mapping completed ---------------------
        $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
        if ($is_GCV) {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio', $requestData->gcv_carrier_type);
        }else{
            $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
        }       
        #$mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
    #------------------end   ---------------

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => '0',
                'status' => false,
                'message' => $mmv['message'],
            ];
        }

        $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);

        $additional_details_data = json_decode($proposal_data->additional_details_data);

        $payment_url = Config('constants.cv.iffco.PAYMENT_GATEWAY_LINK_IFFCO_TOKIO_PCV');
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
            if (config('constants.motorConstant.IS_POS_ENABLED_CV_IFFCO_TESTING') == 'Y') {
                $testing_pos_pan = config('constants.motorConstant.IFFCO_TESTING_POS_PAN');
                $pospPanNumber = !empty($testing_pos_pan) ? $testing_pos_pan : 'ALXPB1224DF';
            }
        }else if (config('constants.motorConstant.IS_POS_ENABLED_CV_IFFCO_TESTING') == 'Y') {
            $testing_pos_pan = config('constants.motorConstant.IFFCO_TESTING_POS_PAN');
            $partnerType = 'POS';
            $pospPanNumber = !empty($testing_pos_pan) ? $testing_pos_pan : 'ALXPB1224DF';
        }

        if(config('IS_IFFCO_CV_NON_POS') == 'Y') {
            $partnerType = '';
            $pospPanNumber = '';
        }
        $is_pa_cover_owner_driver = 'N';
        $ncb_RenewalPolicy = 'N';

        $is_pa_cover_owner_driver = 'N';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                    $is_pa_cover_owner_driver = 'Y';
                } elseif (isset($data['reason']) && $data['reason'] != "") {
                    if ($data['reason'] == 'I do not have a valid driving license.') {
                        $cpa_reason = 'true';
                    }
                }
            }
        }
        if ($proposal_data->ncb_discount != null) {
            $ncb_RenewalPolicy = 'Y';
            $ncb_discount = "-" . $proposal_data->ncb_discount;
        }
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $is_zero_dep = $consumables = 'N';
        if ($vehicle_age < 7) { // Less than 7 i.e. upto 6 and including 6
            foreach ($addons as $key => $value) {
                if (in_array('Zero Depreciation', $value) && $value['premium'] > 0) {
                    $is_zero_dep = 'Y';
                }
                if (in_array('Consumable', $value) && $value['premium'] > 0) {
                    $consumables = 'Y';
                    $is_zero_dep = 'Y';
                }
            }
        }

        $voluntary_insurer_discounts = '0';
        $motor_anti_theft = 'N';
        $tppd_cover = '750000';
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                $voluntary_insurer_discounts = $value['sumInsured'];
            }
            if (in_array('anti-theft device', $value)) {
                $motor_anti_theft = 'Y';
            }
            if ($premium_type != 'own_damage') {
                if (in_array('TPPD Cover', $value)) {
                    $tppd_cover = '6000';
                }
            }

        }
        $motor_lpg_cng_kit = 0;
        $motor_non_electric_accessories = 0;
        $motor_electric_accessories = 0;
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
        $proposal_data->cpa_premium == 0 ? $is_pa_cover_owner_driver = 'N' : $is_pa_cover_owner_driver = 'Y';
        $un_named = '';
        $IsLegalLiabilityDriver = 'N';
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $IsLegalLiabilityDriver = 'Y';
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $un_named = $data['sumInsured'];
                }
            }
        }
        $Coverage_idv_basic = [
            'Code' => 'IDV Basic',
            'SumInsured' => (!$tp_only) ? $proposal_data->idv : '1',
            'Number' => '',
            'ODPremium' => (!$tp_only) ? $additional_details_data->idv_basic_od : '0',
            'TPPremium' => $additional_details_data->tp_premium,
        ];
        $Coverage_no_claim_bonus = [
            'Code' => 'No Claim Bonus',
            'SumInsured' => $additional_details_data->NCB_RenewalPolicy,
            'Number' => '',
            'ODPremium' => $ncb_discount,
            'TPPremium' => 0,
        ];
        /* $Coverage_Electrical_Accessories = [
        'Code' => 'Electrical Accessories',
        'SumInsured' => $motor_electric_accessories,
        'Number' => '',
        'ODPremium' => $additional_details_data->electrical_accessories,
        'TPPremium' => 0,
        ];
        $Coverage_non_elc_accessories = [
        'Code' => 'Cost of Accessories',
        'SumInsured' => $motor_non_electric_accessories,
        'Number' => '',
        'ODPremium' => $additional_details_data->non_electrical_accessories,
        'TPPremium' => 0,
        ]; */
        $Coverage_CNG_Kit = [
            'Code' => 'CNG Kit',
            'SumInsured' => $motor_lpg_cng_kit,
            'Number' => '',
            'ODPremium' => $additional_details_data->LPG_CNG_Kit,
            'TPPremium' => $additional_details_data->LPG_CNG_KIT_TP,
        ];
        $Coverage_pa_owner_driver = [
            'Code' => 'PA Owner / Driver',
            'SumInsured' => $is_pa_cover_owner_driver,
            'Number' => '',
            'ODPremium' => '0.0',
            'TPPremium' => $additional_details_data->cpa_premium,
        ];
        /* $Coverage_Legal_Liability_Driver = [
        'Code' => 'Legal Liability to Driver',
        'SumInsured' => $IsLegalLiabilityDriver,
        'Number' => '',
        'ODPremium' => 0,
        'TPPremium' => $additional_details_data->legalLiability_to_driver,
        ];
        $Coverage_PA_to_Passenger = [
        'Code' => 'PA to Passenger',
        'SumInsured' => $un_named,
        'Number' => '',
        'ODPremium' => 0,
        'TPPremium' => $additional_details_data->pa_unnamed,
        ]; */
        $Coverage = [
            $Coverage_idv_basic, $Coverage_no_claim_bonus, //$Coverage_Legal_Liability_Driver,$Coverage_PA_to_Passenger,
        ];
        if ($motor_lpg_cng_kit > 0) {
            array_push($Coverage, $Coverage_CNG_Kit);
        }
        if ($additional_details_data->zero_dep_value > 0) {
            array_push($Coverage, [
                'Code' => 'Depreciation Waiver',
                'SumInsured' => $is_zero_dep,
                'Number' => '',
                'ODPremium' => $additional_details_data->zero_dep_value,
                'TPPremium' => 0,
            ]);
        }
        if ($additional_details_data->consumable > 0) {
            array_push($Coverage,[
                'Code' => 'Consumable',
                'SumInsured' => $consumables,
                'Number' => '',
                'ODPremium' => $additional_details_data->consumable,
                'TPPremium' => 0,
            ]);
        }
        if ($additional_details_data->cng_internal_OdPremium > 0 || $additional_details_data->cng_internal_TpPremium > 0) {
            array_push($Coverage,[
                'Code' => 'CNG Kit Company Fit',
                'SumInsured' => 'Y',
                'Number' => '',
                'ODPremium' => $additional_details_data->cng_internal_OdPremium,
                'TPPremium' => $additional_details_data->cng_internal_TpPremium,
            ]);
        }
        if ($proposal_data->owner_type == 'I') {
            array_push($Coverage, $Coverage_pa_owner_driver);
        }
        foreach ($Coverage as $k => $v) {
            if ($premium_type == 'own_damage') {
                if ($v['Code'] == 'PA Owner / Driver') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Legal Liability to Driver') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'PA to Passenger') {
                    unset($Coverage[$k]);
                }
            } else if ($tp_only) {
                if ($v['Code'] == 'Electrical Accessories') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Cost of Accessories') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'No Claim Bonus') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Depreciation Waiver') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Towing & Related') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Consumable') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Anti-Theft') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'AAI Discount') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Voluntary Excess') {
                    unset($Coverage[$k]);
                }
            } else if ($requestData->business_type == 'newbusiness') {
                if ($v['Code'] == 'No Claim Bonus') {
                    unset($Coverage[$k]);
                }
            }
        }
        $Coverages = array_values($Coverage);
        $policy_expiry_date = $requestData->previous_policy_expiry_date;
        $BreakInofMorethan90days = 'N';
        $isNewVehicle = 'N';
        $is_breakin = false;
        if ($requestData->business_type == 'newbusiness') {
            $inceptionDate = date('m/d/Y 00:00:00');
            $policy_expiry_date = $ppenddate = $ppstartdate = '';
            $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day'));
            $isNewVehicle = 'Y';
        } else {
            $date_diff = get_date_diff('day', strtolower($policy_expiry_date) == 'new' ? date('Y-m-d') : $policy_expiry_date);
            if ($date_diff > 0 || $requestData->business_type == 'breakin') {
                $is_breakin = true;
                $inceptionDate = date('m/d/Y 00:00:00');
                if($tp_only)
                {
                    $inceptionDate = date('m/d/Y 00:00:00', strtotime('+3 day'));
                }
                $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day', strtotime($inceptionDate)));
            } else {
                $inceptionDate = date('m/d/Y 00:00:00', strtotime('+1 day', strtotime($policy_expiry_date)));
                $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day', strtotime($inceptionDate)));
            }
            if ($date_diff > 90 || strtolower($policy_expiry_date) == 'new') {
                $BreakInofMorethan90days = 'Y';
                $policy_expiry_date = $ppenddate = $ppstartdate = '';
            }else {
                $ppenddate = date("m/d/Y 23:59:59", strtotime($policy_expiry_date));
                $ppstartdate = date('m/d/Y 00:00:00', strtotime('-1 year +1 day', strtotime($ppenddate)));
            }
            //}
        }
        $length = 13;
        $unique_quote = mt_rand(pow(10,($length-1)),pow(10,$length)-1);
        //$unique_quote = $additional_details_data->unique_quote;
        
        $policy_created_date = date("m/d/Y 00:00:00");
        if ($tp_only) {
            $total_sum_insured = $additional_details_data->total_ci + 1;
        } else {
            $total_sum_insured = $additional_details_data->total_sum_insured;
        }
        // -- Driving Licence and Alternate PA Cover Condition Start --
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
        // -- Driving Licence and Alternate PA Cover Condition End --
        $Policy = [
            'CreatedDate' => $policy_created_date, // Policy Start Date
            'ExpiryDate' => $policy_end_date, // // Policy End Date
            "ExternalBranch" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_BRANCH'),
            "ExternalServiceConsumer" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
            //'ExternalSubBranch' => Config('constants.IcConstants.iffco_tokio.partnerSubBranchCar'),
            'GeneralPage' => '',
            'GrossPremium' => $additional_details_data->OdSumDisLoad, //$proposal_data->total_premium,
            'InceptionDate' => $inceptionDate, //$policy_created_date,
            'NetPremiumPayable' => $proposal_data->final_payable_amount,
            'OdDiscountAmt' => $additional_details_data->OdDiscountAmt,
            'OdDiscountLoading' => $additional_details_data->OdDiscountLoading,
            'OdSumDisLoad' => $additional_details_data->total_od,
            'BreakInofMorethan90days' => $BreakInofMorethan90days,
            'PreviousPolicyEnddate' => $ppenddate,
            'PreviousPolicyStartdate' => $ppstartdate,
            'PreviousPolicyInsurer' => $proposal_data->previous_insurance_company,
            'PreviousPolicyNo' => $proposal_data->previous_policy_number,
            'Product' => 'CVI',
            'ServiceTax' => $proposal_data->service_tax_amount,
            'TotalSumInsured' => $total_sum_insured,
            'TpSumDisLoad' => $additional_details_data->totalTpPremium,
            'UniqueQuoteId' => $unique_quote,
            'PartnerType' => $partnerType,
            'POSPanNumber' => $pospPanNumber,
        ];
        $breakin_status = CvBreakinStatus::where('user_proposal_id', $proposal_data->user_proposal_id)->first();             

        if($is_breakin && !$tp_only)
        {
            if(empty($breakin_status))
            {
                return [
                    'status' => false,
                    'message' => 'Breakin details not Found'
                ];
            }
    
            $inspectionResponse =  json_decode($breakin_status->breakin_response, true);

            if(empty($inspectionResponse) || !isset($inspectionResponse['data']['result'][0]['updatedAt']))
            {
                return [
                    'status' => false,
                    'message' => 'Post inspection details not Found'
                ];
            }

            $breakinupdatedAt = $inspectionResponse['data']['result'][0]['updatedAt'];
    
            if(Carbon::now() > Carbon::parse($breakinupdatedAt)->addDay(3))
            {
                return [
                    'status' => false,
                    'message' => 'Payment is Expired'
                ];
            }
        }
        
        if(($breakin_status != NULL) && ($breakin_status->breakin_status == STAGE_NAMES['INSPECTION_APPROVED'])) {
            $Policy['InspectionDate']   = date('m/d/Y',strtotime($breakin_status->inspection_date));
            $Policy['InspectionNo']     = $breakin_status->breakin_number;
            $Policy['InspectionAgency'] = 'LiveChek';
            $Policy['InspectionStatus'] = 'APPROVED';
        }

        if ($proposal_data->owner_type == 'I') {
            $Policy['Nominee'] = $proposal_data->nominee_name;
            //$Policy['NomineeRelationship'] = $proposal_data->nominee_relationship == 'Brother' ? 'Unmarried Brother' : $proposal_data->nominee_relationship;
            $Policy['NomineeRelationship'] = $proposal_data->nominee_relationship;
        } else {
            $Policy['CorporateClient'] = 'Y';
        }
        
        if (in_array($premium_type, ['comprehensive', 'breakin'])) {
            $policy_type = 'CP';
        } else if ($tp_only) {
            $policy_type = 'TP';
        } else if ($premium_type == 'own_damage') {
            $policy_type = 'OD';
            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                $Policy['TpInceptionDate'] = $proposal_data->tp_start_date; //$tp_start_date;
                $Policy['TpExpiryDate'] = $proposal_data->tp_end_date; //$tp_end_date;
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
        $reg_no = explode('-', $proposal_data->vehicale_registration_number);
        if ($reg_no[0] == 'DL') {
            if (strlen($reg_no[2]) == 3) {
                $reg_no[1] = ltrim($reg_no[1], '0') . substr($reg_no[2], 0, 1);
                $reg_no[2] = substr($reg_no[2], 1);
            }
        }

        $Vehicle = [
            'AAIExpiryDate' => '',
            'AAINumber' => '',
            'Capacity' => $mmv_data['seating_capacity'],
            'ChassisNumber' => $proposal_data->chassis_number,
            'EngineCapacity' => $mmv_data['cc'] ?? null, //$mmv_data['data']['cubic_capacity'],
            'EngineNumber' => $proposal_data->engine_number,
            'GrossVehicleWeight' => $mmv_data['grossVehicleWt'] ?? null,
            'ImposedExcessPartialLoss' => '',
            'ImposedExcessTotalLoss' => '',
            'Make' => $mmv_data['make_code'], //$mmv_data['data']['make_code'],
            'ManufacturingYear' => $yearOfManufacture,
            'PolicyType' => ($isNewVehicle == 'Y') ? 'BP' : $policy_type,
            'RegistrationDate' => $reg_date,
            'RegistrationNumber1' => ($isNewVehicle == 'N') ? $reg_no[0] : 'NEW',
            'RegistrationNumber2' => ($isNewVehicle == 'N') ? $reg_no[1] : '',
            'RegistrationNumber3' => ($isNewVehicle == 'N') ? $reg_no[2] : '',
            'RegistrationNumber4' => ($isNewVehicle == 'N') ? $reg_no[3] : '0001',
            'RiskOccupationCode' => '',
            'RTOCity' => $rto_data->rto_city_code,
            'SeatingCapacity' => $mmv_data['seating_capacity'], //$mmv_data['data']['seating_capacity'],
            'VehicleBody' => '',
            'Zcover' => $tp_only ? 'AC' : 'CO',
            'Zone' => $rto_data->irda_zone,
            'ValidDrivingLicence' => $ValidDrivingLicence,
            'AlternatePACover' => $AlternatePACover,
            'NewVehicleFlag' => $isNewVehicle
        ];

        // insured prefix
        $salutation = '';
        if ($requestData->vehicle_owner_type == 'I') {
            if ($proposal_data->gender == "M") {
                $salutation = 'MR';
            } else {
                if ($proposal_data->gender == "F" && $proposal_data->marital_status == "Single") {
                    $salutation = 'MRS';
                } else {
                    $salutation = 'MS';
                }
            }
        }
        //$salutation = (isset($proposal_data->gender) ? (($proposal_data->gender) == 'M' ? 'MR' : 'MRS') : '');

        $dob = date("m/d/Y", strtotime($proposal_data->dob));
        $address_data = [
                'address' => $proposal_data->address_line1,
                'address_1_limit'   => 30,
                'address_2_limit'   => 30,
                'address_3_limit'   => 30,
                'address_4_limit'   => 30
            ];
        $getAddress = getAddress($address_data);
        
        if($proposal_data->owner_type == 'I' && ($proposal_data->last_name === null || $proposal_data->last_name == ''))
        {
            $proposal_data->last_name = '.';
        }

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
            'PAN' => $proposal_data->pan_number,
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
            'InsuredPAN' => $proposal_data->pan_number,
            'InsuredAadhar' => '',
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
            'PAN' => $proposal_data->pan_number,
            'PrimaryAccountCity' => $proposal_data->owner_type == 'I' ? '' : $city->city_code,
            'PrimaryAccountCountry' => $proposal_data->owner_type == 'I' ? '' : 'IND',
            'PrimaryAccountPostalCode' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->pincode,
            'PrimaryAccountState' => $proposal_data->owner_type == 'I' ? '' : $rto_data->state_code,
            'PrimaryAccountStreetAddress' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_1'],
            'PrimaryAccountStreetAddress2' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_2'],
            'PrimaryAccountStreetAddress3' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_3'],
            'PrimaryAccountStreetAddress4' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_4'],
            'AccountGSTIN' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->gst_number,
        ];

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
        
        if ($proposal_data->owner_type == 'C') {
            $Account['DOB'] = date("m/d/Y", strtotime($proposal_data->dob));
        }
        $proposal_xml_request = [
            'Policy' => $Policy,
            'Coverage' => $Coverages,
            'Vehicle' => $Vehicle,
            'Contact' => $Contact,
            'Account' => $Account,
            'VehicleThirdParty' => $VehicleThirdParty,
        ];

        $proposal_xml_request = ArrayToXML::convert($proposal_xml_request, 'Request');
        $proposal_xml_request = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $proposal_xml_request);
        $proposal_xml_request = htmlspecialchars_decode($proposal_xml_request);
        $data = [
            'xml_data' => trim($proposal_xml_request),
            'proposal_no' => $unique_quote,
            'QuoteId' => $unique_quote,
        ];
        $proposal_data->unique_quote = $proposal_data->proposal_no = $proposal_data->unique_proposal_id = $unique_quote;
        $proposal_data->save();
        //return $data['xml_data'];
        // return [
        //     'status'  => 'true',
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
            'return_url' => route('cv.payment-confirm', ['iffco_tokio', 'user_proposal_id' => $proposal_data->user_proposal_id, 'policy_id' => $request->policyId]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'xml_data' => $data['xml_data'],
            'active' => 1,
        ]);

        $return_data = [
            'form_action' => $payment_url,
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'RESPONSE_URL' => route('cv.payment-confirm', ['iffco_tokio']),
                'PARTNER_CODE' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
                'UNIQUE_QUOTEID' => $data['QuoteId'],
                'XML_DATA' => $data['xml_data'],
                'enquiry_id' => $enquiryId,
            ],
        ];
        updateJourneyStage([
            'user_product_journey_id' => $proposal_data->user_product_journey_id,
            'ic_id' => $proposal_data->company_id,
            'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
        ]);
        $data_u['user_product_journey_id'] = $proposal_data->user_product_journey_id;
        $data_u['ic_id'] = $proposal_data->ic_id;
        $data_u['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data_u);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);

    }

    public static function confirm($request)
    {

        $response = explode('|', $request->ITGIResponse);
        $product = $response[0];
        $TraceNumber = $response[1];
        $policy = $response[2];
        $NetPremiumPayable = $response[3];
        $message = $response[4];
        $QuoteId = $response[5];
        #$success_callback = config('constants.motorConstant.CV_PAYMENT_SUCCESS_CALLBACK_URL');
        #$failure_callback = config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL');

        // SHORT TERM CONDITION
        $proposal = UserProposal::where('proposal_no', $TraceNumber)
            ->orderBy('user_proposal_id', 'desc')
            ->first();
        if(!empty($proposal) && $proposal->policy_type == 'short_term')
        {
            return ITSHORTTERM::confirm($request);
        }
        // ENDSHORT TERM CONDITION
        //here we are fetching  UserProposal table data 2 times because condition for checking if policy is annual or short term is different

        $user_proposal = UserProposal::where('unique_quote', $QuoteId)
            ->orderBy('user_proposal_id', 'desc')
            ->select('*')
            ->first();

        $success_callback = paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CV','SUCCESS');
        $failure_callback = paymentSuccessFailureCallbackUrl($user_proposal->user_product_journey_id,'CV','FAILURE');
        if (!$user_proposal) {
            return redirect(config('constants.motorConstant.CV_PAYMENT_FAILURE_CALLBACK_URL'));
        }
        if (isset($policy) && $message != "Transaction_Declined") {
            sleep(5); //Need to apply delay for PDF generation
            DB::table('payment_request_response')
                ->where('order_id', $QuoteId)
                ->where('active', 1)
                ->update([
                    'response' => $request->All(),
                    'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                ]);
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => $policy != "" ? STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] : STAGE_NAMES['PAYMENT_SUCCESS'],
            ]);
            PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id], [
                'policy_number' => $policy,
            ]);
            $user_proposal->policy_no = $policy;
            $user_proposal->save();

            GeneratePDFJob::dispatch('iffco_tokio','cv', $user_proposal)->delay(now()->addMinutes(5));

            return redirect($success_callback);
        } else {
            updateJourneyStage([
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'stage' => STAGE_NAMES['PAYMENT_FAILED'],
            ]);
            return redirect($failure_callback);
        }

    }
    // End confirm method

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
                    'line' => $e->getLine() .' '. $e->getFile(),
                    'pdf_name' => null
                ]);
            }
        }
        $request_array = [
            'contractType' => 'CVI',
            'policyDownloadNo' => $user_proposal->policy_no,
            'partnerDetail' => [
                'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE')
            ]
        ];

        if ($is_rehit === 'N') { sleep(5); } //Need to apply delay for PDF generation

        $u = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE');
        $p = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD');
        $get_response = getWsData(
            config('constants.cv.iffco_tokio.END_POINT_URL_IFFCO_TOKIO_MOTOR_PDF'),
            $request_array,
            'iffco_tokio',
            [
                'requestMethod' =>'post',
                'company'  => 'iffco_tokio',
                'requestType' => 'json',
                'productName'  => '',
                'section' => 'CV',
                'method' => 'PDF Generation',
                'enquiryId' => $user_proposal->user_product_journey_id,
                'transaction_type' => 'proposal',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("$u:$p"),
                    'Accept' => 'application/json',
                    'Content-Length' => strlen(json_encode($request_array)),
                ],
            ]
        );
        $data = $get_response['response'];

        if (!empty($data)) {
            $data = (array) json_decode($data);
            if (isset($data['statusMessage']) && strtoupper($data['statusMessage']) == 'SUCCESS') {
                $pdf_url = $data['policyDownloadLink'];
                $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL').'iffco_tokio/' .  md5($user_proposal->user_proposal_id). '.pdf';
                PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id ], [
                    'ic_pdf_url' => $pdf_url,
                ]);
                try {


                    $pdfGenerationResponse = httpRequestNormal($pdf_url, 'GET', [], [], [], [], false)['response'];

                    if(!preg_match("/^%PDF-/", $pdfGenerationResponse))
                    {
                        //GeneratePDFJob::dispatch('iffco_tokio','cv', $user_proposal)->delay(now()->addMinutes(7));
                        
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

    public static function generatePdf($request) {
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        if (!empty($proposal) && $proposal->policy_type == 'short_term') {
            return ITSHORTTERM::generatePdf($request);
        }
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
        // Error message pop-up will be only shown 'status' is boolean false - @Hrishikesh : 15-09-2022
        if ($generate_pdf['status']) $return_data = array_merge($return_data, $data);

        return response()->json($return_data);
    }
    public static function updatePaymentStatus($user_product_journey_id) {
        $payment_records = PaymentRequestResponse::where('user_product_journey_id', $user_product_journey_id)->get();
        foreach($payment_records as $k => $row) {
            if(empty($row->order_id)) {
                continue;
            }
            $payment_status_check = [
                "soapenv:Header" => [],
                "soapenv:Body" => [
                    "getPolicyStatus" => [
                        'input' =>   [
                            'attribute1' =>  '',
                            'attribute2' =>  '',
                            'attribute3' =>  '',
                            'contractType' => 'CVI',
                            'messageId' => '',
                            'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
                            'uniqueQuoteId' => $row->order_id
                        ]
                    ]
                ]
            ];
            $root = [
                'rootElementName' => 'soapenv:Envelope',
                '_attributes' => [
                    "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
                    "xmlns:prem" => "http://premiumwrapper.motor.itgi.com",
                ],
            ];
            $payment_status_check = ArrayToXml::convert($payment_status_check, $root, false, 'utf-8');
            $get_response = getWsData(config('constants.IcConstants.iffco_tokio.ENDPOINT_PAYMENT_STATUS_CHECK'),
                $payment_status_check,
                'iffco_tokio',
                [
                    'enquiryId' => $user_product_journey_id,
                    'requestMethod' => 'post',
                    'requestType' => 'xml',
                    'headers' =>  [
                        'SOAPAction' => 'http://schemas.xmlsoap.org/soap/envelope/',
                        'Content-Type' => 'text/xml; charset="utf-8"',
                    ],
                    'section' => 'CV',
                    'method' => 'Payment Status',
                    'company' => 'iffco_tokio',
                    'productName' => 'CV Insurance',
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
                            'PCP', //Product Type
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
    
    public static function makeGcv($request)
    {
        $proposal_data = UserProposal::where('user_product_journey_id', customDecrypt($request['userProductJourneyId']))->first();
        if (!$proposal_data) {
            return [
                'status' => false,
                'msg' => 'Proposal data not found',
            ];
        }
        $quote_log_id = QuoteLog::where('user_product_journey_id', $proposal_data->user_product_journey_id)
            ->pluck('quote_id')
            ->first();
        $icId = MasterPolicy::where('policy_id', $request['policyId'])
            ->pluck('insurance_company_id')
            ->first();

        DB::table('payment_request_response')
            ->where('user_product_journey_id', $proposal_data->user_product_journey_id)
            ->update(['active' => 0]);
        $enquiryId = customDecrypt($request['userProductJourneyId']);
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        //$additional_covers = json_decode($selected_addons->additional_covers)
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $is_zero_dep_product = $productData->zero_dep == '0';

        $reg_date = date('m/d/Y', strtotime($requestData->vehicle_register_date));

        $rto_code = $requestData->rto_code;
        $city_name = DB::table('master_rto as mr')
            ->where('mr.rto_number', $rto_code)
            ->select('mr.*')
            ->first();

        /* $rto_data = DB::table('iffco_tokio_city_master as ift')
            ->where('rto_city_name', $city_name->rto_name)
            ->select('ift.*')->first(); */

        $rto_cities = explode('/',  $city_name->rto_name);
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
        }
        $city = DB::table('iffco_tokio_address_city_master as city_code')
            ->where('CITY_DESC', Str::upper($proposal_data->city))
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

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        if(in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']))
        {
            return ITSHORTTERM::make($request);
        }

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false;

        #-------------tempaory adding hardcored mmv for testing until GCV mapping completed ---------------------
        $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
        if ($is_GCV) {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio', $requestData->gcv_carrier_type);
        }else{
            $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
        }       
        #$mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
    #------------------end   ---------------

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => '0',
                'status' => false,
                'message' => $mmv['message'],
            ];
        }

        $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);

        $additional_details_data = json_decode($proposal_data->additional_details_data);

        $payment_url = Config('constants.cv.iffco.PAYMENT_GATEWAY_LINK_IFFCO_TOKIO_PCV');
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
        }else if (config('constants.motorConstant.IS_POS_ENABLED_CV_IFFCO_TESTING') == 'Y') {
            $testing_pos_pan = config('constants.motorConstant.IFFCO_TESTING_POS_PAN');
            $partnerType = 'POS';
            $pospPanNumber = !empty($testing_pos_pan) ? $testing_pos_pan : 'ALXPB1224DF';
        }

        if(config('IS_IFFCO_CV_NON_POS') == 'Y') {
            $partnerType = '';
            $pospPanNumber = '';
        }
        $is_pa_cover_owner_driver = 'N';
        $ncb_RenewalPolicy = 'N';

        $is_pa_cover_owner_driver = 'N';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                    $is_pa_cover_owner_driver = 'Y';
                } elseif (isset($data['reason']) && $data['reason'] != "") {
                    if ($data['reason'] == 'I do not have a valid driving license.') {
                        $cpa_reason = 'true';
                    }
                }
            }
        }
        if ($proposal_data->ncb_discount != null) {
            $ncb_RenewalPolicy = 'Y';
            $ncb_discount = "-" . $proposal_data->ncb_discount;
        }
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $is_zero_dep = $consumables = $Road_Side_Assistance = $include_IMT = 'N';
        if ($vehicle_age < 7) { // Less than 7 i.e. upto 6 and including 6
            foreach ($addons as $key => $value) {
                if (in_array('Zero Depreciation', $value) && $value['premium'] > 0) {
                    $is_zero_dep = 'Y';
                }
                else if (in_array('IMT - 23', $value) && $value['premium'] > 0) {
                    $include_IMT = 'Y';
                    $is_zero_dep = 'Y';
                }
                else if (in_array('Consumable', $value) && $value['premium'] > 0) {
                    $consumables = 'Y';
                    $is_zero_dep = 'Y';
                }
                else if (in_array('Road Side Assistance', $value) && $value['premium'] > 0) {
                    $Road_Side_Assistance = 'Y';
                    $is_zero_dep = 'Y';
                }
            }
        }

        $voluntary_insurer_discounts = '0';
        $motor_anti_theft = 'N';
        $tppd_cover = '750000';
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                $voluntary_insurer_discounts = $value['sumInsured'];
            }
            if (in_array('anti-theft device', $value)) {
                $motor_anti_theft = 'Y';
            }
            if ($premium_type != 'own_damage') {
                if (in_array('TPPD Cover', $value)) {
                    $tppd_cover = '6000';
                }
            }

        }
        $motor_lpg_cng_kit = 0;
        $motor_non_electric_accessories = 0;
        $motor_electric_accessories = 0;
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
        $proposal_data->cpa_premium == 0 ? $is_pa_cover_owner_driver = 'N' : $is_pa_cover_owner_driver = 'Y';
        $un_named = '';
        $IsLegalLiabilityDriver = 'N';
        $LLNumberSum = $LLNumberCleaner = $LLNumberDriver = $LLNumberConductor = 0;
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $IsLegalLiabilityDriver = 'Y';
                }
                else if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $un_named = $data['sumInsured'];
                }
                else if ($is_GCV && $data['name'] == 'LL paid driver/conductor/cleaner')
                {
                    $LLNumberCleaner = $data['LLNumberCleaner'] ?? 0;
                    $LLNumberDriver = $data['LLNumberDriver'] ?? 0;
                    $LLNumberConductor = $data['LLNumberConductor'] ?? 0;
                    $IsLegalLiabilityDriver = 'Y';
                }
            }
        }
        $LLNumberSum = $LLNumberCleaner + $LLNumberDriver + $LLNumberConductor;
        $Coverage_idv_basic = [
            'Code' => 'IDV Basic',
            'SumInsured' => (!$tp_only) ? $proposal_data->idv : '1',
            'Number' => '',
            'ODPremium' => (!$tp_only) ? $additional_details_data->idv_basic_od : '0',
            'TPPremium' => $additional_details_data->tp_premium,
        ];
        $Coverage_no_claim_bonus = [
            'Code' => 'No Claim Bonus',
            'SumInsured' => $additional_details_data->NCB_RenewalPolicy,
            'Number' => '',
            'ODPremium' => $ncb_discount,
            'TPPremium' => 0,
        ];
        if($is_GCV)
        {
            if($motor_electric_accessories > 0)
            {
                $Coverage_Electrical_Accessories = [
                    'Code' => 'Electrical Accessories',
                    'SumInsured' => $motor_electric_accessories,
                    'Number' => '',
                    'ODPremium' => $additional_details_data->electrical_accessories,
                    'TPPremium' => 0,
                ];
            }
            // Not available in PCV & GCV
//            if($motor_non_electric_accessories > 0)
//            {
//                $Coverage_non_elc_accessories = [
//                    'Code' => 'Cost of Accessories',
//                    'SumInsured' => $motor_non_electric_accessories,
//                    'Number' => '',
//                    'ODPremium' => $additional_details_data->non_electrical_accessories,
//                    'TPPremium' => 0,
//                ]; 
//            }            
        }         
       
        $Coverage_CNG_Kit = [
            'Code' => 'CNG Kit',
            'SumInsured' => $motor_lpg_cng_kit,
            'Number' => '',
            'ODPremium' => $additional_details_data->LPG_CNG_Kit,
            'TPPremium' => $additional_details_data->LPG_CNG_KIT_TP,
        ];
        $Coverage_pa_owner_driver = [
            'Code' => 'PA Owner / Driver',
            'SumInsured' => $is_pa_cover_owner_driver,
            'Number' => '',
            'ODPremium' => '0.0',
            'TPPremium' => $additional_details_data->cpa_premium,
        ];
        if($is_GCV){
            if($IsLegalLiabilityDriver == 'Y')
            {
                $Coverage_Legal_Liability_Driver = [
                    'Code' => 'LL Paid Driv/Cleaner/Conductor',
                    'SumInsured' => $IsLegalLiabilityDriver,
                    'Number' => $LLNumberSum,
                    'ODPremium' => 0,
                    'TPPremium' => $additional_details_data->legalLiability_to_driver,
                ];                
            }            
        }
     
        $Coverage_PA_to_Passenger = [
            'Code' => 'PA to Passenger',
            'SumInsured' => $un_named,
            'Number' => '',
            'ODPremium' => 0,
            'TPPremium' => $additional_details_data->pa_unnamed,
        ]; 
        $Coverage = [
            $Coverage_idv_basic, $Coverage_no_claim_bonus //$Coverage_PA_to_Passenger,
        ];
        if ($motor_lpg_cng_kit > 0) {
            array_push($Coverage, $Coverage_CNG_Kit);
        }
        if ($is_GCV) {
            if($motor_electric_accessories > 0)
            {
               array_push($Coverage, $Coverage_Electrical_Accessories); 
            }            
//            if($motor_non_electric_accessories > 0)
//            {
//              array_push($Coverage, $Coverage_non_elc_accessories);
//            }
            if($IsLegalLiabilityDriver == 'Y')
            {
                array_push($Coverage, $Coverage_Legal_Liability_Driver);
            }
            
        }
        if ($additional_details_data->zero_dep_value > 0) {
            array_push($Coverage, [
                'Code' => 'Depreciation Waiver',
                'SumInsured' => $is_zero_dep,
                'Number' => '',
                'ODPremium' => $additional_details_data->zero_dep_value,
                'TPPremium' => 0,
            ]);
        }
        if ($additional_details_data->consumable > 0) {
            array_push($Coverage,[
                'Code' => 'Consumable',
                'SumInsured' => $consumables,
                'Number' => '',
                'ODPremium' => $additional_details_data->consumable,
                'TPPremium' => 0,
            ]);
        }
        if ($additional_details_data->cng_internal_OdPremium > 0 || $additional_details_data->cng_internal_TpPremium > 0) {
            array_push($Coverage,[
                'Code' => 'CNG Kit Company Fit',
                'SumInsured' => 'Y',
                'Number' => '',
                'ODPremium' => $additional_details_data->cng_internal_OdPremium,
                'TPPremium' => $additional_details_data->cng_internal_TpPremium,
            ]);
        }
        if ($is_zero_dep_product) { 
            if($additional_details_data->imt_23_premium > 0)
            {
                $Coverage[] = 
                [
                  'Code'=>'IMT 23',
                  'SumInsured'=> $include_IMT,
                  'Number'=> '',
                  'ODPremium'=>$additional_details_data->imt_23_premium,
                  'TPPremium'=> ''
                ];
            }
             
            if ($is_GCV)
            {
                if($additional_details_data->towing > 0)
                {
                    $Coverage[] = 
                    [
                      'Code'=>'Towing & Related',
                      'SumInsured'=> $Road_Side_Assistance,
                      'Number'=> '',
                      'ODPremium'=>$additional_details_data->towing,
                      'TPPremium'=> ''
                    ]; 
                }                               
            }                  
        }
        if ($proposal_data->owner_type == 'I') {
            array_push($Coverage, $Coverage_pa_owner_driver);
        }
        foreach ($Coverage as $k => $v) {
            if ($premium_type == 'own_damage') {
                if ($v['Code'] == 'PA Owner / Driver') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Legal Liability to Driver') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'PA to Passenger') {
                    unset($Coverage[$k]);
                }
            } else if ($tp_only) {
                if ($v['Code'] == 'Electrical Accessories') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Cost of Accessories') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'No Claim Bonus') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Depreciation Waiver') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Towing & Related') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Consumable') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Anti-Theft') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'AAI Discount') {
                    unset($Coverage[$k]);
                } else if ($v['Code'] == 'Voluntary Excess') {
                    unset($Coverage[$k]);
                }
            } else if ($requestData->business_type == 'newbusiness') {
                if ($v['Code'] == 'No Claim Bonus') {
                    unset($Coverage[$k]);
                }
            }
        }
        $Coverages = array_values($Coverage);
        $policy_expiry_date = $requestData->previous_policy_expiry_date;
        $BreakInofMorethan90days = 'N';
        $isNewVehicle = 'N';
        $is_breakin = false;
        if ($requestData->business_type == 'newbusiness') {
            $inceptionDate = date('m/d/Y 00:00:00');
            $policy_expiry_date = $ppenddate = $ppstartdate = '';
            $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day'));
            $isNewVehicle = 'Y';
        } else {
            $date_diff = get_date_diff('day', strtolower($policy_expiry_date) == 'new' ? date('Y-m-d') : $policy_expiry_date);
            if ($date_diff > 0 || $requestData->business_type == 'breakin') {
                $is_breakin = true;
                $inceptionDate = date('m/d/Y 00:00:00');
                if($tp_only)
                {
                    $inceptionDate = date('m/d/Y 00:00:00', strtotime('+3 day'));
                }
                $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day', strtotime($inceptionDate)));
            } else {
                $inceptionDate = date('m/d/Y 00:00:00', strtotime('+1 day', strtotime($policy_expiry_date)));
                $policy_end_date = date('m/d/Y 23:59:59', strtotime('+1 year -1 day', strtotime($inceptionDate)));
            }
            if ($date_diff > 90 || strtolower($policy_expiry_date) == 'new') {
                $BreakInofMorethan90days = 'Y';
                $policy_expiry_date = $ppenddate = $ppstartdate = '';
            }else {
                $ppenddate = date("m/d/Y 23:59:59", strtotime($policy_expiry_date));
                $ppstartdate = date('m/d/Y 00:00:00', strtotime('-1 year +1 day', strtotime($ppenddate)));
            }
            //}
        }
        $length = 13;
        $unique_quote = mt_rand(pow(10,($length-1)),pow(10,$length)-1);
        //$unique_quote = $additional_details_data->unique_quote;
        
        $policy_created_date = date("m/d/Y 00:00:00");
        if ($tp_only) {
            $total_sum_insured = $additional_details_data->total_ci + 1;
        } else {
            $total_sum_insured = $additional_details_data->total_sum_insured;
        }
        // -- Driving Licence and Alternate PA Cover Condition Start --
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
        // -- Driving Licence and Alternate PA Cover Condition End --
        $Policy = [
            'CreatedDate' => $policy_created_date, // Policy Start Date
            'ExpiryDate' => $policy_end_date, // // Policy End Date
            "ExternalBranch" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_BRANCH'),
            "ExternalServiceConsumer" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
            //'ExternalSubBranch' => Config('constants.IcConstants.iffco_tokio.partnerSubBranchCar'),
            'GeneralPage' => '',
            'GrossPremium' => $additional_details_data->OdSumDisLoad, //$proposal_data->total_premium,
            'InceptionDate' => $inceptionDate, //$policy_created_date,
            'NetPremiumPayable' => $proposal_data->final_payable_amount,
            'OdDiscountAmt' => $additional_details_data->OdDiscountAmt,
            'OdDiscountLoading' => $additional_details_data->OdDiscountLoading,
            'OdSumDisLoad' => $additional_details_data->total_od,
            //'BreakInofMorethan90days' => $BreakInofMorethan90days,
            'PreviousPolicyEnddate' => $ppenddate,
            'PreviousPolicyStartdate' => $ppstartdate,
            'PreviousPolicyInsurer' => $proposal_data->previous_insurance_company,
            'PreviousPolicyNo' => $proposal_data->previous_policy_number,
            'Product' => 'CVI',
            'ServiceTax' => $proposal_data->service_tax_amount,
            'TotalSumInsured' => $total_sum_insured,
            'TpSumDisLoad' => $additional_details_data->totalTpPremium,
            'UniqueQuoteId' => $unique_quote,
            'PartnerType' => $partnerType,
            'POSPanNumber' => $pospPanNumber,
        ];
        if($BreakInofMorethan90days == 'Y')
        {
            $Policy['BreakInofMorethan90days'] = $BreakInofMorethan90days;
        }
        $breakin_status = CvBreakinStatus::where('user_proposal_id', $proposal_data->user_proposal_id)->first();             

        if($is_breakin && !$tp_only)
        {
            if(empty($breakin_status))
            {
                return [
                    'status' => false,
                    'message' => 'Breakin details not Found'
                ];
            }
    
            $inspectionResponse =  json_decode($breakin_status->breakin_response, true);

            if(empty($inspectionResponse) || !isset($inspectionResponse['data']['result'][0]['updatedAt']))
            {
                return [
                    'status' => false,
                    'message' => 'Post inspection details not Found'
                ];
            }

            $breakinupdatedAt = $inspectionResponse['data']['result'][0]['updatedAt'];
    
            if(Carbon::now() > Carbon::parse($breakinupdatedAt)->addDay(3))
            {
                return [
                    'status' => false,
                    'message' => 'Payment is Expired'
                ];
            }
        }
        
        if(($breakin_status != NULL) && ($breakin_status->breakin_status == STAGE_NAMES['INSPECTION_APPROVED'])) {
            $Policy['InspectionDate']   = date('m/d/Y',strtotime($breakin_status->inspection_date));
            $Policy['InspectionNo']     = $breakin_status->breakin_number;
            $Policy['InspectionAgency'] = 'LiveChek';
            $Policy['InspectionStatus'] = 'APPROVED';
        }

        if ($proposal_data->owner_type == 'I') {
            $Policy['Nominee'] = $proposal_data->nominee_name;
            //$Policy['NomineeRelationship'] = $proposal_data->nominee_relationship == 'Brother' ? 'Unmarried Brother' : $proposal_data->nominee_relationship;
            $Policy['NomineeRelationship'] = $proposal_data->nominee_relationship;
        } else {
            $Policy['CorporateClient'] = 'Y';
        }
        
        if (in_array($premium_type, ['comprehensive', 'breakin'])) {
            $policy_type = 'CP';
        } else if ($tp_only) {
            $policy_type = 'TP';
        } else if ($premium_type == 'own_damage') {
            $policy_type = 'OD';
            $Policy['TpInceptionDate'] = $proposal_data->tp_start_date; //$tp_start_date;
            $Policy['TpExpiryDate'] = $proposal_data->tp_end_date; //$tp_end_date;
            $Policy['TpInsurerName'] = $proposal_data->tp_insurance_company; //$tp_insurance_company;
            $Policy['TpPolicyNo'] = $proposal_data->tp_insurance_number; //$tp_insurance_number ;
        }
        $year = explode('-', $requestData->manufacture_year);
        $yearOfManufacture = trim(end($year));
        $reg_no = explode('-', $proposal_data->vehicale_registration_number);
        if ($reg_no[0] == 'DL') {
            if (strlen($reg_no[2]) == 3) {
                $reg_no[1] = ltrim($reg_no[1], '0') . substr($reg_no[2], 0, 1);
                $reg_no[2] = substr($reg_no[2], 1);
            }
        }

        $Vehicle = [
            'AAIExpiryDate' => '',
            'AAINumber' => '',
            'Capacity' => $mmv_data['seating_capacity'],
            'ChassisNumber' => $proposal_data->chassis_number,
            'EngineCapacity' => $mmv_data['cc'] ?? null, //$mmv_data['data']['cubic_capacity'],
            'EngineNumber' => $proposal_data->engine_number,
            'GrossVehicleWeight' => $mmv_data['gvw']?? null,
            'ImposedExcessPartialLoss' => '',
            'ImposedExcessTotalLoss' => '',
            'Make' => $mmv_data['make_code'], //$mmv_data['data']['make_code'],
            'ManufacturingYear' => $yearOfManufacture,
            'PolicyType' => ($isNewVehicle == 'Y') ? 'BP' : $policy_type,
            'RegistrationDate' => $reg_date,
            'RegistrationNumber1' => ($isNewVehicle == 'N') ? $reg_no[0] : 'NEW',
            'RegistrationNumber2' => ($isNewVehicle == 'N') ? $reg_no[1] : '',
            'RegistrationNumber3' => ($isNewVehicle == 'N') ? $reg_no[2] : '',
            'RegistrationNumber4' => ($isNewVehicle == 'N') ? $reg_no[3] : '0001',
            'RiskOccupationCode' => '',
            'RTOCity' => $rto_data->rto_city_code,
            'SeatingCapacity' => $mmv_data['seating_capacity'], //$mmv_data['data']['seating_capacity'],
            'VehicleBody' => '',
            'Zcover' => $tp_only ? 'AC' : 'CO',
            'Zone' => $rto_data->irda_zone,
            'ValidDrivingLicence' => $ValidDrivingLicence,
            'AlternatePACover' => $AlternatePACover,
            'NewVehicleFlag' => $isNewVehicle
        ];

        // insured prefix
        $salutation = '';
        if ($requestData->vehicle_owner_type == 'I') {
            if ($proposal_data->gender == "M") {
                $salutation = 'MR';
            } else {
                if ($proposal_data->gender == "F" && $proposal_data->marital_status == "Single") {
                    $salutation = 'MRS';
                } else {
                    $salutation = 'MS';
                }
            }
        }
        //$salutation = (isset($proposal_data->gender) ? (($proposal_data->gender) == 'M' ? 'MR' : 'MRS') : '');

        $dob = date("m/d/Y", strtotime($proposal_data->dob));
        $address_data = [
                'address' => $proposal_data->address_line1,
                'address_1_limit'   => 30,
                'address_2_limit'   => 30,
                'address_3_limit'   => 30,
                'address_4_limit'   => 30
            ];
        $getAddress = getAddress($address_data);
        
        if($proposal_data->owner_type == 'I' && ($proposal_data->last_name === null || $proposal_data->last_name == ''))
        {
            $proposal_data->last_name = '.';
        }

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
            'PAN' => $proposal_data->pan_number,
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
            'InsuredPAN' => $proposal_data->pan_number,
            'InsuredAadhar' => '',
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
            'PAN' => $proposal_data->pan_number,
            'PrimaryAccountCity' => $proposal_data->owner_type == 'I' ? '' : $city->city_code,
            'PrimaryAccountCountry' => $proposal_data->owner_type == 'I' ? '' : 'IND',
            'PrimaryAccountPostalCode' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->pincode,
            'PrimaryAccountState' => $proposal_data->owner_type == 'I' ? '' : $rto_data->state_code,
            'PrimaryAccountStreetAddress' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_1'],
            'PrimaryAccountStreetAddress2' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_2'],
            'PrimaryAccountStreetAddress3' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_3'],
            'PrimaryAccountStreetAddress4' => $proposal_data->owner_type == 'I' ? '' : $getAddress['address_4'],
            'AccountGSTIN' => $proposal_data->owner_type == 'I' ? '' : $proposal_data->gst_number,
        ];
        $VehicleThirdParty = [
            'InterestedParty'       => '',
            'interestedPartyName'   => '',
            'Relation'              => ''
        ];
        if (!empty($proposal_data->name_of_financer) && $proposal_data->name_of_financer != 'undefined') {
            $financierDetails = iffco_tokioFinancierMaster::whereRaw('UPPER(name) = ?', [strtoupper($proposal_data->name_of_financer)])
                ->first();
            $VehicleThirdParty['interestedPartyName'] = $proposal_data->name_of_financer;
            $VehicleThirdParty['InterestedParty'] = $financierDetails->code ?? '';
            $VehicleThirdParty['Relation'] = 'HY';
        }

        if ($proposal_data->owner_type == 'C') {
            $Account['DOB'] = date("m/d/Y", strtotime($proposal_data->dob));
        }
        $proposal_xml_request = [
            'Policy' => $Policy,
            'Coverage' => $Coverages,
            'Vehicle' => $Vehicle,
            'Contact' => $Contact,
            'Account' => $Account,
            'VehicleThirdParty' => $VehicleThirdParty,
        ];

        $proposal_xml_request = ArrayToXML::convert($proposal_xml_request, 'Request');
        $proposal_xml_request = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $proposal_xml_request);
        $proposal_xml_request = htmlspecialchars_decode($proposal_xml_request);
        $data = [
            'xml_data' => trim($proposal_xml_request),
            'proposal_no' => $unique_quote,
            'QuoteId' => $unique_quote,
        ];
        $proposal_data->unique_quote = $proposal_data->proposal_no = $proposal_data->unique_proposal_id = $unique_quote;
        $proposal_data->save();
        //return $data['xml_data'];
        // return [
        //     'status'  => 'true',
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
            'return_url' => route('cv.payment-confirm', ['iffco_tokio', 'user_proposal_id' => $proposal_data->user_proposal_id, 'policy_id' => $request->policyId]),
            'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            'xml_data' => $data['xml_data'],
            'active' => 1,
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
            'section'           => $is_GCV ? 'GCV' : 'PCV',
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
            // 'response_time'	=> $responseTime->format('%H:%i:%s'),
            'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'        => Carbon::now(),
            'headers'           => json_encode($headers)
        ];
        WebServiceRequestResponse::create($wsLogdata);

        $return_data = [
            'form_action' => $payment_url,
            'form_method' => 'POST',
            'payment_type' => 0, // form-submit
            'form_data' => [
                'RESPONSE_URL' => route('cv.payment-confirm', ['iffco_tokio']),
                'PARTNER_CODE' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
                'UNIQUE_QUOTEID' => $data['QuoteId'],
                'XML_DATA' => $data['xml_data'],
                'enquiry_id' => $enquiryId,
            ],
        ];
        updateJourneyStage([
            'user_product_journey_id' => $proposal_data->user_product_journey_id,
            'ic_id' => $proposal_data->company_id,
            'stage' => STAGE_NAMES['PAYMENT_INITIATED'],
        ]);
        $data_u['user_product_journey_id'] = $proposal_data->user_product_journey_id;
        $data_u['ic_id'] = $proposal_data->ic_id;
        $data_u['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($data_u);
        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);

    }
}