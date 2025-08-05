<?php

namespace App\Http\Controllers\Proposal\Services\Car\V1;

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
        // try {
        $enquiryId = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $breaki_id = '47894';
        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);
        $is_breakin = false;//((strpos($requestData->business_type, 'breakin') === false) ? false : true);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d/m/Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();
        if ($premium_type == 'third_party_breakin') {
            $policy_start_date = today()->addDay(1)->format('d/m/Y');
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
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
        $rto_code = RtoCodeWithOrWithoutZero($rto_code, true); //DL RTO code
        $rto_location = HdfcErgoRtoLocation::where('rto_code', $rto_code)->first();
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $requestData->previous_policy_expiry_date = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $car_age = ceil($age / 12);

        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
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

        $vehicale_registration_number = getRegisterNumberWithHyphen($proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $RSACover = $PAforUnnamedPassengerSI = $nilDepreciationCover = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers); // new business
        $PreviousNilDepreciation = '0'; // addon
        $tppd_cover = 0;
        $voluntary_deductible = 0;
        if (isset($requestData->voluntary_excess_value)) {
            if ($requestData->voluntary_excess_value == 20000 || $requestData->voluntary_excess_value == 25000) {
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
            //$policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
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
            $KeyReplacementYN = $InvReturnYN = $engine_protection = $LossOfPersonBelongYN = $LLtoPaidDriverYN = $PAPaidDriverConductorCleanerSI = $tyresecure = $LossOfPersonalBelonging_SI = 0;
        $LLtoPaidDriverYN = $geoExtension = '0';
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
                $geoExtension = '1';
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
            $CPA_Tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? (string) $selected_addons->compulsory_personal_accident[0]['tenure'] : '1';
        } else {
            $is_cpa = "true";
            $CPA_Tenure = '1';//isset($selected_addons->compulsory_personal_accident[0]['tenure'])? (string)$selected_addons->compulsory_personal_accident[0]['tenure'] : '0';
        }
        if ($premium_type == 'own_damage') {
            $is_cpa = "true";
            $CPA_Tenure = '1';//isset($selected_addons->compulsory_personal_accident[0]['tenure'])? (string)$selected_addons->compulsory_personal_accident[0]['tenure'] : '0';
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
                $LossOfPersonalBelonging_SI = 30000;
            }
            if (in_array('Tyre Secure', $value)) {
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
        $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
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
        } elseif (config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' && $quote->idv <= 5000000) {
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

        if (isBhSeries($proposal->vehicale_registration_number)) {
            $vehicale_registration_number = getRegisterNumberWithHyphen($proposal->vehicale_registration_number);
        }
        //checking last addons
        $PreviousPolicy_IsZeroDept_Cover = $PreviousPolicy_IsRTI_Cover = false;
        if (!empty($proposal->previous_policy_addons_list)) {
            $previous_policy_addons_list = is_array($proposal->previous_policy_addons_list) ? $proposal->previous_policy_addons_list : json_decode($proposal->previous_policy_addons_list);
            foreach ($previous_policy_addons_list as $key => $value) {
                if ($key == 'zeroDepreciation' && $value) {
                    $PreviousPolicy_IsZeroDept_Cover = true;
                } else if ($key == 'returnToInvoice' && $value) {
                    $PreviousPolicy_IsRTI_Cover = true;
                }
            }
        }

        if ($nilDepreciationCover && !$PreviousPolicy_IsZeroDept_Cover) {
            $is_breakin = true;
        }
        if ($InvReturnYN && !$PreviousPolicy_IsRTI_Cover) {
            $is_breakin = true;
        }
        if ($requestData->business_type == 'newbusiness') {
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
            'productName' => $productData->product_name . " ($business_type)",
            'transaction_type' => 'proposal',
            'PRODUCT_CODE' => $ProductCode,// config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
            'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
            'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
            'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
            'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
        ];

        $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.TOKEN_LINK_URL_GIC'), '', 'hdfc_ergo', $additionData);

        $token = $get_response['response'];
        $token_data = json_decode($token, TRUE);

        if (isset($token_data['Authentication']['Token'])) {
            $proposal_array = [
                'TransactionID' => $transactionid,//config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR') ,//$enquiryId,
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
                    'PreviousPolicy_PolicyEndDate' => date('d/m/Y', strtotime($proposal->prev_policy_expiry_date)),
                    'PreviousPolicy_PolicyStartDate' => date('d/m/Y', strtotime($previousPolicyStartDate)),
                    'PreviousPolicy_PolicyClaim' => ($requestData->is_claim == 'N') ? 'NO' : 'YES',
                    'PreviousPolicy_PolicyNo' => $proposal->previous_policy_number,
                    'PreviousPolicy_PreviousPolicyType' => (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage') ? 'Comprehensive Package' : 'TP'),
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
                    'IsLimitedtoOwnPremises' => '0',
                    'ExtensionCountryCode' => $geoExtension,
                    'ExtensionCountryName' => '',
                    'BiFuelType' => ($externalCNGKITSI > 0 ? "CNG" : ""),
                    'POSP_CODE' => [],
                    'BiFuel_Kit_Value' => $externalCNGKITSI,
                    'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : ($premium_type == "third_party" ? "" : 'OD Plus TP'),
                    //'POLICY_TYPE' => $premium_type == 'own_damage' ? 'OD Only' : 'OD Plus TP', // as per the IC in case of tp only value for POLICY_TYPE will be null
                    'LLPaiddriver' => $LLtoPaidDriverYN,
                    //                    "BreakIN_ID" =>(($is_breakin)?'47894':''),
                    //                    "EMIAmount" => "0",
                    'PAPaiddriverSI' => $PAPaidDriverConductorCleanerSI,
                    'IsZeroDept_Cover' => $nilDepreciationCover,
                    'IsNCBProtection_Cover' => (int) $ncb_protction,
                    'IsRTI_Cover' => (int) $InvReturnYN,
                    'IsCOC_Cover' => (int) $consumable,
                    'IsEngGearBox_Cover' => (int) $engine_protection,
                    'IsEA_Cover' => (int) $RSACover,
                    'IsEAW_Cover' => (int) $KeyReplacementYN,
                    'IsTyreSecure_Cover' => (int) $tyresecure,
                    'isBatteryChargerAccessoryCover' => $isBatteryProtect,
                    'NoofUnnamedPerson' => (int) $PAforUnnamedPassenger,
                    'IsLossOfPersonalBelongings_Cover' => (int) $LossOfPersonBelongYN,
                    'LossOfPersonalBelonging_SI' => $LossOfPersonalBelonging_SI,
                    // 'IsLossofUseDownTimeProt_Cover' => (int) $LossOfPersonBelongYN,
                    'UnnamedPersonSI' => (int) $PAforUnnamedPassengerSI,
                    'ElecticalAccessoryIDV' => (int) $ElectricalaccessSI,
                    'NonElecticalAccessoryIDV' => (int) $NonElectricalaccessSI,
                    'CPA_Tenure' => $CPA_Tenure,#($requestData->business_type == 'newbusiness' ? '3' : '1'),
                    'Effectivedrivinglicense' => $is_cpa,
                    // 'Voluntary_Excess_Discount' => $voluntary_deductible,
                    'POLICY_TENURE' => (($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') ? '3' : '1'),
                    // 'TPPDLimit' => $tppd_cover, as per #23856
                    "Owner_Driver_Nominee_Name" => ($proposal->owner_type == 'I') ? $proposal->nominee_name : "",
                    "Owner_Driver_Nominee_Age" => ($proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                    "Owner_Driver_Nominee_Relationship" => (!$premium_type == 'own_damage' || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                    //                    "Owner_Driver_Appointee_Name" => ($proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                    //                    "Owner_Driver_Appointee_Relationship" => ($proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                    'BreakinWaiver' => false,
                    'BreakinInspectionDate' => null,
                    'NumberOfEmployees' => ($requestData->vehicle_owner_type == 'C' ? $mmv_data->seating_capacity : 0),
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
            if (!$is_pos) {
                unset($proposal_array['Req_PvtCar']['POSP_CODE']);
            }
            if ($is_pos) {
                $proposal_array['Req_PvtCar']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
            }
            if($car_age > 5 && $requestData->applicable_ncb != 0 && $productData->zero_dep == '0'){
                $proposal_array['Req_PvtCar']['planType'] = 'Essential ZD plan';
            }
            $additionData = [
                'type' => 'PremiumCalculation',
                'method' => 'Premium Calculation',//'Proposal Submit',
                'requestMethod' => 'post',
                'section' => 'car',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name . " ($business_type)",
                'TOKEN' => $token_data['Authentication']['Token'],
                'transaction_type' => 'proposal',
                'PRODUCT_CODE' => $ProductCode, //config('IC.HDFC_ERGO.V1.CAR.PRODUCT_CODE_HDFC_ERGO_GIC_MOTOR'),
                'SOURCE' => config('IC.HDFC_ERGO.V1.CAR.SOURCE_GIC'),
                'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.CAR.CHANNEL_ID_GIC'),
                'TRANSACTIONID' => $transactionid,// config('IC.HDFC_ERGO.V1.CAR.TRANSACTIONID_HDFC_ERGO_GIC_MOTOR'),
                'CREDENTIAL' => config('IC.HDFC_ERGO.V1.CAR.CREDENTIAL_GIC'),
            ];

            if ($requestData->previous_policy_type != 'Not sure' && $requestData->business_type != 'newbusiness' && empty($proposal->previous_policy_number) && empty($proposal->previous_insurance_company)) {
                return
                    [
                        'status' => false,
                        'message' => 'Previous policy number and previous insurer is mandetory if previous policy type is ' . $requestData->previous_policy_type
                    ];
            }
            if ($requestData->previous_policy_type == 'Not sure') {
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
            //        print_r([config('IC.HDFC_ERGO.V1.CAR.HDFC_ERGO_GIC_MOTOR_PREMIUM'),json_encode($additionData)]);
            //        print_r(json_encode($proposal_array));
            $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_PREMIUM'), $proposal_array, 'hdfc_ergo', $additionData);
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
                $proposal->proposal_no = $arr_premium['TransactionID'];
                $proposal->ic_vehicle_details = $vehicleDetails;
                $proposal->save();
                $Nil_dep = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $age_discount = $lpg_cng_tp = $lpg_cng = $Bonus_Discount = $automobile_discount = $antitheft
                    = $basic_tp_premium = $electrical_accessories = $tppd_value =
                    $non_electrical_accessories = $ncb_protction = $ll_paid_driver =
                    $ncb_protection = $consumables_cover = $Nil_dep = $roadside_asst =
                    $key_replacement = $loss_of_personal_belongings = $eng_protector =
                    $rti = $incon_allow = $Basic_OD_Discount = $electrical_Discount =
                    $non_electrical_Discount = $lpg_od_premium_Discount = $tyre_secure = $GeogExtension_od = $GeogExtension_tp = $OwnPremises_OD = $OwnPremises_TP = $basic_od_premium = $legal_liability_to_employee = 0;
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
                    $GeogExtension_tp = round($premium_data['GeogExtension_TPPremium']);
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
                if (!empty($premium_data['LossOfPersonalBelongings_Premium'])) {
                    $loss_of_personal_belongings = round($premium_data['LossOfPersonalBelongings_Premium']);
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
                if (!empty($premium_data['NumberOfEmployees_Premium'])) {
                    $legal_liability_to_employee = round($premium_data['NumberOfEmployees_Premium']);
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
                    $Nil_dep += (int) $premium_data['Elec_ZD_Premium'];
                    $engine_protection += (int) $premium_data['Elec_ENG_Premium'];
                    $ncb_protection += (int) $premium_data['Elec_NCB_Premium'];
                    $consumables_cover += (int) $premium_data['Elec_COC_Premium'];
                    $rti += (int) $premium_data['Elec_RTI_Premium'];
                }
                if ($non_electrical_accessories > 0) {
                    $Nil_dep += (int) $premium_data['NonElec_ZD_Premium'];
                    $engine_protection += (int) $premium_data['NonElec_ENG_Premium'];
                    $ncb_protection += (int) $premium_data['NonElec_NCB_Premium'];
                    $consumables_cover += (int) $premium_data['NonElec_COC_Premium'];
                    $rti += (int) $premium_data['NonElec_RTI_Premium'];
                }

                if ($lpg_cng > 0) {
                    $Nil_dep += (int) $premium_data['Bifuel_ZD_Premium'];
                    $engine_protection += (int) $premium_data['Bifuel_ENG_Premium'];
                    $ncb_protection += (int) $premium_data['Bifuel_NCB_Premium'];
                    $consumables_cover += (int) $premium_data['Bifuel_COC_Premium'];
                    $rti += (int) $premium_data['Bifuel_RTI_Premium'];
                }

                HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

                $addon_premium = $Nil_dep + $tyre_secure + $consumables_cover + $ncb_protection + $roadside_asst + $key_replacement + $loss_of_personal_belongings + $eng_protector + $rti + $batteryProtect;
                //        print_r([$premium_data['Elec_ZD_Premium'], $premium_data['NonElec_ZD_Premium'], $premium_data['Bifuel_ZD_Premium']]);
                $tp_premium = ($basic_tp_premium + $pa_owner_driver + $ll_paid_driver + $legal_liability_to_employee + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp) - $tppd_value + $GeogExtension_tp + $OwnPremises_TP;
                //print_r([$basic_tp_premium,$pa_owner_driver,$ll_paid_driver,$pa_paid_driver,$pa_unnamed,$lpg_cng_tp,$tppd_value]);
                //die;
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
                    'Customer_Salutation' => $salutation,#($proposal->owner_type == 'I') ? 'MR' : 'M/S',
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

                $od_premium = $basic_od_premium + $non_electrical_accessories + $electrical_accessories + $lpg_cng + $GeogExtension_od + $OwnPremises_OD;
                $final_total_discount = $ncb_discount;
                $total_od_amount = $od_premium - $final_total_discount;
                $additionData['method'] = 'Proposal Submit';
                //$additionData['PRODUCT_CODE'] = '2311';
                //$proposal_url = 'https://integrations.hdfcergo.com/HEI.IntegrationService/Integration/CreateProposal';
                $get_response = getWsData(config('IC.HDFC_ERGO.V1.CAR.GIC_CREATE_PROPOSAL'), $proposal_array, 'hdfc_ergo', $additionData);
                $proposal_submit_response = $get_response['response'];
                $proposal_submit_response = json_decode($proposal_submit_response, true);

                if (!(isset($proposal_submit_response['StatusCode']) && $proposal_submit_response['StatusCode'] == '200'))
                // if($proposal_submit_response['Error'] != 'PAYMENT DETAILS NOT FOUND !')
                {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => $proposal_submit_response['Error'] ?? 'Insurer Not Found'
                    ]);
                }

                if ($proposal_submit_response['Policy_Details']['ProposalNumber'] == null) {
                    return response()->json([
                        'status' => false,
                        'msg' => "The proposal number cannot have a null value",
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
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
                    if (!empty($proposal_data['LossOfPersonalBelongings_Premium'])) {
                        $loss_of_personal_belongings = round($proposal_data['LossOfPersonalBelongings_Premium']);
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

                if ($is_breakin) {
                    CvBreakinStatus::updateOrInsert(
                        [
                            'ic_id' => $productData->company_id,
                            'breakin_number' => $breaki_id,
                            'breakin_id' => $breaki_id,
                            'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                            'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                            //                                'breakin_response'  => $lead_create_response,
                            'payment_end_date' => Carbon::today()->addDay(3)->toDateString(),
                            'created_at' => Carbon::today()->toDateString()
                        ],
                        [
                            'user_proposal_id' => $proposal->user_proposal_id
                        ]
                    );
                    updateJourneyStage([
                        'user_product_journey_id' => $proposal->user_product_journey_id,
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                        'proposal_id' => $proposal->user_proposal_id
                    ]);
                }

                UserProposal::where('user_product_journey_id', $enquiryId)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->update([
                        'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                        'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                        'proposal_no' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                        'unique_proposal_id' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                        'product_code' => $ProductCode,
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
                        'proposalNo' => $proposal_submit_response['Policy_Details']['ProposalNumber'],
                        'finalPayableAmount' => $final_payable_amount,
                        'isBreakinCase' => ($is_breakin) ? 'Y' : 'N',
                        'is_breakin' => ($is_breakin) ? 'Y' : 'N',
                        'inspection_number' => (($is_breakin) ? $breaki_id : '')
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
}