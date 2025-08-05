<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\SyncPremiumDetail\Car\HdfcErgoPremiumDetailController;
use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\CvBreakinStatus;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\HdfcErgoRtoLocation;
use App\Models\HdfcErgoPinCityState;
use App\Models\HdfcErgoMotorPincodeMaster;
use App\Models\HdfcErgoV2MotorPincodeMaster;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Proposal\Services\Car\V2\hdfcErgoSubmitProposal as hdfcErgoSubmitProposalv2;
use App\Http\Controllers\Proposal\Services\Car\V1\hdfcErgoSubmitProposal as hdfcErgoSubmitProposalv1;
use App\Models\ProposalExtraFields;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class hdfcErgoSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y')
        {
            return self::submitV2($proposal, $request);
        }
        else
        {
            if(config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_CAR') == 'Y')
            {
                if (config('IC.HDFC_ERGO.V1.CAR.ENABLE') == 'Y') {
                    return hdfcErgoSubmitProposalv1::submit($proposal, $request);
                } else{
                    return self::submitV1NewFlow($proposal, $request);
                }
            }
            else{ 
                return self::submitV1($proposal, $request);
            }
        }
    }

    public static function submitV1($proposal, $request)
    {
        try {
            $enquiryId = customDecrypt($request['enquiryId']);
            $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $requestData = getQuotation($enquiryId);
            $productData = getProductDataByIc($request['policyId']);
            $breaki_id='47894';
            $quote_data = json_decode($quote_log->quote_data, true);
            $master_policy = MasterPolicy::find($request['policyId']);
            $is_breakin     = false;//((strpos($requestData->business_type, 'breakin') === false) ? false : true);
            $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
                $policy_start_date = date('d/m/Y');
            } elseif ($requestData->business_type == 'rollover') {
                $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }
            
            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code') ->first();
            if($premium_type == 'third_party_breakin')
            {
                $policy_start_date =  today()->addDay(3)->format('d/m/Y');
            }
            
            $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
            //die;
            // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y')) {
            //     return [
            //         'premium_amount' => 0,
            //         'status' => false,
            //         'message' => 'Zero dep is not allowed because zero dep is not part of your previous policy',
            //     ];
            // }
            $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
            $mmv_data = (object)array_change_key_case((array)$mmv, CASE_LOWER);
            $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
            $rto_location = HdfcErgoRtoLocation::where('rto_code', $rto_code)->first();
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new DateTime($vehicleDate);
            $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
            $requestData->previous_policy_expiry_date=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $car_age = ceil($age / 12);
            
            if($premium_type =='third_party_breakin'){
                $premium_type ='third_party';
            }
            if($premium_type =='own_damage_breakin'){
                $premium_type ='own_damage';
            }
            switch ($premium_type) {

                case 'third_party_breakin':
                    $premium_type = 'third_party';
                    break;
                case 'own_damage_breakin':
                    $premium_type = 'own_damage';
                    break;

            }

            switch ($requestData->business_type) {

                case 'rollover':
                    $business_type = 'Roll Over';
                    break;

                case 'newbusiness':
                    $business_type = 'New Business';
                    break;

                default:
                    $business_type = $requestData->business_type;
                    break;

            }

            $ProductCode = '2311';

            if ($premium_type == "third_party") {
                $ProductCode = '2319';

            }

            $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
            $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $ElectricalaccessSI = $RSACover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
            $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers); // new business
            $PreviousNilDepreciation = '0'; // addon
            $tppd_cover = 0;
            $voluntary_deductible =0;
                if (isset($requestData->voluntary_excess_value)) {
                    if($requestData->voluntary_excess_value==20000 || $requestData->voluntary_excess_value==25000){
                        $voluntary_deductible = $requestData->voluntary_excess_value;
                    }
                }
            if (!empty($discounts)) {
                foreach ($discounts as $key => $roww) {
                    if ($roww['name'] == 'TPPD Cover') {
                        $tppd_cover = 1;
                    }
                }
            }
            $additional_details = json_decode($proposal->additional_details);
            if ($requestData->business_type == 'newbusiness') {
                //        if ($quote_log->premium_json['businessType'] == 'newbusiness') {
                $proposal->previous_policy_number = '';
                $proposal->previous_insurance_company = '';
                $PreviousPolicyFromDt = '';
                $PreviousPolicyToDt = '';
                $policy_start_date = date('d/m/Y');
                // $policy_end_date = today()->addYear(3)->subDay(1)->format('d/m/Y');
                if ($premium_type == 'comprehensive') {
                    $policy_end_date =  today()->addYear(1)->subDay(1)->format('d/m/Y');
                } elseif ($premium_type == 'third_party') {
                    $policy_end_date =   today()->addYear(3)->subDay(1)->format('d/m/Y');
                }
                $PolicyProductType = '3TP1OD';
                $previous_ncb = "";
                $db_policy_enddate = today()->addYear(3)->subDay(1)->format('d/m/Y');
                $BusinessType = 'New Vehicle';
                $proposal_date = $policy_start_date;
            } else {
                $policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
                if ($is_breakin) {
                    $policy_start_date = date('Y-m-d');
                }
                $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
                $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
                $PolicyProductType = '1TP1OD';
                $previous_ncb = $requestData->previous_ncb;
                $BusinessType = 'Roll Over';
                $proposal_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));
                $prev_policy_details = $additional_details->prepolicy ?? '';
            }
            $consumable = $engine_protection = $ncb_protction = $nilDepreciationCover = $RSACover = $PAforUnnamedPassenger = $PAforUnnamedPassenger =
            $KeyReplacementYN = $InvReturnYN = $engine_protection = $LossOfPersonBelongYN = $LLtoPaidDriverYN = $PAPaidDriverConductorCleanerSI = $tyresecure = 0;
            $LLtoPaidDriverYN = $geoExtension='0';
            $isBatteryProtect = 0;
            foreach ($additional_covers as $key => $value) {
                if (in_array('LL paid driver', $value)) {
                    $LLtoPaidDriverYN = '1';
                }

                if (in_array('PA cover for additional paid driver', $value)) {
                    $PAPaidDriverConductorCleaner = 1;
                    $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
                }

                if (in_array('Unnamed Passenger PA Cover', $value)) {
                    $PAforUnnamedPassenger = $mmv_data->seating_capacity;
                    $PAforUnnamedPassengerSI = $value['sumInsured'];
                }
            }

            foreach ($accessories as $key => $value) {
                if (in_array('geoExtension', $value)) {
                    $geoExtension ='1';
                }
                if (in_array('Electrical Accessories', $value)) {
                    $Electricalaccess = 1;
                    $ElectricalaccessSI = $value['sumInsured'];
                }

                if (in_array('Non-Electrical Accessories', $value)) {
                    $NonElectricalaccess = 1;
                    $NonElectricalaccessSI = $value['sumInsured'];
                }

                if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                    $externalCNGKIT = 'LPG';
                    $externalCNGKITSI = $value['sumInsured'];
                }
            }
            $is_cpa = "true";
            $CPA_Tenure = '1';
            if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
                $is_cpa = "false";
                $CPA_Tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure'])? (string)$selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
            }
            if($premium_type == 'newbusiness')
            {
                $CPA_Tenure = isset($CPA_Tenure) ? $CPA_Tenure : 3;
            }   
            foreach ($addons as $key => $value) {
                if (in_array('Zero Depreciation', $value)) {
                    $nilDepreciationCover = '1';
                }
                if (in_array('Road Side Assistance', $value)) {
                    $RSACover = '1';
                }
                if (in_array('Key Replacement', $value)) {
                    $KeyReplacementYN = '1';
                }
                if (in_array('Return To Invoice', $value)) {
                    $InvReturnYN = '1';
                }
                if (in_array('NCB Protection', $value)) {
                    $ncb_protction = '1';
                }
                if (in_array('Engine Protector', $value)) {
                    $engine_protection = '1';
                }
                if (in_array('Consumable', $value)) {
                    $consumable = '1';
                }
                if (in_array('Loss of Personal Belongings', $value)) {
                    $LossOfPersonBelongYN = '1';
                }
                if (in_array('Tyre Secure', $value) && $car_age <= 3 && !in_array($mmv_data->vehicle_manufacturer, ['HONDA', 'TATA MOTORS LTD'])) {
                    $tyresecure = '1';
                }

                if (in_array('Battery Protect', $value) && $requestData->fuel_type == 'ELECTRIC') {
                    $isBatteryProtect = 1;
                }
            }
            if ($requestData->vehicle_owner_type == "I") {
                if (strtoupper($proposal->gender) == "MALE" || strtoupper($proposal->gender) == "M") {
                    $salutation = 'MR';
                } else {
                    if ((strtoupper($proposal->gender) == "FEMALE" || strtoupper($proposal->gender) == "F") && $proposal->marital_status == "Single") {
                        $salutation = 'MS';
                    } else {
                        $salutation = 'MRS';
                    }
                }
            } else {
                $salutation = 'M/S';
            }
            // salutaion
            // CPA
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos = false;
            $posp_email = '';
            $posp_name = '';
            $posp_unique_number = '';
            $posp_pan_number = '';
            $posp_aadhar_number = '';
            $posp_contact_number = '';
            $pos_data=CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id', $proposal['user_proposal_id'])
            ->where('seller_type', 'P')
            ->first();

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
                if (config('HDFC_CAR_V1_IS_NON_POS') != 'Y') {
                    $hdfc_pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                        ->pluck('hdfc_ergo_code')
                        ->first();
                    if ((empty($hdfc_pos_code) || is_null($hdfc_pos_code))) {
                        return [
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'HDFC POS Code Not Available'
                        ];
                    }
                    $is_pos = true;
                    $pos_code = $hdfc_pos_code;
                }
            }elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' && $quote->idv <= 5000000){
                $is_pos = true;
                $pos_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
            }

            $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cpareason = '';
            if (!empty($prev_policy_details)) {
                $cpareason = isset($prev_policy_details->reason) ? $prev_policy_details->reason : '';
                $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp : '';
                $cPAPolicyNo = isset($prev_policy_details->cpaPolicyNumber) ? $prev_policy_details->cpaPolicyNumber : '';
                $cPASumInsured = isset($prev_policy_details->cpaSumInsured) ? $prev_policy_details->cpaSumInsured : '';
                $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y') : '';
                $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y') : '';
            }
            // CPA
            $vehicleDetails = $additional_details->vehicle;
            if ($vehicale_registration_number[0] == 'NEW') {
                $vehicale_registration_number[0] = '';
            }
            //checking last addons
            $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsRTI_Cover = false;
            if(!empty($proposal->previous_policy_addons_list))
            {
                $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                foreach ($previous_policy_addons_list as $key => $value) {
                   if($key == 'zeroDepreciation' && $value)
                   {
                        $PreviousPolicy_IsZeroDept_Cover = true;  
                   }
                   else if($key == 'returnToInvoice' && $value)
                   {
                        $PreviousPolicy_IsRTI_Cover = true;
                   }
                }                
            }
            
            if($nilDepreciationCover && !$PreviousPolicy_IsZeroDept_Cover)
            {
               $is_breakin = true;
            }
            if($InvReturnYN && !$PreviousPolicy_IsRTI_Cover)
            {
               $is_breakin = true;
            }
            if($requestData->business_type == 'newbusiness')
            {
                $is_breakin = false;
            }
            // token Generation
            $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
            // $transactionid = customEncrypt($enquiryId);
            $additionData = [
                'type' => 'gettoken',
                'method' => 'tokenGeneration',
                'section' => 'car',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name. " ($business_type)",
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode,// config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                'TRANSACTIONID' => $transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
            ];

            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);

            $token = $get_response['response'];
            $token_data = json_decode($token, TRUE);

            if (isset($token_data['Authentication']['Token'])) {
                $proposal_array = [
                    'TransactionID' => $transactionid,//config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR') ,//$enquiryId,
                    'Policy_Details' => [
                        'PolicyStartDate' => $policy_start_date,
                        'ProposalDate' => date('d/m/Y'),
                        'BusinessType_Mandatary' => $BusinessType,
                        'VehicleModelCode' => $mmv_data->vehicle_model_code,
                        'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                        'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'YearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                        'RTOLocationCode' => $rto_location->rto_location_code,
                        'Vehicle_IDV' => $quote_log->idv,
                        'PreviousPolicy_CorporateCustomerId_Mandatary' => $proposal->previous_insurance_company,//$prev_policy_company,
                        'PreviousPolicy_NCBPercentage' => $previous_ncb,
                        'PreviousPolicy_PolicyEndDate' => $proposal->prev_policy_expiry_date,
                        'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                        'PreviousPolicy_PolicyNo' => $proposal->previous_policy_number,
                        'PreviousPolicy_PreviousPolicyType' => (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage' ) ? 'Comprehensive Package' : 'TP'),
                        'Registration_No' => $requestData->business_type == 'newbusiness' ? '' : $proposal->vehicale_registration_number,
                        'EngineNumber' => $proposal->engine_number,
                        'ChassisNumber' => $proposal->chassis_number,
                        'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                        'FinancierCode' => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : '',
                        'BranchName' => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : '',
                        "PreviousPolicy_IsZeroDept_Cover" => $PreviousPolicy_IsZeroDept_Cover,
                        "PreviousPolicy_IsRTI_Cover" => $PreviousPolicy_IsRTI_Cover
                    ],
                    'Req_GCV' => null,
                    'Req_MISD' => null,
                    'Req_PCV' => null,
                    'IDV_DETAILS' => null,
                    'Req_ExtendedWarranty' => null,
                    'Req_Policy_Document' => null,
                    'Req_PEE' => null,
                    'Req_TW' => null,
                    'Req_RE' => null,
                    'Req_Fire2111' => null,
                    'Req_ClaimIntimation' => null,
                    'Req_ClaimStatus' => null,
                    'Req_Renewal' => null,
                    'Req_HInsurance' => null,
                    'Req_IPA' => null,
                    'Req_CI' => null,
                    'Req_HomeInsurance' => null,
                    'Req_RetailTravel' => null,
                    'Req_HCA' => null,
                    'Req_HF' => null,
                    'Req_HI' => null,
                    'Req_HSTPI' => null,
                    'Req_HSTPF' => null,
                    'Req_ST' => null,
                    'Req_WC' => null,
                    'Req_BSC' => null,
                    'Req_Discount' => null,
                    'Req_POSP' => null,
                    'Req_HSF' => null,
                    'Req_HSI' => null,
                    'Req_CustDec' => null,
                    'Req_TW_Multiyear' => null,
                    'Req_OptimaRestore' => null,
                    'Req_Aviation' => null,
                    'Req_NE' => null,
                    'Req_TravelXDFD' => null,
                    'Req_OptimaSenior' => null,
                    'Req_Energy' => null,
                    'Req_HW' => null,
                    'Req_EH' => null,
                    'Req_Ican' => null,
                    'Req_GetStatus' => null,
                    'Request_UploadDocument' => null,
                    'Req_PolicyDetails' => null,
                    'Req_AMIPA' => null,
                    'Req_PolicyStatus' => null,
                    'Req_MasterData' => null,
                    'Req_ChequeDetails' => null,
                    'Req_appstatus' => null,
                    'Req_OptimaSuper' => null,
                    'PaymentStatusDetails' => null,
                    'Req_PospCodeStatus' => null,
                    'Req_TvlSportify' => null,
                    'Request_Data_OS' => null,
                    'Req_GHCIP' => null,
                    'Req_PolicyConfirmation' => null,
                    'Req_MarineOpen' => null,
                    'Req_CyberSachet' => null,
                    'Req_PvtCar' => [
                        'IsLimitedtoOwnPremises'=>'0',
                        'ExtensionCountryCode' => $geoExtension,
                        'ExtensionCountryName' => '',
                        'BiFuelType' => ($externalCNGKITSI>0 ? "CNG":""),
                        'POSP_CODE' => [],
                        'BiFuel_Kit_Value' => $externalCNGKITSI,
                        'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : ($premium_type == "third_party" ? "" : 'OD Plus TP'),
                        //'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : 'OD Plus TP', // as per the IC in case of tp only value for POLICY_TYPE will be null
                        'LLPaiddriver' => $LLtoPaidDriverYN,
                        //                    "BreakIN_ID" =>(($is_breakin)?'47894':''),
                        //                    "EMIAmount" => "0",
                        'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
                        'IsZeroDept_Cover' => $nilDepreciationCover,
                        'IsNCBProtection_Cover' => (int)$ncb_protction,
                        'IsRTI_Cover' => (int)$InvReturnYN,
                        'IsCOC_Cover' => (int)$consumable,
                        'IsEngGearBox_Cover' => (int)$engine_protection,
                        'IsEA_Cover' => (int)$RSACover,
                        'IsEAW_Cover' => (int)$KeyReplacementYN,
                        'IsTyreSecure_Cover' => (int)$tyresecure,
                        'isBatteryChargerAccessoryCover' => $isBatteryProtect,
                        'NoofUnnamedPerson' => (int)$PAforUnnamedPassenger,
                        'IsLossofUseDownTimeProt_Cover' =>(int)$LossOfPersonBelongYN,
                        'UnnamedPersonSI' => (int)$PAforUnnamedPassengerSI,
                        'ElecticalAccessoryIDV' => (int)$ElectricalaccessSI,
                        'NonElecticalAccessoryIDV' => (int)$NonElectricalaccessSI,
                        'CPA_Tenure' => $CPA_Tenure,#($requestData->business_type == 'newbusiness' ? '3' : '1'),
                        'Effectivedrivinglicense' =>$is_cpa,
                        'Voluntary_Excess_Discount' => $voluntary_deductible,
                        'POLICY_TENURE' => '1',
                        // 'TPPDLimit' => $tppd_cover, as per #23856
                        "Owner_Driver_Nominee_Name" =>($proposal->owner_type == 'I') ? $proposal->nominee_name : "",
                        "Owner_Driver_Nominee_Age" =>($proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                        "Owner_Driver_Nominee_Relationship" =>(!$premium_type == 'own_damage' || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                        //                    "Owner_Driver_Appointee_Name" => ($proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                        //                    "Owner_Driver_Appointee_Relationship" => ($proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",

                    ],
                ];
                if ($premium_type == 'own_damage') {
                    $proposal_array['Policy_Details']['PreviousPolicy_TPENDDATE'] = $proposal->tp_end_date;#$proposal->tp_start_date
                    $proposal_array['Policy_Details']['PreviousPolicy_TPSTARTDATE'] = $proposal->tp_start_date;
                    $proposal_array['Policy_Details']['PreviousPolicy_TPINSURER'] = $proposal->tp_insurance_company;
                    $proposal_array['Policy_Details']['PreviousPolicy_TPPOLICYNO'] = $proposal->tp_insurance_number;
                }  
                if(!$is_pos)
                {
                    unset($proposal_array['Req_PvtCar']['POSP_CODE']);
                }
                if($is_pos)
                {
                    $proposal_array['Req_PvtCar']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
                }
                $additionData = [
                    'type' => 'PremiumCalculation',
                    'method' => 'Premium Calculation',//'Proposal Submit',
                    'requestMethod' => 'post',
                    'section' => 'car',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name. " ($business_type)",
                    'TOKEN' => $token_data['Authentication']['Token'],
                    'transaction_type' => 'proposal',
                    'PRODUCT_CODE' => $ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                    'TRANSACTIONID' => $transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                ];

                if($requestData->previous_policy_type != 'Not sure' && $requestData->business_type != 'newbusiness' &&  empty($proposal->previous_policy_number) && empty($proposal->previous_insurance_company))
                {
                    return
                    [
                        'status' => false,
                        'message' => 'Previous policy number and previous insurer is mandetory if previous policy type is ' . $requestData->previous_policy_type
                    ];
                }
                
                if($requestData->previous_policy_type == 'Not sure')
                {
                    unset($proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_PolicyNo']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType']);
                    
                    
                    
                   //$proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = NULL;
                  // $proposal_array['Policy_Details']['PreviousPolicy_PolicyClaim'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_PolicyNo'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType'] = NULL;
                }
                //            print_r(json_encode($additionData));die;
                //        print_r([config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_PREMIUM'),json_encode($additionData)]);
                //        print_r(json_encode($proposal_array));
                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_PREMIUM'), $proposal_array, 'hdfc_ergo', $additionData);

                $getpremium = $get_response['response'];
                $arr_premium = json_decode($getpremium, TRUE);
                //            print_r(json_encode($arr_premium));die;
                $vehicleDetails = [
                    'manufacture_name' => $mmv_data->vehicle_manufacturer,
                    'model_name' => $mmv_data->vehicle_model_name,
                    'version' => $mmv_data->variant,
                    'fuel_type' => $mmv_data->fuel,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'carrying_capacity' => $mmv_data->carrying_capacity,
                    'cubic_capacity' => $mmv_data->cubic_capacity,
                    'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
                    'vehicle_type' => $mmv_data->veh_ob_type ?? '',
                ];
                if (isset($arr_premium['StatusCode']) && $arr_premium['StatusCode'] == '200') {
                    $premium_data = $arr_premium['Resp_PvtCar'];
                    $proposal->proposal_no = $arr_premium['TransactionID'];
                    $proposal->ic_vehicle_details = $vehicleDetails;
                    $proposal->save();
                    $Nil_dep = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $age_discount = $lpg_cng_tp = $lpg_cng = $Bonus_Discount = $automobile_discount = $antitheft
                        = $basic_tp_premium = $electrical_accessories = $tppd_value =
                        $non_electrical_accessories = $ncb_protction = $ll_paid_driver =
                        $ncb_protection = $consumables_cover = $Nil_dep = $roadside_asst =
                        $key_replacement = $loss_of_personal_belongings = $eng_protector =
                        $rti = $incon_allow = $Basic_OD_Discount = $electrical_Discount =
                        $non_electrical_Discount = $lpg_od_premium_Discount = $tyre_secure = $GeogExtension_od= $GeogExtension_tp=$OwnPremises_OD= $OwnPremises_TP=0;
                        $batteryProtect = 0;

                    if (!empty($premium_data['PAOwnerDriver_Premium'])) {
                        $pa_owner_driver = round($premium_data['PAOwnerDriver_Premium']);
                    }
                    if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                        $Nil_dep = round($premium_data['Vehicle_Base_ZD_Premium']);
                    }

                    if (!empty($premium_data['GeogExtension_ODPremium'])) {
                        $GeogExtension_od = round($premium_data['GeogExtension_ODPremium']);
                    }
                    if (!empty($premium_data['GeogExtension_TPPremium'])) {
                        $GeogExtension_tp= round($premium_data['GeogExtension_TPPremium']);
                    }

                    if (!empty($premium_data['LimitedtoOwnPremises_OD_Premium'])) {
                        $OwnPremises_OD = round($premium_data['LimitedtoOwnPremises_OD_Premium']);
                    }
                    if (!empty($premium_data['LimitedtoOwnPremises_TP_Premium'])) {
                        $OwnPremises_TP = round($premium_data['LimitedtoOwnPremises_TP_Premium']);
                    }

                    if (!empty($premium_data['EA_premium'])) {
                        $roadside_asst = round($premium_data['EA_premium']);
                    }
                    if (!empty($premium_data['Loss_of_Use_Premium'])) {
                        $loss_of_personal_belongings = round($premium_data['Loss_of_Use_Premium']);
                    }
                    if (!empty($premium_data['Vehicle_Base_NCB_Premium'])) {
                        $ncb_protection = round($premium_data['Vehicle_Base_NCB_Premium']);
                    }
                    if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                        $ncb_discount = round($premium_data['NCBBonusDisc_Premium']);
                    }
                    if (!empty($premium_data['Vehicle_Base_ENG_Premium'])) {
                        $eng_protector = round($premium_data['Vehicle_Base_ENG_Premium']);
                    }
                    if (!empty($premium_data['Vehicle_Base_COC_Premium'])) {
                        $consumables_cover = round($premium_data['Vehicle_Base_COC_Premium']);
                    }
                    if (!empty($premium_data['Vehicle_Base_RTI_Premium'])) {
                        $rti = round($premium_data['Vehicle_Base_RTI_Premium']);
                    }
                    if (!empty($premium_data['EAW_premium'])) {
                        $key_replacement = round($premium_data['EAW_premium']);
                    }
                    if (!empty($premium_data['UnnamedPerson_premium'])) {
                        $pa_unnamed = round($premium_data['UnnamedPerson_premium']);
                    }
                    if (!empty($premium_data['Electical_Acc_Premium'])) {
                        $electrical_accessories = round($premium_data['Electical_Acc_Premium']);
                    }
                    if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                        $non_electrical_accessories = round($premium_data['NonElectical_Acc_Premium']);
                    }
                    if (!empty($premium_data['BiFuel_Kit_OD_Premium'])) {
                        $lpg_cng = round($premium_data['BiFuel_Kit_OD_Premium']);
                    }
                    if (!empty($premium_data['BiFuel_Kit_TP_Premium'])) {
                        $lpg_cng_tp = round($premium_data['BiFuel_Kit_TP_Premium']);
                    }
                    if (!empty($premium_data['PAPaidDriver_Premium'])) {
                        $pa_paid_driver = round($premium_data['PAPaidDriver_Premium']);
                    }
                    if (!empty($premium_data['PaidDriver_Premium'])) {
                        $ll_paid_driver = round($premium_data['PaidDriver_Premium']);
                    }
                    if (!empty($premium_data['VoluntartDisc_premium'])) {
                        $voluntary_excess = round($premium_data['VoluntartDisc_premium']);
                    }
                    if (!empty($premium_data['Vehicle_Base_TySec_Premium'])) {
                        $tyre_secure = round($premium_data['Vehicle_Base_TySec_Premium']);
                    }
                    if (!empty($premium_data['AntiTheftDisc_Premium'])) {
                        $anti_theft = round($premium_data['AntiTheftDisc_Premium']);
                    }
                    if (!empty($premium_data['Net_Premium'])) {
                        $final_net_premium = round($premium_data['Net_Premium']);
                    }
                    if (!empty($premium_data['Total_Premium'])) {
                        $final_payable_amount = round($premium_data['Total_Premium']);
                    }
                    if (!empty($premium_data['Basic_OD_Premium'])) {
                        $basic_od_premium = round($premium_data['Basic_OD_Premium']);
                    }
                    if (!empty($premium_data['Basic_TP_Premium'])) {
                        $basic_tp_premium = round($premium_data['Basic_TP_Premium']);
                    }
                    if (!empty($premium_data['TPPD_premium'])) {
                        $tppd_value = round($premium_data['TPPD_premium']);
                    }
                    if (!empty($premium_data['InBuilt_BiFuel_Kit_Premium'])) {
                        $lpg_cng_tp = round($premium_data['InBuilt_BiFuel_Kit_Premium']);
                    }
                    if (!empty($premium_data['BatteryChargerAccessory_Premium'])) {
                        $batteryProtect = round($premium_data['BatteryChargerAccessory_Premium']);
                    }
                    if ($electrical_accessories > 0) {
                        $Nil_dep += (int)$premium_data['Elec_ZD_Premium'];
                        $engine_protection += (int)$premium_data['Elec_ENG_Premium'];
                        $ncb_protection += (int)$premium_data['Elec_NCB_Premium'];
                        $consumables_cover += (int)$premium_data['Elec_COC_Premium'];
                        $rti += (int)$premium_data['Elec_RTI_Premium'];
                    }
                    if ($non_electrical_accessories > 0) {
                        $Nil_dep += (int)$premium_data['NonElec_ZD_Premium'];
                        $engine_protection += (int)$premium_data['NonElec_ENG_Premium'];
                        $ncb_protection += (int)$premium_data['NonElec_NCB_Premium'];
                        $consumables_cover += (int)$premium_data['NonElec_COC_Premium'];
                        $rti += (int)$premium_data['NonElec_RTI_Premium'];
                    }

                    if ($lpg_cng > 0) {
                        $Nil_dep += (int)$premium_data['Bifuel_ZD_Premium'];
                        $engine_protection += (int)$premium_data['Bifuel_ENG_Premium'];
                        $ncb_protection += (int)$premium_data['Bifuel_NCB_Premium'];
                        $consumables_cover += (int)$premium_data['Bifuel_COC_Premium'];
                        $rti += (int)$premium_data['Bifuel_RTI_Premium'];
                    }

                    $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti + $batteryProtect;
                    //        print_r([$premium_data['Elec_ZD_Premium'], $premium_data['NonElec_ZD_Premium'], $premium_data['Bifuel_ZD_Premium']]);
                                    $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp)-$tppd_value+$GeogExtension_tp+$OwnPremises_TP;
                    //print_r([$basic_tp_premium,$pa_owner_driver,$ll_paid_driver,$pa_paid_driver,$pa_unnamed,$lpg_cng_tp,$tppd_value]);
                    //die;

                    $proposal_array['Customer_Details'] = [
                        'Customer_Type' => ($proposal->owner_type == 'I') ? 'Individual' : 'Corporate',
                        'Company_Name' => ($proposal->owner_type == 'I') ? '' : $proposal->first_name,
                        'Customer_FirstName' => ($proposal->owner_type == 'I') ? $proposal->first_name : $proposal->last_name,
                        'Customer_MiddleName' => '',
                        'Customer_LastName' => ($proposal->owner_type == 'I') ? (!empty($proposal->last_name) ? $proposal->last_name : '.' ) : '',
                        'Customer_DateofBirth' => date('d/m/Y', strtotime($proposal->dob)),
                        'Customer_Email' => $proposal->email,
                        'Customer_Mobile' => $proposal->mobile_number,
                        'Customer_Telephone' => '',
                        'Customer_PanNo' => $proposal->pan_number,
                        'Customer_Salutation' => $salutation,#($proposal->owner_type == 'I') ? 'MR' : 'M/S',
                        'Customer_Gender' => $proposal->gender,
                        'Customer_Perm_Address1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                        'Customer_Perm_Address2' => $proposal->address_line2,
                        'Customer_Perm_Apartment' => '',
                        'Customer_Perm_Street' => '',
                        'Customer_Perm_PinCode' => $proposal->pincode,
                        'Customer_Perm_PinCodeLocality' => '',
                        'Customer_Perm_CityDirstCode' => $proposal->city_id,
                        'Customer_Perm_CityDistrict' => $proposal->city,
                        'Customer_Perm_StateCode' => $proposal->state_id,
                        'Customer_Perm_State' => $proposal->state,
                        'Customer_Mailing_Address1' => $proposal->is_car_registration_address_same == 1 ? removeSpecialCharactersFromString($proposal->address_line1, true) : $proposal->car_registration_address1,
                        'Customer_Mailing_Address2' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                        'Customer_Mailing_Apartment' => '',
                        'Customer_Mailing_Street' => '',
                        'Customer_Mailing_PinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                        'Customer_Mailing_PinCodeLocality' => '',
                        'Customer_Mailing_CityDirstCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->city_id : $proposal->car_registration_city_id,
                        'Customer_Mailing_CityDistrict' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
                        'Customer_Mailing_StateCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->state_id : $proposal->car_registration_state_id,
                        'Customer_Mailing_State' => $proposal->is_car_registration_address_same == 1 ? $proposal->state : $proposal->car_registration_state,
                        'Customer_GSTIN_Number' => $proposal->gst_number
                    ];

                    if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                        $proposal_array['Customer_Details']['Customer_Pehchaan_id'] = $proposal->ckyc_reference_id;                            
                    }

                    $proposal_array['Type_of_payment'] = null;
                    $proposal_array['PaymentStatusDetails'] = null;
                    $proposal_array['Payment_Details'] = null;
                    
                    $od_premium = $premium_data['Basic_OD_Premium'] + $non_electrical_accessories + $electrical_accessories + $lpg_cng+$GeogExtension_od+$OwnPremises_OD;
                    //               print_r($od_premium);die;
                    
                    HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

                    $additionData['method'] = 'Proposal Submit';
                    //$additionData['PRODUCT_CODE'] = '2311';
                    //$proposal_url = 'https://integrations.hdfcergo.com/HEI.IntegrationService/Integration/CreateProposal';
                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_PROPOSAL'), $proposal_array, 'hdfc_ergo', $additionData);
                    $proposal_submit_response = $get_response['response'];
                    $proposal_submit_response = json_decode($proposal_submit_response,true);
                    if($proposal_submit_response['Error'] ?? '' != 'PAYMENT DETAILS NOT FOUND !')
                    {
                       return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg'    => $proposal_submit_response['Error']
                        ]); 
                    }

                    if (isset($proposal_submit_response['StatusCode']) && $proposal_submit_response['StatusCode'] == '200') {
                        $proposal_data = $proposal_submit_response['Resp_PvtCar'];
                        $proposal->proposal_no = $proposal_submit_response['TransactionID'];
                        $proposal->ic_vehicle_details = $vehicleDetails;
                        $proposal->save();
                        $Nil_dep = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $age_discount = $lpg_cng_tp = $lpg_cng = $Bonus_Discount = $automobile_discount = $antitheft = $basic_tp_premium = $electrical_accessories = $tppd_value = $non_electrical_accessories = $ncb_protction = $ll_paid_driver = $ncb_protection = $consumables_cover = $Nil_dep = $roadside_asst = $key_replacement = $loss_of_personal_belongings = $eng_protector = $rti = $incon_allow = $Basic_OD_Discount = $electrical_Discount = $non_electrical_Discount = $lpg_od_premium_Discount = $tyre_secure = $GeogExtension_od = $GeogExtension_tp = $OwnPremises_OD = $OwnPremises_TP = $voluntary_excess = 0;

                        if (!empty($proposal_data['PAOwnerDriver_Premium'])) {
                            $pa_owner_driver = round($proposal_data['PAOwnerDriver_Premium']);
                        }
                        if (!empty($proposal_data['Vehicle_Base_ZD_Premium'])) {
                            $Nil_dep = round($proposal_data['Vehicle_Base_ZD_Premium']);
                        }

                        if (!empty($proposal_data['GeogExtension_ODPremium'])) {
                            $GeogExtension_od = round($proposal_data['GeogExtension_ODPremium']);
                        }
                        if (!empty($proposal_data['GeogExtension_TPPremium'])) {
                            $GeogExtension_tp = round($proposal_data['GeogExtension_TPPremium']);
                        }

                        if (!empty($proposal_data['LimitedtoOwnPremises_OD_Premium'])) {
                            $OwnPremises_OD = round($proposal_data['LimitedtoOwnPremises_OD_Premium']);
                        }
                        if (!empty($proposal_data['LimitedtoOwnPremises_TP_Premium'])) {
                            $OwnPremises_TP = round($proposal_data['LimitedtoOwnPremises_TP_Premium']);
                        }

                        if (!empty($proposal_data['EA_premium'])) {
                            $roadside_asst = round($proposal_data['EA_premium']);
                        }
                        if (!empty($proposal_data['Loss_of_Use_Premium'])) {
                            $loss_of_personal_belongings = round($proposal_data['Loss_of_Use_Premium']);
                        }
                        if (!empty($proposal_data['Vehicle_Base_NCB_Premium'])) {
                            $ncb_protection = round($proposal_data['Vehicle_Base_NCB_Premium']);
                        }
                        if (!empty($proposal_data['NCBBonusDisc_Premium'])) {
                            $ncb_discount = round($proposal_data['NCBBonusDisc_Premium']);
                        }
                        if (!empty($proposal_data['Vehicle_Base_ENG_Premium'])) {
                            $eng_protector = round($proposal_data['Vehicle_Base_ENG_Premium']);
                        }
                        if (!empty($proposal_data['Vehicle_Base_COC_Premium'])) {
                            $consumables_cover = round($proposal_data['Vehicle_Base_COC_Premium']);
                        }
                        if (!empty($proposal_data['Vehicle_Base_RTI_Premium'])) {
                            $rti = round($proposal_data['Vehicle_Base_RTI_Premium']);
                        }
                        if (!empty($proposal_data['EAW_premium'])) {
                            $key_replacement = round($proposal_data['EAW_premium']);
                        }
                        if (!empty($proposal_data['UnnamedPerson_premium'])) {
                            $pa_unnamed = round($proposal_data['UnnamedPerson_premium']);
                        }
                        if (!empty($proposal_data['Electical_Acc_Premium'])) {
                            $electrical_accessories = round($proposal_data['Electical_Acc_Premium']);
                        }
                        if (!empty($proposal_data['NonElectical_Acc_Premium'])) {
                            $non_electrical_accessories = round($proposal_data['NonElectical_Acc_Premium']);
                        }
                        if (!empty($proposal_data['BiFuel_Kit_OD_Premium'])) {
                            $lpg_cng = round($proposal_data['BiFuel_Kit_OD_Premium']);
                        }
                        if (!empty($proposal_data['BiFuel_Kit_TP_Premium'])) {
                            $lpg_cng_tp = round($proposal_data['BiFuel_Kit_TP_Premium']);
                        }
                        if (!empty($proposal_data['PAPaidDriver_Premium'])) {
                            $pa_paid_driver = round($proposal_data['PAPaidDriver_Premium']);
                        }
                        if (!empty($proposal_data['PaidDriver_Premium'])) {
                            $ll_paid_driver = round($proposal_data['PaidDriver_Premium']);
                        }
                        if (!empty($proposal_data['VoluntartDisc_premium'])) {
                            $voluntary_excess = round($proposal_data['VoluntartDisc_premium']);
                        }
                        if (!empty($proposal_data['Vehicle_Base_TySec_Premium'])) {
                            $tyre_secure = round($proposal_data['Vehicle_Base_TySec_Premium']);
                        }
                        if (!empty($proposal_data['AntiTheftDisc_Premium'])) {
                            $anti_theft = round($proposal_data['AntiTheftDisc_Premium']);
                        }
                        if (!empty($proposal_data['Net_Premium'])) {
                            $final_net_premium = round($proposal_data['Net_Premium']);
                        }
                        if (!empty($proposal_data['Total_Premium'])) {
                            $final_payable_amount = round($proposal_data['Total_Premium']);
                        }
                        if (!empty($proposal_data['Basic_OD_Premium'])) {
                            $basic_od_premium = round($proposal_data['Basic_OD_Premium']);
                        }
                        if (!empty($proposal_data['Basic_TP_Premium'])) {
                            $basic_tp_premium = round($proposal_data['Basic_TP_Premium']);
                        }
                        if (!empty($proposal_data['TPPD_premium'])) {
                            $tppd_value = round($proposal_data['TPPD_premium']);
                        }
                        if (!empty($proposal_data['InBuilt_BiFuel_Kit_Premium'])) {
                            $lpg_cng_tp = round($proposal_data['InBuilt_BiFuel_Kit_Premium']);
                        }
                        if ($electrical_accessories > 0) {
                            $Nil_dep += (int)$proposal_data['Elec_ZD_Premium'];
                            $engine_protection += (int)$proposal_data['Elec_ENG_Premium'];
                            $ncb_protection += (int)$proposal_data['Elec_NCB_Premium'];
                            $consumables_cover += (int)$proposal_data['Elec_COC_Premium'];
                            $rti += (int)$proposal_data['Elec_RTI_Premium'];
                        }
                        if ($non_electrical_accessories > 0) {
                            $Nil_dep += (int)$proposal_data['NonElec_ZD_Premium'];
                            $engine_protection += (int)$proposal_data['NonElec_ENG_Premium'];
                            $ncb_protection += (int)$proposal_data['NonElec_NCB_Premium'];
                            $consumables_cover += (int)$proposal_data['NonElec_COC_Premium'];
                            $rti += (int)$proposal_data['NonElec_RTI_Premium'];
                        }

                        if ($lpg_cng > 0) {
                            $Nil_dep += (int)$proposal_data['Bifuel_ZD_Premium'];
                            $engine_protection += (int)$proposal_data['Bifuel_ENG_Premium'];
                            $ncb_protection += (int)$proposal_data['Bifuel_NCB_Premium'];
                            $consumables_cover += (int)$proposal_data['Bifuel_COC_Premium'];
                            $rti += (int)$proposal_data['Bifuel_RTI_Premium'];
                        }
                        $od_discount = $ncb_discount + $voluntary_excess;
                        $od_premium = $basic_od_premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD - $od_discount;
                        $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti;
                        $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp) - $tppd_value + $GeogExtension_tp + $OwnPremises_TP;

                    if($is_breakin){
                        CvBreakinStatus::updateOrInsert(
                            [
                                'ic_id'             => $productData->company_id,
                                'breakin_number'    => $breaki_id,
                                'breakin_id'        => $breaki_id,
                                'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                //                                'breakin_response'  => $lead_create_response,
                                'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                                'created_at'        => Carbon::today()->toDateString()
                            ],
                            [
                                'user_proposal_id'  => $proposal->user_proposal_id
                            ]
                        );
                        updateJourneyStage([
                            'user_product_journey_id'   => $proposal->user_product_journey_id,
                            'ic_id'                     => $productData->company_id,
                            'stage'                     => STAGE_NAMES['INSPECTION_PENDING'],
                            'proposal_id'               => $proposal->user_proposal_id
                        ]);
                    }

                    UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                            'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                            'proposal_no' => $proposal->proposal_no,
                            'unique_proposal_id' => $proposal->proposal_no,
                            'product_code'      => $ProductCode,
                            'od_premium' => $od_premium,
                            'business_type' => $BusinessType,
                            'tp_premium' => $tp_premium,
                            'addon_premium' => $addon_premium,
                            'cpa_premium' => $pa_owner_driver,
                            'applicable_ncb' => $requestData->applicable_ncb,
                            'final_premium' => round($final_net_premium),
                            'total_premium' => round($final_net_premium),
                            'service_tax_amount' => round($proposal_data['Service_Tax']),
                            'final_payable_amount' => round($final_payable_amount),
                            'customer_id' => '',
                            'ic_vehicle_details' => json_encode($vehicleDetails),
                            'ncb_discount' => $ncb_discount,
                            'total_discount' => ($ncb_discount + $Basic_OD_Discount + $electrical_Discount + $non_electrical_Discount + $lpg_od_premium_Discount + $tppd_value),
                            'cpa_ins_comp' => $cPAInsComp,
                            'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                            'cpa_policy_no' => $cPAPolicyNo,
                            'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                            'cpa_sum_insured' => $cPASumInsured,
                            'electrical_accessories' => $ElectricalaccessSI,
                            'non_electrical_accessories' => $NonElectricalaccessSI,
                            'additional_details_data' => json_encode($proposal_array),
                            'is_breakin_case' => ($is_breakin) ? 'Y' : 'N',
                            'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                            'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
                        ]);


                    $data['user_product_journey_id'] = $enquiryId;
                    $data['ic_id'] = $master_policy->insurance_company_id;
                    $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                    $data['proposal_id'] = $proposal->user_proposal_id;
                    updateJourneyStage($data);


                    return response()->json([
                        'status' => true,
                        'msg' => $arr_premium['Error'],
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'data' => [
                            'proposalId' => $proposal->user_proposal_id,
                            'userProductJourneyId' => $data['user_product_journey_id'],
                            'proposalNo' => $proposal->proposal_no,
                            'finalPayableAmount' => $final_payable_amount,
                            'isBreakinCase' => ($is_breakin) ? 'Y' : 'N',
                            'is_breakin'    =>($is_breakin) ? 'Y' : 'N',
                            'inspection_number' =>(($is_breakin)?$breaki_id:'')
                        ]
                    ]);
                    }else{
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => "Proposal Service Issue",
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => "Premium Service Issue",
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => "Token Service Issue",
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Premium Service Issue ' . $e->getMessage(),

            ]);
        }
    }

    public static function submitV1NewFlow($proposal, $request)
    {
        // try {
            $enquiryId = customDecrypt($request['enquiryId']);
            $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $requestData = getQuotation($enquiryId);
            $productData = getProductDataByIc($request['policyId']);
            $quote_data = json_decode($quote_log->quote_data, true);
            $master_policy = MasterPolicy::find($request['policyId']);
            $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
                $policy_start_date = date('d/m/Y');
            } elseif ($requestData->business_type == 'rollover') {
                $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }
            
            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code') ->first();
            $is_breakin     = ($premium_type != 'third_party_breakin' &&  $requestData->business_type == 'breakin') ? true : false;
            if($premium_type == 'third_party_breakin')
            {
                $policy_start_date =  today()->addDay(1)->format('d/m/Y');
            }
            // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y')) {
            //     return [
            //         'premium_amount' => 0,
            //         'status' => false,
            //         'message' => 'Zero dep is not allowed because zero dep is not part of your previous policy',
            //     ];
            // }
            $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
            $mmv_data = (object)array_change_key_case((array)$mmv, CASE_LOWER);
            $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
            $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code
            $rto_location = HdfcErgoRtoLocation::where('rto_code', $rto_code)->first();
            $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;

            $date1 = new \DateTime($vehicleDate);
            $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
            $requestData->previous_policy_expiry_date=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $car_age = ceil($age / 12);
            
            $premium_type_data = $premium_type; 
            if($premium_type =='third_party_breakin'){
                $premium_type ='third_party';
            }
            if($premium_type =='own_damage_breakin'){
                $premium_type ='own_damage';
            }
            switch ($premium_type) {

                case 'third_party_breakin':
                    $premium_type = 'third_party';
                    break;
                case 'own_damage_breakin':
                    $premium_type = 'own_damage';
                    break;

            }

            switch ($requestData->business_type) {

                case 'rollover':
                    $business_type = 'Roll Over';
                    break;

                case 'newbusiness':
                    $business_type = 'New Business';
                    break;

                default:
                    $business_type = $requestData->business_type;
                    break;

            }

            $ProductCode = '2311';

            if ($premium_type == "third_party") {
                $ProductCode = '2319';

            }
            $vehicale_registration_number = $proposal->vehicale_registration_number == 'NEW' ? 'NEW' :getRegisterNumberWithHyphen($proposal->vehicale_registration_number);
            $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $ElectricalaccessSI = $RSACover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
            $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers); // new business
            $PreviousNilDepreciation = '0'; // addon
            $tppd_cover = 0;
            $voluntary_deductible =0;
            $isBatteryProtect = 0;
                if (isset($requestData->voluntary_excess_value)) {
                    if($requestData->voluntary_excess_value==20000 || $requestData->voluntary_excess_value==25000){
                        $voluntary_deductible = $requestData->voluntary_excess_value;
                    }
                }
            if (!empty($discounts)) {
                foreach ($discounts as $key => $roww) {
                    if ($roww['name'] == 'TPPD Cover') {
                        $tppd_cover = 1;
                    }
                }
            }
            $additional_details = json_decode($proposal->additional_details);
            if ($requestData->business_type == 'newbusiness')
            {
                $proposal->previous_policy_number = '';
                $proposal->previous_insurance_company = '';
                $PreviousPolicyFromDt = '';
                $PreviousPolicyToDt = '';
                $proposal_date = $policy_start_date = date('d/m/Y');
                $policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
                if ($premium_type == "third_party") 
                {
                    $policy_end_date = today()->addYear(3)->subDay(1)->format('d/m/Y');
                }
                $PolicyProductType = '3TP1OD';
                $previous_ncb = "";
                $db_policy_enddate = today()->addYear(3)->subDay(1)->format('d/m/Y');
                $BusinessType = 'New Vehicle';
            } 
            else 
            {
                if ($is_breakin) {
                    $policy_start_date = date('Y-m-d');
                }
                $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
                $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
                $PolicyProductType = '1TP1OD';
                $previous_ncb = $requestData->previous_ncb;
                $BusinessType = 'Roll Over';
                $proposal_date = date('d/m/Y');
                $prev_policy_details = $additional_details->prepolicy ?? '';
                $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
            }
            
            if ($requestData->previous_policy_type == 'Not sure' || Carbon::parse($requestData->previous_policy_expiry_date)->lt(Carbon::now()->subDays(90))) {
                $BusinessType = 'Used Car';
            } 
            $consumable = $engine_protection = $ncb_protction = $nilDepreciationCover = $RSACover = $PAforUnnamedPassenger = $PAforUnnamedPassenger =
            $KeyReplacementYN = $InvReturnYN = $engine_protection = $LossOfPersonBelongYN = $LLtoPaidDriverYN = $PAPaidDriverConductorCleanerSI = $tyresecure = $LossOfPersonalBelonging_SI = 0;
            $LLtoPaidDriverYN = $geoExtension='0';
            foreach ($additional_covers as $key => $value) {
                if (in_array('LL paid driver', $value)) {
                    $LLtoPaidDriverYN = '1';
                }

                if (in_array('PA cover for additional paid driver', $value)) {
                    $PAPaidDriverConductorCleaner = 1;
                    $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
                }

                if (in_array('Unnamed Passenger PA Cover', $value)) {
                    $PAforUnnamedPassenger = $mmv_data->seating_capacity;
                    $PAforUnnamedPassengerSI = $value['sumInsured'];
                }
            }

            foreach ($accessories as $key => $value) {
                if (in_array('geoExtension', $value)) {
                    $geoExtension ='1';
                }
                if (in_array('Electrical Accessories', $value)) {
                    $Electricalaccess = 1;
                    $ElectricalaccessSI = $value['sumInsured'];
                }

                if (in_array('Non-Electrical Accessories', $value)) {
                    $NonElectricalaccess = 1;
                    $NonElectricalaccessSI = $value['sumInsured'];
                }

                if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                    $externalCNGKIT = 'LPG';
                    $externalCNGKITSI = $value['sumInsured'];
                }
            }
            $is_cpa = "true";
            $CPA_Tenure = '1';
            if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
                $is_cpa = "false";
                $CPA_Tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure'])? (string)$selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
            }else{
                $is_cpa = "true";
                $CPA_Tenure = '1';//isset($selected_addons->compulsory_personal_accident[0]['tenure'])? (string)$selected_addons->compulsory_personal_accident[0]['tenure'] : '0';
            }
            if ($premium_type == 'own_damage') {
                $is_cpa = "true";
                $CPA_Tenure = '1';//isset($selected_addons->compulsory_personal_accident[0]['tenure'])? (string)$selected_addons->compulsory_personal_accident[0]['tenure'] : '0';
            }

            foreach ($addons as $key => $value) {
                if (in_array('Zero Depreciation', $value)) {
                    $nilDepreciationCover = '1';
                }
                if (in_array('Road Side Assistance', $value)) {
                    $RSACover = '1';
                }
                if (in_array('Key Replacement', $value)) {
                    $KeyReplacementYN = '1';
                }
                if (in_array('Return To Invoice', $value)) {
                    $InvReturnYN = '1';
                }
                if (in_array('NCB Protection', $value)) {
                    $ncb_protction = '1';
                }
                if (in_array('Engine Protector', $value)) {
                    $engine_protection = '1';
                }
                if (in_array('Consumable', $value)) {
                    $consumable = '1';
                }
                if (in_array('Loss of Personal Belongings', $value)) {
                    $LossOfPersonBelongYN = '1';
                    $LossOfPersonalBelonging_SI = 30000;
                }
                if (in_array('Tyre Secure', $value) && $car_age <= 3) {
                    $tyresecure = '1';
                }

                if (in_array('Battery Protect', $value) && $requestData->fuel_type == 'ELECTRIC') {
                    $isBatteryProtect = 1;
                }
            }
            if ($requestData->vehicle_owner_type == "I") {
                if (strtoupper($proposal->gender) == "MALE" || strtoupper($proposal->gender) == "M") {
                    $salutation = 'MR';
                } else {
                    if ((strtoupper($proposal->gender) == "FEMALE" || strtoupper($proposal->gender) == "F") && $proposal->marital_status == "Single") {
                        $salutation = 'MS';
                    } else {
                        $salutation = 'MRS';
                    }
                }
            } else {
                $salutation = 'M/S';
            }

            if(/*$car_age >= 5 && $car_age <= 10 && */$requestData->applicable_ncb != 0 && $productData->zero_dep == '0' && !in_array($premium_type,['third_party','third_party_breakin'])){
                switch ($productData->product_identifier) {
                    case 'Essential ZD':
                            $nilDepreciationCover = '1';
                            $LossOfPersonBelongYN = '1';
                            $KeyReplacementYN = '1';
                            $RSACover = '1';
                        break;
                    case 'Essential EGP':
                        $nilDepreciationCover = '1';
                        $LossOfPersonBelongYN = '1';
                        $KeyReplacementYN = '1';
                        $RSACover = '1';
                        $engine_protection = '1';
                        break;
                }
            }
            // salutaion
            // CPA
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos = false;
            $posp_email = '';
            $posp_name = '';
            $posp_unique_number = '';
            $posp_pan_number = '';
            $posp_aadhar_number = '';
            $posp_contact_number = '';
            $pos_data=CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('user_proposal_id', $proposal['user_proposal_id'])
            ->where('seller_type', 'P')
            ->first();

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
                if (config('HDFC_CAR_V1_IS_NON_POS') != 'Y') {
                    $hdfc_pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                        ->pluck('hdfc_ergo_code')
                        ->first();
                    if ((empty($hdfc_pos_code) || is_null($hdfc_pos_code))) {
                        return [
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => 'HDFC POS Code Not Available'
                        ];
                    }
                    $is_pos = true;
                    $pos_code = $hdfc_pos_code;
                }
            }elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' && $quote->idv <= 5000000){
                $is_pos = true;
                $pos_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
            }

            $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cpareason = '';
            if (!empty($prev_policy_details)) {
                $cpareason = isset($prev_policy_details->reason) ? $prev_policy_details->reason : '';
                $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp : '';
                $cPAPolicyNo = isset($prev_policy_details->cpaPolicyNumber) ? $prev_policy_details->cpaPolicyNumber : '';
                $cPASumInsured = isset($prev_policy_details->cpaSumInsured) ? $prev_policy_details->cpaSumInsured : '';
                $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y') : '';
                $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y') : '';
            }
            // CPA
            //$vehicleDetails = $additional_details->vehicle;
            if ($vehicale_registration_number[0] == 'NEW') {
                $vehicale_registration_number[0] = 'NEW';
            }

            if (isBhSeries($proposal->vehicale_registration_number)) 
            {
                $vehicale_registration_number = getRegisterNumberWithHyphen($proposal->vehicale_registration_number);
            }
            //checking last addons
            $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsRTI_Cover = false;
            if(!empty($proposal->previous_policy_addons_list))
            {
                $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
                foreach ($previous_policy_addons_list as $key => $value) {
                   if($key == 'zeroDepreciation' && $value)
                   {
                        $PreviousPolicy_IsZeroDept_Cover = true;  
                   }
                   else if($key == 'returnToInvoice' && $value)
                   {
                        $PreviousPolicy_IsRTI_Cover = true;
                   }
                }                
            }
            
            if($nilDepreciationCover && !$PreviousPolicy_IsZeroDept_Cover)
            {
               $is_breakin = true;
            }
            if($InvReturnYN && !$PreviousPolicy_IsRTI_Cover)
            {
               $is_breakin = true;
            }
            if($requestData->business_type == 'newbusiness')
            {
                $is_breakin = false;
            }

            $previousPolicyStartDate = $proposal->prev_policy_expiry_date == 'New' ? '' : Carbon::createFromDate($proposal->prev_policy_expiry_date)->subYear(1)->addDay(1);

            // token Generation
            $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
            // $transactionid = customEncrypt($enquiryId);
            $additionData = [
                'type' => 'gettoken',
                'method' => 'tokenGeneration',
                'section' => 'car',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name. " ($business_type)",
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode,// config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                'TRANSACTIONID' => $transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
            ];

            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.TOKEN_LINK_URL_HDFC_ERGO_GIC_MOTOR'), '', 'hdfc_ergo', $additionData);

            $token = $get_response['response'];
            $token_data = json_decode($token, TRUE);
            $PreviousPolicy_CorporateCustomerId_Mandatary = DB::table('previous_insurer_lists AS a')
            ->where('a.name', $proposal->insurance_company_name)
            ->where('a.company_alias', 'hdfc_ergo')
            ->pluck('code')
            ->first();
            
            if (isset($token_data['Authentication']['Token'])) {
                $proposal_array = [
                    'TransactionID' => $transactionid,//config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR') ,//$enquiryId,
                    'Policy_Details' => [
                        'PolicyStartDate' => $policy_start_date,
                        'ProposalDate' => date('d/m/Y'),
                        'BusinessType_Mandatary' => $BusinessType,
                        'VehicleModelCode' => $mmv_data->vehicle_model_code,
                        'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                        'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'YearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                        'RTOLocationCode' => $rto_location->rto_location_code,
                        'Vehicle_IDV' => $quote_log->idv,
                        'PreviousPolicy_CorporateCustomerId_Mandatary' => $PreviousPolicy_CorporateCustomerId_Mandatary ?? "",//$prev_policy_company,
                        'PreviousPolicy_NCBPercentage' => $previous_ncb,
                        'PreviousPolicy_PolicyEndDate' => date('d/m/Y', strtotime($proposal->prev_policy_expiry_date)),
                        'PreviousPolicy_PolicyStartDate' => date('d/m/Y', strtotime($previousPolicyStartDate)),
                        'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                        'PreviousPolicy_PolicyNo' => $proposal->previous_policy_number,
                        'PreviousPolicy_PreviousPolicyType' => (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage' ) ? 'Comprehensive Package' : 'TP'),
                        'Registration_No' => $requestData->business_type == 'newbusiness' ? '' : $proposal->vehicale_registration_number,
                        'EngineNumber' => $proposal->engine_number,
                        "Registration_No" => strtoupper($vehicale_registration_number),
                        'ChassisNumber' => $proposal->chassis_number,
                        'CUSTOMER_STATE_CD' => 0,
                        'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                        'FinancierCode' => ($proposal->is_vehicle_finance == '1') ? $proposal->name_of_financer : '',
                        'BranchName' => ($proposal->is_vehicle_finance == '1') ? $proposal->hypothecation_city : '',
                        "PreviousPolicy_IsZeroDept_Cover" => $PreviousPolicy_IsZeroDept_Cover,
                        "PreviousPolicy_IsRTI_Cover" => $PreviousPolicy_IsRTI_Cover,
                        'TXT_GIR_NO' => null,
                        'TSECode' => null,
                        'AVCode' => null,
                        'SMCode' => null,
                        'BankLocation' => null,
                        'ChannelName' => null,
                        'BANK_BRANCH_ID' => null,
                        'AV_SP_Code' => null,
                        'PB_Code' => null,
                        'Lead_ID' => null,
                        'AutoRenewal' => null,
                        'Type_of_payment' => null,
                        'FamilyType' => null,
                        'TypeofPlan' => null,
                        'PolicyTenure' => 0, 
                    ],
                    'Req_PvtCar' => [
                        'IsLimitedtoOwnPremises'=>'0',
                        'ExtensionCountryCode' => $geoExtension,
                        'ExtensionCountryName' => '',
                        'BiFuelType' => ($externalCNGKITSI>0 ? "CNG":""),
                        'POSP_CODE' => [],
                        'BiFuel_Kit_Value' => $externalCNGKITSI,
                        'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : ($premium_type == "third_party" ? "" : 'OD Plus TP'),
                        //'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : 'OD Plus TP', // as per the IC in case of tp only value for POLICY_TYPE will be null
                        'LLPaiddriver' => $LLtoPaidDriverYN,
                        //                    "BreakIN_ID" =>(($is_breakin)?'47894':''),
                        //                    "EMIAmount" => "0",
                        'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
                        'IsZeroDept_Cover' => $nilDepreciationCover,
                        'IsNCBProtection_Cover' => (int)$ncb_protction,
                        'IsRTI_Cover' => (int)$InvReturnYN,
                        'IsCOC_Cover' => (int)$consumable,
                        'IsEngGearBox_Cover' => (int)$engine_protection,
                        'IsEA_Cover' => (int)$RSACover,
                        'IsEAW_Cover' => (int)$KeyReplacementYN,
                        'IsTyreSecure_Cover' => (int)$tyresecure,
                        'isBatteryChargerAccessoryCover' => $isBatteryProtect,
                        'NoofUnnamedPerson' => (int)$PAforUnnamedPassenger,
                        'IsLossOfPersonalBelongings_Cover' => (int)$LossOfPersonBelongYN,
                        'LossOfPersonalBelonging_SI' => $LossOfPersonalBelonging_SI,
                        // 'IsLossofUseDownTimeProt_Cover' =>(int)$LossOfPersonBelongYN,
                        'UnnamedPersonSI' => (int)$PAforUnnamedPassengerSI,
                        'ElecticalAccessoryIDV' => (int)$ElectricalaccessSI,
                        'NonElecticalAccessoryIDV' => (int)$NonElectricalaccessSI,
                        'CPA_Tenure' => $CPA_Tenure,#($requestData->business_type == 'newbusiness' ? '3' : '1'),
                        'Effectivedrivinglicense' =>$is_cpa,
                        // 'Voluntary_Excess_Discount' => $voluntary_deductible,
                        'POLICY_TENURE' => (($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') ? '3' : '1'),
                        // 'TPPDLimit' => $tppd_cover, as per #23856
                        "Owner_Driver_Nominee_Name" =>($proposal->owner_type == 'I') ? $proposal->nominee_name : "",
                        "Owner_Driver_Nominee_Age" =>($proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                        "Owner_Driver_Nominee_Relationship" =>(!$premium_type == 'own_damage' || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                        //                    "Owner_Driver_Appointee_Name" => ($proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                        //                    "Owner_Driver_Appointee_Relationship" => ($proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                        'BreakinWaiver' => false,
                        'BreakinInspectionDate' => null,
                        'NumberOfEmployees' => ($requestData->vehicle_owner_type == 'C' ? $mmv_data->seating_capacity  : 0),
                        'OtherLoadDiscRate' => 0.0,
                        'IsEAAdvance_Cover' => 0,
                        'IsTowing_Cover' => 0,
                        'Towing_Limit' => null,
                        'IsEMIProtector_Cover' => 0,
                        'NoOfEmi' => null,
                        'NoofnamedPerson' => 0,
                        'AutoMobile_Assoication_No' => null,
                        // 'PayAsYouDrive' => false,
                        'InitialOdometerReadingDate' => '23/08/2022'

                    ],
                ];
                if ($premium_type == 'own_damage') {
                    $proposal_array['Policy_Details']['PreviousPolicy_TPENDDATE'] = $proposal->tp_end_date;#$proposal->tp_start_date
                    $proposal_array['Policy_Details']['PreviousPolicy_TPSTARTDATE'] = $proposal->tp_start_date;
                    $proposal_array['Policy_Details']['PreviousPolicy_TPINSURER'] = $proposal->tp_insurance_company;
                    $proposal_array['Policy_Details']['PreviousPolicy_TPPOLICYNO'] = $proposal->tp_insurance_number;
                }
                if(!$is_pos)
                {
                    unset($proposal_array['Req_PvtCar']['POSP_CODE']);
                }
                if($is_pos)
                {
                    $proposal_array['Req_PvtCar']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
                }
                if(/*$car_age >= 5 && $car_age <= 10 &&*/ $requestData->applicable_ncb != 0 && $productData->zero_dep == '0' && !in_array($premium_type,['third_party','third_party_breakin'])){
                    switch($productData->product_identifier){
                        case 'Essential ZD':
                            $proposal_array['Req_PvtCar']['planType'] = 'Essential ZD plan';
                            $proposal_array['Req_PvtCar']['IsZeroDept_Cover'] = $nilDepreciationCover;
                            $proposal_array['Req_PvtCar']['IsEA_Cover'] = $RSACover;
                            $proposal_array['Req_PvtCar']['IsEAW_Cover'] = $KeyReplacementYN;
                            $proposal_array['Req_PvtCar']['IsLossOfPersonalBelongings_Cover'] = $LossOfPersonBelongYN;
                            break;
                        case 'Essential EGP':
                            $proposal_array['Req_PvtCar']['planType'] = 'Essential EGP plan';
                            $proposal_array['Req_PvtCar']['IsZeroDept_Cover'] = $nilDepreciationCover;
                            $proposal_array['Req_PvtCar']['IsEA_Cover'] = $RSACover;
                            $proposal_array['Req_PvtCar']['IsEAW_Cover'] = $KeyReplacementYN;
                            $proposal_array['Req_PvtCar']['IsLossOfPersonalBelongings_Cover'] = $LossOfPersonBelongYN;
                            $proposal_array['Req_PvtCar']['IsEngGearBox_Cover'] = $engine_protection;
                            break;
                    }
                }
                $additionData = [
                    'type' => 'PremiumCalculation',
                    'method' => 'Premium Calculation',//'Proposal Submit',
                    'requestMethod' => 'post',
                    'section' => 'car',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name. " ($business_type)",
                    'TOKEN' => $token_data['Authentication']['Token'],
                    'transaction_type' => 'proposal',
                    'PRODUCT_CODE' => $ProductCode, //config('constants.IcConstants.hdfc_ergo.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                    'SOURCE' => config('constants.IcConstants.hdfc_ergo.SOURCE_HDFC_ERGO_GIC_MOTOR'),
                    'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.CHANNEL_ID_HDFC_ERGO_GIC_MOTOR'),
                    'TRANSACTIONID' => $transactionid,// config('constants.IcConstants.hdfc_ergo.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                    'CREDENTIAL' => config('constants.IcConstants.hdfc_ergo.CREDENTIAL_HDFC_ERGO_GIC_MOTOR'),
                ];

                if($requestData->previous_policy_type != 'Not sure' && $requestData->business_type != 'newbusiness' &&  empty($proposal->previous_policy_number) && empty($proposal->previous_insurance_company))
                {
                    return
                    [
                        'status' => false,
                        'message' => 'Previous policy number and previous insurer is mandetory if previous policy type is ' . $requestData->previous_policy_type
                    ];
                }
                if($requestData->previous_policy_type == 'Not sure')
                {
                    unset($proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_PolicyNo']);
                    unset($proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType']);
                    
                    
                    
                   //$proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = NULL;
                  // $proposal_array['Policy_Details']['PreviousPolicy_PolicyClaim'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_PolicyNo'] = NULL;
                   //$proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType'] = NULL;
                }
                //            print_r(json_encode($additionData));die;
                //        print_r([config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_PREMIUM'),json_encode($additionData)]);
                //        print_r(json_encode($proposal_array));
                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_GIC_MOTOR_PREMIUM'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];
                $arr_premium = json_decode($getpremium, TRUE);
                        //    print_r(json_encode($arr_premium));die;
                $vehicleDetails = [
                    'manufacture_name' => $mmv_data->vehicle_manufacturer,
                    'model_name' => $mmv_data->vehicle_model_name,
                    'version' => $mmv_data->variant,
                    'fuel_type' => $mmv_data->fuel,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'carrying_capacity' => $mmv_data->carrying_capacity,
                    'cubic_capacity' => $mmv_data->cubic_capacity,
                    'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
                    'vehicle_type' => $mmv_data->veh_ob_type ?? '',
                ];
                if (isset($arr_premium['StatusCode']) && $arr_premium['StatusCode'] == '200') {
                    $premium_data = $arr_premium['Resp_PvtCar'];
                    $proposal->proposal_no =  $is_breakin ? null : $arr_premium['TransactionID'];
                    $proposal->ic_vehicle_details = $vehicleDetails;
                    $proposal->save();

                if (($premium_type == 'breakin' || $premium_type_data== 'own_damage_breakin') && ($premium_type != 'third_party')) {

                    $is_breakin = true;
                    $city_location_master = DB::table('hdfc_ergo_city_location_master')->where('city', $proposal->city)->first();
                    //by confirm with tejas(karo pm) on 23-10-2024 they said use fiest one so we using that
                    $breakin_request_array =  [
                        "RFirstName" => ($proposal->owner_type == 'I') ? $proposal->first_name : $proposal->last_name,
                        "RLastName" => ($proposal->owner_type == 'I') ? (!empty($proposal->last_name) ? $proposal->last_name : '.') : '',
                        "InsuredMobile" => $proposal->mobile_number,
                        "Address1" => removeSpecialCharactersFromString($proposal->address_line1, true),
                        "CityId" => $city_location_master->city_id,
                        "locationId" => $city_location_master->location_id,
                        "Pincode" => $proposal->pincode,
                        "ProductTypeId" => 2, //ProductTypeId = 2 forPrivate Car given by ic #29688
                        "Reg" => $proposal->vehicale_registration_number,
                        "Engine" => $proposal->engine_number,
                        "Chassis" => $proposal->chassis_number,
                        "Make" => $mmv_data->manufacturer_code,
                        "ModelId" => $mmv_data->vehicle_model_code,

                        "Year" => date('Y', strtotime($requestData->vehicle_register_date)),
                        "RTOCity" =>  $rto_location->rto_location_description,
                        "RTOLocationId" => $rto_location->rto_location_code,
                        "InitiationReason" => "BRK",
                        "CustomerLocation" => $city_location_master->location,
                        "City" => $city_location_master->city,

                        "Model" => $mmv_data->vehicle_model_name,
                        "VehicleVariant" => $mmv_data->fyntune_version['version_name'],
                        "VehicleType" => "Four Wheeler",
                        "State" => $proposal->state,
                        "RTOLocation" => $rto_location->rto_location_description,

                        "RTOCityID" => $rto_location->rto_location_code,
                        "RTOState" => $rto_location->rto_location_description,
                        "CPSelfInspection" => "N", // for breakin those value will be N
                        "SourceCode" => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_BREAKIN_SOURCE_CODE'),
                        "AllianceFlag" => "N",
                        "VALUATION_REPORT" => "N",
                        "AISTATUS" => "Y",
                        "Photos_Flag_CP" => "Y",
                        "PAYD" => "N"
                    ];

                    $additionData['method'] = 'Break-in Creation';


                    $breakin_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_BREAKIN_CREATION'), $breakin_request_array, 'hdfc_ergo', $additionData);
                    $breakin_data = $breakin_response['response'];

                    $breakin_id_response = json_decode($breakin_data);
                    if (isset($breakin_id_response)) {

                        preg_match('/Breakin ID is (\d+)/', $breakin_id_response, $matches);

                        if (isset($matches[1])) {
                            $breakin_id = $matches[1];

                            if (!empty($breakin_id)) {
                                DB::table('cv_breakin_status')->insert([
                                    'user_proposal_id' => $proposal->user_proposal_id,
                                    'ic_id' => $productData->company_id,
                                    'breakin_number' => $breakin_id,
                                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                                //update user proposal
                                //             DB::table('user_proposal')
                                //                 ->where('user_proposal_id', $proposal->user_proposal_id)
                                //                 ->update([
                                //                     'is_breakin_case' => 'Y',
                                //                     // 'unique_quote' => $quote_response['Data']['quote_id'],
                                //                     'final_payable_amount' => $arr_premium['Resp_PvtCar']['Total_Premium'],
                                //                     'additional_details_data' => $proposal_array
                                //                 ]);

                                //             updateJourneyStage([
                                //                 'user_product_journey_id' => $enquiryId,
                                //                 'ic_id' => $productData->company_id,
                                //                 'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                //                 'proposal_id' => $proposal->user_proposal_id,
                                //             ]);

                                //             //return proposal submitted succesfully with breakin id and is_breakin = Y
                                //             return [
                                //                 'status' => true,
                                //                 'message' => STAGE_NAMES['INSPECTION_PENDING'],
                                //                 'webservice_id' => $get_response['webservice_id'],
                                //                 'table' => $get_response['table'],
                                //                 'data' => [
                                //                     'proposalId' => $proposal->user_proposal_id,
                                //                     'userProductJourneyId' => $proposal->user_product_journey_id,
                                //                     // 'proposalNo' => $quote_response['Data']['quote_id'],
                                //                     'finalPayableAmount' => $arr_premium['Resp_PvtCar']['Total_Premium'],
                                //                     'is_breakin' => 'Y',
                                //                     'inspection_number' => $breakin_id
                                //                 ]
                                //             ];
                                //         } else {
                                //             return [
                                //                 'status' => false,
                                //                 'webservice_id' => $get_response['webservice_id'],
                                //                 'table' => $get_response['table'],
                                //                 'message' => 'Error in Break-in Service'
                                //             ];
                                //         }
                                //     } else {
                                //         return response()->json([
                                //             'status' => false,
                                //             'msg' => "breking id not found",
                                //             'webservice_id' => $get_response['webservice_id'],
                                //             'table' => $get_response['table'],
                                //         ]);
                                //     }
                                // } else {
                                //     return response()->json([
                                //         'status' => false,
                                //         'msg' => "getting breakin service id failed",
                                //         'webservice_id' => $get_response['webservice_id'],
                                //         'table' => $get_response['table']
                                //     ]);
                                // }


                                // $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti;
                                // //        print_r([$premium_data['Elec_ZD_Premium'], $premium_data['NonElec_ZD_Premium'], $premium_data['Bifuel_ZD_Premium']]);
                                //                 $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $legal_liability_to_employee + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp)-$tppd_value+$GeogExtension_tp+$OwnPremises_TP;
                                // //print_r([$basic_tp_premium,$pa_owner_driver,$ll_paid_driver,$pa_paid_driver,$pa_unnamed,$lpg_cng_tp,$tppd_value]);
                                // //die;
                                $proposal_array['Customer_Details'] = [
                                    'GC_CustomerID' => null,
                                    'IsCustomer_modify' => null,
                                    'Customer_Type' => ($proposal->owner_type == 'I') ? 'Individual' : 'Corporate',
                                    'Company_Name' => ($proposal->owner_type == 'I') ? '' : $proposal->first_name,
                                    'Customer_FirstName' => ($proposal->owner_type == 'I') ? $proposal->first_name : $proposal->last_name,
                                    'Customer_MiddleName' => '',
                                    'Customer_LastName' => ($proposal->owner_type == 'I') ? (!empty($proposal->last_name) ? $proposal->last_name : '.') : '',
                                    'Customer_DateofBirth' => date('d/m/Y', strtotime($proposal->dob)),
                                    'Customer_Email' => $proposal->email,
                                    'Customer_Mobile' => $proposal->mobile_number,
                                    'Customer_Telephone' => '',
                                    'Customer_PanNo' => $proposal->pan_number,
                                    'Customer_Salutation' => $salutation, #($proposal->owner_type == 'I') ? 'MR' : 'M/S',
                                    'Customer_Gender' => $proposal->gender,
                                    'Customer_Perm_Address1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                                    'Customer_Perm_Address2' => $proposal->address_line2,
                                    'Customer_Perm_Apartment' => '',
                                    'Customer_Perm_Street' => '',
                                    'Customer_Perm_PinCode' => $proposal->pincode,
                                    'Customer_Perm_PinCodeLocality' => '',
                                    'Customer_Perm_CityDirstCode' => $proposal->city_id,
                                    'Customer_Perm_CityDistrictCode' => null,
                                    'Customer_Perm_CityDistrict' => $proposal->city,
                                    'Customer_Perm_StateCode' => $proposal->state_id,
                                    'Customer_Perm_State' => $proposal->state,
                                    'Customer_Mailing_Address1' => $proposal->is_car_registration_address_same == 1 ? removeSpecialCharactersFromString($proposal->address_line1, true) : $proposal->car_registration_address1,
                                    'Customer_Mailing_Address2' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                                    'Customer_Mailing_Apartment' => null,
                                    'Customer_Mailing_Street' => null,
                                    'Customer_Mailing_CityDistrictCode' => null,
                                    'Customer_Mailing_PinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                                    'Customer_Mailing_PinCodeLocality' => '',
                                    'Customer_Mailing_CityDirstCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->city_id : $proposal->car_registration_city_id,
                                    'Customer_Mailing_CityDistrict' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
                                    'Customer_Mailing_StateCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->state_id : $proposal->car_registration_state_id,
                                    'Customer_Mailing_State' => $proposal->is_car_registration_address_same == 1 ? $proposal->state : $proposal->car_registration_state,
                                    'Customer_GSTIN_Number' => $proposal->gst_number,
                                    'Customer_Professtion' => null,
                                    'Customer_GSTIN_State' => null,
                                    'Customer_MaritalStatus' => null,
                                    'Customer_EIA_Number' => null,
                                    'Customer_IDProof' => null,
                                    'Customer_Nationality' => null,
                                    'Customer_UniqueRefNo' => null,
                                    'Customer_GSTDetails' => null
                                ];
                                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                                    $proposal_array['Customer_Details']['Customer_Pehchaan_id'] = $proposal->ckyc_reference_id;
                                }
                                    $proposal_array['Req_PvtCar']['BreakIN_ID'] = $breakin_id;
                                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = date('d/m/Y', strtotime($proposal->prev_policy_expiry_date));
                            
                                $proposal_array['Type_of_payment'] = null;
                                $proposal_array['PaymentStatusDetails'] = null;

                                // $od_premium = $basic_od_premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;
                                // $final_total_discount = $ncb_discount;
                                // $total_od_amount = $od_premium - $final_total_discount;
                                DB::table('user_proposal')
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                    'is_breakin_case' => 'Y',
                                    'product_code'      => $ProductCode,
                                    // 'unique_quote' => $quote_response['Data']['quote_id'],
                                    'final_payable_amount' => $arr_premium['Resp_PvtCar']['Total_Premium'],
                                    'additional_details_data' => $proposal_array
                                    ]);

                                updateJourneyStage([
                                    'user_product_journey_id' => $enquiryId,
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                    'proposal_id' => $proposal->user_proposal_id,
                                ]);

                                //return proposal submitted succesfully with breakin id and is_breakin = Y
                                return [
                                    'status' => true,
                                    'message' => STAGE_NAMES['INSPECTION_PENDING'],
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'data' => [
                                        'proposalId' => $proposal->user_proposal_id,
                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                        // 'proposalNo' => $quote_response['Data']['quote_id'],
                                        'finalPayableAmount' => $arr_premium['Resp_PvtCar']['Total_Premium'],
                                        'is_breakin' => 'Y',
                                        'inspection_number' => $breakin_id
                                    ]
                                ];
                            } else {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => 'Error in Break-in Service'
                                ];
                            }
                        } else {
                            return response()->json([
                                'status' => false,
                                'msg' => "breking id not found",
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                            ]);
                        }
                    } else {
                        return response()->json([
                            'status' => false,
                            'msg' => "getting breakin service id failed",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table']
                        ]);
                    }
                }

                    $proposal_array['Customer_Details'] = [
                        'GC_CustomerID' => null,
                        'IsCustomer_modify' => null,
                        'Customer_Type' => ($proposal->owner_type == 'I') ? 'Individual' : 'Corporate',
                        'Company_Name' => ($proposal->owner_type == 'I') ? '' : $proposal->first_name,
                        'Customer_FirstName' => ($proposal->owner_type == 'I') ? $proposal->first_name : $proposal->last_name,
                        'Customer_MiddleName' => '',
                        'Customer_LastName' => ($proposal->owner_type == 'I') ? (!empty($proposal->last_name) ? $proposal->last_name : '.') : '',
                        'Customer_DateofBirth' => date('d/m/Y', strtotime($proposal->dob)),
                        'Customer_Email' => $proposal->email,
                        'Customer_Mobile' => $proposal->mobile_number,
                        'Customer_Telephone' => '',
                        'Customer_PanNo' => $proposal->pan_number,
                        'Customer_Salutation' => $salutation, #($proposal->owner_type == 'I') ? 'MR' : 'M/S',
                        'Customer_Gender' => $proposal->gender,
                        'Customer_Perm_Address1' => removeSpecialCharactersFromString($proposal->address_line1, true),
                        'Customer_Perm_Address2' => $proposal->address_line2,
                        'Customer_Perm_Apartment' => '',
                        'Customer_Perm_Street' => '',
                        'Customer_Perm_PinCode' => $proposal->pincode,
                        'Customer_Perm_PinCodeLocality' => '',
                        'Customer_Perm_CityDirstCode' => $proposal->city_id,
                        'Customer_Perm_CityDistrictCode' => null,
                        'Customer_Perm_CityDistrict' => $proposal->city,
                        'Customer_Perm_StateCode' => $proposal->state_id,
                        'Customer_Perm_State' => $proposal->state,
                        'Customer_Mailing_Address1' => $proposal->is_car_registration_address_same == 1 ? removeSpecialCharactersFromString($proposal->address_line1, true) : $proposal->car_registration_address1,
                        'Customer_Mailing_Address2' => $proposal->is_car_registration_address_same == 1 ? $proposal->address_line2 : $proposal->car_registration_address2,
                        'Customer_Mailing_Apartment' => null,
                        'Customer_Mailing_Street' => null,
                        'Customer_Mailing_CityDistrictCode' => null,
                        'Customer_Mailing_PinCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->pincode : $proposal->car_registration_pincode,
                        'Customer_Mailing_PinCodeLocality' => '',
                        'Customer_Mailing_CityDirstCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->city_id : $proposal->car_registration_city_id,
                        'Customer_Mailing_CityDistrict' => $proposal->is_car_registration_address_same == 1 ? $proposal->city : $proposal->car_registration_city,
                        'Customer_Mailing_StateCode' => $proposal->is_car_registration_address_same == 1 ? $proposal->state_id : $proposal->car_registration_state_id,
                        'Customer_Mailing_State' => $proposal->is_car_registration_address_same == 1 ? $proposal->state : $proposal->car_registration_state,
                        'Customer_GSTIN_Number' => $proposal->gst_number,
                        'Customer_Professtion' => null,
                        'Customer_GSTIN_State' => null,
                        'Customer_MaritalStatus' => null,
                        'Customer_EIA_Number' => null,
                        'Customer_IDProof' => null,
                        'Customer_Nationality' => null,
                        'Customer_UniqueRefNo' => null,
                        'Customer_GSTDetails' => null
                    ];
                    if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                        $proposal_array['Customer_Details']['Customer_Pehchaan_id'] = $proposal->ckyc_reference_id;
                    }


                    $proposal_array['Type_of_payment'] = null;
                    $proposal_array['PaymentStatusDetails'] = null;
                    $additionData['method'] = 'Proposal Submit';

                    //$additionData['PRODUCT_CODE'] = '2311';
                    //$proposal_url = 'https://integrations.hdfcergo.com/HEI.IntegrationService/Integration/CreateProposal';
                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_PROPOSAL'), $proposal_array, 'hdfc_ergo', $additionData);
                    $proposal_submit_response = $get_response['response'];
                    $proposal_submit_response = json_decode($proposal_submit_response,true);

                    if ((isset($proposal_submit_response['StatusCode']) && $proposal_submit_response['StatusCode'] == '200')) {
                        $proposal_data = $proposal_submit_response['Resp_PvtCar'];
                        $proposal->proposal_no = $proposal_submit_response['TransactionID'];
                        $proposal->ic_vehicle_details = $vehicleDetails;
                        $proposal->save();
                        $Nil_dep = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $age_discount = $lpg_cng_tp = $lpg_cng = $Bonus_Discount = $automobile_discount = $antitheft
                            = $basic_tp_premium = $electrical_accessories = $tppd_value = 
                            $non_electrical_accessories = $ncb_protction = $ll_paid_driver =
                            $ncb_protection = $consumables_cover = $Nil_dep = $roadside_asst =
                            $key_replacement = $loss_of_personal_belongings = $eng_protector =
                            $rti = $incon_allow = $Basic_OD_Discount = $electrical_Discount =
                            $non_electrical_Discount = $lpg_od_premium_Discount = $tyre_secure = $GeogExtension_od= $GeogExtension_tp=$OwnPremises_OD= $OwnPremises_TP = $basic_od_premium = $legal_liability_to_employee =0;
                        $batteryProtect = 0;

                        if (!empty($proposal_data['PAOwnerDriver_Premium'])) {
                            $pa_owner_driver = $proposal_data['PAOwnerDriver_Premium'];
                        }
                        if (!empty($proposal_data['Vehicle_Base_ZD_Premium'])) {
                            $Nil_dep = $proposal_data['Vehicle_Base_ZD_Premium'];
                        }
    
                        if (!empty($proposal_data['GeogExtension_ODPremium'])) {
                            $GeogExtension_od = $proposal_data['GeogExtension_ODPremium'];
                        }
                        if (!empty($proposal_data['GeogExtension_TPPremium'])) {
                            $GeogExtension_tp= $proposal_data['GeogExtension_TPPremium'];
                        }
    
                        if (!empty($proposal_data['LimitedtoOwnPremises_OD_Premium'])) {
                            $OwnPremises_OD = $proposal_data['LimitedtoOwnPremises_OD_Premium'];
                        }
                        if (!empty($proposal_data['LimitedtoOwnPremises_TP_Premium'])) {
                            $OwnPremises_TP = $proposal_data['LimitedtoOwnPremises_TP_Premium'];
                        }
    
                        if (!empty($proposal_data['EA_premium'])) {
                            $roadside_asst = $proposal_data['EA_premium'];
                        }
                        if (!empty($proposal_data['LossOfPersonalBelongings_Premium'])) {
                            $loss_of_personal_belongings = $proposal_data['LossOfPersonalBelongings_Premium'];
                        }
                        if (!empty($proposal_data['Vehicle_Base_NCB_Premium'])) {
                            $ncb_protection = $proposal_data['Vehicle_Base_NCB_Premium'];
                        }
                        if (!empty($proposal_data['NCBBonusDisc_Premium'])) {
                            $ncb_discount = $proposal_data['NCBBonusDisc_Premium'];
                        }
                        if (!empty($proposal_data['Vehicle_Base_ENG_Premium'])) {
                            $eng_protector = $proposal_data['Vehicle_Base_ENG_Premium'];
                        }
                        if (!empty($proposal_data['Vehicle_Base_COC_Premium'])) {
                            $consumables_cover = $proposal_data['Vehicle_Base_COC_Premium'];
                        }
                        if (!empty($proposal_data['Vehicle_Base_RTI_Premium'])) {
                            $rti = $proposal_data['Vehicle_Base_RTI_Premium'];
                        }
                        if (!empty($proposal_data['EAW_premium'])) {
                            $key_replacement = $proposal_data['EAW_premium'];
                        }
                        if (!empty($proposal_data['UnnamedPerson_premium'])) {
                            $pa_unnamed = $proposal_data['UnnamedPerson_premium'];
                        }
                        if (!empty($proposal_data['Electical_Acc_Premium'])) {
                            $electrical_accessories = $proposal_data['Electical_Acc_Premium'];
                        }
                        if (!empty($proposal_data['NonElectical_Acc_Premium'])) {
                            $non_electrical_accessories = $proposal_data['NonElectical_Acc_Premium'];
                        }
                        if (!empty($proposal_data['BiFuel_Kit_OD_Premium'])) {
                            $lpg_cng = $proposal_data['BiFuel_Kit_OD_Premium'];
                        }
                        if (!empty($proposal_data['BiFuel_Kit_TP_Premium'])) {
                            $lpg_cng_tp = $proposal_data['BiFuel_Kit_TP_Premium'];
                        }
                        if (!empty($proposal_data['PAPaidDriver_Premium'])) {
                            $pa_paid_driver = $proposal_data['PAPaidDriver_Premium'];
                        }
                        if (!empty($proposal_data['PaidDriver_Premium'])) {
                            $ll_paid_driver = $proposal_data['PaidDriver_Premium'];
                        }
                        if(!empty($proposal_data['NumberOfEmployees_Premium'])) {
                            $legal_liability_to_employee = $proposal_data['NumberOfEmployees_Premium'];
                        }
                        if (!empty($proposal_data['VoluntartDisc_premium'])) {
                            $voluntary_excess = $proposal_data['VoluntartDisc_premium'];
                        }
                        if (!empty($proposal_data['Vehicle_Base_TySec_Premium'])) {
                            $tyre_secure = $proposal_data['Vehicle_Base_TySec_Premium'];
                        }
                        if (!empty($proposal_data['AntiTheftDisc_Premium'])) {
                            $anti_theft = $proposal_data['AntiTheftDisc_Premium'];
                        }
                        if (!empty($proposal_data['Net_Premium'])) {
                            $final_net_premium = $proposal_data['Net_Premium'];
                        }
                        if (!empty($proposal_data['Total_Premium'])) {
                            $final_payable_amount = $proposal_data['Total_Premium'];
                        }
                        if (!empty($proposal_data['Basic_OD_Premium'])) {
                            $basic_od_premium = $proposal_data['Basic_OD_Premium'];
                        }
                        if (!empty($proposal_data['Basic_TP_Premium'])) {
                            $basic_tp_premium = $proposal_data['Basic_TP_Premium'];
                        }
                        if (!empty($proposal_data['TPPD_premium'])) {
                            $tppd_value = $proposal_data['TPPD_premium'];
                        }
                        if (!empty($proposal_data['InBuilt_BiFuel_Kit_Premium'])) {
                            $lpg_cng_tp = $proposal_data['InBuilt_BiFuel_Kit_Premium'];
                        }
                        if (!empty($proposal_data['BatteryChargerAccessory_Premium'])) {
                            $batteryProtect = $proposal_data['BatteryChargerAccessory_Premium'];
                        }
                        if ($electrical_accessories > 0) {
                            $Nil_dep += (int)$proposal_data['Elec_ZD_Premium'];
                            $engine_protection += (int)$proposal_data['Elec_ENG_Premium'];
                            $ncb_protection += (int)$proposal_data['Elec_NCB_Premium'];
                            $consumables_cover += (int)$proposal_data['Elec_COC_Premium'];
                            $rti += (int)$proposal_data['Elec_RTI_Premium'];
                        }
                        if ($non_electrical_accessories > 0) {
                            $Nil_dep += (int)$proposal_data['NonElec_ZD_Premium'];
                            $engine_protection += (int)$proposal_data['NonElec_ENG_Premium'];
                            $ncb_protection += (int)$proposal_data['NonElec_NCB_Premium'];
                            $consumables_cover += (int)$proposal_data['NonElec_COC_Premium'];
                            $rti += (int)$proposal_data['NonElec_RTI_Premium'];
                        }
    
                        if ($lpg_cng > 0) {
                            $Nil_dep += (int)$proposal_data['Bifuel_ZD_Premium'];
                            $engine_protection += (int)$proposal_data['Bifuel_ENG_Premium'];
                            $ncb_protection += (int)$proposal_data['Bifuel_NCB_Premium'];
                            $consumables_cover += (int)$proposal_data['Bifuel_COC_Premium'];
                            $rti += (int)$proposal_data['Bifuel_RTI_Premium'];
                        }
                        
                        HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

                        $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti + $batteryProtect;
                        //        print_r([$proposal_data['Elec_ZD_Premium'], $proposal_data['NonElec_ZD_Premium'], $proposal_data['Bifuel_ZD_Premium']]);
                                        $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $legal_liability_to_employee + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp)-$tppd_value+$GeogExtension_tp+$OwnPremises_TP;
                        //print_r([$basic_tp_premium,$pa_owner_driver,$ll_paid_driver,$pa_paid_driver,$pa_unnamed,$lpg_cng_tp,$tppd_value]);

                        $od_premium = $basic_od_premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;
                        $final_total_discount = $ncb_discount;
                        $total_od_amount = $od_premium - $final_total_discount;

                        if($proposal_submit_response['Policy_Details']['ProposalNumber'] == null){
                            return response()->json([
                                'status' => false,
                                'msg' => "The proposal number cannot have a null value",
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                            ]);
                        }
                        //GET CIS DOCUMENT API
                        if(config('IC.HDFC_ERGO.CIS_DOCUMENT_ENABLE') == 'Y'){
                            if(!empty($proposal_submit_response['Policy_Details']['ProposalNumber'])){
                                $get_cis_document_array = [
                                    'TransactionID' => $transactionid,
                                    'Req_Policy_Document' => [
                                        'Proposal_Number' => $proposal_submit_response['Policy_Details']['ProposalNumber'] ?? null,
                                    ],
                                ];
    
                                $additionData['method'] = 'Get CIS Document';
                                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_CIS_DOCUMENT'), $get_cis_document_array, 'hdfc_ergo', $additionData);
                                $cis_doc_resp = json_decode($get_response['response']);
                                $pdfData = base64_decode($cis_doc_resp->Resp_Policy_Document->PDF_BYTES);
                                if (checkValidPDFData($pdfData)) {
                                    Storage::put(config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) .'_cis' .'.pdf', $pdfData);
    
                                    $pdf_url = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf';
                                    ProposalExtraFields::updateOrCreate(
                                        ['enquiry_id' => $enquiryId], 
                                        ['cis_url' => $pdf_url]      
                                    );
                                
                                }else{
                                    return response()->json([
                                        'status' => false,
                                        'webservice_id' => $get_response['webservice_id'],
                                        'table' => $get_response['table'],
                                        'msg'    => $cis_doc_resp->Error ?? 'CIS Document service Issue'
                                    ]);
                                }
                            }
                        }
                        
                        // if($is_breakin){
                        //     CvBreakinStatus::updateOrInsert(
                        //         [
                        //             'ic_id'             => $productData->company_id,
                        //             'breakin_number'    => $breaki_id,
                        //             'breakin_id'        => $breaki_id,
                        //             'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                        //             'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                        //             //                                'breakin_response'  => $lead_create_response,
                        //             'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                        //             'created_at'        => Carbon::today()->toDateString()
                        //         ],
                        //         [
                        //             'user_proposal_id'  => $proposal->user_proposal_id
                        //         ]
                        //     );
                        //     updateJourneyStage([
                        //         'user_product_journey_id'   => $proposal->user_product_journey_id,
                        //         'ic_id'                     => $productData->company_id,
                        //         'stage'                     => STAGE_NAMES['INSPECTION_PENDING'],
                        //         'proposal_id'               => $proposal->user_proposal_id
                        //     ]);
                        // }
    
                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                                'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                                'proposal_no' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                                'unique_proposal_id' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                                'product_code'      => $ProductCode,
                                'od_premium' => $total_od_amount,
                                'business_type' => $BusinessType,
                                'tp_premium' => $tp_premium,
                                'addon_premium' => $addon_premium,
                                'cpa_premium' => $pa_owner_driver,
                                'applicable_ncb' => $requestData->applicable_ncb,
                                'final_premium' => $final_net_premium,
                                'total_premium' => $final_net_premium,
                                'service_tax_amount' => $proposal_data['Service_Tax'],
                                'final_payable_amount' => $final_payable_amount,
                                'customer_id' => '',
                                'ic_vehicle_details' => json_encode($vehicleDetails),
                                'ncb_discount' => $ncb_discount,
                                'total_discount' => ($ncb_discount + $Basic_OD_Discount + $electrical_Discount + $non_electrical_Discount + $lpg_od_premium_Discount + $tppd_value),
                                'cpa_ins_comp' => $cPAInsComp,
                                'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                                'cpa_policy_no' => $cPAPolicyNo,
                                'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                                'cpa_sum_insured' => $cPASumInsured,
                                'electrical_accessories' => $ElectricalaccessSI,
                                'non_electrical_accessories' => $NonElectricalaccessSI,
                                'additional_details_data' => json_encode($proposal_array),
                                'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                                'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
                                // 'is_breakin_case' => ($is_breakin) ? 'Y' : 'N',
                            ]);
    
    
                        $data['user_product_journey_id'] = $enquiryId;
                        $data['ic_id'] = $master_policy->insurance_company_id;
                        $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                        $data['proposal_id'] = $proposal->user_proposal_id;
                        updateJourneyStage($data);
    
    
                        return response()->json([
                            'status' => true,
                            'msg' => $arr_premium['Error'],
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $data['user_product_journey_id'],
                                'proposalNo' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                                'finalPayableAmount' => $final_payable_amount,
                                'isBreakinCase' => ($is_breakin) ? 'Y' : 'N',
                                'is_breakin'    =>($is_breakin) ? 'Y' : 'N',
                                // 'inspection_number' =>(($is_breakin)?$breakin_id:'')
                            ]
                        ]);
                    } else {
                       return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg'    => $proposal_submit_response['Error'] ?? 'Insurer Not Found'
                        ]); 
                    }

                } else {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => "Premium Service Issue",
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => "Token Service Issue",
                ]);
            }
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'webservice_id' => $get_response['webservice_id'],
        //         'table' => $get_response['table'],
        //         'message' => 'Premium Service Issue ' . $e->getMessage(),

        //     ]);
        // }
    }

    public static function submitV2($proposal, $request)
    {
        if (config('IC.HDFC_ERGO.V2.CAR.ENABLE') == 'Y'){
            return  hdfcErgoSubmitProposalv2::submitV2($proposal, $request);
            } 
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv')
            ->first();

        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');

        if ($mmv['status'] == 1)
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

        $mmv_data = (object)array_change_key_case((array)$mmv, CASE_LOWER);

        // dd($mmv_data);
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '')
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle Not Mapped',
                ]
            ];
        }
        elseif ($mmv_data->ic_version_code == 'DNE')
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ]
            ];
        }

        $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code, true);
        $rto_data = HdfcErgoRtoLocation::where('rto_code', $rto_code)->first();
        // dd($rto_data);
        if ( ! $rto_data)
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO does not exists',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'RTO does not exists',
                ]
            ];
        }

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);
        $od_only = in_array($premium_type, ['own_damage', 'own_damage_breakin']);

        if ($requestData->business_type == 'rollover')
        {
            if(isset($requestData->ownership_changed) && $requestData->ownership_changed == 'Y')
            {
                $business_type = 'USED';
            }else
            {
                $business_type = 'ROLLOVER';
            }
            $policy_start_date = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        elseif ($requestData->business_type == 'newbusiness')
        {
            $business_type = 'NEW';
            $policy_start_date = $tp_only ? date('d-m-Y', strtotime('tomorrow')) : date('d-m-Y', time());
        }
        elseif ($requestData->business_type == 'breakin')
        {
            $business_type = 'ROLLOVER';

            if (($requestData->policy_type == 'own_damage' && $requestData->previous_policy_type == 'Third-party') || $requestData->previous_policy_type == 'Not sure')
            {
                $business_type = 'USED';
            }

            $policy_start_date = date('d-m-Y', strtotime($requestData->previous_policy_expiry_date));
        }

        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))));

        $car_age = 0;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);

        if ($requestData->policy_type == 'comprehensive')
        {
            $policy_type = 'Comprehensive';
        }
        elseif ($requestData->policy_type == 'own_damage')
        {
            $policy_type = 'ODOnly';
        }

        $electrical_accessories_sa = 0;
        $non_electrical_accessories_sa = 0;
        $lp_cng_kit_sa = 0;
        $is_lpg_cng_kit = 'NO';
        $motor_cnglpg_type = '';
        $pa_paid_driver = 'NO';
        $pa_paid_driver_sa = 0;
        $is_pa_unnamed_passenger = 'NO';
        $pa_unnamed_passenger_sa = 0;
        $is_ll_paid_driver = 'NO';
        $is_tppd_discount = 'NO';
        $electrical_accessories_sa = 0;
        $electrical_accessories_sa = 0;
        $is_zero_dep = 'NO';
        $is_roadside_assistance = 'NO';
        $is_key_replacement = 'NO';
        $is_engine_protector = 'NO';
        $is_ncb_protection = 'NO';
        $is_tyre_secure = 'NO';
        $is_consumable = 'NO';
        $is_return_to_invoice = 'NO';
        $is_loss_of_personal_belongings = 'NO';
        $is_cpa = false;

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)
            ->first();

        // dd($selected_addons);
        if ($selected_addons)
        {
            if ($selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '')
            {
                foreach ($selected_addons['accessories'] as $accessory)
                {
                    if ($accessory['name'] == 'Electrical Accessories')
                    {
                        $electrical_accessories_sa = $accessory['sumInsured'];
                    }
                    elseif ($accessory['name'] == 'Non-Electrical Accessories')
                    {
                        $non_electrical_accessories_sa = $accessory['sumInsured'];
                    }
                    elseif ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $is_lpg_cng_kit = 'YES';
                        $lp_cng_kit_sa = $accessory['sumInsured'];
                        $motor_cnglpg_type = 'CNG';
                    }
                }
            }

            if ($selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '')
            {
                foreach ($selected_addons['additional_covers'] as $additional_cover)
                {
                    if ($additional_cover['name'] == 'PA cover for additional paid driver')
                    {
                        $pa_paid_driver = 'YES';
                        $pa_paid_driver_sa = $additional_cover['sumInsured'];
                    }

                    if ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                    {
                        $is_pa_unnamed_passenger = 'YES';
                        $pa_unnamed_passenger_sa = $additional_cover['sumInsured'];
                    }

                    if ($additional_cover['name'] == 'LL paid driver')
                    {
                        $is_ll_paid_driver = 'YES';
                    }
                }
            }

            if ($selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '')
            {
                foreach ($selected_addons['discounts'] as $discount)
                {
                    if ($discount['name'] == 'TPPD Cover')
                    {
                        $is_tppd_discount = $tp_only ? 'YES' : 'NO';
                    }
                }
            }

            if ($selected_addons['applicable_addons'] != NULL && $selected_addons['applicable_addons'] != '')
            {
                foreach ($selected_addons['applicable_addons'] as $applicable_addon)
                {
                    if ($applicable_addon['name'] == 'Zero Depreciation')
                    {
                        $is_zero_dep = 'YES';
                    }

                    if ($applicable_addon['name'] == 'Road Side Assistance')
                    {
                        $is_roadside_assistance = 'YES';
                    }

                    if ($applicable_addon['name'] == 'Key Replacement')
                    {
                        $is_key_replacement = 'YES';
                    }

                    if ($applicable_addon['name'] == 'Engine Protector')
                    {
                        $is_engine_protector = 'YES';
                    }
                    
                    if ($applicable_addon['name'] == 'NCB Protection')
                    {
                        $is_ncb_protection = 'YES';
                    }
                    
                    if ($applicable_addon['name'] == 'Tyre Secure')
                    {
                        $is_tyre_secure = 'YES';
                    }
                    
                    if ($applicable_addon['name'] == 'Consumable')
                    {
                        $is_consumable = 'YES';
                    }

                    if ($applicable_addon['name'] == 'Return To Invoice')
                    {
                        $is_return_to_invoice = 'YES';
                    }

                    // if ($applicable_addon['name'] == 'Loss of Personal Belongings')
                    // {
                    //     $is_loss_of_personal_belongings = 'YES';
                    // }
                }
            }
            $cpa_tenure = 0;
            if ($selected_addons['compulsory_personal_accident'] != NULL && $selected_addons['compulsory_personal_accident'] != '')
            {
                foreach ($selected_addons['compulsory_personal_accident'] as $compulsory_personal_accident)
                {
                    if (isset($compulsory_personal_accident['name']) && $compulsory_personal_accident['name'] == 'Compulsory Personal Accident')
                    {
                        $is_cpa = true;
                        $cpa_tenure = isset($compulsory_personal_accident['tenure']) ? $compulsory_personal_accident['tenure'] : 1;
                    }                   
                }
            }
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        $posp_code = '';

        if ($quote_log_data->idv <= 5000000)
        {
            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
                if (config('HDFC_CAR_V2_IS_NON_POS') != 'Y')
                {
                    $posp_code = config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_TEST_POSP_CODE') : $pos_data->pan_no;
                }
            }
            elseif (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
            {
                $posp_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_TEST_POSP_CODE');
            }
        }
        $address_data = [
            'address' => removeSpecialCharactersFromString($proposal->address_line1, true),
            'address_1_limit'   => 50,
            'address_2_limit'   => 50,         
            'address_3_limit'   => 250,         
        ];
        $getAddress = getAddress($address_data);

        $add1 = $getAddress['address_1'];
        $add2 = $getAddress['address_2'];
        $add3 = $getAddress['address_3'];

        if (strlen($getAddress['address_1']) < 10) {
            $add1 = $getAddress['address_1'] ." ". $proposal->state ." ". $proposal->city." ". $proposal->pincode;
        }
        if (strlen($getAddress['address_2']) < 10) {
            $add2 = $getAddress['address_2'] ." ". $proposal->state ." ". $proposal->city ." ". $proposal->pincode;
        }
        if (strlen($getAddress['address_3']) < 10) {
            $add3 = $getAddress['address_3'] ." ". $proposal->state ." ". $proposal->city ." ". $proposal->pincode;
        }
        
        $premium_request = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE')
            ],
            'TypeOfBusiness' => $business_type,
            'VehicleMakeCode' => $mmv_data->manufacturer_code,
            'VehicleModelCode' => $mmv_data->vehicle_model_code,
            'RtoLocationCode' => $rto_data->rto_location_code,
            'Premium_Year' => 1,
            'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "INDIVIDUAL" : "CORPORATE",
            'PolicyType' => $tp_only ? 'ThirdParty' : $policy_type,
            'CustomerStateCode' => $rto_data->state_code,
            'PurchaseRegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
            'RequiredIDV' => $quote_log_data->idv,
            'IsPreviousClaim' => $requestData->is_claim == 'Y' || $requestData->previous_policy_type == 'Third-party' ? 'YES' : 'NO',
            'PreviousPolicyEndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
            'PreviousNCBDiscountPercentage' => $requestData->previous_policy_type == 'Third-party' ? 0 : $requestData->previous_ncb,
            'PreviousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? 'ThirdParty' : 'Comprehensive',
            'PreviousInsurerId' => $proposal->previous_insurance_company,
            'PospCode' => $posp_code,
            'RegistrationNo' => $requestData->business_type == 'newbusiness'? 'NEW' : $proposal->vehicale_registration_number,
            'CORDiscount' => 0,
            'AddOnCovers' => [
                'IsZeroDepTakenLastYear' => $productData->zero_dep == 0 ? 1 : 0,
                'IsZeroDepCover' => $is_zero_dep,
                'IsLossOfUse' => $is_loss_of_personal_belongings,
                'IsEmergencyAssistanceCover' => $is_roadside_assistance,
                'IsNoClaimBonusProtection' => $is_ncb_protection,
                'IsEngineAndGearboxProtectorCover' => $is_engine_protector,
                'IsCostOfConsumable' => $is_consumable,
                'IsReturntoInvoice' => $is_return_to_invoice,
                "ReturntoInvoicePlanType"=>$is_return_to_invoice = 'YES' ? "A_PURCHASE_INVOICE_VALUE" : "",
                'IsEmergencyAssistanceWiderCover' => $is_key_replacement,
                'IsTyreSecureCover' => $is_tyre_secure,
                'NonelectricalAccessoriesIdv' => $non_electrical_accessories_sa,
                'ElectricalAccessoriesIdv' => $electrical_accessories_sa,
                'LpgCngKitIdv' => $lp_cng_kit_sa,
                'SelectedFuelType' => $motor_cnglpg_type,
                'IsPAPaidDriver' => $pa_paid_driver,
                'PAPaidDriverSumInsured' => $pa_paid_driver_sa,
                'IsPAUnnamedPassenger' => $is_pa_unnamed_passenger,
                'PAUnnamedPassengerNo' => $is_pa_unnamed_passenger == 'YES' ? $mmv_data->carrying_capacity : 0,
                'PAPerUnnamedPassengerSumInsured' => $pa_unnamed_passenger_sa,
                'IsLegalLiabilityDriver' => $is_ll_paid_driver,
                'LLPaidDriverNo' => $is_ll_paid_driver == 'YES' ? 1 : 0,
                'IsTPPDDiscount' => $is_tppd_discount,
                'IsExLpgCngKit' => $is_lpg_cng_kit,
                'IsLLEmployee' => ($requestData->vehicle_owner_type == 'C' && !$od_only ? 'YES'  : 'NO'),
                'LLEmployeeNo' => ($requestData->vehicle_owner_type == 'C' && !$od_only ? $mmv_data->seating_capacity  : 0),
                'CpaYear' => $requestData->vehicle_owner_type == 'I' && $is_cpa ? $cpa_tenure : 0,#$requestData->vehicle_owner_type == 'I' && $is_cpa ? ($requestData->business_type == 'newbusiness' ? 3 : 1) : 0,
            ],
        ];

        if($od_only){
            unset($premium_request['AddOnCovers']['LLEmployeeNo']);
            unset($premium_request['AddOnCovers']['IsLLEmployee']);
        }

        if ($requestData->business_type == 'newbusiness')
        {
            unset($premium_request['IsPreviousClaim']);
            unset($premium_request['PreviousPolicyEndDate']);
            unset($premium_request['PreviousNCBDiscountPercentage']);
            unset($premium_request['PreviousPolicyType']);
            unset($premium_request['PreviousInsurerId']);
        }

        if ($requestData->previous_policy_type == 'Not sure')
        {
            unset($premium_request['PreviousPolicyEndDate']);
            unset($premium_request['PreviousNCBDiscountPercentage']);
            unset($premium_request['PreviousPolicyType']);
            unset($premium_request['PreviousInsurerId']);
        }

        if (in_array($premium_type, ['own_damage', 'own_damage_breakin']))
        {
            $premium_request['TPExistingEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
            unset($premium_request['AddOnCovers']['IsPAUnnamedPassenger']);
            unset($premium_request['AddOnCovers']['PAUnnamedPassengerNo']);
            unset($premium_request['AddOnCovers']['IsLegalLiabilityDriver']);
            unset($premium_request['AddOnCovers']['LLPaidDriverNo']);
            unset($premium_request['AddOnCovers']['CpaYear']);
        }
        if($tp_only)
        {
            unset($premium_request['PreviousNCBDiscountPercentage']);
        }

        $rno1 = explode('-', $proposal->vehicale_registration_number);
        if (strtolower($rno1[0]) == 'dl' && $rno1[1] < 10 && strlen($rno1[1]) < 2) 
        {
            $rno1[1] = '0' . $rno1[1];
            $rno1 = implode('-', $rno1);
            $proposal->vehicale_registration_number = $rno1;
        }
        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                'Content-Type' => 'application/json',
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                'Accept-Language' => 'en-US,en;q=0.5'
            ]
        ]);
        $premium_response = $get_response['response'];
        if ($premium_response)
        {
            $premium_response = json_decode($premium_response, TRUE);

            if (isset($premium_response['Status']) && $premium_response['Status'] == 200)
            {
                $proposal_request = [
                    'UniqueRequestID' => $premium_response['UniqueRequestID'],
                    'ProposalDetails' => [
                        'ConfigurationParam' => [
                            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_AGENT_CODE')
                        ],
                        'PremiumYear' => $requestData->business_type == 'newbusiness' && $tp_only ? 3 : 1,
                        'TypeOfBusiness' => $business_type,
                        'PolicyType' => $tp_only ? 'ThirdParty' : $policy_type,
                        'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "INDIVIDUAL" : "CORPORATE",
                        'VehicleMakeCode' => $mmv_data->manufacturer_code,
                        'VehicleModelCode' => $mmv_data->vehicle_model_code,
                        'RtoLocationCode' => $rto_data->rto_location_code,
                        'RequiredIDV' => $quote_log_data->idv,
                        'PurchaseRegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                        'YearofManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                        //'RegistrationNo' => (($requestData->business_type == 'newbusiness') && (strtolower($requestData->fuel_type) == 'electric')) ? 'ELC' : $proposal->vehicale_registration_number,
                        'RegistrationNo' => $requestData->business_type == 'newbusiness'? 'NEW' : $proposal->vehicale_registration_number,
                        'EngineNo' => $proposal->engine_number,
                        'ChassisNo' => $proposal->chassis_number,
                        'CustomerStateCode' => $rto_data->state_code,
                        'PreviousPolicyEndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
                        'IsPreviousClaim' => $requestData->is_claim == 'Y' || $requestData->previous_policy_type == 'Third-party' ? 'YES' : 'NO',
                        'PreviousNCBDiscountPercentage' => $requestData->previous_policy_type == 'Third-party' ? 0 : $requestData->previous_ncb,
                        'PreviousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? 'ThirdParty' : 'Comprehensive',
                        'PreviousPolicyNumber' => $proposal->previous_policy_number ?? '',
                        'PreviousInsurerCode' => $proposal->previous_insurance_company ?? '',
                        'CORDiscount' => 0,
                        'NetPremiumAmount' => (int) $premium_response['Data'][0]['NetPremiumAmount'],
                        'TaxAmount' => (int) $premium_response['Data'][0]['TaxAmount'],
                        'FinancierCode' => (int) $proposal->name_of_financer ?? 0,
                        'TotalPremiumAmount' => (int) $premium_response['Data'][0]['TotalPremiumAmount'],
                        'PAOwnerDriverNomineeName' => $proposal->nominee_name ?? '',
                        'PAOwnerDriverNomineeAge' => (int) $proposal->nominee_age ?? 0,
                        'PAOwnerDriverNomineeRelationship' => $proposal->nominee_relationship ?? '',
                        'AddOnCovers' => [
                            'IsZeroDepTakenLastYear' => $productData->zero_dep == 0 ? 1 : 0,
                            'IsZeroDepCover' => $is_zero_dep,
                            'IsLossOfUse' => $is_loss_of_personal_belongings,
                            'IsEmergencyAssistanceCover' => $is_roadside_assistance,
                            'IsNoClaimBonusProtection' => $is_ncb_protection,
                            'IsEngineAndGearboxProtectorCover' => $is_engine_protector,
                            'IsCostOfConsumable' => $is_consumable,
                            'IsReturntoInvoice' => $is_return_to_invoice,
                            "ReturntoInvoicePlanType"=>$is_return_to_invoice = 'YES' ? "A_PURCHASE_INVOICE_VALUE" : "",
                            'IsEmergencyAssistanceWiderCover' => $is_key_replacement, //Key Replacement
                            'IsTyreSecureCover' => $is_tyre_secure,
                            'NonelectricalAccessoriesIdv' => $non_electrical_accessories_sa,
                            'ElectricalAccessoriesIdv' => $electrical_accessories_sa,
                            'LpgCngKitIdv' => $lp_cng_kit_sa,
                            'SelectedFuelType' => $motor_cnglpg_type,
                            'IsPAPaidDriver' => $pa_paid_driver,
                            'PAPaidDriverSumInsured' => $pa_paid_driver_sa,
                            'IsPAUnnamedPassenger' => $is_pa_unnamed_passenger,
                            'PAUnnamedPassengerNo' => $is_pa_unnamed_passenger == 'YES' ? $mmv_data->carrying_capacity : 0,
                            'PAPerUnnamedPassengerSumInsured' => $pa_unnamed_passenger_sa,
                            'IsLegalLiabilityDriver' => $is_ll_paid_driver,
                            'LLPaidDriverNo' => $is_ll_paid_driver == 'YES' ? 1 : 0,
                            'IsTPPDDiscount' => $is_tppd_discount,
                            'IsExLpgCngKit' => $is_lpg_cng_kit,
                            'IsLLEmployee' => ($requestData->vehicle_owner_type == 'C' && !$od_only ? 'YES'  : 'NO'),
                            'LLEmployeeNo' => ($requestData->vehicle_owner_type == 'C' && !$od_only ? $mmv_data->seating_capacity  : 0),
                            'CpaYear' => $requestData->vehicle_owner_type == 'I' && $is_cpa ? $cpa_tenure : 0,#$requestData->vehicle_owner_type == 'I' && $is_cpa ? ($requestData->business_type == 'newbusiness' ? 3 : 1) : 0,
                        ]
                    ],
                    'CustomerDetails' => [
                        'Title' => $requestData->vehicle_owner_type == 'I' ? ($proposal->gender == 'MALE' ? 'Mr' : ($proposal->marital_status == 'Single' ? 'Ms' : 'Mrs')) : 'M/S',
                        'Gender' => $requestData->vehicle_owner_type == 'I' ? $proposal->gender : '',
                        'FirstName' => $requestData->vehicle_owner_type == 'I' ? preg_replace("/[^a-zA-Z0-9 ]+/", "", $proposal->first_name) : '',
                        'MiddleName' => '',
                        'LastName' => $requestData->vehicle_owner_type == 'I' ? ( ! empty($proposal->last_name) ? preg_replace("/[^a-zA-Z0-9 ]+/", "", $proposal->last_name) : '.') : '',
                        'DateOfBirth' => $requestData->vehicle_owner_type == 'I' ? date('Y-m-d', strtotime($proposal->dob)) : '',
                        'OrganizationName' => $requestData->vehicle_owner_type == 'C' ? $proposal->first_name : '',
                        'OrganizationContactPersonName' => $requestData->vehicle_owner_type == 'C' ? ($proposal->last_name ?? '') : '',
                        'EmailAddress' => $proposal->email,
                        'MobileNumber' => $proposal->mobile_number,
                        'GstInNo' => $proposal->gst_number ?? '',
                        'PanCard' => $proposal->pan_number ?? '',
                        'PospCode' => $posp_code,
                        'EiaNo' => 0,
                        'IsCustomerAuthenticated' => "YES",
                        'UidNo' => '',
                        'AuthentificationType' => "OTP",
                        'LGCode' => '',//"LGCODE123",
                        'CorrespondenceAddress1' => $add1,
                        'CorrespondenceAddress2' => $add2,
                        'CorrespondenceAddress3' => $add3,#$proposal->address_line3,
                        'CorrespondenceAddressCitycode' => $proposal->city_id,
                        'CorrespondenceAddressCityName' => $proposal->city,
                        'CorrespondenceAddressStateCode' => $proposal->state_id,
                        'CorrespondenceAddressStateName' => $proposal->state,
                        'CorrespondenceAddressPincode' => $proposal->pincode,
                        'IsGoGreen' => 0
                    ]                            
                ];

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $proposal_request['CustomerDetails']['KYCId'] = $proposal->ckyc_reference_id;
                }
                if($od_only){
                    unset($premium_request['ProposalDetails']['AddOnCovers']['LLEmployeeNo']);
                    unset($premium_request['ProposalDetails']['AddOnCovers']['IsLLEmployee']);
                }
                if ($requestData->business_type == 'newbusiness')
                {
                    unset($proposal_request['ProposalDetails']['IsPreviousClaim']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyEndDate']);
                    unset($proposal_request['ProposalDetails']['PreviousNCBDiscountPercentage']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyType']);
                    unset($proposal_request['ProposalDetails']['PreviousInsurerId']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyNumber']);
                    unset($proposal_request['ProposalDetails']['PreviousInsurerCode']);
                }

                if ($requestData->previous_policy_type == 'Not sure')
                {
                    unset($proposal_request['ProposalDetails']['PreviousPolicyEndDate']);
                    unset($proposal_request['ProposalDetails']['PreviousNCBDiscountPercentage']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyType']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyNumber']);
                    unset($proposal_request['ProposalDetails']['PreviousInsurerCode']);
                }

                if (in_array($premium_type, ['own_damage', 'own_damage_breakin']))
                {
                    $proposal_request['CustomerDetails']['TPExisitingInsurerCode'] = $proposal->tp_insurance_company;
                    $proposal_request['CustomerDetails']['TPExisitingPolicyNumber'] = $proposal->tp_insurance_number;
                    $proposal_request['CustomerDetails']['TPExistingStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                    $proposal_request['CustomerDetails']['TPExistingEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                    unset($proposal_request['ProposalDetails']['AddOnCovers']['IsPAUnnamedPassenger']);
                    unset($proposal_request['ProposalDetails']['AddOnCovers']['PAUnnamedPassengerNo']);
                    unset($proposal_request['ProposalDetails']['AddOnCovers']['IsLegalLiabilityDriver']);
                    unset($proposal_request['ProposalDetails']['AddOnCovers']['LLPaidDriverNo']);
                    unset($proposal_request['ProposalDetails']['AddOnCovers']['CpaYear']);
                }
                if ($tp_only)
                {
                    unset($proposal_request['ProposalDetails']['PreviousNCBDiscountPercentage']);
                }

                if ($requestData->business_type == 'breakin' && ! $tp_only)
                {
                    $proposal_request['CustomerDetails']['InspectionMethod'] = 'SELF'; // 'SURVEYOR';
                    /* $proposal_request['CustomerDetails']['InspectionStateCode'] = $address->state_id;
                    $proposal_request['CustomerDetails']['InspectionCityCode'] = $address->city_id;
                    $proposal_request['CustomerDetails']['InspectionLocationCode'] = '143'; */
                }

                HdfcErgoPremiumDetailController::saveV2PremiumDetails($get_response['webservice_id']);

                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_PROPOSAL_GENERATION_URL'), $proposal_request, 'hdfc_ergo', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Proposal Generation',
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_MERCHANT_KEY'),
                        'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MOTOR_SECRET_TOKEN'),
                        'Content-Type' => 'application/json',
                        'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                        'Accept-Language' => 'en-US,en;q=0.5'
                    ]
                ]);
                $proposal_response = $get_response['response'];

                if ($proposal_response)
                {
                    $proposal_response = json_decode($proposal_response, TRUE);

                    if (isset($proposal_response['Status']) && $proposal_response['Status'] == 200)
                    {
                        $basic_od = $premium_response['Data'][0]['BasicODPremium'] ?? 0;
                        $basic_tp = $premium_response['Data'][0]['BasicTPPremium'] ?? 0;
                        $electrical_accessories = 0;
                        $non_electrical_accessories = 0;
                        $lpg_cng_kit_od = 0;
                        $cpa = 0;
                        $unnamed_passenger = 0;
                        $ll_paid_driver = 0;
                        $pa_paid_driver = 0;
                        $zero_depreciation = 0;
                        $road_side_assistance = 0;
                        $ncb_protection = 0;
                        $engine_protection = 0;
                        $consumable = 0;
                        $key_replacement = 0;
                        $tyre_secure = 0;
                        $return_to_invoice = 0;
                        $loss_of_personal_belongings = 0;
                        $lpg_cng_kit_tp = $premium_response['Data'][0]['LpgCngKitTPPremium'] ?? 0;
                        $ncb_discount = $premium_response['Data'][0]['NewNcbDiscountAmount'] ?? 0;
                        $tppd_discount = $premium_response['Data'][0]['TppdDiscountAmount'] ?? 0;
                        
                        if(isset($premium_response['Data'][0]['BuiltInLpgCngKitPremium']) && $premium_response['Data'][0]['BuiltInLpgCngKitPremium'] != 0.0)
                        {
                            $lpg_cng_kit_tp = $premium_response['Data'][0]['BuiltInLpgCngKitPremium'] ?? 0;
                        }
                        if (isset($premium_response['Data'][0]['AddOnCovers']))
                        {
                            foreach ($premium_response['Data'][0]['AddOnCovers'] as $addon_cover)
                            {
                                switch($addon_cover['CoverName'])
                                {
                                    case 'ElectricalAccessoriesIdv':
                                        $electrical_accessories = $addon_cover['CoverPremium'];
                                        break;
                                
                                    case 'NonelectricalAccessoriesIdv':
                                        $non_electrical_accessories = $addon_cover['CoverPremium'];
                                        break;
                                        
                                    case 'LpgCngKitIdvOD':
                                        $lpg_cng_kit_od = $addon_cover['CoverPremium'];
                                        break;

                                    case 'LpgCngKitIdvTP':
                                        $lpg_cng_kit_tp = $addon_cover['CoverPremium'];
                                        break;

                                    case 'PACoverOwnerDriver':
                                        $cpa = $addon_cover['CoverPremium'];
                                        break;

                                    case 'PACoverOwnerDriver3Year':
                                        if ($requestData->business_type == 'newbusiness' && $requestData->policy_type == 'comprehensive')
                                        {
                                            $cpa = $addon_cover['CoverPremium'];
                                        }
                                        break;

                                    case 'UnnamedPassenger':
                                        $unnamed_passenger = $addon_cover['CoverPremium'];
                                        break;

                                    case 'LLPaidDriver':
                                        $ll_paid_driver = $addon_cover['CoverPremium'];
                                        break;

                                    case 'PAPaidDriver':
                                        $pa_paid_driver = $addon_cover['CoverPremium'];
                                        break;

                                    case 'ZERODEP':
                                        $zero_depreciation = $is_zero_dep == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'EMERGASSIST':
                                        // $road_side_assistance = $is_roadside_assistance == 'YES' ? $addon_cover['CoverPremium'] : 0;

                                        if (($requestData->business_type=="breakin" && $interval->y < 5) || ($requestData->business_type!="breakin" && $interval->y < 15) ) {
                                            $road_side_assistance=$addon_cover['CoverPremium'];
                                        }
                                        break;

                                    case 'NCBPROT':
                                        $ncb_protection = $is_ncb_protection == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'ENGEBOX':
                                        $engine_protection = $is_engine_protector == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'COSTCONS':
                                        $consumable = $is_consumable == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'EMERGASSISTWIDER':
                                        $key_replacement = $is_key_replacement == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'TYRESECURE':
                                        $tyre_secure = $is_tyre_secure == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'RTI':
                                        $return_to_invoice = $is_return_to_invoice == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;
                                        
                                    case 'LOPB':
                                    case 'LOSSUSEDOWN':
                                        $loss_of_personal_belongings = $is_loss_of_personal_belongings == 'YES' ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    default:
                                        break;
                                }
                            }
                        }

                        $final_total_discount = $ncb_discount;
                        $total_od_amount = $basic_od - $final_total_discount;
                        $final_total_discount = $final_total_discount + $tppd_discount;
                        $total_tp_amount = $basic_tp + $ll_paid_driver + $lpg_cng_kit_tp + $pa_paid_driver + $cpa + $unnamed_passenger - $tppd_discount;
                        $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od + $zero_depreciation + $road_side_assistance + $ncb_protection + $consumable + $key_replacement + $tyre_secure + $engine_protection + $return_to_invoice + $loss_of_personal_belongings;

                        $final_net_premium = (int) $premium_response['Data'][0]['NetPremiumAmount'];
                        $service_tax = (int) $premium_response['Data'][0]['TaxAmount'];
                        $final_payable_amount = (int) $premium_response['Data'][0]['TotalPremiumAmount'];

                        $is_breakin = 'N';
                        $inspection_id = NULL;

                        $ic_vehicle_details = [
                            'manufacture_name' => $mmv_data->vehicle_manufacturer,
                            'model_name' => $mmv_data->vehicle_model_name,
                            'version' => $mmv_data->variant,
                            'fuel_type' => $mmv_data->fuel,
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'carrying_capacity' => $mmv_data->carrying_capacity,
                            'cubic_capacity' => $mmv_data->cubic_capacity,
                            'gross_vehicle_weight' => $mmv_data->gross_vehicle_weight
                        ];

                        $premium_request['QuoteNo'] = $proposal_response['Data']['QuoteNo'];
                        $premium_request['UniqueRequestID'] = $premium_response['UniqueRequestID'];
                        $proposal_request['ProposalDetails']['QuoteNo'] = $proposal_response['Data']['QuoteNo'];

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'policy_start_date' => date('d-m-Y', strtotime($proposal_response['Data']['NewPolicyStartDate'])),
                                'policy_end_date' => date('d-m-Y', strtotime($proposal_response['Data']['NewPolicyEndDate'])),
                                'proposal_no' => (int) $proposal_response['Data']['TransactionNo'],
                                'customer_id' => (int) $proposal_response['Data']['QuoteNo'],
                                'unique_proposal_id' => $premium_response['UniqueRequestID'],
                                'od_premium' => $total_od_amount,
                                'tp_premium' => round($total_tp_amount),
                                'ncb_discount' => round($ncb_discount), 
                                'addon_premium' => round($total_addon_amount),
                                'cpa_premium' => round($cpa),
                                'total_discount' => round($final_total_discount),
                                'total_premium' => round($final_net_premium),
                                'service_tax_amount' => round($service_tax),
                                'final_payable_amount' => round($final_payable_amount),
                                'ic_vehicle_details' => json_encode($ic_vehicle_details),
                                'additional_details_data' => $proposal_response['Data']['IsBreakin'] == 1 ? json_encode([
                                    'premium_request' => $premium_request,
                                    'proposal_request' => $proposal_request
                                ]) : NULL
                            ]);

                        if ($proposal_response['Data']['IsBreakin'] == 1)
                        {
                            $is_breakin = 'Y';
                            $inspection_id = $proposal_response['Data']['BreakinId'];

                            CvBreakinStatus::create([
                                'user_proposal_id' => $proposal->user_proposal_id,
                                'ic_id' => $productData->company_id,
                                'breakin_number' => $inspection_id,
                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        updateJourneyStage([
                            'user_product_journey_id' => $enquiryId,
                            'ic_id' => $productData->company_id,
                            'stage' => $is_breakin == 'Y' ? STAGE_NAMES['INSPECTION_PENDING'] : STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id
                        ]);
            
                        return response()->json([
                            'status' => true,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => "Proposal Submitted Successfully!",
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $enquiryId,
                                'proposalNo' => $proposal_response['Data']['TransactionNo'],
                                'finalPayableAmount' => $final_payable_amount,
                                'is_breakin' => $is_breakin,
                                'inspection_number' => $inspection_id
                            ]
                        ]);
                    }
                    else
                    {
                        return camelCase([
                            'status' => false,
                            'premium_amount' => 0,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => isset($proposal_response['Message']) ? self::createErrorMessage($proposal_response['Message']) : 'An error occured while recalculating premium'
                        ]);
                    }
                }
                else
                {
                    return camelCase([
                        'status' => false,
                        'premium_amount' => 0,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ]);
                }
            }
            else
            {
                return camelCase([
                    'status' => false,
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => isset($premium_response['Message']) ? self::createErrorMessage($premium_response['Message']) : 'An error occured while calculating premium'
                ]);
            }
        }
        else
        {
            return camelCase([
                'status' => false,
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ]);
        }
    }

    public static function createErrorMessage($message)
    {
        if (is_array($message))
        {
            return implode('. ', $message);
        }
        
        return $message;
    }
    
    public static function renewalSubmit($proposal, $request)
    {
        $requestData = getQuotation($proposal->user_product_journey_id);
        $enquiryId   = customDecrypt($request['enquiryId']);
        $productData = getProductDataByIc($request['policyId']);
        $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
        
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER)['data'];
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code') ->first();
        $vehicleDetails = [
            'manf_name'             => $mmv->vehicle_manufacturer,
            'model_name'            => $mmv->vehicle_model_name,
            'version_name'          => $mmv->variant,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->carrying_capacity,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'CAR',
            'version_id'            => $mmv->ic_version_code,
        ];
        
      
        $policy_data = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE'),//'PBIN0001',//
            ],
            'PreviousPolicyNumber' =>  $proposal->previous_policy_number,
            'VehicleRegistrationNumber' => removingExtraHyphen($proposal->vehicale_registration_number),
        ];
        
        // $policy_data = [
        //     'ConfigurationParam' => [
        //         'AgentCode' => 'PBIN0001'
        //     ],
        //     'PreviousPolicyNumber' =>  '2319201172935000000',
        //     'VehicleRegistrationNumber' => 'MH-01-CC-1011',
        // ];
    $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_FETCH_POLICY_DETAILS');
    // $url = 'https://uatcp.hdfcergo.com/PCOnline/ChannelPartner/RenewalCalculatePremium';
   $get_response = getWsData($url, $policy_data, 'hdfc_ergo', [
       'section' => $productData->product_sub_type_code,
       'method' => 'Fetch Policy Details',
       'requestMethod' => 'post',
       'enquiryId' => $enquiryId,
       'productName' => $productData->product_name,
       'transaction_type' => 'proposal',
       'headers' => [
           'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MERCHANT_KEY'),//'RENEWBUY',//
           'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
           'Content-Type' => 'application/json',
           //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
           'User-Agent' => $_SERVER['HTTP_USER_AGENT']
           //'Accept-Language' => 'en-US,en;q=0.5'
       ]
   ]);
   $policy_data_response = $get_response['response'];
    //print_r($policy_data_response);
    
