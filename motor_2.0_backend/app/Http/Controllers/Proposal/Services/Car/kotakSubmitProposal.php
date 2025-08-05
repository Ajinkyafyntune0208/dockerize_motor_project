<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\CkycController;
use App\Models\WebserviceRequestResponseDataOptionList;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterProduct;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\SelectedAddons;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Carbon\Carbon;
use App\Http\Controllers\Proposal\Services\Car\kotakBreakinSubmitProposal;
use App\Http\Controllers\SyncPremiumDetail\Car\KotakPremiumDetailController;

ini_set('max_execution_time', 300);

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class kotakSubmitProposal extends Controller {

    public static function submit($proposal, $request) {
       
      
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
      
        if(($proposal->business_type == 'breakin' || $proposal->business_type == 'own_damage_breakin') && (config('constants.motorConstant.kotak.IS_BREAKIN_ENABLED') == 'Y') && ($premium_type != 'third_party_breakin'))
        {
            return kotakBreakinSubmitProposal::submit($proposal,$request);
        }

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

        if (in_array('kotak', explode(',', config('POS_DISABLED_ICS')))) {
            $is_pos_flag = false;
            $POS_PAN_NO = '';
        }

        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        $prev_insu_comp_name = $policy_start_date = $cpa_required = $vPAODTenure = $PrevPolicyType = $finance_comp_code = $Financing_Institution_Name = '';
        $isPACoverPaidDriverSelected = $paUnnamedPersonCoverselection = $isIMT28 = $zero_dep = $Roadside_Assistance_Selected = $rti = $EngineProtect = $ConsumableCover = $cpa_required = $cngCoverSelection = $electricalCoveSelection =  $nonElectricalCoverSelection = 'false';
        $isPACoverPaidDriverAmount =  $paUnnamedPersonCoverinsuredAmount =  $vPAODTenure = $cngCoverInsuredAmount = $electricalCoverInsuredAmount = $nonElectricalCoverInsuredAmount = 0 ;
        $is_breakin_case = 'N';
        $policy_start_date = Carbon::parse($proposal->prev_policy_expiry_date)->gte(now()) ? Carbon::parse($proposal->prev_policy_expiry_date)->addDay(1)->format('d/m/Y') : today()->format('d/m/Y');
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
        $tp_start_date = $policy_start_date;
        $tp_end_date = $policy_end_date;

        if($requestData->business_type != 'newbusiness') {
            $prev_insu_comp_name =  $proposal->insurance_company_name;#$requestData->previous_insurer;
            $prev_comp_code =  $proposal->previous_insurance_company;#$requestData->previous_insurer_code
        }

        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            if (in_array($requestData->policy_type, ['third_party_breakin', 'third_party'])) {
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
            $tp_start_date =  $policy_start_date;
            $tp_end_date = today()->addYear(3)->subDay(1)->format('d/m/Y');
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
        // dd($compulsory_personal_accident);

        // car age calculation
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = round($age / 12, 2);
       
        $ConsumableCover = 'false';
        $rsa_selected = 'false';
        $rti = 'false';
        $EngineProtect = 'false';
        $isDepreciationCover = 'false';

        $isKeyReplacement = 'false';
        $isLossPersonalBelongings   = 'false';
        $KeyReplacementSI           = 25000;
        $LossPersonalBelongingsSI   = 10000;
        $tyreSecure = 'false';
        $tyreSecureSI = 0;

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
                $ConsumableCover = 'true';
            }

            if (in_array('Key Replacement', $value)) {
                $isKeyReplacement = 'true';              
                $KeyReplacementSI = 25000;                
            }
            if (in_array('Loss of Personal Belongings', $value)) {
                $isLossPersonalBelongings   = 'true';
                $LossPersonalBelongingsSI   = 10000;               
            }

            if (in_array('Tyre Secure', $value)) {
                $tyreSecure = 'true';
                $tyreSecureSI = Carbon::createFromFormat('d-m-Y',$requestData->vehicle_register_date)->format('Y');      
            }
        }

        if (trim($productData->product_identifier) == 'Kotak RSA' && $Roadside_Assistance_Selected == 'true') 
        {
            $ConsumableCover = 'false';
            $rsa_selected = 'true';
            $rti = 'false';
            $EngineProtect = 'false';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak CZDR') {
            $ConsumableCover = 'true';
            $rsa_selected = 'false';
            $rti = 'false';
            $EngineProtect = 'false';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak ECZDR') {
            $ConsumableCover = 'false';
            $rsa_selected = 'false';
            $rti = 'false';
            $EngineProtect = 'true';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak RTCZDR') {
            $ConsumableCover = 'false';
            $rsa_selected = 'false';
            $rti = 'true';
            $EngineProtect = 'false';
            $isDepreciationCover = 'false';
        } else if (trim($productData->product_identifier) == 'Kotak RTCZDREP') {
            $ConsumableCover = 'false';
            $rsa_selected = 'false';
            $rti = 'true';
            $EngineProtect = 'true';
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

            if (in_array('Electrical Accessories', $value)) {
                $electricalCoveSelection = 'true';
                $electricalCoverInsuredAmount = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
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
                    "isKeyReplacement"          => $isKeyReplacement,
                    "KeyReplacementSI"          => $KeyReplacementSI,
                    "isTyreCover" => $tyreSecure,
                    "vPurchaseDate" => date('d/m/Y', strtotime($vehicleDate)),
                    "TyreCoverSI" => $tyreSecureSI,
                    "isLossPersonalBelongings"  => $isLossPersonalBelongings,
                    "LossPersonalBelongingsSI"  => $LossPersonalBelongingsSI,
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
                    "vProductType" => in_array($requestData->policy_type, ['third_party_breakin', 'third_party']) ? "LiabilityOnlyPolicy" : "ComprehensivePolicy",
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
                    "vRegistrationYear" => date('Y', strtotime( $requestData->vehicle_register_date)),
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
                if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin')
                {
                    if(strtolower($requestData->previous_policy_expiry_date) == "new")
                    {
                        $requestData->previous_policy_expiry_date = Carbon::now()->format('Y-m-d');
                    }
                    else
                    {
                        $premium_req_array['dPreviousTPPolicyExpiryDate'] = date('d/m/Y', strtotime(empty($requestData->previous_policy_expiry_date) ? Carbon::now()->format('Y-m-d') : $requestData->previous_policy_expiry_date));
                        $premium_req_array['dPreviousTPPolicyStartDate'] = date('d/m/Y',strtotime('-1 year -1 day', strtotime(empty($requestData->previous_policy_expiry_date) ? Carbon::now()->format('Y-m-d') : $requestData->previous_policy_expiry_date)));
                    }
                    $premium_req_array['vPrevTPInsurerCode'] = $proposal->tp_insurance_company;
                    $premium_req_array['vPrevTPInsurerExpiringPolicyNumber'] = $proposal->tp_insurance_number;
                    $premium_req_array['vPrevTPInsurerName'] = $proposal->tp_insurance_company;
                    $premium_req_array['nProductCode'] = "3176";
                    $premium_req_array['vPAODTenure'] = $vPAODTenure; 
                    $premium_req_array['vCustomerPrevPolicyNumber'] = $proposal->previous_policy_number;
                    $premium_req_array['isNonElectricalAccessoriesChecked'] = 'false';
                    $premium_req_array['isElectricalAccessoriesChecked'] = 'false';
                    $premium_req_array['vNonElectricalAccessoriesSI'] = 0;
                    $premium_req_array['vElectricalAccessoriesSI'] = 0;

                    $premium_req_array['vPAODTenure'] = $requestData->vehicle_owner_type == 'C' ? '0' : $vPAODTenure;
                    if ($premium_type == 'third_party_breakin') {
                        $premium_req_array['dPreviousTPPolicyExpiryDate'] = "";
                        $premium_req_array['dPreviousTPPolicyStartDate'] = "";
                        $premium_req_array['vPrevTPInsurerCode'] = "";
                        $premium_req_array['vPrevTPInsurerName'] = "";
                        $premium_req_array['vCustomerPrevPolicyNumber'] = "";
                    }
                }

                if($requestData->vehicle_owner_type == 'C'){
                    $premium_req_array['isIMT29'] = true;
                    $premium_req_array['vPersonIMT29'] = $mmv_data->seating_capacity - 1;
                }

                $kyc_url = '';
                $is_kyc_url_present = false;
                $kyc_message = '';
                $kyc_status = true;

                $ckyc_controller = new CkycController;
                
                if($proposal->is_ckyc_verified != 'Y' && !empty($proposal->ckyc_reference_id)) {
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

                    if ($data) {
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
                                $legal_liability_to_employee = 0;
                                $KeyReplacementPremium = 0;
                                $LossPersonalBelongingsPremium = 0;
                                $tyreSecurePremium = 0;
                                
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

                                if (isset($premium_res_array['vpaiddriverlegalliability'])) {
                                    $paid_driver_tp = ($premium_res_array['vpaiddriverlegalliability']);
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

                                if (isset($response['vLLEOPDCC'])) {
                                    $legal_liability_to_employee = ($response['vLLEOPDCC']);
                                }

                                if (isset($response['nKeyReplacementPremium'])) {
                                    $KeyReplacementPremium = ($response['nKeyReplacementPremium']);
                                }
                                if (isset($response['nLossPersonalBelongingsPremium'])) {
                                    $LossPersonalBelongingsPremium = ($response['nLossPersonalBelongingsPremium']);
                                }
                                if (isset($premium_res_array['nTyreCoverPremium'])) {
                                    $tyreSecurePremium = ($premium_res_array['nTyreCoverPremium']);
                                }

                                $voluntary_deductible_discount =  ($premium_res_array['vvoluntarydeduction'] ? $premium_res_array['vvoluntarydeduction'] : 0);
                                $other_discount = ($productData->zero_dep == 0 ? $voluntary_deduction_zero_dep : '0');
                                $other_discount = $other_discount + $voluntary_deduction_zero_dep;
                                $addon_premium = $zero_dep_cover + $rsa + $eng_protect + $rti + $consumable + $KeyReplacementPremium + $LossPersonalBelongingsPremium + $tyreSecurePremium;
                                $final_payable_amount = (str_replace("INR ", "", $premium_res_array['vtotalpremium']));
                                $total_discount = $NCB + $other_discount + $voluntary_deductible_discount;
                                $final_od_premium = $od + $addon_premium;
                                $final_tp_premium = $tp + $lpg_cng_tp + $llpaiddriver  + $paid_driver + $pa_unnamed + $paid_driver_tp + $legal_liability_to_employee;

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
                                    'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $premium_res_array['vpolicystartdate']))),
                                    'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d-m-Y') : ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ? Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(3)->subDay(1)->format('d-m-Y')  : Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d-m-Y')),
                                    'tp_start_date' => !empty($proposal->tp_start_date) ? $proposal->tp_start_date :date('d-m-Y', strtotime(str_replace('/', '-', $tp_start_date))),
                                    'tp_end_date' => !empty($proposal->tp_end_date) ? $proposal->tp_end_date : date('d-m-Y', strtotime(str_replace('/', '-', $tp_end_date ))),
                                    'ic_vehicle_details' => $vehicleDetails,
                                    'is_breakin_case' => $is_breakin_case,
                                ]);

                                KotakPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
                                
                            } else {
                                return response()->json([
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => isset($premium_res_array['verrormsg']) ? $premium_res_array['verrormsg'] : 'Error while processing request',
                                ]);
                            }
                        /* } */
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
                    }
                }

                if ($kyc_status) {
                    $org_type = $requestData->vehicle_owner_type;
                
                    // appending 0 for dl rto's where registration numbers 2'nd segment is of 1 charecter or is less than 9
                    $reg_no[1] = $reg_no[1] ?? '';
                    if(($reg_no[0] == 'DL') && ((intval($reg_no[1]) < 10) && (strlen($reg_no[1]) == 1))) {
                        $reg_no[1] = '0'.$reg_no[1];
                    }

                    $proposal_payment_req = [
                        "objParaCustomerDetails" => [
                            "vCustomerLoginId" => $user_id,
                            "vCustomerId" => "",
                            "isSearchCustomer" => false,
                            "vCustomerType" => $org_type,
                            "vCustomerTypeId" => "",
                            "vCustomerFirstName" => ($org_type == 'I' ? $proposal->first_name : ''),
                            "vCustomerMiddleName" => '',
                            "vCustomerLastName" => ($org_type == 'I' ? (!empty($proposal->last_name) ?  $proposal->last_name : '.') : ''),
                            "vCustomerEmail" => ($org_type == 'I' ? $proposal->email : ''),
                            "vCustomerMobile" => ($org_type == 'I' ? $proposal->mobile_number : ''),
                            "vCustomerDOB" => ($org_type == 'I' ? date('d/m/Y', strtotime($proposal->dob)) : ''),
                            "vCustomerGender" => ($org_type == 'I' ? ($proposal->gender == 'F' ? 'FEMALE' : 'MALE') : ''),
                            "vCustomerSalutation" => ($org_type == 'I' ? ($proposal->gender == 'F' ? ($proposal->marital_status == 'Single' ? 'MISS' : 'MRS') : 'MR') : ''),
                            "vCustomerPincode" => ($org_type == 'I' ? $proposal->pincode : ''),
                            "vCustomerAddressLine1" => ($org_type == 'I' ? implode(' ', array_filter([$proposal->address_line1, $proposal->address_line2, $proposal->address_line3])) : ' '),
                            "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                            "vCustomerQuoteId" => $vquoteid,
                            "vCustomerWorkFlowId" => $vworkflowid, #in customer_id workflowid is saved 
                            "vCustomerNomineeName" => ($org_type == 'I' && !empty($proposal->nominee_name) ? $proposal->nominee_name : ''),
                            "vCustomerNomineeDOB" => ($org_type == 'I' && !empty($proposal->nominee_dob) ? date('d/m/Y', strtotime($proposal->nominee_dob)) : ''),
                            "vCustomerNomineeRelationship" => ($org_type == 'I' && !empty($proposal->nominee_relationship) ? $proposal->nominee_relationship : ''),
                            "vCustomerRegNumber1" => isset($reg_no[0]) ? $reg_no[0] :'',
                            "vCustomerRegNumber2" => $reg_no[1] ?? '',
                            "vCustomerRegNumber3" => isset($reg_no[2]) ? $reg_no[2] :'',
                            "vCustomerRegNumber4" => isset($reg_no[3]) ? $reg_no[3] :'',
                            "vCustomerEngineNumber" => $proposal->engine_number,
                            "vCustomerChassisNumber" => $proposal->chassis_number,
                            "vCustomerBusinessType" => ($proposal->business_type == 'newbusiness') ? 'N' : 'R',
                            "vCustomerFullName" => $proposal->first_name . " " . $proposal->last_name,
                            "vAppointeeName" => '',##$proposal->Appointee,
                            "vAppointeeRelation" => '',##$proposal->AppointeeRelation,
                            "vCustomerPrevPolicyNumber" => !empty($proposal->previous_policy_number) ? $proposal->previous_policy_number : '',#($proposal->business_type == 'rollover') ? $proposal->previous_policy_number : '',
                            "vCustomerPreInpectionNumber" => ($proposal->is_breakin_case == 'Y' && $premium_type != 'third_party_breakin')  ? $proposal->bagi_ref_no : "", ##
                            "vCustomerPanNumber" => $proposal->pan_number,
                            "bIsYourVehicleFinanced" => $proposal->is_vehicle_finance == '1' ? '1' : '0',
                            "vFinancierCode" => $proposal->is_vehicle_finance == '1' ? $proposal->name_of_financer : '',
                            "vFinancierName" => $proposal->is_vehicle_finance == '1' ? $Financing_Institution_Name : '', ## pull from master
                            "vFinancierAddress" => $proposal->is_vehicle_finance == '1' ? $proposal->financer_location : '',
                            "vFinancierAgreementType" => $proposal->is_vehicle_finance == '1' ? 'Hypothecation' : '',
                            "vCustomerCRNNumber" => "",
                            "vCustomerLoanAcNumber" => "",
                            "vRMCode" => "",
                            "vBranchInwardNumber" => "",
                            "dBranchInwardDate" => date('d/m/Y'),
                            "vPosPanCard" => $POS_PAN_NO,
                        ]
                    ];
                    
                    if ($is_pos == 'N') {
                        unset($proposal_payment_req['objParaCustomerDetails']['vPosPanCard']);
                    }

                    if ($org_type == 'C') {
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationName'] = $proposal->first_name;
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationContactName'] = $proposal->first_name;
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationEmail'] = $proposal->email;
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationMobile'] = $proposal->mobile_number;
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationPincode'] = $proposal->pincode;
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationAddressLine1'] = $proposal->address_line1 . '' . $proposal->address_line2 . '' . $proposal->address_line3;
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationTANNumber'] = '';
                        $proposal_payment_req['objParaCustomerDetails']['vOrganizationGSTNumber'] = $proposal->gst_number;
                    }

                    $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PROPOSAL').$vquoteid .'/'.$user_id, $proposal_payment_req, 'kotak', [
                        'token' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'enquiryId' => $enquiryId,
                        'requestMethod' =>'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'kotak',
                        'section' => $productData->product_sub_type_code,
                        'method' =>'Private Car Proposal Service',
                        'transaction_type' => 'proposal',
                    ]);

                    $proposal_service_response = $get_response['response'];
                    $proposal_response  = json_decode($proposal_service_response, true);

                    if (isset($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage']) && $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage'] == 'Success'  && empty($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vWarningMsg'])) {
                        
                        $proposalUpdate = [
                            'additional_details_data' => json_encode($proposal_response),
                            'total_premium' => (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vTotalPremium'])),
                            'final_payable_amount' => (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vTotalPremium'])),
                            'proposal_no' => $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vProposalNumber'] ?? $proposal->proposal_no ?? null,
                        ];

                        if (!empty($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vGSTAmount'])) {
                            $proposalUpdate['service_tax_amount'] = (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vGSTAmount']));
                        } else {
                            $proposalUpdate['service_tax_amount'] = ($proposalUpdate['total_premium'] * (18 / (100 + 18)));
                        }
                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update($proposalUpdate);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => (isset($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage']) && $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage'] !== 'Success') ? $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage'] : (isset($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vWarningMsg']) ? $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vWarningMsg']:'Error while processing Proposal Service request'),
                        ]);
                    }

                    try {
                        //$premium_res_array = array_change_key_case_recursive(json_decode($data, true));
                        #breakin code 
                        if(((($requestData->business_type == 'breakin') || ($requestData->previous_policy_type == 'Third-party')) || ($is_breakin_case == 'Y')) && ($premium_type != 'third_party_breakin')) {
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
                            $data = async_http_post_form_data(config('constants.IcConstants.kotak.BREAK_ID_GENERATION_KOTAK_MOTOR'), $brk_in_req_array,$additionalData);
                            if ($data) {
                                $brk_in_res_array = json_decode($data, true);
                                if (isset($brk_in_res_array['status']) && $brk_in_res_array['status'] == 'success' && $brk_in_res_array['pirefno'] != '') {
                                    $bagi_ref_no = $brk_in_res_array['pirefno'];
                                    DB::table('cv_breakin_status')->insert([
                                        'user_proposal_id' => $proposal->user_proposal_id,
                                        'ic_id' => $productData->company_id,
                                        'breakin_number' => (isset($bagi_ref_no)) ? $bagi_ref_no : '',
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
                                } else {
                                    ##what status should be updated
                                    return response()->json([
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'message' => isset($brk_in_res_array['message']) ? 'Breakin Service Error - ' . $brk_in_res_array['message'] : 'Insurer not reachable',
                                    ]);
                                }
                            }
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
                                'inspection_number' => (isset($bagi_ref_no)) ? $bagi_ref_no : '',
                                'kyc_url' => $kyc_url,
                                'is_kyc_url_present' => $is_kyc_url_present,
                                'kyc_message' => $kyc_message,
                                'kyc_status' => $kyc_status,
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
                } else {
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
                            'is_breakin' =>  $is_breakin_case,
                            'inspection_number' => (isset($bagi_ref_no)) ? $bagi_ref_no : '',
                            'kyc_url' => $kyc_url,
                            'is_kyc_url_present' => $is_kyc_url_present,
                            'kyc_message' => $kyc_message,
                            'kyc_status' => $kyc_status,
                        ]
                    ]);
                }

            } else {
                 return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Can not generate Token',
                ]);
            }


        } else {
            return response()->json([
                'status'  => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ]);
            
        }

    }

    public static function renewalSubmit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $mmv = get_mmv_details($productData, $requestData->version_id, 'kotak');
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $is_pos_flag = false;

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_flag = false;
        $POS_PAN_NO = '';

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $tokenData = getKotakTokendetails('motor', $is_pos_flag);

        $token_req_array = [
            'vLoginEmailId' => $tokenData['vLoginEmailId'],
            'vPassword' => $tokenData['vPassword'],
        ];

        $get_response = getWsData(
            config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_MOTOR'),
            $token_req_array,
            'kotak',
            [
                'Key' => $tokenData['vRanKey'],
                'headers' => [
                    'vRanKey' => $tokenData['vRanKey']
                ],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'kotak',
                'section' => $productData->product_sub_type_code,
                'method' => 'Token Generation',
                'transaction_type' => 'proposal',
            ]
        );

        $data = $get_response['response'];
        $user_id = config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID');

        if ($data) {
            $token_response = json_decode($data, true);
            if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {
                $premium_req_array = [
                    "vPolicyNumber" => $proposal->previous_policy_number,
                    "vLoginEmailId" => $user_id,
                    "bIsReCalculate"  => false,
                    "vRegistrationNumber"  => "", #$proposal->vehicale_registration_number,
                    "vChassisNumber"  => $proposal->chassis_number,
                    "vEngineNumber"  => $proposal->engine_number,
                    "nFinalIDV"  => $quote_log->idv,
                    "nMarketMovement"  => -1,
                    "isRoadSideAssistance" => true
                ];

                $kyc_url = '';
                $is_kyc_url_present = false;
                $kyc_message = '';
                $kyc_status = true;

                $ckyc_controller = new CkycController;

                if ((app()->environment() == 'local') && $proposal->is_ckyc_verified != 'Y' && !empty($proposal->ckyc_reference_id)) {
                    // If ckyc reference id exists, we will try to verify using ckyc_reference_id. If we get 'status' as true, then return, else we will try to verify with other modes 
                    $ckycStatusResponse = $ckyc_controller->ckycVerifications(new Request([
                        'companyAlias' => 'kotak',
                        'enquiryId' => $request['enquiryId'],
                        'mode' => 'ckyc_reference_id',
                        'quoteId' => $proposal->ckyc_reference_id,
                    ]));
                    $ckyc_response = $ckycStatusResponse->getOriginalContent();

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
                        if (!empty($ckyc_response['data']['meta_data']['KYCStatus'] ?? '')) {
                            if ($ckyc_response['data']['meta_data']['KYCStatus'] == 'pending') {
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

                if ($proposal->is_ckyc_verified != 'Y') 
                {
                    $get_response = getWsData(config('constants.IcConstants.kotak.KOTAK_MOTOR_FETCH_POLICY_DETAILS_PREMIUM'), $premium_req_array, 'kotak', [
                        'token' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'enquiryId' => $enquiryId,
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'kotak',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Premium Calculation',
                        'transaction_type' => 'proposal',
                    ]);
                    $data = $get_response['response'];
                    if ($data) {
                        $premium_res_array = json_decode($data, true);
                        if ($premium_res_array) {
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
                            $paid_driver = $paid_driver_tp =  0;
                            $voluntary_deduction_zero_dep = 0;
                            $NCB = 0;
                            $tp = 0;

                            if (isset($premium_res_array['vBasicTPPremium'])) {
                                $tp = ($premium_res_array['vBasicTPPremium']);
                            }
                            if (isset($premium_res_array['vPACoverForOwnDriver'])) {
                                $pa_owner = ($premium_res_array['vPACoverForOwnDriver']);
                            }
                            if (isset($premium_res_array['vPAForUnnamedPassengerPremium'])) {
                                $pa_unnamed = ($premium_res_array['vPAForUnnamedPassengerPremium']);
                            }
                            if (isset($premium_res_array['vCngLpgKitPremiumTP'])) {
                                $lpg_cng_tp = ($premium_res_array['vCngLpgKitPremiumTP']);
                            }
                            if (isset($premium_res_array['vPANoOfEmployeeforPaidDriverPremium'])) {
                                $paid_driver = ($premium_res_array['vPANoOfEmployeeforPaidDriverPremium']);
                            }
                            if (isset($premium_res_array['vPaidDriverlegalliability'])) {
                                $paid_driver_tp = ($premium_res_array['vPaidDriverlegalliability']);
                            }

                            if (isset($premium_res_array['vLegalLiabilityPaidDriverNo'])) {
                                $llpaiddriver = ($premium_res_array['vLegalLiabilityPaidDriverNo']);
                            }
                            if (isset($premium_res_array['vDepreciationCover'])) {
                                $zero_dep = ($premium_res_array['vDepreciationCover']);
                            }
                            if (isset($premium_res_array['vRSA'])) {
                                $rsa = ($premium_res_array['vRSA']);
                            }
                            if (isset($premium_res_array['vEngineProtect'])) {
                                $eng_protect = ($premium_res_array['vEngineProtect']);
                            }
                            if (isset($premium_res_array['vConsumableCover'])) {
                                $consumable = ($premium_res_array['vConsumableCover']);
                            }
                            if (isset($premium_res_array['vReturnToInvoice'])) {
                                $rti = ($premium_res_array['vReturnToInvoice']);
                            }
                            if (isset($premium_res_array['vElectronicSI'])) {
                                $electrical_accessories = ($premium_res_array['vElectronicSI']);
                            }
                            if (isset($premium_res_array['vNonElectronicSI'])) {
                                $non_electrical_accessories = ($premium_res_array['vNonElectronicSI']);
                            }
                            if (isset($premium_res_array['vCngLpgKitPremium'])) {
                                $lpg_cng = ($premium_res_array['vCngLpgKitPremium']);
                            }
                            if (isset($premium_res_array['vVoluntaryDeductionDepWaiver'])) {
                                $voluntary_deduction_zero_dep = ($premium_res_array['vVoluntaryDeductionDepWaiver']);
                            }
                            if (isset($premium_res_array['vOwnDamagePremium'])) {
                                $od = ($premium_res_array['vOwnDamagePremium']);
                            }
                            if (isset($premium_res_array['vNCB'])) {
                                $NCB = ($premium_res_array['vNCB']);
                            }
                            $voluntary_deductible_discount =  ($premium_res_array['vVoluntaryDeduction'] ? $premium_res_array['vVoluntaryDeduction'] : 0);
                            $other_discount = ($productData->zero_dep == 0 ? $voluntary_deduction_zero_dep : '0');
                            $other_discount = $other_discount + $voluntary_deduction_zero_dep;
                            $addon_premium = $zero_dep_cover + $rsa + $eng_protect + $rti + $consumable;
                            $final_payable_amount = str_replace("INR ", "", $premium_res_array['nTotalPremium']);
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

                            $additional_details_data = json_decode($proposal->additional_details_data, true);
                            $additional_details_data = empty($additional_details_data) ? [] : $additional_details_data;

                            $additional_details_data = array_replace($additional_details_data, [
                                'vIntermediaryCode' => $premium_res_array['vIntermediaryCode'],
                                'vIntermediaryName' => $premium_res_array['vIntermediaryName']
                            ]);

                            $proposal->additional_details_data = json_encode($additional_details_data);

                            UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                                ->update([
                                    'additional_details_data' => json_encode($additional_details_data),
                                    'od_premium' => $final_od_premium,
                                    'tp_premium' => $final_tp_premium,
                                    'ncb_discount' => $NCB,
                                    'total_discount' => $total_discount,
                                    'addon_premium' => $addon_premium,
                                    'total_premium' => $premium_res_array['nTotalPremium'],
                                    'service_tax_amount' => $premium_res_array['vGSTAmount'],
                                    'final_payable_amount' => $final_payable_amount,
                                    'cpa_premium' => $premium_res_array['vPACoverForOwnDriver'],
                                    'customer_id' => $premium_res_array['vWorkFlowID'], ## workflowid as customer_id
                                    'unique_proposal_id' => $premium_res_array['vQuoteId'],
                                    'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $premium_res_array['vPolicyStartDate']))),
                                    'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $premium_res_array['vPolicyEndDate']))),
                                    'ic_vehicle_details' => $vehicleDetails,
                                    'mobile_number' => $premium_res_array['objCustomerDetails']['vCustomerMobile'],
                                    'email' => $premium_res_array['objCustomerDetails']['vCustomerEmail'],
                                    'nominee_name' => $premium_res_array['objCustomerDetails']['vCustomerNomineeName'],
                                    'nominee_relationship' => $premium_res_array['objCustomerDetails']['vCustomerNomineeRelationship'],
                                    'nominee_dob' => $premium_res_array['objCustomerDetails']['vCustomerNomineeDOB'],
                                    'vehicale_registration_number' => $premium_res_array['vRegistrationNo1']."-".$premium_res_array['vRegistrationNo2']."-".$premium_res_array['vRegistrationNo3']."-".$premium_res_array['vRegistrationNo4'],
                    
                                ]);

                                KotakPremiumDetailController::saveRenewalPremiumDetails($get_response['webservice_id']);
                        } else {
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Error while processing request',
                            ]);
                        }
                        /* } */
                    } else {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Insurer not reachable',
                        ]);
                    }
                }
                
                $vtotalpremium = $premium_res_array['nTotalPremium'] ?? $proposal->total_premium;
                $vquoteid = $premium_res_array['vQuoteId'] ?? $proposal->unique_proposal_id;
                $vworkflowid = $premium_res_array['vWorkFlowID'] ?? $proposal->customer_id;


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

                        // UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                        //     ->update([
                        //         'additional_details_data' => $ckyc_response['data']['redirection_url'],
                        //     ]);
                    }
                }
                
                $vRegistrationNo = explode("-", $proposal->vehicale_registration_number);
                $vRegistrationNo1 = $premium_res_array['vRegistrationNo1'] ?? $vRegistrationNo[0];
                $vRegistrationNo2 = $premium_res_array['vRegistrationNo2'] ?? $vRegistrationNo[1];
                $vRegistrationNo3 = $premium_res_array['vRegistrationNo3'] ?? $vRegistrationNo[2];
                $vRegistrationNo4 = $premium_res_array['vRegistrationNo4'] ?? $vRegistrationNo[3];

                if ($kyc_status) {

                    $proposal_payment_req = [
                        "vLoginEmailId" =>  $user_id,
                        "vVehicleChassisNumber" =>  $proposal->chassis_number,
                        "vCustomerEmail" =>$proposal->email,
                        "vCustomerMobile" => $proposal->mobile_number,
                        "vQuoteId" =>  $vquoteid,
                        "vWorkFlowId" =>  $vworkflowid,
                        "vVehicleRegNumber1" =>  $vRegistrationNo1,
                        "vVehicleRegNumber2" =>  $vRegistrationNo2,
                        "vVehicleRegNumber3" =>  $vRegistrationNo3,
                        "vVehicleRegNumber4" =>  $vRegistrationNo4,
                        "vCustomerNomineeRelationship" =>  $proposal->nominee_relationship,
                        "vCustomerNomineeName" =>  $proposal->nominee_name,
                        "vCustomerNomineeDOB" =>  ($requestData->vehicle_owner_type == 'I' && !empty($proposal->nominee_dob)) ? date('d/m/Y', strtotime($proposal->nominee_dob)) : '',
                        "vAppointeeName" =>  "",
                        "vAppointeeRelation" =>  ""
                    ];
                    
                    $get_response = getWsData(config('constants.IcConstants.kotak.KOTAK_MOTOR_RENEWAL_SAVE_PROPOSAL'), $proposal_payment_req, 'kotak', [
                        'token' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'enquiryId' => $enquiryId,
                        'requestMethod' => 'post',
                        'productName'  => $productData->product_name,
                        'company'  => 'kotak',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Private Car Proposal Service',
                        'transaction_type' => 'proposal',
                    ]);

                    $proposal_service_response = $get_response['response'];
                    $proposal_response  = json_decode($proposal_service_response, true);
                    if ($proposal_response['vErrorMessage'] == '') {
                        
                        $additional_details_data = json_decode($proposal->additional_details_data, true);
                        $additional_details_data = empty($additional_details_data) ? [] : $additional_details_data;

                        $additional_details_data = array_replace($additional_details_data, $proposal_response);

                        $proposalUpdate = [
                            'additional_details_data' => json_encode($additional_details_data),
                            'total_premium' => (str_replace("INR ", "", (int)$proposal_response['vTotalPremium'])),
                            'final_payable_amount' => (str_replace("INR ", "", (int)$proposal_response['vTotalPremium'])),
                            'proposal_no' => $proposal_response['vProposalNumber'] ?? $proposal->proposal_no ?? null,
                        ];

                        if (!empty($proposal_response['vGSTAmount'])) {
                            $proposalUpdate['service_tax_amount'] = (str_replace("INR ", "", $proposal_response['vGSTAmount']));
                        } else {
                            $proposalUpdate['service_tax_amount'] = ($proposalUpdate['total_premium'] * (18 / (100 + 18)));
                        }
                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update($proposalUpdate);
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
                                    'inspection_number' => (isset($bagi_ref_no)) ? $bagi_ref_no : '',
                                    'kyc_url' => $kyc_url,
                                    'is_kyc_url_present' => $is_kyc_url_present,
                                    'kyc_message' => $kyc_message,
                                    'kyc_status' => $kyc_status,
                                ]
                            ]);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => (isset($proposal_response['vErrorMessage']) && $proposal_response['vErrorMessage'] !== 'Success') ? $proposal_response['vErrorMessage'] : (isset($proposal_response['vWarningMsg']) ? $proposal_response['vWarningMsg'] : 'Error while processing Proposal Service request'),
                        ]);
                    }
                } else {
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
                            'inspection_number' => (isset($bagi_ref_no)) ? $bagi_ref_no : '',
                            'kyc_url' => $kyc_url,
                            'is_kyc_url_present' => $is_kyc_url_present,
                            'kyc_message' => $kyc_message,
                            'kyc_status' => $kyc_status,
                        ]
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Can not generate Token',
                ]);
            }
        } else {
            return response()->json([
                'status'  => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ]);
        }
    }
}