<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use App\Models\ProposerCkycDetails;
use App\Http\Controllers\Controller;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Bike\BajajAllianzPremiumDetailController;
use Illuminate\Support\Str;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Helpers/CkycHelpers/BajajAllianzCkycHelper.php';

class bajaj_allianzSubmitProposal extends Controller
{
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
        $premium_type = DB::table('master_premium_type')->where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }

        $is_liability   = (($premium_type == 'third_party') ? true : false);
        $is_od          = (($premium_type == 'own_damage') ? true : false);

        $is_breakin_case = 'N';
        if($requestData->business_type == 'newbusiness') {
            $termStartDate = Carbon::today()->format('d-M-Y');
            $policy_type = "New";
            $BusinessType = '1';

            // $termEndDate = date('d-M-Y', strtotime('+5 year -1 day', strtotime($termStartDate)));
            if ($premium_type == 'comprehensive') {
                $termEndDate =   date('d-M-Y', strtotime('+1 year -1 day', strtotime($termStartDate)));
            } elseif ($premium_type == 'third_party') {
                $termEndDate =   date('d-M-Y', strtotime('+5 year -1 day', strtotime($termStartDate)));
            }
            $polType = '1';
            $product4digitCode = config("constants.motor.bajaj_allianz.PRODUCT_CODE_NEW_BUSINESS_BAJAJ_ALLIANZ_BIKE");;
            $prvInsCompany = '0';
            $no_claim_bonus = '0';
            $date_diff = 0 ;
            if ($premium_type == "third_party") {
                $product4digitCode = config("constants.motor.bajaj_allianz.PRODUCT_CODE_TP_BAJAJ_ALLIANZ_BIKE");
            }
        } else if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
                
            }
            $policy_type = "Rollover";
            $polType = '3';
            $BusinessType = '2';
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_diff > 0 ) 
            {
                if($premium_type == "third_party") {
                    $termStartDate = Carbon::today()->addDay(2)->format('d-M-Y');
                } else {
                    $termStartDate = Carbon::today()->addDay(3)->format('d-M-Y');
                }
                $no_claim_bonus = '0';
            } 
            else {
                $termStartDate = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                $applicable_ncb = $requestData->applicable_ncb;
                $no_claim_bonus = $requestData->previous_ncb;
               
            }
            $additional_details = json_decode($proposal->additional_details);
            $prev_policy_details = $additional_details->prepolicy ?? '';
            
            $termEndDate =  Carbon::parse($termStartDate)->addYear(1)->subDay(1)->format('d-M-Y');
            $product4digitCode =  config("constants.motor.bajaj_allianz.PRODUCT_CODE_BAJAJ_ALLIANZ_BIKE");
            if($premium_type == 'own_damage') {
                ##revisit
                $ods_prev_pol_start_date = date('d-m-Y', strtotime('-1 year +1 day', strtotime($prev_policy_details->prevPolicyExpiryDate)));
                $extCol36 =  (date('d-M-Y', strtotime($ods_prev_pol_start_date)) . '~' . $prev_policy_details->tpInsuranceCompany . '~' . $prev_policy_details->tpInsuranceCompanyName  . '~' . $prev_policy_details->tpInsuranceNumber . '~' . date('d-M-Y', strtotime($prev_policy_details->tpEndDate)) . '~1~5~' . date('d-M-Y', strtotime($prev_policy_details->tpStartDate)) . '~') ;

                $product4digitCode =  config("constants.motor.bajaj_allianz.PRODUCT_CODE_OD_BAJAJ_ALLIANZ_BIKE");
            }
            if ($premium_type == "third_party") {
                $product4digitCode = config("constants.motor.bajaj_allianz.PRODUCT_CODE_TP_BAJAJ_ALLIANZ_BIKE");
            }
        }
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
        $applicable_ncb = 0;

        if (($date_diff <= 90 )) 
        {
            if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
                $applicable_ncb = $requestData->applicable_ncb;
                $no_claim_bonus = $requestData->previous_ncb;
            } else {
                $no_claim_bonus = '0';
            }
        } else {
            $no_claim_bonus = '0';
        }

        if($premium_type == "third_party") 
        {
            $no_claim_bonus = '0';
        }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $applicable_ncb = ($premium_type == "third_party") ? '0' : $applicable_ncb;
        $no_claim_bonus = ($premium_type == "third_party") ? '0' : $no_claim_bonus;
        $cover_data = [];
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $LLtoPaidDriverYN = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = 0;

        $NonElectricalaccessSI = $ElectricalaccessSI = 0;

        $is_electrical = $is_non_electrical = false;

        foreach ($accessories as $key => $value) {
            if(!$is_liability)
            {
                if (in_array('Electrical Accessories', $value) && $value['sumInsured'] != '0') {
                    $ElectricalaccessSI = $value['sumInsured'];
                    $is_electrical = true;
                    $cover_data[] = [
                        'typ:paramDesc' => 'ELECACC',
                        'typ:paramRef' => 'ELECACC',
                    ];
                }

                if (in_array('Non-Electrical Accessories', $value) && $value['sumInsured'] != '0') {
                    $NonElectricalaccessSI = $value['sumInsured'];
                    $is_non_electrical = true;
                    $cover_data[] = [
                        'typ:paramDesc' => 'NELECACC',
                        'typ:paramRef' => 'NELECACC',
                    ];
                }
            }
        }

        foreach($additional_covers as $key => $value) {
            /*
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = 1;
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
            // */
            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'PA',
                    'typ:paramRef' => 'PA',
                ];
            }
        }

        $voluntary_insurer_discounts = 0;
        $is_anti_theft = false;
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
            if (!$is_liability && in_array('anti-theft device', $value)) {
                $is_anti_theft = true;
            }
        }
        if ($voluntary_insurer_discounts != 0) {
            $cover_data[] = [
                'typ:paramDesc' => 'VOLEX',
                'typ:paramRef' => 'VOLEX',
            ];
        }
        if ($is_anti_theft) {
             $cover_data[] = [
                'typ:paramDesc' => 'ATHEFT',
                'typ:paramRef' => 'ATHEFT',
            ];
        }

        $rto_code = $requestData->rto_code;
        // Re-arrange for Delhi RTO code - start
        $rto_code = explode('-', $rto_code);
        if ((int) $rto_code[1] < 10) {
            $rto_code[1] = '0' . (int) $rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        // Re-arrange for Delhi RTO code - End

        $rto_details = DB::table('bajaj_allianz_master_rto')->where('registration_code', str_replace('-', '', $requestData->rto_code))->first();
        if(empty($rto_details))
        {
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
        $extCol40 = (isset($proposal->pan_number) ? $proposal->pan_number : '');
        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pUserId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE");
        $bajaj_new_tp_url = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE");
        $tp_only = ($premium_type == 'third_party') ? 'true' : 'false';
        
        if (config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
            $extCol40 = 'DNPPS5548E';
            $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP") : config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_POS");
        }

        $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type','P')
                    ->first();
        
        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $extCol40   = $pos_data->pan_no;
                $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP") : config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_POS");
            }
        }
        else if(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
            $extCol40 = 'DNPPS5548E';
            $pUserId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE_POS");
        }else {
            $extCol40 = '';
        }

        $pPassword = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PASSWORD_TP") : config("constants.motor.bajaj_allianz.AUTH_PASS_BAJAJ_ALLIANZ_BIKE");
        $branch = config("constants.motor.bajaj_allianz.BRANCH_OFFICE_CODE_BAJAJ_ALLIANZ_BIKE");

        $additional_details = json_decode($proposal->additional_details);

        $prev_policy_details = $additional_details->prepolicy ?? '';

        $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cover_key = '';
        $OD = $pa_owner = $pa_unnamed = $liabilities = $tppd = $discount = $ncb_discount = $total_addon_premium = $voluntary_deductible = $antitheft_discount_amount = $rsa_amount = $zero_dep =  $additionalDiscount = $ExtraPremiumForRejectedRTO = 0;
        if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $requestData->vehicle_owner_type != 'C' && $selected_addons->compulsory_personal_accident[0]['reason'] != "I do not have a valid driving license." && $premium_type != 'own_damage') {
            $cPAInsComp = $prev_policy_details->cPAInsComp ?? '';
            $cPAPolicyNo = $prev_policy_details->cPAPolicyNo ?? '';
            $cPASumInsured = $prev_policy_details->cpaSumInsured ?? '';
            $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y') : '';
            $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y') : '';
        }

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

        if ($proposal->owner_type == "I" && isset($proposal->nominee_name) && $proposal->nominee_name != '' && isset($proposal->nominee_relationship) && $proposal->nominee_relationship != '') {
            $extCol38 = '~' . $proposal->nominee_name . '~' . $proposal->nominee_relationship . '';
        }
 
        $extCol24 = '';
        if ($proposal->owner_type == "I") {
            if($quote_log->premium_json['businessType'] == 'New Business') {
                $cpa = 'MCPA';
                // $extCol24 = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
                $extCol24 = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
            } else if ($premium_type != 'own_damage') { ##
                $cpa = 'MCPA';
                $extCol24 = '1';
            }
        } else if ($proposal->owner_type == 'C') {
            $extCol24 = '';
            $cpa = '';
        }

        if (isset($selected_addons->compulsory_personal_accident[0]['reason']) && $selected_addons->compulsory_personal_accident[0]['reason'] == "I do not have a valid driving license.") {
            $extCol24 = '1';
            $cpa = 'DRVL'; // Not a valid driving license
        } else if(isset($selected_addons->compulsory_personal_accident[0]['reason'])) {
            $extCol24 = '1';
            $cpa = 'ACPA'; // Already Having CPA with other insurer
        }

        $noPreviousPolicyData = ($requestData->previous_policy_type == 'Not sure');
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

        if($requestData->previous_policy_type == 'Not sure')
            {
                $no_claim_bonus = $applicable_ncb = 0;
            }
    
       
        $data = [
            'soapenv:Header' => [],
            'soapenv:Body' => [
                'web:calculateMotorPremiumSig' => [
                    'pUserId' => $pUserId,
                    'pPassword' => $pPassword,
                    'pVehicleCode' => $mmv_data->vehicle_code,
                    'pCity' => strtoupper($rto_details->city_name),
                    'pWeoMotPolicyIn_inout' => [
                        'typ:contractId' => '0',
                        'typ:polType' => $polType,
                        'typ:product4digitCode' => $product4digitCode,
                        'typ:deptCode' => '18',
                        'typ:branchCode' => $branch,
                        'typ:termStartDate' => $termStartDate,
                        'typ:termEndDate' => $termEndDate,
                        'typ:tpFinType' => '',
                        'typ:hypo' => $HypothecationBankName,
                        'typ:vehicleTypeCode' => '21',// 22 is for car and 21 is for two-wheeler
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
                        'typ:cubicCapacity' =>$mmv_data->cubic_capacity,
                        'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                        'typ:color' => $proposal->vehicle_color,
                        'typ:vehicleIdv' => $quote_log->idv,
                        'typ:ncb' => $applicable_ncb,
                        'typ:addLoading' => '0',
                        'typ:addLoadingOn' => '0',
                        'typ:spDiscRate' => '0',
                        'typ:elecAccTotal' => $ElectricalaccessSI,
                        'typ:nonElecAccTotal' => $NonElectricalaccessSI,
                        'typ:prvPolicyRef' => '',
                        'typ:prvExpiryDate' => (($BusinessType == '1' || $noPreviousPolicyData) ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                        'typ:prvInsCompany' => ($noPreviousPolicyData ? '' : $proposal->previous_insurance_company),
                        'typ:prvNcb' => $no_claim_bonus,
                        'typ:prvClaimStatus' => (($requestData->is_claim == 'Y') ? '1' : '0'),
                        'typ:autoMembership' => '',
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
                        'typ:cngValue' => '',
                        'typ:noOfEmployeesLle' => '',
                        'typ:noOfPersonsLlo' =>  $LLtoPaidDriverYN,
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
                        'typ:extCol10' => $masterProduct->product_identifier, //(($zero_dep == 0) ? 'DRIVE_ASSURE_PACK' : ''),
                        'typ:extCol11' => '',
                        'typ:extCol12' => '',
                        'typ:extCol13' => '',
                        'typ:extCol14' => '',
                        'typ:extCol15' => '',
                        'typ:extCol16' => '',
                        'typ:extCol17' => '',
                        'typ:extCol18' => '',
                        'typ:extCol19' => '',
                        'typ:extCol21' => '',
                        'typ:extCol20' => '',
                        'typ:extCol22' => '',
                        'typ:extCol23' => '',
                        'typ:extCol24' => $extCol24,
                        'typ:extCol25' => '',
                        'typ:extCol26' => '',
                        'typ:extCol29' => '',
                        'typ:extCol27' => '',
                        'typ:extCol28' => '',
                        'typ:extCol30' => '',
                        'typ:extCol31' => '',
                        'typ:extCol32' => '',
                        'typ:extCol33' => (isset($extCol33) ? $extCol33 : ''),
                        'typ:extCol34' => $proposal->pincode, // 
                        'typ:extCol35' => '',
                        'typ:extCol36' => (isset($extCol36) ? $extCol36 : ''),
                        'typ:extCol37' => '',
                        'typ:extCol38' => $extCol38,
                        'typ:extCol39' => '',
                        'typ:extCol40' => $extCol40,  //$POS_code,
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
            'section' => 'Bike',
            'method' => 'Premium Calculation',
            'productName' => $productData->product_name . ' - ' . $masterProduct->product_identifier,
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
            $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PROPOSAL_TP_URL'), $input_array, 'bajaj_allianz', $additional_data);
        } else {
            $get_response = getWsData(config('constants.motor.bajaj_allianz.PROPOSAL_END_POINT_URL_BAJAJ_ALLIANZ_BIKE'), $input_array, 'bajaj_allianz', $additional_data);
        }
        $response = $get_response['response'];
        if (empty($response)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $response = XmlToArray::convert($response);
        if(isset($response['env:Body']['m:calculateMotorPremiumSigResponse']) || isset($response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'])) {
            if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                $service_response = $response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'];
            } else {
                $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
            }
        } else {
            return response()->json([
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Insurer not reachable',

            ]);
        }

        if ($service_response['pErrorCode_out'] == '0') {
            $basic_od = $pa_owner = $non_electrical_accessories = $electrical_accessories = $total_discount = $pa_unnamed = $ll_paid_driver = $lpg_cng =  $other_discount = $ncb_discount =  $voluntary_deductible = $restricted_tppd = 0;

            $non_electrical_accessories = $electrical_accessories = $antitheft_discount_amount = 0;

            $addons = [];
            if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
                $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];
                $finalPremium = $service_response['premiumDetailsOut_out'];
                if (!is_array($covers) || !isset($covers[0])) {
                    $covers = [$covers];
                }

                if($quote_log->premium_json['businessType'] == 'New Business') { // new business
                    if($productData->zero_dep == '1' || $productData->zero_dep == 'NA') {
                        if (!isset($covers[0])) {
                            $OD = ($covers['typ:od']);
                        } else {
                            $returned_data = self::common($covers,$productData->zero_dep); 
                        }
                    } else {
                        $returned_data = self::common($covers,$productData->zero_dep);
                    }
                } else { // roll over 
                    if($premium_type == 'own_damage' && $requestData->is_claim == 'Y') {
                        if ($productData->zero_dep == '1' || $productData->zero_dep == 'NA') { //non_zero_dep'
                            foreach($covers as $key => $value) {
                                if($value['typ:paramDesc'] == 'Basic Own Damage')
                                {
                                 $OD = ($value['typ:od']);
                                }
                            }
                        } else {
                            $returned_data = self::common($covers,$productData->zero_dep);
                        }
                    } else {
                        if (!isset($covers[0])) {
                            $tppd = ($covers['typ:act']);
                        } else {
                            $returned_data = self::common($covers,$productData->zero_dep);
                        }
                    }
                }
                if(isset($returned_data)){
                    extract($returned_data);
                }

                BajajAllianzPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                $total_addon_premium = $cover_key;
                $ExtraPremiumForRejectedRTO =  is_array($service_response['pDetariffObj_inout']['typ:extCol22']) ? 0 : $service_response['pDetariffObj_inout']['typ:extCol22'];
                $total_discount = $discount + $additionalDiscount + $ncb_discount + $voluntary_deductible + $restricted_tppd + $antitheft_discount_amount;
                $addLoadPrem = isset($service_response['premiumDetailsOut_out']['typ:addLoadPrem']) ? $service_response['premiumDetailsOut_out']['typ:addLoadPrem'] : 0;
                $tp_premium = $service_response['premiumDetailsOut_out']['typ:totalActPremium'];  //+ $restricted_tppd;
                $addon_premium = '';
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
                $is_premium_to_be_stored = true;
                // $is_premium_to_be_stored = isNewPremiumToBeStored($service_response['premiumDetailsOut_out']['typ:collPremium'], $proposal);

                if ($is_premium_to_be_stored) {
                    $is_premium_different = true;
                    $proposal->proposal_no = $service_response['pTransactionId_inout'];
                    $proposal->pol_sys_id = $service_response['pTransactionId_inout'];
                    $proposal->ic_vehicle_details = $vehicleDetails;
                    $proposal->save();

                    UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $termStartDate))),
                        'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $termEndDate))),
                        'proposal_no' => $proposal->proposal_no,
                        'unique_proposal_id' => $proposal->proposal_no,
                        'od_premium' =>  $service_response['premiumDetailsOut_out']['typ:totalOdPremium'],//($OD),
                        'tp_premium' => $service_response['premiumDetailsOut_out']['typ:totalActPremium'],//$tp_premium,
                        'addon_premium' => array_sum($addons), ##
                        'cpa_premium' => $pa_owner,
                        'final_premium' => $service_response['premiumDetailsOut_out']['typ:totalPremium'],
                        'total_premium' => $service_response['premiumDetailsOut_out']['typ:totalPremium'],
                        'service_tax_amount' => $service_response['premiumDetailsOut_out']['typ:serviceTax'],
                        'final_payable_amount' => $service_response['premiumDetailsOut_out']['typ:collPremium'],
                        'product_code' => $product4digitCode,
                        'ic_vehicle_details' => json_encode($vehicleDetails),
                        'ncb_discount' => $ncb_discount,
                        'total_discount' => ($total_discount),
                        'cpa_ins_comp' => $cPAInsComp,
                        'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                        'cpa_policy_no' => $cPAPolicyNo,
                        'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                        'cpa_sum_insured' => $cPASumInsured,
                        'electrical_accessories' => 0,
                        'non_electrical_accessories' => 0,
                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$termStartDate))),
                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/','-',$termStartDate)))) : date('d-m-Y',strtotime(str_replace('/','-',$termEndDate)))),
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
                                'msg' => 'CKYC verification failed. Try other method',//'Ckyc status is not verified'
                                'data' => [
                                    'verification_status' => false
                                ]
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
                    'soapenv:Header' => [],
                    'soapenv:Body'   => [
                        'web:issuePolicy' => [
                            'pUserId' => $pUserId,
                            'pPassword' => $pPassword,
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
                                // 'typ:firstName' => ($user_proposal['owner_type'] == "C") ? $user_proposal['first_name'] : $user_proposal['first_name'],
                                'typ:firstName' => $user_proposal['first_name'],
                                'typ:middleName' => '',
                                'typ:surname' => ($user_proposal['owner_type'] == 'I') ? $user_proposal['last_name'] : '',
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
                                'typ:cpType' => ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? 'P' : '',
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
                                'typ:branchCode' => config("constants.motor.bajaj_allianz.BRANCH_OFFICE_CODE_BAJAJ_ALLIANZ_MOTOR"),
                                'typ:termStartDate' => date('d-M-Y', strtotime($termStartDate)),
                                'typ:termEndDate' => date('d-M-Y', strtotime($termEndDate)),
                                'typ:tpFinType' => '',
                                'typ:hypo' => $HypothecationBankName,
                                'typ:vehicleTypeCode' => '21',
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
                                'typ:registrationDate' =>date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                                'typ:registrationLocation' => $rto_details->city_name,
                                'typ:regiLocOther' => $rto_details->city_name,
                                'typ:carryingCapacity' => $mmv_data->carrying_capacity,
                                'typ:cubicCapacity' => $mmv_data->cubic_capacity,
                                'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                                'typ:color' => $user_proposal['vehicle_color'],
                                'typ:vehicleIdv' => $user_proposal['idv'],
                                'typ:ncb' => $applicable_ncb,
                                'typ:addLoading' => '',
                                'typ:addLoadingOn' => '',
                                'typ:spDiscRate' => '',
                                'typ:elecAccTotal' => '',
                                'typ:nonElecAccTotal' => '',
                                'typ:prvPolicyRef' => ($noPreviousPolicyData ? '' : $proposal->previous_policy_number),
                                'typ:prvExpiryDate' => (($BusinessType == '1' || $noPreviousPolicyData) ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                                'typ:prvInsCompany' => ($noPreviousPolicyData ? '' : $proposal->previous_insurance_company),
                                'typ:prvNcb' => $no_claim_bonus,
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
                                'typ:cngValue' =>'',
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
                                'typ:extCol20' => route('bike.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]) . '&',
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
                    ],
                ];

                $additional_data = [
                    'enquiryId' => $enquiryId,
                    'headers' => [
                        'Content-Type' => 'text/xml; charset="utf-8"',
                    ],
                    'requestMethod' => 'post',
                    'requestType' => 'xml',
                    'section' => 'Bike',
                    'method' => 'Proposal Submit',
                    'productName' => $productData->product_name . ' - ' . $masterProduct->product_identifier,
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

                $status = false;
                if(($requestData->business_type == 'breakin') && ($premium_type == "own_damage" || $premium_type != "third_party")) {
                    $is_breakin_case = 'Y';
                } //else {
                $status = true;
                $input_array = ArrayToXml::convert($issue_array, $root);
                if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                    $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_POLICY_ISSUE_TP_URL'), $input_array, 'bajaj_allianz', $additional_data);
                } else {
                    $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY'), $input_array, 'bajaj_allianz', $additional_data);
                }
                $response = $get_response['response'];
                $issue_policy = XmlToArray::convert($response);

                if (isset($issue_policy['env:Body']['m:issuePolicyResponse']['pErrorCode_out']) && $issue_policy['env:Body']['m:issuePolicyResponse']['pErrorCode_out'] != '0') {
                    $error = $issue_policy['env:Body']['m:issuePolicyResponse']['pError_out']['typ:WeoTygeErrorMessageUser'];
                    $error = isset($error['typ:errText']) ? $error['typ:errText'] : $error['0']['typ:errText'];
                    return response()->json([
                        'status' => false,
                        'msg' =>  isset($error) ? $error : 'Error occurred. Please try again.',
                    ]);
                } else if(isset($issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pErrorCode_out']) && $issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pErrorCode_out'] != '0')
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
                //}

                if($status) {
                    if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                        $url = $issue_policy['SOAP-ENV:Body']['m:issuePolicyResponse']['pCustDetails_inout']['typ:status1'];
                    } else {
                        $source_name = config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_BAJAJ_SOURCE_NAME') ?? 'WS_MOTOR';
                        $url = config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_URL_BAJAJ_ALLIANZ_BIKE') . '?requestId=' . $proposal_no . '&Username=' . $pUserId. '&sourceName='.$source_name;
                    }
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
                        'msg' => 'Proposal submitted Successfully.',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $enquiryId,
                            'proposalNo' => $proposal->proposal_no,
                            'finalPayableAmount' => $proposal->final_payable_amount,
                            'is_breakin' => 'N',
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
    } //EO submit

    public static function common($covers,$zero_dep) 
    {
        $data = [];
        foreach ($covers as $key => $cover) {
            if (($zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                $data['cover_key'] = ($cover['typ:od']);
            } elseif (in_array($cover['typ:paramDesc'], ['Basic Own Damage','Basic Own Damage 1'])) {
                $data['OD'] = ($cover['typ:od']);
            } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                $data['tppd'] = ($cover['typ:act']);
            } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                $data['pa_owner'] = ($cover['typ:act']);
            } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                $data['pa_unnamed'] = ($cover['typ:act']);
            } elseif ($cover['typ:paramDesc'] === 'Commercial Discount' || $cover['typ:paramDesc'] === 'Commercial Discount8') {
                $data['discount'] = (abs($cover['typ:od']));
            } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                $data['ncb_discount'] = (abs($cover['typ:od']));
            } elseif ($cover['typ:paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                $data['voluntary_deductible'] = (abs($cover['typ:od']));
            } elseif ($cover['typ:paramDesc'] === 'CHDH Additional Discount/Loading') {
                $data['additionalDiscount'] = (abs($cover['typ:od']));
            } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                $data['restricted_tppd'] = ($cover['typ:act']);
            }
            elseif (in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)']))
            {
                $data['antitheft_discount_amount'] = ($cover['typ:act']);
            } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                $data['non_electrical_accessories']= ($cover['typ:od']);
            } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                $data['electrical_accessories'] = ($cover['typ:od']);
            }
        }
        return $data;
    } //EO common

    public static function renewalSubmit($proposal, $request, $return_request = false)
    {
        $enquiryId = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
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
                ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $premium_type = DB::table('master_premium_type')->where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        if ($premium_type == 'breakin') {
            $premium_type = 'comprehensive';
        }
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
        }
        $is_liability   = (($premium_type == 'third_party') ? true : false);
        $is_od          = (($premium_type == 'own_damage') ? true : false);
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $cpa = '';
        if ($proposal->owner_type == "I") {
            if ($quote_log->premium_json['businessType'] == 'New Business') {
                $cpa = 'MCPA';
                $extCol24 = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
            } else if ($premium_type != 'own_damage') { ##
                $cpa = 'MCPA';
                $extCol24 = '1';
            }
        } else if ($proposal->owner_type == 'C') {
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


        $termStartDate = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $termEndDate =  Carbon::parse($termStartDate)->addYear(1)->subDay(1)->format('d-M-Y');
        
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        
        $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type', 'P')
        ->first();

        $extCol40 = (isset($proposal->pan_number) ? $proposal->pan_number : '');
        $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME");
        if ($is_pos_enabled == 'Y') {
            if (isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                $extCol40 = $pos_data->pan_no;
                $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CAR_USERNAME_POS");
            }
        }
       
        $policy_data =
        [
            "userid" => $pUserId,
            "password" => config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_RENEWAL_PASSWORD'),
            "weomotpolicyin" => [
                "registrationno" => str_replace('-', '', $proposal->vehicale_registration_number),
                "prvpolicyref" => $proposal->previous_policy_number
            ],
            "motextracover" => [],
            "custdetails" => []

        ];
       
        $fetch_url = config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_FETCH_RENEWAL');
        $get_response = getWsData($fetch_url, $policy_data, 'bajaj_allianz', [
            'section' => $productData->product_sub_type_code,
            'method' => 'get_renewal_data',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal'
        ]);
        $data = $get_response['response'];
        $response_data = json_decode($data);
       
        if (isset($response_data->errorcode) && $response_data->errorcode == 0) 
        {
            UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $termStartDate))),
                    'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $termEndDate))),
                    'proposal_no' => $response_data->motextracoverout->extrafield2,
                    'unique_proposal_id' => $response_data->motextracoverout->extrafield2,
                    'final_premium' =>  $response_data->custdetailsout->status1 ?? "",
                    'total_premium' =>  $response_data->custdetailsout->status1 ?? "",
                    'service_tax_amount' => ($response_data->custdetailsout->status3 - $response_data->custdetailsout->status1),
                    'final_payable_amount' =>  $response_data->custdetailsout->status3 ?? ""
                ]);

                ProposerCkycDetails::updateOrCreate([
                    'user_product_journey_id' => $enquiryId
                ], [
                    'meta_data' => [
                        'last_premium_calculation_timestamp' => strtotime(date('Y-m-d'))
                    ]
                ]);
                
                //ckyc code verification start
                $kyc_status =false;
                $kyc_verified_using = null;
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    if (config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y') {
                        $response = ckycVerifications($proposal, [
                            'user_id'   => $pUserId,
                            'product_code' => $response_data->weomotpolicyinout->product4digitcode,
                            'is_premium_different' => true
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
                            'product_code' => $response_data->weomotpolicyinout->product4digitcode
                        ];
        
                        $ckycController = new CkycController;
                        $response = $ckycController->ckycVerifications(new Request($request_data));
                        $response = $response->getOriginalContent();
                        if (isset($response['data']['verification_status']) && !$response['data']['verification_status']) {
                            return response()->json([
                                'status' => false,
                                'msg' => 'CKYC verification failed. Try other method',//'Ckyc status is not verified'
                                'data' => [
                                    'verification_status' => false
                                ]
                            ]);
                        }else{
                            $kyc_status =true;
                            $kyc_verified_using = $response['ckyc_verified_using'];
                        }
                    }
                }
                //end

            $issue_policy_array =
            [
                "userid"=> $pUserId,
                "password"=> config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_RENEWAL_PASSWORD'),
                "transactionid"=> $response_data->motextracoverout->extrafield2,
                // 'pTransactionId_inout' => !empty($proposal->proposal_no) && (config('constants.IcConstants.bajaj_allianz.IS_NEW_FLOW_ENABLED_FOR_BAJAJ_ALLIANZ_CKYC') == 'Y') ? $proposal->proposal_no : '',
                "rcptlist"=> [new \stdClass()]
                ,
                "custdetails"=> [
                    "parttempid"=> $response_data->custdetailsout->partid,
                    "firstname"=> $response_data->custdetailsout->firstname ?? $proposal->first_name,
                    "middlename"=> $response_data->custdetailsout->middlename ?? "" ,
                    "surname"=> $response_data->custdetailsout->surname ??  $proposal->last_name,
                    "addline1"=> $response_data->custdetailsout->addline1 ?? $proposal->address_line1,
                    "addline2"=> $response_data->custdetailsout->addline2 ??  "",
                    "addline3"=> $response_data->custdetailsout->addline3 ?? "",
                    "addline5"=> $response_data->custdetailsout->addline5 ?? "",
                    "pincode"=> $response_data->custdetailsout->pincode ?? $proposal->pincode,
                    "email"=> $response_data->custdetailsout->email ?? $proposal->email,
                    "telephone1"=> $response_data->custdetailsout->telephone1 ?? "0",
                    "telephone2"=> $response_data->custdetailsout->telephone2 ?? "0",
                    "mobile"=> $response_data->custdetailsout->mobile ?? $proposal->mobile_number,
                    "delivaryoption"=> $response_data->custdetailsout->delivaryoption ?? "",
                    "poladdline1"=> $response_data->custdetailsout->poladdline1 ??  $proposal->address_line1,
                    "poladdline2"=> $response_data->custdetailsout->poladdline2 ?? "",
                    "poladdline3"=> $response_data->custdetailsout->poladdline3 ?? "",
                    "poladdline5"=> $response_data->custdetailsout->poladdline5 ?? "",
                    "polpincode"=> $response_data->custdetailsout->polpincode ?? $proposal->pincode,
                    "password"=> null,// $response_data->custdetailsout->password ?? null,
                    "cptype"=> $response_data->custdetailsout->cptype,
                    "profession"=> $response_data->custdetailsout->profession ?? "",
                    "dateofbirth"=> $response_data->custdetailsout->dateofbirth ?? "",
                    "availabletime"=> $response_data->custdetailsout->availabletime ?? "",
                    "institutionname"=> null,//$response_data->custdetailsout->institutionname ?? null,
                    "existingyn"=> $response_data->custdetailsout->existingyn ?? "Y",
                    "loggedin"=> $response_data->custdetailsout->loggedin ?? "",
                    "mobilealerts"=> $response_data->custdetailsout->mobilealerts ?? "",
                    "emailalerts"=> $response_data->custdetailsout->emailalerts ?? "",
                    "title"=> null,//$response_data->custdetailsout->title ?? null,
                    "partid"=> $response_data->custdetailsout->partid,
                    "status1"=> $response_data->custdetailsout->status1 ?? "",
                    "status2"=> $response_data->custdetailsout->status2 ?? "",
                    "status3"=> $response_data->custdetailsout->status3 ?? ""
                ],
                "weomotpolicyin"=> [
                    "contractid"=> $response_data->weomotpolicyinout->contractid,
                    "poltype"=>  $response_data->weomotpolicyinout->poltype,
                    "product4digitcode"=> $response_data->weomotpolicyinout->product4digitcode,
                    "deptcode"=> $response_data->weomotpolicyinout->deptcode,
                    "branchcode"=> $response_data->weomotpolicyinout->branchcode,
                    "termstartdate"=> $termStartDate,
                    "termenddate"=> $termEndDate,
                    "tpfintype"=> "0",//$response_data->weomotpolicyinout->tpfintype,
                    "hypo"=> $response_data->weomotpolicyinout->hypo,
                    "vehicletypecode"=> $response_data->weomotpolicyinout->vehicletypecode,
                    "vehicletype"=> $response_data->weomotpolicyinout->vehicletype,
                    "miscvehtype"=> null,//$response_data->weomotpolicyinout->miscvehtype,
                    "vehiclemakecode"=>$response_data->weomotpolicyinout->vehiclemakecode,
                    "vehiclemake"=> $response_data->weomotpolicyinout->vehiclemake,
                    "vehiclemodelcode"=>$response_data->weomotpolicyinout->vehiclemodelcode,
                    "vehiclemodel"=> $response_data->weomotpolicyinout->vehiclemodel,
                    "vehiclesubtypecode"=> $response_data->weomotpolicyinout->vehiclesubtypecode,
                    "vehiclesubtype"=> $response_data->weomotpolicyinout->vehiclesubtype,
                    "fuel"=> $response_data->weomotpolicyinout->fuel,
                    "zone"=> null,// $response_data->weomotpolicyinout->zone,
                    "engineno"=> $response_data->weomotpolicyinout->engineno,
                    "chassisno"=> $response_data->weomotpolicyinout->chassisno,
                    "registrationno"=> $response_data->weomotpolicyinout->registrationno,
                    "registrationdate"=> $response_data->weomotpolicyinout->registrationdate,
                    "registrationlocation"=>$response_data->weomotpolicyinout->registrationlocation,
                    "regilocother"=>$response_data->weomotpolicyinout->regilocother,
                    "carryingcapacity"=> $response_data->weomotpolicyinout->carryingcapacity,
                    "cubiccapacity"=> $response_data->weomotpolicyinout->cubiccapacity,
                    "yearmanf"=> $response_data->weomotpolicyinout->yearmanf,
                    "color"=> $response_data->weomotpolicyinout->color,
                    "vehicleidv"=> $response_data->weomotpolicyinout->vehicleidv,
                    "ncb"=> $response_data->weomotpolicyinout->ncb,
                    "addloading"=> "0",//$response_data->weomotpolicyinout->addloading,
                    "addloadingon"=> "0",//$response_data->weomotpolicyinout->addloadingon,
                    "spdiscrate"=> "0",//$response_data->weomotpolicyinout->spdiscrate,
                    "elecacctotal"=> $response_data->weomotpolicyinout->elecacctotal,
                    "nonelecacctotal"=> $response_data->weomotpolicyinout->nonelecacctotal,
                    "prvpolicyref"=> $proposal->previous_policy_number,
                    "prvexpirydate"=> date("d-M-Y", strtotime($proposal->prev_policy_expiry_date)),
                    "prvinscompany"=> "6", //bajaj
                    "prvncb"=> $response_data->weomotpolicyinout->prvncb,
                    "prvclaimstatus"=> ($response_data->weomotpolicyinout->prvclaimstatus == "") ? "0" :"",
                    "automembership"=> $response_data->weomotpolicyinout->automembership,
                    "partnertype"=> $response_data->weomotpolicyinout->partnertype
                ],
                "accessorieslist"=> [
                    [
                        "contractid"=> "0",
                        "acccategorycode"=> "0",
                        "acctypecode"=> "0",
                        "accmake"=> "",
                        "accmodel"=> "",
                        "acciev"=> "0",
                        "acccount"=> "0"
                    ]
                ],
                "paddoncoverlist"=> [
                    [
                        "paramdesc"=> null,
                        "paramref"=> null
                    ],
                    [
                        "paramdesc"=> null,
                        "paramref"=> null
                    ]
                ],
                "motextracover"=> [
                    "geogextn"=> "0",//$response_data->motextracoverout->geogextn,
                    "noofpersonspa"=> "1",//$response_data->motextracoverout->noofpersonspa ?? "1",
                    "suminsuredpa"=> "0",//$response_data->motextracoverout->suminsuredpa ?? "0",
                    "suminsuredtotalnamedpa"=> "0",//$response_data->motextracoverout->suminsuredtotalnamedpa ?? null,
                    "cngvalue"=> "0",//$response_data->motextracoverout->cngvalue ?? "0",
                    "noofemployeeslle"=> "0",//$response_data->motextracoverout->noofemployeeslle ?? "0",
                    "noofpersonsllo"=> "0",//$response_data->motextracoverout->noofpersonsllo ?? "0",
                    "fibreglassvalue"=> "0",//$response_data->motextracoverout->fibreglassvalue ?? "0",
                    "sidecarvalue"=> "0",//$response_data->motextracoverout->sidecarvalue ?? "0",
                    "nooftrailers"=> "0",//$response_data->motextracoverout->nooftrailers ?? "0",
                    "totaltrailervalue"=> "0",//$response_data->motextracoverout->totaltrailervalue ?? "0",
                    "voluntaryexcess"=> "0",// $response_data->motextracoverout->voluntaryexcess ?? "0",
                    "covernoteno"=> $response_data->motextracoverout->covernoteno ?? "",
                    "covernotedate"=> $response_data->motextracoverout->covernotedate ?? "",
                    "subimdcode"=> $response_data->motextracoverout->subimdcode ?? "",
                    "extrafield1"=> $response_data->motextracoverout->extrafield1 ?? "",
                    "extrafield2"=> $response_data->motextracoverout->extrafield2 ?? "",
                    "extrafield3"=> $response_data->motextracoverout->extrafield3 ?? ""
                ],
                "premiumdetails"=> [
                    "ncbamt"=> "0",
                    "addloadprem"=> "0",
                    "totalodpremium"=> "0",
                    "totalactpremium"=> "0",
                    "totalnetpremium"=> "0",
                    "totalpremium"=> "0",
                    "netpremium"=> "0",
                    "finalpremium"=> "0",
                    "spdisc"=> "0",
                    "servicetax"=> "0",
                    "stampduty"=> "0",
                    "collpremium"=> "0",
                    "imtout"=> "",
                    "totaliev"=> "0"
                ],
                "premiumsummerylist"=> [
                    [
                        "paramdesc"=> "0",
                        "paramref"=> "0",
                        "paramtype"=> "0",
                        "od"=> "0",
                        "act"=> "0",
                        "net"=> "0"
                    ]
                ],
                "questlist"=> [
                    [
                        "questionref"=> "",
                        "contractid"=> "",
                        "questionval"=> ""
                    ]
                ],
                "detariffobj"=> [
                    "vehpurchasetype"=> "",
                    // 'vehpurchasedate' => !empty($requestData->vehicle_invoice_date) ? date('d-M-Y', strtotime($requestData->vehicle_invoice_date)) : "",
                    "monthofmfg"=> "",
                    "registrationauth"=> "",
                    "bodytype"=> "",
                    "goodstranstype"=> "",
                    "natureofgoods"=> "",
                    "othergoodsfrequency"=> "",
                    "permittype"=> "",
                    "roadtype"=> "",
                    "vehdrivenby"=> "",
                    "driverexperience"=> "",
                    "clmhistcode"=> "",
                    "incurredclmexpcode"=> "",
                    "driverqualificationcode"=> "",
                    "tacmakecode"=> "",
                    "extcol1"=> "",
                    "extcol2"=> "",
                    "extcol3"=> "",
                    "extcol4"=> "",
                    "extcol5"=> "",
                    "extcol6"=> "",
                    "extcol7"=> "",
                    "extcol8"=> $cpa,
                    "extcol9"=> "",
                    "extcol10"=> "",
                    "extcol11"=> "",
                    "extcol12"=> "",
                    "extcol13"=> "",
                    "extcol14"=> "",
                    "extcol15"=> "",
                    "extcol16"=> "",
                    "extcol17"=> "",
                    "extcol18"=> "",
                    "extcol19"=> "",
                    "extcol20"=> route('bike.payment-confirm', ['bajaj_allianz', 'enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]) . '&',
                    "extcol21"=> "",
                    "extcol22"=> "",
                    "extcol23"=> "",
                    "extcol24"=> "",
                    "extcol25"=> "",
                    "extcol26"=> "",
                    "extcol27"=> "",
                    "extcol28"=> "",
                    "extcol29"=> "",
                    "extcol30"=> "",
                    "extcol31"=> "",
                    "extcol32"=> "",
                    "extcol33"=> "",
                    "extcol34"=> "",
                    "extcol35"=> "",
                    "extcol36"=> "",
                    "extcol37"=> "",
                    "extcol38"=> "",
                    "extcol39"=> "",
                    "extcol40"=> (isset($extCol40) ? $extCol40 : ''),
                ],
                "potherdetails"=> [
                    "imdcode"=> "",
                    "covernoteno"=> "",
                    "leadno"=> "",
                    "ccecode"=> "",
                    "runnercode"=> "",
                    "extra1"=> "",
                    "extra2"=> "",
                    "extra3"=> "",
                    "extra4"=> "",
                    "extra5"=> ""
                ],
                "premiumpayerid"=> "0",
                "paymentmode"=> "CC"
            ];
            
            $issue_policy_url = config('constants.IcConstants.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_ISSUE_POLICY_URL_RENEWAL');
            $get_response = getWsData($issue_policy_url, $issue_policy_array, 'bajaj_allianz', [
                'section' => $productData->product_sub_type_code,
                'method' => 'issue_policy',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal'
            ]);
            $data = $get_response['response'];

            if(empty($data))
            {
                return [
                    'status' => false,
                    'premium' => '0',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => !empty($response_data->error->validationMessages) ?? 'Insurer not reachable - Issue Policy API'
                ];
            }
            else 
            {
                $issue_policy_response = json_decode($data);
                if($issue_policy_response->errorcode == 0)
                {
                    $source_name = config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_BAJAJ_SOURCE_NAME') ?? 'WS_MOTOR';
                    $url = config('constants.motor.bajaj_allianz.PAYMENT_GATEWAY_URL_BAJAJ_ALLIANZ_BIKE') . '?requestId=' . $response_data->motextracoverout->extrafield2 . '&Username=' . $pUserId . '&sourceName='.$source_name;
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
                        'msg' => 'Proposal submitted Successfully.',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $enquiryId,
                            'proposalNo' => $proposal->proposal_no,
                            'finalPayableAmount' => $response_data->custdetailsout->status3 ?? "",
                            'kyc_status' => $kyc_status,
                            'kyc_verified_using' => $kyc_verified_using
                        ],
                    ]);
                }
                else 
                {
                    return [
                        'status' => false,
                        'premium' => '0',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => !empty($issue_policy_response->errorlist) ?? $issue_policy_response->errorlist
                    ];
                }
            }
        }
        else 
        {
            return [
                'status' => false,
                'premium' => '0',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => !empty($response_data->error->validationMessages) ?? 'Insurer not reachable - Get Renewal Data API'
            ];
        }
    }

} //EO class
