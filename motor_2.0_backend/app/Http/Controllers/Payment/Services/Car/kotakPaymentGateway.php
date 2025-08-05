<?php

namespace App\Http\Controllers\Payment\Services\Car;
include_once app_path() . '/Helpers/CarWebServiceHelper.php';

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProposal;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\PaymentRequestResponse;
use Illuminate\Support\Facades\DB;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CkycController;
use App\Http\Controllers\SyncPremiumDetail\Car\KotakPremiumDetailController;
use App\Models\CvAgentMapping;
use Carbon\Carbon;
use DateTime;
use App\Models\SelectedAddons; 
use App\Models\CvBreakinStatus;


class kotakPaymentGateway extends Controller
{   
    public static function make($request)
    {
        $enquiryId = customDecrypt($request->enquiryId);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $requestData = getQuotation($enquiryId);
        $breakin_data = CvBreakinStatus::where('user_proposal_id', $user_proposal->user_proposal_id)->first();
        $productData = getProductDataByIc($request['policyId']);
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        $allowed_breakin_type = ['breakin','own_damage_breakin'];
        if(in_array($premium_type, $allowed_breakin_type))
        {
           $breakin_proposal_response = self::breakin_proposal_submit($enquiryId, $user_proposal, $requestData,$request,$breakin_data);
           if (!empty($breakin_proposal_response)) 
           {
               return $breakin_proposal_response;
           }
        }
        $key = config('constants.IcConstants.kotak.CHECKSUM_KEY_KOTAK');;
        $txnid = bin2hex(random_bytes(25));
        $amount = $user_proposal->final_payable_amount;
        $productinfo = 'Kotak Car Insurance '. $user_proposal->product_type;
        $firstname = $user_proposal->first_name;
        $email = $user_proposal->email;
        $salt = config('constants.IcConstants.kotak.CAR_SALT_KOTAK');
        $udf1 = $user_proposal->unique_proposal_id;//broker name is passed instead of $user_proposal->unique_proposal_id; confirmed by ic#revert changes due to pg error
        $hash_string = "$key|$txnid|$amount|$productinfo|$firstname|$email|$udf1||||||||||$salt";
        $hash = hash('sha512', $hash_string);

        $return_data = [
            'form_action' => config('constants.IcConstants.kotak.PAYMENT_GATEWAY_LINK_KOTAK_MOTOR'),
            'form_method' => "post",
            'payment_type' => 0,
            'form_data' => [
                'firstname' => $user_proposal->first_name,
                'lastname' => $user_proposal->last_name,
                'surl' => route('car.payment-confirm', ['kotak','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),
                'phone' => $user_proposal->mobile_number,
                'key' => config('constants.IcConstants.kotak.CHECKSUM_KEY_KOTAK'),
                'hash' => $hash,
                'curl' => route('car.payment-confirm', ['kotak','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),
                'furl' => route('car.payment-confirm', ['kotak','enquiry_id' => $enquiryId,'policy_id' => $request['policyId']]),
                'txnid' => $txnid,
                'productinfo' => $productinfo,
                'amount' => $amount,
                'email' => $user_proposal->email,
                'udf1' => $user_proposal->unique_proposal_id,
            ]
        ];

        $icId = MasterPolicy::where('policy_id', $request['policyId'])
                                    ->pluck('insurance_company_id')
                                    ->first();
        $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                                    ->pluck('quote_id')
                                    ->first();

        DB::table('payment_request_response')
        ->where('user_product_journey_id', $enquiryId)
        ->update(['active' => 0]);

        $m_data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
        $m_data['ic_id'] = $user_proposal->ic_id;
        $m_data['stage'] = STAGE_NAMES['PAYMENT_INITIATED'];
        updateJourneyStage($m_data);

        DB::table('payment_request_response')->insert([
            'quote_id'                  => $quote_log_id,
            'user_product_journey_id'   => $enquiryId,
            'user_proposal_id'          => $user_proposal->user_proposal_id,
            'ic_id'                     => $icId,
            'order_id'                  => $user_proposal->proposal_no,
            'amount'                    => $user_proposal->final_payable_amount,
            'payment_url'               => config('constants.IcConstants.kotak.PAYMENT_GATEWAY_LINK_KOTAK_MOTOR'),
            'return_url'                => route('car.payment-confirm', ['kotak']),
            'status'                    => STAGE_NAMES['PAYMENT_INITIATED'],
            'active'                    => 1,
            'xml_data'                  => json_encode($return_data)
        ]);

        return response()->json([
            'status' => true,
            'msg' => "Payment Reidrectional",
            'data' => $return_data,
        ]);

    }
    
    public static function breakin_proposal_submit($enquiryId, $user_proposal, $requestData,$request,$breakin_data)
    {
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
                'premium_amount' => '0',
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
        $policy_start_date = date('d/m/Y');
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');

        if($requestData->business_type != 'newbusiness') {
            $prev_insu_comp_name =  $user_proposal->insurance_company_name;#$requestData->previous_insurer;
            $prev_comp_code =  $user_proposal->previous_insurance_company;#$requestData->previous_insurer_code
        }

            if ($requestData->previous_policy_type == 'Third-party') {
                $PrevPolicyType = 'LiabilityOnlyPolicy';
            } else {
                $PrevPolicyType = 'ComprehensivePolicy';
                if(in_array($premium_type, ['own_damage', 'own_damage_breakin']))
                {
                    $PrevPolicyType = '1+3';
                }
            }

        if ($requestData->vehicle_owner_type == "C") {
            $cpa_required = 'false';
            $vPAODTenure = '0';
        }
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
        $additional_details = json_decode($user_proposal->additional_details);
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

        $reg_no = explode('-', isset($user_proposal->vehicale_registration_number) && $user_proposal->vehicale_registration_number != 'NEW'  ? $user_proposal->vehicale_registration_number : $requestData->rto_code);
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
        if (isBhSeries($user_proposal->vehicale_registration_number)) { 

            $reg_no = getRegisterNumberWithHyphen(str_replace('-', '', $user_proposal->vehicale_registration_number));
            $reg_no = explode('-', $reg_no);
        }

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $compulsory_personal_accident = $selected_addons->compulsory_personal_accident;

        // car age calculation
        $newDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($newDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
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

            if (in_array('Key Replacement', $value)) {
                $isKeyReplacement = 'true';              
                $KeyReplacementSI = 25000;                
            }
            if (in_array('Loss of Personal Belongings', $value)) {
                $isLossPersonalBelongings   = 'true';
                $LossPersonalBelongingsSI   = 10000;               
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
            if (!empty($user_proposal->previous_policy_addons_list)) {
                $previous_policy_addons_list = is_array($user_proposal->previous_policy_addons_list) ? $user_proposal->previous_policy_addons_list : json_decode($user_proposal->previous_policy_addons_list);
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
                    "vPurchaseDate" => date('d/m/Y', strtotime($newDate)),
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
                    "vPolicyStartDate" => '',//$policy_start_date policy should start date should be null as per the kit in premium re calculation 
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
                        "vCustomerGender" => $requestData->vehicle_owner_type == "I" ? ($user_proposal->gender == 'F' ? 'Female' : 'Male') : "",
                    ],
                    "objPrevInsurer" => $PrevInsurer,
                    "bIsCreditScoreOpted" => "0",
                    "bIsNewCustomer" => "0",
                    "vCSCustomerFirstName" => $requestData->vehicle_owner_type == "I" ? $user_proposal->first_name : "",
                    "vCSCustomerLastName" => $requestData->vehicle_owner_type == "I" ? $user_proposal->last_name : "",
                    "dCSCustomerDOB" => $requestData->vehicle_owner_type == "I" ? date('d/m/Y', strtotime(strtr($user_proposal->dob, '-', '/'))) : "",
                    "vCSCustomerMobileNo" => $requestData->vehicle_owner_type == "I" ? $user_proposal->mobile_number : '',
                    "vCSCustomerPincode" => $requestData->vehicle_owner_type == "I" ? $user_proposal->pincode : '',
                    "vCSCustomerIdentityProofType" => "1",
                    "vCSCustomerIdentityProofNumber" => $user_proposal->pan_number ? $user_proposal->pan_number : '',
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
                if($user_proposal->is_ckyc_verified=="Y")
                {
                    $premium_req_array['KYCQuoteId']=$user_proposal->ckyc_reference_id;
                }


                if($premium_type == "own_damage")
                {
                    $premium_req_array['dPreviousTPPolicyExpiryDate']=date('d/m/Y',strtotime($user_proposal->tp_end_date));;
                    $premium_req_array['dPreviousTPPolicyStartDate']=date('d/m/Y',strtotime($user_proposal->tp_start_date));
                    $premium_req_array['vPrevTPInsurerCode']=$user_proposal->tp_insurance_company;
                    $premium_req_array['vPrevTPInsurerExpiringPolicyNumber']=$user_proposal->tp_insurance_number;
                    $premium_req_array['vPrevTPInsurerName']=$user_proposal->tp_insurance_company;
                    $premium_req_array['vProductType']='ODOnly';
                    $premium_req_array['vProductTypeODTP']='';
                    $premium_req_array['nProductCode']="3151";
                    $premium_req_array['vPAODTenure']="0";
                    $premium_req_array['vCustomerPrevPolicyNumber']=$user_proposal->previous_policy_number;
                }
                if ($premium_type=='own_damage_breakin') {
                    $premium_req_array['dPreviousTPPolicyExpiryDate']=date('d/m/Y',strtotime($user_proposal->tp_end_date));;
                    $premium_req_array['dPreviousTPPolicyStartDate']=date('d/m/Y',strtotime($user_proposal->tp_start_date));
                    $premium_req_array['vPrevTPInsurerCode']=$user_proposal->tp_insurance_company;
                    $premium_req_array['vPrevTPInsurerExpiringPolicyNumber']=$user_proposal->tp_insurance_number;
                    $premium_req_array['vPrevTPInsurerName']=$user_proposal->tp_insurance_company;
                    $premium_req_array['nProductCode']="3151";
                    $premium_req_array['vPAODTenure']="0";
                    $premium_req_array['vCustomerPrevPolicyNumber']=$user_proposal->previous_policy_number;
                }

                $kyc_url = '';
                $is_kyc_url_present = false;
                $kyc_message = '';
                $kyc_status = false;
                $kyv_quote_id='';


                $ckyc_controller = new CkycController;
                
                if((app()->environment() == 'local') && $user_proposal->is_ckyc_verified != 'Y' && !empty($user_proposal->ckyc_reference_id)) {
                    // If ckyc reference id exists, we will try to verify using ckyc_reference_id. If we get 'status' as true, then return, else we will try to verify with other modes 
                    $ckycStatusResponse = $ckyc_controller->ckycVerifications(new Request([
                        'companyAlias' => 'kotak',
                        'enquiryId' => $request['enquiryId'],
                        'mode' => 'ckyc_reference_id',
                        'quoteId' => $user_proposal->ckyc_reference_id,
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
                        ]), $ckyc_response_to_save, $user_proposal);

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
                            return response()->json([
                                'status'  => false,
                                'message' => $ckyc_response['data']['message']
                            ]);
                        }
                    }
                }
                if($user_proposal->is_ckyc_verified == 'Y')
                {

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
                        'method' =>'After Breakin Premium Calculation',
                        'transaction_type' => 'proposal',
                    ]);

                    $data = $get_response['response'];

                    if ($data) {
                        $premium_res_array = array_change_key_case_recursive(json_decode($data, true));
                        #Private_Car_Proposal service(split service)

                        /* if (config('constants.IS_CKYC_ENABLED') == 'Y' && $user_proposal->is_ckyc_verified != 'Y') { */
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
                                $KeyReplacementPremium = 0;
                                $LossPersonalBelongingsPremium = 0;
                                
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

                                if (isset($premium_res_array['nKeyReplacementPremium'])) {
                                    $KeyReplacementPremium = ($premium_res_array['nKeyReplacementPremium']);
                                }
                                if (isset($premium_res_array['nLossPersonalBelongingsPremium'])) {
                                    $LossPersonalBelongingsPremium = ($premium_res_array['nLossPersonalBelongingsPremium']);
                                }

                                $voluntary_deductible_discount =  ($premium_res_array['vvoluntarydeduction'] ? $premium_res_array['vvoluntarydeduction'] : 0);
                                $other_discount = ($productData->zero_dep == 0 ? $voluntary_deduction_zero_dep : '0');
                                $other_discount = $other_discount + $voluntary_deduction_zero_dep;
                                $addon_premium = $zero_dep_cover + $rsa + $eng_protect + $rti + $consumable + $KeyReplacementPremium + $LossPersonalBelongingsPremium;
                                $final_payable_amount = (str_replace("INR ", "", $premium_res_array['vtotalpremium']));
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

                                UserProposal::where('user_proposal_id' , $user_proposal->user_proposal_id)
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
                                    'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $premium_res_array['vpolicyenddate']))),
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

                $vtotalpremium = $premium_res_array['vtotalpremium'] ?? $user_proposal->total_premium;
                $vquoteid=$vworkflowid=$kyc_quoteID='';
                // $vquoteid = $premium_res_array['vquoteid'] ?? $user_proposal->unique_proposal_id;
                // $vworkflowid = $premium_res_array['vworkflowid'] ?? $user_proposal->customer_id;
                // $vquoteid = $user_proposal->unique_proposal_id;
                // $vworkflowid = $user_proposal->customer_id;

                // if (config('constants.IS_CKYC_ENABLED') == 'Y' && $user_proposal->is_ckyc_verified != 'Y') {
                    //     $ckyc_modes = [
                    //         'ckyc_number' => 'ckyc_number',
                    //         'pan_card' => 'pan_number_with_dob',
                    //         'aadhar_card' => 'aadhar'
                    //     ];

                    //     $ckyc_response_data = $ckyc_controller->ckycVerifications(new Request([
                    //         'companyAlias' => 'kotak',
                    //         'enquiryId' => $request['enquiryId'],
                    //         'mode' => $ckyc_modes[$user_proposal->ckyc_type],
                    //         'quoteId' => $vquoteid
                    //     ]));

                    //     $ckyc_response = $ckyc_response_data->getOriginalContent();

                    //     if ($ckyc_response['status']) {
                    //         $kyc_url = '';
                    //         $is_kyc_url_present = false;
                    //         $kyc_message = '';
                    //         $kyc_status = true;

                    //         $ckyc_response['data']['customer_details'] = [
                    //             'name' => $ckyc_response['data']['customer_details']['fullName'] ?? null,
                    //             'mobile' => $ckyc_response['data']['customer_details']['mobileNumber'] ?? null,
                    //             'dob' => $ckyc_response['data']['customer_details']['dob'] ?? null,
                    //             'address' => null, // $ckyc_response['data']['customer_details']['address'],
                    //             'pincode' => null, // $ckyc_response['data']['customer_details']['pincode'],
                    //             'email' => $ckyc_response['data']['customer_details']['email'] ?? null,
                    //             'pan_no' => $ckyc_response['data']['customer_details']['panNumber'] ?? null,
                    //             'ckyc' => $ckyc_response['data']['customer_details']['ckycNumber'] ?? null
                    //         ];

                    //         $ckyc_response_to_save['response'] = $ckyc_response;

                    //         $updated_proposal = $ckyc_controller->saveCkycResponseInProposal(new Request([
                    //             'company_alias' => 'kotak',
                    //             'trace_id' => $request['enquiryId']
                    //         ]), $ckyc_response_to_save, $user_proposal);

                    //         UserProposal::where('user_product_journey_id', $enquiryId)
                    //             ->update($updated_proposal);

                    //         $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                    //     } 
                    //     else 
                    //     {
                    //         $kyc_url = $ckyc_response['data']['redirection_url'];
                    //         $is_kyc_url_present = true;
                    //         $kyc_message = '';
                    //         $kyc_status = false;

                    //         UserProposal::where('user_proposal_id' , $user_proposal->user_proposal_id)
                    //             ->update([
                    //                 'additional_details_data' => $ckyc_response['data']['redirection_url'],
                    //             ]);
                                
                    //     }
                    // }
                    $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                    if($proposal->is_ckyc_verified=="Y")
                    {
                        $kyc_status = true;
                        $vquoteid=$proposal->unique_proposal_id;
                        $vworkflowid=$proposal->customer_id;
                        $kyc_quoteID=$user_proposal->ckyc_reference_id;
                    }
                    elseif($proposal->is_ckyc_verified=="N")
                    {
                        $kyc_status = false;
                        $vquoteid = $premium_res_array['vquoteid'] ?? $user_proposal->unique_proposal_id;
                        $vworkflowid = $premium_res_array['vworkflowid'] ?? $user_proposal->customer_id;
                        $kyc_quoteID = $user_proposal->ckyc_reference_id;
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
                            "vCustomerPreInpectionNumber" =>  $breakin_data->wimwisure_case_number , 
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
                            "KYCQuoteID"    => $kyc_quoteID,
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

                    $bagi_ref_no = $breakin_data->breakin_number ?? '';
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
                        'method' =>'After Breakin Proposal Submit',
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
                            'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-',$proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['policyStartDate']))),
                            'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-',$proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['policyEndDate']))),
                        ];

                        if (!empty($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vGSTAmount'])) {
                            $proposalUpdate['service_tax_amount'] = (str_replace("INR ", "", $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vGSTAmount']));
                        } else {
                            $proposalUpdate['service_tax_amount'] = ($proposalUpdate['total_premium'] * (18 / (100 + 18)));
                        }
                        UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                            ->update($proposalUpdate);
                    }
                     else {
                        return response()->json([
                            'status' => false,
                            'message' => (isset($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage']) && $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage'] !== 'Success') ? $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vErrorMessage'] : (isset($proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vWarningMsg']) ? $proposal_response['Fn_Save_Partner_Private_Car_ProposalResult']['vWarningMsg']:'Error while processing Proposal Service request'),
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
    public static function confirm($request,$rehitdata = '')
    {
        if(!empty($rehitdata)) {
            $pg_return_data = $request_data = $rehitdata;
        } else {
            $pg_return_data = $request_data = $request->all();
        }
        unset($pg_return_data['enquiry_id'],$pg_return_data['policy_id']);
        $enquiryId = $request_data['enquiry_id'];
        $productData = getProductDataByIc($request_data['policy_id']);
        $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $stage_data = [];

        if(isset($request_data['status']) && $request_data['status'] == 'success') 
        {
            $pan_number = '';
            $is_pos_flag = false;
            $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

            $POS_PAN_NO = '';
            $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $enquiryId)
                ->where('seller_type','P')
                ->first();

            if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $user_proposal->idv <= 5000000) {
                if($pos_data) {
                    $is_pos_flag = true;
                    $POS_PAN_NO = $pos_data->pan_no;
                }
            }

            if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_KOTAK') == 'Y' && $user_proposal->idv <= 5000000)
            {
                $POS_PAN_NO    = 'ABGTY8890Z';
            }

            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_SUCCESS'];
            updateJourneyStage($stage_data);
            if(empty($rehitdata)) {
                DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('active',1)
                ->update([
                    'status'   => STAGE_NAMES['PAYMENT_SUCCESS'] ,
                    'response' => json_encode($pg_return_data),
                    ]);
            }
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
                        try {
                            $requestData = getQuotation($user_proposal->user_product_journey_id);
                            
                            if ($requestData->is_renewal == 'Y') {
                                $result = self::renewalProposalPayment(
                                    $user_id,
                                    $user_proposal,
                                    $request_data,
                                    $premium_type,
                                    $productData
                                );
                                if (!empty($rehitdata)) {
                                    return $result;
                                } else {
                                    if ($result['status'] ?? false) {
                                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'SUCCESS'));
                                    }
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId, 'CAR', 'FAILURE'));
                                }
                            }

                            if ($user_proposal['vehicale_registration_number'] == 'NEW') {
                                $reg_no = explode("-", $user_proposal['rto_location']);
                                $reg_no[2] = '';
                                $reg_no[3] = '';
                            } else {
                                $reg_no = explode("-", $user_proposal['vehicale_registration_number']);
                            }
                            $org_type = $user_proposal['owner_type'];
                            if($user_proposal['is_vehicle_finance'] == '1') {
                                $Financing_Institution_Name = DB::table('kotak_financier_master')
                                ->where('code', $user_proposal['name_of_financer'])
                                ->pluck('name')
                                ->first();
                            }
                            $additional_details  = json_decode($user_proposal['additional_details_data'],true);
                            $proposal_number     = $additional_details['Fn_Save_Partner_Private_Car_ProposalResult']['vProposalNumber'];
                            $proposal_payment_req = [
                                /* "objParaCustomerDetails" => [
                                    "vCustomerLoginId" => config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID'),
                                    "vCustomerId" => "",
                                    "isSearchCustomer" => false,
                                    "vCustomerType" => $org_type,
                                    "vCustomerTypeId" => "",
                                    "vCustomerFirstName" => ($org_type == 'I' ? $user_proposal['first_name'] : ''),
                                    "vCustomerMiddleName" => '',
                                    "vCustomerLastName" => ($org_type == 'I' ? $user_proposal['last_name'] : ''),
                                    "vCustomerEmail" => ($org_type == 'I' ? $user_proposal['email'] : ''),
                                    "vCustomerMobile" => ($org_type == 'I' ? $user_proposal['mobile_number'] : ''),
                                    "vCustomerDOB" => ($org_type == 'I' ? date('d/m/Y', strtotime($user_proposal['dob'])) : ''),
                                    "vCustomerGender" => ($org_type == 'I' ? ($user_proposal['gender'] == 'F' ? 'FEMALE' : 'MALE') : ''),
                                    "vCustomerSalutation" => ($org_type == 'I' ? ($user_proposal['gender'] == 'F' ? ($user_proposal['marital_status'] == 'Single' ? 'MISS' : 'MRS') : 'MR') : ''),
                                    "vCustomerPincode" => ($org_type == 'I' ? $user_proposal['pincode'] : ''),
                                    "vCustomerAddressLine1" => ($org_type == 'I' ? $user_proposal['address_line1'] . ' ' . $user_proposal['address_line2'] . ' ' . $user_proposal['address_line3'] : ' '),
                                    "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                                    "vCustomerQuoteId" => $user_proposal['unique_proposal_id'],
                                    "vCustomerWorkFlowId" => $user_proposal['customer_id'], #in customer_id workflowid is saved 
                                    "vCustomerNomineeName" => ($org_type == 'I' && !empty($user_proposal['nominee_name']) ? $user_proposal['nominee_name'] : ''),
                                    "vCustomerNomineeDOB" => ($org_type == 'I' && !empty($user_proposal['nominee_dob']) ? date('d/m/Y', strtotime($user_proposal['nominee_dob'])) : ''),
                                    "vCustomerNomineeRelationship" => ($org_type == 'I' && !empty($user_proposal['nominee_relationship']) ? $user_proposal['nominee_relationship'] : ''),
                                    "vCustomerRegNumber1" => $reg_no['0'],
                                    "vCustomerRegNumber2" => $reg_no['1'],
                                    "vCustomerRegNumber3" => $reg_no['2'],
                                    "vCustomerRegNumber4" => $reg_no['3'],
                                    "vCustomerEngineNumber" => $user_proposal['engine_number'],
                                    "vCustomerChassisNumber" => $user_proposal['chassis_number'],
                                    "vCustomerBusinessType" => ($user_proposal['business_type'] == 'newbusiness') ? 'N' : 'R',
                                    "vCustomerFullName" => $user_proposal['first_name'] . " " . $user_proposal['last_name'],
                                    "vAppointeeName" => '',##$user_proposal['Appointee'],
                                    "vAppointeeRelation" => '',##$user_proposal['AppointeeRelation'],
                                    "vCustomerPrevPolicyNumber" => ($user_proposal['business_type'] == 'rollover') ? $user_proposal['previous_policy_number'] : '',
                                    "vCustomerPreInpectionNumber" => $user_proposal['is_breakin_case'] == 'Y' ? $user_proposal['bagi_ref_no'] : "", ##
                                    "vCustomerPanNumber" => $user_proposal['pan_number'],
                                    "bIsYourVehicleFinanced" => $user_proposal['is_vehicle_finance'] == '1' ? '1' : '0',
                                    "vFinancierCode" => $user_proposal['is_vehicle_finance'] == '1' ? $user_proposal['name_of_financer'] : '',
                                    "vFinancierName" => $user_proposal['is_vehicle_finance'] == '1' ? $Financing_Institution_Name : '', ## pull from master
                                    "vFinancierAddress" => $user_proposal['is_vehicle_finance'] == '1' ? $user_proposal['financer_location'] : '',
                                    "vFinancierAgreementType" => $user_proposal['is_vehicle_finance'] == '1' ? 'Hypothecation' : '',
                                    "vCustomerCRNNumber" => "",
                                    "vCustomerLoanAcNumber" => "",
                                    "vRMCode" => "",
                                    "vBranchInwardNumber" => "",
                                    "dBranchInwardDate" => date('d/m/Y'),
                                ], */
                                "objParaPaymentDetails" => [
                                    "vCdAccountNumber" => "",
                                    "vWorkFlowId" => $user_proposal['customer_id'], ##
                                    "vQuoteId" => $user_proposal['unique_proposal_id'],
                                    "vProposalId" => "",
                                    "vIntermediaryCode" => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                                    "vCustomerId" => "",
                                    "vPaymentNumber" => $request_data['mihpayid'],
                                    "nPremiumAmount" => $user_proposal['total_premium'],
                                    "vTransactionFlag" => "BPOS",
                                    "vLoggedInUser" => $user_id,
                                    "vProductInfo" => ($premium_type=='own_damage') ? "Private Car OD Only":"Private Car Comprehensive",
                                    "vPaymentModeCode" => "PA",
                                    "vPaymentModeDescription" => "PAYMENT AGGREGATOR",
                                    "vPayerType" => "1",
                                    "vPayerCode" => "",
                                    "vPayerName" => "",
                                    "vApplicationNumber" => "",
                                    "vBranchName" => "",
                                    "vBankCode" => "0",
                                    "vBankName" => "",
                                    "vIFSCCode" => "",
                                    "vBankAccountNo" => $request_data['bank_ref_num'],
                                    "vHouseBankBranchCode" => "14851091",
                                    "vInstrumentNo" => $request_data['mihpayid'],
                                    "vCustomerName" => $user_proposal['first_name'] . " " . $user_proposal['last_name'],
                                    "vHouseBankId" => "",
                                    //"vInstrumentDate" => date('d/m/Y'),
                                    "vInstrumentDate" => date('d/m/Y', strtotime($request_data['addedon'])),
                                    "vInstrumentAmount" => $user_proposal['total_premium'],
                                    "vPaymentLinkStatus" => "",
                                    "vPaymentEntryId" => "",
                                    "vPaymentAllocationId" => "",
                                    "vPolicyNumber" => "",
                                    "vPolicyStartDate" => "",
                                    "vProposalDate" => "",
                                    "vCustomerFullName" => "",
                                    "vIntermediaryName" => "",
                                    "vCustomerEmailId" => "",
                                    "nCustomerMobileNumber " => "",
                                    "vErrorMsg  " => "",
                                    "vPosPanCard" => env('APP_ENV') == 'local' ? $POS_PAN_NO : "",
                                ],
                            ];

                            /* if ($org_type == 'C') {
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationName'] = $user_proposal['first_name'];
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationContactName'] = $user_proposal['last_name'];
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationEmail'] = $user_proposal['email'];
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationMobile'] = $user_proposal['mobile_number'];
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationPincode'] = $user_proposal['pincode'];
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationAddressLine1'] = $user_proposal['address_line1'] . '' . $user_proposal['address_line2'] . '' . $user_proposal['address_line3'];
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationTANNumber'] = '';
                                $proposal_payment_req['objParaCustomerDetails']['vOrganizationGSTNumber'] = $user_proposal['gst_number'];
                            } */
                            //sleep(10);
                            if(in_array($premium_type, ['third_party', 'third_party_breakin'])) 
                            {
                                $proposal_payment_req['objParaPaymentDetails']['vProductInfo'] = "Private Car Liability Only Policy";
                            }
                            $get_response = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PROPOSAL_PAYMENT').$user_proposal['unique_proposal_id'].'/'.$user_id. '/'.$proposal_number,$proposal_payment_req,'kotak',
                            [
                                'token' => $token_response['vTokenCode'],
                                'headers' => [
                                    'vTokenCode' => $token_response['vTokenCode']
                                ],
                                'enquiryId' => $enquiryId,
                                'requestMethod' =>'post',
                                'productName'  => $productData->product_name,
                                'company'  => 'kotak',
                                'section' => $productData->product_sub_type_code,
                                'method' =>'Proposal Payment',
                                'transaction_type' => 'proposal',
                            ]);
                            $data = $get_response['response'];

                            if($data) {
                                $proposal_payment_resp = json_decode($data, true);

                                if (isset($proposal_payment_resp['Fn_Save_Partner_Private_Car_PaymentResult'])) {
                                    $proposal_payment_resp = $proposal_payment_resp['Fn_Save_Partner_Private_Car_PaymentResult'];
                                }

                                if (isset($proposal_payment_resp['vProposalNumber']) && isset($proposal_payment_resp['vPolicyNumber']) && isset($proposal_payment_resp['vErrorMessage']) && $proposal_payment_resp['vErrorMessage'] == 'Success') {

                                    $policyNo = $proposal_payment_resp['vPolicyNumber'];
                                    $proposalNo = $proposal_payment_resp['vProposalNumber'];
                                    $prop_status = $proposal_payment_resp['vErrorMessage'];
                                    $product_code = $proposal_payment_resp['vProductCode'];
                                    UserProposal::where('user_proposal_id' , $user_proposal['user_proposal_id'])
                                    ->update([
                                        'proposal_no'    => $proposalNo,
                                        'policy_no'      => $policyNo,
                                        'product_code'   => $product_code,
                                    ]);

                                    PolicyDetails::updateOrCreate(
                                        ['proposal_id' => $user_proposal['user_proposal_id']],
                                        [
                                            'policy_number' => $policyNo,
                                            'idv' => $user_proposal['idv'],
                                            'policy_start_date' => $user_proposal['policy_start_date'] ,
                                            'ncb' => $user_proposal['ncb_discount'] ,
                                            'premium' => $user_proposal['final_payable_amount'] ,
                                            'status' => 'SUCCESS'
                                        ]
                                    );

                                    updateJourneyStage([
                                        'user_product_journey_id' => $user_proposal['user_product_journey_id'],
                                        'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                    ]);
                                    $quote_log_id = QuoteLog::where('user_product_journey_id', $enquiryId)
                                                    ->pluck('quote_id')
                                                    ->first();

                                    DB::table('payment_request_response')
                                    ->where('user_product_journey_id', $enquiryId)
                                    ->where('active',1)
                                    ->update([
                                        'status'   => STAGE_NAMES['PAYMENT_SUCCESS'],
                                        'order_id' => $proposalNo,
                                        'proposal_no' => $proposalNo,
                                        ]);

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

                                        if ($data) {
                                            $token_response = json_decode($data, true);

                                                if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {

                                                    $pdf_generate_url = config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PDF') . '' . $proposalNo . '/' . $policyNo . '/' . $product_code . '/' . $user_id;

                                                    $additional_data = [
                                                        'TokenCode' => $token_response['vTokenCode'],
                                                        'headers' => [
                                                            'vTokenCode' => $token_response['vTokenCode']
                                                        ],
                                                        'requestMethod' => 'get',
                                                        'enquiryId' => $enquiryId,
                                                        'method' => 'PDF Generation',
                                                        'section' => 'car',
                                                        'transaction_type' => 'proposal',
                                                        'productName'  => $productData->product_name,
                                                        'request_method' => 'get',
                                                    ];
                                                     sleep(3);
                                                    $get_response = getWsData($pdf_generate_url, '', 'kotak', $additional_data);
                                                    $pdf_generation_result = $get_response['response'];

                                                    if (!empty($pdf_generation_result) && checkValidPDFData(base64_decode($pdf_generation_result))) {
                                                        $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'kotak/'. md5($user_proposal['user_proposal_id']). '.pdf';
                                                        Storage::put($pdf_name, base64_decode($pdf_generation_result));

                                                        PolicyDetails::updateOrCreate(
                                                            ['proposal_id' => $user_proposal['user_proposal_id']],
                                                            [
                                                                'ic_pdf_url' => $pdf_generate_url,
                                                                'pdf_url' => $pdf_name
                                                            ]
                                                        );

                                                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                                                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                                                        updateJourneyStage($stage_data);
                                                        if(!empty($rehitdata)) {
                                                            return [
                                                                'status' => true,
                                                                'msg' => STAGE_NAMES['POLICY_ISSUED'],
                                                                'data' => [
                                                                    'policy_number' => $policyNo,
                                                                    'pdf_link' => file_url($pdf_name)
                                                                ]
                                                            ];
                                                        } else {
                                                            //$enquiryId = $proposal->user_product_journey_id;
                                                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)])); 
                                                        }

                                                    } else {
                                                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                                                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                                        updateJourneyStage($stage_data);
                                                        if(!empty($rehitdata)) {
                                                            return [
                                                                'status' => false,
                                                                'msg' => 'Pdf generation service not working'
                                                            ];
                                                        } else {
                                                            //$enquiryId = $proposal->user_product_journey_id;
                                                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
                                                        }
 
                                                    }
                                                } else {
                                                    $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                                    $stage_data['ic_id'] = $user_proposal['ic_id'];
                                                    $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                                    updateJourneyStage($stage_data);
                                                    if(!empty($rehitdata)) {
                                                        return [
                                                            'status' => false,
                                                            'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                                        ];
                                                    } else {
                                                        //$enquiryId = $proposal->user_product_journey_id;
                                                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
                                                    }

                                                }
                                        } else {
                                            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                            $stage_data['ic_id'] = $user_proposal['ic_id'];
                                            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                            updateJourneyStage($stage_data);
                                            if(!empty($rehitdata)) {
                                                return [
                                                    'status' => false,
                                                    'msg' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                                                ];
                                            } else {
                                                //$enquiryId = $proposal->user_product_journey_id;
                                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL').'?'.http_build_query(['enquiry_id' => customEncrypt($enquiryId)]));
                                            }

                                        }
                                } else { 
                                    $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                    $stage_data['ic_id'] = $user_proposal['ic_id'];
                                    $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                    updateJourneyStage($stage_data);
                                    if(!empty($rehitdata)) {
                                        return [
                                            'status' => false,
                                            'msg' => $proposal_payment_resp['vErrorMessage']
                                        ];
                                    } else {
                                        //$enquiryId = $user_proposal['user_product_journey_id'];
                                        return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                        //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                    }

                                }

                                // $product = getProductDataByIc($request_data['policy_id']);
                            } else {
                                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                                $stage_data['ic_id'] = $user_proposal['ic_id'];
                                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                                updateJourneyStage($stage_data);
                                if(!empty($rehitdata)) {
                                    return [
                                        'status' => false,
                                        'msg' => 'Proposal Payment service not working'
                                    ];
                                } else {
                                    //$enquiryId = $user_proposal['user_product_journey_id'];
                                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                                }

                            }

                        } catch (\Exception $e) {
                            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                            $stage_data['ic_id'] = $user_proposal['ic_id'];
                            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                            updateJourneyStage($stage_data);
                            if(!empty($rehitdata)) {
                                return [
                                    'status' => false,
                                    'msg' => $e->getMessage()
                                ];
                            } else {
                                //$enquiryId = $user_proposal['user_product_journey_id'];
                                return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                                //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                            }

                        }
                    } else {
                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                        updateJourneyStage($stage_data);
                        if(!empty($rehitdata)) {
                            return [
                                'status' => false,
                                'msg' => 'token generation service not working'
                            ];
                        } else {
                            //$enquiryId = $user_proposal['user_product_journey_id'];
                            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                            //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                        }

                    }
            } else {
                ##need to revisit this part
                $data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $data['ic_id'] = $user_proposal['ic_id'];
                $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
                if(!empty($rehitdata)) {
                    return [
                        'status' => false,
                        'msg' => 'token generation service not working'
                    ];
                } else {
                    //$enquiryId = $user_proposal['user_product_journey_id'];
                    return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','SUCCESS'));
                    //return redirect(config('constants.motorConstant.CAR_PAYMENT_SUCCESS_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
                }
            }

        } else {
            DB::table('payment_request_response')
                ->where('user_product_journey_id', $enquiryId)
                ->where('active',1)
                ->update([
                    'status'   => STAGE_NAMES['PAYMENT_FAILED'] ,
                    'response' => json_encode($pg_return_data),
                    ]);
            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['PAYMENT_FAILED'];
            updateJourneyStage($stage_data);
            //$enquiryId = $proposal->user_product_journey_id;
            return redirect(paymentSuccessFailureCallbackUrl($enquiryId,'CAR','FAILURE'));
            //return redirect(config('constants.motorConstant.CAR_PAYMENT_FAILURE_CALLBACK_URL') . '?' . http_build_query(['enquiry_id' => customEncrypt($user_proposal->user_product_journey_id)]));
        }
    }

    public static function generatePdf($request)
    {
        $request_data = $request->all();
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payment_data = self::check_payment_status($user_product_journey_id);
        if(!$payment_data['status'])
        {
            return  [
                'status' => false,
                'msg'    => 'Payment Is Pending'
            ];
        }

        $is_pos_disabled_renewbuy = config('constants.motorConstant.IS_POS_DISABLED_RENEWBUY');
        $is_pos = ($is_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_flag = false;

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $is_pos_flag = true;
            }
        }

        $user_proposal = UserProposal::where('user_product_journey_id', $user_product_journey_id)->first();
        $productData = getProductDataByIc($request_data['master_policy_id']);
        $stage_data = [];

        if($user_proposal['policy_no'] != NULL) {
            ##pdf generation
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
                    'enquiryId' =>  $user_product_journey_id,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'kotak',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Token Generation',
                    'transaction_type' => 'proposal',
                ]
            );
            $data = $get_response['response'];

            $user_id = $is_pos_flag ? config('constants.IcConstants.kotak.KOTAK_MOTOR_POS_USERID') : config('constants.IcConstants.kotak.KOTAK_MOTOR_USERID');

            if ($data) {
                $token_response = json_decode($data, true);

                if ($token_response['vErrorMsg'] == 'Success' && isset($token_response['vTokenCode']) && $token_response['vTokenCode'] != '') {

                    $pdf_generate_url = config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PDF') . '' . $user_proposal['proposal_no'] . '/' . $user_proposal['policy_no'] . '/' . $user_proposal['product_code'] . '/' . $user_id;
                    $additional_data = [
                        'TokenCode' => $token_response['vTokenCode'],
                        'headers' => [
                            'vTokenCode' => $token_response['vTokenCode']
                        ],
                        'requestMethod' => 'get',
                        'enquiryId' => $user_product_journey_id,
                        'method' => 'PDF Generation',
                        'section' => 'car',
                        'transaction_type' => 'proposal',
                        'productName'  => $productData->product_name,
                        'request_method' => 'get',
                    ];

                    $get_response = getWsData($pdf_generate_url, '', 'kotak', $additional_data);
                    $pdf_generation_result = $get_response['response'];
                    if (!empty($pdf_generation_result) && checkValidPDFData(base64_decode($pdf_generation_result))) {
                        $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'kotak/'. md5($user_proposal['user_proposal_id']). '.pdf';
                        Storage::put($pdf_name, base64_decode($pdf_generation_result));
                        PolicyDetails::updateOrCreate(
                            ['proposal_id' => $user_proposal['user_proposal_id']],
                            [
                                'policy_number' => $user_proposal['policy_no'],
                                'ic_pdf_url' => $pdf_generate_url,
                                'pdf_url' => $pdf_name,
                                'idv' => $user_proposal['idv'] ,
                                'policy_start_date' => $user_proposal['policy_start_date'] ,
                                'ncb' => $user_proposal['ncb_discount'] ,
                                'premium' => $user_proposal['final_payable_amount'] ,
                                'status' => 'SUCCESS'
                            ]
                        );

                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
                        updateJourneyStage($stage_data);
                        return [
                            'status' => true,
                            'msg' => STAGE_NAMES['POLICY_PDF_GENERATED'],
                            'data' => [
                                'policy_number' => $user_proposal['policy_no'],
                                'pdf_link' => file_url($pdf_name)
                            ]
                        ];
                    } else {
                        $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                        $stage_data['ic_id'] = $user_proposal['ic_id'];
                        $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                        updateJourneyStage($stage_data);
                        return [
                            'status' => false,
                            'msg' => 'pdf service service not working'
                        ];
                    }

                } else {
                    $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                    $stage_data['ic_id'] = $user_proposal['ic_id'];
                    $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    updateJourneyStage($stage_data);
                    return [
                        'status' => false,
                        'msg' => 'token generation service not working'
                    ];
                }

            } else {
                $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
                $stage_data['ic_id'] = $user_proposal['ic_id'];
                $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                updateJourneyStage($stage_data);
                return [
                    'status' => false,
                    'msg' => 'token generation service not working'
                ];
            }

        } else {
            ##need to run save proposal payment and pdf generation service
            $paymentLog =  DB::table('payment_request_response')
                                ->where('user_product_journey_id', $user_product_journey_id)
                                ->where('active',1)
                                ->first();

            if($paymentLog->status == STAGE_NAMES['PAYMENT_SUCCESS']) {
                $paymentResponse = json_decode($paymentLog->response,true);
                $paymentResponse['enquiry_id'] = $user_product_journey_id;
                $paymentResponse['policy_id']  = $request_data['master_policy_id'];
                $paymentResponse['isRehit']    = 'true';

                if(isset($paymentResponse['mihpayid']))
                {
                    $rehit = self::confirm('',(array)$paymentResponse);
                    return $rehit;
                }else
                {
                    return [
                        'status' => false,
                        'msg' => 'payment response is not proper for this case please confirm'
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'msg' => 'payment not done for this case please confirm'
                ];
            }

        }
    }

    public static function check_payment_status($enquiryId)
    {
        $get_payment_details = PaymentRequestResponse::where('user_product_journey_id', $enquiryId)->get();
        if (empty($get_payment_details)) {
            return [
                'status' => false
            ];
        }
        $key = config('constants.IcConstants.kotak.CHECKSUM_KEY_KOTAK'); #JPTXg
        $salt = config('constants.IcConstants.kotak.CAR_SALT_KOTAK');

        foreach ($get_payment_details as $row) {
            $response_Data = json_decode($row['xml_data'], true);

            if (!isset($response_Data['form_data'])) {
                continue;
            }
            $var1 = $response_Data['form_data']['txnid'];
            $command = 'verify_payment';
            $hash_string = "$key|$command|$var1|$salt";
            $hash = hash('sha512', $hash_string);
            $url = config('constants.IcConstants.kotak.PAYMENT_CHECK_END_POINT_URL');#'https://test.payu.in/merchant/postservice.php?form=2';
            $request_array = [
                'key' => $key,
                'command' => $command,
                'var1' => $var1,
                'hash' => $hash
            ];

            $get_response = getWsData(
                $url,
                http_build_query($request_array),
                'kotak',
                [
                    'enquiryId' =>  $enquiryId,
                    'requestMethod' => 'post',
                    'productName'  => 'Car',
                    'company'  => 'kotak',
                    'section' => 'car',
                    'method' => 'Payment check',
                    'transaction_type' => 'proposal',
                    'request_method' => 'post',
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
                ]
            );
            $payment_req = $get_response['response'];
            $payment_resp = json_decode($payment_req, true);
            if (($payment_resp['status'] ?? 0) == 1) {
                $payment_data = $payment_resp['transaction_details'];
                foreach ($payment_data as $value)
                {
                    if(($value['status'] ?? '') == 'success')
                    {
                        PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
                                ->update([
                                    'active'  => 0
                                ]);

                            PaymentRequestResponse::where('id', $row->id)
                                ->update([
                                    'response'      => json_encode($value),
                                    'updated_at'    => date('Y-m-d H:i:s'),
                                    'status'        => STAGE_NAMES['PAYMENT_SUCCESS'],
                                    'active'        => 1
                                ]);
                            $data['user_product_journey_id']    = $enquiryId;
                            $data['proposal_id']                = $row->user_proposal_id;
                            $data['ic_id']                      = '38';
                            $data['stage']                      = STAGE_NAMES['PAYMENT_SUCCESS'];
                            updateJourneyStage($data);

                            return [
                                'status' => true,
                                'msg' => 'success'
                            ];
                    }
                }

                
            }
        }
        return [
            'status' => false
        ];
    }

    public static function renewalProposalPayment($user_id, $user_proposal, $request_data, $premium_type, $productData)
    {
        $enquiryId = $user_proposal['user_product_journey_id'];
        $token_response = self::tokenGeneration($enquiryId, $productData, $user_proposal);
        if (!($token_response['status'] ?? false)) {
            return [
                'status' => false,
                'msg' => 'Token service issue'
            ];
        }
        $additional_details  = json_decode($user_proposal['additional_details_data'], true);
        $proposal_payment_req = [
            "vLoggedInUser" => $user_id,
            "vWorkFlowId" => $user_proposal['customer_id'], ##
            "vQuoteId" => $user_proposal['unique_proposal_id'],
            "vProposalId" => $user_proposal['proposal_no'],
            "vIntermediaryCode" => $additional_details['vIntermediaryCode'] ?? config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
            "nPremiumAmount" => $user_proposal['total_premium'],
            "vInstrumentAmount" => $user_proposal['total_premium'],
            "vInstrumentNo" => $request_data['mihpayid'],
            "vPaymentNumber" => $request_data['mihpayid'],
            "vTransactionFlag" => "BPOS",
            "vProductInfo" => ($premium_type == 'own_damage') ? "Private Car OD Only" : "Private Car Comprehensive",
            "vPaymentModeCode" => "PA",
            "vPaymentModeDescription" => "PAYMENT AGGREGATOR",
            "vPayerType" => "1",
            "vPayerCode" => "",
            "vPayerName" => "",
            "vApplicationNumber" => "",
            "vBranchName" => "",
            "vBankCode" => "0",
            "vBankName" => "",
            "vIFSCCode" => "",
            "vBankAccountNo" => $request_data['bank_ref_num'],
            "vHouseBankBranchCode" => "14851091",
            "vHouseBankId" => "",
            "vCustomerName" => $user_proposal['first_name'] . " " . $user_proposal['last_name'],
            "vInstrumentDate" => date('d/m/Y', strtotime($request_data['addedon'])),
            "vPaymentLinkStatus" => "",
            "vPaymentEntryId" => "",
            "vPaymentAllocationId" => "",
            "vPolicyNumber" => "",
            "vPolicyStartDate" => "",
            "vProposalDate" => "",
            "vCustomerFullName" => "",
            "vIntermediaryName" => "",
            "vCustomerEmailId" => "",
            "nCustomerMobileNumber " => "",
            "vCdAccountNumber" => ""
        ];

        $get_response = getWsData(
            config('constants.IcConstants.kotak.KOTAK_MOTOR_RENEWAL_POLICY_CREATION'),
            $proposal_payment_req,
            'kotak',
            [
                'token' => $token_response['token'],
                'headers' => [
                    'vTokenCode' => $token_response['token']
                ],
                'enquiryId' => $enquiryId,
                'requestMethod' => 'post',
                'productName'  => $productData->product_name,
                'company'  => 'kotak',
                'section' => $productData->product_sub_type_code,
                'method' => 'Proposal Payment',
                'transaction_type' => 'proposal',
            ]
        );
        $response = $get_response['response'];
        $response = json_decode($response, true);
        if (!empty($response['vPolicyNumber'])) {
            $policyNo = $response['vPolicyNumber'];
            $proposalNo = $response['vProposalNumber'];
            $product_code = $response['vProductCode'];

            UserProposal::where('user_proposal_id', $user_proposal['user_proposal_id'])
            ->update([
                'proposal_no'    => $proposalNo,
                'policy_no'      => $policyNo,
                'product_code'   => $product_code,
            ]);

            PolicyDetails::updateOrCreate(
                [
                    'proposal_id' => $user_proposal['user_proposal_id']
                ],
                [
                    'policy_number' => $policyNo,
                    'idv' => $user_proposal['idv'],
                    'policy_start_date' => $user_proposal['policy_start_date'],
                    'ncb' => $user_proposal['ncb_discount'],
                    'premium' => $user_proposal['final_payable_amount'],
                    'status' => 'SUCCESS'
                ]
            );

            updateJourneyStage([
                'user_product_journey_id' => $user_proposal['user_product_journey_id'],
                'stage' => STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ]);

            PaymentRequestResponse::where('user_product_journey_id', $enquiryId)
            ->where('active', 1)
            ->update([
                'status'   => STAGE_NAMES['PAYMENT_SUCCESS'],
                'order_id' => $proposalNo,
                'proposal_no' => $proposalNo,
            ]);

            return self::pdfGeneration($proposalNo, $policyNo, $product_code, $user_id, $user_proposal, $productData);
        }

        return ['status' => false, 'msg' => 'Policy number not found'];
    }

    public static function pdfGeneration($proposalNo, $policyNo, $product_code, $user_id, $user_proposal, $productData)
    {
        $enquiryId = $user_proposal['user_product_journey_id'];
        $token_response = self::tokenGeneration($enquiryId, $productData, $user_proposal);
        if (!($token_response['status'] ?? false)) {
            return [
                'status' => false,
                'msg' => 'Token service issue'
            ];
        }
        $pdf_generate_url = config('constants.IcConstants.kotak.END_POINT_URL_KOTAK_MOTOR_PDF') . '' . $proposalNo . '/' . $policyNo . '/' . $product_code . '/' . $user_id;

        $additional_data = [
            'TokenCode' => $token_response['token'],
            'headers' => [
                'vTokenCode' => $token_response['token']
            ],
            'requestMethod' => 'get',
            'enquiryId' => $enquiryId,
            'method' => 'PDF Generation',
            'section' => 'car',
            'transaction_type' => 'proposal',
            'productName'  => $productData->product_name,
            'request_method' => 'get',
        ];
        sleep(3);
        $get_response = getWsData($pdf_generate_url, '', 'kotak', $additional_data);
        $pdf_generation_result = $get_response['response'];

        if (!empty($pdf_generation_result) && checkValidPDFData(base64_decode($pdf_generation_result))) {
            $pdf_name = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'kotak/' . md5($user_proposal['user_proposal_id']) . '.pdf';
            Storage::put($pdf_name, base64_decode($pdf_generation_result));

            PolicyDetails::updateOrCreate(
                ['proposal_id' => $user_proposal['user_proposal_id']],
                [
                    'ic_pdf_url' => $pdf_generate_url,
                    'pdf_url' => $pdf_name
                ]
            );

            $stage_data['user_product_journey_id'] = $user_proposal['user_product_journey_id'];
            $stage_data['ic_id'] = $user_proposal['ic_id'];
            $stage_data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
            updateJourneyStage($stage_data);

            return [
                'status' => true,
                'msg' => STAGE_NAMES['POLICY_ISSUED'],
                'data' => [
                    'policy_number' => $policyNo,
                    'pdf_link' => file_url($pdf_name)
                ]
            ];
        }

        return ['status' => false, 'msg' => 'Pdf service issue'];
    }

    public static function tokenGeneration($enquiryId, $productData, $user_proposal)
    {
        $pos_data = CvAgentMapping::where('user_product_journey_id', $enquiryId)
        ->where('seller_type', 'P')
        ->first();

        $is_pos = config('constants.motorConstant.IS_POS_ENABLED');

        $is_pos_flag = $is_pos == 'Y' && ($pos_data->seller_type ?? '' == 'P') && $user_proposal->idv <= 5000000;

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
        $token_response = json_decode($data, true);
        if (
            !empty($token_response['vErrorMsg'])
            && $token_response['vErrorMsg'] == 'Success'
            && !empty($token_response['vTokenCode'])
        ) {
            return ['status' => true, 'token' => $token_response['vTokenCode']];
        }

        return ['status' => false];
    }
}