//     $policy_data_response = '{
//     "Status": 200,
//     "UniqueRequestID": "100a0cd5-3928-44c2-a38f-7785e6c85300",
//     "Message": null,
//     "Data": {
//         "PreviousPolicyNo": "2311201091832500000",
//         "VehicleMakeCode": 84,
//         "VehicleMakeName": "MARUTI",
//         "VehicleModelName": "ALTO 800",
//         "VehicleModelCode": 25373,
//         "RegistrationNo": "MH-02-YP-2987",
//         "PurchaseRegistrationDate": "2017-04-30",
//         "ManufacturingYear": "2017",
//         "PreviousPolicyStartDate": "2018-04-30",
//         "PreviousPolicyEndDate": "2019-04-29",
//         "RtoLocationCode": 10406,
//         "RegistrationCity": "MUMBAI",
//         "EngineNo": "4658965874",
//         "ChassisNo": "3658965874",
//         "VehicleCubicCapacity": 796,
//         "VehicleSeatingCapacity": 5,
//         "NcbDiscount": "20",
//         "OldNcbPercentage": 20,
//         "PolicyIssuingAddress": "LEELA BUSINESS PARK, 6TH FLR, ANDHERI - KURLA RD, MUMBAI, 400059.",
//         "PolicyIssuingPhoneno": "+91-22-66383600",
//         "IntermediaryCode": "200278133519",
//         "AddOnsOptedLastYear": "ZERODEP,EMERGASSIST,NCBPROT",
//         "PAPaidDriverLastYear": "NO",
//         "UnnamedPassengerLastYear": "YES",
//         "LLPaidDriverLastyear": "NO",
//         "LLEmployeeLastYear": "NO",
//         "TppdDiscountLastYear": null,
//         "ExLpgCngKitLastYear": null,
//         "ElectricalAccessoriesIdv": 0,
//         "NonelectricalAccessoriesIdv": 0,
//         "LpgCngKitIdv": 0,
//         "PAPaidDriverSI": 0,
//         "PAOwnerDriverSI": 100000,
//         "UnnamedPassengerSI": 40000,
//         "NoOfUnnamedPassenger": 5,
//         "NoOfLLPaidDrivers": 0,
//         "NumberOfLLEmployees": 0,
//         "TppdLimit": 0,
//         "IsBreakin": 1,
//         "IsWaiver": 0,
//         "CustomerDetail": [
//             {
//                 "CustomerType": "INDIVIDUAL",
//                 "Title": "MR",
//                 "Gender": "MALE",
//                 "FirstName": "AMIT",
//                 "MiddleName": "KUMAR",
//                 "LastName": "SHARMA",
//                 "DateofBirth": "1970-01-25",
//                 "EmailAddress": "AMEYA.GOKHALE@HDFCERGO.CO.IN",
//                 "MobileNumber": "9899999999",
//                 "OrganizationName": null,
//                 "OrganizationContactPersonName": "NA",
//                 "Pancard": "BJKLI3653P",
//                 "GstInNo": "27AAACB5343E1Z1"
//             }
//         ],
//         "PrivateCarRenewalPremiumList": [
//             {
//                 "VehicleIdv": 255494,
//                 "BasicODPremium": 5611,
//                 "BasicTPPremium": 1850,
//                 "NetPremiumAmount": 9192,
//                 "TaxPercentage": 18,
//                 "TaxAmount": 1655,
//                 "TotalPremiumAmount": 10847,
//                 "NewNcbDiscountAmount": 1403,
//                 "NewNcbDiscountPercentage": 25,
//                 "VehicleIdvMin": 255494,
//                 "VehicleIdvMax": 268269,
//                 "BestIdv": 255494,
//                 "VehicleIdvYear2": 0,
//                 "VehicleIdvYear3": 0,
//                 "ElectricalAccessoriesPremium": 0,
//                 "NonelectricalAccessoriesPremium": 0,
//                 "LpgCngKitODPremium": 0,
//                 "LpgCngKitTPPremium": 60,
//                 "LLPaidDriverRate": 50,
//                 "LLPaidDriversPremium": 0,
//                 "UnnamedPassengerRate": 0.0005,
//                 "UnnamedPassengerPremium": 100,
//                 "PAPaidDriverRate": 0.0005,
//                 "PAPaidDriverPremium": 0,
//                 "LLEmployeeRate": 50,
//                 "LLEmployeePremium": 0,
//                 "PACoverOwnerDriverPremium": 325,
//                 "NewPolicyStartDate": "2019-05-13",
//                 "NewPolicyEndDate": "2020-05-12",
//                 "CgstPercentage": 9,
//                 "CgstAmount": 827.5,
//                 "SgstPercentage": 9,
//                 "SgstAmount": 827.5,
//                 "IgstPercentage": 0,
//                 "IgstAmount": 0,
//                 "OtherDiscountAmount": 0,
//                 "TotalAppliedDiscount": 29.77,
//                 "DiscountLimit": 70,
//                 "ODRate": 0.02196,
//                 "ElectricalODRate": 0,
//                 "LpgcngodRate": 0,
//                 "NonelectricalODRate": 0,
//                 "OtherDiscountPercentage": 0,
//                 "BreakinAmount": 0,
//                 "BreakinRate": 0,
//                 "PremiumPAPaidDriverSI": 0,
//                 "PremiumNoOfLLPaidDrivers": 0,
//                 "PremiumUnnamedPassengerSI": 40000,
//                 "PremiumNoOfUnnamedPassenger": 5,
//                 "PremiumNumberOfLLEmployees": 0,
//                 "PolicyIssuingBranchOfficeCode": 1000,
//                 "AddOnCovers": [
//                     {
//                         "CoverName": "ZERODEP",
//                         "CoverPremium": 1788
//                     },
//                     {
//                         "CoverName": "NCBPROT",
//                         "CoverPremium": 511
//                     },
//                     {
//                         "CoverName": "ENGEBOX",
//                         "CoverPremium": 511
//                     },
//                     {
//                         "CoverName": "RTI",
//                         "CoverPremium": 639
//                     },
//                     {
//                         "CoverName": "COSTCONS",
//                         "CoverPremium": 1277
//                     },
//                     {
//                         "CoverName": "EMERGASSIST",
//                         "CoverPremium": 350
//                     },
//                     {
//                         "CoverName": "EMERGASSISTWIDER",
//                         "CoverPremium": 250
//                     }
//                 ]
//             }
//         ]
//     }
// }';

    $policy_data_response = json_decode($policy_data_response,true);
    if($policy_data_response['Status'] == 200)
    {
        $all_data = $policy_data_response['Data'];
        $AddOnsOptedLastYear = explode(',',$all_data['AddOnsOptedLastYear']);
        $PrivateCarRenewalPremiumList = $all_data['PrivateCarRenewalPremiumList'][0];
        $AddOnCovers = $PrivateCarRenewalPremiumList['AddOnCovers'] ?? '';
        $CustomerDetail = $all_data['CustomerDetail'][0];
        $UniqueRequestID = $policy_data_response['UniqueRequestID'];

        //OD Premium
        $basicOD = $PrivateCarRenewalPremiumList['BasicODPremium'] ?? 0;
        $ElectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['ElectricalAccessoriesPremium'] ?? 0 ;
        $NonelectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['NonelectricalAccessoriesPremium'] ?? 0;
        $LpgCngKitODPremium = $PrivateCarRenewalPremiumList['LpgCngKitODPremium'] ?? 0;

        $fianl_od = $basicOD + $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;

        //TP Premium           
        $basic_tp = $PrivateCarRenewalPremiumList['BasicTPPremium'] ?? 0;        
        $LLPaidDriversPremium = $PrivateCarRenewalPremiumList['LLPaidDriversPremium'] ?? 0;
        $UnnamedPassengerPremium = $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] ?? 0;
        $PAPaidDriverPremium = $PrivateCarRenewalPremiumList['PAPaidDriverPremium'] ?? 0;       
        $PremiumNoOfLLPaidDrivers = $PrivateCarRenewalPremiumList['PremiumNoOfLLPaidDrivers'] ?? 0;
        $LpgCngKitTPPremium = $PrivateCarRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
        if (!empty($PrivateCarRenewalPremiumList['BuiltInLpgCngKitPremium'])) {
            $LpgCngKitTPPremium += $PrivateCarRenewalPremiumList['BuiltInLpgCngKitPremium'];
        }
        $PACoverOwnerDriverPremium = $PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] ?? 0;
        $tppD_Discount = $PrivateCarRenewalPremiumList['TppdAmount'] ?? 0;

        $final_tp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium + $PremiumNoOfLLPaidDrivers + $LpgCngKitTPPremium + $PACoverOwnerDriverPremium -$tppD_Discount;
        $NewNcbDiscountPercentage = $PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0;
        //Discount 
        $applicable_ncb = ($requestData->is_claim == 'Y' && $NewNcbDiscountPercentage == 0) ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0);
        $NcbDiscountAmount = ($requestData->is_claim == 'Y' && $NewNcbDiscountPercentage == 0) ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountAmount'] ?? 0);
        $OtherDiscountAmount = $PrivateCarRenewalPremiumList['OtherDiscountAmount'] ?? 0;
        $total_discount = $NcbDiscountAmount + $OtherDiscountAmount ;
        
        $zeroDepreciation           = 0;
        $engineProtect              = 0;
        $keyProtect                 = 0;
        $tyreProtect                = 0;
        $returnToInvoice            = 0;
        $lossOfPersonalBelongings   = 0;
        $roadSideAssistance         = 0;
        $consumables                = 0;
        $ncb_protection             = 0;
        
        $IsZeroDepCover = $IsLossOfUse = $IsEmergencyAssistanceCover = $IsNoClaimBonusProtection = $IsEngineAndGearboxProtectorCover = 
        $IsCostOfConsumable = $IsReturntoInvoice = $IsEmergencyAssistanceWiderCover = $IsTyreSecureCover = 'NO';
        
        if(is_array($AddOnCovers))  //LOPB cover is discontinued by hdfc
        {
            foreach ($AddOnCovers as $key => $value) 
            {
                if(in_array($value['CoverName'], $AddOnsOptedLastYear))
                {
                    if($value['CoverName'] == 'ZERODEP')
                    {
                       $zeroDepreciation = $value['CoverPremium'];
                       $IsZeroDepCover = 'YES';
                    }
                    else if($value['CoverName'] == 'NCBPROT')
                    {
                        $ncb_protection = $value['CoverPremium'];
                        $IsNoClaimBonusProtection = 'YES';
                    }
                    else if($value['CoverName'] == 'ENGEBOX')
                    {
                        $engineProtect = $value['CoverPremium'];
                        $IsEngineAndGearboxProtectorCover = 'YES';
                    }
                    else if($value['CoverName'] == 'RTI')
                    {
                        $returnToInvoice = $value['CoverPremium'];
                        $IsReturntoInvoice = 'YES';
                    }
                    else if($value['CoverName'] == 'COSTCONS')
                    {
                        $consumables = $value['CoverPremium'];//consumable
                        $IsCostOfConsumable = 'YES';
                    }
                    else if($value['CoverName'] == 'EMERGASSIST')
                    {
                        $roadSideAssistance = $value['CoverPremium'];//road side assis
                        $IsEmergencyAssistanceCover = 'YES';
                    }
                    else if($value['CoverName'] == 'EMERGASSISTWIDER')
                    {
                       $keyProtect = $value['CoverPremium'];//$key_replacement 
                       $IsEmergencyAssistanceWiderCover = 'YES';
                    }
                    else if($value['CoverName'] == 'TYRESECURE')
                    {
                        $tyreProtect = $value['CoverPremium'];
                        $IsTyreSecureCover = 'YES';
                    }
                    else if($value['CoverName'] == 'LOSSUSEDOWN')
                    {
                        $lossOfPersonalBelongings = $value['CoverPremium'];
                        $IsLossOfUse = 'YES';
                    }               
                }                                 
            }                       
        }

        $addons = $zeroDepreciation + $ncb_protection + $engineProtect + $returnToInvoice + $consumables + $roadSideAssistance + $keyProtect + $tyreProtect + $lossOfPersonalBelongings;
        //final calc
        $NetPremiumAmount = $fianl_od + $final_tp + $addons - $NcbDiscountAmount;
        $TaxAmount = round($NetPremiumAmount * 0.18);
        $TotalPremiumAmount = $NetPremiumAmount + $TaxAmount;
        $TotalPremiumAmount = $PrivateCarRenewalPremiumList['TotalPremiumAmount'] ?? $TotalPremiumAmount;
        $is_cpa = (int)$PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] > 0 ? true : false;
        $address = HdfcErgoMotorPincodeMaster::where('num_pincode', $proposal->pincode)->where('txt_pincode_locality',$proposal->city)->first();
        $address_data = [
            'address' => removeSpecialCharactersFromString($proposal->address_line1, true),
            'address_1_limit'   => 50,
            'address_2_limit'   => 50,         
            'address_3_limit'   => 250,         
        ];
        $getAddress = getAddress($address_data);
        $add1 = $getAddress['address_1'];
        $add2 = $getAddress['address_2'];
        $add3 = $getAddress['address_3'];

        if (strlen($getAddress['address_1']) < 10) {
            $add1 = $getAddress['address_1'] ." ". $proposal->state ." ". $proposal->city." ". $proposal->pincode;
        }
        if (strlen($getAddress['address_2']) < 10) {
            $add2 = $getAddress['address_2'] ." ". $proposal->state ." ". $proposal->city ." ". $proposal->pincode;
        }
        if (strlen($getAddress['address_3']) < 10) {
            $add3 = $getAddress['address_3'] ." ". $proposal->state ." ". $proposal->city ." ". $proposal->pincode;
        }

        $GstInNo = !empty($proposal->gst_number) ? $proposal->gst_number : $CustomerDetail['GstInNo'];
        if(strtoupper($GstInNo) == "NA")
        {
            $GstInNo = '';
        }
        $proposal_input_data = [
            'UniqueRequestID' => $UniqueRequestID,
            'ProposalDetails' => [
                'ConfigurationParam' => [
                    'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE')//'PBIN0001',
                ],
                'PreviousPolicyNumber'              => $all_data['PreviousPolicyNo'],//'2311201090946600000',
                'VehicleRegistrationNumber'         => $all_data['RegistrationNo'],//'MH-02-LO-3534',
                'IsEADiscount'                      => 0,
                'RequiredIdv'                       => $PrivateCarRenewalPremiumList['VehicleIdv'] ?? 0,//487364,
                'NetPremiumAmount'                  => $NetPremiumAmount, //$PrivateCarRenewalPremiumList['NetPremiumAmount'],//15410,
                'TotalPremiumAmount'                => $TotalPremiumAmount, //$PrivateCarRenewalPremiumList['TotalPremiumAmount'],//18184,
                'TaxAmount'                         => $TaxAmount, //round($PrivateCarRenewalPremiumList['TaxAmount']),//2774,
            
                'AddOnCovers' => [
                  'IsZeroDepCover'                  => $IsZeroDepCover,
                  'IsLossOfUse'                     => $IsLossOfUse,
                  'IsEmergencyAssistanceCover'      => $IsEmergencyAssistanceCover,
                  'IsNoClaimBonusProtection'        => $IsNoClaimBonusProtection,
                  'IsEngineAndGearboxProtectorCover' => $IsEngineAndGearboxProtectorCover,//'NO',
                  'IsCostOfConsumable'                  => $IsCostOfConsumable,//'NO',
                  'IsReturntoInvoice' => $IsReturntoInvoice,
                  'IsEmergencyAssistanceWiderCover' => $IsEmergencyAssistanceWiderCover,//'NO',
                  'IsTyreSecureCover' => $IsTyreSecureCover,//'YES',
                  'NonelectricalAccessoriesIdv' => $all_data['NonelectricalAccessoriesIdv'],
                  'ElectricalAccessoriesIdv' => $all_data['ElectricalAccessoriesIdv'],
                  'LpgCngKitIdv' => $all_data['LpgCngKitIdv'],
                  'SelectedFuelType' => (int) $all_data['LpgCngKitIdv'] > 0 ? 'CNG' : NULL,
                  'IsPAPaidDriver' => $all_data['PAPaidDriverLastYear'],
                  'PAPaidDriverSumInsured' => $all_data['PAPaidDriverSI'],
                  'IsPAUnnamedPassenger' => $all_data['UnnamedPassengerLastYear'],
                  'PAUnnamedPassengerNo' => $all_data['NoOfUnnamedPassenger'],
                  'PAPerUnnamedPassengerSumInsured' => $all_data['UnnamedPassengerSI'],//40000,
                  'IsLegalLiabilityDriver' => $all_data['LLPaidDriverLastyear'],
                  'LLPaidDriverNo' => $all_data['NoOfLLPaidDrivers'],
                  'IsLLEmployee' => $all_data['LLEmployeeLastYear'],//'YES',
                  'LLEmployeeNo' => $all_data['NumberOfLLEmployees'],
                  'CpaYear' => $is_cpa == true ? 1 : 0,
                  'IsTPPDDiscount' => !empty($tppD_Discount) ? 'YES' : 'NO',
                ]
            ],
            'CustomerDetails' => [
              'EmailAddress'    => $CustomerDetail['EmailAddress'],//'ankit.mori@synoverge.com',
              'MobileNumber'    => $CustomerDetail['MobileNumber'],//'8128911914',
              'PanCard'         => !empty($proposal->pan_number) ? $proposal->pan_number : $CustomerDetail['Pancard'],
              'GstInNo'         => $GstInNo,//strtoupper($CustomerDetail['GstInNo']) != "NA" ? $CustomerDetail['GstInNo'] : '',
              //'LGCode'          => 'LGCODE123',
              'CorrespondenceAddress1' => $add1,
              'CorrespondenceAddress2' => $add2,
              'CorrespondenceAddress3' => $add3,
              'CorrespondenceAddressCitycode'   => $address->num_citydistrict_cd,
              'CorrespondenceAddressCityName'   => $address->txt_pincode_locality,
              'CorrespondenceAddressStateCode'  => $address->city->state->num_state_cd,
              'CorrespondenceAddressStateName'  => $address->city->state->txt_state,
              'CorrespondenceAddressPincode'    => $proposal->pincode,
              //'InspectionMethod' => 'SURVEYOR',
              //'InspectionStateCode' => 14,
              //'InspectionCityCode' => 273,
              //'InspectionLocationCode' => 143,
              'IsGoGreen' => 0,
            ]
          ];

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $proposal_input_data['CustomerDetails']['KYCId'] = $proposal->ckyc_reference_id;
        }
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        if (isset($selected_addons->compulsory_personal_accident[0]['name'])) {
            $is_cpa = true;
        }else{
            $is_cpa = false;
        }
        if($is_cpa == true)
        {
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeName'] = $proposal->nominee_name;
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeAge'] = $proposal->nominee_age;
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeRelationship'] = $proposal->nominee_relationship;           
        }else
        {
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeAge'] = 0;
        }

        if($requestData->previous_policy_type == 'Third-party'){
            $proposal_input_data['proposalDetails']['proposalRisk']['tpEndDate'] = $proposal->tp_end_date;
            $proposal_input_data['proposalDetails']['proposalRisk']['tpStartDate'] =  $proposal->tp_start_date;
            $proposal_input_data['proposalDetails']['proposalRisk']['tpTerm'] = 1;
            $proposal_input_data['proposalDetails']['proposalRisk']['typeOfInspection'] = "SELF";
            $proposal_input_data['proposalDetails']['proposalRisk']['userEnteredIDV'] = 0;
        }
        HdfcErgoPremiumDetailController::saveRenewalPremiumDetails($get_response['webservice_id']);
        
        // $url = "https://uatcp.hdfcergo.com/PCOnline/ChannelPartner/RenewalSaveTransaction";
        $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_RENEWAL_MOTOR_PROPOSAL_SUBMIT_URL');
           $get_response = getWsData($url, $proposal_input_data, 'hdfc_ergo', [
               'section' => $productData->product_sub_type_code,
               'method' => 'Renewal Proposal Submit',
               'requestMethod' => 'post',
               'enquiryId' => $enquiryId,
               'productName' => $productData->product_name,
               'transaction_type' => 'proposal',
               'headers' => [
                   'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MERCHANT_KEY'),//'RENEWBUY',//
                   'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
                   'Content-Type' => 'application/json',
                   //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
                   'User-Agent' => $_SERVER['HTTP_USER_AGENT']
                   //'Accept-Language' => 'en-US,en;q=0.5'
               ]
           ]);
           $proposal_input_response = $get_response['response'];
