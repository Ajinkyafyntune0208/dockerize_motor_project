<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use App\Http\Controllers\SyncPremiumDetail\Bike\ReliancePremiumDetailController;
use App\Models\MasterProduct;
// use Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\PreviousInsurerList;
use DateTime;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

class reliancerenewalSubmitProposal {

    public static function renewalSubmit($proposal, $request) {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $is_renewal_journey = false;
        $CoverDetails = '';
        $ClientDetails_fetch =  '';
        $RenewalPolicy =  '';
        $Vehicle =  '';
        $PreviousInsuranceDetails =  '';
        $Premium =  '';
        $NCBEligibility = '';
        $PreviousNCBId =  '';
        $CurrentNCBId =  '';

    if(isset($request['is_renewal']) && $request['is_renewal'] === 'Y') {
        $is_renewal_journey = true;

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

        $UserID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_USERID_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_USERID_RELIANCE') : config('constants.IcConstants.reliance.USERID_RELIANCE');

        $AuthToken = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE') : config('constants.IcConstants.reliance.AUTH_TOKEN_RELIANCE');

        // Define $SourceSystemID before using it in the array
        $SourceSystemID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE') : config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE');

        $url = config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_FETCH_RENEWAL');

        $renewal_fetch_array = [
            'PrevPolicyNumber' => $proposal->previous_policy_number,
            'EngineNo' => '',
            'ChassisNo' => '',
            'RegistrationNo' => $proposal->vehicale_registration_number,
            'PrevPolicyEndDate' => '',
            'ProductCode' => '',
            'SourceSystemID' => $SourceSystemID,  // Now $SourceSystemID is defined
            'AuthToken' => $AuthToken,
            'UserID' => $UserID
        ];

        $get_response = getWsData(
            $url,
            $renewal_fetch_array,
            'reliance',
            [
                'root_tag'      => 'RenwalPolicy',
                'section'       => $productData->product_sub_type_code,
                'method'        => 'Renewal Fetch',
                'requestMethod' => 'post',
                'enquiryId'     => $enquiryId,
                'productName'   => $productData->product_name . " Renewal",
                'transaction_type'    => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ]
            ]
        );

        $renewal_res_data = $get_response['response'];

        if ($renewal_res_data) {
            $renewal_response = json_decode($renewal_res_data);
            if (!isset($renewal_response->PolicyDetails->ErrorMessages->ErrMessages)) {
                $CoverDetails = $renewal_response->PolicyDetails->CoverDetails->CoverList;
                $ClientDetails_fetch = $renewal_response->PolicyDetails->ClientDetails;
                $RenewalPolicy = $renewal_response->PolicyDetails->Policy;
                $Vehicle = $renewal_response->PolicyDetails->Vehicle;
                $PreviousInsuranceDetails = $renewal_response->PolicyDetails->PreviousInsuranceDetails;
                $Premium = $renewal_response->PolicyDetails->Premium;
                $NCBEligibility = $renewal_response->PolicyDetails->NCBEligibility;

                $ncb_master = [
                    "0" => "0",
                    "1" => "20",
                    "2" => "25",
                    "3" => "35",
                    "4" => "45",
                    "5" => "50",
                ];

                $PreviousNCBId = array_search($renewal_response->PolicyDetails->NCBEligibility->PreviousNCB, $ncb_master);
                $CurrentNCBId = array_search($renewal_response->PolicyDetails->NCBEligibility->CurrentNCB, $ncb_master);
        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        {
            return  response()->json([
                'status' => false,
                'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            ]);
        } */
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv')
            ->first();
        $idv = $quote_log_data->idv;

        $mmv = get_mmv_details($productData,$requestData->version_id,'reliance');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
            $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

            if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle Not Mapped',
                ];
            } else if ($mmv_data->ic_version_code == 'DNE') {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ];
            }
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        // $rto_code = $requestData->rto_code;
        // $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);
        // $rto_data = DB::table('reliance_rto_master as rm')
        //     ->where('rm.region_code',$rto_code)
        //     ->select('rm.*')
        //     ->first();

        $rto_code = $requestData->rto_code;
        $registration_number = $proposal->vehicale_registration_number;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness'
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }

        $registration_number = $rcDetails['rcNumber'];
        $rto_data = $rcDetails['rtoData'];

        $TypeOfFuel = [
            'petrol' => '1',
            'diesel' => '2',
            'cng' => '3',
            'lpg' => '4',
            'bifuel' => '5',
            'battery operated' => '6',
            'none' => '0',
            'na' => '7',
        ];
        $ncb_levels = [
            '0' => '0',
            '20' => '1',
            '25' => '2',
            '35' => '3',
            '45' => '4',
            '50' => '5'
        ];

        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false;

        if($premium_type != 'third_party' && $requestData->previous_policy_type == 'Third-party') {
            $requestData->business_type = 'breakin';
        }

