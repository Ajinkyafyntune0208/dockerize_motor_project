<?php
use Carbon\Carbon;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Models\MasterProduct;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    //mmv
    $mmv = get_mmv_details($productData,$requestData->version_id,'reliance');
    if($mmv['status'] == 1) {
        $mmv = $mmv['data'];
        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);
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
    }
    
    $prev_policy_end_date = $requestData->previous_policy_expiry_date;
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($prev_policy_end_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = $car_age = floor($age / 12);

    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();

    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    $url=config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_FETCH_RENEWAL');

    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
    ->pluck('premium_type_code')
    ->first();

    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    $UserID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_USERID_RELIANCE'))) ? config('constants.IcConstants.reliance.TP_USERID_RELIANCE') : config('constants.IcConstants.reliance.USERID_RELIANCE');

    $SourceSystemID = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE')) )? config('constants.IcConstants.reliance.TP_SOURCE_SYSTEM_ID_RELIANCE') : config('constants.IcConstants.reliance.SOURCE_SYSTEM_ID_RELIANCE');

    $AuthToken = (($tp_only == 'true') && !empty(config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE')) ) ? config('constants.IcConstants.reliance.TP_AUTH_TOKEN_RELIANCE') : config('constants.IcConstants.reliance.AUTH_TOKEN_RELIANCE');

    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
    ->where('seller_type','P')
    ->first();

    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');

    $POSType = '';
    $POSAadhaarNumber = '';
    $POSPANNumber = '';
    if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        $POSType = '2';
        $POSAadhaarNumber = !empty($pos_data->aadhar_no) ? $pos_data->aadhar_no : '';
        $POSPANNumber = !empty($pos_data->pan_no) ? $pos_data->pan_no : '';
    }

    $renewal_fetch_array = [
                            'PrevPolicyNumber' => $user_proposal->previous_policy_number,//'920222123110003941',
                            'EngineNo' => '',
                            'ChassisNo' => '',
                            'RegistrationNo' => $user_proposal->vehicale_registration_number,// 'MH-01-AZ-3455',
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
                                    'transaction_type'    => 'quote',
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
                                    $CoverDetails = $renewal_response->PolicyDetails->CoverDetails->CoverList;
                                    $ClientDetails = $renewal_response->PolicyDetails->ClientDetails;
                                    $Policy = $renewal_response->PolicyDetails->Policy;
                                    $Vehicle = $renewal_response->PolicyDetails->Vehicle;
                                    $PreviousInsuranceDetails = $renewal_response->PolicyDetails->PreviousInsuranceDetails;
                                    $Premium = $renewal_response->PolicyDetails->Premium;
                                    $NCBEligibility = $renewal_response->PolicyDetails->NCBEligibility;
                                    $reg_no = explode('-',$Vehicle->Registration_Number);

                                    $ncb_master = [
                                        "0" => "0",
                                        "1" => "20",
                                        "2" => "25",
                                        "3" => "35",
                                        "4" => "45",
                                        "5" => "50",
                                    ];

                                    $policyType = null;
                                    if ($Policy->ProductName == 'Private Car Comprehensive Policy') {
                                        $policyType = 'comprehensive';
                                    } elseif ($Policy->ProductName == 'ACT POL-PRIVATE CAR') {
                                        $policyType = 'third_party';
                                    } elseif (in_array($Policy->ProductName,['2309 Standalone OD Pvt Car product','Reliance Private Car Policy- Own Damage'])) {
                                        $policyType = 'own_damage';
                                    }

                                    if (!empty($policyType) && $requestData->business_type != 'breakin' && $policyType != $premium_type) {
                                        return [
                                            'status' => false,
                                            'message' => 'Missmatched policy type',
                                            'request' => [
                                                'message' => 'Missmatched policy type',
                                                'business_type' => $requestData->business_type,
                                                'premium_type_code' => $premium_type,
                                                'policyType' => $policyType
                                            ]
                                        ];
                                    }

                                  $PreviousNCBId = array_search($NCBEligibility->PreviousNCB,$ncb_master);
                                  $CurrentNCBId = array_search($NCBEligibility->CurrentNCB,$ncb_master);

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

                                  $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                                  $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                                  $vehicle_manf = explode('-',$user_proposal->vehicle_manf_year);
                                  $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
                                  $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
                                  $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

                                    //PA for un named passenger
                                    $IsPAToUnnamedPassengerCovered = 'false';
                                    $PAToUnNamedPassenger_IsChecked = '';
                                    $PAToUnNamedPassenger_NoOfItems = '';
                                    $PAToUnNamedPassengerSI = 0;
                                    $liabilitytoemployee = [];

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
                                            if ($value['name'] == 'PA cover for additional paid driver') {
                                                $IsPAToDriverCovered = 'true';
                                                $PAToPaidDriver_IsChecked = 'true';
                                                $PAToPaidDriver_NoOfItems = '1';
                                                $PAToPaidDriver_SumInsured = (int) $value['sumInsured'];
                                            }

                                            if ($value['name'] == 'Unnamed Passenger PA Cover') {
                                                $IsPAToUnnamedPassengerCovered = 'true';
                                                $PAToUnNamedPassenger_IsChecked = 'true';
                                                $PAToUnNamedPassenger_NoOfItems = $mmv_data->seating_capacity;
                                                $PAToUnNamedPassengerSI = (int) $value['sumInsured'];
                                            }

                                            if ($value['name'] == 'LL paid driver') {
                                                $IsLiabilityToPaidDriverCovered = 'true';
                                                $LiabilityToPaidDriver_IsChecked = 'true';
                                            }

                                            if($value['name'] == 'Geographical Extension')
                                            {
                                                $IsGeographicalAreaExtended = 'true';
                                                $Countries = 'true';
                                            }
                                        }
                                    }

                                    $IsElectricalItemFitted = 'false';
                                    $ElectricalItemsTotalSI = 0;
                        
                                    $IsNonElectricalItemFitted = 'false';
                                    $NonElectricalItemsTotalSI = 0;
                                    $BiFuelKitSi = 0;


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
                                                $VoluntaryDeductible = (int) $value['sumInsured'];
                                            }
                                            if ($value['name'] == 'TPPD Cover') {
                                                $TPPDCover = 'true';
                                            }
                                        }
                                    }


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
                                    if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
                                        $accessories = $selected_addons->accessories;
                                        foreach ($accessories as $value) {
                                            if ($value['name'] == 'Electrical Accessories') {
                                                $IsElectricalItemFitted = 'true';
                                                $ElectricalItemsTotalSI = (int) $value['sumInsured'];
                                            }
                                            if ($value['name'] == 'Non-Electrical Accessories') {
                                                $IsNonElectricalItemFitted = 'true';
                                                $NonElectricalItemsTotalSI = (int) $value['sumInsured'];
                                            }
                                            if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                                                $type_of_fuel = '5';
                                                $Fueltype = 'CNG'; 
                                                $BiFuelKitSi = (int) $value['sumInsured'];
                                                $is_bifuel_kit = 'true';
                                            }
                                        }
                                    }
                                    $vehicle_registration_date = DateTime::createFromFormat('d/m/Y', $Vehicle->Registration_date);
                                    $new_format_registration_date = $vehicle_registration_date->format('Y-m-d');

                                    $DateOfPurchase = DateTime::createFromFormat('d/m/Y', $Vehicle->DateOfPurchase);
                                    $new_format_DateOfPurchase = $DateOfPurchase->format('Y-m-d');


                                    if($requestData->vehicle_owner_type == "C")
                                    {
                                        $liabilitytoemployee = [
                                                'LiabilityToEmployee' => [
                                                        'NoOfItems' => $mmv_data->carrying_capacity ?? 0
                                                    ]
                                            ];
                                    }
                                    else
                                    {
                                        $liabilitytoemployee = null;
                                    }
                                //   $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime(str_replace('/', '-',$Policy->PolicyEndDate))));
                                //   $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime(str_replace('/', '-',$Policy->PolicyEndDate))));
                                    $premium_req_array = 
                                    [
                                        'ClientDetails' => [
                                            'ClientType' => $ClientDetails->ClientTypeID,
                                          ],
                                          'Policy' => [
                                            'BusinessType' => '2',
                                            'AgentCode' => 'Direct',
                                            'AgentName' => 'Direct',
                                            'Branch_Name' =>'Direct',
                                            'Cover_From' => $policy_start_date ,
                                            'Cover_To' => $policy_end_date,
                                            'Branch_Code' => $Policy->Branchcode,
                                            'productcode' => $Policy->productcode,
                                            'OtherSystemName' => '1',
                                            'isMotorQuote' => 'false',
                                            'isMotorQuoteFlow' => '',
                                            'POSType' => $POSType,
                                            'POSAadhaarNumber' => $POSAadhaarNumber,
                                            'POSPANNumber' => $POSPANNumber,
                                          ],
                                          'Risk' => [
                                            'VehicleMakeID' => $Vehicle->VehicleMakeID,
                                            'VehicleModelID' => $Vehicle->VehicleModelID,
                                            'StateOfRegistrationID' => $Vehicle->RTOstateID,
                                            'RTOLocationID' =>  $Vehicle->RTOLocationID,
                                            'ExShowroomPrice' => $Vehicle->ExShowroomPrice,
                                            'IDV' =>  $Vehicle->IDV,
                                            'DateOfPurchase' => $new_format_DateOfPurchase,#date('Y-m-d', strtotime($Vehicle->DateOfPurchase)),
                                            // 'ManufactureMonth' => date('m', strtotime($Vehicle->DateOfPurchase)),
                                            // 'ManufactureYear' => date('Y', strtotime($Vehicle->DateOfPurchase)),
                                            'ManufactureMonth'                    => $vehicle_manf[0],
                                            'ManufactureYear'                     => $vehicle_manf[1],
                                            'EngineNo' => $Vehicle->EngineNo,
                                            'Chassis' => $Vehicle->Chassis,
                                            'IsRegAddressSameasCommAddress' => 'true',
                                            'IsRegAddressSameasPermanentAddress' => 'true',
                                            'IsPermanentAddressSameasCommAddress' => 'true',
                                            'VehicleVariant' => $Vehicle->VehicleVariant,
                                            'IsVehicleHypothicated' =>  $Vehicle->IsVehicleHypothicated,
                                            'FinanceType' =>  $Vehicle->FinanceType ?? '',
                                            'FinancierName' =>  $Vehicle->FinancierName ?? '',
                                            'FinancierAddress' =>  $Vehicle->FinancierAddress ?? '',
                                            // 'FinancierCity' =>  $Vehicle->FinancierCity ?? '',
                                            'IsHavingValidDrivingLicense' => '',
                                            'IsOptedStandaloneCPAPolicy' => '',
                                            'CubicCapacity' => $Vehicle->CubicCapacity,
                                            'Zone' => $Vehicle->Zone,
                                            'Rto_RegionCode' => $reg_no[0].'-'.$reg_no[1],
                                          ],
                                          'Vehicle' => [
                                            'TypeOfFuel' => $type_of_fuel,#$Vehicle->TypeOfFuelID,
                                            'ISNewVehicle' => 'false',
                                            'Registration_Number' => $Vehicle->Registration_Number,
                                            'Registration_date' => $new_format_registration_date,#date('Y-m-d', strtotime($Vehicle->Registration_date)),
                                            'MiscTypeOfVehicleID' => '',
                                            // 'SeatingCapacity' => $Vehicle->SeatingCapacity,
                                          ],
                                          'Cover' => [
                                            'IsPAToUnnamedPassengerCovered' => $IsPAToUnnamedPassengerCovered,'IsVoluntaryDeductableOpted'    => $IsVoluntaryDeductableOpted,
                                            'IsGeographicalAreaExtended' => $IsGeographicalAreaExtended,
                                            'IsElectricalItemFitted'        => $IsElectricalItemFitted,
                                            'ElectricalItemsTotalSI'        => $ElectricalItemsTotalSI,
                                            'IsPAToOwnerDriverCoverd'       => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                                            'IsLiabilityToPaidDriverCovered' => $IsLiabilityToPaidDriverCovered,
                                            'IsTPPDCover'                   => $TPPDCover,
                                            'IsBasicODCoverage'             => ($tp_only == 'true') ? 'false' : 'true',
                                            'IsBasicLiability'              => ($tp_only == 'true') ? 'true' : 'false',
                                            'IsNonElectricalItemFitted'     => $IsNonElectricalItemFitted,
                                            'NonElectricalItemsTotalSI'     => $NonElectricalItemsTotalSI,
                                            'IsPAToDriverCovered'           => $IsPAToDriverCovered,
                                            'IsBiFuelKit'                   => $is_bifuel_kit,
                                            'BiFuelKitSi'                   => $BiFuelKitSi,
                                            'IsBifuelTypeChecked'           => ($Vehicle->TypeOfFuelID == '5') ? 'true' : 'false',
                                            'SecurePlus'                    => 'true',
                                            'SecurePremium'                 => 'true',
                                            'IsInsurancePremium'            => 'true',
                                            'IsLiabilityToEmployeeCovered'  => ($requestData->vehicle_owner_type == 'C') ? 'true' : 'false',
                                            'LiabilityToEmployee'           => $liabilitytoemployee,
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
                                            'IsAntiTheftDeviceFitted'       => $anti_theft,
                                            'IsAutomobileAssociationMember' => 'false',
                                            'AutomobileAssociationName' => '',
                                            'AutomobileAssociationNo' => '',
                                            'AutomobileAssociationExpiryDate' => '',
                                            'PACoverToOwnerDriver'            => '1',
                                            'PACoverToOwner'                => [
                                                'PACoverToOwner' => [
                                                    'IsChecked'           => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
                                                    'NoOfItems'           => ($requestData->vehicle_owner_type == 'I') ? '1' : '',
                                                    'CPAcovertenure'      => ($requestData->vehicle_owner_type == 'I') ? '1' : '',
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


                                            'VoluntaryDeductible'             => [
                                                'VoluntaryDeductible' => [
                                                    'SumInsured' => $VoluntaryDeductible
                                                ],
                                            ],
                                          ],
                                          'PreviousInsuranceDetails' => [
                                            'IsPreviousPolicyDetailsAvailable' => 'true',
                                            'PrevInsuranceID' =>'',
                                            'IsVehicleOfPreviousPolicySold' => 'false',
                                            'IsNCBApplicable' => ($NCBEligibility->NCBEligibilityCriteria == '2') ? 'true' : 'false',
                                            'PrevYearInsurer' => '11',
                                            'PrevYearPolicyNo' => $PreviousInsuranceDetails->PrevYearPolicyNo,
                                            'PrevYearInsurerAddress' => '',
                                            'DocumentProof'         => '',
                                            'PrevPolicyPeriod'  => '1',
                                            'PrevYearPolicyType' => '1',
                                            // 'PrevYearPolicyStartDate' => $PreviousInsuranceDetails->PrevYearPolicyStartDate,
                                            // 'PrevYearPolicyEndDate' => $PreviousInsuranceDetails->PrevYearPolicyEndDate,
                                            'PrevYearPolicyStartDate'       => $PrevYearPolicyStartDate,
                                            'PrevYearPolicyEndDate'         => $PrevYearPolicyEndDate,
                                            'MTAReason' => '',
                                            'PrevYearNCB' => $ncb_master[$PreviousNCBId],
                                            'IsInspectionDone' => 'false',
                                            'InspectionDate'   => '',
                                            'Inspectionby'  => '',
                                            'InspectorName' => '',
                                            'IsNCBEarnedAbroad' => 'false',
                                            'ODLoading'       => '',
                                            'IsClaimedLastYear' => $Vehicle->IsClaimedLastYear,
                                            'ODLoadingReason'   => '',
                                            'PreRateCharged'    => '',
                                            'PreSpecialTermsAndConditions'   => '',
                                            'IsTrailerNCB' => 'false',
                                            'InspectionID' => '',



                                          ],
                                          'NCBEligibility' => [

                                            'NCBEligibilityCriteria' => $NCBEligibility->NCBEligibilityCriteria,
                                            'NCBReservingLetter' => '',
                                            'PreviousNCB' => $PreviousNCBId,
                                            'CurrentNCB' => $CurrentNCBId,
                                          ],
                                          'ProductCode' => $Policy->productcode,
                                          'UserID' => $UserID,
                                        //   'LstCoveragePremium' => '',
                                        //   'ValidateFlag' => 'false',
                                          'SourceSystemID' => $SourceSystemID,
                                          'AuthToken' => $AuthToken,
                                        //   'LstTaxComponentDetails' => '',
                                          'IsQuickquote' => 'true',
                                        ];
                        
                                    $get_response = getWsData(
                                        config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_COVERAGE'),
                                        $premium_req_array,
                                        'reliance',
                                        [
                                            'root_tag'      => 'PolicyDetails',
                                            'section'       => $productData->product_sub_type_code,
                                            'method'        => 'Coverage Calculation Renewal',
                                            'requestMethod' => 'post',
                                            'enquiryId'     => $enquiryId,
                                            'productName'   => $productData->product_name. " ($Policy->BusinessType)",
                                            'transaction_type'    => 'quote',
                                            'headers' => [
                                                'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                                            ]
                                        ]
                                    );
                                    $coverage_res_data = $get_response['response'];

                                    if ($coverage_res_data) {
                                        $coverage_res_data = json_decode($coverage_res_data);
                                        if (!isset($coverage_res_data->ErrorMessages)) {
                                            $nil_dep_rate = '';
                                            $secure_plus_rate = '';
                                            $secure_premium_rate = '';
                                            $IsNilDepreciation = 'false';

                                            // dd($CoverDetails);
                                            if ($productData->zero_dep == '0') {
                                                $IsNilDepreciation = 'true';
                                            }
                                            if (isset($coverage_res_data->LstAddonCovers)) 
                                            {

                                                foreach ($coverage_res_data->LstAddonCovers as $k => $v) 
                                                {
                                                    if ($v->CoverageName == 'Nil Depreciation') 
                                                    {
                                                        $nil_dep_rate = $v->rate;
                                                    } elseif ($v->CoverageName == 'Secure Plus') 
                                                    {
                                                        $secure_plus_rate = $v->rate;
                                                    } elseif ($v->CoverageName == 'Secure Premium') 
                                                    {
                                                        $secure_premium_rate = $v->rate;
                                                    }
                                                }

                                                if ($masterProduct->product_identifier == 'zero_dep' && $nil_dep_rate !== '') {
                                                    unset($premium_req_array['Cover']['SecurePlus']);
                                                    unset($premium_req_array['Cover']['SecurePremium']);
                                                    $premium_req_array['Cover']['IsNilDepreciation'] = $IsNilDepreciation;
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
                                                }else
                                                {
                                                    $premium_req_array['Cover']['SecurePlus'] = 'false';
                                                    $premium_req_array['Cover']['SecurePremium'] = 'false';

                                                }

                                                // dd($coverage_res_data);

                        
                        
                                                $get_response = getWsData(
                                                    config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PREMIUM'),
                                                    $premium_req_array,
                                                    'reliance',
                                                    [
                                                        'root_tag'      => 'PolicyDetails',
                                                        'section'       => $productData->product_sub_type_code,
                                                        'method'        => 'Premium Calculation Renewal',
                                                        'requestMethod' => 'post',
                                                        'enquiryId'     => $enquiryId,
                                                        'productName'   => $productData->product_name. " ($Policy->BusinessType)",
                                                        'transaction_type'    => 'quote',
                                                        'headers' => [
                                                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                                                        ]
                                                    ]
                                                );
                                                $premium_res_data = $get_response['response'];
                        
                                                if ($premium_res_data) {
                                                    $response = json_decode($premium_res_data)->MotorPolicy ?? '';
                                                    if(empty($response)) {
                                                        return [
                                                            'premium_amount' => 0,
                                                            'webservice_id'=> $get_response['webservice_id'],
                                                            'table'=> $get_response['table'],
                                                            'status'         => false,
                                                            'message'        => 'Insurer not reachable1',
                                                        ];
                                                    }
                        
                                                    unset($premium_res_data);
                                                    if (trim($response->ErrorMessages) == '')
                                                    {
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
                                                        $other_addon_amount = 0;
                                                        $liabilities = 0;
                                                        $voluntary_excess = 0;
                                                        $tppd_discount = 0;
                                                        $geog_Extension_OD_Premium = 0;
                                                        $geog_Extension_TP_Premium = 0;
                                                        $inspection_charges = !empty((int) $response->InspectionCharges) ? (int) $response->InspectionCharges : 0;
                                                        $liability_to_employee_premium = 0;
                                                        $idv = $response->IDV;
                                                        $response->lstPricingResponse = is_object($response->lstPricingResponse) ? [$response->lstPricingResponse] : $response->lstPricingResponse;
                                                        foreach ($response->lstPricingResponse as $k => $v) {
                                                            $value = (float)(trim(str_replace('-', '', $v->Premium)));
                                                            if ($v->CoverageName == 'Basic OD') {
                                                                $basic_od = round($value) + $inspection_charges;
                                                            } elseif (($v->CoverageName == 'Nil Depreciation')) {
                                                                $zero_dep_amount = round($value);
                                                            }elseif (($v->CoverageName == 'Secure Plus') || ($v->CoverageName == 'Secure Premium')) {
                                                                $other_addon_amount = round($value);
                                                            }  elseif ($v->CoverageName == 'Bifuel Kit') {
                                                                $lpg_cng = round($value);
                                                            } elseif ($v->CoverageName == 'Electrical Accessories') {
                                                                $electrical_accessories = round($value);
                                                            } elseif ($v->CoverageName == 'Non Electrical Accessories') {
                                                                $non_electrical_accessories = round($value);
                                                            } elseif ($v->CoverageName == 'NCB') {
                                                                $ncb_discount = round($value);
                                                            } elseif ($v->CoverageName == 'Basic Liability') {
                                                                $tppd = round($value);
                                                            } elseif ($v->CoverageName == 'PA to Unnamed Passenger') {
                                                                $pa_unnamed = round($value);
                                                            } elseif ($v->CoverageName == 'PA to Owner Driver') {
                                                                $pa_owner = round($value);
                                                            } elseif ($v->CoverageName == 'PA to Paid Driver') {
                                                                $pa_paid_driver = round($value);
                                                            } elseif ($v->CoverageName == 'Liability to Paid Driver') {
                                                                $liabilities = round($value);
                                                            } elseif ($v->CoverageName == 'Bifuel Kit TP') {
                                                                $lpg_cng_tp = round($value);
                                                            } elseif ($v->CoverageName == 'Automobile Association Membership') {
                                                                $automobile_association = round(abs($value));
                                                            } elseif ($v->CoverageName == 'Anti-Theft Device') {
                                                                $anti_theft = round(abs($value));
                                                            } elseif ($v->CoverageName == 'Voluntary Deductible') {
                                                                $voluntary_excess = round(abs($value));
                                                            } elseif ($v->CoverageName == 'TPPD') {
                                                                $tppd_discount = round(abs($value));
                                                            } elseif (in_array($v->CoverageName, ['Geographical Extension' , 'Geo Extension']) && $v->CoverID == 5) {
                                                                $geog_Extension_OD_Premium = round(abs($value));
                                                            } elseif ($v->CoverageName == 'Geographical Extension'  && in_array($v->CoverID, [6,403])) {#$v->CoverID == 6
                                                                $geog_Extension_TP_Premium = round(abs($value));
                                                            }elseif($v->CoverageName == 'Liability to Employees') {
                                                                $liability_to_employee_premium = $value;
                                                            }
                        
                                                            unset($value);
                                                        }

                                                        // dd($ncb_discount);
                        
                                                        $add_ons_data = [];
                                                        $add_ons_data['in_built'] = [];
                                                        $add_ons_data['additional'] = [];
                                                        $add_ons_data['other'] = [];
                        
                                                        if ($tp_only == 'false') {
                                                            switch (strtolower($masterProduct->product_identifier)) {
                                                                case 'zero_dep':
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
                                                                                'return_to_invoice' => 0,
                                                                                'lopb' => 0,
                                                                            ],
                                                                            'other' => []
                                                                        ];
                                                                    }else {
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
                                                                                'return_to_invoice' => 0,
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
                                                                                'return_to_invoice' => 0,
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
                                                                                'return_to_invoice' => 0,
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
                                                                                'return_to_invoice' => 0,
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
                                                                                'return_to_invoice' => 0,
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
                                                                                'return_to_invoice' => 0,
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
                                                                                'return_to_invoice' => 0,
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
                                                            'zeroDepreciation', 'keyReplace', 'engineProtector', 'consumables', 'tyreSecure', 'lopb'
                                                        ];
                        
                                                        if ($car_age > 5) {
                                                            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                                                            array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                                                            array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                                                            array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                                                            array_splice($applicable_addons, array_search('tyreSecure', $applicable_addons), 1);
                                                            array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                                                        }
                                                        if($productData->zero_dep == '0' && ($add_ons_data['additional']['zeroDepreciation'] ?? 0) > 0) {
                                                            $add_ons_data['in_built']['zeroDepreciation'] = $add_ons_data['additional']['zeroDepreciation'];
                                                            unset($add_ons_data['additional']['zeroDepreciation']);
                                                        }
                                                        if($productData->zero_dep == '0' && !($zero_dep_amount > 0 || $other_addon_amount > 0)) {
                                                            return [
                                                                'premium_amount' => 0,
                                                                'status'         => false,
                                                                'webservice_id'=> $get_response['webservice_id'],
                                                                'table'=> $get_response['table'],
                                                                'message'        => 'Zero Dep Amount is Not avaliable',
                                                                'request' => [
                                                                    'productData' => $productData,
                                                                    'zero_dep_amount' => $zero_dep_amount
                                                                ]
                                                            ];
                                                        }
                                                        $ic_vehicle_discount = 0;
                                                        $other_discount = 0;
                                                        $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng + $geog_Extension_OD_Premium;
                                                        $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $geog_Extension_TP_Premium + $liability_to_employee_premium;
                                                        $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount;
                        
                                                        $final_net_premium     = $final_od_premium + $final_tp_premium - $final_total_discount;
                                                        $final_gst_amount      = $final_net_premium * 0.18;
                                                        $final_payable_amount  = $final_net_premium + $final_gst_amount;
                                                        $data_response = [
                                                            'status' => true,
                                                            'msg' => 'Found',
                                                            'webservice_id'=> $get_response['webservice_id'],
                                                            'table'=> $get_response['table'],
                                                            'Data' => [
                                                                'isRenewal'                 => 'Y',
                                                                'idv'                       => round($Vehicle->IDV),
                                                                'min_idv'                   => round($Vehicle->IDV),
                                                                'max_idv'                   => round($Vehicle->IDV),
                                                                'vehicle_idv'               => round($idv),
                                                                'qdata'                     => NULL,
                                                                'pp_enddate'                => $requestData->previous_policy_expiry_date,
                                                                'addonCover'                => NULL,
                                                                'addon_cover_data_get'      => '',
                                                                'rto_decline'               => NULL,
                                                                'rto_decline_number'        => NULL,
                                                                'mmv_decline'               => NULL,
                                                                'mmv_decline_name'          => NULL,
                                                                'policy_type'               => $premium_type == 'third_party' ? 'Third Party' : (($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'),
                                                                'cover_type'                => '1YC',
                                                                'hypothecation'             => '',
                                                                'hypothecation_name'        => '',
                                                                'vehicle_registration_no'   => $requestData->rto_code,
                                                                'rto_no'                    => $requestData->rto_code,
                        
                                                                'version_id'                => $requestData->version_id,
                                                                'selected_addon'            => [],
                                                                'showroom_price'            => 0,
                                                                'fuel_type'                 => $requestData->fuel_type,
                                                                'ncb_discount'              => $requestData->applicable_ncb,
                                                                'company_name'              => $productData->company_name,
                                                                'company_logo'              => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                                                                'product_name'              => $productData->product_name,
                                                                'mmv_detail' => $mmv_data,
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
                                                                    'ic_vehicle_discount'   => 0 //round($insurer_discount)
                                                                ],
                        
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
                                                                'GeogExtension_ODPremium'                   => $geog_Extension_OD_Premium,
                                                                'GeogExtension_TPPremium'                   => $geog_Extension_TP_Premium,
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
                                                                'business_type'                             => $requestData->business_type,
                                                                'service_err_code'                          => NULL,
                                                                'service_err_msg'                           => NULL,
                                                                'policyStartDate'                           => date('d-m-Y', strtotime($policy_start_date)),
                                                                'policyEndDate'                             => date('d-m-Y', strtotime($policy_end_date)),
                                                                'ic_of'                                     => $productData->company_id,
                                                                'ic_vehicle_discount'                       => 0,
                                                                // 'vehicle_in_90_days'                        => $vehicle_in_90_days,
                                                                'get_policy_expiry_date'                    => NULL,
                                                                'get_changed_discount_quoteid'              => 0,
                                                                'vehicle_discount_detail' => [
                                                                    'discount_id'       => NULL,
                                                                    'discount_rate'     => NULL
                                                                ],
                                                                // 'other_covers' => [
                                                                //     'LegalLiabilityToEmployee' => $liability_to_employee_premium ?? 0
                                                                // ],
                                                                'is_premium_online'     => $productData->is_premium_online,
                                                                'is_proposal_online'    => $productData->is_proposal_online,
                                                                'is_payment_online'     => $productData->is_payment_online,
                                                                'policy_id'             => $productData->policy_id,
                                                                'insurane_company_id'   => $productData->company_id,
                                                                'max_addons_selection'  => NULL,
                        
                                                                'add_ons_data' =>   [
                                                                    'in_built'   => [],
                                                                    'additional' => [
                                                                        'engine_protector'            => 0,
                                                                        'ncb_protection'              => 0,
                                                                        'keyReplace'                  => 0,
                                                                        'consumables'                 => 0,
                                                                        'tyre_secure'                 => 0,
                                                                        'return_to_invoice'           => 0,
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
                                                                ]
                                                            ]
                                                        ];
                        
                                                        if ($tp_only != 'true') {
                                                            $data_response['Data']['add_ons_data'] = $add_ons_data;
                                                        }
                                                        if ($is_bifuel_kit == 'true') {
                                                            $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng;
                                                            $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                                                            $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                                                        }

                                                        if ($liability_to_employee_premium > 0) {
                                                            $data_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $liability_to_employee_premium;
                                                            $data_response['Data']['LegalLiabilityToEmployee'] = $liability_to_employee_premium;
                                                        }
                        
                                                        //  if(strtolower($masterProduct->product_identifier == 'secure_premium' || $masterProduct->product_identifier == 'secure_plus' || $masterProduct->product_identifier == 'zero_dep')) {
                                                        //     $data_response['Data']['add_ons_data'] = $add_ons_data;
                                                        // }

                        
                                                        return camelCase($data_response);
                                                    } else {
                                                        return [
                                                            'premium_amount' => 0,
                                                            'status'         => false,
                                                            'webservice_id'=> $get_response['webservice_id'],
                                                            'table'=> $get_response['table'],
                                                            'message'        => $response->ErrorMessages
                                                        ];
                                                    }
                                                } else {
                                                    return [
                                                        'premium_amount' => 0,
                                                        'status'         => false,
                                                        'webservice_id'=> $get_response['webservice_id'],
                                                        'table'=> $get_response['table'],
                                                        'message'        => 'Insurer not reachable4',
                                                    ];
                                                }
                                                die;
                                            } else {
                                                return [
                                                    'premium_amount' => 0,
                                                    'status'         => false,
                                                    'webservice_id'=> $get_response['webservice_id'],
                                                    'table'=> $get_response['table'],
                                                    'message'        => 'Insurer not reachable3',
                                                ];
                                            }
                                        } else {
                                            return [
                                                'premium_amount' => 0,
                                                'status'         => false,
                                                'webservice_id'=> $get_response['webservice_id'],
                                                'table'=> $get_response['table'],
                                                'message'        => $coverage_res_data->ErrorMessages
                                            ];
                                        }
                                    } else {
                                        return [
                                            'premium_amount' => 0,
                                            'status'         => false,
                                            'webservice_id'=> $get_response['webservice_id'],
                                            'table'=> $get_response['table'],
                                            'message'        => 'Insurer Not Reachable2'
                                        ];
                                    }
                                }else
                                {
                                    return [
                                        'premium_amount' => 0,
                                        'status'         => false,
                                        'webservice_id'=> $get_response['webservice_id'],
                                        'table'=> $get_response['table'],
                                        'message'        => $renewal_response->PolicyDetails->ErrorMessages->ErrMessages
                                    ];

                                }
                            }else
                            {
                                return [
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'webservice_id'=> $get_response['webservice_id'],
                                    'table'=> $get_response['table'],
                                    'message'        => 'Insurer Not Reachable1'
                                ];
                            }
                            
}