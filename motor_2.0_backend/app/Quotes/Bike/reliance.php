<?php
include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
use Illuminate\Support\Facades\DB;
use App\Quotes\Bike\V1\Reliance as RELIANCE_V1;
use App\Models\SelectedAddons;

function getQuote($enquiryId, $requestData, $productData) {

    if(config('IC.RELIANCE.V1.BIKE.ENABLE') == 'Y'){
        return RELIANCE_V1::getQuote($enquiryId, $requestData, $productData);
    }

    $refer_webservice = $productData->db_config['quote_db_cache'];
    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available',
            'request'=> $requestData->rto_code
        ];
    }

    // $rto_code = $requestData->rto_code;  
    // $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code

    // $rto_data = DB::table('reliance_rto_master as rm')
    //     ->where('rm.region_code', $rto_code)
    //     ->select('rm.*')
    //     ->first();

    // if (empty($rto_data)) {
    //     return [
    //         'status' => false,
    //         'premium' => '0',
    //         'message' => 'RTO not available',
    //         'request'=> [
    //             'rto_code'=>$rto_code,
    //             'rto_data'=>$rto_data
    //         ]
    //     ];
    // }
    
    // if(isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }

    $mmv = get_mmv_details($productData, $requestData->version_id, 'reliance');

    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
            ]
        ];
    }

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
            ]
        ];
    } else {
        $vehicle_invoice_date = new DateTime($requestData->vehicle_invoice_date);
        $registration_date = new DateTime($requestData->vehicle_register_date);

        $date1 = !empty($requestData->vehicle_invoice_date) ? $vehicle_invoice_date : $registration_date;
        
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = ceil($age / 12);

        // As confirmed from IC ZD is on condition on type of vehicle and rto and if getting coverage service then show or else 0
        // if ($vehicle_age > 3 && $productData->zero_dep == '0') {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => true,
        //         'message' => 'Zero dep is not allowed for vehicle age greater than 3 years',
        //         'request'=> [
        //             'vehicle_age'=>$vehicle_age,
        //             'productData'=>$productData->zero_dep
        //         ]
        //     ];
        // }

        $ncb_levels = [
            '0' => '0',
            '20' => '1',
            '25' => '2',
            '35' => '3',
            '45' => '4',
            '50' => '5'
        ];

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        /* if($premium_type != 'third_party' && $requestData->previous_policy_type == 'Third-party') {
            $requestData->business_type = 'breakin';
        } */
        $is_breakin     = ($requestData->business_type == "breakin");
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $isMotorQuote = 'true';
        $isMotorQuoteFlow = '';
        $TPPDCover = 'false';
        $policy_type = 'Comprehensive';
        $cpa_tenure = '1';
        $previous_policy_expiry_date = '';
        $PreviousNCB = $ncb_levels[$requestData->previous_ncb] ?? '0';
        $IsNCBApplicable = 'true';
        $NCBEligibilityCriteria = '2';
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                    
                }
            }
        }

        if (($interval->y >= 15) && ($tp_only == 'true')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 15 year',
            ];
    }

        if(($requestData->vehicle_registration_no != '') && $requestData->vehicle_registration_no != 'NEW')
        {
            $reg_no = explode('-',$requestData->vehicle_registration_no);

            if (count($reg_no) < 2 ) {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Invalid vehicle registration number',
                    'request' => [
                        'message' => 'Invalid vehicle registration number',
                        'vehicle_registration_no'=>$requestData->vehicle_registration_no
                    ]
                ];
            }
            // $Registration_Number = $reg_no[0] .'-'. $reg_no[1] .'-'. (isset($reg_no[2]) ? $reg_no[2] : '') .'-'. (isset($reg_no[3]) ? $reg_no[3] : '');
        }
        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $business_type = 'New Business';
            $ISNewVehicle = 'true';
            $productCode = '2375';
            // $Registration_Number = 'NEW';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            // By default CPA tenure will be 1 Year
            //$cpa_tenure = '5';
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '5';
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
        } elseif ($requestData->business_type == 'rollover') {
            $BusinessType = '5';
            $business_type = 'Roll Over';
            $ISNewVehicle = 'false';
            $productCode = '2312';
            // $Registration_Number = ($requestData->vehicle_registration_no != '') ? $Registration_Number: $rto_code . '-DU-7458';
            $previous_policy_expiry_date = $requestData->previous_policy_expiry_date;
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        } elseif ($requestData->business_type == 'breakin') {
            $BusinessType = '5';
            $business_type = 'Break-In';
            $ISNewVehicle = 'false';
            $productCode = '2312';
            // $Registration_Number = ($requestData->vehicle_registration_no != '') && isset($Registration_Number) ? $Registration_Number : $rto_code . '-DU-7458';
            $previous_policy_expiry_date = !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new']) ? $requestData->previous_policy_expiry_date : '';
            //$policy_start_date = date('Y-m-d', strtotime('+1 day'));
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            if($date_difference > 0 || $requestData->previous_policy_type == 'Not sure')
            {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }
            else{
                $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }
        }

        $rto_code = $requestData->rto_code;
        $registration_number = $requestData->vehicle_registration_no;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness',
            [
                'appendRegNumber' => '-DU-7458'
            ]
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }

        $Registration_Number = $rcDetails['rcNumber'];
        $rto_data = $rcDetails['rtoData'];

        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
            $productCode = ($ISNewVehicle == 'true') ? '2370' : '2348';
            $policy_type = 'Third Party';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $isMotorQuote = 'false';
            $isMotorQuoteFlow = 'false';
        } else if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            $productCode = '2308';
            $policy_type = 'Own Damage';
            $isMotorQuote = ($premium_type == 'own_damage_breakin') ? 'false' : 'true';
            $isMotorQuoteFlow = 'true';
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $vehicledate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $DateOfPurchase = date('d/m/Y', strtotime($vehicledate));
        $vehicle_manufacture_date = explode('-', $requestData->manufacture_year);
        $vehicle_register_date = date('d/m/Y', strtotime($requestData->vehicle_register_date));
        $IsNCBApplicable = 'true';
        $IsClaimedLastYear = 'false';
        $vehicle_in_90_days = 'Y';

        if ($requestData->is_claim == 'Y') {
            $IsNCBApplicable = 'false';
            $IsClaimedLastYear = 'true';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $PreviousNCB = $ncb_levels[$requestData->previous_ncb];
        }

        if ($requestData->business_type == 'breakin' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new']))  {
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);

            if ($date_diff > 90) {
                $NCBEligibilityCriteria = '1';
                $PreviousNCB = '0';
                $vehicle_in_90_days = 'N';
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
        if(in_array($requestData->previous_policy_type, ['Third-party']))
        {
            $NCBEligibilityCriteria = '1';
        }
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

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $electrical_accessories_selected = 'false';
        $electrical_accessories_value = '0';
        $non_electrical_accessories_selected = 'false';
        $non_electrical_accessories_value = '0';

        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'Electrical Accessories') {
                    $electrical_accessories_selected = 'true';
                    $electrical_accessories_value = $data['sumInsured'];
                }
                if ($data['name'] == 'Non-Electrical Accessories') {
                    $non_electrical_accessories_selected = 'true';
                    $non_electrical_accessories_value = $data['sumInsured'];
                }
            }
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = 'false';
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $cover_ll_paid_driver = 'false';
        #age calculation
       
        $is_geo_ext = false;
        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if (!in_array($premium_type, ['own_damage', 'own_damage_breakin']) && $data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = 'true';
                    $cover_pa_paid_driver_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger = 'true';
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'LL paid driver') {
                    $cover_ll_paid_driver = 'true';
                }

                if ($data['name'] == 'Geographical Extension') {
                    $is_geo_ext = true;
                    $countries = $data['countries'];
                }
            }
        }

        $is_voluntary_deductible = 'false';
        $voluntary_deductible_amt = 0;
        $anti_theft = 'false';

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
                    $anti_theft = 'true';
                }
            }
        }

        $type_of_fuel = $TypeOfFuel[strtolower($mmv_data->operated_by)];
        $IsNilDepreciation = 'false';
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $POSType = '';
        $POSAadhaarNumber = '';
        $POSPANNumber = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            $POSType = '2';
            $POSAadhaarNumber = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
            $POSPANNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
        }
        if(config('constants.motor.reliance.IS_POS_TESTING_MODE_ENABLE_RELIANCE') == 'Y')
        {
            $POSType = '2';
            $POSPANNumber = 'ABGTY8890Z';
            $POSAadhaarNumber = '569278616999';
        }
        if ($productData->zero_dep == 0) {
            $IsNilDepreciation = 'true';
        }

        $applicable_addons = [];
        $date_difference = get_date_diff('year', $requestData->vehicle_register_date);
    
        /* if ($date_difference > 5) {
            $applicable_addons = [];
        } */

        $previous_insurance_details = [];
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
                'PolicyNo' => '1234567890',
                'PrevYearPolicyType' => ($requestData->previous_policy_type == 'Third-party') ? '2' : '1',
                'PrevInsuranceID' => '',
                'PrevYearInsurer' => '',
                'PrevYearPolicyNo' => '',
                'PrevYearInsurerAddress' => '',
                'PrevYearPolicyStartDate' => date('Y-m-d', strtotime('-1 year +1 day', strtotime($previous_policy_expiry_date))),
                'PrevYearPolicyEndDate' => date('Y-m-d', strtotime($previous_policy_expiry_date)),
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
        }

        if ($BusinessType == 6) {
            $policy_start_date = date('Y-m-d', strtotime('+3 day'));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        $premium_req_array = [
            'CoverDetails' => '',
            'TrailerDetails' => '',
            'ClientDetails' => [
                'ClientType' => '0',
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
            "Policy" => [
                "AgentCode" => "Direct",
                "AgentName" => "Direct",
                "BusinessType" => $BusinessType,
                "Branch_Name" => "Direct",
                "Cover_From" => $policy_start_date,
                "Cover_To" => $policy_end_date,
                "Branch_Code" => "9202",
                "productcode" => $productCode, //'2311',
                "OtherSystemName" => "1",
                "isMotorQuote" => $premium_type == 'breakin' ? 'false' : $isMotorQuote,
                "isMotorQuoteFlow" => $isMotorQuoteFlow,
                'POSType' => $POSType,
                'POSAadhaarNumber' => $POSAadhaarNumber,
                'POSPANNumber' => $POSPANNumber,
            ],
            "Risk" => [
                "VehicleMakeID" => $mmv_data->make_id_pk,
                "VehicleModelID" => $mmv_data->model_id_pk,
                "StateOfRegistrationID" => $rto_data->state_id_fk,
                "RTOLocationID" => $rto_data->model_region_id_pk,
                'Rto_RegionCode' => $rto_data->region_code,
                'Zone' => $rto_data->model_zone_name,
                'Colour' => '',
                'BodyType' => '',
                'OtherColour' => '',
                'GrossVehicleWeight' => '',
                'CubicCapacity' => '',
                "ExShowroomPrice" => "0",
                "IDV" => 0,
                "DateOfPurchase" => $DateOfPurchase,
                "ManufactureMonth" => $vehicle_manufacture_date[0],
                "ManufactureYear" => $vehicle_manufacture_date[1],
                "VehicleVariant" => $mmv_data->variance,
                "IsHavingValidDrivingLicense" => "",
                "IsOptedStandaloneCPAPolicy" => "",
                'LicensedCarryingCapacity' => '',
                'NoOfWheels' => '',
                'PurposeOfUsage' => '',
                'EngineNo' => '',
                'Chassis' => '',
                'TrailerIDV' => '',
                'IsVehicleHypothicated' => 'false',
                'FinanceType' => '',
                'FinancierName' => '',
                'FinancierAddress' => '',
                'FinancierCity' => '',
                'IsRegAddressSameasCommAddress' => 'true',
                'IsRegAddressSameasPermanentAddress' => 'true',
                'IsPermanentAddressSameasCommAddress' => 'true',
                'SalesManagerCode' => 'Direct',
                'SalesManagerName' => 'Direct',
                'BodyIDV' => '0',
                'ChassisIDV' => '0',
                'Rto_State_City' => '',
            ],
            "Vehicle" => [
                "TypeOfFuel" => $type_of_fuel,
                "ISNewVehicle" => $ISNewVehicle,
                "Registration_Number" => $Registration_Number,
                "Registration_date" => $vehicle_register_date,
                'SeatingCapacity' => $mmv_data->seating_capacity,
                'MiscTypeOfVehicle' => '',
                "MiscTypeOfVehicleID" => "",
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
            "Cover" => [
                'PACoverToNamedPassengerSI' => '0',
                "IsPAToUnnamedPassengerCovered" => $cover_pa_unnamed_passenger,
                'UnnamedPassengersSI' => $cover_pa_unnamed_passenger_amt,
                'IsRacingCovered' => 'false',
                'IsLossOfAccessoriesCovered' => 'false',
                'IsVoluntaryDeductableOpted' => $is_voluntary_deductible,
                'VoluntaryDeductableAmount' => $voluntary_deductible_amt,
                "IsElectricalItemFitted" => $electrical_accessories_selected,
                "ElectricalItemsTotalSI" => $electrical_accessories_value,
                "IsNonElectricalItemFitted" => $non_electrical_accessories_selected,
                "NonElectricalItemsTotalSI" => $non_electrical_accessories_value,
                'IsGeographicalAreaExtended' => 'false',
                'IsBiFuelKit' => 'false',
                'BiFuelKitSi' => '0',
                'IsAutomobileAssociationMember' => 'false',
                'IsVehicleMadeInIndia' => 'false',
                'IsUsedForDrivingTuition' => 'false',
                'IsInsuredAnIndividual' => 'false',
                'IsIndividualAlreadyInsured' => 'false',
                "IsPAToOwnerDriverCoverd" => $requestData->vehicle_owner_type == "I"  && $policy_type != 'Own Damage' ? "true" : "false",
                'ISLegalLiabilityToDefenceOfficialDriverCovered' => 'false',
                "IsLiabilityToPaidDriverCovered" => $cover_ll_paid_driver,
                'IsLiabilityToEmployeeCovered' => 'false',
                "IsPAToDriverCovered" => $cover_pa_paid_driver,
                'IsPAToPaidCleanerCovered' => 'false',
                'IsAdditionalTowingCover' => 'false',
                'IsLegalLiabilityToCleanerCovered' => 'false',
                'IsLegalLiabilityToNonFarePayingPassengersCovered' => 'false',
                'IsLegalLiabilityToCoolieCovered' => 'false',
                'IsCoveredForDamagedPortion' => 'false',
                'IsImportedVehicle' => 'false',
                'IsFibreGlassFuelTankFitted' => 'false',
                'IsConfinedToOwnPremisesCovered' => 'false',
                'IsAntiTheftDeviceFitted' => $anti_theft,
                'IsTPPDLiabilityRestricted' => 'false',
                "IsTPPDCover" => $TPPDCover,
                "IsBasicODCoverage" => "true",
                "IsBasicLiability" => $TPPDCover,
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
                'PACoverToOwnerDriver' => '1',
                'ElectricItems' => [
                    'ElectricalItems' => [
                        'ElectricalItemsID' => '',
                        'PolicyId' => '',
                        'SerialNo' => '',
                        'MakeModel' => '',
                        'ElectricPremium' => '',
                        'Description' => '',
                        'ElectricalAccessorySlNo' => '',
                        'SumInsured' => '0',
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
                        'SumInsured' => '',
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
                        'IsChecked' => $anti_theft,
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
                        'IsMandatory' => ($productData->zero_dep == 0) ? 'true' : 'false',
                        'IsChecked' => ($productData->zero_dep == 0) ? 'true' : 'false',
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
                        'NomineeName' => '',
                        'NomineeDOB' => '',
                        'NomineeRelationship' => '',
                        'NomineeAddress' => '',
                        'OtherRelation' => '',
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
                        'IsChecked' => $cover_pa_paid_driver,
                        'NoOfItems' => '1',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                        'SumInsured' => $cover_pa_unnamed_passenger_amt,
                    ],
                ],
                'PAToPaidDriver' => [
                    'PAToPaidDriver' => [
                        'IsMandatory' => 'false',
                        'IsChecked' => 'false',
                        'NoOfItems' => '',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                        'SumInsured' => '0',
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
                "IsA2KSelected" => "true",
                "A2KDiscountCover" =>  [
                    "A2KCover" => [
                        "CoverageName" => "Assistance Cover",
                        "IsChecked" => "true",
                        "Rate" => 100.00,
                        "CoverCode" => "Cover15",
                        "SubCoverName" => "",
                        "CalculationType" => "ODDiscount"
                    ]
                ],
            ],
            "PreviousInsuranceDetails" => $previous_insurance_details,
            "NCBEligibility" => [
                "NCBEligibilityCriteria" => $NCBEligibilityCriteria,
                "NCBReservingLetter" => "",
                "PreviousNCB" => $PreviousNCB,
            ],
            'LstCoveragePremium' => '',
            "ProductCode" => $productCode,
            "UserID" => $UserID,
            "SourceSystemID" => $SourceSystemID,
            "AuthToken" => $AuthToken,
        ];

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

        if ($requestData->business_type == 'newbusiness') {
            unset($premium_req_array['PreviousInsuranceDetails']);
        }
        $RTI2w = config('constants.IcConstants.reliance.RTI2W');
        $enable2wRTI = ($RTI2w =='Y') ? true : false;
        if($enable2wRTI)
        {
           $premium_req_array['Cover'] +=[
            "IsReturntoInvoice" => "true",
            "ReturntoInvoiceCoverage" => [
                "AddonSumInsuredFlatRates" => [
                    "IsChecked"                                 => "true",
                    "addonOptedYesRate"                         => "3.456",
                    "addonOptedNoRate"                          => "7.538",
                    "isOptedByCustomer"                         => "true",
                    "isOptedByCustomerRate"                     => "addonOptedYesRate",
                    "addonYesMultiplicationFactorRate"          => "12.356",
                    "addonNoMultiplicationFactorRate"           => "11.121",
                    "ageofVehicleRate"                          => "1.12",
                    "vehicleCCRate"                             => "1.11",
                    "zoneRate"                                  => "1.4",
                    "parkingRate"                               => "1.1",
                    "driverAgeRate"                             => "1.2",
                    "ncbApplicabilityRate"                      => "1.4",
                    "noOfVehicleUserRate"                       => "1.4",
                    "occupationRate"                            => "1.0",
                    "policyIssuanceMethodRate"                  => "1.4",
                    "existingRGICustomerRate"                   => "1.4",
                    "addonLastYearYesRate"                      => "1.4",
                    "addonLastYearNoRate"                       => "1.26",
                ]
            ]
           ];
        }

        $temp_data = $premium_req_array;
        $temp_data['product_identifier'] = $productData->product_identifier;
        $temp_data['type'] = 'Coverage Calculation';
        $temp_data['zero_dep'] = $productData->zero_dep;
        $checksum_data = checksum_encrypt($temp_data);
        // dump($checksum_data);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'reliance',$checksum_data,'BIKE');
        // dump($is_data_exits_for_checksum);
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
        {
            $get_response = $is_data_exits_for_checksum;
        }
        else
        {
            $get_response = getWsData(
                config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_COVERAGE'),
                $premium_req_array,
                'reliance',
                [
                    'root_tag' => 'PolicyDetails',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Coverage Calculation',
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
                    'checksum' => $checksum_data,
                    'productName' => $productData->product_name. " ($business_type)",
                    'transaction_type' => 'quote',
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                    ]
                ]
            );
        }
        $send_resp_rti=false;
        $coverage_res_data = $get_response['response'];
        if ($coverage_res_data) {
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Coverage Calculation - Success", "Success" );
            $coverage_res_data = json_decode($coverage_res_data);
            if (!isset($coverage_res_data->ErrorMessages)) {
                $nil_dep_rate = '';
                if (isset($coverage_res_data->LstAddonCovers)) {
                    foreach ($coverage_res_data->LstAddonCovers as $k => $v) {
                        if ($v->CoverageName == 'Nil Depreciation') {
                            $nil_dep_rate = $v->rate;
                        }
                        if($v->CoverageName =='Return to Invoice' && $enable2wRTI)
                        {
                            if(!empty($v->ReturntoInvoice[0]))
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
                                $send_resp_rti = true;
                            }
                            else
                            {
                                unset($premium_req_array['Cover']['ReturntoInvoiceCoverage']);
                                unset($premium_req_array['Cover']['IsReturntoInvoice']);
                                $send_resp_rti = false;
                            }
                        }
                        if($v->CoverageName == 'Assistance Cover (Two Wheeler Shield)')
                            {
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
                            
                    }

                    if ($IsNilDepreciation == 'true' && !empty($nil_dep_rate)) {
                        $premium_req_array['Cover']['IsNilDepreciation'] = $IsNilDepreciation;
                        $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                    }

                    $temp_data = $premium_req_array;
                    $temp_data['product_identifier'] = $productData->product_identifier;
                    $temp_data['type'] = 'Premium Calculation';
                    $temp_data['zero_dep'] = $productData->zero_dep;
                    $checksum_data = checksum_encrypt($temp_data);
                    if ($productData->product_identifier == "BASIC") {
                        $premium_req_array['Cover']['IsNilDepreciation'] = "false";
                        $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = "";
                        unset($premium_req_array['Cover']['ReturntoInvoiceCoverage']);
                        unset($premium_req_array['Cover']['IsReturntoInvoice']);
                        $send_resp_rti = false;
                        $premium_req_array['Cover']['IsA2KSelected'] = "false";
                        $premium_req_array['Cover']['A2KDiscountCover']['A2KCover'] = '';
                    }
                    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'reliance',$checksum_data,'BIKE');
                    if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                    {
                        $get_response = $is_data_exits_for_checksum;
                    }
                    else
                    {
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
                                'checksum' => $checksum_data,
                                'productName' => $productData->product_name. " ($business_type)",
                                'transaction_type' => 'quote',
                                'headers' => [
                                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                                ]
                            ]
                        );
                    }

                    $premium_res_data = $get_response['response'];
                    if ($premium_res_data) {
                        $response = json_decode($premium_res_data)->MotorPolicy ?? '';
                        $skip_second_call = false;
                        if(empty($response)) {
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'premium_amount' => 0,
                                'status'         => false,
                                'message'        => 'Insurer not reachable',
                            ];
                        }
                        if($response->InspectionErrorMessage != ''){
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'premium_amount' => 0,
                                'status'         => false,
                                'message'        => $response->InspectionErrorMessage,
                            ];
                        }

                        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Premium Calculation - Success", "Success" );
                        unset($premium_res_data);
                        if (trim($response->ErrorMessages) == '') {
                            $min_idv = $response->MinIDV;
                            $max_idv = $response->MaxIDV;
                            if ($requestData->edit_idv != '') {
                                if ($requestData->is_idv_changed == 'Y')  {
                                    if ($response->MaxIDV != "" && $requestData->edit_idv >= floor($response->MaxIDV)) {
                                        $premium_req_array['Risk']['IDV'] = floor($response->MaxIDV);
                                    } elseif ($response->MinIDV != "" && $requestData->edit_idv <= ceil($response->MinIDV)) {
                                        $premium_req_array['Risk']['IDV'] = ceil($response->MinIDV);
                                    } else {
                                        $premium_req_array['Risk']['IDV'] = $requestData->edit_idv;
                                    } 
                                }else {
                                    
                                    $getIdvSetting = getCommonConfig('idv_settings');
                                    switch ($getIdvSetting) {
                                        case 'default':
                                            $premium_req_array['Risk']['IDV'] = $requestData->edit_idv;
                                            $skip_second_call = true;
                                            $vehicle_idv =  $requestData->edit_idv;
                                            break;
                                        case 'min_idv':
                                            $premium_req_array['Risk']['IDV'] = $min_idv;
                                            $vehicle_idv =  $min_idv;
                                            break;
                                        case 'max_idv':
                                            $premium_req_array['Risk']['IDV'] = $max_idv;
                                            $vehicle_idv =  $max_idv;
                                            break;
                                        default:
                                            $premium_req_array['Risk']['IDV'] = $min_idv;
                                            $vehicle_idv =  $min_idv;
                                            break;
                                    }
                                    /* $premium_req_array['Risk']['IDV'] = $min_idv; */
                                }
                                if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $premium_req_array['Risk']['IDV'] >= 5000000){
                                    $premium_req_array['Policy']['POSType'] = '';
                                    $premium_req_array['Policy']['POSAadhaarNumber'] = '';
                                    $premium_req_array['Policy']['POSPANNumber'] = '';
                                }elseif(!empty($pos_data)){
                                    $premium_req_array['Policy']['POSType'] = '';
                                    $premium_req_array['Policy']['POSAadhaarNumber'] = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
                                    $premium_req_array['Policy']['POSPANNumber'] = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
                                }
                                if ($productData->product_identifier == "BASIC") {
                                    $premium_req_array['Cover']['IsNilDepreciation'] = "false";
                                    $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = "";
                                    unset($premium_req_array['Cover']['ReturntoInvoiceCoverage']);
                                    unset($premium_req_array['Cover']['IsReturntoInvoice']);
                                    $send_resp_rti = false;
                                    $premium_req_array['Cover']['IsA2KSelected'] = "false";
                                    $premium_req_array['Cover']['A2KDiscountCover']['A2KCover'] = '';
                                }
                                if(!$skip_second_call) {

                                $checksum_data = checksum_encrypt($premium_req_array);
                                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'reliance',$checksum_data,'BIKE');
                                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                                {
                                    $get_response = $is_data_exits_for_checksum;
                                }
                                else
                                {
                                    $get_response = getWsData(
                                        config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PREMIUM'),
                                        $premium_req_array,
                                        'reliance',
                                        [
                                            'root_tag' => 'PolicyDetails',
                                            'section' => $productData->product_sub_type_code,
                                            'method' => 'Premium Recalculation',
                                            'requestMethod' => 'post',
                                            'enquiryId' => $enquiryId,
                                            'checksum' => $checksum_data,
                                            'productName' => $productData->product_name. " ($business_type)",
                                            'transaction_type' => 'quote',
                                            'headers' => [
                                                'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                                            ]
                                        ]
                                    );
                                }
                            }
                                $response = $get_response['response'];
                                if ($response) {
                                    $response = json_decode($response)->MotorPolicy ?? '';
                                    update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Premium Recalculation - Success", "Success" );
                                    if(empty($response)) {
                                        return [
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'premium_amount' => 0,
                                            'status'         => false,
                                            'message'        => 'Insurer not reachable',
                                        ];
                                    }
                                    if($response->ErrorMessages !='')
                                    {
                                        return [
                                            'webservice_id' => $get_response['webservice_id'],
                                            'table' => $get_response['table'],
                                            'premium_amount' => 0,
                                            'status'         => false,
                                            'message'        => $response->ErrorMessages
                                        ];
                                    }
                                }
                            }

                            $basic_od = 0;
                            $odDiscount = 0;
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
                            $tppd_discount = 0;
                            $voluntary_excess = 0;
                            $ic_vehicle_discount = 0;
                            $other_discount = 0;
                            $RTIAddonPremium = 0;
                            $rsaAddonPremium = 0;
                            $inspection_charges = !empty($response->InspectionCharges) ? $response->InspectionCharges : 0;

                            $ribbonMessage = null;//ic will throw error if percentage exceeds
                            
                            $idv = $response->IDV;
                            $response->lstPricingResponse = is_object($response->lstPricingResponse) ? [$response->lstPricingResponse] : $response->lstPricingResponse;
                            foreach ($response->lstPricingResponse as $k => $v) {
                                $value =  trim(str_replace('-', '', $v->Premium));
                                if ($v->CoverageName == 'Basic OD') {
                                    $basic_od = $value + $inspection_charges;
                                } elseif (($v->CoverageName == 'Nil Depreciation')) {
                                    $zero_dep_amount = $value;
                                } elseif ($v->CoverageName == 'Bifuel Kit') {
                                    $lpg_cng = $value;
                                } elseif ($v->CoverageName == 'Electrical Accessories') {
                                    $electrical_accessories = $value;
                                } elseif ($v->CoverageName == 'Non Electrical Accessories') {
                                    $non_electrical_accessories = $value;
                                } elseif ($v->CoverageName == 'NCB') {
                                    $ncb_discount = abs($value);
                                } elseif ($v->CoverageName == 'Basic Liability') {
                                    $tppd = $value;
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
                                    $automobile_association = abs($value);
                                } elseif ($v->CoverageName == 'Anti-Theft Device') {
                                    $anti_theft = abs($value);
                                } elseif ($v->CoverageName == 'TPPD') {
                                    $tppd_discount = abs($value);
                                } elseif ($v->CoverageName == 'Voluntary Deductible') {
                                    $voluntary_excess = abs($value);
                                } elseif ($v->CoverageName == 'OD Discount') {
                                    $odDiscount = $value;
                                }
                                elseif ($v->CoverageName == 'Return to Invoice' && $enable2wRTI) {
                                    $RTIAddonPremium = $value;
                                }
                                elseif($v->CoverageName == 'Assistance Cover (Two Wheeler Shield)') {
                                    $rsaAddonPremium = $value;
                                }
                                unset($value);
                            }

                            $discountPercentage =  null;
                            $ribbonMessage = null;
                            if (isset($premium_req_array['Vehicle']['ODDiscount']) && $odDiscount > 0) {
                                $totalOD = $odDiscount + $basic_od;
                                // $discountPercentage = round(($odDiscount / $totalOD) * 100);
                                $basic_od = $totalOD;

                                // if ($discountPercentage != $premium_req_array['Vehicle']['ODDiscount']) {
                                //     $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount') . ' ' . $discountPercentage . '%';
                                // }
                            } else {
                                $odDiscount = 0;
                            }

                            // if ($response->TotalODPremium <= 100) {
                            //     $basic_od = $response->TotalODPremium + $ncb_discount + $anti_theft + $voluntary_excess;
                            // }
                            $loading_amount = 0;
                            if ($tp_only != 'true' && $response->TotalODPremium <= 100) {
                                $loading_amount = $ncb_discount + $anti_theft + $voluntary_excess;
                            }
                            $final_od_premium = $basic_od + $other_discount + $electrical_accessories + $non_electrical_accessories;
                            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed;
                            $final_total_discount = $ncb_discount + $anti_theft + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount + $odDiscount;
                            $final_total_discount_for_loading_cal = $ncb_discount + $anti_theft + $voluntary_excess + $ic_vehicle_discount + $other_discount ;//+ $tppd_discount;
                            $checkvalue = abs($final_od_premium - $final_total_discount_for_loading_cal);
                            if($tp_only != 'true' && $checkvalue < 100 && $response->TotalODPremium <= 100)
                            {
                                $loading_amount = 100 - $checkvalue;
                            }
                            $final_net_premium = $final_od_premium + $final_tp_premium;
                            $final_gst_amount = $final_net_premium * 0.18;
                            $final_payable_amount = $final_net_premium + $final_gst_amount;

                            /* if ((int) $zero_dep_amount == 0)
                            {
                                $applicable_addons = [];
                            } */
                            if (!empty($nil_dep_rate)) {
                                $applicable_addons = [
                                    'zeroDepreciation'
                                ];
                            }

                            $data_response = [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                "status" => true,
                                "msg" => "Found",
                                "Data" => [
                                    "idv" => $idv,
                                    "min_idv" => $min_idv != "" ? $min_idv : 0,
                                    "max_idv" => $max_idv != "" ? $max_idv : 0,
                                    "UnderwritingLoadingAmount" => $loading_amount,
                                    "vehicle_idv" => $idv,
                                    "qdata" => null,
                                    "pp_enddate" => $previous_policy_expiry_date,
                                    "addonCover" => null,
                                    "addon_cover_data_get" => "",
                                    "rto_decline" => null,
                                    "rto_decline_number" => null,
                                    "mmv_decline" => null,
                                    "mmv_decline_name" => null,
                                    "policy_type" => $policy_type,
                                    #"business_type" => "Rollover",
                                    "cover_type" => "1YC",
                                    "hypothecation" => "",
                                    "hypothecation_name" => "",
                                    "vehicle_registration_no" => (!in_array($requestData->vehicle_registration_no, ['', 'New', 'NEW', 'new'])) ? $requestData->vehicle_registration_no : $rto_code,
                                    "rto_no" => $rto_code,
                                    "version_id" => $requestData->version_id,
                                    "selected_addon" => [],
                                    "showroom_price" => 0,
                                    "fuel_type" => $requestData->fuel_type,
                                    "ncb_discount" => $requestData->applicable_ncb,
                                    "company_name" => $productData->company_name,
                                    "company_logo" => url(config("constants.motorConstant.logos")) . "/" . $productData->logo,
                                    "product_name" => $productData->product_sub_type_name,
                                    "mmv_detail" => [
                                        'manf_name' => $mmv_data->make_name,
                                        'model_name' => $mmv_data->model_name,
                                        'version_name' => $mmv_data->variance,
                                        'seating_capacity' => $mmv_data->seating_capacity,
                                        'carrying_capacity' => ((int) $mmv_data->seating_capacity) - 1,
                                        'cubic_capacity' => $mmv_data->cc,
                                        'engine_capacity_amount' => $mmv_data->cc,
                                        'vehicle_type' => 'BIKE',
                                        'version_id' => $mmv_data->ic_version_code,
                                        'fuel_type'   => $mmv_data->operated_by,
                                    ],
                                    "master_policy_id" => [
                                        "policy_id" => $productData->policy_id,
                                        "policy_no" => $productData->policy_no,
                                        "policy_start_date" => $policy_start_date,
                                        "policy_end_date" => $policy_end_date,
                                        "sum_insured" => $productData->sum_insured,
                                        "corp_client_id" => $productData->corp_client_id,
                                        "product_sub_type_id" => $productData->product_sub_type_id,
                                        "insurance_company_id" => $productData->company_id,
                                        "status" => $productData->status,
                                        "corp_name" => "",
                                        "company_name" => $productData->company_name,
                                        "logo" => env("APP_URL") . config("constants.motorConstant.logos") . $productData->logo,
                                        "product_sub_type_name" => $productData->product_sub_type_name,
                                        "flat_discount" => $productData->default_discount,
                                        "predefine_series" => "",
                                        "is_premium_online" => $productData->is_premium_online,
                                        "is_proposal_online" => $productData->is_proposal_online,
                                        "is_payment_online" => $productData->is_payment_online,
                                    ],
                                    "motor_manf_date" => $requestData->vehicle_register_date,
                                    "vehicle_register_date" => $requestData->vehicle_register_date,
                                    "vehicleDiscountValues" => [
                                        "master_policy_id" => $productData->policy_id,
                                        "product_sub_type_id" => $productData->product_sub_type_id,
                                        "segment_id" => 0,
                                        "rto_cluster_id" => 0,
                                        "car_age" => $vehicle_age,
                                        "aai_discount" => 0,
                                        "ic_vehicle_discount" => $ic_vehicle_discount + $odDiscount, //round($insurer_discount)
                                    ],
                                    "ic_vehicle_discount" => $ic_vehicle_discount + $odDiscount,
                                    'ribbon' => $ribbonMessage,
                                    "basic_premium" => $basic_od,
                                    "motor_electric_accessories_value" => $electrical_accessories,
                                    "motor_non_electric_accessories_value" => $non_electrical_accessories,
                                    "motor_lpg_cng_kit_value" => $lpg_cng,
                                    "total_accessories_amount(net_od_premium)" => $electrical_accessories + $non_electrical_accessories + $lpg_cng,
                                    "total_own_damage" => $final_od_premium,
                                    "tppd_premium_amount" => $tppd,
                                    "compulsory_pa_own_driver" => $pa_owner, // Not added in Total TP Premium
                                    "cover_unnamed_passenger_value" => $pa_unnamed,
                                    "default_paid_driver" => $liabilities,
                                    "motor_additional_paid_driver" => $pa_paid_driver,
                                    "cng_lpg_tp" => $lpg_cng_tp,
                                    "seating_capacity" => $mmv_data->seating_capacity,
                                    "deduction_of_ncb" => $ncb_discount,
                                    "antitheft_discount" => $anti_theft,
                                    "aai_discount" => $automobile_association,
                                    "voluntary_excess" => $voluntary_excess,
                                    "tppd_discount" => $tppd_discount,
                                    "other_discount" => $other_discount,
                                    "total_liability_premium" => $final_tp_premium,
                                    "net_premium" => $final_net_premium,
                                    "service_tax_amount" => $final_gst_amount,
                                    "service_tax" => 18,
                                    "total_discount_od" => 0,
                                    "add_on_premium_total" => 0,
                                    "addon_premium" => 0,
                                    "vehicle_lpg_cng_kit_value" => $requestData->bifuel_kit_value,
                                    "quotation_no" => "",
                                    "premium_amount" => $final_payable_amount,
                                    "service_data_responseerr_msg" => "success",
                                    "user_id" => $requestData->user_id,
                                    "product_sub_type_id" => $productData->product_sub_type_id,
                                    "user_product_journey_id" => $requestData->user_product_journey_id,
                                    "business_type" => ($requestData->business_type =='newbusiness') ? 'New Business' : (($is_breakin ? 'Breakin' : 'Roll over')),#($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over'))
                                    "service_err_code" => null,
                                    "service_err_msg" => null,
                                    "policyStartDate" => $requestData->business_type == 'breakin' ? "" : date("d-m-Y", strtotime($policy_start_date)),
                                    "policyEndDate" => date("d-m-Y", strtotime($policy_end_date)),
                                    "ic_of" => $productData->company_id,
                                    "vehicle_in_90_days" => $vehicle_in_90_days,
                                    "get_policy_expiry_date" => null,
                                    "get_changed_discount_quoteid" => 0,
                                    "vehicle_discount_detail" => [
                                        "discount_id" => null,
                                        "discount_rate" => null,
                                    ],
                                    "is_premium_online" => $productData->is_premium_online,
                                    "is_proposal_online" => $productData->is_proposal_online,
                                    "is_payment_online" => $productData->is_payment_online,
                                    "policy_id" => $productData->policy_id,
                                    "insurane_company_id" => $productData->company_id,
                                    "max_addons_selection" => null,
                                    "add_ons_data" => [
                                        "in_built" => [],
                                        "additional" => [
                                        ],
                                    ],
                                    'applicable_addons' =>$applicable_addons,
                                    "final_od_premium" => $final_od_premium,
                                    "final_tp_premium" => $final_tp_premium,
                                    "final_total_discount" => $final_total_discount,
                                    "final_net_premium" => $final_net_premium,
                                    "final_gst_amount" => $final_gst_amount,
                                    "final_payable_amount" => $final_payable_amount
                                ],
                            ];

                            if ($tp_only == 'true') {
                                $data_response['Data']['add_ons_data'] = [
                                    'in_built'   => [],
                                    'additional' => [
                                        'zero_depreciation' => '0'
                                    ]
                                ];
                            }

                            if($is_geo_ext)
                            {
                                $data_response['Data']['GeogExtension_ODPremium'] = 0;
                                $data_response['Data']['GeogExtension_TPPremium'] = 0;
                            }

                            if($enable2wRTI && $send_resp_rti)
                            {
                                    $data_response['Data']['add_ons_data']['additional'] = [
                                            'return_to_invoice' => $RTIAddonPremium
                                    ];
                            }
                            if(!empty($rsaAddonPremium))
                            {
                                    $data_response['Data']['add_ons_data']['additional'] = [
                                            'roadSideAssistance' => $rsaAddonPremium
                                    ];
                            }

                            if($requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
                            {
                                
                                $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
                            }
                            if (!empty($nil_dep_rate) && $productData->zero_dep == '0') {
                                $data_response['Data']['add_ons_data']['in_built'] = [
                                            'zero_depreciation' => $zero_dep_amount
                                    ];
                            }
                            if ($productData->product_identifier == 'BASIC') {
                                $data_response['Data']['add_ons_data'] = [
                                    'in_built'   => [],
                                    'additional' => [],
                                    'other'      => [],
                                ];
                            }
                            return camelCase($data_response);
                        } else {
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'premium_amount' => 0,
                                'status'         => false,
                                'message'        => $response->ErrorMessages
                            ];
                        }
                    } else {
                        return [
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'Insurer not reachable',
                        ];
                    }
                } else {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => 'Insurer not reachable',
                    ];
                }
            } else {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => $coverage_res_data->ErrorMessages
                ];
            }
        } else {
            return [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount' => 0,
                'status'         => false,
                'message'        => 'Insurer Not Reachable'
            ];
        }
    }
}