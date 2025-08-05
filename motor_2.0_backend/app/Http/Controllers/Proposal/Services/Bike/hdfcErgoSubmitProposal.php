<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use App\Http\Controllers\Proposal\Services\Bike\V1\hdfcErgoSubmitProposal as hdfcErgoSubmitProposalV1;
use App\Http\Controllers\SyncPremiumDetail\Bike\HdfcErgoPremiumDetailController;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\HdfcErgoBikeRtoLocation;
use App\Models\HdfcErgoMotorPincodeMaster;
use App\Models\HdfcErgoV2MotorPincodeMaster;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\ProposalExtraFields;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
include_once app_path() . '/Quotes/Bike/hdfc_ergo.php';

class hdfcErgoSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y')
        {
            return self::submitV2($proposal, $request);
        }
        else
        {
            if(config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_BIKE') == 'Y')
            {
                if (config('IC.HDFC_ERGO.V1.BIKE.ENABLE') == 'Y') {
                    return hdfcErgoSubmitProposalV1::submit($proposal, $request);
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
            $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
            // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
            // {
            //     return  response()->json([
            //         'status' => false,
            //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            //     ]);
            // }
            $master_policy = MasterPolicy::find($request['policyId']);

            if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
                $policy_start_date = date('d/m/Y');
            } elseif ($requestData->business_type == 'rollover') {
                $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }
            $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
            $mmv = get_mmv_details($productData,$requestData->version_id,'hdfc_ergo');
            if($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
            $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
            $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
            $rto_location =HdfcErgoBikeRtoLocation::where('rto_code', $rto_code)
                ->first();

            $bike_age = 0;
            $prev_policy_end_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new \DateTime($vehicleDate);
            $date2 = new \DateTime($prev_policy_end_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $bike_age = ceil($age / 12);

            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
            $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
            $od_only = ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false;
            $TPPolicyNumber = '';
            $TPPolicyInsurer = '';
            $TPPolicyStartDate = '';
            $TPPolicyEndDate = '';
            $prev_policy_number = '';
            $prev_policy_company = '';
            $is_previous_claim = $requestData->is_claim == 'Y' ? true : false;
            $applicable_ncb = $is_previous_claim ? 0 : $requestData->applicable_ncb;
            $previous_ncb = $is_previous_claim ? 0 : $requestData->previous_ncb;
            $ProductCode = '2312';
            $type_of_cover = 'OD Plus TP';
            $policyType = 'Comprehensive';
            $cpa_tenure = '1';

            if ($requestData->business_type == 'newbusiness') {
                $business_type = 'New Business';
                $BusinessType = 'New Vehicle';
                $policy_start_date = date('Y-m-d');
                $cpa_tenure = '5';
                if ($premium_type == 'comprehensive') {
                    $policy_end_date =  today()->addYear(1)->subDay(1)->format('d/m/Y');
                } elseif ($premium_type == 'third_party') {
                    $policy_end_date =   today()->addYear(5)->subDay(1)->format('d/m/Y');
                }
                // $prev_policy_end_date = '';
            } else if ($requestData->business_type == 'rollover') {
                $business_type = 'Roll Over';
                $BusinessType = 'Roll Over';
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
                $prev_policy_number = $proposal->previous_policy_number;
                $prev_policy_company = $proposal->previous_insurance_company;
            } else if ($requestData->business_type == 'breakin') {
                $business_type = 'Break-In';
                $BusinessType = 'Roll Over';
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
                $prev_policy_number = $proposal->previous_policy_number;
                $prev_policy_company = $proposal->previous_insurance_company;
            }

            if ($tp_only) {
                $ProductCode = '2320';
                $policyType = 'Third Party';
                $type_of_cover = '';
                $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
            } else if ($od_only){
                $policyType = 'Own Damage';
                $type_of_cover = 'OD Only';
                $TPPolicyNumber = $proposal->tp_insurance_number;
                $TPPolicyInsurer = $proposal->tp_insurance_company;
                $TPPolicyStartDate = date('d/m/Y', strtotime($proposal->tp_start_date));
                $TPPolicyEndDate = date('d/m/Y', strtotime($proposal->tp_end_date));
            }
                if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                    $ProductCode = '2367';
                }

            $productName = $productData->product_name. " ($business_type)";
            if ($requestData->business_type != 'newbusiness' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
                $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
                if ($date_difference > 0) {
                    $policy_start_date = date('Y-m-d', strtotime('+3 day'));
                }

                if($date_difference > 90){
                    $applicable_ncb = 0;
                }
            }

            if ($requestData->business_type != 'newbusiness' && in_array($requestData->previous_policy_type, ['Not sure'])) {
                $policy_start_date = date('Y-m-d', strtotime('+3 day'));
                $prev_policy_end_date = date('Y-m-d', strtotime('-120 days'));
                $applicable_ncb = 0;
            }
            if(in_array($premium_type, ['breakin', 'own_damage_breakin','third_party_breakin']))
            {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }

            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('Y-m-d');
            $vehicle_registration_number = explode('-', $proposal->vehicale_registration_number);
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $applicable_addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers); // new business
            $cpa_cover = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident); // new business
            $additional_details = json_decode($proposal->additional_details);
            $is_zero_dep = $is_rsa = $is_rti = $electrical_accessories_si = $non_electrical_accessories_si = $unnamed_passenger = $unnamed_passenger_si = $paid_driver = $paid_driver_si = $LLtoPaidDriverYN = $LLtoPaidDriver = '0';
            $geoExtension="0";
            $cpa = false;
            if (!empty($cpa_cover) && $requestData->vehicle_owner_type == 'I') {
                foreach ($cpa_cover as $key => $value)  {
                    if (isset($value['name']) && $value['name'] == 'Compulsory Personal Accident')  {
                        $cpa = true;
                        $cpa_tenure = isset($value['tenure']) ? (string) $value['tenure'] :'1';
                    }
                }
            }

            foreach($additional_covers as $key => $value) {

                if (in_array('PA cover for additional paid driver', $value)) {
                    $paid_driver = 1;
                    $paid_driver_si = $value['sumInsured'];
                }

                if (in_array('Unnamed Passenger PA Cover', $value)) {
                    $unnamed_passenger = 1;
                    $unnamed_passenger_si = $value['sumInsured'];
                }
                if (in_array('LL paid driver', $value)) {
                    $LLtoPaidDriverYN = '1';
                    $LLtoPaidDriver = $value['sumInsured'];
                }
                if (in_array('Geographical Extension', $value) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $geoExtension = '7';
                }
            }

            if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                $unnamed_passenger = 0;
                $unnamed_passenger_si = 0;
            }

            foreach ($accessories as $key => $value) {
                if (in_array('geoExtension', $value) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $geoExtension = '7';
                }
                if (in_array('Electrical Accessories', $value)) {
                    $electrical_accessories_si = $value['sumInsured'];
                }

                if (in_array('Non-Electrical Accessories', $value)) {
                    $non_electrical_accessories_si = $value['sumInsured'];
                }
            }

            foreach ($applicable_addons as $key => $value) {
                if (isset($value['name'])) {
                    if ($value['name'] == 'Zero Depreciation' && isset($value['premium']) && (int) $value['premium'] > 0) {
                        $is_zero_dep = '1';
                    }
                    if ($value['name'] == 'Road Side Assistance' && isset($value['premium'])) {
                        $is_rsa = '1';
                    }
                    if ($value['name'] == 'Return To Invoice' && isset($value['premium'])) {
                        $is_rti = '1';
                    }
                }
            }

            $bike_anti_theft = 'false';
            $voluntary_insurer_discounts = 0;
            $tppd_cover = 0;
            if (!empty($discounts)) {
                foreach ($discounts as $key => $value) {
                    if ($value['name'] == 'anti-theft device') {
                        $bike_anti_theft = 'true';
                    }
                    if (!empty($value['name']) && !empty($value['sumInsured']) && $value['name'] == 'voluntary_insurer_discounts') {
                        $voluntary_insurer_discounts = $value['sumInsured'];
                    }
                    if ($value['name'] == 'TPPD Cover') {
                        $tppd_cover = 6000;
                    }
                }
            }
            #voluntary deductible applicable only vehicle age less than 5 years
            if($bike_age > 5 && $voluntary_insurer_discounts > 0)
            {
                $voluntary_insurer_discounts = 0;
            }
            // salutaion
            if ($requestData->vehicle_owner_type == "I") {
                if (strtoupper($proposal->gender) == "MALE") {
                    $salutation = 'MR';
                } else {
                    if (strtoupper($proposal->gender) == "FEMALE" && $proposal->marital_status == "Single") {
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
            $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cpareason = '';
            if (!empty($prev_policy_details)) {
                $cpareason = isset($prev_policy_details->reason) ? $prev_policy_details->reason :'';
                $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp :'';
                $cPAPolicyNo =  isset($prev_policy_details->cpaPolicyNumber)? $prev_policy_details->cpaPolicyNumber : '';
                $cPASumInsured = isset($prev_policy_details->cpaSumInsured) ? $prev_policy_details->cpaSumInsured:'';
                $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y'):'';
                $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y'):'';
            }
            // CPA
            $vehicleDetails = $additional_details->vehicle;
            if ($vehicle_registration_number[0] == 'NEW') {
                $vehicle_registration_number[0] = '';
            }

            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos = false;
            $posp_email= '';
            $posp_name = '';
            $posp_unique_number = '';
            $posp_pan_number = '';
            $posp_aadhar_number = '';
            $posp_contact_number = '';

            $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('user_proposal_id',$proposal['user_proposal_id'])
                ->where('seller_type','P')
                ->first();

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
                if (config('HDFC_BIKE_V1_IS_NON_POS') != 'Y') {
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
                    $pos_code = $hdfc_pos_code;#config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
                }
            }
            elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' && $quote->idv <= 5000000){
                $is_pos = true;
                $pos_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
            }
            // token Generation
            // $transactionid = customEncrypt($enquiryId);
            $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
            $token = hdfcErgoGetToken($enquiryId, $transactionid, $productName, $ProductCode, 'proposal');

            if($token['status']) {
                $proposal_array = [
                    'TransactionID' => $transactionid,
                    'Policy_Details' => [
                        'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                        'ProposalDate' => date('d/m/Y'),
                        'BusinessType_Mandatary' => $BusinessType,
                        'VehicleModelCode' => $mmv_data->vehicle_model_code,
                        'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                        'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'YearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                        'RTOLocationCode' => $rto_location->rto_location_code,
                        'Vehicle_IDV' => $quote_log->idv,
                        'Registration_No' => $requestData->business_type == 'newbusiness' ? 'NEW' : $proposal->vehicale_registration_number,
                        'EngineNumber' => $proposal->engine_number,
                        'ChassisNumber' => $proposal->chassis_number,
                        'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                        'FinancierCode' => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                        'BranchName' => ($proposal->is_vehicle_finance == '1') ?  $proposal->hypothecation_city: '',
                    ],
                ];

                if ($requestData->business_type != 'newbusiness') {
                    $proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? $prev_policy_company : '';
                    $proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? $previous_ncb : '';
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ?  date('d/m/Y', strtotime($prev_policy_end_date)) : '' ;
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyClaim'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? (($requestData->is_claim == 'N') ? 'NO' : 'YES' ): '';
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyNo'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? $prev_policy_number : '';
                    $proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage' ) ? 'Comprehensive Package' : 'TP') : '';
                }
               /*  if($is_pos)
                {
                    $proposal_array['Req_POSP']['EMAILID'] = $posp_email;
                    $proposal_array['Req_POSP']['NAME'] = $posp_name;
                    $proposal_array['Req_POSP']['UNIQUE_CODE'] = $posp_unique_number;
                    $proposal_array['Req_POSP']['STATE'] = '';
                    $proposal_array['Req_POSP']['PAN_CARD'] = $posp_pan_number;
                    $proposal_array['Req_POSP']['ADHAAR_CARD'] = $posp_aadhar_number;
                    $proposal_array['Req_POSP']['NUM_MOBILE_NO'] = $posp_contact_number;
                } */
                if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                    $proposal_array['Req_TW_Multiyear'] = [
                        "VehicleClass" => $mmv_data->vehicle_class_code,
                        "DateOfRegistration" => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'POSP_CODE' => [],
                        'IsLimitedtoOwnPremises'=>'0',
                        'productcode'=>$ProductCode,
                        'ExtensionCountryCode' => $geoExtension,
                        'ExtensionCountryName' => '',#($geoExtension == 0) ? null : '',
                        'Effectivedrivinglicense' => ((!$od_only || $proposal->owner_type == 'I') && $cpa) ? 'false' : 'true',
                        'Owner_Driver_Nominee_Name' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                        'Owner_Driver_Nominee_Age' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                        'Owner_Driver_Nominee_Relationship' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                        'IsZeroDept_Cover' => $is_zero_dep,
                        'IsRTI_Cover' => $is_rti,
                        'IsEA_Cover' => $is_rsa,
                        'CPA_Tenure' => $cpa_tenure,
                        'Paiddriver' => $LLtoPaidDriverYN,
                        'PAPaiddriverSI' => $LLtoPaidDriver,
                        'NoofUnnamedPerson' => $unnamed_passenger,
                        'UnnamedPersonSI' => $unnamed_passenger_si,
                        'ElecticalAccessoryIDV'=> $electrical_accessories_si,
                        'NonElecticalAccessoryIDV'=> $non_electrical_accessories_si,
                        // 'Voluntary_Excess_Discount' => $voluntary_insurer_discounts,
                        // 'TPPDLimit' => $tppd_cover,as per #23856
                        'AntiTheftDiscFlag' => $bike_anti_theft,
                        'POLICY_TENURE' => 5,
                        'POLICY_TYPE' => $type_of_cover
                    ];
                } else {
                    $proposal_array['Req_TW'] = [
                            'POSP_CODE' => [],
                            'IsLimitedtoOwnPremises'=>'0',
                            'ExtensionCountryCode' => $geoExtension,
                            'ExtensionCountryName' => '',#($geoExtension == 0) ? null : '',
                            'Effectivedrivinglicense' => ((!$od_only || $proposal->owner_type == 'I') && $cpa) ? 'false' : 'true',
                            'Owner_Driver_Nominee_Name' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                            'Owner_Driver_Nominee_Age' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                            'Owner_Driver_Nominee_Relationship' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                            'IsZeroDept_Cover' => $is_zero_dep,
                            'IsRTI_Cover' => $is_rti,
                            'IsEA_Cover' => $is_rsa,
                            'CPA_Tenure' => $cpa_tenure,
                            'Paiddriver' => $LLtoPaidDriverYN,
                            'PAPaiddriverSI' => $LLtoPaidDriverYN,
                            'NoofUnnamedPerson' => $unnamed_passenger,
                            'UnnamedPersonSI' => $unnamed_passenger_si,
                            'ElecticalAccessoryIDV'=> $electrical_accessories_si,
                            'NonElecticalAccessoryIDV'=> $non_electrical_accessories_si,
                            // 'Voluntary_Excess_Discount' => $voluntary_insurer_discounts,
                            // 'TPPDLimit' => $tppd_cover,as per #23856
                            'AntiTheftDiscFlag' => $bike_anti_theft,
                            'POLICY_TENURE' => 1,
                            'POLICY_TYPE' => $type_of_cover
                    ];
                }
                if(!$is_pos)
                {
                    unset($proposal_array['Req_TW']['POSP_CODE']);
                    if(isset($proposal_array['Req_TW_Multiyear']['POSP_CODE']))
                    {
                        unset($proposal_array['Req_TW_Multiyear']['POSP_CODE']);
                    }
                }
                if($is_pos)
                {
                    $proposal_array['Req_TW']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
                    if(isset($proposal_array['Req_TW_Multiyear']['POSP_CODE']))
                    {
                        $proposal_array['Req_TW_Multiyear']['POSP_CODE'] =!empty($pos_code) ? $pos_code : [];
                    }
                }
                $additionData = [
                    'type' => 'withToken',
                    'method' => 'Premium Calculation',
                    'requestMethod' => 'post',
                    'section' => 'bike',
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'proposal',
                    'productName' => $productData->product_name. " ($business_type)",
                    'TOKEN' => $token['message'],
                    'PRODUCT_CODE' => $ProductCode,
                    'TRANSACTIONID' =>$transactionid,
                    'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
                    'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
                    'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
                ];
                
                if($requestData->previous_policy_type != 'Not sure' && $requestData->business_type != 'newbusiness' &&  empty($proposal->previous_policy_number) && empty($proposal->previous_insurance_company))
                {
                    return
                    [
                        'status' => false,
                        'message' => 'Previous policy number and previous insurer is mandetory if previous policy type is ' . $requestData->previous_policy_type
                    ];
                }

                if($requestData->previous_policy_type == 'Not sure') {
                    $proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = NULL;
                    // $proposal_array['Policy_Details']['PreviousPolicy_PolicyClaim'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyNo'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType'] = NULL;
                } 

                $get_response = getWsData(config('HDFC_ERGO_GIC_BIKE_CALCULATE_PREMIUM'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];

                $arr_premium = json_decode($getpremium, TRUE);
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

                if ($arr_premium['StatusCode'] == '200') {
                    $premium_data = $arr_premium['Resp_TW'];
                    $proposal->proposal_no = $arr_premium['TransactionID'];
                    $proposal->ic_vehicle_details = $vehicleDetails;
                    $proposal->save();
                    $basic_od_premium = $basic_tp_premium = 0;
                    $pa_unnamed = $pa_paid_driver = $pa_owner_driver = 0;
                    $zd_premium = $rsa_premium = $rti_premium = 0;
                    $electrical_accessories = $non_electrical_accessories = 0;
                    $ncb_discount = $tppd_discount = $voluntary_discount = 0;
                    $final_net_premium = $final_payable_amount = 0;
                    $GeogExtension_od= $GeogExtension_tp=$OwnPremises_OD= $OwnPremises_TP = 0;

                    if (!empty($premium_data['PAOwnerDriver_Premium'])) {
                        $pa_owner_driver = ($premium_data['PAOwnerDriver_Premium']);
                    } if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                        $zd_premium += ($premium_data['Vehicle_Base_ZD_Premium']);
                    } if (!empty($premium_data['EA_premium'])) {
                        $rsa_premium = ($premium_data['EA_premium']);
                    } if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                        $ncb_discount = ($premium_data['NCBBonusDisc_Premium']);
                    } if (!empty($premium_data['Vehicle_Base_RTI_premium_Premium'])) {
                        $rti_premium += ($premium_data['Vehicle_Base_RTI_Premium']);
                    } if (!empty($premium_data['UnnamedPerson_premium'])) {
                        $pa_unnamed = ($premium_data['UnnamedPerson_premium']);
                    } if (!empty($premium_data['Electical_Acc_Premium'])) {
                        $electrical_accessories = ($premium_data['Electical_Acc_Premium']);
                    } if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                        $non_electrical_accessories = ($premium_data['NonElectical_Acc_Premium']);
                    } if (!empty($premium_data['PAPaidDriver_Premium'])) {
                        $pa_paid_driver = ($premium_data['PAPaidDriver_Premium']);
                    } if (!empty($premium_data['Net_Premium'])) {
                        $final_net_premium = ($premium_data['Net_Premium']);
                    } if (!empty($premium_data['Total_Premium'])) {
                        $final_payable_amount = ($premium_data['Total_Premium']);
                    } if (!empty($premium_data['Basic_OD_Premium'])) {
                        $basic_od_premium = ($premium_data['Basic_OD_Premium']);
                    } if (!empty($premium_data['Basic_TP_Premium'])) {
                        $basic_tp_premium = ($premium_data['Basic_TP_Premium']);
                    } if (!empty($premium_data['VoluntartDisc_premium'])) {
                        $voluntary_discount = ($premium_data['VoluntartDisc_premium']);
                    } if (!empty($premium_data['TPPD_premium'])) {
                        $tppd_discount = ($premium_data['TPPD_premium']);
                    } if ($is_zero_dep == '1' && !empty($premium_data['Elec_ZD_Premium'])) {
                        $zd_premium += ($premium_data['Elec_ZD_Premium']);
                    } if ($is_zero_dep == '1' && !empty($premium_data['NonElec_ZD_Premium'])) {
                        $zd_premium += ($premium_data['NonElec_ZD_Premium']);
                    } if (!empty($premium_data['Elec_RTI_Premium'])) {
                        $rti_premium += ($premium_data['Elec_RTI_Premium']);
                    } if (!empty($premium_data['NonElec_RTI_Premium'])) {
                        $rti_premium += ($premium_data['NonElec_RTI_Premium']);
                    }
                    if (!empty($premium_data['GeogExtension_ODPremium'])) {
                        $GeogExtension_od = ($premium_data['GeogExtension_ODPremium']);
                    }
                    if (!empty($premium_data['GeogExtension_TPPremium'])) {
                        $GeogExtension_tp= ($premium_data['GeogExtension_TPPremium']);
                    }

                    if (!empty($premium_data['LimitedtoOwnPremises_OD_Premium'])) {
                        $OwnPremises_OD = ($premium_data['LimitedtoOwnPremises_OD_Premium']);
                    }
                    if (!empty($premium_data['LimitedtoOwnPremises_TP_Premium'])) {
                        $OwnPremises_TP = ($premium_data['LimitedtoOwnPremises_TP_Premium']);
                    }
                    $addon_premium = $zd_premium + $rsa_premium + $rti_premium;
                    $od_premium = $basic_od_premium + $electrical_accessories + $non_electrical_accessories+$GeogExtension_od+$OwnPremises_OD;
                    $tp_premium = $basic_tp_premium + $pa_owner_driver + $pa_paid_driver + $pa_unnamed+$GeogExtension_tp+$OwnPremises_TP;
                    $total_discount = $ncb_discount + $tppd_discount + $voluntary_discount;

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
                        'Customer_Salutation' => $salutation,
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

                    if ($od_only) {
                        $proposal_array['Policy_Details']['PreviousPolicy_TPENDDATE'] = $TPPolicyEndDate;
                        $proposal_array['Policy_Details']['PreviousPolicy_TPSTARTDATE'] = $TPPolicyStartDate;
                        $proposal_array['Policy_Details']['PreviousPolicy_TPINSURER'] = $TPPolicyInsurer;
                        $proposal_array['Policy_Details']['PreviousPolicy_TPPOLICYNO'] = $TPPolicyNumber;
                    }

                    //PROPOSAL SERVICE BEFORE PAYMENT NEW CODE START

                   $additionData = [
                        'type' => 'withToken',
                        'method' => 'Proposal Generation',
                        'requestMethod' => 'post',
                        'section' => 'bike',
                        'enquiryId' => $enquiryId,
                        'TOKEN' => $token['message'],
                        'transaction_type' => 'proposal',
                        'productName' => $productName,
                        'PRODUCT_CODE' => $ProductCode,
                        'TRANSACTIONID' =>$transactionid,
                        'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
                        'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
                        'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
                    ];
            
                    $get_response = getWsData(config('HDFC_ERGO_GIC_BIKE_GENERATE_POLICY'), $proposal_array, 'hdfc_ergo', $additionData);
                    $getpremium = $get_response['response'];
                    $arr_proposal = json_decode($getpremium, true);

                    if (isset($arr_proposal['StatusCode'] ) && $arr_proposal['StatusCode'] == '200') {
                        $proposal_data = $arr_proposal['Resp_TW'];
                        $proposal->proposal_no = $arr_proposal['TransactionID'];
                        $proposal->ic_vehicle_details = $vehicleDetails;
                        $proposal->save();
                        $basic_od_premium = $basic_tp_premium = 0;
                        $pa_unnamed = $pa_paid_driver = $pa_owner_driver = 0;
                        $zd_premium = $rsa_premium = $rti_premium = 0;
                        $electrical_accessories = $non_electrical_accessories = 0;
                        $ncb_discount = $tppd_discount = $voluntary_discount = 0;
                        $final_net_premium = $final_payable_amount = 0;
                        $GeogExtension_od= $GeogExtension_tp=$OwnPremises_OD= $OwnPremises_TP = 0;
    
                        if (!empty($proposal_data['PAOwnerDriver_Premium'])) {
                            $pa_owner_driver = ($proposal_data['PAOwnerDriver_Premium']);
                        } if (!empty($proposal_data['Vehicle_Base_ZD_Premium'])) {
                            $zd_premium += ($proposal_data['Vehicle_Base_ZD_Premium']);
                        } if (!empty($proposal_data['EA_premium'])) {
                            $rsa_premium = ($proposal_data['EA_premium']);
                        } if (!empty($proposal_data['NCBBonusDisc_Premium'])) {
                            $ncb_discount = ($proposal_data['NCBBonusDisc_Premium']);
                        } if (!empty($proposal_data['Vehicle_Base_RTI_premium_Premium'])) {
                            $rti_premium += ($proposal_data['Vehicle_Base_RTI_Premium']);
                        } if (!empty($proposal_data['UnnamedPerson_premium'])) {
                            $pa_unnamed = ($proposal_data['UnnamedPerson_premium']);
                        } if (!empty($proposal_data['Electical_Acc_Premium'])) {
                            $electrical_accessories = ($proposal_data['Electical_Acc_Premium']);
                        } if (!empty($proposal_data['NonElectical_Acc_Premium'])) {
                            $non_electrical_accessories = ($proposal_data['NonElectical_Acc_Premium']);
                        } if (!empty($proposal_data['PAPaidDriver_Premium'])) {
                            $pa_paid_driver = ($proposal_data['PAPaidDriver_Premium']);
                        } if (!empty($proposal_data['Net_Premium'])) {
                            $final_net_premium = ($proposal_data['Net_Premium']);
                        } if (!empty($proposal_data['Total_Premium'])) {
                            $final_payable_amount = ($proposal_data['Total_Premium']);
                        } if (!empty($proposal_data['Basic_OD_Premium'])) {
                            $basic_od_premium = ($proposal_data['Basic_OD_Premium']);
                        } if (!empty($proposal_data['Basic_TP_Premium'])) {
                            $basic_tp_premium = ($proposal_data['Basic_TP_Premium']);
                        } if (!empty($proposal_data['VoluntartDisc_premium'])) {
                            $voluntary_discount = ($proposal_data['VoluntartDisc_premium']);
                        } if (!empty($proposal_data['TPPD_premium'])) {
                            $tppd_discount = ($proposal_data['TPPD_premium']);
                        } if ($is_zero_dep == '1' && !empty($proposal_data['Elec_ZD_Premium'])) {
                            $zd_premium += ($proposal_data['Elec_ZD_Premium']);
                        } if ($is_zero_dep == '1' && !empty($proposal_data['NonElec_ZD_Premium'])) {
                            $zd_premium += ($proposal_data['NonElec_ZD_Premium']);
                        } if (!empty($proposal_data['Elec_RTI_Premium'])) {
                            $rti_premium += ($proposal_data['Elec_RTI_Premium']);
                        } if (!empty($proposal_data['NonElec_RTI_Premium'])) {
                            $rti_premium += ($proposal_data['NonElec_RTI_Premium']);
                        }
                        if (!empty($proposal_data['GeogExtension_ODPremium'])) {
                            $GeogExtension_od = ($proposal_data['GeogExtension_ODPremium']);
                        }
                        if (!empty($proposal_data['GeogExtension_TPPremium'])) {
                            $GeogExtension_tp= ($proposal_data['GeogExtension_TPPremium']);
                        }
    
                        if (!empty($proposal_data['LimitedtoOwnPremises_OD_Premium'])) {
                            $OwnPremises_OD = ($proposal_data['LimitedtoOwnPremises_OD_Premium']);
                        }
                        if (!empty($proposal_data['LimitedtoOwnPremises_TP_Premium'])) {
                            $OwnPremises_TP = ($proposal_data['LimitedtoOwnPremises_TP_Premium']);
                        }
                        $addon_premium = $zd_premium + $rsa_premium + $rti_premium;
                        $od_discount = $ncb_discount + $voluntary_discount;
                        $od_premium = $basic_od_premium + $electrical_accessories + $non_electrical_accessories+$GeogExtension_od+$OwnPremises_OD - $od_discount;
                        $tp_premium = $basic_tp_premium + $pa_owner_driver + $pa_paid_driver + $pa_unnamed+$GeogExtension_tp+$OwnPremises_TP - $tppd_discount;
                        $total_discount = $ncb_discount + $tppd_discount + $voluntary_discount;
    
                        UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                            'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                            'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
                            'proposal_no' => $proposal->proposal_no,
                            'unique_proposal_id' => $proposal->proposal_no,
                            'product_code' => $ProductCode,
                            'od_premium' => $od_premium,
                            'tp_premium' => $tp_premium,
                            'addon_premium' => $addon_premium,
                            'cpa_premium' => $pa_owner_driver,
                            'final_premium' => ($final_net_premium),
                            'total_premium' => ($final_net_premium),
                            'service_tax_amount' => ($proposal_data['Service_Tax']),
                            'final_payable_amount' => ($final_payable_amount),
                            'customer_id' => '',
                            'ic_vehicle_details' => json_encode($vehicleDetails),
                            'ncb_discount' => $ncb_discount,
                            'total_discount' => $total_discount,
                            'cpa_ins_comp' => $cPAInsComp,
                            'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                            'cpa_policy_no' => $cPAPolicyNo,
                            'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                            'cpa_sum_insured' => $cPASumInsured,
                            'electrical_accessories' =>  $electrical_accessories_si,
                            'non_electrical_accessories' => $non_electrical_accessories_si,
                            'additional_details_data' => base64_encode(json_encode($proposal_array))
                        ]);

                        $data['user_product_journey_id'] = $enquiryId;
                        $data['ic_id'] = $master_policy->insurance_company_id;
                        $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                        $data['proposal_id'] = $proposal->user_proposal_id;
                        updateJourneyStage($data);

                        HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

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
                                'is_breakin' => '',
                                'inspection_number' => ''
                            ]
                        ]);

                    }else
                    {
                        if($arr_proposal['Error'] == 'PAYMENT DETAILS NOT FOUND !')
                        {
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
                                    'is_breakin' => '',
                                    'inspection_number' => ''
                                ]
                            ]);

                        }else
                        {
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => $arr_proposal['Error'],
                            ]);
                        }
                    }

                // PROPOSAL SERVICE BEFORE PAYMENT NEW CODE END
                } else {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => isset($arr_premium['ErrorText']) ? $arr_premium['ErrorText'] : $arr_premium['Error'],
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => $token['message'],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Premium Service Issue ' . $e->getMessage(),

            ]);
        }

    }

    public static function submitV1NewFlow($proposal, $request)
    {
        try {
            $enquiryId = customDecrypt($request['enquiryId']);
            $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $requestData = getQuotation($enquiryId);
            $productData = getProductDataByIc($request['policyId']);
            $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
            $corporate_vehicle_quotes_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();
            // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
            // {
            //     return  response()->json([
            //         'status' => false,
            //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            //     ]);
            // }
            $master_policy = MasterPolicy::find($request['policyId']);

            if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
                $policy_start_date = date('d/m/Y');
            } elseif ($requestData->business_type == 'rollover') {
                $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }
            $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
            $mmv = get_mmv_details($productData,$requestData->version_id,'hdfc_ergo');
            if($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
            $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
            $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
            $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code
            $rto_location =HdfcErgoBikeRtoLocation::where('rto_code', $rto_code)
                ->first();

            $bike_age = 0;
            $prev_policy_end_date = (empty($requestData->previous_policy_expiry_date) || $requestData->previous_policy_expiry_date == 'New') ? date('Y-m-d') : date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $date1 = new \DateTime($vehicleDate);
            $date2 = new \DateTime($prev_policy_end_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $bike_age = ceil($age / 12);

            $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
            $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
            $od_only = ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') ? true : false;
            $TPPolicyNumber = '';
            $TPPolicyInsurer = '';
            $TPPolicyStartDate = '';
            $TPPolicyEndDate = '';
            $prev_policy_number = '';
            $prev_policy_company = '';
            $is_previous_claim = $requestData->is_claim == 'Y' ? true : false;
            $applicable_ncb = $is_previous_claim ? 0 : $requestData->applicable_ncb;
            $previous_ncb = $is_previous_claim ? 0 : $requestData->previous_ncb;
            $ProductCode = '2312';
            $type_of_cover = 'OD Plus TP';
            $policyType = 'Comprehensive';
            $cpa_tenure = '1';

            if ($requestData->business_type == 'newbusiness') {
                $business_type = 'New Business';
                $BusinessType = 'New Vehicle';
                $cpa_tenure = '5';
                $policy_start_date = date('Y-m-d');
                // $prev_policy_end_date = '';
                if ($premium_type == 'comprehensive') {
                    $policy_end_date =  today()->addYear(1)->subDay(1)->format('d/m/Y');
                } elseif ($premium_type == 'third_party') {
                    $policy_end_date =   today()->addYear(5)->subDay(1)->format('d/m/Y');
                }
            } else if ($requestData->business_type == 'rollover') {
                $business_type = 'Roll Over';
                $BusinessType = 'Roll Over';
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($prev_policy_end_date)));
                $prev_policy_number = $proposal->previous_policy_number;
                $prev_policy_company = $proposal->previous_insurance_company;
            } else if ($requestData->business_type == 'breakin') {
                $business_type = 'Break-In';
                $BusinessType = 'Roll Over';
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
                $prev_policy_number = $proposal->previous_policy_number;
                $prev_policy_company = $proposal->previous_insurance_company;
            }

            if ($tp_only) {
                $ProductCode = '2320';
                $policyType = 'Third Party';
                $type_of_cover = '';
                $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
            } else if ($od_only){
                $policyType = 'Own Damage';
                $type_of_cover = 'OD Only';
                $TPPolicyNumber = $proposal->tp_insurance_number;
                $TPPolicyInsurer = $proposal->tp_insurance_company;
                $TPPolicyStartDate = date('d/m/Y', strtotime($proposal->tp_start_date));
                $TPPolicyEndDate = date('d/m/Y', strtotime($proposal->tp_end_date));
            }
                if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                    $ProductCode = '2367';
                }

            $productName = $productData->product_name. " ($business_type)";
            if ($requestData->business_type != 'newbusiness' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
                $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
                if ($date_difference > 0) {
                    $policy_start_date = date('Y-m-d', strtotime('+3 day'));
                }

                if($date_difference > 90){
                    $applicable_ncb = 0;
                }
            }

            if ($requestData->business_type != 'newbusiness' && in_array($requestData->previous_policy_type, ['Not sure'])) {
                $policy_start_date = date('Y-m-d', strtotime('+3 day'));
                $prev_policy_end_date = date('Y-m-d', strtotime('-120 days'));
                $applicable_ncb = 0;
            }
            if(in_array($premium_type, ['breakin', 'own_damage_breakin','third_party_breakin']))
            {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }

            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('Y-m-d');
            $vehicle_registration_number = explode('-', $proposal->vehicale_registration_number);
            $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $applicable_addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
            $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
            $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
            $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers); // new business
            $cpa_cover = ($selected_addons->compulsory_personal_accident == null ? [] : $selected_addons->compulsory_personal_accident); // new business
            $additional_details = json_decode($proposal->additional_details);
            $is_zero_dep = $is_rsa = $is_rti = $electrical_accessories_si = $non_electrical_accessories_si = $unnamed_passenger = $unnamed_passenger_si = $paid_driver = $paid_driver_si = $LLtoPaidDriverYN = $LLtoPaidDriver = '0';
            $geoExtension="0";
            $cpa = false;
            if (!empty($cpa_cover) && $requestData->vehicle_owner_type == 'I') {
                foreach ($cpa_cover as $key => $value)  {
                    if (isset($value['name']) && $value['name'] == 'Compulsory Personal Accident')  {
                        $cpa = true;
                        $cpa_tenure = isset($value['tenure']) ? (string) $value['tenure'] :'1';
                    }
                }
            }

            foreach($additional_covers as $key => $value) {

                if (in_array('PA cover for additional paid driver', $value)) {
                    $paid_driver = 1;
                    $paid_driver_si = $value['sumInsured'];
                }

                if (in_array('Unnamed Passenger PA Cover', $value)) {
                    $unnamed_passenger = 1;
                    $unnamed_passenger_si = $value['sumInsured'];
                }
                if (in_array('LL paid driver', $value)) {
                    $LLtoPaidDriverYN = '1';
                    $LLtoPaidDriver = $value['sumInsured'];
                }
                if (in_array('Geographical Extension', $value) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $geoExtension = '7';
                }
            }

            if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                $unnamed_passenger = 0;
                $unnamed_passenger_si = 0;
            }

            foreach ($accessories as $key => $value) {
                if (in_array('geoExtension', $value) && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $geoExtension = '7';
                }
                if (in_array('Electrical Accessories', $value)) {
                    $electrical_accessories_si = $value['sumInsured'];
                }

                if (in_array('Non-Electrical Accessories', $value)) {
                    $non_electrical_accessories_si = $value['sumInsured'];
                }
            }

            foreach ($applicable_addons as $key => $value) {
                if (isset($value['name'])) {
                    if ($value['name'] == 'Zero Depreciation' && isset($value['premium']) && (int) $value['premium'] > 0) {
                        $is_zero_dep = '1';
                    }
                    if ($value['name'] == 'Road Side Assistance' && isset($value['premium'])) {
                        $is_rsa = '1';
                    }
                    if ($value['name'] == 'Return To Invoice' && isset($value['premium'])) {
                        $is_rti = '1';
                    }
                }
            }

            $bike_anti_theft = 'false';
            $voluntary_insurer_discounts = 0;
            $tppd_cover = 0;
            if (!empty($discounts)) {
                foreach ($discounts as $key => $value) {
                    if ($value['name'] == 'anti-theft device') {
                        $bike_anti_theft = 'true';
                    }
                    if (!empty($value['name']) && !empty($value['sumInsured']) && $value['name'] == 'voluntary_insurer_discounts') {
                        $voluntary_insurer_discounts = $value['sumInsured'];
                    }
                    if ($value['name'] == 'TPPD Cover') {
                        $tppd_cover = 6000;
                    }
                }
            }
            #voluntary deductible applicable only vehicle age less than 5 years
            if($bike_age > 5 && $voluntary_insurer_discounts > 0)
            {
                $voluntary_insurer_discounts = 0;
            }
            // salutaion
            if ($requestData->vehicle_owner_type == "I") {
                if (strtoupper($proposal->gender) == "MALE") {
                    $salutation = 'MR';
                } else {
                    if (strtoupper($proposal->gender) == "FEMALE" && $proposal->marital_status == "Single") {
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
            $cPAInsComp = $cPAPolicyNo = $cPASumInsured = $cPAPolicyFmDt = $cPAPolicyToDt = $cpareason = '';
            if (!empty($prev_policy_details)) {
                $cpareason = isset($prev_policy_details->reason) ? $prev_policy_details->reason :'';
                $cPAInsComp = isset($prev_policy_details->cPAInsComp) ? $prev_policy_details->cPAInsComp :'';
                $cPAPolicyNo =  isset($prev_policy_details->cpaPolicyNumber)? $prev_policy_details->cpaPolicyNumber : '';
                $cPASumInsured = isset($prev_policy_details->cpaSumInsured) ? $prev_policy_details->cpaSumInsured:'';
                $cPAPolicyFmDt = isset($prev_policy_details->cpaPolicyStartDate) ? Carbon::parse($prev_policy_details->cpaPolicyStartDate)->format('d/m/Y'):'';
                $cPAPolicyToDt = isset($prev_policy_details->cPAPolicyToDt) ? Carbon::parse($prev_policy_details->cPAPolicyToDt)->format('d/m/Y'):'';
            }
            // CPA
            $vehicleDetails = $additional_details->vehicle;
            if ($vehicle_registration_number[0] == 'NEW') {
                $vehicle_registration_number[0] = '';
            }

            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos = false;
            $posp_email= '';
            $posp_name = '';
            $posp_unique_number = '';
            $posp_pan_number = '';
            $posp_aadhar_number = '';
            $posp_contact_number = '';

            $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('user_proposal_id',$proposal['user_proposal_id'])
                ->where('seller_type','P')
                ->first();

            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
                if (config('HDFC_BIKE_V1_IS_NON_POS') != 'Y') {
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
                    $pos_code = $hdfc_pos_code;#config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
                }
            }
            elseif(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' && $quote->idv <= 5000000){
                $is_pos = true;
                $pos_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_POS_CODE');
            }
            // token Generation
            // $transactionid = customEncrypt($enquiryId);
            $transactionid = substr(str_shuffle('1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 10);
            $token = hdfcErgoGetToken($enquiryId, $transactionid, $productName, $ProductCode, 'proposal');

            if($token['status']) {
                $proposal_array = [
                    'TransactionID' => $transactionid,
                    'Policy_Details' => [
                        'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
                        'ProposalDate' => date('d/m/Y'),
                        'BusinessType_Mandatary' => $BusinessType,
                        'VehicleModelCode' => $mmv_data->vehicle_model_code,
                        'DateofDeliveryOrRegistration' => date('d/m/Y', strtotime($vehicleDate)),
                        'DateofFirstRegistration' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'YearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                        'RTOLocationCode' => $rto_location->rto_location_code,
                        'Vehicle_IDV' => $quote_log->idv,
                        'Registration_No' => $requestData->business_type == 'newbusiness' ? 'NEW' : $proposal->vehicale_registration_number,
                        'EngineNumber' => $proposal->engine_number,
                        'ChassisNumber' => $proposal->chassis_number,
                        'AgreementType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                        'FinancierCode' => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                        'BranchName' => ($proposal->is_vehicle_finance == '1') ?  $proposal->hypothecation_city: '',
                    ],
                ];

                if ($requestData->business_type != 'newbusiness') {
                    $proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? $prev_policy_company : '';
                    $proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? $previous_ncb : '';
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ?  date('d/m/Y', strtotime($prev_policy_end_date)) : '' ;
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyClaim'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? (($requestData->is_claim == 'N') ? 'NO' : 'YES' ): '';
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyNo'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? $prev_policy_number : '';
                    $proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType'] = ($corporate_vehicle_quotes_request->previous_policy_type !== 'Not sure') ? (($requestData->previous_policy_type == 'Comprehensive' || $requestData->previous_policy_type == 'Own-damage' ) ? 'Comprehensive Package' : 'TP') : '';
                }
               /*  if($is_pos)
                {
                    $proposal_array['Req_POSP']['EMAILID'] = $posp_email;
                    $proposal_array['Req_POSP']['NAME'] = $posp_name;
                    $proposal_array['Req_POSP']['UNIQUE_CODE'] = $posp_unique_number;
                    $proposal_array['Req_POSP']['STATE'] = '';
                    $proposal_array['Req_POSP']['PAN_CARD'] = $posp_pan_number;
                    $proposal_array['Req_POSP']['ADHAAR_CARD'] = $posp_aadhar_number;
                    $proposal_array['Req_POSP']['NUM_MOBILE_NO'] = $posp_contact_number;
                } */
                if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') {
                    $proposal_array['Req_TW_Multiyear'] = [
                        "VehicleClass" => $mmv_data->vehicle_class_code,
                        "DateOfRegistration" => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'POSP_CODE' => [],
                        'IsLimitedtoOwnPremises'=>'0',
                        'productcode'=>$ProductCode,
                        'ExtensionCountryCode' => $geoExtension,
                        'ExtensionCountryName' => '',#($geoExtension == 0) ? null : '',
                        'Effectivedrivinglicense' => ((!$od_only || $proposal->owner_type == 'I') && $cpa) ? 'false' : 'true',
                        'Owner_Driver_Nominee_Name' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                        'Owner_Driver_Nominee_Age' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                        'Owner_Driver_Nominee_Relationship' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                        'IsZeroDept_Cover' => $is_zero_dep,
                        'IsRTI_Cover' => $is_rti,
                        'IsEA_Cover' => $is_rsa,
                        'CPA_Tenure' => $cpa_tenure,
                        'Paiddriver' => $LLtoPaidDriverYN,
                        'PAPaiddriverSI' => $LLtoPaidDriver,
                        'NoofUnnamedPerson' => $unnamed_passenger,
                        'UnnamedPersonSI' => $unnamed_passenger_si,
                        'ElecticalAccessoryIDV'=> $electrical_accessories_si,
                        'NonElecticalAccessoryIDV'=> $non_electrical_accessories_si,
                        // 'Voluntary_Excess_Discount' => $voluntary_insurer_discounts,
                        // 'TPPDLimit' => $tppd_cover,as per #23856
                        'AntiTheftDiscFlag' => false,
                        'POLICY_TENURE' => 5,
                        'POLICY_TYPE' => $type_of_cover
                    ];
                } else {
                    $proposal_array['Req_TW'] = [
                            'POSP_CODE' => [],
                            'IsLimitedtoOwnPremises'=>'0',
                            'ExtensionCountryCode' => $geoExtension,
                            'ExtensionCountryName' => '',#($geoExtension == 0) ? null : '',
                            'Effectivedrivinglicense' => ((!$od_only || $proposal->owner_type == 'I') && $cpa) ? 'false' : 'true',
                            'Owner_Driver_Nominee_Name' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_name : "0",
                            'Owner_Driver_Nominee_Age' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_age : "0",
                            'Owner_Driver_Nominee_Relationship' => (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
                            'IsZeroDept_Cover' => $is_zero_dep,
                            'IsRTI_Cover' => $is_rti,
                            'IsEA_Cover' => $is_rsa,
                            'CPA_Tenure' => $cpa_tenure,
                            'Paiddriver' => $LLtoPaidDriverYN,
                            'PAPaiddriverSI' => $LLtoPaidDriverYN,
                            'NoofUnnamedPerson' => $unnamed_passenger,
                            'UnnamedPersonSI' => $unnamed_passenger_si,
                            'ElecticalAccessoryIDV'=> $electrical_accessories_si,
                            'NonElecticalAccessoryIDV'=> $non_electrical_accessories_si,
                            // 'Voluntary_Excess_Discount' => $voluntary_insurer_discounts,
                            // 'TPPDLimit' => $tppd_cover,as per #23856
                            'AntiTheftDiscFlag' => false,
                            'POLICY_TENURE' => 1,
                            'POLICY_TYPE' => $type_of_cover
                    ];
                }
                if(!$is_pos)
                {
                    unset($proposal_array['Req_TW']['POSP_CODE']);
                    if(isset($proposal_array['Req_TW_Multiyear']['POSP_CODE']))
                    {
                        unset($proposal_array['Req_TW_Multiyear']['POSP_CODE']);
                    }
                }
                if($is_pos)
                {
                    $proposal_array['Req_TW']['POSP_CODE'] = !empty($pos_code) ? $pos_code : [];
                    if(isset($proposal_array['Req_TW_Multiyear']['POSP_CODE']))
                    {
                        $proposal_array['Req_TW_Multiyear']['POSP_CODE'] =!empty($pos_code) ? $pos_code : [];
                    }
                }
                $additionData = [
                    'type' => 'withToken',
                    'method' => 'Premium Calculation',
                    'requestMethod' => 'post',
                    'section' => 'bike',
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'proposal',
                    'productName' => $productData->product_name. " ($business_type)",
                    'TOKEN' => $token['message'],
                    'PRODUCT_CODE' => $ProductCode,
                    'TRANSACTIONID' =>$transactionid,
                    'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
                    'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
                    'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
                ];
                
                if($requestData->previous_policy_type != 'Not sure' && $requestData->business_type != 'newbusiness' &&  empty($proposal->previous_policy_number) && empty($proposal->previous_insurance_company))
                {
                    return
                    [
                        'status' => false,
                        'message' => 'Previous policy number and previous insurer is mandetory if previous policy type is ' . $requestData->previous_policy_type
                    ];
                }

                if($requestData->previous_policy_type == 'Not sure') {
                    $proposal_array['Policy_Details']['PreviousPolicy_CorporateCustomerId_Mandatary'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_NCBPercentage'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyEndDate'] = NULL;
                    // $proposal_array['Policy_Details']['PreviousPolicy_PolicyClaim'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_PolicyNo'] = NULL;
                    $proposal_array['Policy_Details']['PreviousPolicy_PreviousPolicyType'] = NULL;
                } 

                $get_response = getWsData(config('HDFC_ERGO_GIC_BIKE_CALCULATE_PREMIUM'), $proposal_array, 'hdfc_ergo', $additionData);
                $getpremium = $get_response['response'];

                $arr_premium = json_decode($getpremium, TRUE);
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
                    $premium_data = $arr_premium['Resp_TW'];
                    $proposal->proposal_no = $arr_premium['TransactionID'];
                    $proposal->ic_vehicle_details = $vehicleDetails;
                    $proposal->save();
                    $basic_od_premium = $basic_tp_premium = 0;
                    $pa_unnamed = $pa_paid_driver = $pa_owner_driver = 0;
                    $zd_premium = $rsa_premium = $rti_premium = 0;
                    $electrical_accessories = $non_electrical_accessories = 0;
                    $ncb_discount = $tppd_discount = $voluntary_discount = 0;
                    $final_net_premium = $final_payable_amount = 0;
                    $GeogExtension_od= $GeogExtension_tp=$OwnPremises_OD= $OwnPremises_TP = 0;

                    if (!empty($premium_data['PAOwnerDriver_Premium'])) {
                        $pa_owner_driver = ($premium_data['PAOwnerDriver_Premium']);
                    } if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                        $zd_premium += ($premium_data['Vehicle_Base_ZD_Premium']);
                    } if (!empty($premium_data['EA_premium'])) {
                        $rsa_premium = ($premium_data['EA_premium']);
                    } if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                        $ncb_discount = ($premium_data['NCBBonusDisc_Premium']);
                    } if (!empty($premium_data['Vehicle_Base_RTI_premium_Premium'])) {
                        $rti_premium += ($premium_data['Vehicle_Base_RTI_Premium']);
                    } if (!empty($premium_data['UnnamedPerson_premium'])) {
                        $pa_unnamed = ($premium_data['UnnamedPerson_premium']);
                    } if (!empty($premium_data['Electical_Acc_Premium'])) {
                        $electrical_accessories = ($premium_data['Electical_Acc_Premium']);
                    } if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                        $non_electrical_accessories = ($premium_data['NonElectical_Acc_Premium']);
                    } if (!empty($premium_data['PAPaidDriver_Premium'])) {
                        $pa_paid_driver = ($premium_data['PAPaidDriver_Premium']);
                    } if (!empty($premium_data['Net_Premium'])) {
                        $final_net_premium = ($premium_data['Net_Premium']);
                    } if (!empty($premium_data['Total_Premium'])) {
                        $final_payable_amount = ($premium_data['Total_Premium']);
                    } if (!empty($premium_data['Basic_OD_Premium'])) {
                        $basic_od_premium = ($premium_data['Basic_OD_Premium']);
                    } if (!empty($premium_data['Basic_TP_Premium'])) {
                        $basic_tp_premium = ($premium_data['Basic_TP_Premium']);
                    } if (!empty($premium_data['VoluntartDisc_premium'])) {
                        $voluntary_discount = ($premium_data['VoluntartDisc_premium']);
                    } if (!empty($premium_data['TPPD_premium'])) {
                        $tppd_discount = ($premium_data['TPPD_premium']);
                    } if ($is_zero_dep == '1' && !empty($premium_data['Elec_ZD_Premium'])) {
                        $zd_premium += ($premium_data['Elec_ZD_Premium']);
                    } if ($is_zero_dep == '1' && !empty($premium_data['NonElec_ZD_Premium'])) {
                        $zd_premium += ($premium_data['NonElec_ZD_Premium']);
                    } if (!empty($premium_data['Elec_RTI_Premium'])) {
                        $rti_premium += ($premium_data['Elec_RTI_Premium']);
                    } if (!empty($premium_data['NonElec_RTI_Premium'])) {
                        $rti_premium += ($premium_data['NonElec_RTI_Premium']);
                    }
                    if (!empty($premium_data['GeogExtension_ODPremium'])) {
                        $GeogExtension_od = ($premium_data['GeogExtension_ODPremium']);
                    }
                    if (!empty($premium_data['GeogExtension_TPPremium'])) {
                        $GeogExtension_tp= ($premium_data['GeogExtension_TPPremium']);
                    }

                    if (!empty($premium_data['LimitedtoOwnPremises_OD_Premium'])) {
                        $OwnPremises_OD = ($premium_data['LimitedtoOwnPremises_OD_Premium']);
                    }
                    if (!empty($premium_data['LimitedtoOwnPremises_TP_Premium'])) {
                        $OwnPremises_TP = ($premium_data['LimitedtoOwnPremises_TP_Premium']);
                    }
                    $addon_premium = $zd_premium + $rsa_premium + $rti_premium;
                    $od_premium = $basic_od_premium + $electrical_accessories + $non_electrical_accessories+$GeogExtension_od+$OwnPremises_OD;
                    $tp_premium = $basic_tp_premium + $pa_owner_driver + $pa_paid_driver + $pa_unnamed+$GeogExtension_tp+$OwnPremises_TP;
                    $od_discount = $ncb_discount + $voluntary_discount;
                    $total_discount = $od_discount + $tppd_discount;

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
                        'Customer_Salutation' => $salutation,
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

                    if ($od_only) {
                        $proposal_array['Policy_Details']['PreviousPolicy_TPENDDATE'] = $TPPolicyEndDate;
                        $proposal_array['Policy_Details']['PreviousPolicy_TPSTARTDATE'] = $TPPolicyStartDate;
                        $proposal_array['Policy_Details']['PreviousPolicy_TPINSURER'] = $TPPolicyInsurer;
                        $proposal_array['Policy_Details']['PreviousPolicy_TPPOLICYNO'] = $TPPolicyNumber;
                    }

                    $additionData = [
                        'type' => 'withToken',
                        'method' => 'Proposal Generation',
                        'requestMethod' => 'post',
                        'section' => 'bike',
                        'enquiryId' => $enquiryId,
                        'TOKEN' => $token['message'],
                        'transaction_type' => 'proposal',
                        'productName' => $productName,
                        'PRODUCT_CODE' => $ProductCode,
                        'TRANSACTIONID' =>$transactionid,
                        'SOURCE' => config('HDFC_ERGO_GIC_BIKE_SOURCE_ID'),
                        'CHANNEL_ID' => config('HDFC_ERGO_GIC_BIKE_CHANNEL_ID'),
                        'CREDENTIAL' => config('HDFC_ERGO_GIC_BIKE_CREDENTIAL'),
                    ];

                    HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

                    $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_BIKE_CREATE_PROPOSAL'), $proposal_array, 'hdfc_ergo', $additionData);
                    $getpremium = $get_response['response'];
                    $arr_proposal = json_decode($getpremium, true);

                    if ($arr_proposal['StatusCode'] != 200 )
                    {
                        return response()->json([
                            'premium_amount' => 0,
                            'status' => false,
                            'msg' => $arr_premium['Error'],
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                        ]);

                    }

                    if($arr_proposal['Policy_Details']['ProposalNumber'] == null){
                        return response()->json([
                            'status' => false,
                            'msg' => "The proposal number cannot have a null value",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                        ]);
                    }
                    //GET CIS DOCUMENT API
                    if(config('IC.HDFC_ERGO.CIS_DOCUMENT_ENABLE') == 'Y'){
                        if(!empty($arr_proposal['Policy_Details']['ProposalNumber'])){
                            $get_cis_document_array = [
                                'TransactionID' => $transactionid,
                                'Req_Policy_Document' => [
                                    'Proposal_Number' => $arr_proposal['Policy_Details']['ProposalNumber'] ?? null,
                                ],
                            ];
    
                            $additionData['method'] = 'Get CIS Document';
                            $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_MOTOR_GIC_CREATE_CIS_DOCUMENT'), $get_cis_document_array, 'hdfc_ergo', $additionData);
                            $cis_doc_resp = json_decode($get_response['response']);
                            $pdfData = base64_decode($cis_doc_resp->Resp_Policy_Document->PDF_BYTES);
                            if (checkValidPDFData($pdfData)) {
                                Storage::put(config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'hdfc_ergo/' . md5($proposal->user_proposal_id) .'_cis' .'.pdf', $pdfData);
    
                                // $pdf_url = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf';
                                ProposalExtraFields::insert([
                                    'enquiry_id' => $enquiryId,
                                    'cis_url'    => config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL') . 'hdfc_ergo/'. md5($proposal->user_proposal_id) .'_cis' . '.pdf'
                                ]);
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

                    UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update([
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                            'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                            'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
                            'proposal_no' => $arr_proposal['Policy_Details']['ProposalNumber'],
                            'unique_proposal_id' => $arr_proposal['Policy_Details']['ProposalNumber'],
                            'product_code' => $ProductCode,
                            'od_premium' => ($od_premium) - ($od_discount),
                            'tp_premium' => $tp_premium,
                            'addon_premium' => $addon_premium,
                            'cpa_premium' => $pa_owner_driver,
                            'final_premium' => ($final_net_premium),
                            'total_premium' => ($final_net_premium),
                            'service_tax_amount' => ($premium_data['Service_Tax']),
                            'final_payable_amount' => ($final_payable_amount),
                            'customer_id' => '',
                            'ic_vehicle_details' => json_encode($vehicleDetails),
                            'ncb_discount' => $ncb_discount,
                            'total_discount' => $total_discount,
                            'cpa_ins_comp' => $cPAInsComp,
                            'cpa_policy_fm_dt' => str_replace('/', '-', $cPAPolicyFmDt),
                            'cpa_policy_no' => $cPAPolicyNo,
                            'cpa_policy_to_dt' => str_replace('/', '-', $cPAPolicyToDt),
                            'cpa_sum_insured' => $cPASumInsured,
                            'electrical_accessories' =>  $electrical_accessories_si,
                            'non_electrical_accessories' => $non_electrical_accessories_si,
                            'additional_details_data' => base64_encode(json_encode($proposal_array))
                        ]);

                    $data['user_product_journey_id'] = $enquiryId;
                    $data['ic_id'] = $master_policy->insurance_company_id;
                    $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
                    $data['proposal_id'] = $proposal->user_proposal_id;
                    updateJourneyStage($data);

                    //PROPOSAL SERVICE BEFORE PAYMENT NEW CODE START

                
                    if ($arr_proposal['StatusCode'] == 200 )
                    {
                        return response()->json([
                            'status' => true,
                            'msg' => $arr_premium['Error'] ?? "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $data['user_product_journey_id'],
                                'proposalNo' => $arr_proposal['Policy_Details']['ProposalNumber'] ?? null,
                                'finalPayableAmount' => $final_payable_amount,
                                'is_breakin' => '',
                                'inspection_number' => ''
                            ]
                        ]);

                    }else
                    {
                        if($arr_proposal['Error'] == 'PAYMENT DETAILS NOT FOUND !')
                        {
                            return response()->json([
                                'status' => true,
                                'msg' => $arr_premium['Error'],
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'data' => [
                                    'proposalId' => $proposal->user_proposal_id,
                                    'userProductJourneyId' => $data['user_product_journey_id'],
                                    'proposalNo' => $arr_proposal['Policy_Details']['ProposalNumber'],
                                    'finalPayableAmount' => $final_payable_amount,
                                    'is_breakin' => '',
                                    'inspection_number' => ''
                                ]
                            ]);

                        }else
                        {
                            return response()->json([
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => $arr_proposal['Error'],
                            ]);
                        }
                    }

                // PROPOSAL SERVICE BEFORE PAYMENT NEW CODE END
                } else {
                    return response()->json([
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => isset($arr_premium['ErrorText']) ? $arr_premium['ErrorText'] : (isset($arr_premium['Error']) ? $arr_premium['Error'] : "Premium service issue"),
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => $token['message'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Premium Service Issue ' . $e->getMessage(),

            ]);
        }

    }

    public static function submitV2($proposal, $request)
    {
        // dd($proposal);
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
        $rto_data = HdfcErgoBikeRtoLocation::where('rto_code', $rto_code)->first();
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

        if ($requestData->business_type == 'rollover')
        {
            $business_type = 'ROLLOVER';
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
            $policy_start_date = $tp_only ? date('d-m-Y', strtotime('+3 day', time())) : date('d-m-Y', strtotime('tomorrow'));
        }

        if ($requestData->ownership_changed == 'Y' || in_array($requestData->previous_policy_type, ['Not sure']))
        {
            $business_type = 'USED';
            $policy_start_date = $tp_only ? date('d-m-Y', strtotime('+3 day', time())) : date('d-m-Y', strtotime('tomorrow'));
        }

        $policy_end_date = date('d-m-Y', strtotime('+1 year -1 day', strtotime(str_replace('/', '-', $policy_start_date))));

        $car_age = 0;
        $date1 = new \DateTime($requestData->vehicle_register_date);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = floor($age / 12);

        if ($requestData->policy_type == 'comprehensive')
        {
            $policy_type = 'Comprehensive';
        }
        elseif ($requestData->policy_type == 'own_damage')
        {
            $policy_type = 'OwnDamage';
        }

        $pa_unnamed_passenger_sa = 0;
        $is_ll_paid_driver = 'NO';
        $is_tppd_discount = 'NO';
        $is_zero_dep = 'NO';
        $is_roadside_assistance = 'NO';
        $is_engine_protector = '';
        $is_cpa = false;

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)
            ->first();

        // dd($selected_addons);
        if ($selected_addons)
        {
            if ($selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '')
            {
                foreach ($selected_addons['additional_covers'] as $additional_cover)
                {
                    if ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                    {
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
                        $is_tppd_discount = 'YES';
                    }
                }
            }
            $is_tppd_discount = 'NO'; // UW Criteria from Base Service : Restricted Cover of TPPD is not allowed
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

                    if ($applicable_addon['name'] == 'Engine Protector')
                    {
                        $is_engine_protector = 'ENGEBOX';
                    }
                }
            }
            $CPA_year = 0;
            if ($selected_addons['compulsory_personal_accident'] != NULL && $selected_addons['compulsory_personal_accident'] != '')
            {
                foreach ($selected_addons['compulsory_personal_accident'] as $compulsory_personal_accident)
                {
                    if (isset($compulsory_personal_accident['name']) && $compulsory_personal_accident['name'] == 'Compulsory Personal Accident')
                    {
                        $is_cpa = true;
                        $CPA_year = isset($compulsory_personal_accident['tenure'])? $compulsory_personal_accident['tenure'] : 1;
                    }                   
                }
            }
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();

        $posp_code = '';

        if ($quote_log_data->idv <= 5000000)
        {
            if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
            {
                if (config('HDFC_BIKE_V2_IS_NON_POS') != 'Y') {
                    $posp_code = config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y' ? config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_TEST_POSP_CODE') : $pos_data->pan_no;
                }
            }
            elseif (config('IS_POS_TESTING_MODE_ENABLE_HDFC_ERGO') == 'Y')
            {
                $posp_code = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_TEST_POSP_CODE');
            }
        }

        $premium_request = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE')
            ],
            'TypeOfBusiness' => $business_type,
            'VehicleMakeCode' => $mmv_data->manufacturer_code,
            'VehicleModelCode' => $mmv_data->vehicle_model_code,
            'RtoLocationCode' => $rto_data->rto_location_code,
            'Premium_Year' => '1',//$Premium_Year,
            'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "INDIVIDUAL" : "CORPORATE",
            'PolicyType' => $tp_only ? 'ThirdParty' : $policy_type,
            'CustomerStateCode' => $rto_data->state_code,
            'PurchaseRegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
            'RequiredIDV' => $quote_log_data->idv,
            'IsPreviousClaim' => ($requestData->is_claim == 'Y') || ($requestData->previous_policy_type == 'Third-party') ? 'YES' : 'NO',
            'PreviousPolicyEndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
            'PreviousNCBDiscountPercentage' => $requestData->previous_ncb,
            'PospCode' => $posp_code,
            'CORDiscount' => 0,
            'AddOnCovers' => [
                'IsZeroDepCover' => $is_zero_dep,
                'IsEmergencyAssistanceCover' => $is_roadside_assistance,
                'planType' => $is_engine_protector,
                'UnnamedPassengerSumInsured' => $pa_unnamed_passenger_sa,
                'IsLegalLiabilityDriver' => $is_ll_paid_driver,
                'IsTPPDDiscount' => $is_tppd_discount,
                'CpaYear' => $CPA_year,#$requestData->vehicle_owner_type == 'I' && $is_cpa ? ($requestData->business_type == 'newbusiness' ? 5 : 1) : 0,
            ],
        ];

        if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure')
        {
            unset($premium_request['IsPreviousClaim']);
            unset($premium_request['PreviousPolicyEndDate']);
            unset($premium_request['PreviousNCBDiscountPercentage']);
        }

        if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')
        {
            $premium_request['TPEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
            unset($premium_request['AddOnCovers']['UnnamedPassengerSumInsured']); //PA Passenger is not allowed for ODOnly policy type
            unset($premium_request['AddOnCovers']['IsLegalLiabilityDriver']); // Legal liability driver is not allowed for ODOnly policy type
            unset($premium_request['AddOnCovers']['IsTPPDDiscount']); // IsTPPDDiscount is not allowed for ODOnly policy type
            unset($premium_request['AddOnCovers']['CpaYear']); // CpaYear is not allowed for ODOnly policy type
        }
        $rno1 = explode('-', $proposal->vehicale_registration_number);
        if (strtolower($rno1[0]) == 'dl' && $rno1[1] < 10 && strlen($rno1[1]) < 2) 
        {
            $rno1[1] = '0' . $rno1[1];
            $rno1 = implode('-', $rno1);
            $proposal->vehicale_registration_number = $rno1;
        }

        $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_PREMIUM_CALCULATION_URL'), $premium_request, 'hdfc_ergo', [
            'section' => $productData->product_sub_type_code,
            'method' => 'Premium Calculation',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),
                'Content-Type' => 'application/json',
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
                'Accept-Language' => 'en-US,en;q=0.5'
            ]
        ]);
        $premium_response = $get_response['response'];

        if ($premium_response)
        {
            $premium_response = json_decode($premium_response, TRUE);

            if ($premium_response['Status'] == 200)
            {
                $address_data = [
                    'address' => removeSpecialCharactersFromString($proposal->address_line1, true),
                    'address_1_limit'   => 50,
                    'address_2_limit'   => 50,         
                    'address_3_limit'   => 250,         
                ];
                $getAddress = getAddress($address_data);
                $RegistrationNo = $proposal->vehicale_registration_number;
                if ($requestData->business_type == 'newbusiness') {
                    if (strtoupper($requestData->fuel_type) == 'ELECTRIC' && $mmv_data->cubic_capacity > 2.5) {
                        $RegistrationNo = "NEW";
                    } elseif (strtoupper($requestData->fuel_type) == 'ELECTRIC') {
                        $RegistrationNo = "ELC";
                    } else {
                        $RegistrationNo = "NEW";
                    }
                }
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
                $proposal_request = [
                    'UniqueRequestID' => $premium_response['UniqueRequestID'],
                    'ProposalDetails' => [
                        'ConfigurationParam' => [
                            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE')
                        ],
                        'PremiumYear' => $requestData->business_type == 'newbusiness' && $tp_only ? 5 : 1,
                        'TypeOfBusiness' => $business_type,
                        'PolicyType' => $tp_only ? 'ThirdParty' : $policy_type,
                        'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "INDIVIDUAL" : "CORPORATE",
                        'VehicleMakeCode' => (int) $mmv_data->manufacturer_code,
                        'VehicleModelCode' => (int) $mmv_data->vehicle_model_code,
                        'RtoLocationCode' => $rto_data->rto_location_code,
                        'RequiredIDV' => $quote_log_data->idv,
                        'PurchaseRegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                        'YearofManufacture' => (int) date('Y', strtotime('01-' . $requestData->manufacture_year)),
                        //'RegistrationNo' => (($requestData->business_type == 'newbusiness') && (strtolower($requestData->fuel_type) == 'electric')) ? 'ELC' : $proposal->vehicale_registration_number,
                        'RegistrationNo' => $RegistrationNo,
                        'EngineNo' => $proposal->engine_number,
                        'ChassisNo' => $proposal->chassis_number,
                        'PreviousPolicyEndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
                        'IsPreviousClaim' => ($requestData->is_claim == 'Y') || ($requestData->previous_policy_type == 'Third-party') ? 'YES' : 'NO',
                        'PreviousNcbDiscountPercentage' => (int) $requestData->previous_ncb,
                        'PreviousPolicyType' => $requestData->previous_policy_type == 'Third-party' ? 'ThirdParty' : 'Comprehensive',
                        'PreviousPolicyNumber' => $proposal->previous_policy_number,
                        'PreviousInsurerCode' => (int) $proposal->previous_insurance_company ?? 0,
                        'NetPremiumAmount' => (int) $premium_response['Data'][0]['NetPremiumAmount'],
                        'TaxAmount' => (int) $premium_response['Data'][0]['TaxAmount'],
                        'FinancierCode' => (int) $proposal->name_of_financer ?? 0,
                        'TotalPremiumAmount' => (int) $premium_response['Data'][0]['TotalPremiumAmount'],
                        'PAOwnerDriverNomineeName' => $proposal->nominee_name ?? '',
                        'PAOwnerDriverNomineeAge' => (int) $proposal->nominee_age ?? 0,
                        'PAOwnerDriverNomineeRelationship' => $proposal->nominee_relationship ?? '',
                        'AddOnCovers' => [
                            'IsZeroDepCover' => $is_zero_dep,
                            'IsEmergencyAssistanceCover' => $is_roadside_assistance,
                            'planType' => $is_engine_protector,
                            'UnnamedPassengerSumInsured' => $pa_unnamed_passenger_sa,
                            'IsLegalLiabilityDriver' => $is_ll_paid_driver,
                            'IsTPPDDiscount' => $is_tppd_discount,
                            'CpaYear' => $CPA_year,#$requestData->vehicle_owner_type == 'I' && $is_cpa ? ($requestData->business_type == 'newbusiness' ? 5 : 1) : 0,
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
                        'MobileNumber' => (int) $proposal->mobile_number,
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
                        'CorrespondenceAddressPincode' => $proposal->pincode
                    ]                            
                ];

                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $proposal_request['CustomerDetails']['KYCId'] = $proposal->ckyc_reference_id;
                }

                if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure')
                {
                    unset($proposal_request['ProposalDetails']['IsPreviousClaim']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyEndDate']);
                    unset($proposal_request['ProposalDetails']['PreviousNCBDiscountPercentage']);
                    unset($proposal_request['ProposalDetails']['PreviousPolicyNumber']);
                    unset($proposal_request['ProposalDetails']['PreviousInsurerCode']);
                }

                if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')
                {
                    $proposal_request['ProposalDetails']['ExistingInsurerId'] = (int) $proposal->tp_insurance_company;
                    $proposal_request['ProposalDetails']['ExistingPolicyNumber'] = $proposal->tp_insurance_number;
                    $proposal_request['ProposalDetails']['ExistingPolicyEndDate'] = date('Y-m-d', strtotime($proposal->tp_end_date));
                    $proposal_request['ProposalDetails']['ExistingPolicyStartDate'] = date('Y-m-d', strtotime($proposal->tp_start_date));
                    unset($premium_request['AddOnCovers']['UnnamedPassengerSumInsured']); //PA Passenger is not allowed for ODOnly policy type
                    unset($premium_request['AddOnCovers']['IsLegalLiabilityDriver']); // Legal liability driver is not allowed for ODOnly policy type
                    unset($premium_request['AddOnCovers']['IsTPPDDiscount']); // IsTPPDDiscount is not allowed for ODOnly policy type
                    unset($premium_request['AddOnCovers']['CpaYear']); // CpaYear is not allowed for ODOnly policy type
                }

                HdfcErgoPremiumDetailController::saveV2PremiumDetails($get_response['webservice_id']);

                $get_response = getWsData(config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_PROPOSAL_GENERATION_URL'), $proposal_request, 'hdfc_ergo', [
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Proposal Generation',
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),
                        'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),
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
                        foreach ($premium_response['Data'] as $premium_data)
                        {
                            if ($premium_data['PremiumYear'] == 5)
                            {
                                $premium_response = $premium_data;
                            }
                            elseif ($premium_data['PremiumYear'] == 1)
                            {
                                $premium_response = $premium_data;
                            }
                        }

                        $basic_od = $premium_response['BasicODPremium'] ?? 0;
                        $basic_tp = $premium_response['BasicTPPremium'] ?? 0;
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
                        $lpg_cng_kit_tp = 0;
                        $ncb_discount = $premium_response['NewNcbDiscountAmount'] ?? 0;
                        $tppd_discount = $premium_response['TppdDiscountAmount'] ?? 0;
                        
                        if (isset($premium_response['AddOnCovers']))
                        {
                            foreach ($premium_response['AddOnCovers'] as $addon_cover)
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

                                    case 'PACoverOwnerDriver5Year':
                                        if ($requestData->business_type == 'newbusiness' && $requestData->policy_type == 'comprehensive' && ($CPA_year == 5))
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

                                    case 'ZeroDepreciation':
                                        $zero_depreciation = $productData->zero_dep == 0 ? $addon_cover['CoverPremium'] : 0;
                                        break;

                                    case 'EmergencyAssistance':
                                        $road_side_assistance = $addon_cover['CoverPremium'];
                                        break;

                                    case 'EngineProtection':
                                        $engine_protection = $addon_cover['CoverPremium'];
                                        break;

                                    default:
                                        break;
                                }
                            }
                        }

                        $final_total_discount = $ncb_discount + $tppd_discount;
                        $total_od_amount = $basic_od - $final_total_discount;
                        $total_tp_amount = $basic_tp + $ll_paid_driver + $lpg_cng_kit_tp + $pa_paid_driver + $cpa + $unnamed_passenger;
                        $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od + $zero_depreciation + $road_side_assistance + $ncb_protection + $consumable + $key_replacement + $tyre_secure + $engine_protection + $return_to_invoice + $loss_of_personal_belongings;

                        $final_net_premium = (int) $premium_response['NetPremiumAmount'];
                        $service_tax = (int) $premium_response['TaxAmount'];
                        $final_payable_amount = (int) $premium_response['TotalPremiumAmount'];

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

                        $policy_start_date = $proposal_response['Data']['NewPolicyStartDate'] ?? $policy_start_date;
                        $policy_end_date = $proposal_response['Data']['NewPolicyEndDate'] ?? $policy_end_date;
            
                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                                'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
                                'proposal_no' => (int) $proposal_response['Data']['TransactionNo'],
                                'unique_proposal_id' => $proposal_response['UniqueRequestID'],
                                'od_premium' => $total_od_amount,
                                'tp_premium' => ($total_tp_amount),
                                'ncb_discount' => ($ncb_discount), 
                                'addon_premium' => ($total_addon_amount),
                                'cpa_premium' => ($cpa),
                                'total_discount' => ($final_total_discount),
                                'total_premium' => ($final_net_premium),
                                'service_tax_amount' => ($service_tax),
                                'final_payable_amount' => ($final_payable_amount),
                                'ic_vehicle_details' => json_encode($ic_vehicle_details)
                            ]);
            
                        updateJourneyStage([
                            'user_product_journey_id' => $enquiryId,
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id
                        ]);
            
                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $enquiryId,
                                'proposalNo' => $proposal_response['Data']['TransactionNo'],
                                'finalPayableAmount' => $final_payable_amount,
                                'is_breakin' => 'N',//$is_breakin,
                                'inspection_number' => '',//$inspection_id
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
        $vehicleDetails = [
            'manf_name'             => $mmv->vehicle_manufacturer,
            'model_name'            => $mmv->vehicle_model_name,
            'version_name'          => $mmv->variant,
            'seating_capacity'      => $mmv->seating_capacity,
            'carrying_capacity'     => $mmv->carrying_capacity,
            'cubic_capacity'        => $mmv->cubic_capacity,
            'fuel_type'             => $mmv->fuel,
            'gross_vehicle_weight'  => '',
            'vehicle_type'          => 'BIKE',
            'version_id'            => $mmv->ic_version_code,
        ];
        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;
        $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code, true);
        $rto_data = HdfcErgoBikeRtoLocation::where('rto_code', $rto_code)->first();
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
        if ($requestData->policy_type == 'comprehensive')
        {
            $policy_type = 'Comprehensive';
        }
        elseif ($requestData->policy_type == 'own_damage')
        {
            $policy_type = 'OwnDamage';
        }
        $policy_data = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE'),//'PBIN0001',//
            ],
            'PreviousPolicyNumber' =>  $proposal->previous_policy_number,
            'VehicleRegistrationNumber' => $proposal->vehicale_registration_number,
        ];
        
        // $policy_data = [
        //     'ConfigurationParam' => [
        //         'AgentCode' => 'PBIN0001'
        //     ],
        //     'PreviousPolicyNumber' =>  '2319201172935000000',
        //     'VehicleRegistrationNumber' => 'MH-01-CC-1011',
        // ];
    $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_FETCH_POLICY_DETAILS');
    // $url = 'https://uatcp.hdfcergo.com/TWOnline/ChannelPartner/RenewalCalculatePremium ';
   $get_response = getWsData($url, $policy_data, 'hdfc_ergo', [
       'section' => $productData->product_sub_type_code,
       'method' => 'Fetch Policy Details',
       'requestMethod' => 'post',
       'enquiryId' => $enquiryId,
       'productName' => $productData->product_name,
       'transaction_type' => 'proposal',
       'headers' => [
           'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),//'RENEWBUY',//
           'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
           'Content-Type' => 'application/json',
           //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
           'User-Agent' => $_SERVER['HTTP_USER_AGENT']
           //'Accept-Language' => 'en-US,en;q=0.5'
       ]
   ]);
   $policy_data_response = $get_response['response'];
    //print_r($policy_data_response);
    
    // $policy_data_response = '
    //     {
    //         "Status": 200,
    //         "Message": null,
    //         "Data": {
    //             "PreviousPolicyNo": "2312201092684500000",
    //             "UNNamedpaLastyear": "NO",
    //             "TppdDiscountLastYear": null,
    //             "PreviousPolicyStartDate": "2018-08-16",
    //             "PreviousPolicyEndDate": "2019-08-15",
    //             "RegistrationNo": "MH-01-BNG-8768",
    //             "PurchaseRegistrationDate": "2017-08-10",
    //             "EngineNo": "DG54745VGDF",
    //             "ChassisNo": "GRT45645GD",
    //             "RtoLocationCode": 11206,
    //             "RegistrationCity": "MUMBAI",
    //             "ManufacturingYear": "2017",
    //             "LLPaidDriverLastyear": "NO",
    //             "VehicleCubicCapacity": 124,
    //             "VehicleMakeCode": 123,
    //             "VehicleMakeName": "SUZUKI",
    //             "VehicleModelName": "ZEUS",
    //             "VehicleModelCode": 11209,
    //             "VehicleSeatingCapacity": 2,
    //             "PolicyIssuingAddress": "2ND FLOOR, CHICAGO PLAZA, RAJAJI ROAD, NEAR TO KSRTC BUS STAND ERNAKULAM, 682035.",
    //             "PolicyIssuingPhoneno": "+91-484-3934300",
    //             "PAOwnerDriverSI": 1500000.0,
    //             "UnnamedPersonSI": 0.0,
    //             "OldNcbPercentage": 25,
    //             "Zerodeplastyear": "NO",
    //             "Emergencyassistancelastyear": "NO",
    //             "Enggrbxlastyear": "NO",
    //             "Cashallowlastyear": "NO",
    //             "CustomerDetail": [
    //                 {
    //                     "Title": "MR",
    //                     "FirstName": "AMIT KUMAR",
    //                     "MiddleName": "SHYAM",
    //                     "LastName": "SHARMA",
    //                     "Gender": "MALE",
    //                     "Pancard": "ATLPM0431B",
    //                     "GstInNo": "27AAACB5343E1Z1",
    //                     "DateofBirth": "1991-01-01",
    //                     "OrganizationName": null,
    //                     "OrganizationContactPersonName": "NA"
    //                 }
    //             ],
    //             "TwoWheelerRenewalPremiumList": [
    //                 {
    //                     "PremiumYear": 1,
    //                     "VehicleIdv": 34583.0,
    //                     "BasicODPremium": 591.0,
    //                     "BasicTPPremium": 752.0,
    //                     "NetPremiumAmount": 1718.0,
    //                     "TaxPercentage": 18.0,
    //                     "TaxAmount": 309.0,
    //                     "TotalPremiumAmount": 2027.0,
    //                     "NewNcbDiscountAmount": 0.0,
    //                     "NewNcbDiscountPercentage": 0.0,
    //                     "VehicleIdvMin": 29396.0,
    //                     "VehicleIdvMax": 39770.0,
    //                     "VehicleIdvYear2": 31124.7,
    //                     "VehicleIdvYear3": 27666.4,
    //                     "LLPaidDriverRate": 50.0,
    //                     "LLPaidDriverPremium": 50.0,
    //                     "UnnamedPassengerRate": 0.0007,
    //                     "NewPolicyStartDate": "2020-06-22",
    //                     "NewPolicyEndDate": "2021-06-21",
    //                     "PACoverOwnerDriverPremium": 375.0,
    //                     "AddOnCovers": [
    //                         {
    //                             "CoverName": "CashAllowance",
    //                             "CoverPremium": 100.0
    //                         }
    //                     ]
    //                 },
    //                 {
    //                     "PremiumYear": 2,
    //                     "VehicleIdv": 34583.0,
    //                     "BasicODPremium": 947.0,
    //                     "BasicTPPremium": 1504.0,
    //                     "NetPremiumAmount": 3131.0,
    //                     "TaxPercentage": 18.0,
    //                     "TaxAmount": 564.0,
    //                     "TotalPremiumAmount": 3695.0,
    //                     "NewNcbDiscountAmount": 0.0,
    //                     "NewNcbDiscountPercentage": 0.0,
    //                     "VehicleIdvMin": 29396.0,
    //                     "VehicleIdvMax": 39770.0,
    //                     "VehicleIdvYear2": 31124.7,
    //                     "VehicleIdvYear3": 27666.4,
    //                     "LLPaidDriverRate": 50.0,
    //                     "LLPaidDriverPremium": 100.0,
    //                     "UnnamedPassengerRate": 0.0007,
    //                     "NewPolicyStartDate": "2020-06-22",
    //                     "NewPolicyEndDate": "2022-06-21",
    //                     "PACoverOwnerDriverPremium": 680.0,
    //                     "AddOnCovers": []
    //                 },
    //                 {
    //                     "PremiumYear": 3,
    //                     "VehicleIdv": 34583.0,
    //                     "BasicODPremium": 1240.0,
    //                     "BasicTPPremium": 2256.0,
    //                     "NetPremiumAmount": 4516.0,
    //                     "TaxPercentage": 18.0,
    //                     "TaxAmount": 813.0,
    //                     "TotalPremiumAmount": 5329.0,
    //                     "NewNcbDiscountAmount": 0.0,
    //                     "NewNcbDiscountPercentage": 0.0,
    //                     "VehicleIdvMin": 29396.0,
    //                     "VehicleIdvMax": 39770.0,
    //                     "VehicleIdvYear2": 31124.7,
    //                     "VehicleIdvYear3": 27666.4,
    //                     "LLPaidDriverRate": 50.0,
    //                     "LLPaidDriverPremium": 150.0,
    //                     "UnnamedPassengerRate": 0.0007,
    //                     "NewPolicyStartDate": "2020-06-22",
    //                     "NewPolicyEndDate": "2023-06-21",
    //                     "PACoverOwnerDriverPremium": 1020.0,
    //                     "AddOnCovers": []
    //                 }
    //             ]
    //         },
    //         "UniqueRequestID": "6d983f37-fa0e-4d8e-9141-abf26aa00e2d"
    //     }';

    $policy_data_response = json_decode($policy_data_response,true);
    if($policy_data_response['Status'] == 200)
    {
        HdfcErgoPremiumDetailController::saveRenewalPremiumDetails($get_response['webservice_id']);
        $all_data = $policy_data_response['Data'];
        //$AddOnsOptedLastYear = explode(',',$all_data['AddOnsOptedLastYear']);
        $TwoWheelerRenewalPremiumList = $all_data['TwoWheelerRenewalPremiumList'][0];
        $AddOnCovers = $TwoWheelerRenewalPremiumList['AddOnCovers'] ?? '';
        $CustomerDetail = $all_data['CustomerDetail'][0];
        $UniqueRequestID = $policy_data_response['UniqueRequestID'];
        
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
        
        if(is_array($AddOnCovers))
        {
            foreach ($AddOnCovers as $key => $value) 
            {
                
                if(in_array($value['CoverName'], ['ZERODEP','ZeroDepreciation']) && $all_data['Zerodeplastyear'] == 'YES')
                {
                   $zeroDepreciation = $value['CoverPremium'];
                   $IsZeroDepCover = 'YES';
                }
                else if($value['CoverName'] == 'NCBPROT')
                {
                    $ncb_protection = $value['CoverPremium'];
                    $IsNoClaimBonusProtection = 'YES';
                }
                else if(in_array($value['CoverName'], ['ENGEBOX','EngineProtection']) && $all_data['Enggrbxlastyear'] == 'YES')
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
                else if(in_array($value['CoverName'], ['EMERGASSIST','EmergencyAssistance']) && $all_data['Emergencyassistancelastyear'] == 'YES')
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
                    $IsZeroDepCover = 'YES';
                }
                else if($value['CoverName'] == 'LOSSUSEDOWN')
                {
                    $lossOfPersonalBelongings = $value['CoverPremium'];
                    $IsLossOfUse = 'YES';
                }                                                
            }                       
        } 
        $cpa_premium  = $all_data['CPAlastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['PACoverOwnerDriverPremium'] : 0; 
        $is_cpa = (int)$cpa_premium > 0 ? true : false;
        $address = HdfcErgoMotorPincodeMaster::where('num_pincode', $proposal->pincode)->where('txt_pincode_locality',$proposal->city)->first();
        $GstInNo = !empty($proposal->gst_number) ? $proposal->gst_number : $CustomerDetail['GstInNo'];
        if(strtoupper($GstInNo) == "NA")
        {
            $GstInNo = '';
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

        $proposal_input_data = [
            'UniqueRequestID' => $UniqueRequestID,
            'ProposalDetails' => [
                'ConfigurationParam' => [
                    'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE')
                ],
                'PremiumYear' => $requestData->business_type == 'newbusiness' && $tp_only ? 3 : 1,
                'PolicyType' => $tp_only ? 'ThirdParty' : $policy_type,
                'VehicleMakeCode' => $mmv->manufacturer_code,
                'VehicleModelCode' => $mmv->vehicle_model_code,
                'RtoLocationCode' => $rto_data->rto_location_code,
                'PreviousPolicyNumber'              => $all_data['PreviousPolicyNo'],//'2311201090946600000',
                'VehicleRegistrationNumber'         => $all_data['RegistrationNo'],//'MH-02-LO-3534',
                'IsEADiscount'                      => 0,
                'RequiredIdv'                       => $TwoWheelerRenewalPremiumList['VehicleIdv'] ?? 0,//487364,
                'NetPremiumAmount'                  => $TwoWheelerRenewalPremiumList['NetPremiumAmount'],//15410,
                'TotalPremiumAmount'                => $TwoWheelerRenewalPremiumList['TotalPremiumAmount'],//18184,
                'TaxAmount'                         => ($TwoWheelerRenewalPremiumList['TaxAmount']),//2774,
            
                'AddOnCovers' => [
                  'IsZeroDepCover'                  => $IsZeroDepCover,
                  //'IsLossOfUse'                     => $IsLossOfUse,
                  'IsEmergencyAssistanceCover'      => $IsEmergencyAssistanceCover,
                  //'IsNoClaimBonusProtection'        => $IsNoClaimBonusProtection,
                  'IsEngineAndGearboxProtectorCover' => $IsEngineAndGearboxProtectorCover,//'NO',
                  //'IsCostOfConsumable'                  => $IsCostOfConsumable,//'NO',
                  //'IsReturntoInvoice' => $IsReturntoInvoice,
                  'IsEmergencyAssistanceWiderCover' => $IsEmergencyAssistanceWiderCover,//'NO',
                  //'IsTyreSecureCover' => $IsTyreSecureCover,//'YES',
                  //'NonelectricalAccessoriesIdv' => $all_data['NonelectricalAccessoriesIdv'],
                  //'ElectricalAccessoriesIdv' => $all_data['ElectricalAccessoriesIdv'],
                  //'LpgCngKitIdv' => $all_data['LpgCngKitIdv'],
                  //'SelectedFuelType' => (int) $all_data['LpgCngKitIdv'] > 0 ? 'CNG' : NULL,
                 // 'IsPAPaidDriver' => $all_data['PAPaidDriverLastYear'],
                  //'PAPaidDriverSumInsured' => $all_data['PAPaidDriverSI'],
                  //'IsPAUnnamedPassenger' => $all_data['UnnamedPassengerLastYear'],
                  //'PAUnnamedPassengerNo' => $all_data['NoOfUnnamedPassenger'],
                  //'PAPerUnnamedPassengerSumInsured' => $all_data['UnnamedPassengerSI'],//40000,
                  'IsLegalLiabilityDriver' => $all_data['LLPaidDriverLastyear'],
                  //'LLPaidDriverNo' => $all_data['NoOfLLPaidDrivers'],
                  //'IsLLEmployee' => $all_data['LLEmployeeLastYear'],//'YES',
                  //'LLEmployeeNo' => $all_data['NumberOfLLEmployees'],
                  'CpaYear' => $is_cpa == true ? 1 : 0,
                ]
            ],
            'CustomerDetails' => [
            'Title' => $requestData->vehicle_owner_type == 'I' ? ($proposal->gender == 'MALE' ? 'Mr' : ($proposal->marital_status == 'Single' ? 'Ms' : 'Mrs')) : 'M/S',
            'Gender' => $requestData->vehicle_owner_type == 'I' ? $proposal->gender : '',
            'FirstName' => $requestData->vehicle_owner_type == 'I' ? preg_replace("/[^a-zA-Z0-9 ]+/", "", $proposal->first_name) : '',
            'MiddleName' => '',
            'LastName' => $requestData->vehicle_owner_type == 'I' ? (!empty($proposal->last_name) ? preg_replace("/[^a-zA-Z0-9 ]+/", "", $proposal->last_name) : '.') : '',
            'DateOfBirth' => $requestData->vehicle_owner_type == 'I' ? date('Y-m-d', strtotime($proposal->dob)) : '',
            'OrganizationName' => $requestData->vehicle_owner_type == 'C' ? $proposal->first_name : '',
            'OrganizationContactPersonName' => $requestData->vehicle_owner_type == 'C' ? ($proposal->last_name ?? '') : '',
            'EiaNo' => 0,
            'IsCustomerAuthenticated' => "YES",
            'UidNo' => '',
            'AuthentificationType' => "OTP",
            'LGCode' => '', //"LGCODE123",
            'registrationAddress1' => trim($getAddress['address_1']), #$proposal->address_line1,
            'registrationAddress2' => trim($getAddress['address_2']) != '' ? trim($getAddress['address_2']) : '.', #$proposal->address_line2,
              'registrationAddress3' => trim($getAddress['address_3']),
              'registrationAddressCitycode'   => $address->num_citydistrict_cd,
              'registrationAddressCityName'   => $address->txt_pincode_locality,
              'registrationAddressStateCode'  => $address->city->state->num_state_cd,
              'registrationAddressStateName'  => $address->city->state->txt_state,
              'registrationAddressPincode'    => $proposal->pincode,
              'EmailAddress'        => $CustomerDetail['EmailAddress'] ?? $proposal->email,//'ankit.mori@synoverge.com',
              'MobileNumber'        => $CustomerDetail['MobileNumber'] ?? $proposal->mobile_number,//'8128911914',
              'PanCard'             => $CustomerDetail['Pancard'] != 'NA'  ? $CustomerDetail['Pancard'] : $proposal->pan_number,
              'GstInNo'             => $GstInNo,//$CustomerDetail['GstInNo'],
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

        if($is_cpa == true)
        {
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeName'] = $proposal->nominee_name;
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeAge'] = $proposal->nominee_age;
            $proposal_input_data['ProposalDetails']['PAOwnerDriverNomineeRelationship'] = $proposal->nominee_relationship;           
        }
        
        // $url = "https://uatcp.hdfcergo.com/TWOnline/ChannelPartner/RenewalSaveTransaction";
        $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_RENEWAL_TWO_PROPOSAL_SUBMIT_URL');
           $get_response = getWsData($url, $proposal_input_data, 'hdfc_ergo', [
               'section' => $productData->product_sub_type_code,
               'method' => 'Renewal Proposal Submit',
               'requestMethod' => 'post',
               'enquiryId' => $enquiryId,
               'productName' => $productData->product_name,
               'transaction_type' => 'proposal',
               'headers' => [
                   'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),//'RENEWBUY',//
                   'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
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
        
        // $proposal_reponse = '{
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
            //TwoWheelerRenewalPremiumList;
            $policy_start_date = date('d-m-Y', strtotime($proposal_reponse['Data']['NewPolicyStartDate']));
            $policy_end_date = date('d-m-Y', strtotime($proposal_reponse['Data']['NewPolicyStartDate']));
            
             //OD Premium
            $basicOD = $TwoWheelerRenewalPremiumList['BasicODPremium'] ?? 0;
            //$ElectricalAccessoriesPremium = $TwoWheelerRenewalPremiumList['ElectricalAccessoriesPremium'];
            //$NonelectricalAccessoriesPremium = $TwoWheelerRenewalPremiumList['NonelectricalAccessoriesPremium'];
            //$LpgCngKitODPremium = $TwoWheelerRenewalPremiumList['LpgCngKitODPremium'];

            $fianl_od = $basicOD;// + $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;

            //TP Premium           
            $basic_tp = $TwoWheelerRenewalPremiumList['BasicTPPremium'] ?? 0;        
            $LLPaidDriversPremium = $all_data['LLPaidDriverLastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['LLPaidDriverPremium'] : 0;
            $UnnamedPassengerPremium = $all_data['UNNamedpaLastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['UnnamedPassengerPremium'] : 0;
            $PAPaidDriverPremium = $TwoWheelerRenewalPremiumList['PAPaidDriverPremium'] ?? 0;       
            //$PremiumNoOfLLPaidDrivers = $TwoWheelerRenewalPremiumList['PremiumNoOfLLPaidDrivers'];
            //$LpgCngKitTPPremium = $TwoWheelerRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
            $PACoverOwnerDriverPremium = $all_data['CPAlastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['PACoverOwnerDriverPremium'] : 0;

            $final_tp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium;// + $PremiumNoOfLLPaidDrivers + $LpgCngKitTPPremium;

            //Discount 
            $applicable_ncb = $TwoWheelerRenewalPremiumList['NewNcbDiscountPercentage']?? 0;
            $NcbDiscountAmount = $TwoWheelerRenewalPremiumList['NewNcbDiscountAmount']?? 0;
            //$OtherDiscountAmount = $TwoWheelerRenewalPremiumList['OtherDiscountAmount'];
            $tppD_Discount = 0;
            $total_discount = $NcbDiscountAmount;// + $OtherDiscountAmount ;

            //final calc

            // $NetPremiumAmount = //$TwoWheelerRenewalPremiumList['NetPremiumAmount'];
            // $TaxAmount = $TwoWheelerRenewalPremiumList['TaxAmount'];
            // $TotalPremiumAmount = $TwoWheelerRenewalPremiumList['TotalPremiumAmount'];  
            $NetPremiumAmount = $fianl_od + $final_tp - $NcbDiscountAmount;
            $TaxAmount = ($NetPremiumAmount * 0.18);
            $TotalPremiumAmount = $NetPremiumAmount + $TaxAmount;
            
            $addon_premium = $zeroDepreciation + $engineProtect + $keyProtect + $tyreProtect + $returnToInvoice + $lossOfPersonalBelongings + 
            $roadSideAssistance + $consumables + $ncb_protection;//+ $ElectricalAccessoriesPremium + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;
            $idv = $TwoWheelerRenewalPremiumList['VehicleIdv'] ?? 0;
            $proposal->idv                  = $idv;
            $proposal->proposal_no          = $proposal_reponse['Data']['TransactionNo'];
            $proposal->unique_proposal_id   = $proposal_reponse['Data']['TransactionNo'];
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
            $proposal->tp_start_date    = $policy_start_date;
            $proposal->tp_end_date      = $policy_end_date;
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
                'message' => $proposal_reponse['Status'] ?? 'Service Issue'
            ];
        }
     }
     else
     {
        return [
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message' => $proposal_reponse['Message']['ErrorMessage'] ?? 'Service Issue'
        ];
     }
    }
}
