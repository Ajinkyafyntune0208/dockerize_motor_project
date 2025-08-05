<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\CkycController;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\SelectedAddons;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Carbon\Carbon;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class kotakBreakinSubmitProposal extends Controller
{
    //
    public  static function  submit($proposal,$request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        $mmv = get_mmv_details($productData,$requestData->version_id,'kotak');
        if($mmv['status'] == 1) {
          $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $pan_number = '';

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_flag = false;
        $POS_PAN_NO = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->idv <= 5000000) {
            if($pos_data) {
                $is_pos_flag = true;
                $POS_PAN_NO = $pos_data->pan_no;
            }
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_KOTAK') == 'Y' && $quote_log->idv <= 5000000)
        {
            $POS_PAN_NO    = 'ABGTY8890Z';
        }

        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        $prev_insu_comp_name = $policy_start_date = $cpa_required = $vPAODTenure = $PrevPolicyType = $finance_comp_code = $Financing_Institution_Name = '';
        $isPACoverPaidDriverSelected = $paUnnamedPersonCoverselection = $isIMT28 = $zero_dep = $Roadside_Assistance_Selected = $rti = $EngineProtect = $ConsumableCover = $cpa_required = $cngCoverSelection = $electricalCoveSelection =  $nonElectricalCoverSelection = 'false';
        $isPACoverPaidDriverAmount =  $paUnnamedPersonCoverinsuredAmount =  $vPAODTenure = $cngCoverInsuredAmount = $electricalCoverInsuredAmount = $nonElectricalCoverInsuredAmount = 0 ;
        $is_breakin_case = 'N';
        $policy_start_date = Carbon::parse($proposal->prev_policy_expiry_date)->gte(now()) ? Carbon::parse($proposal->prev_policy_expiry_date)->addDay(1)->format('d/m/Y') : today()->format('d/m/Y');
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');

        if($requestData->business_type != 'newbusiness') {
            $prev_insu_comp_name =  $proposal->insurance_company_name;#$requestData->previous_insurer;
            $prev_comp_code =  $proposal->previous_insurance_company;#$requestData->previous_insurer_code
        }

        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            if ($requestData->previous_policy_type == 'Third-party') {
                $PrevPolicyType = 'LiabilityOnlyPolicy';
            } else {
                $PrevPolicyType = 'ComprehensivePolicy';
                if(in_array($premium_type, ['own_damage', 'own_damage_breakin']))
                {
                    $PrevPolicyType = '1+3';
                }
            }
        }

        if ($requestData->vehicle_owner_type == "C") {
            $cpa_required = 'false';
            $vPAODTenure = '0';
        }
        if ($requestData->business_type == 'newbusiness') {

            $policy_start_date = '';
            $motor_claims_made = '';
            $vMarketMovement = '-10';
            $previousInsurerPolicyExpiryDate = '';
            $bIsNoPrevInsurance = '1';
            $prev_no_claim_bonus_percentage = '0';
            $no_claim_bonus_percentage = '0';
            $cpa_required = 'true';
            $vPAODTenure = '3';
            $policy_start_date = today()->format('d/m/Y');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
        } else if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            $previousInsurerPolicyExpiryDate = strtr(date('d/m/Y', strtotime($requestData->previous_policy_expiry_date)), '-', '/');
            $vMarketMovement = ($requestData->business_type == 'breakin') ? '-6.5' : '-10';
            if ($requestData->is_claim == 'N') {
                $motor_claims_made = '';
                $prev_no_claim_bonus_percentage =  $requestData->previous_ncb;
            } else {
                $motor_claims_made = '1 OD Claim';
                $prev_no_claim_bonus_percentage = '0';
            }
            $bIsNoPrevInsurance = '0';
            $vPAODTenure = '1' ;
        }
        $additional_details = json_decode($proposal->additional_details);
        $vehicleDetails = $additional_details->vehicle;

        if ($vehicleDetails->isVehicleFinance == true) {
            $HypothecationType = $vehicleDetails->financerAgreementType;
            $finance_comp_code = $vehicleDetails->nameOfFinancer;
            $Financing_Institution_Name = DB::table('kotak_financier_master')
                        ->where('code', $finance_comp_code)
                        ->pluck('name')
                        ->first();
        }

        $veh_reg_no = explode('-', isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != 'NEW' ? $requestData->vehicle_registration_no : $requestData->rto_code);
        if (isset($veh_reg_no[2]) && $veh_reg_no[2] != '' && isset($veh_reg_no[3]) && $veh_reg_no[3] != '') {
            if (strlen($veh_reg_no[3]) == 3) {
                $veh_reg_no[3] = '0' . $veh_reg_no[3];
            } else if (strlen($veh_reg_no[3]) == 2) {
                $veh_reg_no[3] = '00' . $veh_reg_no[3];
            } else if (strlen($veh_reg_no[3]) == 1) {
                $veh_reg_no[3] = '000' . $veh_reg_no[3];
            }
            $Veh_Regno = $veh_reg_no[0] . '-' . $veh_reg_no[1] . '-' . $veh_reg_no[2] . '-' . $veh_reg_no[3];
            if (strlen($veh_reg_no[1]) == 1) {
                $new_veh_regno = $veh_reg_no[0] . '-0' . $veh_reg_no[1] . '-' . $veh_reg_no[2] . '-' . $veh_reg_no[3];
            }
        }

        $reg_no = explode('-', isset($proposal->vehicale_registration_number) && $proposal->vehicale_registration_number != 'NEW'  ? $proposal->vehicale_registration_number : $requestData->rto_code);
        if (($reg_no[0] == 'DL') && (strlen($reg_no[1]) < 2)) {
            $permitAgency = $reg_no[0] . '0' . $reg_no[1];
        } else {
            $permitAgency = $reg_no[0] . '' . $reg_no[1];
        }

        $permitAgency = isBhSeries($permitAgency) ? $requestData->rto_code : $permitAgency;
        
        $rto_data = DB::table('kotak_rto_location')
                        ->where('NUM_REGISTRATION_CODE', str_replace('-', '', $permitAgency))
                        ->first();
        $rto_data = keysToLower($rto_data);
        if (isBhSeries($proposal->vehicale_registration_number)) { 

            $reg_no = getRegisterNumberWithHyphen(str_replace('-', '', $proposal->vehicale_registration_number));
            $reg_no = explode('-', $reg_no);
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $compulsory_personal_accident = $selected_addons->compulsory_personal_accident;

        // car age calculation
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = round($age / 12, 2);
       
        $ConsumableCover = 'false';
        $rsa_selected = 'false';
        $rti = 'false';
        $EngineProtect = 'false';
        $isDepreciationCover = 'false';

        $rti_selected = $EngineProtect_selected = $Roadside_Assistance_Selected = $zero_dep_selected =
        $ConsumableCover_selected = 'false';
        foreach($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value)) {
                $isIMT28 = 'true';
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $isPACoverPaidDriverSelected = 'true';
                $isPACoverPaidDriverAmount = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $paUnnamedPersonCoverselection = 'true';
                $paUnnamedPersonCoverinsuredAmount = $value['sumInsured'];
            }
        }

        foreach ($addons as $key => $value) {
            if (in_array('Zero Depreciation', $value)) {
                $zero_dep_selected = 'true';
            }

            if (in_array('Road Side Assistance', $value)) {
                $Roadside_Assistance_Selected = 'true';
            }

            if (in_array('Return To Invoice', $value)) {
                $rti_selected = 'true';
            }

            if (in_array('Engine Protector', $value) ) {
                $EngineProtect_selected = 'true';
            }

            if (in_array('Consumable', $value)) {
                $ConsumableCover_selected = 'true';
            }

        }

        if (trim($productData->product_identifier) == 'Kotak RSA' && ($car_age < 7.99) && $Roadside_Assistance_Selected == 'true') 
        {
            $ConsumableCover = 'false';
            $rsa_selected = 'true';
            $rti = 'false';
            $EngineProtect = 'false';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak CZDR' && ($car_age < 4.49)) {
            $ConsumableCover = 'true';
            $rsa_selected = 'false';
            $rti = 'false';
            $EngineProtect = 'false';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak ECZDR' && ($car_age < 4.49)) {
            $ConsumableCover = 'false';
            $rsa_selected = 'false';
            $rti = 'false';
            $EngineProtect = 'true';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak RTCZDR' && ($car_age < 2.49)) {
            $ConsumableCover = 'false';
            $rsa_selected = 'false';
            $rti = 'true';
            $EngineProtect = 'false';
            $isDepreciationCover = 'false';
        }

        if (!empty($compulsory_personal_accident)) {
            foreach ($compulsory_personal_accident as $key => $data) {
                if (isset($data['reason'])) {
                    $vPAODTenure = '0';
                } else if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                    $tenure = '1';
                    $vPAODTenure = isset($data['tenure']) ? (string) $data['tenure'] : $tenure;
                }
            }
        }

       
        foreach ($accessories as $key => $value) {

            if (in_array('Electrical Accessories', $value) && $value['sumInsured'] < 200000) {
                $electricalCoveSelection = 'true';
                $electricalCoverInsuredAmount = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value) && $value['sumInsured'] < 200000) {
                $nonElectricalCoverSelection = 'true';
                $nonElectricalCoverInsuredAmount = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $cngCoverSelection = 'true';
                $cngCoverInsuredAmount = $value['sumInsured'];
            }

        }
        #addon declration logic
        //checking last addons
        if(env('APP_ENV') == 'local' && $requestData->business_type != 'newbusiness' && !in_array($premium_type,['third_party','third_party_breakin']))
        {
            $PreviousPolicy_IsZeroDept_Cover =  $PreviousPolicy_IsLpgCng_Cover = false;
            if (!empty($proposal->previous_policy_addons_list)) {
                $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                foreach ($previous_policy_addons_list as $key => $value) {
                    if ($key == 'zeroDepreciation' && $value) {
                        $PreviousPolicy_IsZeroDept_Cover = true;
                    } else if ($key == 'externalBiKit' && $value) {
                        $PreviousPolicy_IsLpgCng_Cover = true;
                    }
                }
            }

            #addon declaration logic start 
            if ($zero_dep_selected == 'true' && !$PreviousPolicy_IsZeroDept_Cover) {
                $is_breakin_case = 'Y';
            }
            if ($cngCoverSelection == 'true' && !$PreviousPolicy_IsLpgCng_Cover) {
                $is_breakin_case = 'Y';
            }
        }
        if($premium_type=='breakin' && $requestData->business_type == 'breakin' && $productData->premium_type_code=='breakin')
        {
            $is_breakin_case = 'Y';
        }
        #end of addon declaration logic
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) ){
                    $voluntary_deductible_amount = $value['sumInsured'];
                }
            }
        }


        $PrevInsurer = [
            "vPrevInsurerCode" => ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ? $prev_comp_code : ''),
            "vPrevPolicyType" =>  $PrevPolicyType,
            "vPrevInsurerDescription" => ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ? $prev_insu_comp_name : ''),
        ];

        $tokenData = getKotakTokendetails('motor', $is_pos_flag);

        $token_req_array = [
                'vLoginEmailId' => $tokenData['vLoginEmailId'],
                'vPassword' => $tokenData['vPassword'],
            ];

        $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'), $token_req_array, 'kotak', 
            [
                'Key' => $tokenData['vRanKey'],
                'headers' => [
                    'vRanKey' => $tokenData['vRanKey']
                ],
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'kotak',
                'section' => $productData->product_sub_type_code,
                'method' =>'Token Generation',
                'transaction_type' => 'proposal',
            ]);

        $data = $get_response['response'];
        $user_id = $is_pos_flag ? config('constants.IcConstants.kotak.KOTAK_MOTOR_POS_USERID') : config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID');

        if ($data) {
            $token_response = json_decode($data, true);
            if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                $premium_req_array = [
                    "vIdProof" => "",
                    "vIdProofDetail" => "",
                    "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                    "vIntermediaryName" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE_NAME'),
                    "vManufactureCode" => $mmv_data->manufacturer_code,
                    "vManufactureName" => $mmv_data->manufacturer,
                    "vModelCode" => $mmv_data->num_parent_model_code,
                    "vModelDesc" => $mmv_data->vehicle_model,
                    "vVariantCode" => $mmv_data->variant_code,
                    "vVariantDesc" => $mmv_data->txt_variant,
                    "vModelSegment" => $mmv_data->txt_segment_type,
                    "vSeatingCapacity" => $mmv_data->seating_capacity,
                    "vFuelType" => $mmv_data->txt_fuel,
                    "isLPGCNGChecked" => $cngCoverSelection,
                    "vLPGCNGKitSI" => $cngCoverInsuredAmount,
                    "isElectricalAccessoriesChecked" => $electricalCoveSelection,
                    "vElectricalAccessoriesSI" => $electricalCoverInsuredAmount,
                    "isNonElectricalAccessoriesChecked" => $nonElectricalCoverSelection,
                    "vNonElectricalAccessoriesSI" => $nonElectricalCoverInsuredAmount,
                    "vRegistrationDate" => strtr($requestData->vehicle_register_date, '-', '/'),
                    "vRTOCode" => $rto_data->txt_rto_location_code,
                    "vRTOStateCode" => $rto_data->num_state_code,
                    "vRegistrationCode" => $rto_data->num_registration_code,
                    "vRTOCluster" =>  $rto_data->txt_rto_cluster,
                    "vRegistrationZone" => $rto_data->txt_registration_zone,
                    "vModelCluster" => $mmv_data->txt_model_cluster,
                    "vCubicCapacity" => $mmv_data->cubic_capacity,
                    "isReturnToInvoice" => $rti,
                    "isRoadSideAssistance" => $rsa_selected,
                    "isEngineProtect" => $EngineProtect,
                    "isDepreciationCover" =>  $isDepreciationCover,
                    "nVlntryDedctbleFrDprctnCover" => ($productData->zero_dep == '0' && (isset($voluntary_deductible_zero_dept) && $voluntary_deductible_zero_dept == 'true')) ? '1000' : '0',
                    "isConsumableCover" => $ConsumableCover,
                    "isPACoverUnnamed" => $paUnnamedPersonCoverselection,
                    "vPersonUnnamed" => $paUnnamedPersonCoverselection == 'true' ? $mmv_data->seating_capacity: "0",
                    "vUnNamedSI" => $paUnnamedPersonCoverinsuredAmount,
                    "vMarketMovement" => $vMarketMovement,
                    "isPACoverPaidDriver" => $isPACoverPaidDriverSelected,
                    "vPACoverPaidDriver" => $isPACoverPaidDriverSelected  == 'true' ? "1" : "0", 
                    "vSIPaidDriver" => $isPACoverPaidDriverAmount, 
                    "isIMT28" => $isIMT28, 
                    "isIMT29" => "false", 
                    "vPersonIMT28" => "1",
                    "vPersonIMT29" => "0",
                    "vBusinessType" => $requestData->business_type == 'newbusiness' ? 'N' : 'R',
                    "vPolicyStartDate" => '',//$policy_start_date,
                    "vPreviousPolicyEndDate" => $previousInsurerPolicyExpiryDate,
                    "vProductType" => "ComprehensivePolicy",
                    "vClaimCount" => $motor_claims_made,
                    "vClaimAmount" => 0,
                    "vNCBRate" => $prev_no_claim_bonus_percentage,
                    "vWorkflowId" => "",
                    "vFinalIDV" => $quote_log->idv,
                    "objCustomerDetails" => [
                        "vCustomerType" => $requestData->vehicle_owner_type == "I" ? "I" : "C",
                        "vCustomerLoginId" => $user_id,
                        "vCustomerVoluntaryDeductible" => isset($voluntary_deductible_amount) && $voluntary_deductible_amount != '' ? $voluntary_deductible_amount : 0,
                        "vCustomerGender" => $requestData->vehicle_owner_type == "I" ? ($proposal->gender == 'F' ? 'Female' : 'Male') : "",
                    ],
                    "objPrevInsurer" => $PrevInsurer,
                    "bIsCreditScoreOpted" => "0",
                    "bIsNewCustomer" => "0",
                    "vCSCustomerFirstName" => $requestData->vehicle_owner_type == "I" ? $proposal->first_name : "",
                    "vCSCustomerLastName" => $requestData->vehicle_owner_type == "I" ? $proposal->last_name : "",
                    "dCSCustomerDOB" => $requestData->vehicle_owner_type == "I" ? date('d/m/Y', strtotime(strtr($proposal->dob, '-', '/'))) : "",
                    "vCSCustomerMobileNo" => $requestData->vehicle_owner_type == "I" ? $proposal->mobile_number : '',
                    "vCSCustomerPincode" => $requestData->vehicle_owner_type == "I" ? $proposal->pincode : '',
                    "vCSCustomerIdentityProofType" => "1",
                    "vCSCustomerIdentityProofNumber" => $proposal->pan_number ? $proposal->pan_number : '',
                    "nOfficeCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_OFFICE_CODE'),
                    "vOfficeName" => config('constants.IcConstants.kotak.KOTAK_MOTOR_OFFICE_NAME'),
                    "bIsNoPrevInsurance" => $bIsNoPrevInsurance,
                    "vPreviousYearNCB" => $prev_no_claim_bonus_percentage,
                    "vRegistrationYear" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    "vProductTypeODTP" => $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' ? "1011" : "1063",
                    "vPAODTenure" => $requestData->vehicle_owner_type == 'C' ? '0' : $vPAODTenure,
                    "vPosPanCard" => $POS_PAN_NO, // [env('APP_ENV') == 'local' ? $POS_PAN_NO : "",] <== This will not work on production
                    "IsPartnerRequest" => true,
                ];

                if ($is_pos == 'N') {
                    unset($premium_req_array['vPosPanCard']);
                }

                if($premium_type == "own_damage")
                {
                    $premium_req_array['dPreviousTPPolicyExpiryDate']=date('d/m/Y',strtotime($proposal->tp_end_date));;
                    $premium_req_array['dPreviousTPPolicyStartDate']=date('d/m/Y',strtotime($proposal->tp_start_date));
                    $premium_req_array['vPrevTPInsurerCode']=$proposal->tp_insurance_company;
                    $premium_req_array['vPrevTPInsurerExpiringPolicyNumber']=$proposal->tp_insurance_number;
                    $premium_req_array['vPrevTPInsurerName']=$proposal->tp_insurance_company;
                    $premium_req_array['vProductType']='ODOnly';
                    $premium_req_array['vProductTypeODTP']='';
                    $premium_req_array['nProductCode']="3151";
                    $premium_req_array['vPAODTenure']="0";
                    $premium_req_array['vCustomerPrevPolicyNumber']=$proposal->previous_policy_number;
                }
                if ($premium_type=='own_damage_breakin') {
                    $premium_req_array['dPreviousTPPolicyExpiryDate']=date('d/m/Y',strtotime($proposal->tp_end_date));;
                    $premium_req_array['dPreviousTPPolicyStartDate']=date('d/m/Y',strtotime($proposal->tp_start_date));
                    $premium_req_array['vPrevTPInsurerCode']=$proposal->tp_insurance_company;
                    $premium_req_array['vPrevTPInsurerExpiringPolicyNumber']=$proposal->tp_insurance_number;
                    $premium_req_array['vPrevTPInsurerName']=$proposal->tp_insurance_company;
                    $premium_req_array['nProductCode']="3151";
                    $premium_req_array['vProductTypeODTP']='';
                    $premium_req_array['vPAODTenure']="0";
                    $premium_req_array['vCustomerPrevPolicyNumber']=$proposal->previous_policy_number;
                }

                $kyc_url = '';
                $is_kyc_url_present = false;
                $kyc_message = '';
                $kyc_status = false;

                $ckyc_controller = new CkycController;
                
                if((app()->environment() == 'local') && $proposal->is_ckyc_verified != 'Y' && !empty($proposal->ckyc_reference_id)) {
                    // If ckyc reference id exists, we will try to verify using ckyc_reference_id. If we get 'status' as true, then return, else we will try to verify with other modes 
                    $ckycStatusResponse = $ckyc_controller->ckycVerifications(new Request([
                        'companyAlias' => 'kotak',
                        'enquiryId' => $request['enquiryId'],
                        'mode' => 'ckyc_reference_id',
                        'quoteId' => $proposal->ckyc_reference_id,
                    ]));
                    $ckyc_response = $ckycStatusResponse->getOriginalContent();
                    // dd($ckyc_response);

                    if ($ckyc_response['status']) {
                        $kyc_url = '';
                        $kyc_message = '';
                        $kyc_status = true;

                        $ckyc_response['data']['customer_details'] = [
                            'name' => $ckyc_response['data']['customer_details']['fullName'] ?? null,
                            'mobile' => $ckyc_response['data']['customer_details']['mobileNumber'] ?? null,
                            'dob' => $ckyc_response['data']['customer_details']['dob'] ?? null,
                            'address' => null, // $ckyc_response['data']['customer_details']['address'],
                            'pincode' => null, // $ckyc_response['data']['customer_details']['pincode'],
                            'email' => $ckyc_response['data']['customer_details']['email'] ?? null,
                            'pan_no' => $ckyc_response['data']['customer_details']['panNumber'] ?? null,
                            'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
                        ];

                        $ckyc_response_to_save['response'] = $ckyc_response;

                        $updated_proposal = $ckyc_controller->saveCkycResponseInProposal(new Request([
                            'company_alias' => 'kotak',
                            'trace_id' => $request['enquiryId']
                        ]), $ckyc_response_to_save, $proposal);

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->update($updated_proposal);

                        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                    } else {
                        if(!empty($ckyc_response['data']['meta_data']['KYCStatus'] ?? '')) {
                            if($ckyc_response['data']['meta_data']['KYCStatus'] == 'pending') {
                                return response()->json([
                                    'status'  => false,
                                    'message' => $ckyc_response['data']['message']
                                ]);
                            } else {
                                $kyc_message = 'Your previous KYC request is rejected, retrying with new request';
                            }
                        } else {
                            if ($ckyc_response['data']['message'] == 'Error, no data found') {
                                $kyc_message = 'Your previous KYC request is rejected, retrying with new request';
                            } else {
                                return response()->json([
                                    'status'  => false,
                                    'message' => $ckyc_response['data']['message']
                                ]);
                            }
                        }
                    }
                }
                $kyc_status = $proposal->is_ckyc_verified == 'Y' ? true : false;
              
                if ($proposal->is_ckyc_verified != 'Y') {
                    $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PREMIUM') . '/' . $user_id, $premium_req_array, 'kotak', [
                        'token' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'kotak',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Premium Calculation',
                        'transaction_type' => 'proposal',
                    ]);

                    $data = $get_response['response'];

                    if ($data) 
                    {
                        $premium_res_array = array_change_key_case_recursive(json_decode($data, true));
                        #Private_Car_Proposal service(split service)

                        /* if (config('constants.IS_CKYC_ENABLED') == 'Y' && $proposal->is_ckyc_verified != 'Y') { */
                            if ($premium_res_array['verrormsg'] == 'Success' && $premium_res_array['vnetpremium'] != '') {
                                $od = 0;
                                $rsa = 0;
                                $zero_dep_cover = 0;
                                $consumable = 0;
                                $eng_protect = 0;
                                $rti = 0;
                                $electrical_accessories = 0;
                                $non_electrical_accessories = 0;
                                $lpg_cng = 0;
                                $lpg_cng_tp = 0;
                                $pa_owner = 0;
                                $llpaiddriver = 0;
                                $pa_unnamed = 0;
                                $paid_driver = $paid_driver_tp = 0;
                                $voluntary_deduction_zero_dep = 0;
                                $NCB = 0;
                                $tp = 0;
                                
                                if (isset($premium_res_array['vbasictppremium'])) {
                                    $tp = $premium_res_array['vbasictppremium'];
                                }

                                if (isset($premium_res_array['vpacoverforowndriver'])) {
                                    $pa_owner = ($premium_res_array['vpacoverforowndriver']);
                                }

                                if (isset($premium_res_array['vpaforunnamedpassengerpremium'])) {
                                    $pa_unnamed = ($premium_res_array['vpaforunnamedpassengerpremium']);
                                }

                                if (isset($premium_res_array['vcnglpgkitpremiumtp'])) {
                                    $lpg_cng_tp = ($premium_res_array['vcnglpgkitpremiumtp']);
                                }

                                if (isset($premium_res_array['vpanoofemployeeforpaiddriverpremium'])) {
                                    $paid_driver = ($premium_res_array['vpanoofemployeeforpaiddriverpremium']);
                                }

                                if (isset($premium_res_array['vPaidDriverlegalliability'])) {
                                    $paid_driver_tp = ($premium_res_array['vPaidDriverlegalliability']);
                                }


                                if (isset($premium_res_array['vlegalliabilitypaiddriverno'])) {
                                    $llpaiddriver = ($premium_res_array['vlegalliabilitypaiddriverno']);
                                }

                                if (isset($premium_res_array['vdepreciationcover'])) {
                                    $zero_dep_cover = ($premium_res_array['vdepreciationcover']);
                                }

                                if (isset($premium_res_array['vrsa'])) {
                                    $rsa = ($premium_res_array['vrsa']);
                                }

                                if (isset($premium_res_array['vengineprotect'])) {
                                    $eng_protect = ($premium_res_array['vengineprotect']);
                                }

                                if (isset($premium_res_array['vconsumablecover'])) {
                                    $consumable = ($premium_res_array['vconsumablecover']);
                                }

                                if (isset($premium_res_array['vreturntoinvoice'])) {
                                    $rti = ($premium_res_array['vreturntoinvoice']);
                                }

                                if (isset($premium_res_array['velectronicsi'])) {
                                    $electrical_accessories = ($premium_res_array['velectronicsi']);
                                }

                                if (isset($premium_res_array['vnonelectronicsi'])) {
                                    $non_electrical_accessories = ($premium_res_array['vnonelectronicsi']);
                                }

                                if (isset($premium_res_array['vcnglpgkitpremium'])) {
                                    $lpg_cng = ($premium_res_array['vcnglpgkitpremium']);
                                }

                                if (isset($premium_res_array['vvoluntarydeductiondepwaiver'])) {
                                    $voluntary_deduction_zero_dep = ($premium_res_array['vvoluntarydeductiondepwaiver']);
                                }

                                if (isset($premium_res_array['vowndamagepremium'])) {
                                    $od = ($premium_res_array['vowndamagepremium']);
                                }

                                if (isset($premium_res_array['vncb'])) {
                                    $NCB = ($premium_res_array['vncb']);
                                }

                                $voluntary_deductible_discount =  ($premium_res_array['vvoluntarydeduction'] ? $premium_res_array['vvoluntarydeduction'] : 0);
                                $other_discount = ($productData->zero_dep == 0 ? $voluntary_deduction_zero_dep : '0');
                                $other_discount = $other_discount + $voluntary_deduction_zero_dep;
                                $addon_premium = $zero_dep_cover + $rsa + $eng_protect + $rti + $consumable;
                                $final_payable_amount = round(str_replace("INR ", "", $premium_res_array['vtotalpremium']));
                                $total_discount = $NCB + $other_discount + $voluntary_deductible_discount;
                                $final_od_premium = $od + $addon_premium;
                                $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver  + $paid_driver + $pa_unnamed + $paid_driver_tp;

                                $vehicleDetails = [
                                    'manufacture_name' => $mmv_data->manufacturer,
                                    'model_name' => $mmv_data->vehicle_model,
                                    'version' => $mmv_data->txt_variant,
                                    'fuel_type' => $mmv_data->txt_fuel,
                                    'seating_capacity' => $mmv_data->seating_capacity,
                                    'carrying_capacity' => $mmv_data->seating_capacity - 1,
                                    'cubic_capacity' => $mmv_data->cubic_capacity,
                                    'gross_vehicle_weight' => $mmv_data->gross_vehicle_weight,
                                    'vehicle_type' => 'PRIVATE CAR'
                                ];

                                UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                    ->update([
                                    'od_premium' => $final_od_premium,
                                    'tp_premium' => $final_tp_premium,
                                    'ncb_discount' => $NCB,
                                    'total_discount' => $total_discount,
                                    'addon_premium' => $addon_premium,
                                    'total_premium' => $premium_res_array['vtotalpremium'],
                                    'service_tax_amount' => $premium_res_array['vgstamount'],
                                    'final_payable_amount' => $final_payable_amount,
                                    'cpa_premium' => $premium_res_array['vpacoverforowndriver'],
                                    'customer_id' => $premium_res_array['vworkflowid'], ## workflowid as customer_id
                                    'unique_proposal_id' => $premium_res_array['vquoteid'],
                                    'ic_vehicle_details' => $vehicleDetails,
                                ]);
                            } else {
                                return response()->json([
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => isset($premium_res_array['verrormsg']) ? $premium_res_array['verrormsg'] : 'Error while processing request',
                                ]);
                            }
                    } else {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Insurer not reachable',
                        ]);
                    }
                }

                $vtotalpremium = $premium_res_array['vtotalpremium'] ?? $proposal->total_premium;
                $vquoteid = $premium_res_array['vquoteid'] ?? $proposal->unique_proposal_id;
                $vworkflowid = $premium_res_array['vworkflowid'] ?? $proposal->customer_id;

                if (config('constants.IS_CKYC_ENABLED') == 'Y' && $proposal->is_ckyc_verified != 'Y') {
                    $ckyc_modes = [
                        'ckyc_number' => 'ckyc_number',
                        'pan_card' => 'pan_number_with_dob',
                        'aadhar_card' => 'aadhar'
                    ];

                    $ckyc_response_data = $ckyc_controller->ckycVerifications(new Request([
                        'companyAlias' => 'kotak',
                        'enquiryId' => $request['enquiryId'],
                        'mode' => $ckyc_modes[$proposal->ckyc_type],
                        'quoteId' => $vquoteid
                    ]));

                    $ckyc_response = $ckyc_response_data->getOriginalContent();

                    if ($ckyc_response['status']) {
                        $kyc_url = '';
                        $is_kyc_url_present = false;
                        $kyc_message = '';
                        $kyc_status = true;

                        $ckyc_response['data']['customer_details'] = [
                            'name' => $ckyc_response['data']['customer_details']['fullName'] ?? null,
                            'mobile' => $ckyc_response['data']['customer_details']['mobileNumber'] ?? null,
                            'dob' => $ckyc_response['data']['customer_details']['dob'] ?? null,
                            'address' => null, // $ckyc_response['data']['customer_details']['address'],
                            'pincode' => null, // $ckyc_response['data']['customer_details']['pincode'],
                            'email' => $ckyc_response['data']['customer_details']['email'] ?? null,
                            'pan_no' => $ckyc_response['data']['customer_details']['panNumber'] ?? null,
                            'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
                        ];

                        $ckyc_response_to_save['response'] = $ckyc_response;

                        $updated_proposal = $ckyc_controller->saveCkycResponseInProposal(new Request([
                            'company_alias' => 'kotak',
                            'trace_id' => $request['enquiryId']
                        ]), $ckyc_response_to_save, $proposal);

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->update($updated_proposal);

                        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                    } else {
                        $kyc_url = $ckyc_response['data']['redirection_url'];
                        $is_kyc_url_present = true;
                        $kyc_message = '';
                        $kyc_status = false;

                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update([
                                'additional_details_data' => $ckyc_response['data']['redirection_url'],
                            ]);

                            return response()->json([
                                'status' => true,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => 'CKYC is not verified',
                                'data' => [
                                    'proposalId' => null,
                                    'userProductJourneyId' => $enquiryId,
                                    'proposalNo' => null,
                                    'finalPayableAmount' => $vtotalpremium,
                                    'is_breakin' =>  'N',
                                    'inspection_number' => null,
                                    'kyc_url' => $kyc_url,
                                    'is_kyc_url_present' => $is_kyc_url_present,
                                    'kyc_message' => $kyc_message,
                                    'kyc_status' => $kyc_status,
                                ]
                            ]);
                    }
                }

                if ($kyc_status) {
                    try {
                        //$premium_res_array = array_change_key_case_recursive(json_decode($data, true));
                        #breakin code 
                        if((($requestData->business_type == 'breakin') || ($requestData->previous_policy_type == 'Third-party')) || ($is_breakin_case == 'Y')) {
                            $is_breakin_case = 'Y';
                            $brk_in_req_array = [
                                'agentcode' => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                                'picallername' => config('constants.IcConstants.kotak.BROKER_FULL_NAME'),
                                'picallercontactno' => config('constants.IcConstants.kotak.BROKER_CONTACT'),
                                'vehicletype' => '3',
                                'vehicleregistrationno' => $proposal->vehicale_registration_number,
                                'vehiclemake' => $mmv_data->manufacturer,
                                'vehiclemodel' => $mmv_data->vehicle_model,
                                'vehicleregistrationyear' => date('Y', strtotime('01-' . $proposal->vehicle_manf_year)),
                                'customername' => $requestData->vehicle_owner_type == "I" ? $proposal->first_name . ' ' . $proposal->last_name : $proposal->last_name,
                                'customercontactno' => $proposal->mobile_number,
                                'customeraddress' => $proposal->address_line1 . ' ' . $proposal->address_line2 . ' ' . $proposal->address_line3,
                                'customerpincode' => $proposal->pincode,
                                'pipurpose' => 'Kotak Car Breakin for - ' . (($requestData->vehicle_owner_type == "I") ? ($proposal->first_name . ' ' . $proposal->last_name) : ($proposal->last_name)),
                            ];
                            $additionalData = [
                                'enquiryId' => $enquiryId,
                                'productName' => $productData->product_name,

                            ];
                            // $data = getwsdata(config('constants.IcConstants.kotak.BREAK_ID_GENERATION_KOTAK_MOTOR'), $brk_in_req_array,$additionalData);
                            // if ($data) {
                            //     $brk_in_res_array = $data['response'];
                                // if (isset($brk_in_res_array['status']) && $brk_in_res_array['status'] == 'success' && $brk_in_res_array['pirefno'] != '') {
                                    // $inspection_id = $brk_in_res_array['pirefno'];
                                    $wimwisure = new WimwisureBreakinController();
                                    $payload = [
                                        'user_name' => $proposal->first_name . ($proposal->last_name ? ' ' . $proposal->last_name : ''),
                                        'user_email' => $proposal->email,
                                        'reg_number' => $proposal->vehicale_registration_number,
                                        'mobile_number' => $proposal->mobile_number,
                                        'fuel_type' => strtolower($mmv_data->fyntune_version['fuel_type']),
                                        'enquiry_id' => $enquiryId,
                                        'inspection_number' => "",
                                        'section' => 'cv',
                                        'chassis_number' => $proposal->chassis_number,
                                        'engine_number' => $proposal->engine_number,
                                        'api_key' => config('constants.wimwisure.API_KEY_KOTAK')
                                    ];
                    
                                    // $bagi_ref_no = $brk_in_res_array['pirefno'];
                                    $breakin_data = $wimwisure->WimwisureBreakinIdGen($payload);
                                    if ($breakin_data) {
                                        // dd($breakin_data);
                                        if ($breakin_data->original['status'] == true && ! is_null($breakin_data->original['data']))
                                        {
                                            $wimwisure_case_number = $breakin_data->original['data'];
                                            $wimwisure_breakin_url = json_decode($breakin_data->original['data'], true);
                                            $wimwisure_breakin_url = $wimwisure_breakin_url['Inspectors'][0]['InspectionLink'] ?? null;
                                            $wimwisure_case_number = json_decode($wimwisure_case_number)->ID;
                                            DB::table('cv_breakin_status')->insert([
                                                'user_proposal_id' => $proposal->user_proposal_id,
                                                'ic_id' => $productData->company_id,
                                                'wimwisure_case_number' => $wimwisure_case_number,
                                                'breakin_number' => (isset($wimwisure_case_number)) ? $wimwisure_case_number : '',
                                                'ic_breakin_url' => $wimwisure_breakin_url ?? null,
                                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'updated_at' => date('Y-m-d H:i:s')
                                            ]);
                                              UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                                                ->update([
                                                    'is_breakin_case' => $is_breakin_case,

                                                ]);
        
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
                                // } else {
                                //     ##what status should be updated
                                //     return response()->json([
                                //         'status' => false,
                                //         'webservice_id' => $get_response['webservice_id'],
                                //         'table' => $get_response['table'],
                                //         'message' => isset($brk_in_res_array['message']) ? 'Breakin Service Error - ' . $brk_in_res_array['message'] : 'Insurer not reachable',
                                //     ]);
                                // }
                            // }
                        } else {
                            updateJourneyStage([
                                'user_product_journey_id' => $enquiryId,
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                        }

                        return response()->json([
                            'status' => true,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => 'Proposal Submitted Successfully',
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $enquiryId,
                                'proposalNo' => $proposal->proposal_no,
                                'finalPayableAmount' => $vtotalpremium,
                                'is_breakin' =>  $is_breakin_case,
                                'inspection_number' => (isset($wimwisure_case_number)) ? $wimwisure_case_number : '',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                            ]
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Insurer not reachable : ' . $e->getMessage() ,
                        ]);
                    }
             
            }
            else 
            {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Error in ckyc verification Please Fill Proper KYC Details ',
                ]);
            }
        }
        else {
            return response()->json([
                'status'  => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ]);
            
        }

    }
}
}