        $isMotorQuote = 'true';
        $isMotorQuoteFlow = '';
        $TPPDCover = 'false';
        $cpa_tenure = '1';
        $PreviousNCB = $ncb_levels[$requestData->previous_ncb];
        $IsNCBApplicable = 'true';
        $NCBEligibilityCriteria = '2';

        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $business_type = 'New Business';
            $ISNewVehicle = true;
            $productCode = '2375';
            // $Registration_Number = 'NEW';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $IsNCBApplicable = 'false';
            $cpa_tenure = '5';
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
        } elseif ($requestData->business_type == 'rollover') {
            $BusinessType = '5';
            $business_type = 'Roll Over';
            $ISNewVehicle = false;
            $productCode = '2312';
            // $Registration_Number = $proposal->vehicale_registration_number;
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
        } elseif ($requestData->business_type == 'breakin') {
            $BusinessType = '5';
            $business_type = 'Break-In';
            $ISNewVehicle = false;
            $productCode = '2312';
            // $Registration_Number = $proposal->vehicale_registration_number;
            //$policy_start_date = date('Y-m-d', strtotime('+1 day'));
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            if($date_difference > 0 || $requestData->previous_policy_type == 'Not sure')
            {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }
            else{
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
        }

        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
            $productCode = ($ISNewVehicle == 'true') ? '2370' : '2348';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $isMotorQuote = 'false';
            $isMotorQuoteFlow = 'false';
        } else if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            $productCode = '2308';
            $isMotorQuote = 'true';
            $isMotorQuoteFlow = 'true';
            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                $tp_insurer_details = PreviousInsurerList::where('company_alias', 'reliance')
                    ->where('name', $proposal->tp_insurance_company)->first();

                $previous_tp_array = [
                    'TPPolicyNumber' => $proposal->tp_insurance_number,
                    'TPPolicyInsurer' => $tp_insurer_details->code ?? $proposal->tp_insurance_company,
                    'TPPolicyStartDate' => date('Y/m/d', strtotime($proposal->tp_start_date)),
                    'TPPolicyEndDate' => date('Y/m/d', strtotime($proposal->tp_end_date)),
                ];
            }
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $DateOfPurchase = date('d-m-Y', strtotime($requestData->vehicle_register_date));
        $vehicle_register_date = explode('-', $requestData->vehicle_register_date);
        $IsClaimedLastYear = 'false';

        if ($requestData->is_claim == 'Y'){
            $PreviousNCB = '0';
            $PreviousNCB = $ncb_levels[$requestData->previous_ncb];
            $IsNCBApplicable = 'false';
            $IsClaimedLastYear = 'true';
            $NCBEligibilityCriteria = '1';
        }

        if ($requestData->business_type == 'breakin' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
            if ($date_diff > 90) {
                $NCBEligibilityCriteria = '1';
                $PreviousNCB = '0';
                $IsNCBApplicable = 'false';
            }
        }

        $isPreviousPolicyDetailsAvailable = true;

        if (in_array($requestData->previous_policy_type, ['Not sure']) && $requestData->business_type != 'newbusiness') {
            // $BusinessType = '6';////6 means ownership change
            $isPreviousPolicyDetailsAvailable = false;
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $IsNCBApplicable = 'false';
        }
        if(in_array($requestData->previous_policy_type, ['Third-party']))
        {
            $NCBEligibilityCriteria = '1';
        }
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts', 'compulsory_personal_accident')
            ->first();

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = 'false';
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $cover_ll_paid_driver = 'false';
        $sel_RTI = "false";

        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = 'false';
                    $cover_pa_paid_driver_amt = '0';
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger = 'true';
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'LL paid driver' && isset($data['sumInsured'])) {
                    $cover_ll_paid_driver = 'true';
                }
            }
        }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;

        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $BiFuelKitSi = 0;

        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'Electrical Accessories') {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = (int) $data['sumInsured'];
                }
                if ($data['name'] == 'Non-Electrical Accessories') {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = (int) $data['sumInsured'];
                }
            }
        }
        
        $is_voluntary_deductible = 'false';
        $voluntary_deductible_amt = 0;
        $is_anti_theft = 'false';

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'voluntary_insurer_discounts' && !empty($data['sumInsured']) && $data['sumInsured'] != '0') {
                    $is_voluntary_deductible = 'true';
                    $voluntary_deductible_amt = $data['sumInsured'];
                }
                if ($data['name'] == 'TPPD Cover') {
                    $TPPDCover = 'true';
                }
                if ($data['name'] == 'anti-theft device') {
                    $is_anti_theft = 'true';
                }
            }
        }

        $cpa_selected = 'false';
        $IsHavingValidDrivingLicense = '';
        $IsOptedStandaloneCPAPolicy = '';

        if ($requestData->vehicle_owner_type == 'I' && !in_array($premium_type, ['own_damage', 'own_damage_breakin'])) {
            if (!empty($additional['compulsory_personal_accident'])) {
                foreach ($additional['compulsory_personal_accident'] as $key => $data) {
                    if (!empty($data['name']) && $data['name'] == 'Compulsory Personal Accident') {
                        $cpa_selected = 'true';
                        $cpa_tenure = isset($data['tenure']) ? (string) $data['tenure'] : '1';
                    } else if (!empty($data['reason'])) {
                        $cpa_selected = 'false';
                        if ($data['reason'] == 'I do not have a valid driving license.') {
                            $IsHavingValidDrivingLicense = 'false';
                        } else {
                            $IsOptedStandaloneCPAPolicy = 'true';
                        }
                    }
                }
            }
        }

        $type_of_fuel = $TypeOfFuel[strtolower($mmv_data->operated_by)];
        $IsClaimedLastYear = 'false';
        $IsNilDepreciation = $RoadSideAssistance = 'false';
        $IsVehicleHypothicated = ($proposal->is_vehicle_finance == '1' && $proposal->financer_agreement_type == '1') ? 'true' : 'false';
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $POSType = '';
        $POSAadhaarNumber = '';
        $POSPANNumber = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $idv <= 5000000) {
            $POSType = '2';
            $POSAadhaarNumber = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
            $POSPANNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
        }elseif(config('constants.motor.reliance.IS_POS_TESTING_MODE_ENABLE_RELIANCE') == 'Y' && $idv <= 5000000) {
            $POSType = '2';
            $POSPANNumber = 'ABGTY8890Z';
            $POSAadhaarNumber = '569278616999';
        }

        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Zero Depreciation' && !empty($data['premium'])) {
                    $IsNilDepreciation = 'true';
                }
                if ($data['name'] == 'Road Side Assistance' && !empty($data['premium'])) {
                    $RoadSideAssistance = 'true';
                }
                if ($data['name'] == 'Return To Invoice' ) {
                    $sel_RTI = "true";
                }
            }
        }
        $address_data = [
                    'address' => $proposal->address_line1,
                    'address_1_limit'   => 250,
                    'address_2_limit'   => 250
                ];
        $getAddress = getAddress($address_data);

        if ($requestData->vehicle_owner_type == 'I')
        {
            $ClientType = '0';
            $IsPAToOwnerDriverCoverd = $cpa_selected;
            $NomineeName = $proposal->nominee_name;
            $NomineeRelationship = $proposal->nominee_relationship;
            $NomineeAddress = trim($getAddress['address_1'].' '.$getAddress['address_2'].' '.$getAddress['address_3']);
            $NomineeDOB = date('d/m/Y', strtotime($proposal->nominee_dob));
            $Salutation = ($proposal->title == '1') ? 'Mr.' : 'Ms.';
            $ForeName = $proposal->first_name;
            $LastName = ! empty($proposal->last_name) ? $proposal->last_name : '.';
            $CorporateName = '';
            $OccupationID = $proposal->occupation;
            $DOB = date('Y-m-d', strtotime($proposal->dob));
            $Gender = $proposal->gender;
            $MaritalStatus = $proposal->marital_status == 'Single' ? '1952' : '1951';
            $OtherRelation = '';//APpointee relation
        }
        else
        {
            $ClientType = '1';
            $IsPAToOwnerDriverCoverd = 'false';
            $IsHavingValidDrivingLicense = '';
            $IsOptedStandaloneCPAPolicy = '';
            $NomineeName = '';
            $NomineeDOB = '';
            $NomineeRelationship = '';
            $NomineeAddress = '';
            $OtherRelation = '';
            $Salutation = 'M/S';
            $ForeName = '';
            $LastName = '';
            $CorporateName = $proposal->first_name;
            $OccupationID = '';
            $Occupation = '';
            $Gender = '';
            $DOB = '';
            $MaritalStatus = '';
        }

        $previous_insurance_details = [];
        $previous_insurer_name = '';
        $previous_policy_type = '';
        $previous_policy_number = '';
        if (!empty($proposal->previous_insurance_company) && $requestData->business_type != 'newbusiness') {
            /* $previousInsurer = PreviousInsurerList::where([
                'company_alias' => 'reliance',
                'code' => $proposal->previous_insurance_company
            ])->first();
            $previous_insurer_name = $previousInsurer->name; */
            if ($requestData->previous_policy_type == 'Comprehensive') {
                $previous_policy_type = '1';
            } else if ($requestData->previous_policy_type == 'Third-party') {
                $previous_policy_type = '2';
            }
            $previous_policy_number = $proposal->previous_policy_number;
        }

        // $registration_number = $proposal->vehicale_registration_number;
        // $registration_number = explode('-', $registration_number);

        // if ($registration_number[0] == 'DL') {
        //     $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true);
        //     $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3];
        // } else {
        //     $registration_number = $proposal->vehicale_registration_number;
        // }

        $UserID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_USERID_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_USERID_RELIANCE') : config('constants.IcConstants.reliance.USERID_RELIANCE');

        $SourceSystemID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE')) )? config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE') : config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE');

        $AuthToken = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE')) ) ? config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE') : config('constants.IcConstants.reliance.AUTH_TOKEN_RELIANCE');
        if(in_array($requestData->previous_policy_type, ['Not sure']))
        {
            $isPreviousPolicyDetailsAvailable = false;
            $previous_insurance_details = ['IsPreviousPolicyDetailsAvailable' => 'false'];
        }
        if ($isPreviousPolicyDetailsAvailable && ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')) {
            $previous_insurance_details = [
                'IsPreviousPolicyDetailsAvailable' => !empty($PrevYearPolicyEndDate) ? 'True' : 'False', // New tag added
                'PolicyNo' => $previous_policy_number,
                'PrevYearPolicyType' => $previous_policy_type,
                'PrevInsuranceID' => !empty($PrevYearPolicyEndDate) ? $proposal->previous_insurance_company : '',
                'PrevYearInsurer' => !empty($PrevYearPolicyEndDate) ? $proposal->previous_insurance_company : '',
                'PrevYearPolicyNo' => $previous_policy_number,
                'PrevYearInsurerAddress' => '',
                'PrevYearPolicyStartDate' => $PrevYearPolicyStartDate,
                'PrevYearPolicyEndDate' => $PrevYearPolicyEndDate,
                'PrevPolicyPeriod' => '1',
                'IsVehicleOfPreviousPolicySold' => 'false',
                'IsNCBApplicable' => $IsNCBApplicable,
                'MTAReason' => '',
                'PrevYearNCB' => $requestData->previous_ncb,
                'IsInspectionDone' => 'false',
                'InspectionDate' => '',
                'Inspectionby' => '',
                'InspectorName' => '',
                'IsNCBEarnedAbroad' => 'false',
                'ODLoading' => '',
                'IsClaimedLastYear' => $IsClaimedLastYear,
                'ODLoadingReason' => '',
                'PreRateCharged' => '',
                'PreSpecialTermsAndConditions' => '',
                'IsTrailerNCB' => 'false',
                'InspectionID' => '',
                'DocumentProof' => '',
            ];

            if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                $previous_insurance_details['IsPreviousPolicyDetailsAvailable'] = 'false';
                $previous_insurance_details['PolicyNo'] = '';
                $previous_insurance_details['PrevYearPolicyType'] = '';
                $previous_insurance_details['PrevInsuranceID'] = '';
                $previous_insurance_details['PrevYearInsurer'] = '';
                $previous_insurance_details['PrevYearPolicyNo'] = '';
                $previous_insurance_details['PrevYearPolicyStartDate'] = '';
                $previous_insurance_details['PrevYearPolicyEndDate'] = '';
            }
        }

        if ($BusinessType == 6) {
            $policy_start_date = date('Y-m-d', strtotime('+3 day'));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        $premium_req_array = [
            'CoverDetails'=> '',
            'TrailerDetails' => '',
            'ClientDetails' => [
                'ClientType' => $ClientType,
                'LastName' => '',
                'MidName' => '',
                'ForeName' => '',
                'CorporateName' => '',
                'OccupationID' => '',
                'DOB' => '',
                'Gender' => '',
                'PhoneNo' => '',
                'MobileNo' => '',
                'ClientAddress' => [
                    'CommunicationAddress' => [
                        'AddressType' => '0',
                        'Address1' => '',
                        'Address2' => '',
                        'Address3' => '',
                        'CityID' => '',
                        'DistrictID' => '',
                        'StateID' => '',
                        'Pincode' => '',
                        'Country' => '',
                        'NearestLandmark' => '',
                    ],
                    'PermanentAddress' => [
                        'AddressType' => '0',
                        'Address1' => '',
                        'Address2' => '',
                        'Address3' => '',
                        'CityID' => '',
                        'DistrictID' => '',
                        'StateID' => '',
                        'Pincode' => '',
                        'Country' => '',
                        'NearestLandmark' => '',
                    ],
                    'RegistrationAddress' => [
                        'AddressType' => '0',
                        'Address1' => '',
                        'Address2' => '',
                        'Address3' => '',
                        'CityID' => '',
                        'DistrictID' => '',
                        'StateID' => '',
                        'Pincode' => '',
                        'Country' => '',
                        'NearestLandmark' => '',
                    ],
                ],
                'EmailID' => '',
                'Salutation' => '',
                'MaritalStatus' => '',
                'Nationality' => '',
            ],
            'Policy' => [
                'AgentCode' => 'Direct',
                'AgentName' => 'Direct',
                'BusinessType' => $BusinessType,
                'Branch_Name' => 'Direct',
                'Cover_From' => $policy_start_date,
                'Cover_To' => $policy_end_date,
                'Branch_Code' => '9202',
                'productcode' => $productCode,
                'OtherSystemName' => '1',
                'isMotorQuote' => 'false',//$isMotorQuote,
                'isMotorQuoteFlow' => '',//$isMotorQuoteFlow,
                'POSType' => $POSType,
                'POSAadhaarNumber' => $POSAadhaarNumber,
                'POSPANNumber' => $POSPANNumber,
            ],
            'Risk' => [
                'VehicleMakeID' => $mmv_data->make_id_pk,
                'VehicleModelID' => $mmv_data->model_id_pk,
                'StateOfRegistrationID' => $rto_data->state_id_fk,
                'RTOLocationID' => $rto_data->model_region_id_pk,
                'Rto_RegionCode' => $rto_data->region_code,
                'Zone' => $rto_data->model_zone_name,
                'Colour' => '',
                'BodyType' => '',
                'OtherColour' => '',
                'GrossVehicleWeight' => '',
                'CubicCapacity' => '',
                'ExShowroomPrice' => '',
                'IDV' => $tp_only ? 0 : $idv,
                'DateOfPurchase' => $DateOfPurchase,
                "ManufactureMonth" => $vehicle_register_date[1],
                "ManufactureYear" => $vehicle_register_date[2],
                'VehicleVariant' => $mmv_data->variance,
                'IsHavingValidDrivingLicense' => ($IsPAToOwnerDriverCoverd == 'false') ? $IsHavingValidDrivingLicense : '',
                'IsOptedStandaloneCPAPolicy' => ($IsPAToOwnerDriverCoverd == 'false') ? (($IsHavingValidDrivingLicense == 'true') ? $IsOptedStandaloneCPAPolicy : '') : '',
                'LicensedCarryingCapacity' => '',
                'NoOfWheels' => '',
                'PurposeOfUsage' => '',
                'EngineNo' => removeSpecialCharactersFromString($proposal->engine_number),
                'Chassis' => removeSpecialCharactersFromString($proposal->chassis_number),
                'TrailerIDV' => '',
                'IsVehicleHypothicated' => $IsVehicleHypothicated,
                'FinanceType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                'FinancierName' => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                'FinancierAddress' => ($proposal->is_vehicle_finance == '1') ?  $proposal->financer_location: '',
                'FinancierCity' => ($proposal->is_vehicle_finance == '1') ?  $proposal->financer_location: '',
                'IsRegAddressSameasCommAddress' => $proposal->is_car_registration_address_same == 1 ? 'true' : 'false',
                'IsRegAddressSameasPermanentAddress' => $proposal->is_car_registration_address_same == 1 ? 'true' : 'false',
                'IsPermanentAddressSameasCommAddress' => 'true',
                'SalesManagerCode' => 'Direct',
                'SalesManagerName' => 'Direct',
                'BodyIDV' => '0',
                'ChassisIDV' => '0',
                'Rto_State_City' => '',
            ],
            'Vehicle' => [
                'TypeOfFuel' => $type_of_fuel,
                'ISNewVehicle' => $ISNewVehicle ? 'true' : 'false',
                'Registration_Number' => $registration_number,
                'Registration_date' => $DateOfPurchase,
                'SeatingCapacity' => $mmv_data->seating_capacity,
                'MiscTypeOfVehicle' => '',
                'MiscTypeOfVehicleID' => '',
                'RegistrationNumber_New' => '',
                'RoadTypes' => [
                    'RoadType' => [
                        'RoadTypeID' => '',
                        'TypeOfRoad' => '',
                    ],
                ],
                'Permit' => [
                    'PermitType' => [
                        'TypeOfPermit' => '',
                    ],
                ],
            ],
            'Cover' => [
                'PACoverToNamedPassengerSI' => '0',
                'IsPAToUnnamedPassengerCovered' => $cover_pa_unnamed_passenger,
                'UnnamedPassengersSI' => $cover_pa_unnamed_passenger_amt,
                'IsRacingCovered' => 'false',
                'IsLossOfAccessoriesCovered' => 'false',
                'IsVoluntaryDeductableOpted' => $is_voluntary_deductible,
                'VoluntaryDeductableAmount' => $voluntary_deductible_amt,
                'IsElectricalItemFitted' =>  $IsElectricalItemFitted,
                'ElectricalItemsTotalSI' => $ElectricalItemsTotalSI,
                'IsNonElectricalItemFitted' => $IsNonElectricalItemFitted,
                'NonElectricalItemsTotalSI' => $NonElectricalItemsTotalSI,
                'IsGeographicalAreaExtended' => 'false',
                'IsBiFuelKit' => 'false',
                'BiFuelKitSi' => '0',
                'IsAutomobileAssociationMember' => 'false',
                'IsVehicleMadeInIndia' => 'false',
                'IsUsedForDrivingTuition' => 'false',
                'IsInsuredAnIndividual' => 'false',
                'IsIndividualAlreadyInsured' => 'false',
                'IsPAToOwnerDriverCoverd' => $IsPAToOwnerDriverCoverd,
                'ISLegalLiabilityToDefenceOfficialDriverCovered' => 'false',
                'IsLiabilityToPaidDriverCovered' => $cover_ll_paid_driver,
                'IsLiabilityToEmployeeCovered' => 'false',
                'IsPAToDriverCovered' => 'false',
                'IsPAToPaidCleanerCovered' => 'false',
                'IsAdditionalTowingCover' => 'false',
                'IsLegalLiabilityToCleanerCovered' => 'false',
                'IsLegalLiabilityToNonFarePayingPassengersCovered' => 'false',
                'IsLegalLiabilityToCoolieCovered' => 'false',
                'IsCoveredForDamagedPortion' => 'false',
                'IsImportedVehicle' => 'false',
                'IsFibreGlassFuelTankFitted' => 'false',
                'IsConfinedToOwnPremisesCovered' => 'false',
                'IsAntiTheftDeviceFitted' => $is_anti_theft,
                'IsTPPDLiabilityRestricted' => 'false',
                'IsTPPDCover' => $TPPDCover,
                'IsBasicODCoverage' => 'true',
                'IsBasicLiability' => $TPPDCover,
                'IsUseOfVehiclesConfined' => 'false',
                'IsTotalCover' => 'false',
                'IsRegistrationCover' => 'false',
                'IsRoadTaxcover' => 'false',
                'IsInsurancePremium' => 'false',
                'IsCoverageoFTyreBumps' => 'false',
                'IsImportedVehicleCover' => 'false',
                'IsVehicleDesignedAsCV' => 'false',
                'IsWorkmenCompensationExcludingDriver' => 'false',
                'IsLiabilityForAccidentsInclude' => 'false',
                'IsLiabilityForAccidentsExclude' => 'false',
                'IsLiabilitytoCoolie' => 'false',
                'IsLiabilitytoCleaner' => 'false',
                'IsLiabilityToConductor' => 'false',
                'IsPAToConductorCovered' => 'false',
                'IsNFPPIncludingEmployees' => 'false',
                'IsNFPPExcludingEmployees' => 'false',
                'IsNCBRetention' => 'false',
                'IsHandicappedDiscount' => 'false',
                'IsTrailerAttached' => 'false',
                'cAdditionalCompulsoryExcess' => '0',
                'iNumberOfLegalLiabilityCoveredPaidDrivers' => '0',
                'NoOfLiabilityCoveredEmployees' => '0',
                'PAToDriverSI' => '0',
                'PAToCleanerSI' => '0',
                'NumberOfPACoveredPaidDrivers' => '0',
                'NoOfPAtoPaidCleanerCovered' => '0',
                'AdditionalTowingCharge' => '0',
                'NoOfLegalLiabilityCoveredCleaners' => '0',
                'NoOfLegalLiabilityCoveredNonFarePayingPassengers' => '0',
                'NoOfLegalLiabilityCoveredCoolies' => '0',
                'iNoOfLegalLiabilityCoveredPeopleOtherThanPaidDriver' => '0',
                'ISLegalLiabilityToConductorCovered' => 'false',
                'NoOfLegalLiabilityCoveredConductors' => '0',
                'PAToConductorSI' => '0',
                'CompulsoryDeductible' => '0',
                'PACoverToOwnerDriver' => $IsPAToOwnerDriverCoverd == 'true' ? '1' : '0',
                'ElectricItems' => [
                    'ElectricalItems' => [
                        'ElectricalItemsID' => '',
                        'PolicyId' => '',
                        'SerialNo' => '',
                        'MakeModel' => '',
                        'ElectricPremium' => '',
                        'Description' => '',
                        'ElectricalAccessorySlNo' => '',
                        'SumInsured' => $ElectricalItemsTotalSI,
                    ],
                ],
                'NonElectricItems' => [
                    'NonElectricalItems' => [
                        'NonElectricalItemsID' => '',
                        'PolicyID' => '',
                        'SerialNo' => '',
                        'MakeModel' => '',
                        'NonElectricPremium' => '',
                        'Description' => '',
                        'Category' => '',
                        'NonElectricalAccessorySlNo' => '',
                        'SumInsured' => $NonElectricalItemsTotalSI,
                    ],
                ],
                'BasicODCoverage' => [
                    'BasicODCoverage' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'GeographicalExtension' => [
                    'GeographicalExtension' => [
                        'Countries' => '',
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                    ],
                ],
                'BifuelKit' => [
                    'BifuelKit' => [
                        'IsChecked' => 'false',
                        'IsMandatory' => 'false',
                        'PolicyCoverDetailsID' => '',
                        'Fueltype' => '',
                        'ISLpgCng' => 'false',
                        'PolicyCoverID' => '',
                        'SumInsured' => '0',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'DrivingTuitionCoverage' => [
                    'DrivingTuitionCoverage' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'FibreGlassFuelTank' => [
                    'FibreGlassFuelTank' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'AdditionalTowingCoverage' => [
                    'AdditionalTowingCoverage' => [
                        'IsMandatory' => 'false',
                        'PolicyCoverID' => '',
                        'SumInsured' => '0',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'VoluntaryDeductible' => [
                    'VoluntaryDeductible' => [
                        'IsMandatory' => 'false',
                        'PolicyCoverID' => '',
                        'IsChecked' => $is_voluntary_deductible,
                        'SumInsured' => $voluntary_deductible_amt,
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'AntiTheftDeviceDiscount' => [
                    'AntiTheftDeviceDiscount' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => $is_anti_theft,
                        'NoOfItems' => '1',
                        'PackageName' => '',
                    ],
                ],
                'SpeciallyDesignedforChallengedPerson' => [
                    'SpeciallyDesignedforChallengedPerson' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'AutomobileAssociationMembershipDiscount' => [
                    'AutomobileAssociationMembershipDiscount' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'UseOfVehiclesConfined' => [
                    'UseOfVehiclesConfined' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'TotalCover' => [
                    'TotalCover' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'RegistrationCost' => [
                    'RegistrationCost' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'SumInsured' => '0',
                    ],
                ],
                'RoadTax' => [
                    'RoadTax' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'SumInsured' => '0',
                        'PolicyCoverID' => '',
                    ],
                ],
                'InsurancePremium' => [
                    'InsurancePremium' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'NilDepreciationCoverage' => [
                    'NilDepreciationCoverage' => [
                        'IsMandatory' => $IsNilDepreciation,
                        'IsChecked' => $IsNilDepreciation,
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                        'ApplicableRate' => '',
                    ],
                ],
                'BasicLiability' => [
                    'BasicLiability' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
                'TPPDCover' => [
                    'TPPDCover' =>  [
                        'SumInsured' => ($TPPDCover == 'true') ? 6000 : 0,
                        'IsMandatory' => 'false',
                        'PolicyCoverID' => "",
                        'IsChecked' => $TPPDCover,
                        'NoOfItems' => "",
                        'PackageName' => "",
                    ],
                ],
                'PACoverToOwner' => [
                    'PACoverToOwner' => [
                        'IsMandatory' => 'true',
                        'IsChecked' => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                        'CPAcovertenure' => ($requestData->vehicle_owner_type == 'I') ? $cpa_tenure : '',
                        'NoOfItems' => ($requestData->vehicle_owner_type == 'I') ? '1' : '',
                        'PackageName' => '',
                        'AppointeeName' => '',
                        'NomineeName' => $NomineeName,
                        'NomineeDOB' => $NomineeDOB,
                        'NomineeRelationship' => $NomineeRelationship,
                        'NomineeAddress' => $NomineeAddress,
                        'OtherRelation' => $OtherRelation,
                    ],
                ],
                'PAToNamedPassenger' => [
                    'PAToNamedPassenger' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'SumInsured' => '',
                        'PassengerName' => '',
                        'NomineeName' => '',
                        'NomineeDOB' => '',
                        'NomineeRelationship' => '',
                        'NomineeAddress' => '',
                        'OtherRelation' => '',
                        'AppointeeName' => '',
                    ],
                ],
                'PAToUnNamedPassenger' => [
                    'PAToUnNamedPassenger' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => $cover_pa_unnamed_passenger,
                        'NoOfItems' => '1',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                        'SumInsured' => $cover_pa_unnamed_passenger_amt,
                    ],
                ],
                'PAToPaidDriver' => [
                    'PAToPaidDriver' => [
                        'IsMandatory' => $cover_pa_paid_driver,
                        'IsChecked' => $cover_pa_paid_driver,
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                        'SumInsured' => $cover_pa_paid_driver_amt,
                    ],
                ],
                'PAToPaidCleaner' => [
                    'PAToPaidCleaner' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                        'SumInsured' => '0',
                    ],
                ],
                'LiabilityToPaidDriver' => [
                    'LiabilityToPaidDriver' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => $cover_ll_paid_driver,
                        'NoOfItems' => '1',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                    ],
                ],
                'LiabilityToEmployee' => [
                    'LiabilityToEmployee' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                    ],
                ],
                'NFPPIncludingEmployees' => [
                    'NFPPIncludingEmployees' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '0',
                    ],
                ],
                'NFPPExcludingEmployees' => [
                    'NFPPExcludingEmployees' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                    ],
                ],
                'WorkmenCompensationExcludingDriver' => [
                    'WorkmenCompensationExcludingDriver' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '0',
                    ],
                ],
                'PAToConductor' => [
                    'PAToConductor' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'SumInsured' => '',
                    ],
                ],
                'LiabilityToConductor' => [
                    'LiabilityToConductor' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '0',
                    ],
                ],
                'LiabilitytoCoolie' => [
                    'LiabilitytoCoolie' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '0',
                    ],
                ],
                'LegalLiabilitytoCleaner' => '',
                'IndemnityToHirer' => [
                    'IndemnityToHirer' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                    ],
                ],
                'TrailerDetails' => [
                    'TrailerInfo' => [
                        'MakeandModel' => '',
                        'IDV' => '1',
                        'Registration_No' => '',
                        'ChassisNumber' => '',
                        'ManufactureYear' => '',
                        'SerialNumber' => '',
                    ],
                ],
                'IsSpeciallyDesignedForHandicapped' => 'false',
                'IsPAToNamedPassenger' => 'false',
                'IsOverTurningCovered' => 'false',
                'IsLLToPersonsEmployedInOperations_PaidDriverCovered' => 'false',
                'NoOfLLToPersonsEmployedInOperations_PaidDriver' => '0',
                'IsLLToPersonsEmployedInOperations_CleanerConductorCoolieCovered' =>
                'false',
                'NoOfLLToPersonsEmployedInOperations_CleanerConductorCoolie' => '0',
                'IsLLUnderWCActForCarriageOfMoreThanSixEmpCovered' => 'false',
                'NoOfLLUnderWCAct' => '1',
                'IsLLToNFPPNotWorkmenUnderWCAct' => 'false',
                'NoOfLLToNFPPNotWorkmenUnderWCAct' => '0',
                'IsIndemnityToHirerCovered' => 'false',
                'IsAccidentToPassengerCovered' => 'false',
                'NoOfAccidentToPassengerCovered' => '0',
                'IsDetariffRateForOverturning' => 'false',
                'IsAddOnCoverforTowing' => 'false',
                'AddOnCoverTowingCharge' => '0',
                'EMIprotectionCover' => '',
            ],
            'PreviousInsuranceDetails' => $previous_insurance_details,
            'NCBEligibility' => [
                'NCBEligibilityCriteria' => $NCBEligibilityCriteria,
                'NCBReservingLetter' => '',
                'PreviousNCB' => $PreviousNCB,
            ],
            'LstCoveragePremium' => '',
            'ProductCode' => $productCode,
            'UserID' => $UserID,
            'SourceSystemID' => $SourceSystemID,
            'AuthToken' => $AuthToken,
        ];

        if ($ISNewVehicle) {
            unset($premium_req_array['PreviousInsuranceDetails']);
        }
        if($is_renewal_journey) {
            $vehicle_registration_date = DateTime::createFromFormat('d/m/Y', $Vehicle->Registration_date);
            $new_format_DateOfPurchase = $vehicle_registration_date->format('Y-m-d');
            $new_format_registration_date = $vehicle_registration_date->format('Y-m-d');

            $reg_no = explode('-', $Vehicle->Registration_Number);
            $premium_req_array['Policy']['isMotorQuote'] = 'true';
            $premium_req_array['Policy']['isMotorQuoteFlow'] = 'true';
            $premium_req_array['Policy']['BusinessType'] = $RenewalPolicy->BusinessTypeid;
            $premium_req_array['ClientDetails']['Salutation'] = $ClientDetails_fetch->Salutation;        
            $premium_req_array['ClientDetails']['ForeName'] = $ClientDetails_fetch->ForeName;       
            $premium_req_array['ClientDetails']['LastName'] = $ClientDetails_fetch->LastName;        
            $premium_req_array['ClientDetails']['Gender'] = $ClientDetails_fetch->Gender;        
            $premium_req_array['ClientDetails']['DOB'] = $ClientDetails_fetch->DOB;
            $premium_req_array['ClientDetails']['OccupationID'] = $ClientDetails_fetch->OccupationID;  
            if ($premium_type == 'comprehensive') {
                $premium_req_array['Policy']['productcode'] = 2312;
                $premium_req_array['ProductCode'] = 2312;
            } else {
                $premium_req_array['Policy']['productcode'] = $RenewalPolicy->productcode;
                $premium_req_array['ProductCode'] = $RenewalPolicy->productcode;
            }
            $premium_req_array['NCBEligibility']['NCBEligibilityCriteria'] = $NCBEligibility->NCBEligibilityCriteria;
            $premium_req_array['NCBEligibility']['PreviousNCB'] = $PreviousNCBId;
            $premium_req_array['NCBEligibility']['CurrentNCB'] = $CurrentNCBId;

            $premium_req_array['Risk']['VehicleMakeID'] = $Vehicle->VehicleMakeID;
            $premium_req_array['Risk']['VehicleModelID'] = $Vehicle->VehicleModelID;
            $premium_req_array['Risk']['CubicCapacity'] = $Vehicle->CubicCapacity;
            $premium_req_array['Risk']['Zone'] = $Vehicle->Zone;
            $premium_req_array['Risk']['RTOLocationID'] =  $Vehicle->RTOLocationID;
            $premium_req_array['Risk']['ExShowroomPrice'] = $Vehicle->ExShowroomPrice;
            $premium_req_array['Risk']['IDV'] = $Vehicle->IDV;
            $premium_req_array['Risk']['DateOfPurchase'] = $new_format_DateOfPurchase; #date('Y-m-d', strtotime($Vehicle->DateOfPurchase));
            $premium_req_array['Risk']['ManufactureMonth'] = date('m', strtotime($Vehicle->DateOfPurchase));
            $premium_req_array['Risk']['ManufactureYear'] = date('Y', strtotime($Vehicle->DateOfPurchase));
            $premium_req_array['Risk']['EngineNo'] = removeSpecialCharactersFromString($Vehicle->EngineNo);
            $premium_req_array['Risk']['Chassis'] = removeSpecialCharactersFromString($Vehicle->Chassis);
            $premium_req_array['Risk']['VehicleVariant'] = $Vehicle->VehicleVariant;
            $premium_req_array['Risk']['StateOfRegistrationID'] = $Vehicle->RTOstateID;
            $premium_req_array['Risk']['Rto_RegionCode'] = $reg_no[0] . '-' . $reg_no[1];

            $premium_req_array['Vehicle']['Registration_date'] = $new_format_registration_date; #date('Y-m-d', strtotime($Vehicle->Registration_date));
            $premium_req_array['Vehicle']['Registration_Number'] = $Vehicle->Registration_Number;

            $premium_req_array['PreviousInsuranceDetails']['IsPreviousPolicyDetailsAvailable'] = 'true';
            $premium_req_array['PreviousInsuranceDetails']['PrevYearInsurer'] = '11';
            $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyNo'] = $PreviousInsuranceDetails->PrevYearPolicyNo;
            // $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyStartDate'] = date('Y-m-d', strtotime(str_replace('/', '-',$PreviousInsuranceDetails->PrevYearPolicyStartDate)));
            // $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyEndDate'] = date('Y-m-d', strtotime(str_replace('/', '-',$PreviousInsuranceDetails->PrevYearPolicyEndDate)));
            $premium_req_array['PreviousInsuranceDetails']['IsNCBApplicable'] = ($NCBEligibility->NCBEligibilityCriteria == '2') ? 'true' : 'false';
            $premium_req_array['PreviousInsuranceDetails']['IsClaimedLastYear'] = $Vehicle->IsClaimedLastYear;
        }
        $agentDiscount = calculateAgentDiscount($enquiryId, 'reliance', 'bike');
        if ($agentDiscount['status'] ?? false) {
            $premium_req_array['Vehicle']['ODDiscount'] = $agentDiscount['discount'];
        } else {
            if (!empty($agentDiscount['message'] ?? '')) {
                return [
                    'status' => false,
                    'message' => $agentDiscount['message']
                ];
            }
        }

        if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            $premium_req_array = array_merge_recursive($premium_req_array, ['Policy' => $previous_tp_array]);
        }
        $RTI2w = config('constants.IcConstants.reliance.RTI2W');
        $enable2wRTI = ( $RTI2w == 'Y' ) ? true : false;
        if($enable2wRTI && $sel_RTI=="true")
        {
           $premium_req_array['Cover'] += [
                "IsReturntoInvoice" => "true",
                "ReturntoInvoiceCoverage" => [
                    "AddonSumInsuredFlatRates" => [
                        "IsChecked"                                 =>"true",
                        "addonOptedYesRate"                         =>"3.456",
                        "addonOptedNoRate"                          =>"7.538",
                        "isOptedByCustomer"                         =>"true",
                        "isOptedByCustomerRate"                     =>"addonOptedYesRate",
                        "addonYesMultiplicationFactorRate"          =>"12.356",
                        "addonNoMultiplicationFactorRate"           =>"11.121",
                        "ageofVehicleRate"                          =>"1.12",
                        "vehicleCCRate"                             =>"1.11",
                        "zoneRate"                                  =>"1.4",
                        "parkingRate"                               =>"1.1",
                        "driverAgeRate"                             =>"1.2",
                        "ncbApplicabilityRate"                      =>"1.4",
                        "noOfVehicleUserRate"                       =>"1.4",
                        "occupationRate"                            =>"1.0",
                        "policyIssuanceMethodRate"                  =>"1.4",
                        "existingRGICustomerRate"                   =>"1.4",
                        "addonLastYearYesRate"                      =>"1.4",
                        "addonLastYearNoRate"                       =>"1.26",
                    ]
                ]
           ];
        }

        $premium_req_array = trim_array($premium_req_array);

        $get_response = getWsData(
            Config::get('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_COVERAGE'),
            $premium_req_array,
            'reliance',
            [
                'root_tag' => 'PolicyDetails',
                'section' => $productData->product_sub_type_code,
                'method' => 'Coverage Calculation',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name. " ($business_type)",
                'transaction_type' => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ]
            ]
        );
        $coverage_res_data = $get_response['response'];
        $send_in_proposal=false;
        if ($coverage_res_data) {
            $coverage_res_data = json_decode($coverage_res_data);

            if (empty($coverage_res_data->ErrorMessages)) {
                $nil_dep_rate = '';
                if (isset($coverage_res_data->LstAddonCovers)) {
                    foreach ($coverage_res_data->LstAddonCovers as $k => $v) {
                        if ($v->CoverageName == 'Nil Depreciation') {
                            $nil_dep_rate = $v->rate;
                        }
                        if($v->CoverageName=='Return to Invoice' && $enable2wRTI)
                        {
                        if(!empty($v->ReturntoInvoice[0] && $sel_RTI=="true"))
                        {
                            $coverage_rti = json_decode(json_encode($v->ReturntoInvoice[0]->RelativityFactor),true) ;
                            extract($coverage_rti);
                            $premium_req_array['Cover']['ReturntoInvoiceCoverage']['AddonSumInsuredFlatRates'] = [
                                'IsChecked' => "true",
                                'isOptedByCustomer' => "true",
                                'rate' => $v -> ReturntoInvoice[0] -> rate,
                                'isOptedByCustomerRate' => 'addonOptedYesRate',
                                'addonOptedYesRate' => $v -> ReturntoInvoice[0] -> addonOptedYesRate,
                                'addonOptedNoRate' => $v -> ReturntoInvoice[0] -> addonOptedNoRate,
                                'addonYesMultiplicationFactorRate' =>  $addonYesMultiplicationFactorRate,
                                'addonNoMultiplicationFactorRate' =>  $addonNoMultiplicationFactorRate,
                                'ageofVehicleRate'  =>  $ageofVehicleRate,
                                'vehicleCCRate' =>  $vehicleCCRate,
                                'zoneRate' =>  $zoneRate,
                                'parkingRate' =>  $parkingRate,
                                'drivingAgeRate' =>  $driverAgeRate,
                                'ncbApplicableRate' =>  $ncbApplicabilityRate,
                                'noOfVehicleUserRate' =>  $noOfVehicleUserRate,
                                'occupationRate' =>  $occupationRate,
                                'policyIssuanceMethodRate' =>  $policyIssuanceMethodRate,
                                'existingRGICustomerRate' =>  $existingRGICustomerRate,
                                'addonLastYearYesRate' =>  $addonLastYearYesRate,
                                'addonLastYearNoRate' =>  $addonLastYearNoRate,
                            ];
                            $send_in_proposal = true;
                            $RTIProposal_arr = $premium_req_array['Cover']['ReturntoInvoiceCoverage'];
                        }
                        else
                        {
                            unset($premium_req_array['Cover']['ReturntoInvoiceCoverage']);
                            unset($premium_req_array['Cover']['IsReturntoInvoice']);
                            $send_in_proposal = false;
                        }
                    }
                    }

                    if ($IsNilDepreciation == 'true') {
                        $premium_req_array['Cover']['IsNilDepreciation'] = $IsNilDepreciation;
                        $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                    }

                    $get_response = getWsData(
                        config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PREMIUM'),
                        $premium_req_array,
                        'reliance',
                        [
                            'root_tag' => 'PolicyDetails',
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Premium Calculation',
                            'requestMethod' => 'post',
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name. " ($business_type)",
                            'transaction_type' => 'proposal',
                            'headers' => [
                                'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                            ]
                        ]
                    );
                    $premium_res_data = $get_response['response'];

                    if ($premium_res_data) {
                        $motorPolicy = json_decode($premium_res_data);
                        if (!isset($motorPolicy->MotorPolicy)) {
                            return [
                                'status'  => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => "Insurer not reachable. Please try again after sometime."
                            ];
                        }
                        $motorPolicy = $motorPolicy->MotorPolicy;
                        unset($premium_res_data);
                        if (trim($motorPolicy->ErrorMessages) == '') {
                            $basic_od = 0;
                            $tppd = 0;
                            $pa_owner = 0;
                            $pa_unnamed = 0;
                            $pa_paid_driver = 0;
                            $electrical_accessories = 0;
                            $non_electrical_accessories = 0;
                            $zero_dep_amount = 0;
                            $ncb_discount = 0;
                            $lpg_cng = 0;
                            $lpg_cng_tp = 0;
                            $automobile_association = 0;
                            $anti_theft = 0;
                            $liabilities = 0;
                            $voluntary_deductible = 0;
                            $tppd_discount = 0;
                            $other_discount = 0;
                            $idv = $motorPolicy->IDV;
                            $RTIAddonPremium = 0;
                            $basic_own_damage = 0;
                            $geog_Extension_OD_Premium = 0;
                            $geog_Extension_TP_Premium = 0;

                            $corres_address_data = DB::table('reliance_pincode_state_city_master')
                                ->where('pincode',$proposal->pincode)
                                ->select('*')
                                ->first();

                            if ($requestData->vehicle_owner_type == "I") {
                                if (in_array(strtoupper($proposal->gender), ['MALE', 'M']))
                                {
                                    $Salutation = 'Mr.';
                                }
                                else{
                                    if ((in_array(strtoupper($proposal->gender), ['FEMALE', 'F'])) && $proposal->marital_status == "Single") {
                                        $Salutation = 'Miss.';
                                    } else {
                                        $Salutation = 'Mrs.';
                                    }
                                }
                            }
                            else{
                                $Salutation = 'M/S.';
                            }

                            $proposal_array = [
                                'ClientDetails' => [
                                    'ClientType' => $ClientType,
                                    'Salutation' => $Salutation,
                                    'ForeName' => $ForeName,
                                    'LastName' => $LastName,
                                    'MidName' => '',
                                    'CorporateName' => $CorporateName,
                                    'OccupationID' => $OccupationID,
                                    'DOB' => $DOB,
                                    'Gender' => $Gender,
                                    'PhoneNo' => '',
                                    'MobileNo' => $proposal->mobile_number,
                                    'RegisteredUnderGST' => (($proposal->gst_number == '') ? '0' : '1'),
                                    'RelatedParty' => '0',
                                    'GSTIN' => $proposal->gst_number,
                                    'PAN_Card' => $proposal->pan_number,
                                    'GroupCorpID' => '',
                                    'ClientAddress' => [
                                        'CommunicationAddress' => [
                                            'AddressType' => '0',
                                            'Address1'        => trim($getAddress['address_1']),
                                            'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                            'Address3'        => trim($getAddress['address_3']),
                                            'CityID' => $corres_address_data->city_or_village_id_pk,
                                            'DistrictID' => $corres_address_data->district_id_pk,
                                            'StateID' => $corres_address_data->state_id_pk,
                                            'Pincode' => $proposal->pincode,
                                            'Country' => '1',
                                            'NearestLandmark' => '',
                                        ],
                                        'RegistrationAddress' => [
                                            'AddressType' => '0',
                                            'Address1'        => trim($getAddress['address_1']),
                                            'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                            'Address3'        => trim($getAddress['address_3']),
                                            'CityID' => $corres_address_data->city_or_village_id_pk,
                                            'DistrictID' => $corres_address_data->district_id_pk,
                                            'StateID' => $corres_address_data->state_id_pk,
                                            'Pincode' => $proposal->pincode,
                                            'Country' => '1',
                                            'NearestLandmark' => '',
                                        ],
                                        'PermanentAddress' => [
                                            'AddressType' => '0',
                                            'Address1'        => trim($getAddress['address_1']),
                                            'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                            'Address3'        => trim($getAddress['address_3']),
                                            'CityID' => $corres_address_data->city_or_village_id_pk,
                                            'DistrictID' => $corres_address_data->district_id_pk,
                                            'StateID' => $corres_address_data->state_id_pk,
                                            'Pincode' => $proposal->pincode,
                                            'Country' => '1',
                                            'NearestLandmark' => '',
                                        ],
                                    ],
                                    'EmailID' => $proposal->email,
                                    'MaritalStatus' => $MaritalStatus,
                                    'Nationality' => '1949'
                                ],
                                'Policy' => [
                                    'BusinessType' => $BusinessType,
                                    'AgentCode' => 'Direct',
                                    'AgentName' => 'Direct',
                                    'Branch_Name' => 'Direct',
                                    'Cover_From' => $policy_start_date,
                                    'Cover_To' => $policy_end_date,
                                    'Branch_Code' => '9202',
                                    'productcode' => $productCode,
                                    'OtherSystemName' => '1',
                                    'isMotorQuote' => $isMotorQuote,
                                    'isMotorQuoteFlow' => $isMotorQuoteFlow,
                                    'POSType' => $POSType,
                                    'POSAadhaarNumber' => $POSAadhaarNumber,
                                    'POSPANNumber' => $POSPANNumber,
                                ],
                                'Risk' => [
                                    'VehicleMakeID' => $mmv_data->make_id_pk,
                                    'VehicleModelID' => $mmv_data->model_id_pk,
                                    'StateOfRegistrationID' => $rto_data->state_id_fk,
                                    'RTOLocationID' => $rto_data->model_region_id_pk,
                                    'Zone' => $Vehicle->Zone,
                                    'ExShowroomPrice' => '0',
                                    'IDV' => $tp_only ? 0 : $idv,
                                    'DateOfPurchase' => $DateOfPurchase,
                                    'ManufactureMonth' => $vehicle_register_date[1],
                                    'ManufactureYear' => $vehicle_register_date[2],
                                    'EngineNo' => removeSpecialCharactersFromString($proposal->engine_number),
                                    'Chassis' => removeSpecialCharactersFromString($proposal->chassis_number),
                                    'IsRegAddressSameasCommAddress' => $proposal->is_car_registration_address_same == '1' ? 'true' : 'false',
                                    'IsRegAddressSameasPermanentAddress' => $proposal->is_car_registration_address_same == '1' ? 'true' : 'false',
                                    'IsPermanentAddressSameasCommAddress' => 'true',
                                    'VehicleVariant' => $mmv_data->variance,
                                    'IsVehicleHypothicated' => $IsVehicleHypothicated,
                                    'FinanceType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '',
                                    'FinancierName' => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                                    'FinancierAddress' => ($proposal->is_vehicle_finance == '1') ?  $proposal->hypothecation_city: '',
                                    'IsHavingValidDrivingLicense' => ($IsPAToOwnerDriverCoverd == 'false') ? $IsHavingValidDrivingLicense : '',
                                    'IsOptedStandaloneCPAPolicy' => ($IsPAToOwnerDriverCoverd == 'false') ? (($IsHavingValidDrivingLicense == 'true') ? $IsOptedStandaloneCPAPolicy : '') : '',
                                ],
                                'Vehicle' => [
                                    'TypeOfFuel' => $type_of_fuel,
                                    'ISNewVehicle' => $ISNewVehicle ? 'true' : 'false',
                                    'Registration_Number' => $registration_number,
                                    'Registration_date' => $DateOfPurchase,
                                    'MiscTypeOfVehicleID' => '',
                                ],
                                'Cover' => [
                                    'IsPAToUnnamedPassengerCovered' => $cover_pa_unnamed_passenger,
                                    'IsVoluntaryDeductableOpted' => $is_voluntary_deductible,
                                    'IsElectricalItemFitted' =>  $IsElectricalItemFitted,
                                    'ElectricalItemsTotalSI' => $ElectricalItemsTotalSI,
                                    'IsPAToOwnerDriverCoverd' => $IsPAToOwnerDriverCoverd,
                                    'IsTPPDCover' => $TPPDCover,
                                    'IsBasicODCoverage' => 'true',
                                    'IsBasicLiability' => $TPPDCover,
                                    'IsNonElectricalItemFitted' => $IsNonElectricalItemFitted,
                                    'NonElectricalItemsTotalSI' => $NonElectricalItemsTotalSI,
                                    'IsPAToDriverCovered' => 'false',
                                    'IsNilDepreciation' => $IsNilDepreciation,
                                    'IsLiabilityToPaidDriverCovered' => $cover_ll_paid_driver,
                                    'IsBiFuelKit' => 'false',
                                    'IsBifuelTypeChecked' => 'false',
                                    'IsInsurancePremium' => 'false',
                                    'IsAntiTheftDeviceFitted' => $is_anti_theft,
                                    'VoluntaryDeductible' => [
                                        'VoluntaryDeductible' => [
                                            'SumInsured' => $voluntary_deductible_amt,
                                        ],
                                    ],
                                    'PACoverToOwnerDriver' => $IsPAToOwnerDriverCoverd,
                                    'PACoverToOwner' => [
                                        'PACoverToOwner' => [
                                            'IsChecked' => $IsPAToOwnerDriverCoverd,
                                            'NoOfItems' => '',
                                            'CPAcovertenure' => $cpa_tenure,
                                            'PackageName' => '',
                                            'NomineeName' => $NomineeName,
                                            'NomineeDOB' => $NomineeDOB,
                                            'NomineeRelationship' => $NomineeRelationship,
                                            'NomineeAddress' => $NomineeAddress,
                                            'AppointeeName' => '',
                                            'OtherRelation' => $OtherRelation,
                                        ],
                                    ],
                                    'PAToUnNamedPassenger' => [
                                        'PAToUnNamedPassenger' => [
                                            'IsChecked' => $cover_pa_unnamed_passenger,
                                            'NoOfItems' => '1',
                                            'SumInsured' => $cover_pa_unnamed_passenger_amt,
                                        ],
                                    ],
                                    'PAToPaidDriver' => [
                                        'PAToPaidDriver' => [
                                            'IsChecked' => $cover_pa_paid_driver,
                                            'NoOfItems' => '0',
                                            'SumInsured' => $cover_pa_paid_driver_amt,
                                        ],
                                    ],
                                    'NilDepreciationCoverage' => [
                                        'NilDepreciationCoverage' => [
                                            'ApplicableRate' => $nil_dep_rate,
                                        ],
                                    ],
                                    'TPPDCover' => [
                                        'TPPDCover' =>  [
                                            'SumInsured' => ($TPPDCover == 'true') ? 6000 : 0,
                                            'IsMandatory' => 'false',
                                            'PolicyCoverID' => "",
                                            'IsChecked' => $TPPDCover,
                                            'NoOfItems' => "",
                                            'PackageName' => "",
                                        ],
                                    ],
                                    'BifuelKit' => [
                                        'BifuelKit' => [
                                            'Fueltype' => '',
                                            'ISLpgCng' => 'false',
                                            'SumInsured' => '0',
                                        ],
                                    ],
                                    'LiabilityToPaidDriver' => [
                                        'LiabilityToPaidDriver' => [
                                            'IsMandatory' => 'false',
                                            'IsChecked' => $cover_ll_paid_driver,
                                            'NoOfItems' => '1',
                                            'PackageName' => '',
                                            'PolicyCoverID' => '',
                                        ],
                                    ],
                                    'AntiTheftDeviceDiscount' => [
                                        'AntiTheftDeviceDiscount' => [
                                            'IsMandatory' => 'false',
                                            'IsChecked' => $is_anti_theft,
                                            'NoOfItems' => '1',
                                            'PackageName' => '',
                                        ],
                                    ],
                                ],
                                'PreviousInsuranceDetails' => $previous_insurance_details,
                                'ProductCode' => $productCode,
                                'NCBEligibility' => [
                                    'NCBEligibilityCriteria' => $NCBEligibilityCriteria,
                                    'NCBReservingLetter' => '0',
                                    'PreviousNCB' => $PreviousNCB,
                                ],
                                'UserID' => $UserID,
                                'SourceSystemID' => $SourceSystemID,
                                'AuthToken' => $AuthToken,
                            ];
                            if($is_renewal_journey) {
                                $proposal_array['ClientDetails']['Salutation'] = $ClientDetails_fetch->Salutation;        
                                $proposal_array['ClientDetails']['ForeName'] = $ClientDetails_fetch->ForeName;       
                                $proposal_array['ClientDetails']['LastName'] = $ClientDetails_fetch->LastName;        
                                $proposal_array['ClientDetails']['Gender'] = $ClientDetails_fetch->Gender;        
                                $proposal_array['ClientDetails']['DOB'] = $ClientDetails_fetch->DOB;
                                $proposal_array['ClientDetails']['OccupationID'] = $ClientDetails_fetch->OccupationID; 
                                $proposal_array['NCBEligibility']['NCBEligibilityCriteria'] = $NCBEligibility->NCBEligibilityCriteria;
                                $proposal_array['NCBEligibility']['PreviousNCB'] = $PreviousNCBId;
                                $proposal_array['NCBEligibility']['CurrentNCB'] = $CurrentNCBId;
                                $proposal_array['Risk']['DateOfPurchase'] = $new_format_DateOfPurchase;
                                $proposal_array['Risk']['Zone'] = $Vehicle->Zone; //Zone tag
                                $proposal_array['Vehicle']['Registration_date'] = $new_format_registration_date;
                                $proposal_array['Policy']['BusinessType'] = $RenewalPolicy->BusinessTypeid; //Business type fix
                            }
                            if($enable2wRTI && $send_in_proposal)
                            {
                                $proposal_array['Cover']['IsReturntoInvoice'] = "true";
                                $proposal_array['Cover']['ReturntoInvoiceCoverage']=$RTIProposal_arr;
                            }

                            if ($proposal->is_car_registration_address_same == 0) {
                                $reg_address_data = DB::table('reliance_pincode_state_city_master')
                                    ->where('pincode', $proposal->car_registration_pincode)
                                    ->select('*')
                                    ->first();

                                $ClientDetails['ClientAddress']['RegistrationAddress'] = [
                                    'AddressType' => '0',
                                    'Address1' => $proposal->car_registration_address1,
                                    'Address2' => $proposal->car_registration_address2,
                                    'Address3' => $proposal->car_registration_address3,
                                    'CityID' => $reg_address_data->city_or_village_id_pk,
                                    'DistrictID' => $reg_address_data->district_id_pk,
                                    'StateID' => $reg_address_data->state_id_pk,
                                    'Pincode' => $proposal->car_registration_pincode,
                                    'Country' => '1',
                                    'NearestLandmark' => '',
                                ];
                            }

                            if (in_array($premium_type, ['breakin', 'own_damage_breakin'])) {
                                $proposal_array['Risk']['IsInspectionAddressSameasCommAddress'] = 'true';

                                $proposal_array['ClientDetails']['ClientAddress']['InspectionAddress'] = [
                                    'AddressType' => '0',
                                    'Address1' => trim($getAddress['address_1']),
                                    'Address2' => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                    'Address3' => trim($getAddress['address_3']),
                                    'CityID' => $corres_address_data->city_or_village_id_pk,
                                    'DistrictID' => $corres_address_data->district_id_pk,
                                    'StateID' => $corres_address_data->state_id_pk,
                                    'Pincode' => $proposal->pincode,
                                    'Country' => '1',
                                    'NearestLandmark' => ''
                                ];
                            }

                            $agentDiscount = calculateAgentDiscount($enquiryId, 'reliance', 'bike');
                            if ($agentDiscount['status'] ?? false) {
                                $proposal_array['Vehicle']['ODDiscount'] = $agentDiscount['discount'];
                            } else {
                                if (!empty($agentDiscount['message'] ?? '')) {
                                    return [
                                        'status' => false,
                                        'message' => $agentDiscount['message']
                                    ];
                                }
                            }

                            if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                                $proposal_array = array_merge_recursive($proposal_array, ['Policy' => $previous_tp_array]);
                            }

                            $get_response = getWsData(
                                config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL'),
                                $proposal_array,
                                'reliance',
                                [
                                    'root_tag' => 'PolicyDetails',
                                    'section' => $productData->product_sub_type_code,
                                    'method' => 'Proposal Creation',
                                    'requestMethod' => 'post',
                                    'enquiryId' => $enquiryId,
                                    'productName' => $productData->product_name. " ($business_type)",
                                    'transaction_type' => 'proposal',
                                    'headers' => [
                                        'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                                    ]
                                ]
                            );
                            $proposal_res_data = $get_response['response'];
                            if (empty($proposal_res_data)) {
                                return [
                                    'status'  => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => "Insurer not reachable."
                                ];
                            }
                            $motorPolicy = json_decode($proposal_res_data);
                            if (!isset($motorPolicy->MotorPolicy)) {
                                return [
                                    'status'  => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => "Insurer not reachable. Please try again after sometime."
                                ];
                            }
                            $motorPolicy = $motorPolicy->MotorPolicy;
                            unset($proposal_res_data);

                            if($motorPolicy->status == '1') {
                                $motorPolicy->lstPricingResponse = is_object($motorPolicy->lstPricingResponse) ? [$motorPolicy->lstPricingResponse] : $motorPolicy->lstPricingResponse;
                                $inspection_charges = !empty((int) $motorPolicy->InspectionCharges) ? (int) $motorPolicy->InspectionCharges : 0;
                                foreach ($motorPolicy->lstPricingResponse as $k => $v) {
                                    $value = round(trim(str_replace('-', '', (int) $v->Premium)));
                                    if ($v->CoverageName == 'Basic OD') {
                                        $basic_own_damage = $v->Premium + $inspection_charges;
                                    } else if ($v->CoverageName == 'Total OD and Addon') {
                                        $basic_od = $value;
                                    } elseif (($v->CoverageName == 'Nil Depreciation')) {
                                        $zero_dep_amount = $value;
                                    } elseif ($v->CoverageName == 'Bifuel Kit') {
                                        $lpg_cng = $value;
                                    } elseif ($v->CoverageName == 'Electrical Accessories') {
                                        $electrical_accessories = $value;
                                    } elseif ($v->CoverageName == 'Non Electrical Accessories') {
                                        $non_electrical_accessories = $value;
                                    } elseif ($v->CoverageName == 'NCB') {
                                        $ncb_discount = $value;
                                    } elseif ($v->CoverageName == 'Basic Liability') {
                                        $tppd = round(abs( (int) $value));
                                    } elseif ($v->CoverageName == 'PA to Unnamed Passenger') {
                                        $pa_unnamed = $value;
                                    } elseif ($v->CoverageName == 'PA to Owner Driver') {
                                        $pa_owner = $value;
                                    } elseif ($v->CoverageName == 'PA to Paid Driver') {
                                        $pa_paid_driver = $value;
                                    } elseif ($v->CoverageName == 'Liability to Paid Driver') {
                                        $liabilities = $value;
                                    } elseif ($v->CoverageName == 'Bifuel Kit TP') {
                                        $lpg_cng_tp = $value;
                                    } elseif ($v->CoverageName == 'Automobile Association Membership') {
                                        $automobile_association = round(abs($value));
                                    } elseif ($v->CoverageName == 'Anti-Theft Device') {
                                        $anti_theft = abs($value);
                                    } elseif ($v->CoverageName == 'Voluntary Deductible') {
                                        $voluntary_deductible = abs($value);
                                    } elseif ($v->CoverageName == 'TPPD') {
                                        $tppd_discount = abs($value);
                                    } elseif ($v->CoverageName == 'OD Discount') {
                                        $other_discount = abs($value);
                                    }
                                    elseif ($v->CoverageName == 'Return to Invoice' && $enable2wRTI) {
                                        $RTIAddonPremium = $value;
                                    }
                                    unset($value);
                                }

                                $service_tax = 0;
                                foreach ($motorPolicy->LstTaxComponentDetails as $k => $v) {
                                    if ($k == 'TaxComponent') {
                                        if (is_array($v)) {
                                            foreach ($v as $taxComponent) {
                                                $service_tax += (int) $taxComponent->Amount;
                                            }
                                        } else {
                                            $service_tax += (int) $v->Amount;
                                        }
                                    }
                                }

                                $NetPremium = $motorPolicy->NetPremium;
                                $final_payable_amount = $motorPolicy->FinalPremium;
                                $final_discount = $ncb_discount + $voluntary_deductible + $anti_theft + $tppd_discount + $other_discount;
                                $total_od_amount = $basic_od - $final_discount + $other_discount + $tppd_discount;
                                $total_tp_amount = $tppd + $liabilities + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $pa_owner - $tppd_discount;
                                $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $lpg_cng + $zero_dep_amount + $RTIAddonPremium;
                                $ic_vehicle_details = [
                                    'manufacture_name' => $mmv_data->make_name,
                                    'model_name' => $mmv_data->model_name,
                                    'version' => $mmv_data->variance,
                                    'fuel_type' => $mmv_data->operated_by,
                                    'seating_capacity' => $mmv_data->seating_capacity,
                                    'carrying_capacity' => $mmv_data->carrying_capacity,
                                    'cubic_capacity' => $mmv_data->cc,
                                    'gross_vehicle_weight' => $mmv_data->gross_weight ?? 1,
                                    'vehicle_type' => $mmv_data->veh_type_name,
                                ];

                                UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'od_premium' => $basic_od,
                                        'tp_premium' => $total_tp_amount,
                                        'ncb_discount' => $ncb_discount,
                                        'total_discount' => $final_discount,
                                        'addon_premium' => $total_addon_amount,
                                        'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                        'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                                        'proposal_no' => $motorPolicy->ProposalNo,
                                        'cpa_premium' => $pa_owner,
                                        'final_premium' => $NetPremium,
                                        'total_premium' => $NetPremium,
                                        'service_tax_amount' => $final_payable_amount - $NetPremium,
                                        'final_payable_amount'  => $final_payable_amount,
                                        'product_code' => $productCode,
                                        'ic_vehicle_details' => json_encode($ic_vehicle_details)
                                    ]);

                                ReliancePremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                                updateJourneyStage([
                                    'user_product_journey_id' => $enquiryId,
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                    'proposal_id' => $proposal->user_proposal_id
                                ]);

                                $user_proposal_data = UserProposal::where('user_product_journey_id',$enquiryId)
                                    ->where('user_proposal_id',$proposal->user_proposal_id)
                                    ->select('*')
                                    ->first();
                                $proposal_data = $user_proposal_data;

                                if(config('constants.finsall.IS_FINSALL_ACTIVATED') == 'Y')
                                {
                                    if(config('constants.finsall.IS_FINSALL_AVAILABLE_RELIANCE_BIKE') == 'Y')
                                    {
                                        $finsallAvailability = 'Y';
                                    }
                                    else
                                    {
                                        $finsallAvailability = 'N';
                                    }
                                    UserProposal::updateOrCreate([
                                        'user_product_journey_id' => $enquiryId,
                                        'user_proposal_id' => $proposal->user_proposal_id
                                    ],
                                    [
                                        'is_finsall_available' => $finsallAvailability
                                    ]);
                                }

                                return response()->json([
                                    'status' => true,
                                    'msg' => "Proposal Submitted Successfully!",
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'data' => [
                                        'proposalId' => $proposal_data->user_proposal_id,
                                        'userProductJourneyId' => $proposal_data->user_product_journey_id,
                                        'proposalNo' => $proposal_data->proposal_no,
                                        'serviceTaxAmount' => $service_tax,
                                        'finalPayableAmount' => $proposal_data->final_payable_amount,
                                        'is_breakin' => '',
                                        'inspection_number' => ''
                                    ]
                                ]);
                            } else {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message' => $motorPolicy->ErrorMessages
                                ];
                            }
                        }  else {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $motorPolicy->ErrorMessages
                            ];
                        }
                    } else {
                        $message = "Insurer not reachable";
                        if (!empty($premium_res_data->ErrorMessages)) {
                            $message = $premium_res_data->ErrorMessages;
                        }

                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $message
                        ];
                    }
                } else {
                    $message = "Insurer not reachable";
                    if (!empty($coverage_res_data->ErrorMessages)) {
                        $message = $coverage_res_data->ErrorMessages;
                    }

                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $message
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $coverage_res_data->ErrorMessages
                ];
            }
        }  else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    }
        }
    }}}