//    
        
//        print_r($proposal_input_response);
//        die;
        
        // $proposal_input_response = '{
        //     "Status": 200,
        //     "UniqueRequestID": "8483721e-8ca3-47a9-bb74-4aa2b18c45db",
        //     "Message": null,
        //     "Data": {
        //         "TransactionNo": 1122204083207.0,
        //         "QuoteNo": 19122042535780.0,
        //         "IsBreakin": 0,
        //         "IsWaiver": 0,
        //         "PaymentMethod": "",
        //         "NewPolicyStartDate": "2022-04-29",
        //         "NewPolicyEndDate": "2023-04-28",
        //         "TPNewPolicyStartDate": "2022-04-29",
        //         "TPNewPolicyEndDate": "2023-04-28"
        //     }
        // }';
        $proposal_reponse = json_decode($proposal_input_response,true);
       // print_r($proposal_reponse);
        //die;
        if($proposal_reponse['Status'] == 200)
        {
            //$PrivateCarRenewalPremiumList;
            $policy_start_date = date('d-m-Y', strtotime($proposal_reponse['Data']['NewPolicyStartDate']));
            $policy_end_date = date('d-m-Y', strtotime($proposal_reponse['Data']['NewPolicyEndDate']));
            
             //OD Premium
            $basicOD = $PrivateCarRenewalPremiumList['BasicODPremium'] ?? 0;
            $ElectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['ElectricalAccessoriesPremium'] ?? 0 ;
            $NonelectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['NonelectricalAccessoriesPremium'] ?? 0;
            $LpgCngKitODPremium = $PrivateCarRenewalPremiumList['LpgCngKitODPremium'] ?? 0;

            $fianl_od = $basicOD + $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;

            //TP Premium           
            $basic_tp = $PrivateCarRenewalPremiumList['BasicTPPremium'] ?? 0;        
            $LLPaidDriversPremium = $PrivateCarRenewalPremiumList['LLPaidDriversPremium'] ?? 0;
            $UnnamedPassengerPremium = $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] ?? 0;
            $PAPaidDriverPremium = $PrivateCarRenewalPremiumList['PAPaidDriverPremium'] ?? 0;       
            $PremiumNoOfLLPaidDrivers = $PrivateCarRenewalPremiumList['PremiumNoOfLLPaidDrivers'] ?? 0;
            $LpgCngKitTPPremium = $PrivateCarRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
            $PACoverOwnerDriverPremium = $PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] ?? 0;

            $final_tp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium + $PremiumNoOfLLPaidDrivers + $LpgCngKitTPPremium + $PACoverOwnerDriverPremium;

            //Discount 
            // $applicable_ncb = ($requestData->is_claim == 'Y') ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0);
            // $NcbDiscountAmount = ($requestData->is_claim == 'Y') ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountAmount'] ?? 0);
            $OtherDiscountAmount = $PrivateCarRenewalPremiumList['OtherDiscountAmount'] ?? 0;
            $tppD_Discount = $PrivateCarRenewalPremiumList['TppdAmount'] ?? 0;
            $total_discount = $NcbDiscountAmount + $OtherDiscountAmount + $tppD_Discount;

            //final calc
            
            // $NetPremiumAmount = $fianl_od + $final_tp + $addons - $NcbDiscountAmount - $tppD_Discount;
            // $TaxAmount = round($NetPremiumAmount * 0.18);
            // $TotalPremiumAmount = $NetPremiumAmount + $TaxAmount;
            $NetPremiumAmount = $PrivateCarRenewalPremiumList['NetPremiumAmount'] ?? 0;
            $TaxAmount = $PrivateCarRenewalPremiumList['TaxAmount'] ?? 0;
            $TotalPremiumAmount = $PrivateCarRenewalPremiumList['TotalPremiumAmount'] ?? 0;  
            
            $addon_premium = $zeroDepreciation + $engineProtect + $keyProtect + $tyreProtect + $returnToInvoice + $lossOfPersonalBelongings + 
            $roadSideAssistance + $consumables + $ncb_protection + $ElectricalAccessoriesPremium + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;
            $idv = $PrivateCarRenewalPremiumList['VehicleIdv'] ?? 0;
            $proposal->idv                  = $idv;
            $proposal->proposal_no          = $proposal_reponse['Data']['TransactionNo'];
            $proposal->unique_proposal_id   = $proposal_reponse['UniqueRequestID'];
            //$proposal->customer_id          = $generalInformation['customerId'];
            $proposal->od_premium           = $basicOD - $NcbDiscountAmount;
            $proposal->tp_premium           = $final_tp;
            $proposal->ncb_discount         = $NcbDiscountAmount;
            $proposal->addon_premium        = $addon_premium;
            $proposal->total_premium        = $NetPremiumAmount;
            $proposal->service_tax_amount   = $TaxAmount;
            $proposal->final_payable_amount = $TotalPremiumAmount;
            $proposal->cpa_premium          = $PACoverOwnerDriverPremium;
            $proposal->total_discount       = $total_discount;
            $proposal->ic_vehicle_details   = $vehicleDetails;
            $proposal->policy_start_date    = $policy_start_date;
            $proposal->policy_end_date      = $policy_end_date;
            $proposal->tp_start_date        = $policy_start_date;
            $proposal->tp_end_date          = $policy_end_date;
            $proposal->save();

            $updateJourneyStage['user_product_journey_id'] = $enquiryId;
            $updateJourneyStage['ic_id'] = '11';
            $updateJourneyStage['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
            $updateJourneyStage['proposal_id'] = $proposal->user_proposal_id;
            updateJourneyStage($updateJourneyStage);
                
            return response()->json([
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => camelCase([
                    'proposal_no'        => $proposal_reponse['Data']['TransactionNo'],
                    'finalPayableAmount' => $TotalPremiumAmount
                ]),
            ]);        

        }
        else
        {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => is_array($proposal_reponse['Message']) ? implode(',', $proposal_reponse['Message']) : 'Service Issue'
            ];
        }
     }
    }
}