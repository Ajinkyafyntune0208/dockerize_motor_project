<?php

use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Support\Facades\Json;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';


function getpayasyoudriveQuote($request)
{
    $enquiryId   = customDecrypt($request->enquiryId);
    $requestData = getQuotation($enquiryId);
    $requestData->distance = $request->distance;
    $productData = getProductDataByIc($request->policyId);
    if ($productData->company_alias != $request->company_alias) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid company alias'
        ]);
    }

    if (isset($request->addons)) {
        $requestData->addons = $request->addons;
    }
    $quoteData = [];
    if (!empty($productData)) {
        if (!($requestData->product_sub_type_id == 1 && $requestData->product_id == 1)) {
            return response()->json([
                'status' => false,
                'message' => 'Product type mismatch'
            ]);
        }

        if (empty($requestData->previous_policy_expiry_date)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid previous policy expiry date'
            ]);
        }
        if (in_array($requestData->business_type, ['rollover']) && $requestData->previous_policy_expiry_date !== NULL) {
            $policy_days = get_date_diff('day', $requestData->previous_policy_expiry_date) * -1;
            $policy_allowed_days = 90;
            if ($policy_days > $policy_allowed_days) {
                return response()->json([
                    'status'     => false,
                    'message'   => 'Future Policy Expiry date is allowed only upto ' . $policy_allowed_days . ' days'
                ]);
            }
        } else if ($requestData->business_type == 'newbusiness') {
            $reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));
            $today = date('Y-m-d');
            if ($reg_date !== $today) {
                return response()->json([
                    'status'    => false,
                    'message'   => 'Registration date(' . $reg_date . ') should be today date(' . $today . ')for Newbusiness'
                ]);
            }
        }

        $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))
            ->first();
        if (isBhSeries($CorporateVehiclesQuotesRequest->vehicle_registration_no) && !(in_array($productData->company_alias, explode(',', config('CAR_BH_SERIES_ALLOWED_IC'))))) {
            return [
                'premium_amount'    => 0,
                'status'            => false,
                'message'           => 'BH Series number not allowed',
            ];
        }
    }
    $mmv = get_mmv_details($productData, $requestData->version_id, 'reliance');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    } else {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }
    if (isset($requestData->ownership_changed) && $requestData->ownership_changed != null && $requestData->ownership_changed == 'Y') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Quotes not allowed for ownership changed vehicle',
            'request' => [
                'message' => 'Quotes not allowed for ownership changed vehicle',
                'requestData' => $requestData
            ]
        ];
    }

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    } else {
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $car_age = ceil($age / 12);
        $pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        // zero depriciation validation
        // if ($car_age > 5 && $productData->zero_dep == '0') {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => true,
        //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //         'request' => [
        //             'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
        //             'vehicle_age' => $car_age
        //         ]
        //     ];
        // }
        if ($interval->y >= 15) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Car age should not be greater than 15 year',
                'request' => [
                    'message' => 'Car age should not be greater than 15 year',
                    'car_age' => $car_age
                ]
            ];
        }

        $IsNilDepreciation = 'false';
        if ($productData->zero_dep == '0') {
            $IsNilDepreciation = 'true';
        }

        // $rto_code = $requestData->rto_code;
        // $rto_code = RtoCodeWithOrWithoutZero($rto_code,true); //DL RTO code

        // $rto_data = DB::table('reliance_rto_master as rm')
        //     ->where('rm.region_code', $rto_code)
        //     ->select('rm.*')
        //     ->first();
        $isGddEnabled = config('IC.RELIANCE.V1.CAR.IS_GDD_ENABLED') == 'Y' ? true : false;
        $NCB_ID = [
            '0'      => '0',
            '20'     => '1',
            '25'     => '2',
            '35'     => '3',
            '45'     => '4',
            '50'     => '5'
        ];

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
        $policy_type = 'Comprehensive';
        $cpa_tenure = '1';
        $PreviousNCB = $NCB_ID[$requestData->previous_ncb] ?? '0';
        $IsNCBApplicable = 'true';
        $NCBEligibilityCriteria = '2';

        // if (($requestData->business_type != 'newbusiness' && $premium_type != 'third_party' && ($requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))) {
        //     return [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Break-In Quotes Not Allowed'
        //     ];
        // }

        if ($requestData->business_type == 'newbusiness') {
            $BusinessType = '1';
            $business_type = 'New Business';
            $productCode = '2374';
            $ISNewVehicle = 'true';
            // $Registration_Number = 'NEW';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            //$cpa_tenure = '3'; //By deafult CPA will be 1 year
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
            $previous_policy_expiry_date = '';
        } elseif ($requestData->business_type == 'rollover') {
            $BusinessType = '5';
            $business_type = 'Roll Over';
            $ISNewVehicle = 'false';
            $productCode = '2311';
            // $Registration_Number = ($requestData->vehicle_registration_no != '') ? $requestData->vehicle_registration_no : $rto_code . '-GA-8819';
            $previous_policy_expiry_date = $requestData->previous_policy_expiry_date;
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        } elseif ($requestData->business_type == 'breakin') {
            $BusinessType = '5';
            $business_type = 'Break-In';
            $ISNewVehicle = 'false';
            $productCode = '2311';
            // $Registration_Number = ($requestData->vehicle_registration_no != '') ? $requestData->vehicle_registration_no : $rto_code . '-GA-8819';
            $previous_policy_expiry_date = !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new']) ? $requestData->previous_policy_expiry_date : '';
            $policy_start_date = date('Y-m-d', strtotime('+3 day'));
        }

        $rto_code = $requestData->rto_code;
        $registration_number = $requestData->vehicle_registration_no;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness',
            [
                'appendRegNumber' => "-GA-8819"
            ]
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }

        $Registration_Number = $rcDetails['rcNumber'];
        $rto_data = $rcDetails['rtoData'];

        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') {
            $productCode = ($ISNewVehicle == 'true') ? '2371' : '2347';
            $policy_type = 'Third Party';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $policy_start_date = ($premium_type == 'third_party_breakin') ? date('Y-m-d', strtotime('+1 day')) : $policy_start_date;
        } else if ($premium_type == 'own_damage' || $premium_type == 'third_party_breakin') {
            $productCode = '2309';
            $policy_type = 'Own Damage';
        }
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        //$DateOfPurchase = date('Y-m-d', strtotime($requestData->vehicle_register_date));
        //as per git id 16724 reg date format DD/MM/YY
        $DateOfPurchase = date('d/m/Y', strtotime($requestData->vehicle_register_date));
        $vehicle_register_date = explode('-', $requestData->vehicle_register_date);
        $IsNCBApplicable = 'true';
        $IsClaimedLastYear = 'false';
        $vehicle_in_90_days = 'Y';

        if ($requestData->is_claim == 'Y') {
            $IsNCBApplicable = 'false';
            $IsClaimedLastYear = 'true';
            $NCBEligibilityCriteria = '1';
        }

        if ($requestData->business_type == 'breakin' && !in_array($requestData->previous_policy_expiry_date, ['NEW', 'New', 'new'])) {
            $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);

            if ($date_diff > 90) {
                $NCBEligibilityCriteria = '1';
                $PreviousNCB = '0';
                $vehicle_in_90_days = 'N';
            }
        }

        if (in_array($requestData->previous_policy_type, ['Not sure']) && $requestData->business_type != 'newbusiness') {
            $BusinessType = '6';
            $NCBEligibilityCriteria = '1';
            $PreviousNCB = '0';
            $IsNCBApplicable = 'false';
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

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

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

        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = $selected_addons->additional_covers;
            foreach ($additional_covers as $value) {
                if ($value['name'] == 'PA cover for additional paid driver' && !empty($value['sumInsured'])) {
                    $IsPAToDriverCovered = 'true';
                    $PAToPaidDriver_IsChecked = 'true';
                    $PAToPaidDriver_NoOfItems = '1';
                    $PAToPaidDriver_SumInsured = $value['sumInsured'];
                }

                if ($value['name'] == 'Unnamed Passenger PA Cover' && !empty($value['sumInsured'])) {
                    $IsPAToUnnamedPassengerCovered = 'true';
                    $PAToUnNamedPassenger_IsChecked = 'true';
                    $PAToUnNamedPassenger_NoOfItems = $mmv_data->seating_capacity;
                    $PAToUnNamedPassengerSI = $value['sumInsured'];
                }

                if ($value['name'] == 'LL paid driver') {
                    $IsLiabilityToPaidDriverCovered = 'true';
                    $LiabilityToPaidDriver_IsChecked = 'true';
                }

                if ($value['name'] == 'Geographical Extension') {
                    $IsGeographicalAreaExtended = 'true';
                    $Countries = 'true';
                }
            }
        }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;

        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;

        $is_bifuel_kit = 'false';

        if (in_array(strtolower($mmv_data->operated_by), ['petrol+cng', 'petrol+lpg'])) {
            $type_of_fuel = '5';
            $bifuel = 'true';
            $Fueltype = 'CNG';
            $is_bifuel_kit = 'true';
        } else {
            $type_of_fuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? '5' : $TypeOfFuel[strtolower($mmv_data->operated_by)];
            $bifuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
            $Fueltype = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? $mmv_data->operated_by : '';
            $is_bifuel_kit = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
        }

        $BiFuelKitSi = 0;

        if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = $selected_addons->accessories;
            foreach ($accessories as $value) {
                if ($value['name'] == 'Electrical Accessories') {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                }
                if ($value['name'] == 'Non-Electrical Accessories') {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                }
                if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $type_of_fuel = '5';
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];
                    $is_bifuel_kit = 'true';
                }
            }
        }

        $anti_theft = 'false';
        $IsVoluntaryDeductableOpted = 'false';
        $VoluntaryDeductible = '';
        $TPPDCover = 'false';

        if ($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != "") {
            $discounts = $selected_addons->discounts;

            foreach ($discounts as $value) {
                if ($value['name'] == 'anti-theft device') {
                    $anti_theft = 'true';
                }
                if ($value['name'] == 'voluntary_insurer_discounts' && $value['sumInsured'] > 0) {
                    $IsVoluntaryDeductableOpted = 'true';
                    $VoluntaryDeductible = $value['sumInsured'];
                }
                if ($value['name'] == 'TPPD Cover') {
                    $TPPDCover = 'true';
                }
            }
        }

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $POSType = '';
        $POSAadhaarNumber = '';
        $POSPANNumber = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();

        if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            $POSType = '2';
            $POSAadhaarNumber = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
            $POSPANNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
        }

        if (config('IC.RELIANCE.V1.CAR.IS_POS_TESTING_MODE_ENABLE') == 'Y') {
            $POSType = '2';
            $POSPANNumber = 'ABGTY8890Z';
            $POSAadhaarNumber = '569278616999';
        }

        $UserID = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_USERID'))) ? config('IC.RELIANCE.V1.CAR.TP_USERID') : config('IC.RELIANCE.V1.CAR.USERID');

        $SourceSystemID = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_SOURCE_SYSTEM_ID'))) ? config('IC.RELIANCE.V1.CAR.TP_SOURCE_SYSTEM_ID') : config('IC.RELIANCE.V1.CAR.SOURCE_SYSTEM_ID');

        $AuthToken = (($tp_only == 'true') && !empty(config('IC.RELIANCE.V1.CAR.TP_AUTH_TOKEN'))) ? config('IC.RELIANCE.V1.CAR.TP_AUTH_TOKEN') : config('IC.RELIANCE.V1.CAR.AUTH_TOKEN');
        $previous_insurance_details = [];
        if (in_array($requestData->previous_policy_type, ['Not sure'])) {
            $previous_insurance_details = ['IsPreviousPolicyDetailsAvailable' => 'No'];
        }
        if ($BusinessType != '6' && ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin')) {
            $previous_insurance_details = [
                'PrevYearPolicyType'            => ($requestData->previous_policy_type == 'Third-party') ? '2' : '1',
                'IsPreviousPolicyDetailsAvailable' => 'true',
                'PrevYearPolicyStartDate'       => date('Y-m-d', strtotime('-1 year +1 day', strtotime($previous_policy_expiry_date))),
                'PrevYearPolicyEndDate'         => date('Y-m-d', strtotime($previous_policy_expiry_date)),
                'PolicyNo'                      => '1234567890',
                'PrevPolicyPeriod'              => '1',
                'IsVehicleOfPreviousPolicySold' => 'false',
                'IsNCBApplicable'               => $IsNCBApplicable,
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
                'PrevYearInsurer'               => '10'
            ];
        }

        $branch_code = '';
        $exshowroomprice = "";
        // RTI Return To invoice integration start from here 
        $ReturnToInvoiceDetails = [];
        $IsReturnToInvoice = false;
        $RTITrue = "false";
        $enableRTI = (config('IC.RELIANCE.V1.CAR.IS_RETURN_TO_INVOICE') == "Y") ? true : false;
        if ($enableRTI) {
            $IsReturnToInvoice = true;
            if ($IsReturnToInvoice && $enableRTI) {
                $ReturnToInvoiceDetails = [
                    'AddonSumInsuredFlatRates' => [
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
                        'occupationRate'                    => 1.0,
                        'policyIssuanceMethodRate'          => 1.4,
                        'existingRGICustomerRate'           => 1.4,
                        'addonLastYearYesRate'              => 1.4,
                        'addonLastYearNoRate'               => 1.26,
                    ]
                ];
            }
            $RTITrue = "true";
        }
        $premium_req_array = [
            'ClientDetails'            => [
                'ClientType' => ($requestData->vehicle_owner_type == 'I') ? '0' : '1',
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
                'VehicleMakeID'         => $mmv_data->make_id_pk,
                'VehicleModelID'        => $mmv_data->model_id_pk,
                'StateOfRegistrationID' => $rto_data->state_id_fk,
                'RTOLocationID'         => $rto_data->model_region_id_pk,
                'ExShowroomPrice'       => '0',
                'DateOfPurchase'        => $DateOfPurchase,
                'ManufactureMonth'      => $vehicle_register_date[1],
                'ManufactureYear'       => $vehicle_register_date[2],
                'Rto_RegionCode'       => $rto_data->region_code,
                'VehicleVariant'        => $mmv_data->variance,
                'IsHavingValidDrivingLicense' => '',
                'IsOptedStandaloneCPAPolicy'  => '',
                'IDV'                   => ($tp_only == 'true') ? 0 : '',
                'ExShowroomPrice'       => ''
            ],
            'Vehicle'                  => [
                'TypeOfFuel'          => $type_of_fuel,
                'ISNewVehicle'        => $ISNewVehicle,
                'Registration_Number' => changeRegNumberFormat($Registration_Number),
                'IsBHVehicle' => isBhSeries($Registration_Number) ? 'true' : 'false',
                'Registration_date'   => $DateOfPurchase,
                'MiscTypeOfVehicleID' => '',
            ],
            'Cover'                    => [
                'IsPAToUnnamedPassengerCovered' => $IsPAToUnnamedPassengerCovered,
                'IsVoluntaryDeductableOpted'    => $IsVoluntaryDeductableOpted,
                'IsGeographicalAreaExtended' => $IsGeographicalAreaExtended,
                'IsReturntoInvoice'             => "$RTITrue",
                'IsElectricalItemFitted'        => $IsElectricalItemFitted,
                'ElectricalItemsTotalSI'        => $ElectricalItemsTotalSI,
                'IsPAToOwnerDriverCoverd'       => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                'IsLiabilityToPaidDriverCovered' => $IsLiabilityToPaidDriverCovered,
                'IsTPPDCover'                   => $TPPDCover,
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
                'IsBasicODCoverage'             => ($tp_only == 'true') ? 'false' : 'true',
                'IsBasicLiability'              => ($tp_only == 'true') ? 'true' : 'false',
                'IsNonElectricalItemFitted'     => $IsNonElectricalItemFitted,
                'NonElectricalItemsTotalSI'     => $NonElectricalItemsTotalSI,
                'IsPAToDriverCovered'           => $IsPAToDriverCovered,
                'IsBiFuelKit'                   => $is_bifuel_kit,
                'BiFuelKitSi'                   => $BiFuelKitSi,
                'SecurePlus'                    => 'true',
                'SecurePremium'                 => 'true',
                'PACoverToOwner'                => [
                    'PACoverToOwner' => [
                        'IsChecked'           => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                        'NoOfItems'           => ($requestData->vehicle_owner_type == 'I') ? '1' : '',
                        'CPAcovertenure'      => ($requestData->vehicle_owner_type == 'I') ? $cpa_tenure : '',
                        'PackageName'         => '',
                        'NomineeName'         => '',
                        'NomineeDOB'          => '',
                        'NomineeRelationship' => '',
                        'NomineeAddress'      => '',
                        'AppointeeName'       => '',
                        'OtherRelation'       => '',
                    ],
                ],
                'PAToUnNamedPassenger'          => [
                    'PAToUnNamedPassenger' => [
                        'IsChecked'  => $PAToUnNamedPassenger_IsChecked,
                        'NoOfItems'  => $PAToUnNamedPassenger_NoOfItems,
                        'SumInsured' => $PAToUnNamedPassengerSI
                    ],
                ],
                'PAToPaidDriver'                => [
                    'PAToPaidDriver' => [
                        'IsChecked'  => $PAToPaidDriver_IsChecked,
                        'NoOfItems'  => $PAToPaidDriver_NoOfItems,
                        'SumInsured' => $PAToPaidDriver_SumInsured,
                    ],
                ],
                'LiabilityToPaidDriver'            => [
                    'LiabilityToPaidDriver' => [
                        'IsMandatory' => 'true',
                        'IsChecked' => $LiabilityToPaidDriver_IsChecked,
                        'NoOfItems' => '1',
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                    ],
                ],
                'BifuelKit'                     => [
                    'BifuelKit' => [
                        'IsChecked'            => $is_bifuel_kit == 'true' ? 'true' : 'false',
                        'IsMandatory'          => $is_bifuel_kit == 'true' ? 'true' : 'false',
                        'PolicyCoverDetailsID' => '',
                        'Fueltype'             => $Fueltype,
                        'ISLpgCng'             => $bifuel,
                        'PolicyCoverID'        => '',
                        'SumInsured'           => $BiFuelKitSi,
                        'NoOfItems'            => '',
                        'PackageName'          => '',
                    ],
                ],
                'IsAutomobileAssociationMember' => 'false',
                'IsAntiTheftDeviceFitted'       => $anti_theft,
                'VoluntaryDeductible'             => [
                    'VoluntaryDeductible' => [
                        'SumInsured' => $VoluntaryDeductible
                    ],
                ],
                'GeographicalExtension'             => [
                    'GeographicalExtension' => [
                        'Countries' => $Countries,
                    ],
                ],
                'ReturntoInvoiceCoverage'  => $ReturnToInvoiceDetails,
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
        ];

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

        if ($requestData->business_type == 'newbusiness') {
            unset($premium_req_array['PreviousInsuranceDetails']);
        }

        $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
        if ($is_renewbuy) {
            $premium_req_array['Policy']['POSType'] = '';
            $premium_req_array['Policy']['POSAadhaarNumber'] = '';
            $premium_req_array['Policy']['POSPANNumber'] = '';
        }
        // Integration for Pay as you Drive
        if ($BusinessType == "1") {
            $odometerreading = 100;
            $CoverUptoKm = 100;
        } else {
            $odometerreading = 1000;
            $CoverUptoKm = 1000;
        }

        // MADE SOME CHANGES HERE FOR COVERAGE DETAILS
        if ($productData->good_driver_discount == 'Yes' && $isGddEnabled) {
            $premium_req_array['Cover']['IsPayAsYouDrive'] = "";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['isCoverEligible'] = "";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['CurrentVehicalOdoMtrReading'] = "$odometerreading";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['rate'] = ""; //should be in decimal
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['minKMRange'] = ""; //should be in decimal
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['maxKMRange'] = ""; //should be in decimal
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['PlanDescription'] = "";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['CoverUptoKm'] = ""; //should be in decimal
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['CoverUptoKm'] = ""; //should be in decimal
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['IsChecked'] = "";
            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb']['isOptedByCustomer'] = "";
            // hard coded request for coverage details
            $premium_req_array['clientDetails']['ClientType'] = '0';
            $premium_req_array['clientDetails']['IsExistingRGICustomer'] = '';
            $premium_req_array['Policy']['BusinessType'] = $BusinessType;
            $premium_req_array['Policy']['Cover_From'] = $policy_start_date;
            $premium_req_array['Policy']['Cover_To'] = $policy_end_date;
            $premium_req_array['Policy']['Branch_Name'] = 'Direct';
            $premium_req_array['Policy']['Branch_Code'] = '9202';
            $premium_req_array['Policy']['AgentName'] = 'Direct';
            $premium_req_array['Policy']['AgentCode'] = 'Direct';
            $premium_req_array['Policy']['productcode'] = $productCode;
            $premium_req_array['Policy']['OtherSystemName'] = '1';
            $premium_req_array['Policy']['isMotorQuote'] = ($tp_only == 'true') ? 'false' : 'true';
            $premium_req_array['Policy']['isMotorQuoteFlow'] = ($tp_only == 'true') ? 'false' : 'true';
            $premium_req_array['Policy']['POSType'] = $POSType;
            $premium_req_array['Policy']['POSAadhaarNumber'] = $POSAadhaarNumber;
            $premium_req_array['Policy']['POSPANNumber'] = $POSPANNumber;
            $premium_req_array['Policy']['PolicyTenure'] = '3';
            $premium_req_array['Risk']['VehicleMakeID'] = $mmv_data->make_id_pk;
            $premium_req_array['Risk']['VehicleModelID'] = $mmv_data->model_id_pk;
            $premium_req_array['Risk']['RTOLocationID'] = $rto_data->model_region_id_pk;
            $premium_req_array['Risk']['ExShowroomPrice'] = '0';
            $premium_req_array['Risk']['DateOfPurchase'] = $DateOfPurchase;
            $premium_req_array['Risk']['ManufactureMonth'] = $vehicle_register_date[1];
            $premium_req_array['Risk']['ManufactureYear'] = $vehicle_register_date[2];
            $premium_req_array['Risk']['IsVehicleHypothicated'] = 'true';
            $premium_req_array['Risk']['VehicleVariant'] = '';
            $premium_req_array['Risk']['StateOfRegistrationID'] = $rto_data->state_id_fk;
            $premium_req_array['Vehicle']['Registration_date'] = $DateOfPurchase;
            $premium_req_array['Vehicle']['TypeOfFuel'] = $type_of_fuel;
            $premium_req_array['Vehicle']['MiscTypeOfVehicleID'] = '';
            $premium_req_array['Vehicle']['ISNewVehicle'] =  $ISNewVehicle;
            $premium_req_array['PreviousInsuranceDetails']['PrevInsuranceID'] = '';
            $premium_req_array['PreviousInsuranceDetails']['IsVehicleOfPreviousPolicySold'] = 'false';
            $premium_req_array['PreviousInsuranceDetails']['IsNCBApplicable'] = $IsNCBApplicable;
            $premium_req_array['PreviousInsuranceDetails']['PrevYearInsurer'] = '10';
            // $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyNo'] = '920222023090004074';
            $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyType'] = '1';
            $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyStartDate'] = date('Y-m-d', strtotime('-1 year +1 day', strtotime($previous_policy_expiry_date)));
            $premium_req_array['PreviousInsuranceDetails']['PrevYearPolicyEndDate'] = date('Y-m-d', strtotime($previous_policy_expiry_date));
            $premium_req_array['PreviousInsuranceDetails']['PrevYearNCB'] = $requestData->previous_ncb;
            $premium_req_array['PreviousInsuranceDetails']['IsInspectionDone'] = 'false';
            $premium_req_array['PreviousInsuranceDetails']['InspectionDate'] = '';
            $premium_req_array['PreviousInsuranceDetails']['Inspectionby'] = '';
            $premium_req_array['PreviousInsuranceDetails']['InspectorName'] = '';
            $premium_req_array['PreviousInsuranceDetails']['IsNCBEarnedAbroad'] = 'false';
            $premium_req_array['PreviousInsuranceDetails']['ODLoading'] = '';
            $premium_req_array['PreviousInsuranceDetails']['IsClaimedLastYear'] = $IsClaimedLastYear;
            $premium_req_array['PreviousInsuranceDetails']['ODLoadingReason'] = '';
            $premium_req_array['PreviousInsuranceDetails']['PreRateCharged'] = '';
            $premium_req_array['PreviousInsuranceDetails']['PreSpecialTermsAndConditions'] = '';
            $premium_req_array['PreviousInsuranceDetails']['IsTrailerNCB'] = 'false';
            $premium_req_array['PreviousInsuranceDetails']['InspectionID'] = '';
            $premium_req_array['NCBEligibility']['NCBEligibilityCriteria'] = $NCBEligibilityCriteria;
            $premium_req_array['NCBEligibility']['NCBReservingLetter'] = '';
            $premium_req_array['NCBEligibility']['PreviousNCB'] = '0';
            $premium_req_array['ProductCode'] = '2309';
            $premium_req_array['LstCoveragePremium'] = '';
        }
        $coverage_res_data = getWsData(
            config('IC.RELIANCE.V1.CAR.END_POINT_URL_COVERAGE'),
            $premium_req_array,
            'reliance',
            [
                'root_tag'              => 'PolicyDetails',
                'section'               => $productData->product_sub_type_code,
                'method'                => 'Coverage Calculation',
                'requestMethod'         => 'post',
                'enquiryId'             => $enquiryId,
                'productName'           => $productData->product_name . " ($business_type)",
                'transaction_type'      => 'quote',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY')
                ]
            ]
        );
        $coverage_res_data_response = $coverage_res_data;
        if ($coverage_res_data['response']) {
            $coverage_res_data = json_decode($coverage_res_data['response']);
            if (!isset($coverage_res_data->ErrorMessages)) {
                update_quote_web_servicerequestresponse($coverage_res_data_response['table'], $coverage_res_data_response['webservice_id'], "Coverage Calculation Success..!", "Success");
                $nil_dep_rate = '';
                $secure_plus_rate = '';
                $secure_premium_rate = '';
                if (isset($coverage_res_data->LstAddonCovers)) {
                    foreach ($coverage_res_data->LstAddonCovers as $k => $v) {
                        if ($v->CoverageName == 'Nil Depreciation') {
                            $nil_dep_rate = $v->rate;
                        } elseif ($v->CoverageName == 'Secure Plus') {
                            $secure_plus_rate = $v->rate;
                        } elseif ($v->CoverageName == 'Secure Premium') {
                            $secure_premium_rate = $v->rate;
                        }
                        if ($v->CoverageName == 'Pay As You Drive' and $productData->good_driver_discount == 'Yes' && $isGddEnabled) {
                            if (!empty($gdd_product_identifier)) {
                                foreach ($v->PayAsYouDrive as $key => $value) {
                                    if ($value->maxKMRange == $gdd_product_identifier) {
                                        $selectedPayud = $key;
                                        $obj_PayAsYouDrive = $v->PayAsYouDrive;
                                        break;
                                    } else {
                                        $selectedPayud = 0;
                                        $obj_PayAsYouDrive = $v->PayAsYouDrive;
                                    }
                                }
                            } else {
                                $selectedPayud = 0;
                                $obj_PayAsYouDrive = $v->PayAsYouDrive;
                            }
                            $minKmRange = $v->PayAsYouDrive[$selectedPayud]->minKMRange;
                            $maxKMRange = $v->PayAsYouDrive[$selectedPayud]->maxKMRange;
                            $PlanDescription = $v->PayAsYouDrive[$selectedPayud]->PlanDescription;
                            $rate = $v->PayAsYouDrive[$selectedPayud]->rate;
                            $CoverUptoKm = "1000.0";
                            $premium_req_array['Cover']['IsPayAsYouDrive'] = "true";
                            $premium_req_array['Cover']['PayAsYouDriveCoverage']['PayAsYouDriveweb'] = [
                                'isCoverEligible' => "true",
                                'CurrentVehicalOdoMtrReading' => "$odometerreading",
                                'rate' => $rate, //should be in decimal
                                'minKMRange' => $minKmRange, //should be in decimal
                                'maxKMRange' => $maxKMRange, //should be in decimal
                                'PlanDescription' => $PlanDescription,
                                'CoverUptoKm' => $CoverUptoKm, //should be in decimal
                                'IsChecked' => "true",
                                'isOptedByCustomer' => "false",
                            ];
                        }
                        // taking the coverage response for return to invoice and passing to premium calculation to get final RTI premium
                        if ($v->CoverageName == 'Return to Invoice') {
                            if (!empty($v->ReturntoInvoice[0])) {

                                $premium_req_array['Cover']['ReturntoInvoiceCoverage']['AddonSumInsuredFlatRates'] = [
                                    'IsChecked' => true,
                                    'isOptedByCustomer' => true,
                                    'isOptedByCustomerRate' => 'addonOptedYesRate',
                                    'addonOptedYesRate' => $v->ReturntoInvoice[0]->addonOptedYesRate,
                                    'addonOptedNoRate' => $v->ReturntoInvoice[0]->addonOptedNoRate,
                                    'addonYesMultiplicationFactorRate' => $v->ReturntoInvoice[0]->RelativityFactor->addonYesMultiplicationFactorRate,
                                    'addonNoMultiplicationFactorRate' => $v->ReturntoInvoice[0]->RelativityFactor->addonNoMultiplicationFactorRate,
                                    'ageofVehicleRate' => $v->ReturntoInvoice[0]->RelativityFactor->ageofVehicleRate,
                                    'vehicleCCRate' => $v->ReturntoInvoice[0]->RelativityFactor->vehicleCCRate,
                                    'zoneRate' => $v->ReturntoInvoice[0]->RelativityFactor->zoneRate,
                                    'parkingRate' => $v->ReturntoInvoice[0]->RelativityFactor->parkingRate,
                                    'drivingAgeRate' => $v->ReturntoInvoice[0]->RelativityFactor->driverAgeRate,
                                    'ncbApplicableRate' => $v->ReturntoInvoice[0]->RelativityFactor->ncbApplicabilityRate,
                                    'noOfVehicleUserRate' => $v->ReturntoInvoice[0]->RelativityFactor->noOfVehicleUserRate,
                                    'occupationRate' => $v->ReturntoInvoice[0]->RelativityFactor->occupationRate,
                                    'policyIssuanceMethodRate' => $v->ReturntoInvoice[0]->RelativityFactor->policyIssuanceMethodRate,
                                    'existingRGICustomerRate' => $v->ReturntoInvoice[0]->RelativityFactor->existingRGICustomerRate,
                                    'addonLastYearYesRate' => $v->ReturntoInvoice[0]->RelativityFactor->addonLastYearYesRate,
                                    'addonLastYearNoRate' => $v->ReturntoInvoice[0]->RelativityFactor->addonLastYearNoRate,
                                ];
                            } else {
                                unset($premium_req_array['Cover']['ReturntoInvoiceCoverage']);
                                unset($premium_req_array['Cover']['IsReturntoInvoice']);
                            }
                        }
                    }

                    if ($masterProduct->product_identifier == 'zero_dep') {
                        unset($premium_req_array['Cover']['SecurePlus']);
                        unset($premium_req_array['Cover']['SecurePremium']);
                        $premium_req_array['Cover']['IsNilDepreciation'] = $IsNilDepreciation;
                        $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                    } elseif ($masterProduct->product_identifier == 'secure_plus') {
                        $premium_req_array['Cover']['IsSecurePlus'] = 'true';
                        $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = 'true';
                        unset($premium_req_array['Cover']['SecurePremium']);
                        $premium_req_array['Cover']['SecurePlus'] = [
                            'SecurePlus' => [
                                'IsChecked' => 'true',
                                'ApplicableRate' => $secure_plus_rate,
                            ],
                        ];
                    } elseif ($masterProduct->product_identifier == 'secure_premium') {
                        $premium_req_array['Cover']['IsSecurePremium'] = 'true';
                        $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = 'true';
                        unset($premium_req_array['Cover']['SecurePlus']);
                        $premium_req_array['Cover']['SecurePremium'] = [
                            'SecurePremium' => [
                                'IsChecked' => 'true',
                                'ApplicableRate' => $secure_premium_rate,
                            ],
                        ];
                    } elseif ($premium_type == 'third_party') {
                        $premium_req_array['Cover']['IsSecurePremium'] = '';
                        $premium_req_array['Cover']['IsNilDepApplyingFirstTime'] = '';
                        unset($premium_req_array['Cover']['SecurePlus']);
                        unset($premium_req_array['Cover']['SecurePremium']);
                    }

                    $premium_res_data = getWsData(
                        config('IC.RELIANCE.V1.CAR.END_POINT_URL_PREMIUM'),
                        $premium_req_array,
                        'reliance',
                        [
                            'root_tag'      => 'PolicyDetails',
                            'section'       => $productData->product_sub_type_code,
                            'method'        => 'Premium Calculation',
                            'requestMethod' => 'post',
                            'enquiryId'     => $enquiryId,
                            'productName'   => $productData->product_name . " ($business_type)",
                            'transaction_type'    => 'quote',
                            'headers' => [
                                'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY')
                            ]
                        ]
                    );

                    if ($premium_res_data['response']) {
                        $response = json_decode($premium_res_data['response'])->MotorPolicy ?? '';
                        $skip_second_call = false;
                        if (empty($response)) {
                            return [
                                'webservice_id' => $premium_res_data['webservice_id'],
                                'table' => $premium_res_data['table'],
                                'premium_amount' => 0,
                                'status'         => false,
                                'message'        => 'Insurer not reachable',
                            ];
                        }

                        // unset($premium_res_data);
                        if (trim($response->ErrorMessages) == '') {
                            update_quote_web_servicerequestresponse($premium_res_data['table'], $premium_res_data['webservice_id'], "Premium Calculation Success..!", "Success");
                            $min_idv = (int)$response->MinIDV;
                            $max_idv = (int)$response->MaxIDV;
                            if ($tp_only == 'false') {
                                if ($requestData->is_idv_changed == 'Y') {
                                    if ($response->MaxIDV != "" && $requestData->edit_idv >= floor($response->MaxIDV)) {
                                        $premium_req_array['Risk']['IDV'] = floor($response->MaxIDV);
                                    } elseif ($response->MinIDV != "" && $requestData->edit_idv <= ceil($response->MinIDV)) {
                                        $premium_req_array['Risk']['IDV'] = ceil($response->MinIDV);
                                    } else {
                                        $premium_req_array['Risk']['IDV'] = $requestData->edit_idv;
                                    }
                                } else {
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

                                $FIFTYLAKH_IDV_RESTRICTION_APPLICABLE = config('constants.motorConstant.FIFTYLAKH_IDV_RESTRICTION_APPLICABLE');
                                if ($is_renewbuy || $FIFTYLAKH_IDV_RESTRICTION_APPLICABLE == 'Y') {
                                    if ($premium_req_array['Risk']['IDV'] > 5000000) {
                                        $premium_req_array['Policy']['POSType'] = '';
                                        $premium_req_array['Policy']['POSAadhaarNumber'] = '';
                                        $premium_req_array['Policy']['POSPANNumber'] = '';
                                    } else {
                                        $premium_req_array['Policy']['POSType'] = $POSType;
                                        $premium_req_array['Policy']['POSAadhaarNumber'] = $POSAadhaarNumber;
                                        $premium_req_array['Policy']['POSPANNumber'] = $POSPANNumber;
                                    }
                                }

                                if (!$skip_second_call) {
                                    $get_response = getWsData(
                                        config('IC.RELIANCE.V1.CAR.END_POINT_URL_PREMIUM'),
                                        $premium_req_array,
                                        'reliance',
                                        [
                                            'root_tag'      => 'PolicyDetails',
                                            'section'       => $productData->product_sub_type_code,
                                            'method'        => 'Premium Re Calculation',
                                            'requestMethod' => 'post',
                                            'enquiryId'     => $enquiryId,
                                            'productName'   => $productData->product_name . " ($business_type)",
                                            'transaction_type'    => 'quote',
                                            'headers' => [
                                                'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CAR.OCP_APIM_SUBSCRIPTION_KEY')
                                            ]
                                        ]
                                    );

                                    if ($get_response['response']) {
                                        $response = json_decode($get_response['response'])->MotorPolicy;
                                        if (isset($response->ErrorMessages) && $response->ErrorMessages !== '') {
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
                            }
                            $basic_od = 0;
                            $odDiscount = 0;
                            $tppd = 0;
                            $ic_vehicle_discount = 0;
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
                            $other_addon_amount = 0;
                            $liabilities = 0;
                            $voluntary_excess = 0;
                            $tppd_discount = 0;
                            $geog_Extension_OD_Premium = 0;
                            $geog_Extension_TP_Premium = 0;
                            $gdd_discount = 0;
                            $enable_gdd = "N";
                            $RTIAddonPremium = 0;
                            $inspection_charges = !empty((int) $response->InspectionCharges) ? (int) $response->InspectionCharges : 0;

                            $idv = $response->IDV;
                            // dd($response);
                            $response->lstPricingResponse = is_object($response->lstPricingResponse) ? [$response->lstPricingResponse] : $response->lstPricingResponse;
                            foreach ($response->lstPricingResponse as $k => $v) {
                                $value = (float)(trim(str_replace('-', '', $v->Premium)));
                                if ($v->CoverageName == 'Basic OD') {
                                    $basic_od = $value + $inspection_charges;
                                } elseif (($v->CoverageName == 'Nil Depreciation')) {
                                    $zero_dep_amount = round($value);
                                } elseif (($v->CoverageName == 'Secure Plus') || ($v->CoverageName == 'Secure Premium')) {
                                    $other_addon_amount = round($value);
                                } elseif ($v->CoverageName == 'Bifuel Kit') {
                                    $lpg_cng = $value;
                                } elseif ($v->CoverageName == 'Electrical Accessories') {
                                    $electrical_accessories = $value;
                                } elseif ($v->CoverageName == 'Non Electrical Accessories') {
                                    $non_electrical_accessories = $value;
                                } elseif ($v->CoverageName == 'NCB') {
                                    $ncb_discount = $value;
                                } elseif ($v->CoverageName == 'Basic Liability') {
                                    $tppd = $value;
                                } elseif ($v->CoverageName == 'PA to Unnamed Passenger') {
                                    $pa_unnamed = $value;
                                } elseif ($v->CoverageName == 'PA to Owner Driver') {
                                    $pa_owner = $value;
                                } elseif ($v->CoverageName == 'PA to Paid Driver') {
                                    $pa_paid_driver = $value;
                                } elseif ($v->CoverageName == 'Pay As You Drive' && $isGddEnabled && $productData->good_driver_discount == "Yes") {
                                    // $pa_paid_driver = $value;
                                    $isOptedFalse = array_map(function ($item) {
                                        $item->isOptedByCustomer = false; // Set it to boolean false
                                        return $item;
                                    }, $obj_PayAsYouDrive);

                                    if (!empty($selectedPayud)) {
                                        $obj_PayAsYouDrive[$selectedPayud]->Premium = $value;
                                        $obj_PayAsYouDrive[$selectedPayud]->isOptedByCustomer = true;
                                    } elseif (empty($selectedPayud)) {
                                        // this is for bussniess type nuwbussiness $BusinessType == '1'
                                        $obj_PayAsYouDrive[$selectedPayud]->Premium = $value;
                                        $obj_PayAsYouDrive[$selectedPayud]->isOptedByCustomer = true;
                                        $obj_PayAsYouDrive[$selectedPayud]->selectedOptionNotApplicable = true;
                                    }
                                    $gdd_discount = $value;
                                    $enable_gdd = "Y";
                                } elseif ($v->CoverageName == 'Return to Invoice' && $enableRTI) {
                                    $RTIAddonPremium = $value;
                                } elseif ($v->CoverageName == 'Liability to Paid Driver') {
                                    $liabilities = $value;
                                } elseif ($v->CoverageName == 'Bifuel Kit TP') {
                                    $lpg_cng_tp = $value;
                                } elseif ($v->CoverageName == 'Automobile Association Membership') {
                                    $automobile_association = round(abs($value));
                                } elseif ($v->CoverageName == 'Anti-Theft Device') {
                                    $anti_theft = round(abs($value));
                                } elseif ($v->CoverageName == 'Voluntary Deductible') {
                                    $voluntary_excess = round(abs($value));
                                } elseif ($v->CoverageName == 'TPPD') {
                                    $tppd_discount = round(abs($value));
                                } elseif (in_array($v->CoverageName, ['Geographical Extension', 'Geo Extension']) && $v->CoverID == 5) {
                                    $geog_Extension_OD_Premium = round(abs($value));
                                } elseif ($v->CoverageName == 'Geographical Extension'  && in_array($v->CoverID, [6, 403])) { #$v->CoverID == 6
                                    $geog_Extension_TP_Premium = round(abs($value));
                                } elseif ($v->CoverageName == 'OD Discount') {
                                    $odDiscount = $value;
                                }

                                unset($value);
                            }

                            $discountPercentage =  null;
                            $ribbonMessage = null;
                            if (isset($premium_req_array['Vehicle']['ODDiscount']) && $odDiscount > 0) {
                                $totalOD = round($odDiscount + $basic_od);
                                // $discountPercentage = round(($odDiscount/$totalOD)*100);
                                $basic_od = $totalOD;

                                // if ($discountPercentage != $premium_req_array['Vehicle']['ODDiscount']) {
                                //     $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount').' '.$discountPercentage.'%';
                                // }
                            } else {
                                $odDiscount = 0;
                            }

                            $add_ons_data = [];
                            $add_ons_data['in_built'] = [];
                            $add_ons_data['additional'] = [];
                            $add_ons_data['other'] = [];

                            if ($tp_only == 'false') {
                                switch (strtolower($masterProduct->product_identifier)) {
                                    case 'zero_dep':
                                        if (!($zero_dep_amount > 0)) {
                                            return [
                                                'webservice_id' => $premium_res_data['webservice_id'],
                                                'table' => $premium_res_data['table'],
                                                'status'         => false,
                                                'message'        => 'Zero Depreciation / Nil Depreciation amount not received in service response',
                                                'request'       => [
                                                    'zero_dep_amount' => $zero_dep_amount,
                                                ],
                                            ];
                                        }
                                        if ($requestData->policy_type == 'newbusiness') {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                ],
                                                'additional' => [
                                                    'zeroDepreciation' => $zero_dep_amount,
                                                    'engine_protector' => 0,
                                                    'ncb_protection' => 0,
                                                    'keyReplace' => 0,
                                                    'consumables' => 0,
                                                    'tyre_secure' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                    'lopb' => 0,
                                                ],
                                                'other' => []
                                            ];
                                        } else {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                ],
                                                'additional' => [
                                                    'zeroDepreciation' => $zero_dep_amount,
                                                    'engine_protector' => 0,
                                                    'ncb_protection' => 0,
                                                    'keyReplace' => 0,
                                                    'consumables' => 0,
                                                    'tyre_secure' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                    'lopb' => 0,
                                                ],
                                                'other' => []
                                            ];
                                        }
                                        break;
                                    case 'secure_plus':
                                        if ($requestData->policy_type == 'newbusiness') {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                    'zeroDepreciation' => $other_addon_amount, // included
                                                    'engine_protector' => 0, // included
                                                    'consumables' => 0, // included
                                                    'keyReplace' => 0,
                                                    'lopb' => 0,
                                                ],
                                                'additional' => [
                                                    'ncb_protection' => 0,
                                                    'tyre_secure' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                ],
                                                'other' => []
                                            ];
                                        } else {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                    'zeroDepreciation' => $other_addon_amount, //'Included',
                                                    'engine_protector' => 0, // 'Included',
                                                    'consumables' => 0, // 'Included',
                                                    'keyReplace' => 0,
                                                    'lopb' => 0,
                                                ],
                                                'additional' => [
                                                    'ncb_protection' => 0,
                                                    'tyre_secure' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                ],
                                                'other' => []
                                            ];
                                        }
                                        break;
                                    case 'secure_premium':

                                        if ($requestData->policy_type == 'newbusiness') {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                    'zeroDepreciation' => $other_addon_amount, //'Included',
                                                    'engine_protector' => 0, //'Included',
                                                    'consumables' => 0, //'Included',
                                                    'keyReplace' => 0, // 'Included',
                                                    'tyre_secure' => 0,
                                                    'lopb' => 0,
                                                ],
                                                'additional' => [
                                                    'ncb_protection' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                ],
                                                'other' => []
                                            ];
                                        } else {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                    'zeroDepreciation' => $other_addon_amount, //'Included',
                                                    'engine_protector' => 0, // 'Included',
                                                    'consumables' => 0, // 'Included',
                                                    'keyReplace' => 0, //'Included',
                                                    'tyre_secure' => 0,
                                                    'lopb' => 0,
                                                ],
                                                'additional' => [
                                                    'ncb_protection' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                ],
                                                'other' => []
                                            ];
                                        }
                                        break;
                                    default:

                                        if ($requestData->policy_type == 'newbusiness') {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                ],
                                                'additional' => [
                                                    'zeroDepreciation' => 0,
                                                    'engine_protector' => 0,
                                                    'ncb_protection' => 0,
                                                    'keyReplace' => 0,
                                                    'consumables' => 0,
                                                    'tyre_secure' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                ],
                                                'other' => []
                                            ];
                                        } else {
                                            $add_ons_data = [
                                                'in_built' => [
                                                    //'roadSideAssistance' => 0,
                                                ],
                                                'additional' => [
                                                    'zeroDepreciation' => 0,
                                                    'engine_protector' => 0,
                                                    'ncb_protection' => 0,
                                                    'keyReplace' => 0,
                                                    'consumables' => 0,
                                                    'tyre_secure' => 0,
                                                    'return_to_invoice' => $RTIAddonPremium,
                                                    'lopb' => 0,
                                                ],
                                                'other' => []
                                            ];
                                        }
                                        break;
                                }
                            }

                            $add_ons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
                            $add_ons_data['additional_premium'] = array_sum($add_ons_data['additional']);
                            $add_ons_data['other_premium'] = array_sum($add_ons_data['other']);

                            $applicable_addons = [
                                'zeroDepreciation',
                                'keyReplace',
                                'engineProtector',
                                'consumables',
                                'tyreSecure',
                                'lopb'
                            ];

                            // if ($car_age > 5) {
                            //     array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                            //     array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                            //     array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                            //     array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                            //     array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                            //     array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                            // }
                            if ($productData->zero_dep == '0' && ($add_ons_data['additional']['zeroDepreciation'] ?? 0) > 0) {
                                $add_ons_data['in_built']['zeroDepreciation'] = $add_ons_data['additional']['zeroDepreciation'];
                                unset($add_ons_data['additional']['zeroDepreciation']);
                            }

                            $other_discount = 0;
                            $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng + $geog_Extension_OD_Premium;
                            $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $geog_Extension_TP_Premium;
                            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount + $odDiscount;
                            $final_total_discount = $final_total_discount + $gdd_discount;

                            $final_net_premium     = $final_od_premium + $final_tp_premium - $final_total_discount;
                            $final_gst_amount      = $final_net_premium * 0.18;
                            $final_payable_amount  = $final_net_premium + $final_gst_amount;
                            $data_response = [
                                'webservice_id' => $premium_res_data['webservice_id'],
                                'table' => $premium_res_data['table'],
                                'status' => true,
                                'msg' => 'Found',
                                'Data' => [
                                    'idv'                       => round($idv),
                                    'min_idv'                   => $min_idv != "" ? round($min_idv) : 0,
                                    'max_idv'                   => $max_idv != "" ? round($max_idv) : 0,
                                    'vehicle_idv'               => round($idv),
                                    'qdata'                     => NULL,
                                    'pp_enddate'                => $requestData->previous_policy_expiry_date,
                                    'addonCover'                => NULL,
                                    'addon_cover_data_get'      => '',
                                    'rto_decline'               => NULL,
                                    'rto_decline_number'        => NULL,
                                    'mmv_decline'               => NULL,
                                    'mmv_decline_name'          => NULL,
                                    'policy_type'               => $policy_type,
                                    'cover_type'                => '1YC',
                                    'hypothecation'             => '',
                                    'hypothecation_name'        => '',
                                    'vehicle_registration_no'   => $requestData->rto_code,
                                    'rto_no'                    => $rto_code,

                                    'version_id'                => $requestData->version_id,
                                    'selected_addon'            => [],
                                    'showroom_price'            => 0,
                                    'fuel_type'                 => $requestData->fuel_type,
                                    'ncb_discount'              => $requestData->applicable_ncb,
                                    'company_name'              => $productData->company_name,
                                    'company_logo'              => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                                    'product_name'              => $productData->product_name,
                                    'mmv_detail'                => $mmv_data,
                                    'master_policy_id' => [
                                        'policy_id'             => $productData->policy_id,
                                        'policy_no'             => $productData->policy_no,
                                        'policy_start_date'     => $policy_start_date,
                                        'policy_end_date'       => $policy_end_date,
                                        'sum_insured'           => $productData->sum_insured,
                                        'corp_client_id'        => $productData->corp_client_id,
                                        'product_sub_type_id'   => $productData->product_sub_type_id,
                                        'insurance_company_id'  => $productData->company_id,
                                        'status'                => $productData->status,
                                        'corp_name'             => '',
                                        'company_name'          => $productData->company_name,
                                        'logo'                  => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                                        'product_sub_type_name' => $productData->product_sub_type_name,
                                        'flat_discount'         => $productData->default_discount,
                                        'predefine_series'      => '',
                                        'is_premium_online'     => $productData->is_premium_online,
                                        'is_proposal_online'    => $productData->is_proposal_online,
                                        'is_payment_online'     => $productData->is_payment_online
                                    ],
                                    'motor_manf_date'           => $requestData->vehicle_register_date,
                                    'vehicle_register_date'     => $requestData->vehicle_register_date,
                                    'vehicleDiscountValues'     => [
                                        'master_policy_id'      => $productData->policy_id,
                                        'product_sub_type_id'   => $productData->product_sub_type_id,
                                        'segment_id'            => 0,
                                        'rto_cluster_id'        => 0,
                                        'car_age'               => $car_age,
                                        'aai_discount'          => 0,
                                        'ic_vehicle_discount'   => $ic_vehicle_discount + $odDiscount //round($insurer_discount)
                                    ],
                                    'ic_vehicle_discount'       => $ic_vehicle_discount + $odDiscount,
                                    'ribbon' => $ribbonMessage,
                                    'basic_premium'                             => $basic_od,
                                    'motor_electric_accessories_value'          => $electrical_accessories,
                                    'motor_non_electric_accessories_value'      => $non_electrical_accessories,
                                    'total_accessories_amount(net_od_premium)'  => $electrical_accessories + $non_electrical_accessories + $lpg_cng,
                                    'total_own_damage'                          => $final_od_premium,

                                    'tppd_premium_amount'                       => $tppd,
                                    'tppd_discount'                             => $tppd_discount,
                                    'compulsory_pa_own_driver'                  => $pa_owner,  // Not added in Total TP Premium
                                    'cover_unnamed_passenger_value'             => $pa_unnamed,
                                    'default_paid_driver'                       => $liabilities,
                                    'motor_additional_paid_driver'              => $pa_paid_driver,
                                    'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                                    'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,

                                    'seating_capacity'                          => $mmv_data->seating_capacity,

                                    'deduction_of_ncb'                          => $ncb_discount,
                                    'antitheft_discount'                        => $anti_theft,
                                    'aai_discount'                              => $automobile_association,
                                    'voluntary_excess'                          => $voluntary_excess,
                                    'other_discount'                            => $other_discount,
                                    'total_liability_premium'                   => $final_tp_premium,
                                    'net_premium'                               => round($final_net_premium),
                                    'service_tax_amount'                        => round($final_gst_amount),
                                    'service_tax'                               => 18,
                                    'total_discount_od'                         => 0,
                                    'add_on_premium_total'                      => 0,
                                    'addon_premium'                             => 0,
                                    'quotation_no'                              => '',
                                    'premium_amount'                            => $final_payable_amount,

                                    'service_data_responseerr_msg'              => 'success',
                                    'user_id'                                   => $requestData->user_id,
                                    'product_sub_type_id'                       => $productData->product_sub_type_id,
                                    'user_product_journey_id'                   => $requestData->user_product_journey_id,
                                    'business_type'                             => $business_type,
                                    'service_err_code'                          => NULL,
                                    'service_err_msg'                           => NULL,
                                    'policyStartDate'                           => date('d-m-Y', strtotime($policy_start_date)),
                                    'policyEndDate'                             => date('d-m-Y', strtotime($policy_end_date)),
                                    'ic_of'                                     => $productData->company_id,
                                    'vehicle_in_90_days'                        => $vehicle_in_90_days,
                                    'get_policy_expiry_date'                    => NULL,
                                    'get_changed_discount_quoteid'              => 0,
                                    'vehicle_discount_detail' => [
                                        'discount_id'       => NULL,
                                        'discount_rate'     => NULL
                                    ],
                                    'is_premium_online'     => $productData->is_premium_online,
                                    'is_proposal_online'    => $productData->is_proposal_online,
                                    'is_payment_online'     => $productData->is_payment_online,
                                    'policy_id'             => $productData->policy_id,
                                    'insurane_company_id'   => $productData->company_id,
                                    'max_addons_selection'  => NULL,

                                    'add_ons_data' =>   [
                                        'in_built'   => [],
                                        'additional' => [
                                            'engine_protector'  => 0,
                                            'ncb_protection'    => 0,
                                            'keyReplace'        => 0,
                                            'consumables'       => 0,
                                            'tyre_secure'       => 0,
                                            'return_to_invoice' => 0,
                                            'lopb' => 0,
                                        ]
                                    ],
                                    'applicable_addons' => $applicable_addons,
                                    'final_od_premium'      => $final_od_premium,
                                    'final_tp_premium'      => $final_tp_premium,
                                    'final_total_discount'  => $final_total_discount,
                                    'final_net_premium'     => $final_net_premium,
                                    'final_gst_amount'      => round($final_gst_amount),
                                    'final_payable_amount'  => round($final_payable_amount),
                                    'mmv_detail' => [
                                        'manf_name'             => $mmv_data->make_name,
                                        'model_name'            => $mmv_data->model_name,
                                        'version_name'          => $mmv_data->variance,
                                        'fuel_type'             => $mmv_data->operated_by,
                                        'seating_capacity'      => $mmv_data->seating_capacity,
                                        'carrying_capacity'     => $mmv_data->carrying_capacity,
                                        'cubic_capacity'        => $mmv_data->cc,
                                        'gross_vehicle_weight'  => $mmv_data->gross_weight ?? 1,
                                        'vehicle_type'          => $mmv_data->veh_type_name,
                                    ],
                                    'company_alias' => $request->company_alias,
                                ]
                            ];
                            // adding the gdd=y for pay as you drive in $data_response 
                            if ($enable_gdd == 'Y' && $productData->good_driver_discount == "Yes" && $isGddEnabled) {
                                $data_response['Data']['gdd'] = "Y";
                                $data_response['Data']['PayAsYouDrive'] = $obj_PayAsYouDrive;
                            }

                            if ($tp_only != 'true') {
                                $data_response['Data']['add_ons_data'] = $add_ons_data;
                            }
                            if ($is_bifuel_kit == 'true') {
                                $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng;
                                $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                                $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                            }

                            //  if(strtolower($masterProduct->product_identifier == 'secure_premium' || $masterProduct->product_identifier == 'secure_plus' || $masterProduct->product_identifier == 'zero_dep')) {
                            //     $data_response['Data']['add_ons_data'] = $add_ons_data;
                            // }
                            return camelCase($data_response);
                        } else {
                            return [
                                'webservice_id' => $premium_res_data['webservice_id'],
                                'table' => $premium_res_data['table'],
                                'premium_amount' => 0,
                                'status'         => false,
                                'message'        => $response->ErrorMessages
                            ];
                        }
                    } else {
                        return [
                            'webservice_id' => $premium_res_data['webservice_id'],
                            'table' => $premium_res_data['table'],
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'Insurer not reachable',
                        ];
                    }
                    die;
                } else {
                    return [
                        'webservice_id' => $coverage_res_data_response['webservice_id'],
                        'table' => $coverage_res_data_response['table'],
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => 'Insurer not reachable',
                    ];
                }
            } else {
                return [
                    'webservice_id' => $coverage_res_data_response['webservice_id'],
                    'table' => $coverage_res_data_response['table'],
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => $coverage_res_data->ErrorMessages
                ];
            }
        } else {
            return [
                'webservice_id' => $coverage_res_data_response['webservice_id'],
                'table' => $coverage_res_data_response['table'],
                'premium_amount' => 0,
                'status'         => false,
                'message'        => 'Insurer Not Reachable'
            ];
        }
    }
}
function getquotess($request)
{
    $policy_id = $request->policyId;
    $response = [];
    foreach ($policy_id as $id) {
        $request->policyId = $id;
        $result = getpayasyoudriveQuote($request);
        $res_obj = $result;
        $response[] = $res_obj;
    }
    return json_encode($response, true);
}
