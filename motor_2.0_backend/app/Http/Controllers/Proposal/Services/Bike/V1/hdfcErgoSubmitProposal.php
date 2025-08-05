<?php

namespace App\Http\Controllers\Proposal\Services\Bike\V1;

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
use App\Quotes\Bike\V1\hdfc_ergo;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class hdfcErgoSubmitProposal
{
    public static function submit($proposal, $request)
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
            $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;

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
                if ($premium_type == 'comprehensive') {
                    $policy_end_date =  today()->addYear(1)->subDay(1)->format('d/m/Y');
                } elseif ($premium_type == 'third_party') {
                    $policy_end_date =   today()->addYear(5)->subDay(1)->format('d/m/Y');
                }
                $cpa_tenure = 5;
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
            // if($bike_age > 5 && $voluntary_insurer_discounts > 0)
            // {
            //     $voluntary_insurer_discounts = 0;
            // }
            
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
            $token = hdfc_ergo::hdfcErgoGetToken($enquiryId, $transactionid, $productName, $ProductCode, 'proposal');

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
                        'Owner_Driver_Nominee_Relationship' => 'Brother', // (!$od_only || $proposal->owner_type == 'I') ? $proposal->nominee_relationship : "0",
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
                    'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
                    'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
                    'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
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

                $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.CALCULATE_PREMIUM'), $proposal_array, 'hdfc_ergo', $additionData);
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
                        $pa_owner_driver = round($premium_data['PAOwnerDriver_Premium']);
                    } if (!empty($premium_data['Vehicle_Base_ZD_Premium'])) {
                        $zd_premium += round($premium_data['Vehicle_Base_ZD_Premium']);
                    } if (!empty($premium_data['EA_premium'])) {
                        $rsa_premium = round($premium_data['EA_premium']);
                    } if (!empty($premium_data['NCBBonusDisc_Premium'])) {
                        $ncb_discount = round($premium_data['NCBBonusDisc_Premium']);
                    } if (!empty($premium_data['Vehicle_Base_RTI_premium_Premium'])) {
                        $rti_premium += round($premium_data['Vehicle_Base_RTI_Premium']);
                    } if (!empty($premium_data['UnnamedPerson_premium'])) {
                        $pa_unnamed = round($premium_data['UnnamedPerson_premium']);
                    } if (!empty($premium_data['Electical_Acc_Premium'])) {
                        $electrical_accessories = round($premium_data['Electical_Acc_Premium']);
                    } if (!empty($premium_data['NonElectical_Acc_Premium'])) {
                        $non_electrical_accessories = round($premium_data['NonElectical_Acc_Premium']);
                    } if (!empty($premium_data['PAPaidDriver_Premium'])) {
                        $pa_paid_driver = round($premium_data['PAPaidDriver_Premium']);
                    } if (!empty($premium_data['Net_Premium'])) {
                        $final_net_premium = round($premium_data['Net_Premium']);
                    } if (!empty($premium_data['Total_Premium'])) {
                        $final_payable_amount = round($premium_data['Total_Premium']);
                    } if (!empty($premium_data['Basic_OD_Premium'])) {
                        $basic_od_premium = round($premium_data['Basic_OD_Premium']);
                    } if (!empty($premium_data['Basic_TP_Premium'])) {
                        $basic_tp_premium = round($premium_data['Basic_TP_Premium']);
                    } if (!empty($premium_data['VoluntartDisc_premium'])) {
                        $voluntary_discount = round($premium_data['VoluntartDisc_premium']);
                    } if (!empty($premium_data['TPPD_premium'])) {
                        $tppd_discount = round($premium_data['TPPD_premium']);
                    } if ($is_zero_dep == '1' && !empty($premium_data['Elec_ZD_Premium'])) {
                        $zd_premium += round($premium_data['Elec_ZD_Premium']);
                    } if ($is_zero_dep == '1' && !empty($premium_data['NonElec_ZD_Premium'])) {
                        $zd_premium += round($premium_data['NonElec_ZD_Premium']);
                    } if (!empty($premium_data['Elec_RTI_Premium'])) {
                        $rti_premium += round($premium_data['Elec_RTI_Premium']);
                    } if (!empty($premium_data['NonElec_RTI_Premium'])) {
                        $rti_premium += round($premium_data['NonElec_RTI_Premium']);
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
                    $addon_premium = $zd_premium + $rsa_premium + $rti_premium;
                    $od_premium = $basic_od_premium + $electrical_accessories + $non_electrical_accessories+$GeogExtension_od+$OwnPremises_OD;
                    $tp_premium = $basic_tp_premium + $pa_owner_driver + $pa_paid_driver + $pa_unnamed+$GeogExtension_tp+$OwnPremises_TP;
                    $total_discount = $ncb_discount + $tppd_discount + $voluntary_discount;

                    $proposal_array['Customer_Details'] = [
                        'Customer_Type' => ($proposal->owner_type == 'I') ? 'Individual' : 'Corporate',
                        'Company_Name' => ($proposal->owner_type == 'I') ? '' : $proposal->first_name,
                        'Customer_FirstName' => 'PRIYANKA JAGANNATH', // ($proposal->owner_type == 'I') ? $proposal->first_name : $proposal->last_name,
                        'Customer_MiddleName' => '',
                        'Customer_LastName' => 'BHAMRE', // ($proposal->owner_type == 'I') ? (!empty($proposal->last_name) ? $proposal->last_name : '.' ) : '',
                        'Customer_DateofBirth' => date('d/m/Y', strtotime($proposal->dob)),
                        'Customer_Email' => 'test@gmail.com', // $proposal->email,
                        'Customer_Mobile' => $proposal->mobile_number,
                        'Customer_Telephone' => '',
                        'Customer_PanNo' => $proposal->pan_number,
                        'Customer_Salutation' => $salutation,
                        'Customer_Gender' => 'MALE', // $proposal->gender,
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
                        $proposal_array['Customer_Details']['Customer_Pehchaan_id'] = 'EGEAMB537D';
                        // $proposal_array['Customer_Details']['Customer_Pehchaan_id'] = $proposal->ckyc_reference_id;
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
                        'SOURCE' => config('IC.HDFC_ERGO.V1.BIKE.SOURCE_ID'),
                        'CHANNEL_ID' => config('IC.HDFC_ERGO.V1.BIKE.CHANNEL_ID'),
                        'CREDENTIAL' => config('IC.HDFC_ERGO.V1.BIKE.CREDENTIAL'),
                    ];

                    HdfcErgoPremiumDetailController::saveV1PremiumDetails($get_response['webservice_id']);

                    $get_response = getWsData(config('IC.HDFC_ERGO.V1.BIKE.GIC_BIKE_CREATE_PROPOSAL'), $proposal_array, 'hdfc_ergo', $additionData);
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
                            $pa_owner_driver = round($proposal_data['PAOwnerDriver_Premium']);
                        } if (!empty($proposal_data['Vehicle_Base_ZD_Premium'])) {
                            $zd_premium += round($proposal_data['Vehicle_Base_ZD_Premium']);
                        } if (!empty($proposal_data['EA_premium'])) {
                            $rsa_premium = round($proposal_data['EA_premium']);
                        } if (!empty($proposal_data['NCBBonusDisc_Premium'])) {
                            $ncb_discount = round($proposal_data['NCBBonusDisc_Premium']);
                        } if (!empty($proposal_data['Vehicle_Base_RTI_premium_Premium'])) {
                            $rti_premium += round($proposal_data['Vehicle_Base_RTI_Premium']);
                        } if (!empty($proposal_data['UnnamedPerson_premium'])) {
                            $pa_unnamed = round($proposal_data['UnnamedPerson_premium']);
                        } if (!empty($proposal_data['Electical_Acc_Premium'])) {
                            $electrical_accessories = round($proposal_data['Electical_Acc_Premium']);
                        } if (!empty($proposal_data['NonElectical_Acc_Premium'])) {
                            $non_electrical_accessories = round($proposal_data['NonElectical_Acc_Premium']);
                        } if (!empty($proposal_data['PAPaidDriver_Premium'])) {
                            $pa_paid_driver = round($proposal_data['PAPaidDriver_Premium']);
                        } if (!empty($proposal_data['Net_Premium'])) {
                            $final_net_premium = round($proposal_data['Net_Premium']);
                        } if (!empty($proposal_data['Total_Premium'])) {
                            $final_payable_amount = round($proposal_data['Total_Premium']);
                        } if (!empty($proposal_data['Basic_OD_Premium'])) {
                            $basic_od_premium = round($proposal_data['Basic_OD_Premium']);
                        } if (!empty($proposal_data['Basic_TP_Premium'])) {
                            $basic_tp_premium = round($proposal_data['Basic_TP_Premium']);
                        } if (!empty($proposal_data['VoluntartDisc_premium'])) {
                            $voluntary_discount = round($proposal_data['VoluntartDisc_premium']);
                        } if (!empty($proposal_data['TPPD_premium'])) {
                            $tppd_discount = round($proposal_data['TPPD_premium']);
                        } if ($is_zero_dep == '1' && !empty($proposal_data['Elec_ZD_Premium'])) {
                            $zd_premium += round($proposal_data['Elec_ZD_Premium']);
                        } if ($is_zero_dep == '1' && !empty($proposal_data['NonElec_ZD_Premium'])) {
                            $zd_premium += round($proposal_data['NonElec_ZD_Premium']);
                        } if (!empty($proposal_data['Elec_RTI_Premium'])) {
                            $rti_premium += round($proposal_data['Elec_RTI_Premium']);
                        } if (!empty($proposal_data['NonElec_RTI_Premium'])) {
                            $rti_premium += round($proposal_data['NonElec_RTI_Premium']);
                        }
                        if (!empty($proposal_data['GeogExtension_ODPremium'])) {
                            $GeogExtension_od = round($proposal_data['GeogExtension_ODPremium']);
                        }
                        if (!empty($proposal_data['GeogExtension_TPPremium'])) {
                            $GeogExtension_tp= round($proposal_data['GeogExtension_TPPremium']);
                        }
    
                        if (!empty($proposal_data['LimitedtoOwnPremises_OD_Premium'])) {
                            $OwnPremises_OD = round($proposal_data['LimitedtoOwnPremises_OD_Premium']);
                        }
                        if (!empty($proposal_data['LimitedtoOwnPremises_TP_Premium'])) {
                            $OwnPremises_TP = round($proposal_data['LimitedtoOwnPremises_TP_Premium']);
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
                            'proposal_no' => $arr_proposal['Policy_Details']['ProposalNumber'],
                            'unique_proposal_id' => $arr_proposal['Policy_Details']['ProposalNumber'],
                            'product_code' => $ProductCode,
                            'od_premium' => $od_premium,
                            'tp_premium' => $tp_premium,
                            'addon_premium' => $addon_premium,
                            'cpa_premium' => $pa_owner_driver,
                            'final_premium' => round($final_net_premium),
                            'total_premium' => round($final_net_premium),
                            'service_tax_amount' => round($proposal_data['Service_Tax']),
                            'final_payable_amount' => round($final_payable_amount),
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
                                'proposalNo' => $arr_proposal['Policy_Details']['ProposalNumber'],
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
}
