<?php

namespace App\Http\Controllers\Proposal\Services;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterRto;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ckycUploadDocuments;
use App\Models\ProposerCkycDetails;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Services\BajajAllianzPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/BajajAllianzCkycHelper.php';

class bajaj_allianzSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {
        $product_sub_types = [
            'AUTO-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_AUTO_RICKSHAW_KIT_TYPE'),
            'TAXI' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TAXI_KIT_TYPE'),
            'ELECTRIC-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_E_RICKSHAW_KIT_TYPE'),
            'PICK UP/DELIVERY/REFRIGERATED VAN' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_PICKUP_DELIVERY_VAN_KIT_TYPE'),
            'DUMPER/TIPPER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_DUMPER_TIPPER_KIT_TYPE'),
            'TRUCK' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRUCK_KIT_TYPE'),
            'TRACTOR' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRACTOR_KIT_TYPE'),
            'TANKER/BULKER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TANKER_BULKER_KIT_TYPE')
        ];

        $product_sub_type_code = policyProductType($request['policyId'])->product_sub_type_code;

        if ($product_sub_types[$product_sub_type_code] == 'JSON') {
            return self::submitJson($proposal, $request);
        } else {
            return self::submitXml($proposal, $request);
        }
    }

    public static function submitXml($proposal, $request)
    {
        $enquiryId = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
        $premium_type = DB::table('master_premium_type')->where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $is_breakin_case = 'N';
        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
        if ($requestData->business_type == 'newbusiness') {
            $polType = '1';
            $policy_start_date = Carbon::today()->format('d-M-Y');
            $policy_end_date = date('d-M-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));
        } else if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_diff > 0 && $premium_type == "third_party") {
                $policy_start_date = Carbon::today()->addDay(2)->format('d-M-Y');
            } else {
                $policy_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1)->format('d-M-Y');
            }

            if (($requestData->business_type == 'breakin') && ($premium_type == "own_damage" || $premium_type == "comprehensive")) {
                $policy_start_date = Carbon::today()->addDay(2)->format('d-M-Y');
            }
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
            $polType = '3';
        }
        $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
        if ($is_GCV) {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz', $requestData->gcv_carrier_type);
        } else {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');
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
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $quote_data->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($proposal->prev_policy_expiry_date == 'New' ? date('Y-m-d') : $proposal->prev_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
        // zero depriciation validation
        if ($car_age > 5 && $productData->zero_dep == '0') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            ];
        }
        if ($car_age > 16) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle age should not be greater than 15 year',
            ];
        }
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();

        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();

        $tp_only = ($premium_type == 'third_party') ? true : false;
        // new business
        if ($quote_log->premium_json['businessType'] == 'New Business') {
            $BusinessType = '1';
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $polType = '1';
            $previous_ncb = '0';
        } else {
            $BusinessType = '2';
            $polType = '3';
            $previous_ncb = (int) $requestData->previous_ncb;
        }
        $externalCNGKITSI = $NonElectricalaccessSI = $NonElectricalaccessSI = $ElectricalaccessSI = $LLtoPaidDriverYN = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = 0;

        $cover_data = [];
        foreach ($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = 1;
                $cover_data[] = [
                    'typ:paramDesc' => 'LLO',
                    'typ:paramRef' => 'LLO',
                ];
            }

            /* if (in_array('PA cover for additional paid driver', $value)) {
            $PAPaidDriverConductorCleaner = 1;
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            } */

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'PA',
                    'typ:paramRef' => 'PA',
                ];
            }
        }
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $ElectricalaccessSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'ELECACC',
                    'typ:paramRef' => 'ELECACC',
                ];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccessSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'NELECACC',
                    'typ:paramRef' => 'NELECACC',
                ];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKITSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'CNG',
                    'typ:paramRef' => 'CNG',
                ];
            }
        }
        $voluntary_insurer_discounts = 0;
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                $voluntary_insurer_discounts = isset($value['sumInsured']) ? $value['sumInsured'] : 0;
            }
            if (in_array('TPPD Cover', $value)) {
                $cover_data[] = [
                    'typ:paramDesc' => 'TPPD_RES',
                    'typ:paramRef' => 'TPPD_RES',
                ];
            }
        }
        if ($voluntary_insurer_discounts != 0) {
            $cover_data[] = [
                'typ:paramDesc' => 'VOLEX',
                'typ:paramRef' => 'VOLEX',
            ];
        }
        // rto code
        if (isset($quote_data['rto_code'])) {
            $rto_code = $quote_data['rto_code'];
        } else {
            $rto_code = ($vehicale_registration_number[0] ?? '') . '-' . ($vehicale_registration_number[1] ?? '');
        }

        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number = explode('-', $rto_code);
        }
        // rto code

        // rto details
        $rto_details = DB::table('bajaj_allianz_master_rto')->where('registration_code', str_replace('-', '', $requestData->rto_code))->first();
        if (empty($rto_details)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO Not Available',
                'request' => [
                    'message' => 'RTO Not Available',
                    'rto_code' => $requestData->rto_code,
                ],
            ];
        }
        $zone_A = ['AHMEDABAD', 'BANGALORE', 'CHENNAI', 'HYDERABAD', 'KOLKATA', 'MUMBAI', 'NEW DELHI', 'PUNE', 'DELHI'];
        $zone = ((in_array(strtoupper($rto_details->city_name), $zone_A)) ? 'A' : 'B');
        // rto details

        // salutaion
        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $insured_prefix = '1'; // Mr
            } else {
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $insured_prefix = '2'; // Mrs
                } else {
                    $insured_prefix = '4'; // Miss
                }
            }
        } else {
            $insured_prefix = '3'; // M/S
        }
        // salutaion
        // CPA
        $cpa = '';
        $extCol24 = '';
        if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            if ($quote_log->premium_json['businessType'] == 'New Business') {
                $cpa = 'MCPA';
                $extCol24 = '1';
            } else if ($premium_type != 'own_damage') {
                $cpa = 'MCPA';
                $extCol24 = '1';
            }
        } else if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
            $extCol24 = '';
            $cpa = '';
        }

        $additional_details = json_decode($proposal->additional_details);

        $prev_policy_details = $additional_details->prepolicy ?? '';
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
            $extCol24 = '1';
            $cpa = 'DRVL'; // Not a valid driving license
        } else if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $corporate_vehicles_quotes_request->vehicle_owner_type == 'I') {
            $extCol24 = '1';
            $cpa = 'ACPA'; // Already Having CPA with other insurer

            if (config('constants.IS_OLA_BROKER') != 'Y') {
                $cPAInsComp = $prev_policy_details->cPAInsComp ?? '';
                $cPAPolicyNo = $prev_policy_details->cPAPolicyNo;
                $cPASumInsured = $prev_policy_details->cpaSumInsured;
                $cPAPolicyFmDt = Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y');
                $cPAPolicyToDt = Carbon::parse($prev_policy_details->cpaPolicyEndDate)->format('d/m/Y');
            }
        }

        /* if (!($premium_type == 'own_damage') && isset($selected_addons->compulsory_personal_accident[0]['reason']) && $corporate_vehicles_quotes_request->vehicle_owner_type != 'C') {
        } */
        // CPA

        // Policy Type
        if ($master_policy->premium_type_id == 7 || $master_policy->premium_type_id == 2) {
            $quote_log->idv = '';
        }
        // Policy Type

        //Hypothecation
        $HypothecationType = $HypothecationBankName = $HypothecationAddress1 = $HypothecationCity = '';
        $vehicleDetails = $additional_details->vehicle ?? null;
        if ($vehicleDetails->isVehicleFinance ?? null == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType;
            $HypothecationBankName = $vehicleDetails->nameOfFinancer;
            $HypothecationAddress1 = $vehicleDetails->hypothecationCity ?? '';
            $HypothecationCity = $vehicleDetails->hypothecationCity ?? '';
        }
        //Hypothecation
        $extCol38 = '';
        if ($requestData->vehicle_owner_type == "I" && isset($proposal->nominee_name) && $proposal->nominee_name != '' && isset($proposal->nominee_relationship) && $proposal->nominee_relationship != '') {
            $extCol38 = '~' . $proposal->nominee_name . '~' . $proposal->nominee_relationship . '';
        }

        if ($premium_type == "third_party") { // Only TP
            $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_TP_BAJAJ_ALLIANZ_MOTOR");
        } else if ($BusinessType == '1') { // New Business
            $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_BAJAJ_ALLIANZ_MOTOR_NEW");
        } else if ($premium_type == 'own_damage') { // Stand Alone OD
            $product4digitCode = config("constants.motor.bajaj_allianz.PRODUCT_CODE_OD_BAJAJ_ALLIANZ_MOTOR");
            $ods_prev_pol_start_date = date('d-m-Y', strtotime('-1 year +1 day', strtotime($prev_policy_details->prevPolicyExpiryDate)));
            $extCol36 =  (date('d-M-Y', strtotime($ods_prev_pol_start_date)) . '~' . $prev_policy_details->tpInsuranceCompany . '~' . $prev_policy_details->tpInsuranceCompanyName  . '~' . $prev_policy_details->tpInsuranceNumber . '~' . date('d-M-Y', strtotime($prev_policy_details->tpEndDate)) . '~1~3~' . date('d-M-Y', strtotime($prev_policy_details->tpStartDate)) . '~');
        } else { // Comprehensive Rollover
            $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_BAJAJ_ALLIANZ_MOTOR");
        }

        $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME");
        $pPassword = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PASSWORD");

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();
        $extCol40 = '';

        if ($quote_log->idv <= 5000000) {
            if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                if ($pos_data) {
                    $extCol40 = config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y' ? 'AAAAA1234A' : $pos_data->pan_no;
                    $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_USERNAME");
                    $pPassword = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_PASSWORD");
                }
            } elseif (config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
                $extCol40 = 'AAAAA1234A';
                $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_USERNAME");
                $pPassword = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_PASSWORD");
            }
        }

        $data = [
            'soapenv:Header' => [],
            'soapenv:Body' => [
                'web:calculateMotorPremiumSig' => [
                    'pUserId' => $pUserId,
                    'pPassword' => $pPassword,
                    'pVehicleCode' => $mmv_data->vehiclecode,
                    'pCity' => strtoupper($rto_details->city_name),
                    'pWeoMotPolicyIn_inout' => [
                        'typ:contractId' => '0',
                        'typ:polType' => $polType,
                        'typ:product4digitCode' => $product4digitCode,
                        'typ:deptCode' => '18',
                        'typ:branchCode' => config("constants.motor.bajaj_allianz.BRANCH_OFFICE_CODE_BAJAJ_ALLIANZ_MOTOR"),
                        'typ:termStartDate' => date('d-M-Y', strtotime($policy_start_date)),
                        'typ:termEndDate' => date('d-M-Y', strtotime($policy_end_date)),
                        'typ:tpFinType' => $HypothecationType,
                        'typ:hypo' => $HypothecationBankName,
                        'typ:vehicleTypeCode' => $mmv_data->vehicletypecode,
                        'typ:vehicleType' => '', //$mmv_data->vehicletype,
                        'typ:miscVehType' => '0',
                        'typ:vehicleMakeCode' => $mmv_data->vehiclemakecode,
                        'typ:vehicleMake' => $mmv_data->vehiclemake,
                        'typ:vehicleModelCode' => $mmv_data->vehiclemodelcode,
                        'typ:vehicleModel' => $mmv_data->vehiclemodel,
                        'typ:vehicleSubtypeCode' => $mmv_data->vehiclesubtypecode,
                        'typ:vehicleSubtype' => $mmv_data->vehiclesubtype,
                        'typ:fuel' => $mmv_data->fuel,
                        'typ:zone' => $zone,
                        'typ:engineNo' => $proposal->engine_number,
                        'typ:chassisNo' => $proposal->chassis_number,
                        'typ:registrationNo' => $BusinessType == '1' ? "NEW" : str_replace('-', '', implode('', $vehicale_registration_number)),
                        'typ:registrationDate' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                        'typ:registrationLocation' => $rto_details->city_name,
                        'typ:regiLocOther' => $rto_details->city_name,
                        'typ:carryingCapacity' => $mmv_data->carryingcapacity,
                        'typ:cubicCapacity' => $mmv_data->cubiccapacity,
                        'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                        'typ:color' => $proposal->vehicle_color,
                        'typ:vehicleIdv' => $quote_log->idv,
                        'typ:ncb' => $tp_only ? '' : $requestData->applicable_ncb,
                        'typ:addLoading' => '0',
                        'typ:addLoadingOn' => '0',
                        'typ:spDiscRate' => '0',
                        'typ:elecAccTotal' => $ElectricalaccessSI,
                        'typ:nonElecAccTotal' => $NonElectricalaccessSI,
                        'typ:prvPolicyRef' => $proposal->previous_policy_number,
                        'typ:prvExpiryDate' => (($BusinessType == '1') ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                        'typ:prvInsCompany' => $proposal->previous_insurance_company,
                        'typ:prvNcb' => $requestData->previous_ncb,
                        'typ:prvClaimStatus' => (($requestData->is_claim == 'Y') ? '1' : '0'),
                        'typ:autoMembership' => '0',
                        'typ:partnerType' => (($requestData->vehicle_owner_type == 'I') ? 'P' : 'I'),
                    ],
                    'accessoriesList_inout' => [
                        'typ:WeoMotAccessoriesUser' => [
                            'typ:contractId' => '',
                            'typ:accCategoryCode' => '',
                            'typ:accTypeCode' => '',
                            'typ:accMake' => '',
                            'typ:accModel' => '',
                            'typ:accIev' => '',
                            'typ:accCount' => '',
                        ],
                    ],
                    'paddoncoverList_inout' => [
                        'typ:WeoMotGenParamUser' => $cover_data,
                    ],
                    'motExtraCover' => [
                        'typ:geogExtn' => '',
                        'typ:noOfPersonsPa' => $PAforUnnamedPassenger ? $mmv_data->carryingcapacity : '',
                        'typ:sumInsuredPa' => $PAforUnnamedPassenger ? $PAforUnnamedPassengerSI : '',
                        'typ:sumInsuredTotalNamedPa' => '',
                        'typ:cngValue' => $externalCNGKITSI,
                        'typ:noOfEmployeesLle' => '',
                        'typ:noOfPersonsLlo' => $LLtoPaidDriverYN,
                        'typ:fibreGlassValue' => '',
                        'typ:sideCarValue' => '',
                        'typ:noOfTrailers' => '',
                        'typ:totalTrailerValue' => '',
                        'typ:voluntaryExcess' => $voluntary_insurer_discounts,
                        'typ:covernoteNo' => '',
                        'typ:covernoteDate' => '',
                        'typ:subImdcode' => '',
                        'typ:extraField1' => $proposal->pincode,
                        'typ:extraField2' => '',
                        'typ:extraField3' => '',
                    ],
                    'pQuestList_inout' => [
                        'typ:WeoBjazMotQuestionaryUser' => [
                            'typ:questionRef' => '',
                            'typ:contractId' => '',
                            'typ:questionVal' => '',
                        ],
                    ],
                    'pDetariffObj_inout' => [
                        'typ:vehPurchaseType' => '',
                        // 'typ:vehPurchaseDate' => '', // removed tag as per Ic changes #32102
                        'typ:monthOfMfg' => '',
                        'typ:bodyType' => '',
                        'typ:goodsTransType' => '',
                        'typ:natureOfGoods' => '',
                        'typ:otherGoodsFrequency' => '',
                        'typ:permitType' => '',
                        'typ:roadType' => '',
                        'typ:vehDrivenBy' => '',
                        'typ:driverExperience' => '',
                        'typ:clmHistCode' => '',
                        'typ:incurredClmExpCode' => '',
                        'typ:driverQualificationCode' => '',
                        'typ:tacMakeCode' => '',
                        'typ:registrationAuth' => '',
                        'typ:extCol1' => '',
                        'typ:extCol2' => '',
                        'typ:extCol3' => '',
                        'typ:extCol4' => '',
                        'typ:extCol5' => '',
                        'typ:extCol6' => '',
                        'typ:extCol7' => config("constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR"),
                        'typ:extCol8' => $cpa,
                        'typ:extCol9' => '',
                        'typ:extCol10' => $masterProduct->product_identifier,
                        'typ:extCol11' => '',
                        'typ:extCol12' => '',
                        'typ:extCol13' => '',
                        'typ:extCol14' => '',
                        'typ:extCol15' => $proposal->occupation,
                        'typ:extCol16' => '',
                        'typ:extCol17' => '',
                        'typ:extCol18' => '', //'CPA',
                        'typ:extCol19' => '',
                        'typ:extCol21' => 'Y', // Discount tag needed for Auto-Rickshaw 3WL
                        'typ:extCol20' => '',
                        'typ:extCol22' => '',
                        'typ:extCol23' => '',
                        'typ:extCol24' => $extCol24, //CPA cover
                        'typ:extCol25' => '',
                        'typ:extCol26' => '',
                        'typ:extCol29' => '',
                        'typ:extCol27' => '',
                        'typ:extCol28' => '',
                        'typ:extCol30' => '',
                        'typ:extCol31' => '',
                        'typ:extCol32' => '',
                        'typ:extCol33' => (($requestData->vehicle_owner_type == 'I') ? '' :  $proposal->gst_number),
                        'typ:extCol34' => '',
                        'typ:extCol35' => '',
                        'typ:extCol36' => (isset($extCol36) ? $extCol36 : ''),
                        'typ:extCol37' => '',
                        'typ:extCol38' => $extCol38, //Nominee details
                        'typ:extCol39' => '',
                        'typ:extCol40' => $extCol40,
                    ],
                    'premiumDetailsOut_out' => [
                        'typ:serviceTax' => '',
                        'typ:collPremium' => '',
                        'typ:totalActPremium' => '',
                        'typ:netPremium' => '',
                        'typ:totalIev' => '',
                        'typ:addLoadPrem' => '',
                        'typ:totalNetPremium' => '',
                        'typ:imtOut' => '',
                        'typ:totalPremium' => '',
                        'typ:ncbAmt' => '',
                        'typ:stampDuty' => '',
                        'typ:totalOdPremium' => '',
                        'typ:spDisc' => '',
                        'typ:finalPremium' => '',
                    ],
                    'premiumSummeryList_out' => [
                        'typ:WeoMotPremiumSummaryUser' => [
                            'typ:od' => '',
                            'typ:paramDesc' => '',
                            'typ:paramRef' => '',
                            'typ:net' => '',
                            'typ:act' => '',
                            'typ:paramType' => '',
                        ],
                    ],
                    'pError_out' => [
                        'typ:WeoTygeErrorMessageUser' => [
                            'typ:errNumber' => '',
                            'typ:parName' => '',
                            'typ:property' => '',
                            'typ:errText' => '',
                            'typ:parIndex' => '',
                            'typ:errLevel' => '',
                        ],
                    ],
                    'pErrorCode_out' => '',
                    'pTransactionId_inout' => '',
                    'pTransactionType' => '',
                    'pContactNo' => '',
                ],
            ],
        ];
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'CV',
            'method' => 'Proposal Submit',
            'productName' => $masterProduct->product_identifier,
            'product' => 'Commercial Vehicle',
            'transaction_type' => 'proposal',
        ];
        $root = [
            'rootElementName' => 'soapenv:Envelope',
            '_attributes' => [
                "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
                "xmlns:web" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl",
                "xmlns:typ" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl/types/",
            ],
        ];
        $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');
        $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PROPOSAL_URL'), $input_array, 'bajaj_allianz', $additional_data);
        $response = $get_response['response'];
        if (empty($response)) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $response = XmlToArray::convert($response);

        $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
        if ($service_response['pErrorCode_out'] == '0') {
            if (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'])) {
                if (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errLevel']) && $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errLevel'] == 1) {
                    return [
                        'status' => false,
                        'message' => $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText']
                    ];
                }
            }
            $basic_od = $pa_owner = $non_electrical_accessories = $electrical_accessories = $pa_unnamed = $ll_paid_driver = $lpg_cng =  $other_discount = $ncb_discount =  $voluntary_deductible = 0;
            $addons = [];
            if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
                $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];
                if ($premium_type == 'own_damage' && $requestData->is_claim == 'Y' && $productData->zero_dep == '1' && isset($covers['od'])) {
                    $basic_od = ($covers['typ:od']);
                } else {
                    foreach ($covers as $key => $cover) {
                        if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                            $addons['zeroDept'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                            $addons['key_replace'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                            $addons['engine_protect'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') {
                            $basic_od = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                            $tppd = round($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                            $pa_owner = round($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                            $non_electrical_accessories = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                            $electrical_accessories = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                            $pa_unnamed = round($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'LL To Person For Operation/Maintenance(IMT.28/39)') {
                            $ll_paid_driver = round($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                            $lpg_cng = round($cover['typ:od']);
                            $lpg_cng_tp = round($cover['typ:act']);
                        } elseif (in_array($cover['typ:paramDesc'], ['Commercial Discount', 'Commercial Discount3'])) {
                            $other_discount = round(abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                            $ncb_discount = round(abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Personal Baggage Cover') {
                            $addons['lopb'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                            $addons['rsa'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Accident Sheild') {
                            $addons['accident_shield'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Conveyance Benefit') {
                            $addons['conveyance'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                            $addons['consumables'] = round($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                            $voluntary_deductible = (abs($cover['typ:od']));
                        }
                    }
                }
            }
            $vehicleDetails = [
                'manufacture_name' => $mmv_data->vehiclemake,
                'model_name' => $mmv_data->vehiclemodel,
                'version' => $mmv_data->vehiclesubtype,
                'fuel_type' => $mmv_data->fuel,
                'seating_capacity' => $mmv_data->carryingcapacity,
                'carrying_capacity' => $mmv_data->carryingcapacity,
                'cubic_capacity' => $mmv_data->cubiccapacity,
                'gross_vehicle_weight' => '',
                'vehicle_type' =>  '', //$mmv_data->vehicletype
            ];

            BajajAllianzPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

            $is_premium_different = false;
            $is_premium_to_be_stored = true;
            // $is_premium_to_be_stored = isNewPremiumToBeStored($service_response['premiumDetailsOut_out']['typ:collPremium'], $proposal);

            if ($is_premium_to_be_stored) {
                $is_premium_different = true;

                $proposal->proposal_no = $service_response['pTransactionId_inout'];
                $proposal->pol_sys_id = $service_response['pTransactionId_inout'];
                $proposal->ic_vehicle_details = $vehicleDetails;
                $proposal->save();
                $total_discount = $ncb_discount + $other_discount + $voluntary_deductible;

                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                        'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                        'tp_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                        'tp_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                        'proposal_no' => $proposal->proposal_no,
                        'unique_proposal_id' => $proposal->proposal_no,
                        'od_premium' => ($basic_od - $total_discount),
                        'tp_premium' => $service_response['premiumDetailsOut_out']['typ:totalActPremium'],
                        'addon_premium' => array_sum($addons) + ($lpg_cng + $electrical_accessories + $non_electrical_accessories),
                        'cpa_premium' => $pa_owner,
                        'final_premium' => $service_response['premiumDetailsOut_out']['typ:totalPremium'],
                        'total_premium' => $service_response['premiumDetailsOut_out']['typ:totalPremium'],
                        'service_tax_amount' => $service_response['premiumDetailsOut_out']['typ:serviceTax'],
                        'final_payable_amount' => $service_response['premiumDetailsOut_out']['typ:collPremium'],
                        'product_code' => $product4digitCode,
                        'ic_vehicle_details' => json_encode($vehicleDetails),
                        'ncb_discount' => $ncb_discount,
                        'total_discount' => $total_discount,
                        'cpa_ins_comp' => $cPAInsComp,
                        'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                        'cpa_policy_no' => $cPAPolicyNo,
                        'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                        'cpa_sum_insured' => $cPASumInsured,
                        'electrical_accessories' => $ElectricalaccessSI,
                        'non_electrical_accessories' => $NonElectricalaccessSI,
                    ]);

                ProposerCkycDetails::updateOrCreate([
                    'user_product_journey_id' => $enquiryId
                ], [
                    'meta_data' => [
                        'last_premium_calculation_timestamp' => strtotime(date('Y-m-d'))
                    ]
                ]);
            }
            //ckyc code verification start
            $kyc_status =false;
            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                if (config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y') {
                    $response = ckycVerifications($proposal, [
                        'user_id'   => $pUserId,
                        'product_code' => $product4digitCode,
                        'is_premium_different' => $is_premium_different,
                        'trigger_old_document_flow' => !empty($request['newFlowBajaj']) && $request['newFlowBajaj'] == 'Y' ? 'Y' : 'N',
                    ]);

                    if ( ! $response['status']) {
                        return response()->json($response);
                    }

                    $kyc_status = true;
                } else {
                    $request_data = [
                        'companyAlias' => 'bajaj_allianz',
                        'mode' =>  $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' :'documents',
                        'enquiryId' => customEncrypt($enquiryId),
                        'user_id'   => $pUserId,
                        'product_code' => $product4digitCode
                    ];

                    $ckycController = new CkycController;
                    $response = $ckycController->ckycVerifications(new Request($request_data));
                    $response = $response->getOriginalContent();
                    if (isset($response['data']['verification_status']) && !$response['data']['verification_status']) {
                        return response()->json([
                            'status' => false,
                            'message' => 'CKYC verification failed. Try other method'//'Ckyc status is not verified'
                        ]);
                    }else{
                        $kyc_status =true;
                    }
                }
            }
            //end
            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
            $address_data = [
                'address' => $proposal->address_line1,
                'address_1_limit'   => 100,
                'address_2_limit'   => 100,
                'address_3_limit'   => 60
            ];
            $getAddress = getAddress($address_data);
            $issue_array = [
                'web:issuePolicy' => [
                    'pUserId' => config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME"),
                    'pPassword' => config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PASSWORD"),
                    'pTransactionId' => $service_response['pTransactionId_inout'],
                    'pRcptList_inout' => [
                        'typ:WeoTyacPayRowWsUser' => [
                            'typ:payMode' => '',
                            'typ:receiptNo' => '',
                            'typ:payAmt' => '',
                            'typ:collectionNo' => '',
                            'typ:collectionAmt' => '',
                        ],
                    ],
                    'pCustDetails_inout' => [
                        'typ:partTempId' => '',
                        // 'typ:firstName' => ($user_proposal['owner_type'] == "C") ? $user_proposal['last_name'] : $user_proposal['first_name'],
                        'typ:firstName' => $user_proposal['first_name'],
                        'typ:middleName' => '',
                        'typ:surname' => ($user_proposal['owner_type'] == 'I') ? $user_proposal['last_name'] : '',
                        'typ:addLine1' => $getAddress['address_1'],
                        'typ:addLine2' => $getAddress['address_2'],
                        'typ:addLine3' => $getAddress['address_3'],
                        'typ:addLine4' => $user_proposal['city'],
                        'typ:addLine5' => $user_proposal['state'],
                        'typ:pincode' => $user_proposal['pincode'],
                        'typ:email' => $user_proposal['email'],
                        'typ:telephone1' => $user_proposal['mobile_number'],
                        'typ:telephone2' => '',
                        'typ:mobile' => $user_proposal['mobile_number'],
                        'typ:delivaryOption' => '',
                        'typ:polAddLine1' => $getAddress['address_1'],
                        'typ:polAddLine2' => $getAddress['address_2'],
                        'typ:polAddLine3' => $getAddress['address_3'],
                        'typ:polAddLine5' => $user_proposal['state'],
                        'typ:polPincode' => $user_proposal['pincode'],
                        'typ:password' => '',
                        'typ:cpType' => ($user_proposal['owner_type'] == 'C') ? 'I' : '',
                        'typ:profession' => $user_proposal['occupation'],
                        'typ:dateOfBirth' =>  date('d-M-Y', strtotime($user_proposal['dob'])),
                        'typ:availableTime' => '',
                        'typ:institutionName' => ($user_proposal['owner_type'] == 'C') ? $user_proposal['last_name'] : '',
                        'typ:existingYn' => '',
                        'typ:loggedIn' => '',
                        'typ:mobileAlerts' => '',
                        'typ:emailAlerts' => '',
                        'typ:title' => (($user_proposal['gender'] == 'MALE') ? 'Mr' : 'Ms'),
                        'typ:partId' => '',
                        'typ:status1' => '',
                        'typ:status2' => '',
                        'typ:status3' => '',
                    ],
                    'pWeoMotPolicyIn_inout' => [
                        'typ:contractId' => '',
                        'typ:polType' => (($user_proposal['business_type'] == 'newbusiness') ? '1' : '3'),
                        'typ:product4digitCode' => $user_proposal['product_code'],
                        'typ:deptCode' => '18',
                        'typ:branchCode' => config("constants.motor.bajaj_allianz.BRANCH_OFFICE_CODE_BAJAJ_ALLIANZ_MOTOR"),
                        'typ:termStartDate' => date('d-M-Y', strtotime($policy_start_date)),
                        'typ:termEndDate' => date('d-M-Y', strtotime($policy_end_date)),
                        'typ:tpFinType' => $HypothecationType,
                        'typ:hypo' => $HypothecationBankName,
                        'typ:vehicleTypeCode' => $mmv_data->vehicletypecode,
                        'typ:vehicleType' => '', //$mmv_data->vehicletype,
                        'typ:miscVehType' => '',
                        'typ:vehicleMakeCode' => $mmv_data->vehiclemakecode,
                        'typ:vehicleMake' => $mmv_data->vehiclemake,
                        'typ:vehicleModelCode' => $mmv_data->vehiclemodelcode,
                        'typ:vehicleModel' => $mmv_data->vehiclemodel,
                        'typ:vehicleSubtypeCode' => $mmv_data->vehiclesubtypecode,
                        'typ:vehicleSubtype' => $mmv_data->vehiclesubtype,
                        'typ:fuel' => $mmv_data->fuel,
                        'typ:zone' => $zone,
                        'typ:engineNo' => $user_proposal['engine_number'],
                        'typ:chassisNo' => $user_proposal['chassis_number'],
                        'typ:registrationNo' => $BusinessType == '1' ? "NEW" : str_replace('-', '', implode('', $vehicale_registration_number)),
                        'typ:registrationDate' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                        'typ:registrationLocation' => $rto_details->city_name,
                        'typ:regiLocOther' => $rto_details->city_name,
                        'typ:carryingCapacity' => $mmv_data->carryingcapacity,
                        'typ:cubicCapacity' => $mmv_data->cubiccapacity,
                        'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                        'typ:color' => $user_proposal['vehicle_color'],
                        'typ:vehicleIdv' => $user_proposal['idv'],
                        'typ:ncb' => $requestData->applicable_ncb,
                        'typ:addLoading' => '',
                        'typ:addLoadingOn' => '',
                        'typ:spDiscRate' => '',
                        'typ:elecAccTotal' => $ElectricalaccessSI,
                        'typ:nonElecAccTotal' => $NonElectricalaccessSI,
                        'typ:prvPolicyRef' => $proposal->previous_policy_number,
                        'typ:prvExpiryDate' => (($BusinessType == '1') ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                        'typ:prvInsCompany' => $proposal->previous_insurance_company,
                        'typ:prvNcb' => $requestData->previous_ncb,
                        'typ:prvClaimStatus' => (($requestData->is_claim == 'Y') ? '1' : '0'),
                        'typ:autoMembership' => '0',
                        'typ:partnerType' => (($requestData->vehicle_owner_type == 'I') ? 'P' : 'I'),
                    ],
                    'accessoriesList' => [
                        'typ:WeoMotAccessoriesUser' => [
                            'typ:contractId' => '',
                            'typ:accCategoryCode' => '',
                            'typ:accTypeCode' => '',
                            'typ:accMake' => '',
                            'typ:accModel' => '',
                            'typ:accIev' => '',
                            'typ:accCount' => '',
                        ],
                    ],
                    'paddoncoverList' => [
                        'typ:WeoMotGenParamUser' => $cover_data,
                    ],
                    'motExtraCover_inout' => [
                        'typ:geogExtn' => '',
                        'typ:noOfPersonsPa' => $PAforUnnamedPassenger ? $mmv_data->carryingcapacity : '',
                        'typ:sumInsuredPa' => $PAforUnnamedPassenger ? $PAforUnnamedPassengerSI : '',
                        'typ:sumInsuredTotalNamedPa' => '',
                        'typ:cngValue' => $externalCNGKITSI,
                        'typ:noOfEmployeesLle' => '',
                        'typ:noOfPersonsLlo' => $LLtoPaidDriverYN,
                        'typ:fibreGlassValue' => '',
                        'typ:sideCarValue' => '',
                        'typ:noOfTrailers' => '',
                        'typ:totalTrailerValue' => '',
                        'typ:voluntaryExcess' => $voluntary_insurer_discounts,
                        'typ:covernoteNo' => '',
                        'typ:covernoteDate' => '',
                        'typ:subImdcode' => '',
                        'typ:extraField1' => '',
                        'typ:extraField2' => '',
                        'typ:extraField3' => '',
                    ],
                    'premiumDetails' => [
                        'typ:serviceTax' => $user_proposal['service_tax_amount'],
                        'typ:collPremium' => $user_proposal['final_payable_amount'],
                        'typ:totalActPremium' => $user_proposal['tp_premium'],
                        'typ:netPremium' => $service_response['premiumDetailsOut_out']['typ:netPremium'],
                        'typ:totalIev' => $service_response['premiumDetailsOut_out']['typ:totalIev'],
                        'typ:addLoadPrem' => $service_response['premiumDetailsOut_out']['typ:addLoadPrem'],
                        'typ:totalNetPremium' => $service_response['premiumDetailsOut_out']['typ:totalNetPremium'],
                        'typ:imtOut' => $service_response['premiumDetailsOut_out']['typ:imtOut'],
                        'typ:totalPremium' => $service_response['premiumDetailsOut_out']['typ:totalPremium'],
                        'typ:ncbAmt' => $service_response['premiumDetailsOut_out']['typ:ncbAmt'],
                        'typ:stampDuty' => $service_response['premiumDetailsOut_out']['typ:stampDuty'],
                        'typ:totalOdPremium' => $service_response['premiumDetailsOut_out']['typ:totalOdPremium'],
                        'typ:spDisc' => $service_response['premiumDetailsOut_out']['typ:spDisc'],
                        'typ:finalPremium' => $service_response['premiumDetailsOut_out']['typ:finalPremium'],
                    ],
                    'premiumSummeryList' => [
                        'typ:WeoMotPremiumSummaryUser' => [
                            'typ:od' => '',
                            'typ:paramDesc' => '',
                            'typ:paramRef' => '',
                            'typ:net' => '',
                            'typ:act' => '',
                            'typ:paramType' => '',
                        ],
                    ],
                    'pQuestList' => [
                        'typ:WeoBjazMotQuestionaryUser' => [
                            'typ:questionRef' => '',
                            'typ:contractId' => '',
                            'typ:questionVal' => '',
                        ],
                    ],
                    'ppolicyref_out' => '',
                    'ppolicyissuedate_out' => '',
                    'ppartId_out' => '',
                    'pError_out' => [
                        'typ:WeoTygeErrorMessageUser' => [
                            'typ:errNumber' => '',
                            'typ:parName' => '',
                            'typ:property' => '',
                            'typ:errText' => '',
                            'typ:parIndex' => '',
                            'typ:errLevel' => '',
                        ],
                    ],
                    'pErrorCode_out' => '',
                    'ppremiumpayerid' => '',
                    'paymentmode' => 'CC',
                    'potherdetails' => [
                        'typ:covernoteNo' => '',
                        'typ:cceCode' => '',
                        'typ:extra1' => 'NEWPG',
                        'typ:extra2' => '',
                        'typ:imdcode' => '',
                        'typ:extra3' => '',
                        'typ:extra4' => '',
                        'typ:extra5' => '',
                        'typ:leadNo' => '',
                        'typ:runnerCode' => '',
                    ],
                    'pMotDetariff' => [
                        'typ:vehPurchaseType' => '',
                        // 'typ:vehPurchaseDate' => '',
                        'typ:monthOfMfg' => '',
                        'typ:bodyType' => '',
                        'typ:goodsTransType' => '',
                        'typ:natureOfGoods' => '',
                        'typ:otherGoodsFrequency' => '',
                        'typ:permitType' => '',
                        'typ:roadType' => '',
                        'typ:vehDrivenBy' => '',
                        'typ:driverExperience' => '',
                        'typ:clmHistCode' => '',
                        'typ:incurredClmExpCode' => '',
                        'typ:driverQualificationCode' => '',
                        'typ:registrationAuth' => '',
                        'typ:extCol1' => '',
                        'typ:extCol2' => '',
                        'typ:extCol3' => '',
                        'typ:extCol4' => '',
                        'typ:extCol5' => '',
                        'typ:extCol6' => '',
                        'typ:extCol7' => config("constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR"),
                        'typ:extCol8' => $cpa,
                        'typ:extCol9' => '',
                        'typ:extCol10' => $masterProduct->product_identifier,
                        'typ:extCol11' => '',
                        'typ:extCol12' => '',
                        'typ:extCol13' => '',
                        'typ:extCol14' => '',
                        'typ:extCol15' => $user_proposal['occupation'],
                        'typ:extCol16' => '',
                        'typ:extCol17' => '',
                        'typ:extCol18' => '',
                        'typ:extCol19' => '',
                        'typ:extCol20' => route('cv.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiryId, 'policy_id' => $request['policyId']]) . '?',
                        'typ:extCol21' => 'Y', // Discount tag needed for Auto-Rickshaw 3WL
                        'typ:extCol22' => '',
                        'typ:extCol23' => '',
                        'typ:extCol24' => $extCol24,
                        'typ:extCol25' => '',
                        'typ:extCol26' => '',
                        'typ:extCol27' => '',
                        'typ:extCol28' => '',
                        'typ:extCol29' => '',
                        'typ:extCol30' => '',
                        'typ:extCol31' => '',
                        'typ:extCol32' => '',
                        'typ:extCol33' => (($requestData->vehicle_owner_type == 'I') ? '' :  $proposal->gst_number),
                        'typ:extCol34' => '',
                        'typ:extCol35' => '',
                        'typ:extCol36' => (isset($extCol36) ? $extCol36 : ''),
                        'typ:extCol37' => '',
                        'typ:extCol38' => (isset($extCol38) ? $extCol38 : ''),  ##nominee rel
                        'typ:extCol39' => '',
                        'typ:extCol40' => $extCol40, //(isset($extCol40) ? $extCol40 : ''), ##pancard
                    ],
                ],
            ];
            $additional_data = [
                'enquiryId' => $enquiryId,
                'headers' => [
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ],
                'requestMethod' => 'post',
                'requestType' => 'xml',
                'section' => 'CV',
                'method' => 'Policy Issuing - ' . $masterProduct->product_identifier,
                'product' => 'PCV',
                'transaction_type' => 'proposal',
                'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:web="http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl" xmlns:typ="http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl/types/"><soapenv:Header />#replace</soapenv:Envelope>',
            ];

            $status = false;

            if (($requestData->business_type == 'breakin') && ($premium_type == "own_damage" || $premium_type != "third_party")) {
                $is_breakin_case = 'Y';
                $pin_number = $service_response['pTransactionId_inout'];
                $pin_veh_number = explode('-', $proposal->vehicale_registration_number);
                $pin_fields['bag:pTransactionId']   = $pin_number;
                $pin_fields['bag:pRegNoPart1']      = $pin_veh_number[0];
                $pin_fields['bag:pRegNoPart2']      = $pin_veh_number[1];
                $pin_fields['bag:pRegNoPart3']      = $pin_veh_number[2];
                $pin_fields['bag:pRegNoPart4']      = $pin_veh_number[3];
                $pin_fields['bag:pUserName']        = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME");
                $pin_fields['bag:pFlag']            = '';
                $pin_fields['bag:pPinNumber_out']   = '';
                $pin_fields['bag:pPinStatus_out']   = '';
                $pin_fields['bag:pVchlDtlsObj_out'] = '';
                $pin_array = [
                    'x:Header' => [],
                    'x:Body' => [
                        'bag:pinProcessWs' => $pin_fields
                    ],
                ];

                $additional_data = [
                    'enquiryId' => $enquiryId,
                    'headers' => [
                        'Content-Type' => 'text/xml; charset="utf-8"',
                    ],
                    'requestMethod' => 'post',
                    'requestType' => 'xml',
                    'section' => 'CV',
                    'method' => 'Pin Generation',
                    'product' => 'Commvercial Vehicle',
                    'transaction_type' => 'proposal',
                ];
                $root = [
                    'rootElementName' => 'x:Envelope',
                    '_attributes' => [
                        "xmlns:x" => "http://schemas.xmlsoap.org/soap/envelope/",
                        "xmlns:bag" => "http://com/bajajallianz/BagicMotorWS.wsdl",
                        "xmlns:typ" => "http://com/bajajallianz/BagicMotorWS.wsdl/types/",
                    ],
                ];
                $input_array = ArrayToXml::convert($pin_array, $root, false, 'utf-8');

                $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_MOTOR_PIN_GENERATION'), $input_array, 'bajaj_allianz', $additional_data);
                $pin_generation_response = $pin_generation_response_xml = $get_response['response'];
                $pin_generation_response = XmlToArray::convert($pin_generation_response);
                $pinProcessWsResponse = $pin_generation_response['env:Body']['m:pinProcessWsResponse'];
                $pPinNumber_out = $pinProcessWsResponse['pPinNumber_out'];
                $pPinStatus_out = $pinProcessWsResponse['pPinStatus_out'];
                $stringval11 = $pinProcessWsResponse['pVchlDtlsObj_out']['typ:stringval11'];
                if ($stringval11 == 'Pin Generated Succefully') {
                    $status = true;
                    $proposal_no = $pin_number;

                    DB::table('cv_breakin_status')->insert([
                        'user_proposal_id' => $proposal->user_proposal_id,
                        'ic_id' => $productData->company_id,
                        'breakin_number' => (isset($pPinNumber_out)) ? $pPinNumber_out : '',
                        'breakin_response' => (isset($pin_generation_response_xml)) ? $pin_generation_response_xml : '',
                        'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    updateJourneyStage([
                        'user_product_journey_id' => $enquiryId,
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        'proposal_id' => $proposal->user_proposal_id,
                    ]);

                    UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'is_breakin_case' => $is_breakin_case,
                        ]);
                } else {
                    ##what status should be updated
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable - Break-in serivce not working',
                    ]);
                }
            } else {
                $input_array = ArrayToXml::convert($issue_array, 'soapenv:Body');
                $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PROPOSAL_URL'), $input_array, 'bajaj_allianz', $additional_data);
                $response = $get_response['response'];
                $issue_policy = XmlToArray::convert($response);

                if ($issue_policy['env:Body']['m:issuePolicyResponse']['pErrorCode_out'] != '0') {
                    $error = $issue_policy['env:Body']['m:issuePolicyResponse']['pError_out']['typ:WeoTygeErrorMessageUser'];
                    $error = isset($error['typ:errText']) ? $error['typ:errText'] : $error['0']['typ:errText'];
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' =>  isset($error) ? $error : 'Error occurred. Please try again.',
                    ]);
                } else {
                    $is_breakin = false;
                    $status = true;
                    $proposal_no = $issue_policy['env:Body']['m:issuePolicyResponse']['motExtraCover_inout']['typ:extraField3'];
                }
            }
            if ($status) {
                $url = config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_URL_BAJAJ_ALLIANZ_MOTOR') . '?requestId=' . $proposal_no . '&Username=' . config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME") . '&sourceName=WS_MOTOR';
                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'payment_url' => $url,
                    ]);

                $user_data['user_product_journey_id'] = $enquiryId;
                $user_data['ic_id'] = $master_policy->insurance_company_id;
                $user_data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                $user_data['proposal_id'] = $proposal->user_proposal_id;
                if (!isset($pPinNumber_out)) updateJourneyStage($user_data);

                return response()->json([
                    'status' => true,
                    'msg' => 'Proposal submitted Successfully.',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $enquiryId,
                        'proposalNo' => $proposal->proposal_no,
                        'finalPayableAmount' => $proposal->final_payable_amount,
                        'is_breakin' => $is_breakin_case,
                        'inspection_number' => (isset($pPinNumber_out)) ? $pPinNumber_out : '',
                        'kyc_status' => $kyc_status,
                    ],
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => 'Unable to calculate premium amount. Please check the provided details',
                ]);
            }
        } else {
            $error_msg = 'Insurer not reachable';
            if (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'])) {
                $error_msg = $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
            } elseif (is_array($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) && count($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) > 0) {
                $error_msg = implode(', ', array_column($service_response['pError_out']['typ:WeoTygeErrorMessageUser'], 'typ:errText'));
            }
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $error_msg,
            ];
        }
    }

    public static function submitJson($proposal, $request)
    {
        $is_gcv = policyProductType($request['policyId'])->parent_id == 4;

        $enquiryId = $proposal->user_product_journey_id;

        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv,
                ],
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $user_product_journey = UserProductJourney::find($enquiryId);
        $quote_log = $user_product_journey->quote_log;
        $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
        $addons = $user_product_journey->addons;
        $agent_details = $user_product_journey->agent_details;
        $master_product = $user_product_journey->quote_log->master_policy->master_product;

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

        if (in_array($corporate_vehicles_quote_request->business_type, ['newbusiness', 'breakin'])) {
            $policy_start_date = date('d-M-Y', time());
        } else {
            $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($corporate_vehicles_quote_request->previous_policy_expiry_date)));
        }

        $policy_end_date = date('d-M-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));

        $rto_data = MasterRto::where('rto_code', $corporate_vehicles_quote_request->rto_code)
            ->first();

        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);

        $cover_list = [];

        $electrical_accessories_sa = 0;
        $non_electrical_accessories_sa = 0;
        $lpg_cng_kit_sa = 0;
        $additional_paid_driver_sa = 0;
        $pa_unnamed_passenger_sa = 0;
        $no_of_unnamed_passenger = 0;
        $voluntary_deductible_si = 0;
        $ll_paid_driver = 0;
        $geo_extension = 0;
        $cpa_selected = '';
        $extCol24 = '';

        $additional_details = json_decode($proposal->additional_details);

        $prev_policy_details = $additional_details->prepolicy ?? '';
        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';

        if ($addons) {
            if (!is_null($addons[0]['compulsory_personal_accident'])) {
                foreach ($addons[0]['compulsory_personal_accident'] as $compulsory_personal_accident) {
                    if (isset($compulsory_personal_accident['name']) && $compulsory_personal_accident['name'] == 'Compulsory Personal Accident') {
                        $cpa_selected = 'MCPA';
                        $extCol24 = '1';
                    } elseif (isset($compulsory_personal_accident['reason'])) {
                        if ($compulsory_personal_accident['reason'] == 'I do not have a valid driving license.') {
                            $cpa_selected = 'DRVL';
                            $extCol24 = '1';
                        } elseif ($compulsory_personal_accident['reason'] == 'I have another motor policy with PA owner driver cover in my name' || $compulsory_personal_accident['reason'] == 'I have another PA policy with cover amount of INR 15 Lacs or more') {
                            $cpa_selected = 'ACPA';
                            $extCol24 = '1';

                            if (config('constants.IS_OLA_BROKER') != 'Y') {
                                $cPAInsComp = $prev_policy_details->cPAInsComp ?? '';
                                $cPAPolicyNo = $prev_policy_details->cPAPolicyNo ?? '';
                                $cPASumInsured = $prev_policy_details->cpaSumInsured ?? '';
                                $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y') : NULL;
                                $cPAPolicyToDt = isset($prev_policy_details->cpaPolicyEndDate) ? Carbon::parse($prev_policy_details->cpaPolicyEndDate)->format('d/m/Y') : NULL;
                            }
                        }
                    }
                }
            }

            if (!is_null($addons[0]['accessories'])) {
                foreach ($addons[0]['accessories'] as $accessory) {
                    if ($accessory['name'] == 'Electrical Accessories') {
                        $electrical_accessories_sa = $accessory['sumInsured'];

                        $cover_list[] = [
                            'paramdesc' => 'ELECACC',
                            'paramref' => 'ELECACC'
                        ];
                    } elseif ($accessory['name'] == 'Non-Electrical Accessories') {
                        $non_electrical_accessories_sa = $accessory['sumInsured'];

                        $cover_list[] = [
                            'paramdesc' => 'NELECACC',
                            'paramref' => 'NELECACC'
                        ];
                    } elseif ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $lpg_cng_kit_sa = $accessory['sumInsured'];

                        $cover_list[] = [
                            'paramdesc' => 'CNG',
                            'paramref' => 'CNG'
                        ];
                    }
                }
            }

            if (!is_null($addons[0]['additional_covers'])) {
                foreach ($addons[0]['additional_covers'] as $additional_cover) {
                    if ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                        $pa_unnamed_passenger_sa = $additional_cover['sumInsured'];
                        $no_of_unnamed_passenger = (int) $mmv_data->carryingcapacity;

                        $cover_list[] = [
                            'paramdesc' => 'PA',
                            'paramref' => 'PA'
                        ];
                    } elseif ($additional_cover['name'] == 'LL paid driver') {
                        $ll_paid_driver = 1;

                        $cover_list[] = [
                            'paramdesc' => 'LLO',
                            'paramref' => 'LLO'
                        ];
                    } elseif ($additional_cover['name'] == 'LL paid driver/conductor/cleaner') {
                        $ll_paid_driver = $additional_cover['LLNumberDriver'] + $additional_cover['LLNumberConductor'] + $additional_cover['LLNumberCleaner'];

                        $cover_list[] = [
                            'paramdesc' => 'LLO',
                            'paramref' => 'LLO'
                        ];
                    } elseif ($additional_cover['name'] == 'Geographical Extension') {
                        $geo_extension = 1;

                        $cover_list[] = [
                            'paramdesc' => 'GEOG',
                            'paramref' => 'GEOG'
                        ];
                    }
                }
            }

            if (!is_null($addons[0]['discounts'])) {
                foreach ($addons[0]['discounts'] as $discount) {
                    if ($discount['name'] == 'TPPD Cover') {
                        $cover_list[] = [
                            'paramdesc' => 'TPPD_RES',
                            'paramref' => 'TPPD_RES'
                        ];
                    }
                }
            }

            if (!is_null($addons[0]['applicable_addons'])) {
                foreach ($addons[0]['applicable_addons'] as $applicable_addon) {
                    if ($applicable_addon['name'] == 'IMT - 23') {
                        $cover_list[] = [
                            'paramdesc' => 'IMT23',
                            'paramref' => 'IMT23'
                        ];
                    }
                }
            }
        }

        if ($requestData->vehicle_owner_type == 'C') {
            $extCol24 = '';
            $cpa = '';
        }
        $extCol38 = '';

        if ($requestData->vehicle_owner_type == 'I' && isset($proposal->nominee_name) && $proposal->nominee_name != '' && isset($proposal->nominee_relationship) && $proposal->nominee_relationship != '') {
            $extCol38 = '~' . $proposal->nominee_name . '~' . $proposal->nominee_relationship . '';
        }

        $extCol40 = '';

        $userid = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_USER_ID');
        $password = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PASSWORD');

        $is_pos = config('constants.motorConstant.IS_POS_ENABLED');

        if ($quote_log->idv <= 5000000) {
            if ($is_pos == 'Y' && $agent_details && isset($agent_details[0]->seller_type) && $agent_details[0]->seller_type == 'P') {
                $extCol40 = config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y' ? 'AAAAA1234A' : $agent_details[0]->pan_no;
                $userid = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_USERNAME");
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_PASSWORD");
            } elseif (config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
                $extCol40 = 'AAAAA1234A';
                $userid = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_USERNAME");
                $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_PASSWORD");
            }
        }

        $rto_details = MasterRto::where('rto_code', $requestData->rto_code)->first();

        $zone_A = ['AHMEDABAD', 'BANGALORE', 'CHENNAI', 'HYDERABAD', 'KOLKATA', 'MUMBAI', 'NEW DELHI', 'PUNE', 'DELHI'];

        $zone = in_array(strtoupper($rto_details->rto_name), $zone_A) ? 'A' : 'B';

        $premium_request = [
            'userid' => $userid,
            'password' => $password,
            'vehiclecode' => $mmv_data->vehiclecode,
            'city' => $rto_data->rto_name,
            'weomotpolicyin' => [
                'contractid' => 0,
                'poltype' => $corporate_vehicles_quote_request->business_type == 'newbusiness' ? 1 : 3,
                'product4digitcode' => in_array($premium_type, ['third_party', 'third_party_breakin']) ? 1831 : 1803,
                'deptcode' => 18,
                'branchcode' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_BRANCH_CODE'),
                'termstartdate' => $policy_start_date,
                'termenddate' => $policy_end_date,
                'vehicletypecode' => $mmv_data->vehicletypecode,
                'vehicletype' => 'Commercial Vehicle',
                'miscvehtype' => 0,
                'vehiclemakecode' => $mmv_data->vehiclemakecode,
                'vehiclemake' => $mmv_data->vehiclemake,
                'vehiclemodelcode' => $mmv_data->vehiclemodelcode,
                'vehiclemodel' => $mmv_data->vehiclemodel,
                'vehiclesubtypecode' => $mmv_data->vehiclesubtypecode,
                'vehiclesubtype' => $mmv_data->vehiclesubtype,
                'fuel' => $mmv_data->fuel,
                'zone' => $zone,
                'registrationno' => (strtolower($proposal->vehicale_registration_number) == 'new') ? str_replace('-', '', $proposal->rto_location) :  str_replace('-', '', $proposal->vehicale_registration_number),
                'registrationdate' => date('d-M-Y', strtotime($corporate_vehicles_quote_request->vehicle_register_date)),
                'registrationlocation' => $rto_data->rto_name,
                'regilocother' => $rto_data->rto_name,
                'carryingcapacity' => $mmv_data->carryingcapacity,
                'cubiccapacity' => !empty($mmv_data->cubiccapacity) ? $mmv_data->cubiccapacity : ($is_gcv && !empty($mmv_data->vehiclegvw) ? $mmv_data->vehiclegvw : 0),
                'yearmanf' => date('Y', strtotime('01-' . $proposal->vehicle_manf_year)),
                'vehicleidv' => !in_array($premium_type, ['third_party', 'third_party_breakin']) ? (int) $quote_log->idv - $electrical_accessories_sa - $non_electrical_accessories_sa - $lpg_cng_kit_sa : 0,
                'ncb' => $proposal->applicable_ncb,
                'addloading' => '0', //
                'addloadingon' => '0', //
                'elecacctotal' => $electrical_accessories_sa,
                'nonelecacctotal' => $non_electrical_accessories_sa,
                'prvpolicyref' => $proposal->previous_policy_number,
                'prvexpirydate' => date('d-M-Y', strtotime($proposal->prev_policy_expiry_date)),
                'prvinscompany' => $proposal->previous_insurance_company,
                'prvncb' => $proposal->previous_ncb,
                'prvclaimstatus' => $proposal->is_claim == 'Y' ? '1' : '0',
                'partnertype' => $corporate_vehicles_quote_request->vehicle_owner_type == 'I' ? 'P' : 'I'
            ],
            'accessorieslist' => [
                [
                    'contractid' => '0',
                    'acccategorycode' => '0',
                    'acctypecode' => '0',
                    'accmake' => '0',
                    'accmodel' => '0',
                    'acciev' => '0',
                    'acccount' => '0'
                ]
            ],
            'paddoncoverlist' => $cover_list,
            'motextracover' => [
                'geogextn' => $geo_extension,
                'noofpersonspa' => $no_of_unnamed_passenger,
                'suminsuredpa' => $pa_unnamed_passenger_sa,
                'suminsuredtotalnamedpa' => 0,
                'cngvalue' => $lpg_cng_kit_sa,
                'noofemployeeslle' => '0',
                'noofpersonsllo' => $ll_paid_driver,
                'fibreglassvalue' => '0',
                'sidecarvalue' => '0',
                'nooftrailers' => '0',
                'totaltrailervalue' => '0',
                'voluntaryexcess' => '0',
                'covernoteno' => '',
                'covernotedate' => '',
                'subimdcode' => '',
                'extrafield1' => '',
                'extrafield2' => '',
                'extrafield3' => ''
            ],
            'questlist' => [
                [
                    'questionref' => '',
                    'contractid' => '',
                    'questionval' => ''
                ]
            ],
            'detariffobj' => [
                'vehpurchasetype' => '',
                // 'typ:vehPurchaseDate' => '',
                'monthofmfg' => '',
                'registrationauth' => '',
                'bodytype' => '',
                'goodstranstype' => '',
                'natureofgoods' => '',
                'othergoodsfrequency' => '',
                'permittype' => '',
                'roadtype' => '',
                'vehdrivenby' => '',
                'driverexperience' => '',
                'clmhistcode' => '',
                'incurredclmexpcode' => '',
                'driverqualificationcode' => '',
                'tacmakecode' => '',
                'extcol1' => '',
                'extcol2' => '',
                'extcol3' => '',
                'extcol4' => '',
                'extcol5' => '',
                'extcol6' => '',
                'extcol7' => config('constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR'),
                'extcol8' => $cpa_selected,
                'extcol9' => '',
                'extcol10' => $master_product->product_identifier,
                'extcol11' => '',
                'extcol12' => '',
                'extcol13' => '',
                'extcol14' => '',
                'extcol15' => $proposal->occupation ?? '',
                'extcol16' => '',
                'extcol17' => '',
                'extcol18' => '',
                'extcol19' => '',
                'extcol20' => '',
                'extcol21' => 'Y',
                'extcol22' => '',
                'extcol23' => '',
                'extcol24' => $extCol24,
                'extcol25' => '',
                'extcol26' => '',
                'extcol27' => '',
                'extcol28' => '',
                'extcol29' => '',
                'extcol30' => '',
                'extcol31' => '',
                'extcol32' => '',
                'extcol33' => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
                'extcol34' => '',
                'extcol35' => '',
                'extcol36' => '',
                'extcol37' => '',
                'extcol38' => $extCol38,
                'extcol39' => '',
                'extcol40' => $extCol40
            ],
            'transactionid' => '0',
            'transactiontype' => 'MOTOR_WEBSERVICE',
            'contactno' => '9999912123'
        ];

        if ($corporate_vehicles_quote_request->business_type == 'newbusiness') {
            unset($premium_request['weomotpolicyin']['prvncb']);
        }

        $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PREMIUM_CALCULATION_URL'), $premium_request, 'bajaj_allianz', [
            'enquiryId' => $enquiryId,
            'requestMethod' => 'post',
            'productName'  => $productData->product_name,
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'transaction_type' => 'proposal',
            'contentType' => 'json'
        ]);
        $premium_response = $get_response['response'];
        if ($premium_response) {
            $premium_response = json_decode($premium_response, TRUE);

            if ($premium_response['errorcode'] == 0 && !is_null($premium_response['premiumdetails'])) {
                $basic_od = 0;
                $basic_tp = 0;
                $ncb_discount = 0;
                $cpa = 0;
                $pa_unnamed_passenger = 0;
                $ll_paid_driver = 0;
                $other_discount = 0;
                $non_electrical_accessories = 0;
                $electrical_accessories = 0;
                $lpg_cng_kit_od = 0;
                $lpg_cng_kit_tp = 0;
                $tppd_discount = 0;
                $imt_23 = 0;
                $geo_extension_od_premium = 0;
                $geo_extension_tp_premium = 0;

                if (isset($premium_response['premiumsummerylist']) && !empty($premium_response['premiumsummerylist'])) {
                    foreach ($premium_response['premiumsummerylist'] as $premium) {
                        switch ($premium['paramref']) {
                            case 'OD': // Basic OD
                                $basic_od = round($premium['od']);
                                break;

                            case 'ACT': // Basic TP
                                $basic_tp = round($premium['act']);
                                break;

                            case 'PA_DFT': // CPA
                                $cpa = round($premium['act']);
                                break;

                            case 'NELECACC': // Non-electrical Accessories
                                $non_electrical_accessories = round($premium['od']);
                                break;

                            case 'ELECACC': // Electrical Accesssories
                                $electrical_accessories = round($premium['od']);
                                break;

                            case 'PA': // PA for Unnamed Passenger
                                $pa_unnamed_passenger = round($premium['act']);
                                break;

                            case 'CNG': // External LPG/CNG Kit
                                $lpg_cng_kit_od = round($premium['od']);
                                $lpg_cng_kit_tp = round($premium['act']);
                                break;

                            case 'LLO': // LL Paid Driver
                                $ll_paid_driver = round(abs($premium['act']));
                                break;

                            case 'IMT23': // IMT-23
                                $imt_23 = round(abs($premium['od']));
                                break;

                            case 'TPPD_RES': // TPPD Discount
                                $tppd_discount = round(abs($premium['act']));
                                break;

                            case 'COMMDISC': // Other Discount
                                $other_discount = round(abs($premium['od']));
                                break;
                            
                            case 'GEOG': // Geographical Extention
                                $geo_extension_od_premium = abs($premium['od']);
                                $geo_extension_tp_premium = abs($premium['act']);
                                break;

                            default:
                                break;
                        }
                    }

                    $final_od_premium = $basic_od;
                    $addon_premium = $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od + $imt_23 + $geo_extension_od_premium;
                    $final_tp_premium = $basic_tp + $pa_unnamed_passenger + $lpg_cng_kit_tp + $ll_paid_driver + $tppd_discount + $geo_extension_tp_premium;
                    $ncb_discount = is_null($premium_response['premiumdetails']['ncbamt']) || $premium_response['premiumdetails']['ncbamt'] == 'null' ? 0 : (int) $premium_response['premiumdetails']['ncbamt'];
                    $final_total_discount = $ncb_discount + $other_discount + $tppd_discount;
                    $final_net_premium = $premium_response['premiumdetails']['netpremium'];
                    $final_gst_amount = $premium_response['premiumdetails']['servicetax'];

                    $final_payable_amount = $premium_response['premiumdetails']['finalpremium'];

                    BajajAllianzPremiumDetailController::saveJsonPremiumDetails($get_response['webservice_id']);

                    $is_premium_different = false;
                    $is_premium_to_be_stored = true;
                    // $is_premium_to_be_stored = isNewPremiumToBeStored($final_payable_amount, $proposal);

                    if ($is_premium_to_be_stored) {
                        $is_premium_different = true;

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->update([
                                'proposal_no' => $premium_response['transactionid'],
                                'unique_proposal_id' => $premium_response['transactionid'],
                            ]);

                        ProposerCkycDetails::updateOrCreate([
                            'user_product_journey_id' => $enquiryId
                        ], [
                            'meta_data' => [
                                'last_premium_calculation_timestamp' => strtotime(date('Y-m-d'))
                            ]
                        ]);
                    }
                        //ckyc code verification start
                        $kyc_status = false;
                        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                            if (config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y') {
                                $response = ckycVerifications($proposal, [
                                    'user_id'   => $userid,
                                    'product_code' => '1831',
                                    'is_premium_different' => $is_premium_different
                                ]);
            
                                if ( ! $response['status']) {
                                    return response()->json($response);
                                }

                                $kyc_status = true;
                            } else {
                                $request_data = [
                                    'companyAlias' => 'bajaj_allianz',
                                    'mode' =>  $proposal->ckyc_type == 'ckyc_number' ? 'ckyc_number' :'documents',
                                    'enquiryId' => customEncrypt($enquiryId),
                                    'user_id'   => $userid,
                                    'product_code' => '1831',
                                    'trigger_old_document_flow' => !empty($request['newFlowBajaj']) && $request['newFlowBajaj'] == 'Y' ? 'Y' : 'N',
                                ];

                                $ckycController = new CkycController;
                                $response = $ckycController->ckycVerifications(new Request($request_data));
                                $response = $response->getOriginalContent();
                                if (isset($response['data']['verification_status']) && !$response['data']['verification_status']) {
                                    return response()->json([
                                        'status' => false,
                                        'msg' => 'CKYC verification failed. Try other method',//'Ckyc status is not verified'
                                        'data'    => [
                                            'verification_status' => false
                                        ]
                                    ]);
                                }else{
                                    $kyc_status = true;
                                }
                            }
                        }
                        //end
                } else {
                    return camelCase([
                        'status' => FALSE,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'message' => isset($premium_response[0]['errtext']) ? $premium_response[0]['errtext'] : 'Insurer Not Reachable'
                    ]);
                }
            
                $proposal_request = [
                    'userid' => $userid,
                    'password' => $password,
                    'transactionid' => $premium_response['transactionid'],
                    'custdetails' => [
                        'parttempid' => '',
                        'firstname' => $proposal->first_name,
                        'middlename' => '',
                        'surname' => $proposal->last_name,
                        'addline1' => $proposal->address_line1,
                        'addline2' => $proposal->address_line2 ?? '',
                        'addline3' => $proposal->address_line3 ?? '',
                        'addline5' => $proposal->state,
                        'pincode' => $proposal->pincode,
                        'email' => $proposal->email,
                        'telephone1' => '',
                        'telephone2' => '',
                        'mobile' => $proposal->mobile_number,
                        'delivaryoption' => '',
                        'poladdline1' => $proposal->address_line1,
                        'poladdline2' => $proposal->address_line2 ?? '',
                        'poladdline3' => $proposal->address_line3 ?? '',
                        'poladdline5' => $proposal->state,
                        'polpincode' => $proposal->pincode,
                        'password' => $password,
                        'cptype' => $corporate_vehicles_quote_request->vehicle_owner_type == 'I' ? 'P' : 'I',
                        'profession' => $proposal->occupation ?? '',
                        'dateofbirth' => !is_null($proposal->dob) ? date('d-M-Y', strtotime($proposal->dob)) : '',
                        'availabletime' => '',
                        'institutionname' => ($requestData->vehicle_owner_type == 'C') ? $proposal->first_name : '',
                        'existingyn' => 'N',
                        'loggedin' => '',
                        'mobilealerts' => '',
                        'emailalerts' => '',
                        'title' => $proposal->gender == 'MALE' ? 'MR' : 'MS',
                        'partid' => '',
                        'status1' => '',
                        'status2' => '',
                        'status3' => ''
                    ],
                    'weomotpolicyin' => [
                        'contractid' => "0",
                        'poltype' => $corporate_vehicles_quote_request->business_type == 'newbusiness' ? 1 : 3,
                        'product4digitcode' => in_array($premium_type, ['third_party', 'third_party_breakin']) ? 1831 : 1803,
                        'deptcode' => 18,
                        'branchcode' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_BRANCH_CODE'),
                        'termstartdate' => $policy_start_date,
                        'termenddate' => $policy_end_date,
                        'tpfintype' => !is_null($proposal->financer_agreement_type) ? $proposal->financer_agreement_type : '1',
                        'hypo' => !is_null($proposal->name_of_financer) ? $proposal->name_of_financer : '',
                        'vehicletypecode' => $mmv_data->vehicletypecode,
                        'vehicletype' => 'Commercial Vehicle',
                        'miscvehtype' => 0,
                        'vehiclemakecode' => $mmv_data->vehiclemakecode,
                        'vehiclemake' => $mmv_data->vehiclemake,
                        'vehiclemodelcode' => $mmv_data->vehiclemodelcode,
                        'vehiclemodel' => $mmv_data->vehiclemodel,
                        'vehiclesubtypecode' => $mmv_data->vehiclesubtypecode,
                        'vehiclesubtype' => $mmv_data->vehiclesubtype,
                        'fuel' => $mmv_data->fuel,
                        'zone' => $zone,
                        'engineno' => $proposal->engine_number,
                        'chassisno' => $proposal->chassis_number,
                        'registrationno' => $corporate_vehicles_quote_request->business_type == 'newbusiness' ? 'NEW' : str_replace('-', '', $proposal->vehicale_registration_number),
                        'registrationdate' => strtoupper(date('d-M-Y', strtotime($corporate_vehicles_quote_request->vehicle_register_date))),
                        'registrationlocation' => $rto_data->rto_name,
                        'regilocother' => $rto_data->rto_name,
                        'carryingcapacity' => $mmv_data->carryingcapacity,
                        'cubiccapacity' => !empty($mmv_data->cubiccapacity) ? $mmv_data->cubiccapacity : ($is_gcv && !empty($mmv_data->vehiclegvw) ? $mmv_data->vehiclegvw : 0),
                        'yearmanf' => strtoupper(date('Y', strtotime('01-' . $proposal->vehicle_manf_year))),
                        'color' => !is_null($proposal->vehicle_color) ? $proposal->vehicle_color : '',
                        'vehicleidv' => $quote_log->idv,
                        'ncb' => ($corporate_vehicles_quote_request->business_type == 'newbusiness' ? "0" : $proposal->applicable_ncb),
                        'addloading' => '0',
                        'addloadingon' => '0',
                        'spdiscrate' => '0',
                        'elecacctotal' => $electrical_accessories_sa,
                        'nonelecacctotal' => $non_electrical_accessories_sa,
                        'prvpolicyref' => ($corporate_vehicles_quote_request->business_type == 'newbusiness' ? "" : $proposal->previous_policy_number),
                        'prvexpirydate' => ($corporate_vehicles_quote_request->business_type == 'newbusiness' ? "" : strtoupper(date('d-M-Y', strtotime($proposal->prev_policy_expiry_date)))),
                        'prvinscompany' => $proposal->previous_insurance_company,
                        'prvncb' => $proposal->previous_ncb,
                        'prvclaimstatus' => $proposal->is_claim == 'Y' ? '1' : '0',
                        'automembership' => '',
                        'partnertype' => $corporate_vehicles_quote_request->vehicle_owner_type == 'I' ? 'P' : 'I'
                    ],
                    'accessorieslist' => [
                        [
                            'contractid' => '0',
                            'acccategorycode' => '0',
                            'acctypecode' => '0',
                            'accmake' => '',
                            'accmodel' => '',
                            'acciev' => '0',
                            'acccount' => '0'
                        ]
                    ],
                    'paddoncoverlist' => !empty($cover_list) ? $cover_list : [
                        [
                            'paramdesc' => NULL,
                            'paramref' => NULL
                        ]
                    ],
                    'motextracover' => [
                        'geogextn' => '0',
                        'noofpersonspa' => $no_of_unnamed_passenger,
                        'suminsuredpa' => $pa_unnamed_passenger_sa,
                        'suminsuredtotalnamedpa' => 0,
                        'cngvalue' => $lpg_cng_kit_sa,
                        'noofemployeeslle' => '0',
                        'noofpersonsllo' => $ll_paid_driver,
                        'fibreglassvalue' => '0',
                        'sidecarvalue' => '0',
                        'nooftrailers' => '0',
                        'totaltrailervalue' => '0',
                        'voluntaryexcess' => '0',
                        'covernoteno' => '',
                        'covernotedate' => '',
                        'subimdcode' => '',
                        'extrafield1' => '',
                        'extrafield2' => '',
                        'extrafield3' => ''
                    ],
                    'premiumdetails' => [
                        'ncbamt' => '0',
                        'addloadprem' => '0',
                        'totalodpremium' => '0',
                        'totalactpremium' => '0',
                        'totalnetpremium' => '0',
                        'totalpremium' => '0',
                        'netpremium' => '0',
                        'finalpremium' => '0',
                        'spdisc' => '0',
                        'servicetax' => '0',
                        'stampduty' => '0',
                        'collpremium' => '0',
                        'imtout' => '',
                        'totaliev' => '0',
                    ],
                    'premiumsummerylist' => [
                        [
                            'paramdesc' => '0',
                            'paramref' => '0',
                            'paramtype' => '0',
                            'od' => '0',
                            'act' => '0',
                            'net' => '0'
                        ]
                    ],
                    'questlist' => [
                        [
                            'questionref' => '',
                            'contractid' => '',
                            'questionval' => ''
                        ]
                    ],
                    'detariffobj' => [
                        'vehpurchasetype' => '',
                        // 'typ:vehPurchaseDate' => '',
                        'monthofmfg' => '',
                        'registrationauth' => '',
                        'bodytype' => '',
                        'goodstranstype' => '',
                        'natureofgoods' => '',
                        'othergoodsfrequency' => '',
                        'permittype' => '',
                        'roadtype' => '',
                        'vehdrivenby' => '',
                        'driverexperience' => '',
                        'clmhistcode' => '',
                        'incurredclmexpcode' => '',
                        'driverqualificationcode' => '',
                        'tacmakecode' => '',
                        'extcol1' => '',
                        'extcol2' => '',
                        'extcol3' => '',
                        'extcol4' => '',
                        'extcol5' => '',
                        'extcol6' => '',
                        'extcol7' => config("constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR"),
                        'extcol8' => $cpa_selected,
                        'extcol9' => '',
                        'extcol10' => $master_product->product_identifier,
                        'extcol11' => '',
                        'extcol12' => '',
                        'extcol13' => '',
                        'extcol14' => '',
                        'extcol15' => !is_null($proposal->occupation) ? $proposal->occupation : '',
                        'extcol16' => '',
                        'extcol17' => '',
                        'extcol18' => '',
                        'extcol19' => '',
                        'extcol20' => route('cv.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiryId, 'policy_id' => $request['policyId']]),
                        'extcol21' => 'Y', // Discount tag needed for Auto-Rickshaw 3WL
                        'extcol22' => '',
                        'extcol23' => '',
                        'extcol24' => $extCol24,
                        'extcol25' => '',
                        'extcol26' => '',
                        'extcol27' => '',
                        'extcol28' => '',
                        'extcol29' => '',
                        'extcol30' => '',
                        'extcol31' => '',
                        'extcol32' => '',
                        'extcol33' => !is_null($proposal->gst_number) ? $proposal->gst_number : '',
                        'extcol34' => '',
                        'extcol35' => '',
                        'extcol36' => '',
                        'extcol37' => '',
                        'extcol38' => $extCol38,
                        'extcol39' => '',
                        'extcol40' => $extCol40
                    ],
                    'potherdetails' => [
                        'imdcode' => '',
                        'covernoteno' => '',
                        'leadno' => '',
                        'ccecode' => '',
                        'runnercode' => '',
                        'extra1' => 'NEWPG',
                        'extra2' => '',
                        'extra3' => '',
                        'extra4' => '',
                        'extra5' => ''
                    ],
                    'premiumpayerid' => '0',
                    'paymentmode' => 'CC'
                ];

                $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PROPOSAL_SUBMIT_URL'), $proposal_request, 'bajaj_allianz', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Proposal Submit',
                    'transaction_type' => 'proposal',
                    'contentType' => 'json'
                ]);
                $proposal_response = $get_response['response'];

                if ($proposal_response) {
                    $proposal_response = json_decode($proposal_response, TRUE);

                    if ($proposal_response['errorcode'] == 0) {
                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->update([
                                'od_premium' => $final_od_premium,
                                'tp_premium' => $final_tp_premium,
                                'ncb_discount' => $ncb_discount,
                                'addon_premium' => $addon_premium,
                                'total_premium' => $final_net_premium,
                                'service_tax_amount' => $final_gst_amount,
                                'final_payable_amount' => $final_payable_amount,
                                'cpa_premium' => $cpa,
                                'total_discount' => $final_total_discount,
                                'proposal_no' => $premium_response['transactionid'],
                                'unique_proposal_id' => $premium_response['transactionid'],
                                'payment_url' => config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_URL_BAJAJ_ALLIANZ_MOTOR') . '?requestId=' . $premium_response['transactionid'] . '&Username=' . $userid . '&sourceName=WS_MOTOR',
                                'is_policy_issued' => 'N',
                                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                'product_code' => in_array($premium_type, ['third_party', 'third_party_breakin']) ? 1831 : 1803,
                                'ic_vehicle_details' => [
                                    'manufacture_name' => $mmv_data->vehiclemake,
                                    'model_name' => $mmv_data->vehiclemodel,
                                    'version' => $mmv_data->vehiclesubtype,
                                    'fuel_type' => $mmv_data->fuel,
                                    'seating_capacity' => $mmv_data->carryingcapacity + 1,
                                    'carrying_capacity' => $mmv_data->carryingcapacity,
                                    'cubic_capacity' => $mmv_data->cubiccapacity,
                                    'gross_vehicle_weight' => '', //$mmv_data->gross_vehicle_weight,
                                    'vehicle_type' => !$is_gcv ? 'PCV' : 'GCV'
                                ],
                                'is_breakin_case' => 'N',
                                'cpa_ins_comp' => $cPAInsComp,
                                'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                                'cpa_policy_no' => $cPAPolicyNo,
                                'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                                'cpa_sum_insured' => $cPASumInsured,
                                'electrical_accessories' => $electrical_accessories_sa,
                                'non_electrical_accessories' => $non_electrical_accessories_sa,
                            ]);

                        return [
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $premium_response['transactionid'],
                                'finalPayableAmount' => $final_payable_amount,
                                'is_breakin' => 'N',
                                'inspection_number' => '',
                                'kyc_status' => $kyc_status,
                            ]
                        ];
                    } else {
                        return camelCase([
                            'status' => FALSE,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => isset($proposal_response['errorlist'][0]['errtext']) ? $proposal_response['errorlist'][0]['errtext'] : 'Insurer Not Reachable'
                        ]);
                    }
                } else {
                    return camelCase([
                        'status' => FALSE,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Proposal Service Not Reachable'
                    ]);
                }
            } else {
                return camelCase([
                    'status' => FALSE,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => isset($premium_response['errorlist'][0]['errtext']) ? $premium_response['errorlist'][0]['errtext'] : 'Insurer Not Reachable'
                ]);
            }
        } else {
            return camelCase([
                'status' => FALSE,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Premium Service Not Reachable'
            ]);
        }
    }
}
