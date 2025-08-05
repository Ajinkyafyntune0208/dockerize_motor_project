<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\SyncPremiumDetail\Car\ReliancePremiumDetailController;
use App\Models\CvBreakinStatus;
use Config;
use DateTime;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\QuoteLog;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class relianceSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_data=DB::table('quote_log')->where('user_product_journey_id',$enquiryId)->first();
        $quote_payud=json_decode($quote_data->premium_json);
        $mDistance=0;
        $SelRTI = $SelRSA = "false";
        $RsaAvailable = false;
        if(!empty($quote_payud->payAsYouDrive))
        {

            $user_sel_payud=$quote_payud->payAsYouDrive;
            foreach($user_sel_payud as $keypayud=>$payud ){
                if($payud->isOptedByCustomer == true)
                {
                    $mDistance=$payud->maxKMRange;
                    break;
                }
            }
        }
        $is_renewal_journey = false;
        $CoverDetails =  '';
        $ClientDetails_fetch =  '';
        $Policy =  '';
        $Vehicle =  '';
        $PreviousInsuranceDetails =  '';
        $Premium =  '';
        $NCBEligibility = '';
        $PreviousNCBId =  '';
        $CurrentNCBId =  '';
        if(isset($request['is_renewal']) && $request['is_renewal'] === 'Y')
        {
            $is_renewal_journey = true;
            $fetch_data = self::renewal_data($proposal,$request,$enquiryId,$requestData,$productData);

            if($fetch_data['status'])
            {
                $CoverDetails =  $fetch_data['data']['CoverDetails'];
                $ClientDetails_fetch =  $fetch_data['data']['ClientDetails'];
                $Policy =  $fetch_data['data']['Policy'];
                $Vehicle =  $fetch_data['data']['Vehicle'];
                $PreviousInsuranceDetails =  $fetch_data['data']['PreviousInsuranceDetails'];
                $Premium =  $fetch_data['data']['Premium'];
                $NCBEligibility = $fetch_data['data']['NCBEligibility'];
                $PreviousNCBId =  $fetch_data['data']['PreviousNCBId'];
                $CurrentNCBId =  $fetch_data['data']['CurrentNCBId'];
            }
        }

        $isGddEnabled = config('constants.motorConstant.IS_GDD_ENABLED_RELIANCE') == 'Y' ? true : false;

        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        {
            return  response()->json([
                'status' => false,
                'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
            ]);
        } */

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv')
            ->first();
        $idv = $quote_log_data->idv;
        if($is_renewal_journey)
        {
            $idv = $Vehicle->IDV;
        }

        //        $mmv_data = DB::table('ic_version_mapping as icvm')
        //            ->leftJoin('cv_reliance_modal_master as cvrm' , 'cvrm.Model_ID_PK' , '=' , 'icvm.ic_version_code')
        //            ->where([
        //                'icvm.fyn_version_id' => $requestData->version_id,
        //                'icvm.ic_id' => $productData->company_id
        //            ])
        //            ->select('icvm.*','cvrm.*')
        //            ->first();
        $mmv = get_mmv_details($productData,$requestData->version_id,'reliance');
        if($mmv['status'] == 1)
        {
          $mmv = $mmv['data'];
        }
        else
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
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
            'petrol'  => '1',
            'diesel'  => '2',
            'cng'     => '3',
            'lpg'     => '4',
            'bifuel'  => '5',
            'battery operated' => '6',
            'none'    => '0',
            'na'      => '7',
        ];
        $NCB_ID = [
            '0'      => '0',
            '20'     => '1',
            '25'     => '2',
            '35'     => '3',
            '45'     => '4',
            '50'     => '5'
        ];

        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $TPPDCover = 'false';
        $previous_tp_array = [];
        $cpa_tenure = '1';
        $NCBEligibilityCriteria = '2';
        $PreviousNCB = $NCB_ID[$requestData->previous_ncb];
        $PrevYearPolicyType = '';

        if($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $business_type = 'New Business';
            $productCode = '2374';
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
            $ISNewVehicle = 'true';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $previous_insurance_details = [];
            $cpa_tenure = '3';
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date =  date('d-m-Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime($tp_start_date))))) ;
        } elseif ($requestData->business_type == 'rollover') {
            $BusinessType = '5';
            $business_type = 'Roll Over';
            $ISNewVehicle = 'false';
            $productCode = '2311';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date =  date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) ;
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            if ($requestData->previous_policy_type == 'Third-party') {
                $NCBEligibilityCriteria = '1';
            }
        } elseif ($requestData->business_type == 'breakin') {
            $BusinessType = '5';
            $business_type = 'Break-In';
            if ($requestData->previous_policy_type == 'Third-party') {
                $NCBEligibilityCriteria = '1';
            }
            $ISNewVehicle = 'false';
            $productCode = '2311';
            $policy_start_date = date('Y-m-d', strtotime('+3 day'));
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date =  date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) ;
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
        }
        
        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
            $productCode = ($ISNewVehicle == 'true') ? '2371' : '2347';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date =   $requestData->business_type == 'newbusiness'? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime($tp_start_date))))) : date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) ;
        } else if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            $productCode = '2309';
            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                $previous_tp_array = [
                    'TPPolicyNumber' => $proposal->tp_insurance_number,
                    'TPPolicyInsurer' => $proposal->tp_insurance_company,
                    'TPPolicyStartDate' => date('Y/m/d', strtotime($proposal->tp_start_date)),
                    'TPPolicyEndDate' => date('Y/m/d', strtotime($proposal->tp_end_date)),
                ];
            }
        }
        
        // $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $policy_end_date =  $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ? date('Y-m-d', strtotime('+3 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-'])))) : date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date))));
        $IsNCBApplicable = 'true';
        $IsClaimedLastYear = 'false';
        if ($requestData->business_type == 'breakin' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new']))  {
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));

            if ($date_diff > 90) {
                $NCBEligibilityCriteria = '1';
                $PreviousNCB = '0';
                $IsNCBApplicable = 'false';
            }
        }

        $isPreviousPolicyDetailsAvailable = true;

        if (in_array($requestData->previous_policy_type, ['Not sure']) && $requestData->business_type != 'newbusiness') {
            // $BusinessType = '6';//6 means ownership change
            $isPreviousPolicyDetailsAvailable = false;
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $IsNCBApplicable = 'false';
        }

        if ($requestData->is_claim == 'Y'){
            $PreviousNCB = '0';
            
            $PreviousNCB = $NCB_ID[$requestData->previous_ncb];

            $IsNCBApplicable = 'false';
            $IsClaimedLastYear = 'true';
            $NCBEligibilityCriteria = '1';
        }

        if ($ISNewVehicle == 'false' && in_array($requestData->previous_policy_type, ['Comprehensive', 'Own-damage'])) {
            $PrevYearPolicyType = '1';
        } else if ($ISNewVehicle == 'false' && $requestData->previous_policy_type == 'Third-party') {
            $PrevYearPolicyType = '2';
        }
        if($is_renewal_journey)
        {
            $BusinessType = '2';
        }
        if(in_array($requestData->previous_policy_type, ['Not sure']))
        {
            $isPreviousPolicyDetailsAvailable = false;
            $previous_insurance_details = ['IsPreviousPolicyDetailsAvailable' => 'false'];
        }
        if ($isPreviousPolicyDetailsAvailable && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') { 
            $previous_insurance_details = [
                'IsPreviousPolicyDetailsAvailable' => $isPreviousPolicyDetailsAvailable ? 'true' : 'false',
                'PrevInsuranceID'               => '',
                'IsVehicleOfPreviousPolicySold' => 'false',
                'IsNCBApplicable'               => $IsNCBApplicable,
                'PrevYearInsurer'               => $proposal->previous_insurance_company,
                'PrevYearPolicyNo'              => $proposal->previous_policy_number,
                'PrevYearInsurerAddress'        => $proposal->previous_insurer_address.' '.$proposal->previous_insurer_pin,
                'DocumentProof'                 => '',
                'PrevPolicyPeriod'              => '1',
                'PrevYearPolicyType'            => $PrevYearPolicyType,
                'PrevYearPolicyStartDate'       => $PrevYearPolicyStartDate,
                'PrevYearPolicyEndDate'         => $PrevYearPolicyEndDate,
                'MTAReason'                     => '',
                'PrevYearNCB'                   => $requestData->previous_ncb,
                'IsInspectionDone'              => 'false',
                'InspectionDate'                => '',
                'Inspectionby'                  => '',
                'InspectorName'                 => '',
                'IsNCBEarnedAbroad'             => 'false',
                'ODLoading'                     => '',
                'IsClaimedLastYear'             => $IsClaimedLastYear,
                'ODLoadingReason'               => '',
                'PreRateCharged'                => '',
                'PreSpecialTermsAndConditions'  => '',
                'IsTrailerNCB'                  => 'false',
                'InspectionID'                  => '',
            ];

            if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                $previous_insurance_details['IsPreviousPolicyDetailsAvailable'] = 'false';
                $previous_insurance_details['PrevYearPolicyType'] = '';
                $previous_insurance_details['PrevInsuranceID'] = '';
                $previous_insurance_details['PrevYearInsurer'] = '';
                $previous_insurance_details['PrevYearPolicyNo'] = '';
                $previous_insurance_details['PrevYearPolicyStartDate'] = '';
                $previous_insurance_details['PrevYearPolicyEndDate'] = '';
            }

            if (in_array(strtoupper($requestData->previous_policy_expiry_date), ['NEW'])) {
                $previous_insurance_details['IsPreviousPolicyDetailsAvailable'] = 'false';
            }
        }

        $IsVehicleHypothicated = ($proposal->is_vehicle_finance == '1') ? 'true' : 'false';
        $selected_addons = DB::table('selected_addons')
                ->where('user_product_journey_id',$enquiryId)
                ->first();

         //PA for un named passenger
            $IsPAToUnnamedPassengerCovered = 'false';
            $PAToUnNamedPassenger_IsChecked = '';
            $PAToUnNamedPassenger_NoOfItems = '';
            $PAToUnNamedPassengerSI = 0;

            //additional Paid Driver
            $IsPAToDriverCovered = 'false';
            $PAToPaidDriver_IsChecked = 'false';
            $PAToPaidDriver_NoOfItems = '1';
            $PAToPaidDriver_SumInsured = '0';

            $IsLiabilityToPaidDriverCovered = 'false';
            $LiabilityToPaidDriver_IsChecked = 'false';

            $IsGeographicalAreaExtended = 'false';
            $Countries = 'false';


            if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = json_decode($selected_addons->additional_covers);
                foreach ($additional_covers as $value) {
                   if($value->name == 'PA cover for additional paid driver')
                   {
                        $IsPAToDriverCovered = 'true';
                        $PAToPaidDriver_IsChecked = 'true';
                        $PAToPaidDriver_NoOfItems = '1';
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                   }

                   if ($value->name == 'Unnamed Passenger PA Cover') {
                        $IsPAToUnnamedPassengerCovered = 'true';
                        $PAToUnNamedPassenger_IsChecked = 'true';
                        $PAToUnNamedPassenger_NoOfItems = $mmv_data->seating_capacity;
                        $PAToUnNamedPassengerSI = $value->sumInsured;
                    }

                    if ($value->name == 'LL paid driver') {
                        $IsLiabilityToPaidDriverCovered = 'true';
                        $LiabilityToPaidDriver_IsChecked = 'true';
                    }
                    if($value->name == 'Geographical Extension')
                    {
                        $IsGeographicalAreaExtended = 'true';
                        $Countries = 'true';
                    }
                }
            }
            if($selected_addons && !empty($selected_addons->addons))
            {
               $addons_sel=json_decode($selected_addons->addons);
                foreach ($addons_sel as $value)
                {
                    if($value->name == 'Return To Invoice')
                    {
                        $SelRTI = 'true';
                    }
                    if($value->name == 'Road Side Assistance')
                    {
                        $SelRSA = "true";
                    }
                }
            }

            $IsElectricalItemFitted = 'false';
            $ElectricalItemsTotalSI = 0;

            $IsNonElectricalItemFitted = 'false';
            $NonElectricalItemsTotalSI = 0;

            $is_bifuel_kit = 'true';

            if (in_array(strtolower($mmv_data->operated_by), ['petrol+cng', 'petrol+lpg'])) {
                $type_of_fuel = '5';
                $bifuel = 'true';
                $Fueltype = 'CNG';
            } else {
                $type_of_fuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? '5' : $TypeOfFuel[strtolower($mmv_data->operated_by)];
                $bifuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
                $Fueltype = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? $mmv_data->operated_by : '';
                $is_bifuel_kit = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
            }

            $BiFuelKitSi = 0;

            if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
            {
                $accessories = json_decode($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if($value->name == 'Electrical Accessories')
                    {
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value->sumInsured;
                    }
                    else if($value->name == 'Non-Electrical Accessories')
                    {
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value->sumInsured;
                    }
                    else if($value->name == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $type_of_fuel = '5';
                        $is_bifuel_kit = 'true';
                        $Fueltype = 'CNG';
                        $BiFuelKitSi = $value->sumInsured;
                    }
                }
            }

            // selected addons
            $IsNilDepreciation  = 'false';
            if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
                $addons = json_decode($selected_addons->applicable_addons);
                foreach ($addons as $value) {
                    if($value->name == 'Zero Depreciation')
                    {
                        $IsNilDepreciation = 'true';
                    }
                 }
            }

        $IsVoluntaryDeductableOpted = 'false';
        $VoluntaryDeductible = '';
        $anti_theft = 'false';

        if ($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != "") {
            $discounts = json_decode($selected_addons->discounts);

            foreach ($discounts as $value) {
                if ($value->name == 'anti-theft device') {
                    $anti_theft = 'true';
                }
                if ($value->name == 'voluntary_insurer_discounts') {
                    $IsVoluntaryDeductableOpted = 'true';
                    $VoluntaryDeductible = $value->sumInsured;
                }
                if ($value->name == 'TPPD Cover') {
                    $TPPDCover = 'true';
                }
            }
        }

        $cpa_selected = 'false';

        if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
            $addons = json_decode($selected_addons->compulsory_personal_accident);
            foreach ($addons as $value) {
                if(isset($value->name) && ($value->name == 'Compulsory Personal Accident'))
                {
                    $cpa_selected = 'true';
                    $cpa_tenure = isset($value->tenure) ? (string) $value->tenure : '1';
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
            $NomineeDOB = date('Y-m-d', strtotime($proposal->nominee_dob));
            $Salutation = ($proposal->title == '1') ? 'Mr.' : 'Ms.';
            $ForeName = $proposal->first_name;
            $LastName = ! empty($proposal->last_name) ? $proposal->last_name : '.';
            $CorporateName = '';
            $OccupationID = $proposal->occupation;
            $DOB = date('Y-m-d', strtotime($proposal->dob));
            $Gender = $proposal->gender;
            $MaritalStatus = $proposal->marital_status == 'Single' ? '1952' : '1951';
            $IsHavingValidDrivingLicense = '';
            $IsOptedStandaloneCPAPolicy = '';
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

        //$DateOfPurchase = date('Y-m-d', strtotime($requestData->vehicle_register_date));
        //as per git id 16724 reg date format DD/MM/YY
        $vehileInvoiceDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $DateOfPurchase = date('d/m/Y', strtotime($vehileInvoiceDate));
        $vehicleRegisterDate =  date('d/m/Y', strtotime($requestData->vehicle_register_date));
        $vehicle_manf = explode('-',$proposal->vehicle_manf_year);

        $isSecurePremium = 'false';
        $isSecurePlus = 'false';
        if ($masterProduct->product_identifier == 'secure_plus') {
            $isSecurePlus = 'true';
        }elseif($masterProduct->product_identifier == 'secure_premium'){
            $isSecurePremium = 'true';
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $POSType = '';
        $POSAadhaarNumber = '';
        $POSPANNumber = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();


        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $idv < 5000000 ) {
            $POSType = '2';
            $POSAadhaarNumber = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
            $POSPANNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
        }elseif(config('constants.motor.reliance.IS_POS_TESTING_MODE_ENABLE_RELIANCE') == 'Y') {
            $POSType = '2';
            $POSPANNumber = 'ABGTY8890Z';
            $POSAadhaarNumber = '569278616999';
        }

        $FIFTYLAKH_IDV_RESTRICTION_APPLICABLE = config('constants.motorConstant.FIFTYLAKH_IDV_RESTRICTION_APPLICABLE');//create this constant for renewbuy for allowing 50l above idv case to be non pos
        if( $FIFTYLAKH_IDV_RESTRICTION_APPLICABLE == 'Y')
        {
            if($idv > 5000000)
            {
                $POSType = '';
                $POSAadhaarNumber = '';
                $POSPANNumber = '';
            }
        }

        $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
        if($is_renewbuy && $idv > 5000000)
        {
            $POSType = '';
            $POSAadhaarNumber = '';
            $POSPANNumber = '';
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

        $ReturnToInvoiceDetails=[];
        $RTITrue="false";
        $enableRTI=(config('constants.IcConstants.reliance.IS_RETURN_TO_INVOICE')=="Y")?true:false;
        if($enableRTI)
        {
            if($SelRTI=="true" && $enableRTI)
            {
                $ReturnToInvoiceDetails=[
                    'AddonSumInsuredFlatRates'=>[
                        'IsChecked'                         => 'true',
                        'addonOptedYesRate'                 => '3.456',
                        'addonOptedNoRate'                  => 7.538,
                        'isOptedByCustomer'                 => 'true',
                        'isOptedByCustomerRate'             => 'addonOptedYesRate',
                        'addonYesMultiplicationFactorRate'  => 12.356,
                        'addonNoMultiplicationFactorRate'   => 11.121,
                        'ageofVehicleRate'                  => 1.12,
                        'vehicleCCRate'                     => 1.11,
                        'zoneRate'                          => 1.4,
                        'parkingRate'                       => 1.1,
                        'drivingAgeRate'                    => 1.2,
                        'ncbApplicableRate'                 => 1.4,
                        'noOfVehicleUserRate'               => 1.4,
                        'occupationRate'                    =>1.0,
                        'policyIssuanceMethodRate'          =>1.4,
                        'existingRGICustomerRate'           =>1.4,
                        'addonLastYearYesRate'              => 1.4,
                        'addonLastYearNoRate'               => 1.26,
                    ]
                ];
                $RTITrue="true";
            }
        }
        $liabilitytoemployee = [];
        if($requestData->vehicle_owner_type == "C" && $LiabilityToPaidDriver_IsChecked == 'true' && $IsLiabilityToPaidDriverCovered == 'true')
        {
            $liabilitytoemployee = [
                    'LiabilityToEmployee' => [
                        'NoOfItems' => ($mmv_data->seating_capacity - 1) ?? 0
                        ]
                ];
        } else if ($requestData->vehicle_owner_type == "C"){
            $liabilitytoemployee = [
                'LiabilityToEmployee' => [
                        'NoOfItems' => $mmv_data->seating_capacity ?? 0
                    ]
            ];
        }
        else
        {
            $liabilitytoemployee = null;
        }
        $isLiabilityToEmployeeCovered = "false";
        if ($requestData->vehicle_owner_type == 'C' && !($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')) {
        $isLiabilityToEmployeeCovered = "true";
        }

        
        $premium_req_array = [
            'ClientDetails'            => [
                'ClientType' => $ClientType,
            ],
            'Policy'                   => [
                'BusinessType'     => $BusinessType,
                'AgentCode'        => 'Direct',
                'AgentName'        => 'Direct',
                'Branch_Name'      => 'Direct',
                'Cover_From'       => $policy_start_date,
                'Cover_To'         => $policy_end_date,
                'Branch_Code'      => '9202',
                'productcode'      => $productCode,
                'OtherSystemName'  => '1',
                'isMotorQuote'     => ($tp_only == 'true') ? 'false' : 'true',
                'isMotorQuoteFlow' => ($tp_only == 'true') ? 'false' : 'true',
                'POSType' => $POSType,
                'POSAadhaarNumber' => $POSAadhaarNumber,
                'POSPANNumber' => $POSPANNumber,
            ],
            'Risk'                     => [
                'VehicleMakeID'                       => $mmv_data->make_id_pk,
                'VehicleModelID'                      => $mmv_data->model_id_pk,
                'StateOfRegistrationID'               => $rto_data->state_id_fk,
                'RTOLocationID'                       => $rto_data->model_region_id_pk,
                'ExShowroomPrice'                     => '',
                'IDV'                                 => ($tp_only == 'true') ? 0 : $idv,
                'DateOfPurchase'                      => $DateOfPurchase,
                'ManufactureMonth'                    => $vehicle_manf[0],
                'ManufactureYear'                     => $vehicle_manf[1],
                'EngineNo'                            => removeSpecialCharactersFromString($proposal->engine_number),
                'Chassis'                             => removeSpecialCharactersFromString($proposal->chassis_number),
                'IsRegAddressSameasCommAddress'       => 'true',
                'IsRegAddressSameasPermanentAddress'  => 'true',
                'IsPermanentAddressSameasCommAddress' => 'true',
                'VehicleVariant'                      => $mmv_data->variance,
                'IsVehicleHypothicated'               => $IsVehicleHypothicated,
                'FinanceType'                         => ($proposal->is_vehicle_finance == '1') ? '1' : '',
                'FinancierName'                       => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                'FinancierAddress'                    => ($proposal->is_vehicle_finance == '1') ?  $proposal->hypothecation_city: '',
		        'IsHavingValidDrivingLicense'         => ($IsPAToOwnerDriverCoverd == 'false') ? $IsHavingValidDrivingLicense : '',
                'IsOptedStandaloneCPAPolicy'          => ($IsPAToOwnerDriverCoverd == 'false') ? (($IsHavingValidDrivingLicense == 'true') ? $IsOptedStandaloneCPAPolicy : '') : '',
            ],
            'Vehicle'                  => [
                'TypeOfFuel'          => $type_of_fuel,
                'ISNewVehicle'        => $ISNewVehicle,
                'Registration_Number' => isBhSeries($registration_number) ? changeRegNumberFormat($registration_number) : $registration_number,
                'IsBHVehicle' => isBhSeries($registration_number) ? 'true' : 'false',
                'Registration_date'   => $vehicleRegisterDate,
                'MiscTypeOfVehicleID' => '',
            ],
            'Cover'                    => [
                'IsPAToUnnamedPassengerCovered'   => $IsPAToUnnamedPassengerCovered,
                'IsVoluntaryDeductableOpted'      => $IsVoluntaryDeductableOpted,
                'IsGeographicalAreaExtended' => $IsGeographicalAreaExtended,
                'IsElectricalItemFitted'          => $IsElectricalItemFitted,
                'ElectricalItemsTotalSI'          => $ElectricalItemsTotalSI,
                'IsPAToOwnerDriverCoverd'         => $IsPAToOwnerDriverCoverd,
		        'IsLiabilityToPaidDriverCovered'  => $IsLiabilityToPaidDriverCovered,
                'IsTPPDCover'                     => $TPPDCover,//($tp_only == 'true') ? 'false' : 'true',
                'IsBasicODCoverage'               => ($tp_only == 'true') ? 'false' : 'true',
                'IsBasicLiability'                => ($tp_only == 'true') ? 'true' : 'false',
                'IsNonElectricalItemFitted'       => $IsNonElectricalItemFitted,
                'NonElectricalItemsTotalSI'       => $NonElectricalItemsTotalSI,
                'IsPAToDriverCovered'             => $IsPAToDriverCovered,
                'IsBiFuelKit'                     => $is_bifuel_kit,
                'BiFuelKitSi'                     => $BiFuelKitSi,
                'IsBifuelTypeChecked'             => $bifuel,
                'SecurePlus'                      => $isSecurePlus,
                'SecurePremium'                   => $isSecurePremium,
                'IsInsurancePremium'              => 'true',
                'IsReturntoInvoice'             => "$RTITrue",
                'IsLiabilityToEmployeeCovered'  => $isLiabilityToEmployeeCovered,
                'LiabilityToEmployee'           => $liabilitytoemployee,

                'VoluntaryDeductible'             => [
                    'VoluntaryDeductible' => ['SumInsured' => $VoluntaryDeductible],
                ],
                'GeographicalExtension'             => [
                    'GeographicalExtension' => [
                        'Countries' => $Countries,
                    ],
                ],
                'IsTPPDCover'                     => $TPPDCover,
                'TPPDCover' => [
                    'TPPDCover' =>  [
                        'SumInsured' => ($TPPDCover == 'true') ? 6000 : '',
                        'IsMandatory' => 'false',
                        'PolicyCoverID' => "",
                        'IsChecked' => $TPPDCover,
                        'NoOfItems' => "",
                        'PackageName' => "",
                    ],
                ],
                'IsAntiTheftDeviceFitted'         => $anti_theft,
                'IsAutomobileAssociationMember'   => 'false',
                'AutomobileAssociationName'       => '',
                'AutomobileAssociationNo'         => '',
                'AutomobileAssociationExpiryDate' => '',
		        'PACoverToOwnerDriver' => '1',
                'PACoverToOwner'                  => [
                    'PACoverToOwner' => [
                        'IsChecked'           => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                        'NoOfItems'           => ($requestData->vehicle_owner_type == 'I') ? '1' : '',
                        'CPAcovertenure'      => ($requestData->vehicle_owner_type == 'I') ? $cpa_tenure : '',
                        'PackageName'         => '',
                        'NomineeName'         => $NomineeName,
                        'NomineeDOB'          => $NomineeDOB,
                        'NomineeRelationship' => $NomineeRelationship,
                        'NomineeAddress'      => $NomineeAddress,
                        'AppointeeName'       => '',
                        'OtherRelation'       => '',
                    ],
                ],
                'PAToUnNamedPassenger'            => [
                    'PAToUnNamedPassenger' => [
                        'IsChecked'  => $PAToUnNamedPassenger_IsChecked,
                        'NoOfItems'  => $PAToUnNamedPassenger_NoOfItems,
                        'SumInsured' => $PAToUnNamedPassengerSI,
                    ],
                ],
                'PAToPaidDriver'                  => [
                    'PAToPaidDriver' => [
                        'IsChecked'  => $PAToPaidDriver_IsChecked,
                        'NoOfItems'  => $PAToPaidDriver_NoOfItems,
                        'SumInsured' => $PAToPaidDriver_SumInsured,
                    ],
                ],
                'LiabilityToPaidDriver'			=> [
                    'LiabilityToPaidDriver' => [
                            'IsMandatory' => $IsLiabilityToPaidDriverCovered,
                            'IsChecked' => $LiabilityToPaidDriver_IsChecked,
                            'NoOfItems' => '1',
                            'PackageName' => '',
                            'PolicyCoverID' => '',
                    ]
                ],
                'BifuelKit' => [
                    'BifuelKit' => [
                        'IsChecked'            => 'false',
                        'IsMandatory'          => 'false',
                        'PolicyCoverDetailsID' => '',
                        'Fueltype'             => $Fueltype,
                        'ISLpgCng'             => $bifuel,
                        'PolicyCoverID'        => '',
                        'SumInsured'           => $BiFuelKitSi,
                        'NoOfItems'            => '',
                        'PackageName'          => '',
                    ],
                ],
                'ReturntoInvoiceCoverage'  => $ReturnToInvoiceDetails,
                "IsA2KSelected" => $SelRSA,
                "A2KDiscountCover" =>  [
                    "A2KCover" => [
                        "CoverageName" => "Assistance Cover",
                        "IsChecked" => $SelRSA,
                        "Rate" => 100.00,
                        "CoverCode" => "Cover15",
                        "SubCoverName" => "",
                        "CalculationType" => "ODDiscount"
                    ]
                ],
            ],
            'PreviousInsuranceDetails' => $previous_insurance_details,
            'NCBEligibility'           => [
                'NCBEligibilityCriteria' => $NCBEligibilityCriteria,
                'NCBReservingLetter'     => '',
                'PreviousNCB'            => $PreviousNCB,
            ],
            'ProductCode'              => $productCode,
            'UserID'                   => $UserID,
            'SourceSystemID'           => $SourceSystemID,
            'AuthToken'                => $AuthToken,
            'IsQuickquote'             => ($tp_only == 'true') ? 'false' : 'true',
        ];

        if($NomineeName == '')
        {
            unset($premium_req_array['Cover']['PACoverToOwner']['PACoverToOwner']['NomineeName']);
        }

        if ($requestData->business_type == 'newbusiness') {
            unset($premium_req_array['PreviousInsuranceDetails']);
        }

        if($is_renewal_journey)
        {
            $vehicle_registration_date = DateTime::createFromFormat('d/m/Y', $Vehicle->Registration_date);
            //$new_format_registration_date = $vehicle_registration_date->format('Y-m-d');
            //as per git id 16724 reg date format DD/MM/YY
            $new_format_registration_date = $vehicle_registration_date->format('d/m/Y');
            $DateOfPurchase = DateTime::createFromFormat('d/m/Y', $Vehicle->DateOfPurchase);
            //$new_format_DateOfPurchase = $DateOfPurchase->format('Y-m-d');
            //as per git id 16724 reg date format DD/MM/YY
            $new_format_DateOfPurchase = $DateOfPurchase->format('d/m/Y');
            $reg_no = explode('-',$Vehicle->Registration_Number);

            // $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime(str_replace('/', '-',$Policy->PolicyEndDate))));
            // $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-',$Policy->PolicyEndDate))));

            // $premium_req_array['Policy']['Cover_From'] = $policy_start_date;
            // $premium_req_array['Policy']['Cover_To'] = $policy_end_date;
            $premium_req_array['Policy']['isMotorQuote'] = 'false';
            $premium_req_array['Policy']['isMotorQuoteFlow'] = '';

            $premium_req_array['NCBEligibility']['NCBEligibilityCriteria'] = $NCBEligibility->NCBEligibilityCriteria;
            $premium_req_array['NCBEligibility']['PreviousNCB'] = $PreviousNCBId;
            $premium_req_array['NCBEligibility']['CurrentNCB'] = $CurrentNCBId;

            $premium_req_array['Risk']['VehicleMakeID'] = $Vehicle->VehicleMakeID;
            $premium_req_array['Risk']['VehicleModelID'] = $Vehicle->VehicleModelID;
            $premium_req_array['Risk']['CubicCapacity'] = $Vehicle->CubicCapacity;
            $premium_req_array['Risk']['Zone'] = $Vehicle->Zone;
            $premium_req_array['Risk']['RTOLocationID'] =  $Vehicle->RTOLocationID;
            $premium_req_array['Risk']['ExShowroomPrice'] = $Vehicle->ExShowroomPrice;
            $premium_req_array['Risk']['IDV'] =  $Vehicle->IDV;
            $premium_req_array['Risk']['DateOfPurchase'] = $new_format_DateOfPurchase;#date('Y-m-d', strtotime($Vehicle->DateOfPurchase));
            // $premium_req_array['Risk']['ManufactureMonth'] = date('m', strtotime($Vehicle->DateOfPurchase));
            // $premium_req_array['Risk']['ManufactureYear'] = date('Y', strtotime($Vehicle->DateOfPurchase));
            $premium_req_array['Risk']['EngineNo'] = removeSpecialCharactersFromString($Vehicle->EngineNo);
            $premium_req_array['Risk']['Chassis'] = removeSpecialCharactersFromString($Vehicle->Chassis);
            $premium_req_array['Risk']['VehicleVariant'] = $Vehicle->VehicleVariant;
            $premium_req_array['Risk']['StateOfRegistrationID'] = $Vehicle->RTOstateID;
            $premium_req_array['Risk']['Rto_RegionCode'] = $rto_data->region_code;

            $premium_req_array['Vehicle']['Registration_date'] = $new_format_registration_date;#date('Y-m-d', strtotime($Vehicle->Registration_date));
            $premium_req_array['Vehicle']['Registration_Number'] = $Vehicle->Registration_Number;

            $premium_req_array['PreviousInsuranceDetails']['IsPreviousPolicyDetailsAvailable'] = 'true';
            $premium_req_array['PreviousInsuranceDetails']['PrevYearInsurer'] = '11';
            $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyNo'] = $PreviousInsuranceDetails->PrevYearPolicyNo;
            // $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyStartDate'] = date('Y-m-d', strtotime(str_replace('/', '-',$PreviousInsuranceDetails->PrevYearPolicyStartDate)));
            // $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyEndDate'] = date('Y-m-d', strtotime(str_replace('/', '-',$PreviousInsuranceDetails->PrevYearPolicyEndDate)));
            $premium_req_array['PreviousInsuranceDetails']['IsNCBApplicable'] = ($NCBEligibility->NCBEligibilityCriteria == '2') ? 'true' : 'false';
            $premium_req_array['PreviousInsuranceDetails']['IsClaimedLastYear'] = $Vehicle->IsClaimedLastYear;
        }

        if ($premium_type == 'own_damage') {
            $premium_req_array = array_merge_recursive($premium_req_array, ['Policy' => $previous_tp_array]);
        }

        $agentDiscount = calculateAgentDiscount($enquiryId, 'reliance', 'car');
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

        $premium_req_array = trim_array($premium_req_array);
        // pay as you drive for coverage details newbussiness 
        $odometerreading=($BusinessType==1)?100:1000;
        if ($productData->good_driver_discount=="Yes" && $isGddEnabled) {
            $premium_req_array['Cover']['IsPayAsYouDrive']="";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']=[

                'isCoverEligible'=>"",
                'CurrentVehicalOdoMtrReading'=>"$odometerreading",
                'rate'=>"",//should be in decimal
                'minKMRange'=>"",//should be in decimal
                'maxKMRange'=>"",//should be in decimal
                'PlanDescription'=>"",
                'CoverUptoKm'=>"",//should be in decimal
                'CoverUptoKm'=>"",//should be in decimal
                'IsChecked'=>"",
                'isOptedByCustomer'=>"",
            ];
            
        }

        $get_response = getWsData(
            config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_COVERAGE'),
            $premium_req_array,
            'reliance',
            [
                'root_tag'      => 'PolicyDetails',
                'section'       => $productData->product_sub_type_code,
                'method'        => 'Coverage Calculation',
                'requestMethod' => 'post',
                'enquiryId'     => $enquiryId,
                'productName'   => $productData->product_name. " ($business_type)",
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ],
                'transaction_type'    => 'proposal'
            ]
        );
        $minKmRange=0;
        $maxKMRange=0;
        $PlanDescription="";
        $rate="";
        $CoverUptoKm=0;
        $coverage_res_data = $get_response['response'];

        if($coverage_res_data)
        {

            $coverage_res_data = json_decode($coverage_res_data);
            if(!isset($coverage_res_data->ErrorMessages))
            {
                $nil_dep_rate = '';
                $secure_plus_rate = '';
                $secure_premium_rate = '';
                foreach ($coverage_res_data->LstAddonCovers as $k => $v)
                {
                    if($v->CoverageName == 'Nil Depreciation')
                    {
                       $nil_dep_rate = $v->rate;
                    }
                    elseif ($v->CoverageName == 'Secure Plus')
                    {
                        $secure_plus_rate = $v->rate;
                    }
                    elseif ($v->CoverageName == 'Secure Premium')
                    {
                        $secure_premium_rate = $v->rate;
                    }
                    if ($v->CoverageName=='Pay As You Drive' && $isGddEnabled && $productData->good_driver_discount=="Yes") 
                    {
                        $CoverUptoKm=(int) $mDistance+$odometerreading;
                        foreach ($v->PayAsYouDrive as $key => $value) {
                            if($value->maxKMRange==$mDistance){
                                $minKmRange=$value->minKMRange;
                                $maxKMRange=$value->maxKMRange;
                                $PlanDescription=$value->PlanDescription;
                                $rate=$value->rate;
                                break;
                            }
                        }
                        // for pay as you drive premium calculation from the repsonse of coveragedetails
                        $premium_req_array['Cover']['IsPayAsYouDrive']="true";
                        $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']=[
                            'isCoverEligible'=>"true",
                            'CurrentVehicalOdoMtrReading'=>"$odometerreading",
                            'rate'=>$rate,//should be in decimal
                            'minKMRange'=>$minKmRange,//should be in decimal
                            'maxKMRange'=>$maxKMRange,//should be in decimal
                            'PlanDescription'=>$PlanDescription,
                            'CoverUptoKm'=>"$CoverUptoKm",//should be in decimal
                            'IsChecked'=>"true",
                            'isOptedByCustomer'=>"true",
                        ];
                    }
                    // taking the coverage response for return to invoice and passing to premium calculation to get final RTI premium
                    if($v->CoverageName=='Return to Invoice' && $enableRTI)
                    {
                        if(!empty($v->ReturntoInvoice[0]))
                        {

                            $premium_req_array['Cover']['ReturntoInvoiceCoverage']['AddonSumInsuredFlatRates']=[
                                'IsChecked'=>'true',
                                'isOptedByCustomer'=>'true',
                                'isOptedByCustomerRate'=>'addonOptedYesRate',
                                'addonOptedYesRate'=>$v->ReturntoInvoice[0]->addonOptedYesRate,
                                'addonOptedNoRate'=>$v->ReturntoInvoice[0]->addonOptedNoRate,
                                'addonYesMultiplicationFactorRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonYesMultiplicationFactorRate,
                                'addonNoMultiplicationFactorRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonNoMultiplicationFactorRate,
                                'ageofVehicleRate'=>$v->ReturntoInvoice[0]->RelativityFactor->ageofVehicleRate,
                                'vehicleCCRate'=>$v->ReturntoInvoice[0]->RelativityFactor->vehicleCCRate,
                                'zoneRate'=>$v->ReturntoInvoice[0]->RelativityFactor->zoneRate,
                                'parkingRate'=>$v->ReturntoInvoice[0]->RelativityFactor->parkingRate,
                                'drivingAgeRate'=>$v->ReturntoInvoice[0]->RelativityFactor->driverAgeRate,
                                'ncbApplicableRate'=>$v->ReturntoInvoice[0]->RelativityFactor->ncbApplicabilityRate,
                                'noOfVehicleUserRate'=>$v->ReturntoInvoice[0]->RelativityFactor->noOfVehicleUserRate,
                                'occupationRate'=>$v->ReturntoInvoice[0]->RelativityFactor->occupationRate,
                                'policyIssuanceMethodRate'=>$v->ReturntoInvoice[0]->RelativityFactor->policyIssuanceMethodRate,
                                'existingRGICustomerRate'=>$v->ReturntoInvoice[0]->RelativityFactor->existingRGICustomerRate,
                                'addonLastYearYesRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonLastYearYesRate,
                                'addonLastYearNoRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonLastYearNoRate,
                            ];
                        }
                        else
                        {
                            $premium_req_array['Cover']['ReturntoInvoiceCoverage']='';
                            $premium_req_array['Cover']['IsReturntoInvoice']='false';
                        }
                    }
                    if($v->CoverageName == 'Assistance cover- 24/7 RSA' && $SelRSA == 'true')
                    {
                        $RsaAvailable = true;
                        if($RsaAvailable){
                            $premium_req_array['Cover']['IsA2KSelected'] = "true";
                            $premium_req_array['Cover']['A2KDiscountCover']['A2KCover'] = [
                                'CoverageName' => $v->CoverageName ,
                                "rate" => $v->rate ,
                                "IsChecked" => "true",
                                "SubCoverName" => "",
                                "CoverCode" => $v->A2KCoverCode,
                                "CalculationType" => $v->CalculationType
                            ];
                        }
                        else
                        {
                            unset($premium_req_array['Cover']['IsA2KSelected']);
                            unset($premium_req_array['Cover']['A2KDiscountCover']);
                        }
                    }
                }
                // $IsNilDepreciation = false;
                if($masterProduct->product_identifier == 'zero_dep')
                {
                    $premium_req_array['Cover']['SecurePlus'];
                    $premium_req_array['Cover']['SecurePremium'];
                    $premium_req_array['Cover']['IsNilDepreciation'] = 'true'; //$IsNilDepreciation;
                    $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                }elseif($masterProduct->product_identifier == 'secure_plus'){
                    $premium_req_array['Cover']['IsSecurePlus'] = 'true';
                    $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = 'true';
                    unset($premium_req_array['Cover']['SecurePremium']);
                    $premium_req_array['Cover']['SecurePlus'] = [
                        'SecurePlus' => [
                            'IsChecked' => 'true',
                            'ApplicableRate' => $secure_plus_rate,
                        ],
                    ];
                }elseif($masterProduct->product_identifier == 'secure_premium'){
                    $premium_req_array['Cover']['IsSecurePremium'] = 'true';
                    $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = 'true';
                    unset($premium_req_array['Cover']['SecurePlus']);
                    $premium_req_array['Cover']['SecurePremium'] = [
                        'SecurePremium' => [
                            'IsChecked' => 'true',
                            'ApplicableRate' => $secure_premium_rate,
                        ],
                    ];
                }elseif ($premium_type == 'third_party'){
                    $premium_req_array['Cover']['IsSecurePremium'] = '';
                    $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = '';
                    unset($premium_req_array['Cover']['SecurePlus']);
                    unset($premium_req_array['Cover']['SecurePremium']);
                }

                // if($tp_only)
                // {
                //     $premium_req_array['Cover']['IsSecurePremium'] = '';
                // }
                $get_response = getWsData(
                    config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PREMIUM'),
                    $premium_req_array,
                    'reliance',
                    [
                        'root_tag'      => 'PolicyDetails',
                        'section'       => $productData->product_sub_type_code,
                        'method'        => 'Premium Calculation',
                        'requestMethod' => 'post',
                        'enquiryId'     => $enquiryId,
                        'productName'   => $productData->product_name. " ($business_type)",
                        'transaction_type'    => 'proposal',
                        'headers' => [
                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                        ]
                    ]
                );
                $premium_res_data = $get_response['response'];

                $response = json_decode($premium_res_data)->MotorPolicy;
                unset($premium_res_data);
                if (trim($response->ErrorMessages) == '')
                {
                    $isBreakin = '';
                    $inspectionId = '';

                    $corres_address_data = DB::table('reliance_pincode_state_city_master')
                    ->where('pincode', $proposal->pincode)
                    ->select('*')
                    ->first();

                    if (
                        in_array($premium_type, ['breakin', 'own_damage_breakin'])
                        || ($requestData->previous_policy_type == 'Third-party'
                        && !in_array($premium_type, ['third_party', 'third_party_breakin']))
                        && ($proposal->is_inspection_done == 'N' ||
                        $proposal->is_inspection_done == NULL)
                    ) {

                        ReliancePremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        $totalPayable = $response->FinalPremium ?? 0;
                        $isBreakin = 'Y';
                        $vehicleManf = explode('-',$proposal->vehicle_manf_year);

                        $leadArray = [
                            'LEADDESCRIPTION' => [
                                'CUSTOMERFNAME' => $requestData->vehicle_owner_type == 'C' ? $CorporateName : $ForeName,
                                'CUSTOMERMNAME' => '',
                                'CUSTOMERLNAME' => $LastName,
                                'CUSTOMERADDRESS1' => trim($getAddress['address_1']),
                                'CUSTOMERADDRESS2' => trim($getAddress['address_2']),
                                'CUSTOMERADDRESS3' => trim($getAddress['address_3']),
                                'CUSTOMERCONTACTNO' => $proposal->mobile_number,
                                'CUSTOMERTELEPHONENO' => '',
                                'CUSTOMEREMAILID' => $proposal->email,
                                'VENDORCODE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_VENDORCODE'),
                                'VENDORCODEVALUE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_VENDORCODEVALUE'),
                                'BASCODE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_BASCODE'),
                                'BASCODEVALUE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_BASCODEVALUE'),
                                'BASMOBILE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_BASMOBILE'),
                                'SMCODE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_SMCODE'),
                                'SMCODEVALUE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_SMCODEVALUE'),
                                'SMMOBILENO' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_SMMOBILENO'),
                                'VEHICLETYPE' => 6,
                                'VEHICLETYPEVALUE' => 'Pvt. Car',
                                'VEHICLEREGNO' => $registration_number, 
                                'MAKE' => $mmv_data->make_id_pk,
                                'MAKEVALUE' => $mmv_data->make_name,
                                'MODEL' => $mmv_data->model_id_pk,
                                'MODELVALUE' => $mmv_data->variance,
                                'ENGINENO' => removeSpecialCharactersFromString($proposal->engine_number),
                                'CHASSISNO' => removeSpecialCharactersFromString($proposal->chassis_number),
                                'STATE' => $corres_address_data->state_id_pk,
                                'STATEVALUE' => $corres_address_data->state_name,
                                'DISTRICT' => $corres_address_data->district_id_pk,
                                'DISTRICTVALUE' => $corres_address_data->district_name,
                                'CITY' => $corres_address_data->city_or_village_id_pk,
                                'CITYVALUE' => $corres_address_data->city_or_village_name,
                                'PINCODE' => $proposal->pincode,
                                'RGICL_OFFICE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_RGICL_OFFICE'),
                                'RGICL_OFFICEVALUE' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_RGICL_OFFICEVALUE'),
                                'VECH_INSP_ADDRESS' => trim($getAddress['address_1']),
                                'VECH_INSP_ADDRESS2' => trim($getAddress['address_2']),
                                'VECH_INSP_ADDRESS3' => trim($getAddress['address_3']),
                                'REMARK' => 'GZB',
                                'LEADTYPE' => 'F',
                                'STATE_VEH' => $corres_address_data->state_id_pk,
                                'STATEVALUE_VEH' => $corres_address_data->state_name,
                                'DISTRICT_VEH' => $corres_address_data->district_id_pk,
                                'DISTRICTVALUE_VEH' => $corres_address_data->district_name,
                                'CITY_VEH' => $corres_address_data->city_or_village_id_pk,
                                'CITYVALUE_VEH' => $corres_address_data->city_or_village_name,
                                'PINCODE_VEH' => $proposal->pincode,
                                'OBJECTIVEOFINSPECTION' => '8',
                                'OBJECTIVEOFINSPECTIONVALUE' => 'Policy Booking',
                                'INSPECTIONTOBEDONE' => '12',
                                'INSPECTIONTOBEDONEVALUE' => 'Inspection Agency',
                                'LEADCREATEDBY' => 'reliance',
                                'LEADCREATEDBYSYSTEM' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_LEADCREATEDBYSYSTEM'),
                                'INTIMATORNAME' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_INTIMATORNAME'),
                                'INTIMATORMOBILENO' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_INTIMATORMOBILENO'),
                                'PREVIOUS_POLICYNUMBER' => '',
                                'BUSINESSTYPE_CODE' => '4',
                                'FITNESS_CERTIFICATE' => '',
                                'FITNESS_VALID_UPTO' => '',
                                'PERMIT_NO' => '',
                                'PERMIT_VALID_UPTO' =>'',
                                'PERMIT_TYPE' => 'All India',
                                'MANUFACTURER_MONTH' => $vehicleManf[0],
                                'MANUFACTURER_YEAR' => $vehicleManf[1],
                            ]
                        ];

                        $leadArray = ArrayToXml::convert($leadArray, 'LEAD');
                        $leadArray = preg_replace("/<\\?xml .*\\?>/i", '', $leadArray);

                        $root = [
                            'rootElementName' => 'soapenv:Envelope',
                            '_attributes' => [
                                'xmlns:soapenv' => 'http://schemas.xmlsoap.org/soap/envelope/',
                                'xmlns:tem' => 'http://tempuri.org/'
                            ]
                        ];

                        $requestArray = [
                            'soapenv:Header' => [
                                'tem:UserCredential' => [
                                    'tem:UserID' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_USERID'),
                                    'tem:UserPassword' => config('constants.IcConstants.reliance.RELIANCE_MOTOR_LEAD_USERPASSWORD')
                                ]
                            ],
                            'soapenv:Body' => [
                                'tem:InsertLead' => [
                                    'tem:xmlstring' => [
                                        '_cdata' => $leadArray
                                    ]
                                ]
                            ]
                        ];

                        $leadArray = ArrayToXml::convert($requestArray, $root, false);

                        $headers = [
                            'Content-type' => 'text/xml',
                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                        ];

                        $getResponse = getWsData(
                            config('constants.IcConstants.reliance.CAR_LEAD_CREATION_URL'),
                            $leadArray,
                            'reliance',
                            [
                                'section' => $productData->product_sub_type_code,
                                'method' => 'Lead Creation',
                                'requestMethod' => 'post',
                                'enquiryId' => $enquiryId,
                                'productName' => $productData->product_name,
                                'transaction_type' => 'proposal',
                                'headers' => $headers
                            ]
                        );

                        $invalidLeadResponse = json_decode($getResponse['response'], true);
                        if (isset($invalidLeadResponse['statusCode']) && $invalidLeadResponse['statusCode'] !== 200) {
                            return [
                                'status' => false,
                                'webservice_id' => $getResponse['webservice_id'],
                                'table' => $getResponse['table'],
                                'message' => $invalidLeadResponse['message'] ?? 'Error in Lead Creation',
                            ];
                        }

                        $leadResponse = $getResponse['response'];

                        if (empty($leadResponse)) {
                            return [
                                'status' => false,
                                'webservice_id' => $getResponse['webservice_id'],
                                'table' => $getResponse['table'],
                                'message' => 'Error in lead creation service'
                            ];
                        }

                        $leadResponse = XmlToArray::convert($leadResponse);
                        if (is_numeric($leadResponse['soap:Body']['InsertLeadResponse']['InsertLeadResult'] ?? null)) {
                            $inspectionId = $leadResponse['soap:Body']['InsertLeadResponse']['InsertLeadResult'];

                            UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'customer_id' => $inspectionId,
                                'final_payable_amount' => $totalPayable
                            ]);
                        } else {
                            if (!empty($proposal->customer_id ?? null)) {
                                $inspectionId = $proposal->customer_id;
                            } else {
                                return [
                                    'status' => false,
                                    'webservice_id' => $getResponse['webservice_id'],
                                    'table' => $getResponse['table'],
                                    'message' => $leadResponse['soap:Body']['InsertLeadResponse']['InsertLeadResult'] ?? 'Error in lead creation service'
                                ];
                            }
                        }

                        $proposal->is_breakin_case = 'Y';
                        $proposal->save();

                        CvBreakinStatus::updateOrCreate(
                            ['user_proposal_id'  => $proposal->user_proposal_id],
                            [
                                'ic_id' => $productData->company_id,
                                'breakin_number' => $inspectionId,
                                'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                            ]
                        );

                        updateJourneyStage([
                            'user_product_journey_id' => $proposal->user_product_journey_id,
                            'ic_id' => $proposal->ic_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $proposal->proposal_no,
                                'finalPayableAmount' => $totalPayable,
                                'is_breakin' => $isBreakin ?? '',
                                'inspection_number' => $inspectionId ?? ''
                            ]
                        ]);

                    }
                        // salutaion

                        if ($requestData->vehicle_owner_type == "I") {
                            if (in_array(strtoupper($proposal->gender), ['MALE', 'M'])) {
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
                        // salutaion

                    $ClientDetails = [
                        'ClientType'         => $ClientType,
                        'Salutation'         => $Salutation,
                        'ForeName'           => $ForeName,
                        'LastName'           => $LastName,
                        'CorporateName'      => $CorporateName,
                        'MidName'            => '',
                        'OccupationID'       => $OccupationID,
                        'DOB'                => $DOB,
                        'Gender'             => $Gender,
                        'PhoneNo'            => '',
                        'MobileNo'           => $proposal->mobile_number,
                        'RegisteredUnderGST' => trim($proposal->gst_number) == '' ? '0' : '1',
                        'RelatedParty'       => '0',
                        'GSTIN'              => $proposal->gst_number,
                        'GroupCorpID'        => '',
                        'ClientAddress'      => [
                            'CommunicationAddress' => [
                                'AddressType'     => '0',
                                'Address1'        => trim($getAddress['address_1']),
                                'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                'Address3'        => trim($getAddress['address_3']),
                                'CityID'          => $corres_address_data->city_or_village_id_pk,
                                'DistrictID'      => $corres_address_data->district_id_pk,
                                'StateID'         => $corres_address_data->state_id_pk,
                                'Pincode'         => $proposal->pincode,
                                'Country'         => '1',
                                'NearestLandmark' => '',
                            ],
                            'RegistrationAddress'  => [
                                'AddressType'     => '0',
                                'Address1'        => trim($getAddress['address_1']),
                                'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                'Address3'        => trim($getAddress['address_3']),
                                'CityID'          => $corres_address_data->city_or_village_id_pk,
                                'DistrictID'      => $corres_address_data->district_id_pk,
                                'StateID'         => $corres_address_data->state_id_pk,
                                'Pincode'         => $proposal->pincode,
                                'Country'         => '1',
                                'NearestLandmark' => '',
                            ],
                            'PermanentAddress'     => [
                                'AddressType'     => '0',
                                'Address1'        => trim($getAddress['address_1']),
                                'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                'Address3'        => trim($getAddress['address_3']),
                                'CityID'          => $corres_address_data->city_or_village_id_pk,
                                'DistrictID'      => $corres_address_data->district_id_pk,
                                'StateID'         => $corres_address_data->state_id_pk,
                                'Pincode'         => $proposal->pincode,
                                'Country'         => '1',
                                'NearestLandmark' => '',
                            ],
                        ],
                        'EmailID'            => $proposal->email,
                        'MaritalStatus'      => $MaritalStatus,
                        'Nationality'        => '1949'
                    ];
                    unset($premium_req_array['ClientDetails']);
                    $client['ClientDetails'] = $ClientDetails;
                    $premium_req_array = array_merge($client,$premium_req_array);
                    //print_r($premium_req_array);

                    if($is_renewal_journey)
                    {
                        $premium_req_array['ClientDetails']['Salutation'] = $ClientDetails_fetch->Salutation;        
                        $premium_req_array['ClientDetails']['ForeName'] = $ClientDetails_fetch->ForeName;       
                        $premium_req_array['ClientDetails']['LastName'] = $ClientDetails_fetch->LastName;        
                        $premium_req_array['ClientDetails']['Gender'] = $ClientDetails_fetch->Gender;        
                        $premium_req_array['ClientDetails']['DOB'] = $ClientDetails_fetch->DOB;
                        $premium_req_array['ClientDetails']['OccupationID'] = $ClientDetails_fetch->OccupationID;  
                        $premium_req_array['ClientDetails']['ClientAddress']['CommunicationAddress']['Country'] = 'India';
                        $premium_req_array['ClientDetails']['ClientAddress']['RegistrationAddress']['Country'] = 'India';
                        $premium_req_array['ClientDetails']['ClientAddress']['PermanentAddress']['Country'] = 'India';
                        $premium_req_array['Policy']['isMotorQuote'] = 'true';
                        $premium_req_array['Policy']['isMotorQuoteFlow'] = 'true';
                    }
                    $get_response = getWsData(
                        config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL'),
                        $premium_req_array,
                        'reliance',
                        [
                            'root_tag'      => 'PolicyDetails',
                            'section'       => $productData->product_sub_type_code,
                            'method'        => 'Proposal Creation',
                            'requestMethod' => 'post',
                            'enquiryId'     => $enquiryId,
                            'productName'   => $productData->product_name. " ($business_type)",
                            'transaction_type'    => 'proposal',
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
                    $proposal_resp = json_decode($proposal_res_data);
                    if (!isset($proposal_resp->MotorPolicy)) {
                        return [
                            'status'  => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => "Insurer not reachable. Please try again after sometime."
                        ];
                    }
                    $proposal_resp = $proposal_resp->MotorPolicy;
                    unset($proposal_res_data);
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
                    $other_addon_amount = 0;
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;
                    $RTIAddonPremium='0';
                    $inspection_charges = !empty((int) $proposal_resp->InspectionCharges) ? (int) $proposal_resp->InspectionCharges : 0;
                    $basic_own_damage = 0;
                    $liability_to_employee_premium = 0;

                    if($proposal_resp->status == '1')
                    {
                        $cpa_premium = 0;
                        $proposal_resp->lstPricingResponse = is_object($proposal_resp->lstPricingResponse) ? [$proposal_resp->lstPricingResponse] : $proposal_resp->lstPricingResponse;
                        foreach($proposal_resp->lstPricingResponse as $single)
                        {
                            if (isset($single->CoverageName)) {
                                if ($single->CoverageName == 'Basic OD') {
                                    $basic_own_damage = $single->Premium + $inspection_charges;
                                } else if($single->CoverageName == 'PA to Owner Driver') {
                                    $cpa_premium = $single->Premium;
                                } elseif (($single->CoverageName == 'Nil Depreciation')) {
                                    $zero_dep_amount = $single->Premium;
                                } elseif ($single->CoverageName == 'Bifuel Kit') {
                                    $lpg_cng = $single->Premium;
                                } elseif ($single->CoverageName == 'Electrical Accessories') {
                                    $electrical_accessories = $single->Premium;
                                } elseif ($single->CoverageName == 'Non Electrical Accessories') {
                                    $non_electrical_accessories = $single->Premium;
                                } elseif ($single->CoverageName == 'NCB') {
                                    $ncb_discount = abs( (float) $single->Premium);
                                }
                                elseif ($single->CoverageName == 'Total OD and Addon')
                                {
                                    $basic_od = abs( (float) $single->Premium);
                                }
                                elseif ($single->CoverageName == 'Secure Plus' || $single->CoverageName == 'Secure Premium')
                                {
                                    $other_addon_amount = abs( (float) $single->Premium);
                                }
                                elseif ($single->CoverageName == 'Basic Liability') {
                                    $tppd = abs( (float) $single->Premium);
                                } elseif ($single->CoverageName == 'PA to Unnamed Passenger') {
                                    $pa_unnamed = $single->Premium;
                                } elseif ($single->CoverageName == 'PA to Paid Driver') {
                                    $pa_paid_driver = $single->Premium;
                                } elseif ($single->CoverageName == 'Liability to Paid Driver') {
                                    $liabilities = $single->Premium;
                                } elseif ($single->CoverageName == 'Bifuel Kit TP') {
                                    $lpg_cng_tp = $single->Premium;
                                } elseif ($single->CoverageName == 'Automobile Association Membership') {
                                    $automobile_association = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Anti-Theft Device') {
                                    $anti_theft = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Voluntary Deductible') {
                                    $voluntary_deductible = abs($single->Premium);
                                } elseif ($single->CoverageName == 'TPPD') {
                                    $tppd_discount = abs($single->Premium);
                                } elseif (in_array($single->CoverageName, ['Geographical Extension' , 'Geo Extension']) && $single->CoverID == 5) {
                                    $geog_Extension_OD_Premium = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Geographical Extension'  && in_array($single->CoverID, [6,403])) {
                                    $geog_Extension_TP_Premium = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Return to Invoice'  && $enableRTI) {
                                    $RTIAddonPremium = abs($single->Premium);
                                
                                } elseif ($single->CoverageName == 'Liability to Employees') {
                                    $liability_to_employee_premium = abs($single->Premium);
                                }
                            }
                        }
                        $NetPremium = $proposal_resp->NetPremium;
                        $od_discount = $ncb_discount + $voluntary_deductible + $anti_theft;
                        // $total_od_amount = $basic_od - $final_discount + $tppd_discount;
                        $total_od_amount = ($basic_own_damage + $electrical_accessories + $non_electrical_accessories + $lpg_cng + $geog_Extension_OD_Premium) - $od_discount;
                        $total_tp_amount = $tppd + $liabilities + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $cpa_premium - $tppd_discount + $geog_Extension_TP_Premium + $liability_to_employee_premium;
                        $final_discount = $od_discount + $tppd_discount;
                        $total_addon_amount =  $zero_dep_amount + $other_addon_amount + $RTIAddonPremium; 
                        $final_payable_amount = $proposal_resp->FinalPremium;
                        $ic_vehicle_details = [
                            'manufacture_name'      => $mmv_data->make_name,
                            'model_name'            => $mmv_data->model_name,
                            'version'               => $mmv_data->variance,
                            'fuel_type'             => $mmv_data->operated_by,
                            'seating_capacity'      => $mmv_data->seating_capacity,
                            'carrying_capacity'     => $mmv_data->carrying_capacity,
                            'cubic_capacity'        => $mmv_data->cc,
                            'gross_vehicle_weight'  => $mmv_data->gross_weight ?? 1,
                            'vehicle_type'          => $mmv_data->veh_type_name,
                        ];
                        // UserProposal::where('user_product_journey_id', $enquiryId)
                        //     ->where('user_proposal_id', $proposal->user_proposal_id)
                        //     ->update([
                        //         'policy_start_date'     => date('d-m-Y', strtotime($policy_start_date)),
                        //         'policy_end_date'       => date('d-m-Y', strtotime($policy_end_date)),
                        //         'proposal_no'           => $proposal_resp->ProposalNo,
                        //         'od_premium'            => $total_od_amount,
                        //         'tp_premium'            => $total_tp_amount,
                        //         'addon_premium'         => $total_addon_amount,
                        //         'cpa_premium'           => $cpa_premium,
                        //         'total_premium'         => $NetPremium, 
                        //         'total_discount'        => $final_discount,
                        //         'service_tax_amount'    => $final_payable_amount - $NetPremium,
                        //         'final_payable_amount'  => $final_payable_amount,
                        //         'product_code'          => $productCode,
                        //         'ic_vehicle_details'    => json_encode($ic_vehicle_details)
                        //     ]);

                        $updateData = [
                            'policy_start_date'     => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date'       => date('d-m-Y', strtotime($policy_end_date)),
                            'proposal_no'           => $proposal_resp->ProposalNo,
                            'od_premium'            => $total_od_amount,
                            'tp_premium'            => $total_tp_amount,
                            'addon_premium'         => $total_addon_amount,
                            'cpa_premium'           => $cpa_premium,
                            'total_premium'         => $NetPremium, 
                            'total_discount'        => $final_discount,
                            'service_tax_amount'    => $final_payable_amount - $NetPremium,
                            'final_payable_amount'  => $final_payable_amount,
                            'product_code'          => $productCode,
                            'tp_start_date'         => $tp_start_date,
                            'tp_end_date'           => $tp_end_date,

                    ];
                    if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                        unset($updateData['tp_start_date']);
                        unset($updateData['tp_end_date']);
                    }
                    $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($updateData);

                        ReliancePremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        updateJourneyStage([
                            'user_product_journey_id' => $enquiryId,
                            'ic_id' => $productData->company_id,
                            'stage' => ($isBreakin ?? '') == 'Y' ? STAGE_NAMES['INSPECTION_PENDING'] : STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id
                        ]);

                        $user_proposal_data = UserProposal::where('user_product_journey_id',$enquiryId)
                            ->where('user_proposal_id',$proposal->user_proposal_id)
                            ->select('*')
                            ->first();
                        $proposal_data = $user_proposal_data;

                        if(config('constants.finsall.IS_FINSALL_ACTIVATED') == 'Y')
                        {
                            if(config('constants.finsall.IS_FINSALL_AVAILABLE_RELIANCE_CAR') == 'Y')
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
                                'finalPayableAmount' => $proposal_data->final_payable_amount,
                                'is_breakin' => $isBreakin ?? '',
                                'inspection_number' => $inspectionId ?? ''
                            ]
                        ]);

                    }
                    else
                    {
                        return [
                            'status'  => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $proposal_resp->ErrorMessages
                        ];

                    }
                    die;
                }
                else
                {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message'        => $response->ErrorMessages
                    ];
                }
            }
            else
            {
                return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message'        => $coverage_res_data->ErrorMessages
                ];
            }
        }
        else
        {
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => 'Insurer Not Reachable'
            ];
        }


    }

    public static function renewal_data($proposal,$request,$enquiryId,$requestData,$productData)
    {
        $return_data=[];
        $premium_type = DB::table('master_premium_type')
        ->where('id',$productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

        $UserID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_USERID_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_USERID_RELIANCE') : config('constants.IcConstants.reliance.USERID_RELIANCE');

        $SourceSystemID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE')) )? config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE') : config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE');
        
        $AuthToken = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE')) ) ? config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE') : config('constants.IcConstants.reliance.AUTH_TOKEN_RELIANCE');

        $url=config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_FETCH_RENEWAL');

        $renewal_fetch_array = [
            'PrevPolicyNumber' => $proposal->previous_policy_number,//'920222123110003941',
            'EngineNo' => '',
            'ChassisNo' => '',
            'RegistrationNo' => $proposal->vehicale_registration_number,// 'MH-01-AZ-3455',
            'PrevPolicyEndDate' => '',
            'ProductCode' => '',
            'SourceSystemID' => $UserID,
            'AuthToken' => $AuthToken,
            'UserID' => $SourceSystemID,
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
                'productName'   => $productData->product_name. " Renewal",
                'transaction_type'    => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ]
            ]
        );
        $renewal_res_data = $get_response['response'];

        if ($renewal_res_data) 
        {

            $renewal_response = json_decode($renewal_res_data);
            // dd($renewal_response);
            
            if(!isset($renewal_response->PolicyDetails->ErrorMessages->ErrMessages))
            {
                // print_pre($renewal_response->PolicyDetails->CoverDetails);
                $return_data['CoverDetails'] = $renewal_response->PolicyDetails->CoverDetails->CoverList;
                $return_data['ClientDetails'] = $renewal_response->PolicyDetails->ClientDetails;
                $return_data['Policy'] = $renewal_response->PolicyDetails->Policy;
                $return_data['Vehicle'] = $renewal_response->PolicyDetails->Vehicle;
                $return_data['PreviousInsuranceDetails'] = $renewal_response->PolicyDetails->PreviousInsuranceDetails;
                $return_data['Premium'] = $renewal_response->PolicyDetails->Premium;
                $return_data['NCBEligibility'] = $renewal_response->PolicyDetails->NCBEligibility;

                $ncb_master = [
                    "0" => "0",
                    "1" => "20",
                    "2" => "25",
                    "3" => "35",
                    "4" => "45",
                    "5" => "50",
                ];

              $return_data['PreviousNCBId'] = array_search($renewal_response->PolicyDetails->NCBEligibility->PreviousNCB,$ncb_master);
              $return_data['CurrentNCBId'] = array_search($renewal_response->PolicyDetails->NCBEligibility->CurrentNCB,$ncb_master);
            
              return [
                'premium_amount' => 0,
                'status'         => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data'        => $return_data
            ];
            }else
            {
                return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'        => $renewal_response->PolicyDetails->ErrorMessages->ErrMessages
                ];

            }
        }else
        {
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => 'Insurer Not Reachable1'
            ];
        }
            
    }

    public static function postInspectionSubmit($proposal)
    {
        // Please note this block of code is copied from above submit() method
        $enquiryId   = $proposal->user_product_journey_id;
        $requestData = getQuotation($enquiryId);
        $quoteData = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $productData = getProductDataByIc($quoteData->master_policy_id);
        $quotePayUD = $quoteData->premium_json;
        $mDistance = 0;
        $SelRTI = $SelRSA = "false";
        $RsaAvailable = false;

        $breakinDetails = CvBreakinStatus::where('user_proposal_id', $proposal->user_proposal_id)->first();

        if(!empty($quotePayUD->payAsYouDrive)) {

            $user_sel_payud=$quotePayUD->payAsYouDrive;
            foreach($user_sel_payud as $keypayud=>$payud ){
                if($payud->isOptedByCustomer == true)
                {
                    $mDistance = $payud->maxKMRange;
                    break;
                }
            }
        }

        $isGddEnabled = config('constants.motorConstant.IS_GDD_ENABLED_RELIANCE') == 'Y' ? true : false;

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();

        $idv = $quoteData->idv;
        $mmv = get_mmv_details($productData,$requestData->version_id, 'reliance');
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
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
            'petrol'  => '1',
            'diesel'  => '2',
            'cng'     => '3',
            'lpg'     => '4',
            'bifuel'  => '5',
            'battery operated' => '6',
            'none'    => '0',
            'na'      => '7',
        ];
        $NCB_ID = [
            '0'      => '0',
            '20'     => '1',
            '25'     => '2',
            '35'     => '3',
            '45'     => '4',
            '50'     => '5'
        ];

        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $TPPDCover = 'false';
        $previous_tp_array = [];
        $cpa_tenure = '1';
        $NCBEligibilityCriteria = '2';
        $PreviousNCB = $NCB_ID[$requestData->previous_ncb];
        $PrevYearPolicyType = '';

        if($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $business_type = 'New Business';
            $productCode = '2374';
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
            $ISNewVehicle = 'true';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $previous_insurance_details = [];
            $cpa_tenure = '3';
        } elseif ($requestData->business_type == 'rollover') {
            $BusinessType = '5';
            $business_type = 'Roll Over';
            $ISNewVehicle = 'false';
            $productCode = '2311';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));

            if ($requestData->previous_policy_type == 'Third-party') {
                $NCBEligibilityCriteria = '1';
            }
        } elseif ($requestData->business_type == 'breakin') {
            $BusinessType = '5';
            $business_type = 'Break-In';
            $ISNewVehicle = 'false';
            if ($requestData->previous_policy_type == 'Third-party') {
                $NCBEligibilityCriteria = '1';
            }
            $productCode = '2311';
            $policy_start_date = date('Y-m-d', strtotime('+3 day'));
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
        }
        
        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
            $productCode = ($ISNewVehicle == 'true') ? '2371' : '2347';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
        } else if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            $productCode = '2309';
            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                $previous_tp_array = [
                    'TPPolicyNumber' => $proposal->tp_insurance_number,
                    'TPPolicyInsurer' => $proposal->tp_insurance_company,
                    'TPPolicyStartDate' => date('Y/m/d', strtotime($proposal->tp_start_date)),
                    'TPPolicyEndDate' => date('Y/m/d', strtotime($proposal->tp_end_date)),
                ];
            }
        }
        
        $policy_start_date = date('Y-m-d');
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $IsNCBApplicable = 'true';
        $IsClaimedLastYear = 'false';
        if ($requestData->business_type == 'breakin' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new']))  {
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));

            if ($date_diff > 90) {
                $NCBEligibilityCriteria = '1';
                $PreviousNCB = '0';
                $IsNCBApplicable = 'false';
            }
        }

        $isPreviousPolicyDetailsAvailable = true;
        if (in_array($requestData->previous_policy_type, ['Not sure']) && $requestData->business_type != 'newbusiness') {
            // $BusinessType = '6';//6 means ownership change
            $isPreviousPolicyDetailsAvailable = false;
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $IsNCBApplicable = 'false';
        }

        if ($requestData->is_claim == 'Y'){
            $PreviousNCB = '0';
            
            $PreviousNCB = $NCB_ID[$requestData->previous_ncb];

            $IsNCBApplicable = 'false';
            $IsClaimedLastYear = 'true';
            $NCBEligibilityCriteria = '1';
        }

        if ($ISNewVehicle == 'false' && $requestData->previous_policy_type == 'Comprehensive') {
            $PrevYearPolicyType = '1';
        } elseif ($ISNewVehicle == 'false' && $requestData->previous_policy_type == 'Third-party') {
            $PrevYearPolicyType = '2';
        }
        if(in_array($requestData->previous_policy_type, ['Not sure']))
        {
            $isPreviousPolicyDetailsAvailable = false;
            $previous_insurance_details = ['IsPreviousPolicyDetailsAvailable' => 'false'];
        }
        if ($isPreviousPolicyDetailsAvailable && $requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') { 
            $previous_insurance_details = [
                'IsPreviousPolicyDetailsAvailable' => $isPreviousPolicyDetailsAvailable ? 'true' : 'false',
                'PrevInsuranceID'               => '',
                'IsVehicleOfPreviousPolicySold' => 'false',
                'IsNCBApplicable'               => $IsNCBApplicable,
                'PrevYearInsurer'               => $proposal->previous_insurance_company,
                'PrevYearPolicyNo'              => $proposal->previous_policy_number,
                'PrevYearInsurerAddress'        => $proposal->previous_insurer_address.' '.$proposal->previous_insurer_pin,
                'DocumentProof'                 => '',
                'PrevPolicyPeriod'              => '1',
                'PrevYearPolicyType'            => $PrevYearPolicyType,
                'PrevYearPolicyStartDate'       => $PrevYearPolicyStartDate,
                'PrevYearPolicyEndDate'         => $PrevYearPolicyEndDate,
                'MTAReason'                     => '',
                'PrevYearNCB'                   => $requestData->previous_ncb,
                'IsInspectionDone'              => 'true',
                'InspectionDate'                => date('d/m/Y', strtotime($breakinDetails->inspection_date)),
                'Inspectionby'                  => '',
                'InspectorName'                 => '',
                'IsNCBEarnedAbroad'             => 'false',
                'ODLoading'                     => '',
                'IsClaimedLastYear'             => $IsClaimedLastYear,
                'ODLoadingReason'               => '',
                'PreRateCharged'                => '',
                'PreSpecialTermsAndConditions'  => '',
                'IsTrailerNCB'                  => 'false',
                'InspectionID'                  => $breakinDetails->breakin_number,
            ];

            if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                $previous_insurance_details['IsPreviousPolicyDetailsAvailable'] = 'false';
                $previous_insurance_details['PrevYearPolicyType'] = '';
                $previous_insurance_details['PrevInsuranceID'] = '';
                $previous_insurance_details['PrevYearInsurer'] = '';
                $previous_insurance_details['PrevYearPolicyNo'] = '';
                $previous_insurance_details['PrevYearPolicyStartDate'] = '';
                $previous_insurance_details['PrevYearPolicyEndDate'] = '';
            }

            if (in_array(strtoupper($requestData->previous_policy_expiry_date), ['NEW'])) {
                $previous_insurance_details['IsPreviousPolicyDetailsAvailable'] = 'false';
            }
        }

        $IsVehicleHypothicated = ($proposal->is_vehicle_finance == '1') ? 'true' : 'false';
        $selected_addons = DB::table('selected_addons')
                ->where('user_product_journey_id',$enquiryId)
                ->first();

         //PA for un named passenger
            $IsPAToUnnamedPassengerCovered = 'false';
            $PAToUnNamedPassenger_IsChecked = '';
            $PAToUnNamedPassenger_NoOfItems = '';
            $PAToUnNamedPassengerSI = 0;

            //additional Paid Driver
            $IsPAToDriverCovered = 'false';
            $PAToPaidDriver_IsChecked = 'false';
            $PAToPaidDriver_NoOfItems = '1';
            $PAToPaidDriver_SumInsured = '0';

            $IsLiabilityToPaidDriverCovered = 'false';
            $LiabilityToPaidDriver_IsChecked = 'false';

            $IsGeographicalAreaExtended = 'false';
            $Countries = 'false';


            if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = json_decode($selected_addons->additional_covers);
                foreach ($additional_covers as $value) {
                   if($value->name == 'PA cover for additional paid driver')
                   {
                        $IsPAToDriverCovered = 'true';
                        $PAToPaidDriver_IsChecked = 'true';
                        $PAToPaidDriver_NoOfItems = '1';
                        $PAToPaidDriver_SumInsured = $value->sumInsured;
                   }

                   if ($value->name == 'Unnamed Passenger PA Cover') {
                        $IsPAToUnnamedPassengerCovered = 'true';
                        $PAToUnNamedPassenger_IsChecked = 'true';
                        $PAToUnNamedPassenger_NoOfItems = $mmv_data->seating_capacity;
                        $PAToUnNamedPassengerSI = $value->sumInsured;
                    }

                    if ($value->name == 'LL paid driver') {
                        $IsLiabilityToPaidDriverCovered = 'true';
                        $LiabilityToPaidDriver_IsChecked = 'true';
                    }
                    if($value->name == 'Geographical Extension')
                    {
                        $IsGeographicalAreaExtended = 'true';
                        $Countries = 'true';
                    }
                }
            }
            if($selected_addons && !empty($selected_addons->addons))
            {
               $addons_sel=json_decode($selected_addons->addons);
                foreach ($addons_sel as $value)
                {
                    if($value->name == 'Return To Invoice')
                    {
                        $SelRTI = 'true';
                    }
                    if($value->name == 'Road Side Assistance')
                    {
                        $SelRSA = "true";
                    }
                }
            }

            $IsElectricalItemFitted = 'false';
            $ElectricalItemsTotalSI = 0;

            $IsNonElectricalItemFitted = 'false';
            $NonElectricalItemsTotalSI = 0;

            $is_bifuel_kit = 'true';

            if (in_array(strtolower($mmv_data->operated_by), ['petrol+cng', 'petrol+lpg'])) {
                $type_of_fuel = '5';
                $bifuel = 'true';
                $Fueltype = 'CNG';
            } else {
                $type_of_fuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? '5' : $TypeOfFuel[strtolower($mmv_data->operated_by)];
                $bifuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
                $Fueltype = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? $mmv_data->operated_by : '';
                $is_bifuel_kit = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
            }

            $BiFuelKitSi = 0;

            if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
            {
                $accessories = json_decode($selected_addons->accessories);
                foreach ($accessories as $value) {
                    if($value->name == 'Electrical Accessories')
                    {
                        $IsElectricalItemFitted = 'true';
                        $ElectricalItemsTotalSI = $value->sumInsured;
                    }
                    else if($value->name == 'Non-Electrical Accessories')
                    {
                        $IsNonElectricalItemFitted = 'true';
                        $NonElectricalItemsTotalSI = $value->sumInsured;
                    }
                    else if($value->name == 'External Bi-Fuel Kit CNG/LPG')
                    {
                        $type_of_fuel = '5';
                        $is_bifuel_kit = 'true';
                        $Fueltype = 'CNG';
                        $BiFuelKitSi = $value->sumInsured;
                    }
                }
            }

            // selected addons
            if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
                $addons = json_decode($selected_addons->applicable_addons);
                foreach ($addons as $value) {
                    if($value->name == 'Zero Depreciation')
                    {
                    }
                 }
            }

        $IsVoluntaryDeductableOpted = 'false';
        $VoluntaryDeductible = '';
        $anti_theft = 'false';

        if ($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != "") {
            $discounts = json_decode($selected_addons->discounts);

            foreach ($discounts as $value) {
                if ($value->name == 'anti-theft device') {
                    $anti_theft = 'true';
                }
                if ($value->name == 'voluntary_insurer_discounts') {
                    $IsVoluntaryDeductableOpted = 'true';
                    $VoluntaryDeductible = $value->sumInsured;
                }
                if ($value->name == 'TPPD Cover') {
                    $TPPDCover = 'true';
                }
            }
        }

        $cpa_selected = 'false';

        if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
            $addons = json_decode($selected_addons->compulsory_personal_accident);
            foreach ($addons as $value) {
                if(isset($value->name) && ($value->name == 'Compulsory Personal Accident'))
                {
                    $cpa_selected = 'true';
                    $cpa_tenure = isset($value->tenure) ? (string) $value->tenure : '1';
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
            $NomineeDOB = date('Y-m-d', strtotime($proposal->nominee_dob));
            $Salutation = ($proposal->title == '1') ? 'Mr.' : 'Ms.';
            $ForeName = $proposal->first_name;
            $LastName = ! empty($proposal->last_name) ? $proposal->last_name : '.';
            $CorporateName = '';
            $OccupationID = $proposal->occupation;
            $DOB = date('Y-m-d', strtotime($proposal->dob));
            $Gender = $proposal->gender;
            $MaritalStatus = $proposal->marital_status == 'Single' ? '1952' : '1951';
            $IsHavingValidDrivingLicense = '';
            $IsOptedStandaloneCPAPolicy = '';
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

        //$DateOfPurchase = date('Y-m-d', strtotime($requestData->vehicle_register_date));
        //as per git id 16724 reg date format DD/MM/YY
        $DateOfPurchase = date('d/m/Y', strtotime($requestData->vehicle_register_date));
        $vehicle_manf = explode('-',$proposal->vehicle_manf_year);

        $isSecurePremium = 'false';
        $isSecurePlus = 'false';
        if ($masterProduct->product_identifier == 'secure_plus') {
            $isSecurePlus = 'true';
        }elseif($masterProduct->product_identifier == 'secure_premium'){
            $isSecurePremium = 'true';
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $POSType = '';
        $POSAadhaarNumber = '';
        $POSPANNumber = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();


        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' ) {
            $POSType = '2';
            $POSAadhaarNumber = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
            $POSPANNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
        }elseif(config('constants.motor.reliance.IS_POS_TESTING_MODE_ENABLE_RELIANCE') == 'Y') {
            $POSType = '2';
            $POSPANNumber = 'ABGTY8890Z';
            $POSAadhaarNumber = '569278616999';
        }

        $FIFTYLAKH_IDV_RESTRICTION_APPLICABLE = config('constants.motorConstant.FIFTYLAKH_IDV_RESTRICTION_APPLICABLE');//create this constant for renewbuy for allowing 50l above idv case to be non pos
        if( $FIFTYLAKH_IDV_RESTRICTION_APPLICABLE == 'Y')
        {
            if($idv > 5000000)
            {
                $POSType = '';
                $POSAadhaarNumber = '';
                $POSPANNumber = '';
            }
        }

        $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
        if($is_renewbuy && $idv > 5000000)
        {
            $POSType = '';
            $POSAadhaarNumber = '';
            $POSPANNumber = '';
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

        $ReturnToInvoiceDetails=[];
        $RTITrue="false";
        $enableRTI=(config('constants.IcConstants.reliance.IS_RETURN_TO_INVOICE')=="Y")?true:false;
        if($enableRTI)
        {
            if($SelRTI=="true" && $enableRTI)
            {
                $ReturnToInvoiceDetails=[
                    'AddonSumInsuredFlatRates'=>[
                        'IsChecked'                         => 'true',
                        'addonOptedYesRate'                 => '3.456',
                        'addonOptedNoRate'                  => 7.538,
                        'isOptedByCustomer'                 => 'true',
                        'isOptedByCustomerRate'             => 'addonOptedYesRate',
                        'addonYesMultiplicationFactorRate'  => 12.356,
                        'addonNoMultiplicationFactorRate'   => 11.121,
                        'ageofVehicleRate'                  => 1.12,
                        'vehicleCCRate'                     => 1.11,
                        'zoneRate'                          => 1.4,
                        'parkingRate'                       => 1.1,
                        'drivingAgeRate'                    => 1.2,
                        'ncbApplicableRate'                 => 1.4,
                        'noOfVehicleUserRate'               => 1.4,
                        'occupationRate'                    =>1.0,
                        'policyIssuanceMethodRate'          =>1.4,
                        'existingRGICustomerRate'           =>1.4,
                        'addonLastYearYesRate'              => 1.4,
                        'addonLastYearNoRate'               => 1.26,
                    ]
                ];
                $RTITrue="true";
            }
        }
        $liabilitytoemployee = [];
        if($requestData->vehicle_owner_type == "C")
        {
            $liabilitytoemployee = [
                    'LiabilityToEmployee' => [
                            'NoOfItems' =>  $mmv_data->carrying_capacity ?? 0
                        ]
                ];
        }
        else
        {
            $liabilitytoemployee = null;
        }
        $isLiabilityToEmployeeCovered = "false";
        if ($requestData->vehicle_owner_type == 'C' && !($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin')) {
        $isLiabilityToEmployeeCovered = "true";
        }
        $premium_req_array = [
            'ClientDetails'            => [
                'ClientType' => $ClientType,
            ],
            'Policy'                   => [
                'BusinessType'     => $BusinessType,
                'AgentCode'        => 'Direct',
                'AgentName'        => 'Direct',
                'Branch_Name'      => 'Direct',
                'Cover_From'       => $policy_start_date,
                'Cover_To'         => $policy_end_date,
                'Branch_Code'      => '9202',
                'productcode'      => $productCode,
                'OtherSystemName'  => '1',
                'isMotorQuote'     => ($tp_only == 'true') ? 'false' : 'true',
                'isMotorQuoteFlow' => ($tp_only == 'true') ? 'false' : 'true',
                'POSType' => $POSType,
                'POSAadhaarNumber' => $POSAadhaarNumber,
                'POSPANNumber' => $POSPANNumber,
            ],
            'Risk'                     => [
                'VehicleMakeID'                       => $mmv_data->make_id_pk,
                'VehicleModelID'                      => $mmv_data->model_id_pk,
                'StateOfRegistrationID'               => $rto_data->state_id_fk,
                'RTOLocationID'                       => $rto_data->model_region_id_pk,
                'ExShowroomPrice'                     => '',
                'IDV'                                 => ($tp_only == 'true') ? 0 : $idv,
                'DateOfPurchase'                      => $DateOfPurchase,
                'ManufactureMonth'                    => $vehicle_manf[0],
                'ManufactureYear'                     => $vehicle_manf[1],
                'EngineNo'                            => removeSpecialCharactersFromString($proposal->engine_number),
                'Chassis'                             => removeSpecialCharactersFromString($proposal->chassis_number),
                'IsRegAddressSameasCommAddress'       => 'true',
                'IsRegAddressSameasPermanentAddress'  => 'true',
                'IsPermanentAddressSameasCommAddress' => 'true',
                'IsInspectionAddressSameasCommAddress' => 'true',
                'VehicleVariant'                      => $mmv_data->variance,
                'IsVehicleHypothicated'               => $IsVehicleHypothicated,
                'FinanceType'                         => ($proposal->is_vehicle_finance == '1') ? '1' : '',
                'FinancierName'                       => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                'FinancierAddress'                    => ($proposal->is_vehicle_finance == '1') ?  $proposal->hypothecation_city: '',
		        'IsHavingValidDrivingLicense'         => ($IsPAToOwnerDriverCoverd == 'false') ? $IsHavingValidDrivingLicense : '',
                'IsOptedStandaloneCPAPolicy'          => ($IsPAToOwnerDriverCoverd == 'false') ? (($IsHavingValidDrivingLicense == 'true') ? $IsOptedStandaloneCPAPolicy : '') : '',
            ],
            'Vehicle'                  => [
                'InspectionNo' => $breakinDetails->breakin_number,
                'TypeOfFuel'          => $type_of_fuel,
                'ISNewVehicle'        => $ISNewVehicle,
                'Registration_Number' => isBhSeries($registration_number) ? changeRegNumberFormat($registration_number) : $registration_number,
                'IsBHVehicle' => isBhSeries($registration_number) ? 'true' : 'false',
                'Registration_date'   => $DateOfPurchase,
                'MiscTypeOfVehicleID' => '',
            ],
            'Cover'                    => [
                'IsPAToUnnamedPassengerCovered'   => $IsPAToUnnamedPassengerCovered,
                'IsVoluntaryDeductableOpted'      => $IsVoluntaryDeductableOpted,
                'IsGeographicalAreaExtended' => $IsGeographicalAreaExtended,
                'IsElectricalItemFitted'          => $IsElectricalItemFitted,
                'ElectricalItemsTotalSI'          => $ElectricalItemsTotalSI,
                'IsPAToOwnerDriverCoverd'         => $IsPAToOwnerDriverCoverd,
		        'IsLiabilityToPaidDriverCovered'  => $IsLiabilityToPaidDriverCovered,
                'IsTPPDCover'                     => $TPPDCover,//($tp_only == 'true') ? 'false' : 'true',
                'IsBasicODCoverage'               => ($tp_only == 'true') ? 'false' : 'true',
                'IsBasicLiability'                => ($tp_only == 'true') ? 'true' : 'false',
                'IsNonElectricalItemFitted'       => $IsNonElectricalItemFitted,
                'NonElectricalItemsTotalSI'       => $NonElectricalItemsTotalSI,
                'IsPAToDriverCovered'             => $IsPAToDriverCovered,
                'IsBiFuelKit'                     => $is_bifuel_kit,
                'BiFuelKitSi'                     => $BiFuelKitSi,
                'IsBifuelTypeChecked'             => $bifuel,
                'SecurePlus'                      => $isSecurePlus,
                'SecurePremium'                   => $isSecurePremium,
                'IsInsurancePremium'              => 'true',
                'IsReturntoInvoice'             => "$RTITrue",
                'IsLiabilityToEmployeeCovered'  => $isLiabilityToEmployeeCovered,
                'LiabilityToEmployee'           => $liabilitytoemployee,


                'VoluntaryDeductible'             => [
                    'VoluntaryDeductible' => ['SumInsured' => $VoluntaryDeductible],
                ],
                'GeographicalExtension'             => [
                    'GeographicalExtension' => [
                        'Countries' => $Countries,
                    ],
                ],
                'IsTPPDCover'                     => $TPPDCover,
                'TPPDCover' => [
                    'TPPDCover' =>  [
                        'SumInsured' => ($TPPDCover == 'true') ? 6000 : '',
                        'IsMandatory' => 'false',
                        'PolicyCoverID' => "",
                        'IsChecked' => $TPPDCover,
                        'NoOfItems' => "",
                        'PackageName' => "",
                    ],
                ],
                'IsAntiTheftDeviceFitted'         => $anti_theft,
                'IsAutomobileAssociationMember'   => 'false',
                'AutomobileAssociationName'       => '',
                'AutomobileAssociationNo'         => '',
                'AutomobileAssociationExpiryDate' => '',
		        'PACoverToOwnerDriver' => '1',
                'PACoverToOwner'                  => [
                    'PACoverToOwner' => [
                        'IsChecked'           => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                        'NoOfItems'           => ($requestData->vehicle_owner_type == 'I') ? '1' : '',
                        'CPAcovertenure'      => ($requestData->vehicle_owner_type == 'I') ? $cpa_tenure : '',
                        'PackageName'         => '',
                        'NomineeName'         => $NomineeName,
                        'NomineeDOB'          => $NomineeDOB,
                        'NomineeRelationship' => $NomineeRelationship,
                        'NomineeAddress'      => $NomineeAddress,
                        'AppointeeName'       => '',
                        'OtherRelation'       => '',
                    ],
                ],
                'PAToUnNamedPassenger'            => [
                    'PAToUnNamedPassenger' => [
                        'IsChecked'  => $PAToUnNamedPassenger_IsChecked,
                        'NoOfItems'  => $PAToUnNamedPassenger_NoOfItems,
                        'SumInsured' => $PAToUnNamedPassengerSI,
                    ],
                ],
                'PAToPaidDriver'                  => [
                    'PAToPaidDriver' => [
                        'IsChecked'  => $PAToPaidDriver_IsChecked,
                        'NoOfItems'  => $PAToPaidDriver_NoOfItems,
                        'SumInsured' => $PAToPaidDriver_SumInsured,
                    ],
                ],
                'LiabilityToPaidDriver'			=> [
                    'LiabilityToPaidDriver' => [
                            'IsMandatory' => $IsLiabilityToPaidDriverCovered,
                            'IsChecked' => $LiabilityToPaidDriver_IsChecked,
                            'NoOfItems' => '1',
                            'PackageName' => '',
                            'PolicyCoverID' => '',
                    ]
                ],
                'BifuelKit' => [
                    'BifuelKit' => [
                        'IsChecked'            => 'false',
                        'IsMandatory'          => 'false',
                        'PolicyCoverDetailsID' => '',
                        'Fueltype'             => $Fueltype,
                        'ISLpgCng'             => $bifuel,
                        'PolicyCoverID'        => '',
                        'SumInsured'           => $BiFuelKitSi,
                        'NoOfItems'            => '',
                        'PackageName'          => '',
                    ],
                ],
                'ReturntoInvoiceCoverage'  => $ReturnToInvoiceDetails,
                'IsA2KSelected' => $SelRSA,
                'A2KDiscountCover' =>  [
                    'A2KCover' => [
                        'CoverageName' => 'Assistance Cover',
                        'IsChecked' => $SelRSA,
                        'Rate' => 100.00,
                        'CoverCode' => 'Cover15',
                        'SubCoverName' => '',
                        'CalculationType' => 'ODDiscount'
                    ]
                ],
            ],
            'PreviousInsuranceDetails' => $previous_insurance_details,
            'NCBEligibility'           => [
                'NCBEligibilityCriteria' => $NCBEligibilityCriteria,
                'NCBReservingLetter'     => '',
                'PreviousNCB'            => $PreviousNCB,
            ],
            'ProductCode'              => $productCode,
            'UserID'                   => $UserID,
            'SourceSystemID'           => $SourceSystemID,
            'AuthToken'                => $AuthToken,
            'IsQuickquote'             => ($tp_only == 'true') ? 'false' : 'true',
        ];

        if($NomineeName == '')
        {
            unset($premium_req_array['Cover']['PACoverToOwner']['PACoverToOwner']['NomineeName']);
        }

        if ($requestData->business_type == 'newbusiness') {
            unset($premium_req_array['PreviousInsuranceDetails']);
        }

        if (in_array($premium_type, ['own_damage', 'own_damage_breakin'])) {
            $premium_req_array = array_merge_recursive($premium_req_array, ['Policy' => $previous_tp_array]);
        }

        $agentDiscount = calculateAgentDiscount($enquiryId, 'reliance', 'car');
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

        $premium_req_array = trim_array($premium_req_array);
        // pay as you drive for coverage details newbussiness 
        $odometerreading=($BusinessType==1)?100:1000;
        if ($productData->good_driver_discount=="Yes" && $isGddEnabled) {
            $premium_req_array['Cover']['IsPayAsYouDrive']="";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']=[

                'isCoverEligible'=>"",
                'CurrentVehicalOdoMtrReading'=>"$odometerreading",
                'rate'=>"",//should be in decimal
                'minKMRange'=>"",//should be in decimal
                'maxKMRange'=>"",//should be in decimal
                'PlanDescription'=>"",
                'CoverUptoKm'=>"",//should be in decimal
                'CoverUptoKm'=>"",//should be in decimal
                'IsChecked'=>"",
                'isOptedByCustomer'=>"",
            ];
            
        }

        $get_response = getWsData(
            config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_COVERAGE'),
            $premium_req_array,
            'reliance',
            [
                'root_tag'      => 'PolicyDetails',
                'section'       => $productData->product_sub_type_code,
                'method'        => 'Coverage Calculation',
                'requestMethod' => 'post',
                'enquiryId'     => $enquiryId,
                'productName'   => $productData->product_name. " ($business_type)",
                'transaction_type'    => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ]
            ]
        );
        $minKmRange=0;
        $maxKMRange=0;
        $PlanDescription="";
        $rate="";
        $CoverUptoKm=0;
        $coverage_res_data = $get_response['response'];

        if($coverage_res_data)
        {

            $coverage_res_data = json_decode($coverage_res_data);
            if(!isset($coverage_res_data->ErrorMessages))
            {
                $nil_dep_rate = '';
                $secure_plus_rate = '';
                $secure_premium_rate = '';
                foreach ($coverage_res_data->LstAddonCovers as $k => $v)
                {
                    if($v->CoverageName == 'Nil Depreciation')
                    {
                       $nil_dep_rate = $v->rate;
                    }
                    elseif ($v->CoverageName == 'Secure Plus')
                    {
                        $secure_plus_rate = $v->rate;
                    }
                    elseif ($v->CoverageName == 'Secure Premium')
                    {
                        $secure_premium_rate = $v->rate;
                    }
                    if ($v->CoverageName=='Pay As You Drive' && $isGddEnabled && $productData->good_driver_discount=="Yes") 
                    {
                        $CoverUptoKm=(int) $mDistance+$odometerreading;
                        foreach ($v->PayAsYouDrive as $key => $value) {
                            if($value->maxKMRange==$mDistance){
                                $minKmRange=$value->minKMRange;
                                $maxKMRange=$value->maxKMRange;
                                $PlanDescription=$value->PlanDescription;
                                $rate=$value->rate;
                                break;
                            }
                        }
                        // for pay as you drive premium calculation from the repsonse of coveragedetails
                        $premium_req_array['Cover']['IsPayAsYouDrive']="true";
                        $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']=[
                            'isCoverEligible'=>"true",
                            'CurrentVehicalOdoMtrReading'=>"$odometerreading",
                            'rate'=>$rate,//should be in decimal
                            'minKMRange'=>$minKmRange,//should be in decimal
                            'maxKMRange'=>$maxKMRange,//should be in decimal
                            'PlanDescription'=>$PlanDescription,
                            'CoverUptoKm'=>"$CoverUptoKm",//should be in decimal
                            'IsChecked'=>"true",
                            'isOptedByCustomer'=>"true",
                        ];
                    }
                    // taking the coverage response for return to invoice and passing to premium calculation to get final RTI premium
                    if($v->CoverageName=='Return to Invoice' && $enableRTI)
                    {
                        if(!empty($v->ReturntoInvoice[0]))
                        {

                            $premium_req_array['Cover']['ReturntoInvoiceCoverage']['AddonSumInsuredFlatRates']=[
                                'IsChecked'=>'true',
                                'isOptedByCustomer'=>'true',
                                'isOptedByCustomerRate'=>'addonOptedYesRate',
                                'addonOptedYesRate'=>$v->ReturntoInvoice[0]->addonOptedYesRate,
                                'addonOptedNoRate'=>$v->ReturntoInvoice[0]->addonOptedNoRate,
                                'addonYesMultiplicationFactorRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonYesMultiplicationFactorRate,
                                'addonNoMultiplicationFactorRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonNoMultiplicationFactorRate,
                                'ageofVehicleRate'=>$v->ReturntoInvoice[0]->RelativityFactor->ageofVehicleRate,
                                'vehicleCCRate'=>$v->ReturntoInvoice[0]->RelativityFactor->vehicleCCRate,
                                'zoneRate'=>$v->ReturntoInvoice[0]->RelativityFactor->zoneRate,
                                'parkingRate'=>$v->ReturntoInvoice[0]->RelativityFactor->parkingRate,
                                'drivingAgeRate'=>$v->ReturntoInvoice[0]->RelativityFactor->driverAgeRate,
                                'ncbApplicableRate'=>$v->ReturntoInvoice[0]->RelativityFactor->ncbApplicabilityRate,
                                'noOfVehicleUserRate'=>$v->ReturntoInvoice[0]->RelativityFactor->noOfVehicleUserRate,
                                'occupationRate'=>$v->ReturntoInvoice[0]->RelativityFactor->occupationRate,
                                'policyIssuanceMethodRate'=>$v->ReturntoInvoice[0]->RelativityFactor->policyIssuanceMethodRate,
                                'existingRGICustomerRate'=>$v->ReturntoInvoice[0]->RelativityFactor->existingRGICustomerRate,
                                'addonLastYearYesRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonLastYearYesRate,
                                'addonLastYearNoRate'=>$v->ReturntoInvoice[0]->RelativityFactor->addonLastYearNoRate,
                            ];
                        }
                        else
                        {
                            $premium_req_array['Cover']['ReturntoInvoiceCoverage']='';
                            $premium_req_array['Cover']['IsReturntoInvoice']='false';
                        }
                    }
                    if($v->CoverageName == 'Assistance cover- 24/7 RSA' && $SelRSA == 'true') {
                        $RsaAvailable = true;
                        if($RsaAvailable){
                            $premium_req_array['Cover']['IsA2KSelected'] = "true";
                            $premium_req_array['Cover']['A2KDiscountCover']['A2KCover'] = [
                                'CoverageName' => $v->CoverageName ,
                                "rate" => $v->rate ,
                                "IsChecked" => "true",
                                "SubCoverName" => "",
                                "CoverCode" => $v->A2KCoverCode,
                                "CalculationType" => $v->CalculationType
                            ];
                        }
                        else
                        {
                            unset($premium_req_array['Cover']['IsA2KSelected']);
                            unset($premium_req_array['Cover']['A2KDiscountCover']);
                        }
                    }
                }

                if($masterProduct->product_identifier == 'zero_dep')
                {
                    $premium_req_array['Cover']['SecurePlus'];
                    $premium_req_array['Cover']['SecurePremium'];
                    $premium_req_array['Cover']['IsNilDepreciation'] = 'true';
                    $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                }elseif($masterProduct->product_identifier == 'secure_plus'){
                    $premium_req_array['Cover']['IsSecurePlus'] = 'true';
                    $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = 'true';
                    unset($premium_req_array['Cover']['SecurePremium']);
                    $premium_req_array['Cover']['SecurePlus'] = [
                        'SecurePlus' => [
                            'IsChecked' => 'true',
                            'ApplicableRate' => $secure_plus_rate,
                        ],
                    ];
                }elseif($masterProduct->product_identifier == 'secure_premium'){
                    $premium_req_array['Cover']['IsSecurePremium'] = 'true';
                    $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = 'true';
                    unset($premium_req_array['Cover']['SecurePlus']);
                    $premium_req_array['Cover']['SecurePremium'] = [
                        'SecurePremium' => [
                            'IsChecked' => 'true',
                            'ApplicableRate' => $secure_premium_rate,
                        ],
                    ];
                }elseif ($premium_type == 'third_party'){
                    $premium_req_array['Cover']['IsSecurePremium'] = '';
                    $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = '';
                    unset($premium_req_array['Cover']['SecurePlus']);
                    unset($premium_req_array['Cover']['SecurePremium']);
                }
                $get_response = getWsData(
                    config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PREMIUM'),
                    $premium_req_array,
                    'reliance',
                    [
                        'root_tag'      => 'PolicyDetails',
                        'section'       => $productData->product_sub_type_code,
                        'method'        => 'Premium Calculation',
                        'requestMethod' => 'post',
                        'enquiryId'     => $enquiryId,
                        'productName'   => $productData->product_name. " ($business_type)",
                        'transaction_type'    => 'proposal',
                        'headers' => [
                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                        ]
                    ]
                );
                $premium_res_data = $get_response['response'];

                if (empty($premium_res_data)) {
                    return [
                        'status'  => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Insurer not reachable."
                    ];
                }
                $response = json_decode($premium_res_data);
                if (!isset($response->MotorPolicy)) {
                    return [
                        'status'  => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Insurer not reachable. Please try again after sometime."
                    ];
                }
                $response = $response->MotorPolicy;
                unset($premium_res_data);
                if (trim($response->ErrorMessages) == '')
                {
                    $corres_address_data = DB::table('reliance_pincode_state_city_master')
                    ->where('pincode', $proposal->pincode)
                    ->select('*')
                    ->first();

                    // salutaion

                    if ($requestData->vehicle_owner_type == "I") {
                        if (in_array(strtoupper($proposal->gender), ['MALE', 'M'])) {
                            $Salutation = 'Mr.';
                        } else {
                            if ((in_array(strtoupper($proposal->gender), ['FEMALE', 'F'])) && $proposal->marital_status == "Single") {
                                $Salutation = 'Miss.';
                            } else {
                                $Salutation = 'Mrs.';
                            }
                        }
                    } else {
                        $Salutation = 'M/S.';
                    }
                        // salutaion

                    $ClientDetails = [
                        'ClientType'         => $ClientType,
                        'Salutation'         => $Salutation,
                        'ForeName'           => $ForeName,
                        'LastName'           => $LastName,
                        'CorporateName'      => $CorporateName,
                        'MidName'            => '',
                        'OccupationID'       => $OccupationID,
                        'DOB'                => $DOB,
                        'Gender'             => $Gender,
                        'PhoneNo'            => '',
                        'MobileNo'           => $proposal->mobile_number,
                        'RegisteredUnderGST' => trim($proposal->gst_number) == '' ? '0' : '1',
                        'RelatedParty'       => '0',
                        'GSTIN'              => $proposal->gst_number,
                        'GroupCorpID'        => '',
                        'ClientAddress'      => [
                            'CommunicationAddress' => [
                                'AddressType'     => '0',
                                'Address1'        => trim($getAddress['address_1']),
                                'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                'Address3'        => trim($getAddress['address_3']),
                                'CityID'          => $corres_address_data->city_or_village_id_pk,
                                'DistrictID'      => $corres_address_data->district_id_pk,
                                'StateID'         => $corres_address_data->state_id_pk,
                                'Pincode'         => $proposal->pincode,
                                'Country'         => '1',
                                'NearestLandmark' => '',
                            ],
                            'RegistrationAddress'  => [
                                'AddressType'     => '0',
                                'Address1'        => trim($getAddress['address_1']),
                                'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                'Address3'        => trim($getAddress['address_3']),
                                'CityID'          => $corres_address_data->city_or_village_id_pk,
                                'DistrictID'      => $corres_address_data->district_id_pk,
                                'StateID'         => $corres_address_data->state_id_pk,
                                'Pincode'         => $proposal->pincode,
                                'Country'         => '1',
                                'NearestLandmark' => '',
                            ],
                            'PermanentAddress'     => [
                                'AddressType'     => '0',
                                'Address1'        => trim($getAddress['address_1']),
                                'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                                'Address3'        => trim($getAddress['address_3']),
                                'CityID'          => $corres_address_data->city_or_village_id_pk,
                                'DistrictID'      => $corres_address_data->district_id_pk,
                                'StateID'         => $corres_address_data->state_id_pk,
                                'Pincode'         => $proposal->pincode,
                                'Country'         => '1',
                                'NearestLandmark' => '',
                            ],
                            'InspectionAddress' => [
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
                            ]
                        ],
                        'EmailID'            => $proposal->email,
                        'MaritalStatus'      => $MaritalStatus,
                        'Nationality'        => '1949'
                    ];
                    unset($premium_req_array['ClientDetails']);
                    $client['ClientDetails'] = $ClientDetails;
                    $premium_req_array = array_merge($client,$premium_req_array);

                    $get_response = getWsData(
                        config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_CAR_PROPOSAL_POST_INSPECTION'),
                        $premium_req_array,
                        'reliance',
                        [
                            'root_tag'      => 'PolicyDetails',
                            'section'       => $productData->product_sub_type_code,
                            'method'        => 'Proposal Creation for Post Inspection',
                            'requestMethod' => 'post',
                            'enquiryId'     => $enquiryId,
                            'productName'   => $productData->product_name. " ($business_type)",
                            'transaction_type'    => 'proposal',
                            'headers' => [
                                'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                            ]
                        ]
                    );

                        
                    $proposal_res_data = $get_response['response'];
                    $proposal_resp = json_decode($proposal_res_data)->MotorPolicy;
                    unset($proposal_res_data);
                    if($proposal_resp->status == '1')
                    {
                        $basic_od = 0;
                        $tppd = 0;
                        $pa_unnamed = 0;
                        $pa_paid_driver = 0;
                        $electrical_accessories = 0;
                        $non_electrical_accessories = 0;
                        $zero_dep_amount = 0;
                        $ncb_discount = 0;
                        $lpg_cng = 0;
                        $lpg_cng_tp = 0;
                        $anti_theft = 0;
                        $liabilities = 0;
                        $voluntary_deductible = 0;
                        $tppd_discount = 0;
                        $other_addon_amount = 0;
                        $geog_Extension_OD_Premium = 0;
                        $geog_Extension_TP_Premium = 0;
                        $RTIAddonPremium = '0';
                        $inspection_charges = !empty((int) $proposal_resp->InspectionCharges) ? (int) $proposal_resp->InspectionCharges : 0;
                        $liability_to_employee_premium = 0;

                        $cpa_premium = 0;
                        $proposal_resp->lstPricingResponse = is_object($proposal_resp->lstPricingResponse) ? [$proposal_resp->lstPricingResponse] : $proposal_resp->lstPricingResponse;
                        foreach ($proposal_resp->lstPricingResponse as $single) {
                            if (isset($single->CoverageName)) {
                                if ($single->CoverageName == 'Basic OD') {
                                    $basic_own_damage = $single->Premium + $inspection_charges;
                                } elseif ($single->CoverageName == 'PA to Owner Driver') {
                                    $cpa_premium = $single->Premium;
                                } elseif ($single->CoverageName == 'Nil Depreciation') {
                                    $zero_dep_amount = $single->Premium;
                                } elseif ($single->CoverageName == 'Bifuel Kit') {
                                    $lpg_cng = $single->Premium;
                                } elseif ($single->CoverageName == 'Electrical Accessories') {
                                    $electrical_accessories = $single->Premium;
                                } elseif ($single->CoverageName == 'Non Electrical Accessories') {
                                    $non_electrical_accessories = $single->Premium;
                                } elseif ($single->CoverageName == 'NCB') {
                                    $ncb_discount = abs((float) $single->Premium);
                                } elseif ($single->CoverageName == 'Total OD and Addon') {
                                    $basic_od = abs((float) $single->Premium);
                                } elseif ($single->CoverageName == 'Secure Plus' || $single->CoverageName == 'Secure Premium') {
                                    $other_addon_amount = abs((float) $single->Premium);
                                } elseif ($single->CoverageName == 'Basic Liability') {
                                    $tppd = abs((float) $single->Premium);
                                } elseif ($single->CoverageName == 'PA to Unnamed Passenger') {
                                    $pa_unnamed = $single->Premium;
                                } elseif ($single->CoverageName == 'PA to Paid Driver') {
                                    $pa_paid_driver = $single->Premium;
                                } elseif ($single->CoverageName == 'Liability to Paid Driver') {
                                    $liabilities = $single->Premium;
                                } elseif ($single->CoverageName == 'Bifuel Kit TP') {
                                    $lpg_cng_tp = $single->Premium;
                                } elseif ($single->CoverageName == 'Automobile Association Membership') {
                                    $automobile_association = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Anti-Theft Device') {
                                    $anti_theft = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Voluntary Deductible') {
                                    $voluntary_deductible = abs($single->Premium);
                                } elseif ($single->CoverageName == 'TPPD') {
                                    $tppd_discount = abs($single->Premium);
                                } elseif (in_array($single->CoverageName, ['Geographical Extension', 'Geo Extension']) && $single->CoverID == 5) {
                                    $geog_Extension_OD_Premium = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Geographical Extension' && ($single->CoverID =='6' || $single->CoverID == '403')) {
                                    $geog_Extension_TP_Premium = abs($single->Premium);
                                } elseif ($single->CoverageName == 'Return to invoice') {
                                    $RTIAddonPremium = abs($single->Premium ?? 0);
                                }
                                elseif ($single->CoverageName == 'Liability to Employees') {
                                    $liability_to_employee_premium = abs($single->Premium ?? 0);
                                }
                            }
                        }
                        $netPremium = $proposal_resp->NetPremium;
                        $final_discount = $ncb_discount + $voluntary_deductible + $anti_theft + $tppd_discount;
                        $total_tp_amount = $tppd + $liabilities + $pa_unnamed + $lpg_cng_tp + $pa_paid_driver + $cpa_premium - $tppd_discount + $geog_Extension_TP_Premium + $liability_to_employee_premium;
                        $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $lpg_cng + $zero_dep_amount + $other_addon_amount + $geog_Extension_OD_Premium + $RTIAddonPremium;
                        $final_payable_amount = $proposal_resp->FinalPremium;

                        UserProposal::where('user_product_journey_id', $enquiryId)->update([
                            'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                            'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                            'final_payable_amount' => $proposal_resp->FinalPremium,
                            'proposal_no' => $proposal_resp->ProposalNo,
                            'od_premium'            => $basic_od,
                            'tp_premium'            => $total_tp_amount,
                            'addon_premium'         => $total_addon_amount,
                            'cpa_premium'           => $cpa_premium,
                            'total_premium'         => $netPremium,
                            'total_discount'        => $final_discount,
                            'service_tax_amount'    => $final_payable_amount - $netPremium,
                            'final_payable_amount'  => $final_payable_amount,
                        ]);

                        ReliancePremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        return [
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!"
                        ];

                    }
                    else
                    {
                        return [
                            'status'  => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $proposal_resp->ErrorMessages
                        ];

                    }
                }
                else
                {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message'        => $response->ErrorMessages
                    ];
                }
            }
            else
            {
                return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message'        => $coverage_res_data->ErrorMessages
                ];
            }
        }
        else
        {
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => 'Insurer Not Reachable'
            ];
        }
    }
}