<?php

namespace App\Http\Controllers\Proposal\Services\Car\V1;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\CkycVerificationTypes;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Car\BajajAllianzPremiumDetailController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\ProposerCkycDetails;
use Illuminate\Support\Str;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/BajajAllianzCkycHelper.php';

class BajajAllianzSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request, $return_request = false)
    {
        $enquiryId = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
        $premium_type = DB::table('master_premium_type')->where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        $is_breakin_case = 'N';

        if ($premium_type == "third_party_breakin") {
            $premium_type = "third_party";
        }

        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d-m-Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
        if ($requestData->business_type == 'newbusiness') {
            $polType = '1';
            $policy_start_date = Carbon::today()->format('d-M-Y');
            // $policy_end_date = date('d-M-Y', strtotime('+3 year -1 day', strtotime($policy_start_date)));
            if ($premium_type == 'comprehensive') {
                $policy_end_date =  Carbon::today()->addYear(1)->subDay(1)->format('d-m-Y');
            } elseif ($premium_type == 'third_party') {
                $policy_end_date =   Carbon::today()->addYear(3)->subDay(1)->format('d-m-Y');
            }
        } else if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $prev_policy_expiry_date = ($requestData->previous_policy_expiry_date == 'New') ? date('d-m-Y') : $requestData->previous_policy_expiry_date;
            $date_diff = get_date_diff('day', $prev_policy_expiry_date);
            if ($date_diff > 0) {
                $policy_start_date = Carbon::today()->addDay(2)->format('d-M-Y');
            } else {
                $policy_start_date = Carbon::parse($prev_policy_expiry_date)->addDay(1)->format('d-M-Y');
            }

            if (($requestData->business_type == 'breakin') && ($premium_type == "own_damage" || $premium_type == "comprehensive")) {
                $policy_start_date = Carbon::today()->addDay(2)->format('d-M-Y');
            }
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
            $polType = '3';
        }

        if ($requestData->business_type == 'breakin') {

            //RID for breakin is T+1
            $policy_start_date = date('d-m-Y', strtotime('+1 day', time()));

            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                //RID for TP breakin is T+3
                $policy_start_date = date('d-m-Y', strtotime('+3 day', time()));
            }

            $policy_end_date = date('d-M-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        ##handling of ncb part is pending
        ##handling of delhi rto is pending
        ##handling of GST is pending extCol33

        $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');
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

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($proposal->prev_policy_expiry_date == 'New' ? date('Y-m-d') : $proposal->prev_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
        // zero depriciation validation
        // if ($car_age > 5 && $productData->zero_dep == '0') {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //     ];
        // }
        // if ($car_age > 15) {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Car age should not be greater than 15 year',
        //     ];
        // }
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();

        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $corporate_vehicles_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();

        $tp_only = ($premium_type == 'third_party') ? 'true' : 'false';
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
        // $cover_data[] = [
        //     'typ:paramDesc' => 'LLO',
        //     'typ:paramRef' => 'LLO',
        // ]; // it was giving error because ll0 is not selected and noOfPersonsLlo is going 0 code moved when selected below block
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

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value) && $value['sumInsured'] != '0') {
                $externalCNGKITSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'CNG',
                    'typ:paramRef' => 'CNG',
                ];
                if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $externalCNGKITSI = 0;
                }
            }
        }
        $voluntary_insurer_discounts = 0;
        $is_tppd_discount = false;
        $is_anti_theft = false;
        foreach ($discounts as $key => $value) {
            if (($tp_only != 'true') && in_array('voluntary_insurer_discounts', $value)) {
                $voluntary_insurer_discounts = isset($value['sumInsured']) ? $value['sumInsured'] : 0;
            }
            if (in_array('TPPD Cover', $value)) {
                $is_tppd_discount = true;
            }
            if (($tp_only != 'true') && in_array('anti-theft device', $value)) {
                $is_anti_theft = true;
            }
        }
        if ($voluntary_insurer_discounts != 0) {
            $cover_data[] = [
                'typ:paramDesc' => 'VOLEX',
                'typ:paramRef' => 'VOLEX',
            ];
        }

        if ($is_tppd_discount) {
            $cover_data[] = [
                'typ:paramDesc' => 'TPPD_RES',
                'typ:paramRef' => 'TPPD_RES',
            ];
        }

        if ($is_anti_theft) {
            $cover_data[] = [
                'typ:paramDesc' => 'ATHEFT',
                'typ:paramRef' => 'ATHEFT',
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
                // $extCol24 = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
                $extCol24 = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : '3';
            } else if ($premium_type != 'own_damage') { ##
                $cpa = 'MCPA';
                $extCol24 = '1';
            }
        } else if ($corporate_vehicles_quotes_request->vehicle_owner_type == 'C') {
            $extCol24 = '';
            $cpa = '';
        }

        if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
            $extCol24 = '1';
            $cpa = 'DRVL'; // Not a valid driving license
        } else if (isset($selected_addons->compulsory_personal_accident[0]['reason'])) {
            $extCol24 = '1';
            $cpa = 'ACPA'; // Already Having CPA with other insurer
        }
        $additional_details = json_decode($proposal->additional_details);

        $prev_policy_details = $additional_details->prepolicy ?? '';

        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = '';
        if (
            !($premium_type == 'own_damage')
            && (isset($selected_addons->compulsory_personal_accident[0]['reason'])
                && $selected_addons->compulsory_personal_accident[0]['reason'] != 'I do not have a valid driving license.'
            )
            && $corporate_vehicles_quotes_request->vehicle_owner_type != 'C'
        ) {
            $cPAInsComp = $prev_policy_details->cPAInsComp ?? '';
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo ?? '';
            $cPASumInsured = $prev_policy_details->cpaSumInsured ?? '';
            $cPAPolicyFmDt = empty($prev_policy_details->cpaPolicyStartDate ?? '') ? '' : Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y');
            $cPAPolicyToDt = empty($prev_policy_details->cpaPolicyEndDate ?? '') ? '' : Carbon::parse($prev_policy_details->cpaPolicyEndDate)->format('d/m/Y');
        }
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
            $HypothecationAddress1 = $vehicleDetails->hypothecationCity;
            $HypothecationCity = $vehicleDetails->hypothecationCity;
        }
        //Hypothecation
        $extCol38 = '';
        if ($requestData->vehicle_owner_type == "I" && isset($proposal->nominee_name) && $proposal->nominee_name != '' && isset($proposal->nominee_relationship) && $proposal->nominee_relationship != '') {
            $extCol38 = '~' . $proposal->nominee_name . '~' . $proposal->nominee_relationship . '';
        }

        if ($premium_type == "third_party") { // Only TP
            $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_TP");
        } else if ($BusinessType == '1') { // New Business
            $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_NEW");
        } else if ($premium_type == 'own_damage') { // Stand Alone OD
            $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE_OD");
            $ods_prev_pol_start_date = date('d-m-Y', strtotime('-1 year +1 day', strtotime($prev_policy_details->prevPolicyExpiryDate)));
            $extCol36 =  (date('d-M-Y', strtotime($ods_prev_pol_start_date)) . '~' . $prev_policy_details->tpInsuranceCompany . '~' . $prev_policy_details->tpInsuranceCompanyName  . '~' . $prev_policy_details->tpInsuranceNumber . '~' . date('d-M-Y', strtotime($prev_policy_details->tpEndDate)) . '~1~3~' . date('d-M-Y', strtotime($prev_policy_details->tpStartDate)) . '~');
        } else { // Comprehensive Rollover
            $product4digitCode = config("IC.BAJAJ_ALLIANZ.V1.CAR.PRODUCT_CODE");
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $extCol40 = '';

        $pUserId = config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME");
        $bajaj_new_tp_url = config("IC.BAJAJ_ALLIANZ.V1.CAR.NEW_TP_URL_ENABLE");

        if (config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
            $extCol40 = 'DNPPS5548E';
            $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS");
        }

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id', $proposal['user_proposal_id'])
            ->where('seller_type', 'P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->idv<= 5000000) {
            if ($pos_data) {
                $extCol40 = $pos_data->pan_no;
                $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_POS");
            }
        }

        if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
            $pUserId = config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_TP");
        }
        $check_rto_name = !Str::is(Str::lower($requestData->rto_city),Str::lower($rto_details->city_name)) ? true : false;
        $chk_rei_name = Str::contains($rto_details->city_name, '/');
        if($chk_rei_name && $check_rto_name)
        {
            $sep_city_name = explode('/',$rto_details->city_name);
            foreach($sep_city_name as $val)
            {
                $city_to_lowercase = Str::lower($val);
                $inp_rto = Str::lower($requestData->rto_city);
                if(Str::is($inp_rto, $city_to_lowercase))
                {
                    $rto_details->city_name = $val;
                    break;
                }
                else
    
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'RTO Name Not Available',
                        'request' => [
                            'message' => 'RTO Name Not Available',
                            'rto_code' => $requestData->rto_code,
                        ],
                    ]; 
                }
            }
        }
    

        $data = [
            'soapenv:Header' => [],
            'soapenv:Body' => [
                'web:calculateMotorPremiumSig' => [
                    'pUserId' => $pUserId, //config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME"),
                    'pPassword' => ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD"),
                    'pVehicleCode' => $mmv_data->vehicle_code,
                    'pCity' => strtoupper($rto_details->city_name),
                    'pWeoMotPolicyIn_inout' => [
                        'typ:contractId' => '0',
                        'typ:polType' => $polType,
                        'typ:product4digitCode' => $product4digitCode,
                        'typ:deptCode' => '18',
                        'typ:branchCode' => config("IC.BAJAJ_ALLIANZ.V1.CAR.BRANCH_OFFICE_CODE"),
                        'typ:termStartDate' => date('d-M-Y', strtotime($policy_start_date)),
                        'typ:termEndDate' => date('d-M-Y', strtotime($policy_end_date)),
                        'typ:tpFinType' => $HypothecationType,
                        'typ:hypo' => $HypothecationBankName,
                        'typ:vehicleTypeCode' => '22',
                        'typ:vehicleType' => $mmv_data->vehicle_type,
                        'typ:miscVehType' => '0',
                        'typ:vehicleMakeCode' => $mmv_data->vehicle_make_code,
                        'typ:vehicleMake' => $mmv_data->vehicle_make,
                        'typ:vehicleModelCode' => $mmv_data->vehicle_model_code,
                        'typ:vehicleModel' => $mmv_data->vehicle_model,
                        'typ:vehicleSubtypeCode' => $mmv_data->vehicle_subtype_code,
                        'typ:vehicleSubtype' => $mmv_data->vehicle_subtype,
                        'typ:fuel' => $mmv_data->fuel,
                        'typ:zone' => $zone,
                        'typ:engineNo' => $proposal->engine_number,
                        'typ:chassisNo' => $proposal->chassis_number,
                        'typ:registrationNo' => $BusinessType == '1' ? "NEW" : str_replace('-', '', implode('', $vehicale_registration_number)),
                        'typ:registrationDate' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                        'typ:registrationLocation' => $rto_details->city_name,
                        'typ:regiLocOther' => $rto_details->city_name,
                        'typ:carryingCapacity' => $mmv_data->carrying_capacity,
                        'typ:cubicCapacity' => $mmv_data->cubic_capacity,
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
                        'typ:noOfPersonsPa' => $PAforUnnamedPassenger ? $mmv_data->carrying_capacity : '',
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
                        'typ:vehPurchaseDate' => !empty($requestData->vehicle_invoice_date) ? date('d-M-Y', strtotime($requestData->vehicle_invoice_date)) : "",
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
                        'typ:extCol7' => '',
                        'typ:extCol8' => $cpa,
                        'typ:extCol9' => '',
                        'typ:extCol10' => $masterProduct->product_identifier,
                        'typ:extCol11' => '',
                        'typ:extCol12' => '',
                        'typ:extCol13' => '',
                        'typ:extCol14' => '',
                        'typ:extCol15' => '',
                        'typ:extCol16' => '',
                        'typ:extCol17' => '',
                        'typ:extCol18' => '', //'CPA',
                        'typ:extCol19' => '',
                        'typ:extCol21' => '',
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
                        'typ:extCol33' => '',
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
        $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
        if($is_renewbuy)
        {
            // $data['soapenv:Body']['web:calculateMotorPremiumSig']['pUserId'] = '';
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pDetariffObj_inout']['typ:extCol40'] = '';
        }
        if ($return_request) {
            return $data;
        }
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Car',
            'method' => 'Premium Calculation',
            'productName' => $masterProduct->product_identifier,
            'product' => 'Private Car',
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
        if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
            $get_response = getWsData(config('IC.BAJAJ_ALLIANZ.V1.CAR.PROPOSAL_TP_URL'), $input_array, 'bajaj_allianz', $additional_data);
        } else {
            $get_response = getWsData(config('IC.BAJAJ_ALLIANZ.V1.CAR.PROPOSAL_URL'), $input_array, 'bajaj_allianz', $additional_data);
        }
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
        
        if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
            $service_response = $response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'];
        } else {
            $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
        }
        
        if ($service_response['pErrorCode_out'] == '0') {
            $basic_od = $pa_owner = $non_electrical_accessories = $electrical_accessories = $pa_unnamed = $ll_paid_driver = $lpg_cng =  $other_discount = $ncb_discount =  $voluntary_deductible = 0;
            $restricted_tppd = 0;
            $antitheft_discount_amount = 0;
            $addons = [];
            if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
                $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];
                if ($premium_type == 'own_damage' && $requestData->is_claim == 'Y' && $productData->zero_dep == '1' && isset($covers['od'])) {
                    $basic_od = ($covers['typ:od']);
                } else {
                    if (isset($covers[0]))
                    {
                        foreach ($covers as $key => $cover) {
                            if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                                $addons['zeroDept'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                                $addons['key_replace'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                                $addons['engine_protect'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') {
                                $basic_od = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                                $tppd = ($cover['typ:act']);
                            } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                                $pa_owner = ($cover['typ:act']);
                            } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                                $non_electrical_accessories = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                                $electrical_accessories = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                                $pa_unnamed = ($cover['typ:act']);
                            } elseif (in_array($cover['typ:paramDesc'], ['LL To Person For Operation/Maintenance(IMT.28/39)', '19LL To Person For Operation/Maintenance(IMT.28/39)'])) {
                                $ll_paid_driver = ($cover['typ:act']);
                            } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                                $lpg_cng = ($cover['typ:od']);
                                $lpg_cng_tp = ($cover['typ:act']);
                            } elseif (in_array($cover['typ:paramDesc'], ['Commercial Discount', 'Commercial Discount3'])) {
                                $other_discount = (abs($cover['typ:od']));
                            } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                                $ncb_discount = (abs($cover['typ:od']));
                            } elseif ($cover['typ:paramDesc'] === 'Personal Baggage Cover') {
                                $addons['lopb'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                                $addons['rsa'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Accident Sheild') {
                                $addons['accident_shield'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Conveyance Benefit') {
                                $addons['conveyance'] = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                                $addons['consumables'] = ($cover['typ:od']);
                            } elseif (in_array($cover['typ:paramDesc'], ['Voluntary Excess (IMT.22 A)', '6Voluntary Excess (IMT.22 A)'])) {
                                $voluntary_deductible = (abs($cover['typ:od']));
                            } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                                $restricted_tppd = ($cover['typ:act']);
                            } elseif (in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)','Anti-Theft Device (IMT.10)'])) {
                                $antitheft_discount_amount = (abs($cover['typ:od']));
                            } elseif(in_array($cover['typ:paramDesc'], ['CHDH Additional Discount/Loading', 'CHDH Additional Discount/Loading '])) 
                            {
                                if ($cover['typ:od'] < 0) 
                                {
                                    $other_discount += (abs($cover['typ:od']));
                                }
                            }
                        }
                    } else{
                        $cover=$covers;
                        if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                            $addons['zeroDept'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                            $addons['key_replace'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                            $addons['engine_protect'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') {
                            $basic_od = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                            $tppd = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                            $pa_owner = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                            $non_electrical_accessories = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                            $electrical_accessories = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                            $pa_unnamed = ($cover['typ:act']);
                        } elseif (in_array($cover['typ:paramDesc'], ['LL To Person For Operation/Maintenance(IMT.28/39)', '19LL To Person For Operation/Maintenance(IMT.28/39)'])) {
                            $ll_paid_driver = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                            $lpg_cng = ($cover['typ:od']);
                            $lpg_cng_tp = ($cover['typ:act']);
                        } elseif (in_array($cover['typ:paramDesc'], ['Commercial Discount', 'Commercial Discount3'])) {
                            $other_discount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                            $ncb_discount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Personal Baggage Cover') {
                            $addons['lopb'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                            $addons['rsa'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Accident Sheild') {
                            $addons['accident_shield'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Conveyance Benefit') {
                            $addons['conveyance'] = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                            $addons['consumables'] = ($cover['typ:od']);
                        } elseif (in_array($cover['typ:paramDesc'], ['Voluntary Excess (IMT.22 A)', '6Voluntary Excess (IMT.22 A)'])) {
                            $voluntary_deductible = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                            $restricted_tppd = ($cover['typ:act']);
                        } elseif (in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)','Anti-Theft Device (IMT.10)'])) {
                            $antitheft_discount_amount = (abs($cover['typ:od']));
                        } elseif(in_array($cover['typ:paramDesc'], ['CHDH Additional Discount/Loading', 'CHDH Additional Discount/Loading '])) 
                        {
                            if ($cover['typ:od'] < 0) 
                            {
                                $other_discount += (abs($cover['typ:od']));
                            }
                        }
                    }
                }

                BajajAllianzPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
            }
            $vehicleDetails = [
                'manufacture_name' => $mmv_data->vehicle_make,
                'model_name' => $mmv_data->vehicle_model,
                'version' => $mmv_data->vehicle_subtype,
                'fuel_type' => $mmv_data->fuel,
                'seating_capacity' => $mmv_data->carrying_capacity,
                'carrying_capacity' => $mmv_data->carrying_capacity,
                'cubic_capacity' => $mmv_data->cubic_capacity,
                'gross_vehicle_weight' => '',
                'vehicle_type' =>  $mmv_data->vehicle_type
            ];

            $is_premium_different = false;
            // $is_premium_to_be_stored = isNewPremiumToBeStored($service_response['premiumDetailsOut_out']['typ:collPremium'], $proposal);
            $is_premium_to_be_stored = true;

            if ($is_premium_to_be_stored) {
                $proposal->proposal_no = $service_response['pTransactionId_inout'];
                $proposal->pol_sys_id = $service_response['pTransactionId_inout'];
                $proposal->ic_vehicle_details = $vehicleDetails;
                $proposal->save();
            }

            $total_discount = $ncb_discount + $other_discount + $voluntary_deductible + $restricted_tppd + $antitheft_discount_amount;

            //checking last addons
            $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IskeyReplace_Cover = $PreviousPolicy_IsroadSideAssistance_Cover = $PreviousPolicy_IsEngine_Cover = $PreviousPolicy_Islopb_Cover = $is_breakin = false;
            if ((env('APP_ENV') == 'local') && $requestData->business_type != 'newbusiness' && !in_array($premium_type,['third_party','third_party_breakin'])) {
                if (!empty($proposal->previous_policy_addons_list)) {
                    $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                    foreach ($previous_policy_addons_list as $key => $value) {
                        if ($key == 'zeroDepreciation' && $value) {
                            $PreviousPolicy_IsZeroDept_Cover = true;
                        } else if ($key == 'keyReplace' && $value) {
                            $PreviousPolicy_IskeyReplace_Cover = true;
                        } else if ($key == 'roadSideAssistance' && $value) {
                            $PreviousPolicy_IsroadSideAssistance_Cover = true;
                        } else if ($key == 'engineProtector' && $value) {
                            $PreviousPolicy_IsEngine_Cover = true;
                        } else if ($key == 'lopb' && $value) {
                            $PreviousPolicy_Islopb_Cover = true;
                        }
                    }
                }

                if (array_key_exists('zeroDept',$addons) && !$PreviousPolicy_IsZeroDept_Cover) {
                    $is_breakin = true;
                }
                if (array_key_exists('key_replace',$addons) && !$PreviousPolicy_IskeyReplace_Cover) {
                    $is_breakin = true;
                }
                if (array_key_exists('rsa',$addons) && !$PreviousPolicy_IsroadSideAssistance_Cover) {
                    $is_breakin = true;
                }
                if (array_key_exists('engine_protect',$addons) && !$PreviousPolicy_IsEngine_Cover) {
                    $is_breakin = true;
                }
                if (array_key_exists('lopb',$addons) && !$PreviousPolicy_Islopb_Cover) {
                    $is_breakin = true;
                }
            }

            if ($is_premium_to_be_stored) {
                $is_premium_different = true;

                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                        'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
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
                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
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
            $kyc_verified_using = null;

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                if (config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y') {
                    $response = ckycVerifications($proposal, [
                        'user_id'   => $pUserId,
                        'product_code' => $product4digitCode,
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
                        'user_id'   => $pUserId,
                        'product_code' => $product4digitCode,
                        'trigger_old_document_flow' => !empty($request['newFlowBajaj']) && $request['newFlowBajaj'] == 'Y' ? 'Y' : 'N',
                    ];

                    $ckycController = new CkycController;
                    $response = $ckycController->ckycVerifications(new Request($request_data));
                    $response = $response->getOriginalContent();
                    if (!isset($response['data']['verification_status']) || !$response['data']['verification_status']) {
                        return response()->json([
                            'status' => false,
                            'msg' => 'CKYC verification failed. Try other method',//'Ckyc status is not verified',
                            'data'    => [
                                'verification_status' => false
                            ] 
                        ]);
                    } else {
                        $kyc_status = true;
                        $kyc_verified_using = $response['ckyc_verified_using'];
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

            $cpType = '';
            if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                $pUserId = config("IC.BAJAJ_ALLIANZ.V1.CAR.USERNAME_TP");
                $cpType = 'P';
            }
        
            $issue_array = [
                'web:issuePolicy' => [
                    'pUserId' => $pUserId, //config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME"),
                    'pPassword' => ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD_TP") : config("IC.BAJAJ_ALLIANZ.V1.CAR.PASSWORD"),
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
                        'typ:surname' => ($user_proposal['owner_type'] == 'I') ? ($user_proposal['last_name'] ?? '.') : '',
                        'typ:addLine1' => $getAddress['address_1'],
                        'typ:addLine2' => $getAddress['address_2'],
                        'typ:addLine3' => $getAddress['address_3'],
                        'typ:addLine5' => $user_proposal['state'],
                        'typ:pincode' => $user_proposal['pincode'],
                        'typ:email' => $user_proposal['email'],
                        'typ:telephone1' => '',
                        'typ:telephone2' => '',
                        'typ:mobile' => $user_proposal['mobile_number'],
                        'typ:delivaryOption' => '',
                        'typ:polAddLine1' => $getAddress['address_1'],
                        'typ:polAddLine2' => $getAddress['address_2'],
                        'typ:polAddLine3' => $getAddress['address_3'],
                        'typ:polAddLine5' => $user_proposal['state'],
                        'typ:polPincode' => $user_proposal['pincode'],
                        'typ:password' => '',
                        'typ:cpType' => $cpType,
                        'typ:profession' => '',
                        'typ:dateOfBirth' =>  date('d-M-Y', strtotime($user_proposal['dob'])),
                        'typ:availableTime' => '',
                        'typ:institutionName' => ($user_proposal['owner_type'] == 'C') ? $user_proposal['first_name'] : '',
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
                        'typ:branchCode' => config("IC.BAJAJ_ALLIANZ.V1.CAR.BRANCH_OFFICE_CODE"),
                        'typ:termStartDate' => date('d-M-Y', strtotime($policy_start_date)),
                        'typ:termEndDate' => date('d-M-Y', strtotime($policy_end_date)),
                        'typ:tpFinType' => '',
                        'typ:hypo' => '',
                        'typ:vehicleTypeCode' => '22',
                        'typ:vehicleType' => $mmv_data->vehicle_type,
                        'typ:miscVehType' => '',
                        'typ:vehicleMakeCode' => $mmv_data->vehicle_make_code,
                        'typ:vehicleMake' => $mmv_data->vehicle_make,
                        'typ:vehicleModelCode' => $mmv_data->vehicle_model_code,
                        'typ:vehicleModel' => $mmv_data->vehicle_model,
                        'typ:vehicleSubtypeCode' => $mmv_data->vehicle_subtype_code,
                        'typ:vehicleSubtype' => $mmv_data->vehicle_subtype,
                        'typ:fuel' => $mmv_data->fuel,
                        'typ:zone' => $zone,
                        'typ:engineNo' => $user_proposal['engine_number'],
                        'typ:chassisNo' => $user_proposal['chassis_number'],
                        'typ:registrationNo' => $BusinessType == '1' ? "NEW" : str_replace('-', '', implode('', $vehicale_registration_number)),
                        'typ:registrationDate' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                        'typ:registrationLocation' => $rto_details->city_name,
                        'typ:regiLocOther' => $rto_details->city_name,
                        'typ:carryingCapacity' => $mmv_data->carrying_capacity,
                        'typ:cubicCapacity' => $mmv_data->cubic_capacity,
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
                        'typ:noOfPersonsPa' => $PAforUnnamedPassenger ? $mmv_data->carrying_capacity : '',
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
                        'typ:vehPurchaseDate' => !empty($requestData->vehicle_invoice_date) ? date('d-M-Y', strtotime($requestData->vehicle_invoice_date)) : "",
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
                        'typ:extCol7' => '',
                        'typ:extCol8' => $cpa,
                        'typ:extCol9' => '',
                        'typ:extCol10' => $masterProduct->product_identifier,
                        'typ:extCol11' => '',
                        'typ:extCol12' => '',
                        'typ:extCol13' => '',
                        'typ:extCol14' => '',
                        'typ:extCol15' => '',
                        'typ:extCol16' => '',
                        'typ:extCol17' => '',
                        'typ:extCol18' => '',
                        'typ:extCol19' => '',
                        'typ:extCol20' => ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? route('car.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiryId, 'policy_id' => $request['policyId']]) . '&' : route('car.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiryId, 'policy_id' => $request['policyId']]) . '?',
                        'typ:extCol21' => '',
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
                        'typ:extCol33' => (isset($extCol33) ? $extCol33 : ''),
                        'typ:extCol34' => '',
                        'typ:extCol35' => '',
                        'typ:extCol36' => (isset($extCol36) ? $extCol36 : ''),
                        'typ:extCol37' => '',
                        'typ:extCol38' => (isset($extCol38) ? $extCol38 : ''),  ##nominee rel
                        'typ:extCol39' => '',
                        'typ:extCol40' => (isset($extCol40) ? $extCol40 : ''), ##pancard
                    ],
                ],
            ];

            $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
            if($is_renewbuy)
            {
                $issue_array['web:issuePolicy']['pMotDetariff']['typ:extCol40'] = '';
            }
            $additional_data = [
                'enquiryId' => $enquiryId,
                'headers' => [
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ],
                'requestMethod' => 'post',
                'requestType' => 'xml',
                'section' => 'Car',
                'method' => 'Proposal Submit',
                'productName' => $masterProduct->product_identifier,
                'product' => 'Private Car',
                'transaction_type' => 'proposal',
                'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:web="http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl" xmlns:typ="http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl/types/"><soapenv:Header />#replace</soapenv:Envelope>',
            ];

            $status = false;

            if ((($requestData->business_type == 'breakin') && ($premium_type == "own_damage" || $premium_type != "third_party")) || $is_breakin) {
                $is_breakin_case = 'Y';
                $pin_number = $service_response['pTransactionId_inout'];
                $pin_veh_number = explode('-', $proposal->vehicale_registration_number);
                $pin_fields['bag:pTransactionId']   = $pin_number;
                $pin_fields['bag:pRegNoPart1']      = $pin_veh_number[0];
                $pin_fields['bag:pRegNoPart2']      = $pin_veh_number[1];
                $pin_fields['bag:pRegNoPart3']      = $pin_veh_number[2];
                $pin_fields['bag:pRegNoPart4']      = $pin_veh_number[3];
                $pin_fields['bag:pUserName']        = $pUserId; //config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME");
                $pin_fields['bag:pFlag']            = $user_proposal['mobile_number'];
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
                    'section' => 'Car',
                    'method' => 'Pin Generation',
                    'product' => 'Private Car',
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

                $get_response = getWsData(config('IC.BAJAJ_ALLIANZ.V1.CAR.END_POINT_URL_PIN_GENERATION'), $input_array, 'bajaj_allianz', $additional_data);
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
                        $issue_array['web:issuePolicy']['pMotDetariff']['typ:extCol6'] = (isset($pPinNumber_out)) ? $pPinNumber_out : '';
                } else {
                    ##what status should be updated
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable - Break-in serivce not working',
                        'stringval11' => $stringval11
                    ]);
                }
            } else {
                $input_array = ArrayToXml::convert($issue_array, 'soapenv:Body');
                if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                    $get_response = getWsData(config('IC.BAJAJ_ALLIANZ.V1.CAR.POLICY_ISSUE_TP_URL'), $input_array, 'bajaj_allianz', $additional_data);
                } else {
                    $get_response = getWsData(config('IC.BAJAJ_ALLIANZ.V1.CAR.POLICY_ISSUE_URL'), $input_array, 'bajaj_allianz', $additional_data);
                }
                $response = $get_response['response'];
                $issue_policy = XmlToArray::convert($response);
                if (isset($issue_policy['env:Body']['m:issuePolicyResponse']['pErrorCode_out']) && $issue_policy['env:Body']['m:issuePolicyResponse']['pErrorCode_out'] != '0') {
                    $error = $issue_policy['env:Body']['m:issuePolicyResponse']['pError_out']['typ:WeoTygeErrorMessageUser'];
                    $error = isset($error['typ:errText']) ? $error['typ:errText'] : $error['0']['typ:errText'];
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' =>  isset($error) ? $error : 'Error occurred. Please try again.',
                    ]);
                }else if(isset($issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pErrorCode_out']) && $issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pErrorCode_out'] != '0')
                {
                    $error = $issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pError_out']['typ:WeoTygeErrorMessageUser'];
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
                    $proposal_no = isset($issue_policy['env:Body']['m:issuePolicyResponse']['motExtraCover_inout']['typ:extraField3']) ? $issue_policy['env:Body']['m:issuePolicyResponse']['motExtraCover_inout']['typ:extraField3']
                    : $issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['motExtraCover_inout']['typ:extraField3'];
                }
            }
            if ($status) {
                // $url = config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_URL_BAJAJ_ALLIANZ_MOTOR') . '?requestId=' . $proposal_no . '&Username=' . config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME") . '&sourceName=WS_MOTOR';
                if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                    $url = $issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pCustDetails_inout']['typ:status1'];
                } else {
                    $source_name = config('IC.BAJAJ_ALLIANZ.V1.CAR.PAYMENT_GATEWAY_SOURCE_NAME') ?? 'WS_MOTOR';
                    $url = config('IC.BAJAJ_ALLIANZ.V1.CAR.PAYMENT_GATEWAY_URL') . '?requestId=' . $proposal_no . '&Username=' . $pUserId . '&sourceName='.$source_name;
                }
                $proposalXml = ArrayToXml::convert($issue_array, 'soapenv:Body');  
                UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([  
                     'additional_details_data' => $proposalXml
                    ]);
                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'payment_url' => $url,
                    ]);

                $user_data['user_product_journey_id'] = $enquiryId;
                $user_data['ic_id'] = $master_policy->insurance_company_id;
                $user_data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                $user_data['proposal_id'] = $proposal->user_proposal_id;
                updateJourneyStage($user_data);

                return response()->json([
                    'status' => true,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => 'Proposal submitted Successfully.',
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $enquiryId,
                        'proposalNo' => $proposal->proposal_no,
                        'finalPayableAmount' => $proposal->final_payable_amount,
                        'is_breakin' => $is_breakin_case,
                        'inspection_number' => (isset($pPinNumber_out)) ? $pPinNumber_out : '',
                        'kyc_status' => $kyc_status,
                        'kyc_verified_using' => $kyc_verified_using
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
            } else if(isset($service_response['pError_out']) && !is_array($service_response['pError_out']))
            {
               $error_msg = $service_response['pError_out']; 
            }
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => $error_msg,
            ];
        }
    }
}
