<?php
namespace App\Http\Controllers\Proposal\Services;

use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterRto;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use App\Models\MagmaRtoLocation;
use Illuminate\Support\Facades\DB;
use App\Models\MagmaFinancierMaster;
use App\Models\MagmaVehiclePriceMaster;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class magmaSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        //$quote_data = json_decode($quote_log->quote_data, true);
        
        $parent_code = get_parent_code($productData->product_sub_type_id);

        if ($parent_code == 'PCV') {
            $mmv = [
                'MANUFACTURER' => 'HONDA',
                'MANUFACTURERCODE' => 'HO',
                'VEHICLEMODEL' => 'CITY',
                'VEHICLEMODELNAMECODE' => 'HOM10006',
                'TXT_VARIANT' => '1.3 LXI',
                'VEHICLEMODELCODE' => 'HO0022',
                'PRODUCTCODE' => '4103',
                'VEHICLECLASSCODE' => '63',
                'VEHICLECLASSDESC' => 'C1A PCV 4 Wheeler not exceeding 6 passengers',
                'ALLOW_SUBCLASS' => 'N',
                'TXT_VEHICLE_CLASS_SHORT_DESC' => 'PCV4whNotExceeding6psngr',
                'NUMBEROFWHEELS' => '4',
                'CUBICCAPACITY' => '1343',
                'GROSSVEHICLEWEIGHT' => '0',
                'SEATINGCAPACITY' => '5',
                'CARRYINGCAPACITY' => '4',
                'BODYTYPECODE' => '46',
                'VEHICLEBODYTYPEDESCRIPTION' => 'SALOON',
                'TXT_FUEL' => 'Petrol',
                'TXT_SEGMENTTYPE' => 'NotApplicable',
                'TXT_TACMAKECODE' => '7001',
                'NUM_VEHICLE_SUBCLASS_CODE' => '',
                'SPEED' => '',
                'DAT_START_DATE' => '04-01-2012  12.00.00 AM',
                'DAT_END_DATE' => '12/31/2050 12:00:00 AM',
                'VEHICLEMODELSTATUS' => 'Active',
                'ACTIVE_FLAG' => 'A',
                'TXT_STATUS_FLAG' => 'A'
            ];
        } else {
            $mmv = get_mmv_details($productData, $requestData->version_id,'magma');

            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        //$rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
        $rto_code = $requestData->rto_code;
        $rto_code = preg_replace("/OR/", "OD", $rto_code);
        if (str_starts_with(strtoupper($rto_code), "DL-0")) {
            $rto_code = RtoCodeWithOrWithoutZero($rto_code);
        }

        // $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();
        $rto_location = MagmaRtoLocation::where('rto_location_code', 'like', '%' . $rto_code . '%')->first();
        
        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d/m/Y');
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        
        //$policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new \DateTime($vehicleDate);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $carage = floor($age / 12);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);

        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);

        if ($requestData->business_type == 'newbusiness') {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = today()->format('d/m/Y');
            $policy_end_date = today()->addYear(1)->subDay(1)->format('d/m/Y');
            $PolicyProductType = $tp_only ? '1TP' : '1TP1OD';
            $previous_ncb = "";
            $PreviousPolicyType = "";
            $businesstype       = 'New Business';
            $proposal_date = $policy_start_date;
            $time = date('H:i', time());
        } else {
            $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
            $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
            $PolicyProductType = $tp_only ? '1TP' : '1TP1OD';
            $PreviousPolicyType = "MOT-PLT-001";
            $previous_ncb = $requestData->previous_ncb;
            $businesstype       = 'Roll Over';
            $proposal_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));
            $time = '00:00';
            if ($requestData->business_type == 'breakin') {
                $policy_start_date = date('d/m/Y');
                if ($productData->premium_type_id == 7) {
                    $today = date('d-m-Y');
                    $policy_start_date_d_m_y  = date('d-m-Y', strtotime($today . ' + 2 days'));
                    $policy_start_date = date('d/m/Y', strtotime($policy_start_date_d_m_y));
                } else {
                    return [
                        'status' => false,
                        'premium' => '0',
                        'message' => 'Breakin not allowed for this policy type',
                        'request' => [
                            'message' => 'Breakin not allowed for this policy type',
                            'premium_type_id' => $productData->premium_type_id,
                            'premium_type' => $premium_type
                        ]
                    ];
                }
            }
        }
        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');

        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "MALE") {
                $salutation = 'Mr';
            } else {
                if ($proposal->gender == "FEMALE" && $proposal->marital_status == "Single") {
                    $salutation = 'Mrs';
                } else {
                    $salutation = 'Ms';
                }
            }
        } else {
            $salutation = 'Miss';
        }

        if ($vehicale_registration_number[0] == 'NEW') {
            $vehicale_registration_number[0] = '';
        }

        $tokenParam = [
            'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
            'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
            'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
            'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
        ];

        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_CV_GETTOKEN'), http_build_query($tokenParam), 'magma', [
            'method' => 'Token Generation',
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'section' => $productData->product_sub_type_code,
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal'
        ]);
        $token = $get_response['response'];

        if ($token) {
            $token_data = json_decode($token, true);

            if (isset($token_data['access_token'])) {
                $vehicle_price = MagmaVehiclePriceMaster::where('vehicleclasscode', $mmv_data->vehicleclasscode)
                ->where('vehiclemodelcode', $mmv_data->vehiclemodelcode)
                ->first();

                $corresponding_address = DB::table('magma_motor_pincode_master AS mmpm')
                    ->leftJoin('magma_motor_city_master AS mmcm', 'mmpm.num_citydistrict_cd', '=', 'mmcm.num_citydistrict_cd')
                    ->leftJoin('magma_motor_state_master AS mmsm', 'mmpm.num_state_cd', '=', 'mmsm.num_state_cd')
                    ->where('mmpm.num_pincode', $proposal->pincode)
                    ->first();

                $proposal_array = [
                    'BusinessType' => $businesstype,
                    'PolicyProductType' => $PolicyProductType,
                    'ProposalDate' => $proposal_date,
                    'VehicleDetails' => [
                        'RegistrationDate' => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                        'TempRegistrationDate' => '',
                        'RegistrationNumber' => $requestData->business_type == 'newbusiness' ?  'NEW' : strtoupper($proposal->vehicale_registration_number),
                        'ChassisNumber' => $proposal->chassis_number,
                        'EngineNumber' => $proposal->engine_number,
                        'RTOCode' => $rto_code,
                        'RTOName' => $rto_location->rto_location_description,
                        'ManufactureCode' => $mmv_data->manufacturercode,
                        'ManufactureName' => $mmv_data->manufacturer,
                        'ModelCode' => $mmv_data->vehiclemodelcode,
                        'ModelName' => $mmv_data->vehiclemodel,
                        'HPCC' => $mmv_data->cubiccapacity,
                        'MonthOfManufacture' => Carbon::parse($requestData->vehicle_register_date)->format('m'),
                        'YearOfManufacture' => Carbon::parse($requestData->vehicle_register_date)->format('Y'),
                        'VehicleClassCode' => $mmv_data->vehicleclasscode,
                        'VehicleClassName' => $mmv_data->txt_vehicle_class_short_desc,
                        'SeatingCapacity' => $mmv_data->seatingcapacity,
                        'CarryingCapacity' => $mmv_data->carryingcapacity,
                        'BodyTypeCode' => $mmv_data->bodytypecode,
                        'BodyTypeName' => $mmv_data->vehiclebodytypedescription,
                        'FuelType' => $mmv_data->txt_fuel,
                        'GVW' => $mmv_data->grossvehicleweight,
                        'SeagmentType' => $mmv_data->txt_segmenttype,
                        'TACMakeCode' => $mmv_data->txt_tacmakecode,
                        'ExShowroomPrice' => $vehicle_price->vehiclesellingprice ?? '',
                        'IDVofVehicle' => $quote_log->idv,
                        'HigherIDV' => '',
                        'LowerIDV' => '',
                        'IDVofChassis' => '',
                        'Zone' => 'Zone-' . $rto_location->registration_zone,
                        'IHoldValidPUC' => $requestData->business_type == 'newbusiness' ? false : true,
                        'InsuredHoldsValidPUC' => false//$requestData->business_type == 'newbusiness' || $tp_only ? false : true,
                    ],
                    'GeneralProposalInformation' => [
                        'CustomerType' => $requestData->vehicle_owner_type,
                        'BusineeChannelType' => 'BROKER',
                        'BusinessSource' => 'INTERMEDIARY',
                        'EntityRelationShipCode' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPCODE'),
                        'EntityRelationShipName' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPNAME'),
                        'ChannelNumber' => config('constants.IcConstants.magma.MAGMA_CHANNELNUMBER'),
                        'DisplayOfficeCode' => config('constants.IcConstants.magma.MAGMA_DISPLAYOFFICECODE'),
                        'OfficeCode' => config('constants.IcConstants.magma.MAGMA_OFFICECODE'),
                        'OfficeName' => config('constants.IcConstants.magma.MAGMA_OFFICENAME'),
                        'IntermediaryCode' => config('constants.IcConstants.magma.MAGMA_INTERMEDIARYCODE'),
                        'IntermediaryName' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPNAME'),
                        'BusinessSourceType' => 'P_AGENT',
                        'PolicyEffectiveFromDate' => $policy_start_date,
                        'PolicyEffectiveToDate' => $policy_end_date,
                        'PolicyEffectiveFromHour' => $time,
                        'PolicyEffectiveToHour' => '23:59',
                        'SPCode' => config('constants.IcConstants.magma.MAGMA_SPCode'),
                        'SPName' => config('constants.IcConstants.magma.MAGMA_SPName'),
                    ],
                    'AddOnsPlanApplicable' => false,
                    'OptionalCoverageApplicable' => false,
                    'IsPrevPolicyApplicable' => $requestData->business_type == 'newbusiness' ? false : true,
                    'CompulsoryExcessAmount' => '1000',
                    'ImposedExcessAmount' => '',
                    'VoluntaryExcessAmount' => '',
                ];

                $selected_addons = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->first();

                if ($selected_addons) {
                    if ($selected_addons['compulsory_personal_accident'] && $selected_addons['compulsory_personal_accident'] != NULL && $selected_addons['compulsory_personal_accident'] != '' && $requestData->vehicle_owner_type == 'I') {
                        $proposal_array['PAOwnerCoverDetails'] = [
                            'PAOwnerSI'                => '',
                            'PAOwnerTenure'            => '',
                            'ValidDrvLicense'          => false,
                            'DoNotHoldValidDrvLicense' => false,
                            'Ownmultiplevehicles'      => true,
                            'ExistingPACover'          => false
                        ];

                        foreach ($selected_addons['compulsory_personal_accident'] as $compulsory_personal_accident) {
                            if (isset($compulsory_personal_accident['name']) && $compulsory_personal_accident['name'] == 'Compulsory Personal Accident') {
                                $proposal_array['PAOwnerCoverApplicable'] = true;
                                $proposal_array['PAOwnerCoverDetails'] = [
                                    'PAOwnerSI'                => '1500000',
                                    'PAOwnerTenure'            => '1',
                                    'ValidDrvLicense'          => true,
                                    'DoNotHoldValidDrvLicense' => false,
                                    'Ownmultiplevehicles'      => false,
                                    'ExistingPACover'          => false
                                ];

                                $proposal_array['NomineeDetails'] = [
                                    'NomineeName'               => $proposal->nominee_name == null ? '' : $proposal->nominee_name,
                                    'NomineeDOB'                => date('d/m/Y', strtotime($proposal->nominee_dob)),
                                    'NomineeRelationWithHirer'  => $proposal->nominee_relationship == null ? '' : $proposal->nominee_relationship,
                                    'PercentageOfShare'         => '100',
                                    'GuardianName'              => '',
                                    'GuardianDOB'               => '',
                                    'RelationshoipWithGuardian' => ''
                                ];
                            } else {
                                if (isset($compulsory_personal_accident['reason'])) {
                                    if ($compulsory_personal_accident['reason'] == 'I do not have a valid driving license.') {
                                        $proposal_array['PAOwnerCoverDetails']['DoNotHoldValidDrvLicense'] = true;
                                    } elseif ($compulsory_personal_accident['reason'] == 'I have another motor policy with PA owner driver cover in my name') {
                                        $proposal_array['PAOwnerCoverDetails']['Ownmultiplevehicles'] = true;
                                    } elseif ($compulsory_personal_accident['reason'] == 'I have another PA policy with cover amount of INR 15 Lacs or more') {
                                        $proposal_array['PAOwnerCoverDetails']['ExistingPACover'] = true;
                                    }
                                }

                                unset($proposal_array['NomineeDetails']);
                            }
                        }
                    }

                    if ($selected_addons['accessories'] && $selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '') {
                        $proposal_array['OptionalCoverageApplicable'] = true;

                        foreach ($selected_addons['accessories'] as $accessory) {
                            if ($accessory['name'] == 'Electrical Accessories') {
                                $proposal_array['OptionalCoverageDetails']['ElectricalApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['ElectricalDetails'] = [
                                    [
                                        'Description' => 'Head Light',
                                        'ElectricalSI' => (string) $accessory['sumInsured'] ,
                                        'SerialNumber' => '2',
                                        'YearofManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year))
                                    ],
                                ];
                            } elseif ($accessory['name'] == 'Non-Electrical Accessories') {
                                $proposal_array['OptionalCoverageDetails']['NonElectricalApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['NonElectricalDetails'] = [
                                    [
                                        'Description' => 'Head Light',
                                        'NonElectricalSI' => (string) $accessory['sumInsured'],
                                        'SerialNumber' => '2',
                                        'YearofManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year))
                                    ],
                                ];
                            } elseif ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                                $proposal_array['OptionalCoverageDetails']['ExternalCNGkitApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['ExternalCNGLPGkitDetails'] = [
                                        'CngLpgSI' => (string) $accessory['sumInsured']
                                ];
                            }
                        }
                    }

                    if ($selected_addons['additional_covers'] && $selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '') {
                        $proposal_array['OptionalCoverageApplicable'] = true;

                        foreach ($selected_addons['additional_covers'] as $additional_cover) {
                            if (in_array($additional_cover['name'], ['LL paid driver', 'LL paid driver/conductor/cleaner'])) {
                                $proposal_array['OptionalCoverageDetails']['LLPaidDriverCleanerApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['LLPaidDriverCleanerDetails'] = [
                                    'NoofPerson' => $parent_code == 'PCV' ? '1' : $additional_cover['LLNumberDriver'] + $additional_cover['LLNumberConductor'] + $additional_cover['LLNumberCleaner']
                                ];
                            } elseif ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                                $proposal_array['OptionalCoverageDetails']['UnnamedPACoverApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['UnnamedPACoverDetails'] = [
                                    'NoOfPerunnamed' => $mmv_data->seating_capacity,
                                    'UnnamedPASI' => $additional_cover['sumInsured'],
                                ];
                            } elseif (in_array($additional_cover['name'], ['PA cover for additional paid driver', 'PA paid driver/conductor/cleaner'])) {
                                $proposal_array['OptionalCoverageDetails']['PAPaidDriverApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['PAPaidDriverDetails'] = [
                                    'NoofPADriver' => $parent_code == 'PCV' ? 1 : $mmv_data->seatingcapacity,
                                    'PAPaiddrvSI' => $additional_cover['sumInsured'],
                                ];
                            } elseif ($additional_cover['name'] == 'Geographical Extension') {
                                $proposal_array['OptionalCoverageDetails']['GeographicalExtensionApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['GeographicalExtensionDetails'] = [
                                    'Sri Lanka' => in_array('Sri Lanka', $additional_cover['countries']) ? true : false,
                                    'Bhutan' => in_array('Bhutan', $additional_cover['countries']) ? true : false,
                                    'Nepal' => in_array('Nepal', $additional_cover['countries']) ? true : false,
                                    'Bangladesh' => in_array('Bangladesh', $additional_cover['countries']) ? true : false,
                                    'Pakistan' => in_array('Pakistan', $additional_cover['countries']) ? true : false,
                                    'Maldives' => in_array('Maldives', $additional_cover['countries']) ? true : false
                                ];
                            }
                        }
                    }

                    if ($selected_addons['discounts'] && $selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '') {                
                        $proposal_array['OptionalCoverageApplicable'] = true;

                        foreach ($selected_addons['discounts'] as $discount) {
                            if ($discount['name'] == 'anti-theft device') {
                                $proposal_array['OptionalCoverageDetails']['ApprovedAntiTheftDevice'] = true;
                                $proposal_array['OptionalCoverageDetails']['CertifiedbyARAI'] = true;
                            } elseif ($discount['name'] == 'voluntary_insurer_discounts') {
                                $proposal_array['VoluntaryExcessAmount'] = $discount['sumInsured'];
                            } elseif ($discount['name'] == 'TPPD Cover') {
                                $proposal_array['OptionalCoverageDetails']['TPPDLimitApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['TPPDLimitDetails'] = [
                                    'TPPDLimitAmount' => 6000
                                ];
                            } elseif ($discount['name'] == 'Vehicle Limited to Own Premises') {
                                $proposal_array['OptionalCoverageDetails']['UseofLimitedPermission'] = true;
                            }
                        }
                    }

                    if ($selected_addons['applicable_addons'] && $selected_addons['applicable_addons'] != NULL && $selected_addons['applicable_addons'] != '') {                
                        $proposal_array['AddOnsPlanApplicable'] = false;
                        $proposal_array['AddOnsPlanApplicableDetails']['PlanName'] = 'Optional Add on';

                        $AddOnsPlanApplicable = false;

                        foreach ($selected_addons['applicable_addons'] as $addon) {
                            if ($addon['name'] == 'Zero Depreciation') {
                                $proposal_array['AddOnsPlanApplicableDetails']['ZeroDepreciation'] = true;
                                $AddOnsPlanApplicable = true;
                            } elseif ($addon['name'] == 'Road Side Assistance') {
                                $proposal_array['AddOnsPlanApplicableDetails']['RoadSideAssistance'] = true;
                                $AddOnsPlanApplicable = true;
                            } elseif ($addon['name'] == 'Consumables') {
                                $proposal_array['AddOnsPlanApplicableDetails']['Consumables'] = true;
                                $AddOnsPlanApplicable = true;
                            } elseif ($addon['name'] == 'IMT - 23') {
                                $proposal_array['OptionalCoverageApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['IMT23Applicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['IMT23ApplicableDetails'] = [
                                    'IMT23InPreviousPolicy' => false
                                ];
                            }
                        }

                        $proposal_array['AddOnsPlanApplicable'] = $AddOnsPlanApplicable;

                        if ( ! $AddOnsPlanApplicable) {
                            $proposal_array['AddOnsPlanApplicableDetails'] = null;
                        }
                    }
                }
                    
                if ( ! isset($proposal_array['OptionalCoverageDetails']['TPPDLimitApplicable']) && $tp_only) {
                    $proposal_array['OptionalCoverageApplicable'] = true;
                    $proposal_array['OptionalCoverageDetails']['TPPDLimitApplicable'] = false;
                }

                if ($requestData->business_type != 'newbusiness') {
                    $PrevPolicyDetails = [
                        'PrevPolicyDetails' => [
                            'PrevNCBPercentage' => (int) $previous_ncb,
                            'PrevInsurerCompanyCode' => $proposal->previous_insurance_company,
                            'HavingClaiminPrevPolicy' => $requestData->is_claim == 'Y' ? true : false,
                            'PrevPolicyEffectiveFromDate' => $PreviousPolicyFromDt,
                            'PrevPolicyEffectiveToDate' => $PreviousPolicyToDt,
                            'PrevPolicyNumber' => $proposal->previous_policy_number,
                            'PrevPolicyType' => $tp_only ? 'LiabilityOnly' : 'PackagePolicy',
                            'PrevAddOnAvialable' => ($productData->zero_dep == '1') ? false : true,
                            'PrevPolicyTenure' => '1',
                            'IIBStatus' => 'Not Applicable',
                            'PrevInsuranceAddress' => 'ARJUN NAGAR',
                        ],
                    ];

                    $proposal_array = array_merge($proposal_array, $PrevPolicyDetails);
                }

                if ($proposal->is_vehicle_finance == 1) {
                    $financer = MagmaFinancierMaster::where('code', $proposal->name_of_financer)
                        ->first();

                    $proposal_array['FinancierDetailsApplicable'] = true;

                    $proposal_array['FinancierDetails'] = [
                        'FinancierName' => $financer['name'],
                        'FinancierCode' => $financer['code'],
                        'FinancierAddress' => $proposal->hypothecation_city,
                        'AgreementType' => $proposal->financer_agreement_type,
                        'BranchName' => '', //$Financier_Branch,
                        'CityCode' => '',
                        'CityName' => '', //$finance_City,
                        'DistrictCode' => '',
                        'DistrictName' => '', //$finance_City,
                        'Pincode' => '', //$finance_Pin,
                        'PincodeLocality' => '',
                        'StateCode' => '',
                        'StateName' => '', //$finance_State,
                        'FinBusinessType' => '',
                        'LoanAccountNumber' => ''
                    ];
                }

                $premium_calculation_url = '';

                if ($parent_code == 'PCV') {
                    $premium_calculation_url = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_PCV_GETPREMIUM');
                } else {
                    $premium_calculation_url = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_GCV_GETPREMIUM');
                }

                if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
                    $agentDiscount = calculateAgentDiscount($enquiryId, 'magma', strtolower($parent_code));
                    if ($agentDiscount['status'] ?? false) {
                        $proposal_array['GeneralProposalInformation']['DetariffDis'] = $agentDiscount['discount'];
                    } else {
                        if (!empty($agentDiscount['message'] ?? '')) {
                            return [
                                'status' => false,
                                'message' => $agentDiscount['message']
                            ];
                        }
                    }
                }

                $get_response = getWsData($premium_calculation_url, $proposal_array, 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Premium Calculation',
                    'requestMethod'    => 'post',
                    'type'             => 'premiumCalculation',
                    'headers'          => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token_data['access_token']
                    ],
                    'enquiryId'        => $enquiryId,
                    'productName'      => $productData->product_name,
                    'transaction_type' => 'proposal'
                ]);
                $premium_data = $get_response['response'];

                if ($premium_data) {
                    $arr_premium = json_decode($premium_data, true);

                    if (isset($arr_premium['ServiceResult']) && $arr_premium['ServiceResult'] == "Success") {
                        $vehicleDetails = [
                            'manufacture_name'  => $mmv_data->manufacturer,
                            'model_name'        => $mmv_data->vehiclemodel,
                            'version'           => $mmv_data->txt_variant,
                            'fuel_type'         => $mmv_data->txt_fuel,
                            'seating_capacity'  => $mmv_data->seatingcapacity,
                            'carrying_capacity' => $mmv_data->carryingcapacity,
                            'cubic_capacity'    => $mmv_data->cubiccapacity,
                            'gross_vehicle_weight' => $mmv_data->grossvehicleweight ?? 1,
                            'vehicle_type'      => $mmv_data->vehiclebodytypedescription ?? '',
                        ];

                        $ckyc_meta_data = ! empty($proposal->ckyc_meta_data) ? json_decode($proposal->ckyc_meta_data, true) : null;

                        $proposal_array['CustomerDetails'] = [
                            'CustomerType' => $requestData->vehicle_owner_type,
                            'CustomerName' => $requestData->vehicle_owner_type == 'I' ? $proposal->first_name . " " . $proposal->last_name : $proposal->first_name,
                            'CountryCode' => '91',
                            'CountryName' => 'India',
                            'ContactNo' => $proposal->mobile_number,
                            'PinCode' => $proposal->pincode,
                            'PincodeLocality' => $proposal->city,
                            'Nationality' => 'Indian',
                            'Salutation' => $requestData->vehicle_owner_type == 'I' ? $salutation : '',
                            'EmailId' => $proposal->email,
                            'DOB' => $requestData->vehicle_owner_type == 'I' ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',
                            'Gender' => $requestData->vehicle_owner_type == 'I' ? $proposal->gender : '',
                            'MaritalStatus' => $requestData->vehicle_owner_type == 'I' ? $proposal->marital_status : '',
                            'OccupationCode' => $requestData->vehicle_owner_type == 'C' ? '' : $proposal->occupation,
                            'AddressLine1' => $proposal->address_line1,
                            'AddressLine2' => $proposal->address_line2,
                            'AddressLine3' => $proposal->address_line3,
                            'CityDistrictCode' => $corresponding_address->num_citydistrict_cd,
                            'CityDistrictName' => $proposal->city,
                            'StateCode' => $corresponding_address->num_state_cd,
                            'StateName' => $proposal->state,
                            'PanNo' => config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y' && $proposal->ckyc_type == 'pan_card' && ! empty($ckyc_meta_data) ? $ckyc_meta_data['KYCData'] : $proposal->pan_number,
                            'AnnualIncome' => '1212121',
                            'GSTNumber' => $proposal->gst_number,
                            'UIDNo' => config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y' && $proposal->ckyc_type == 'aadhar_card' && ! empty($ckyc_meta_data) ? $ckyc_meta_data['KYCData'] : null
                        ];

                        if (config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y') {
                            $proposal_array['CustomerDetails'] = array_merge($proposal_array['CustomerDetails'], [
                                "IsKYCSuccess" => true,
                                "KYCNumber" => $proposal->ckyc_number ?? '',
                                "KYCType" => ! empty($ckyc_meta_data) ? $ckyc_meta_data['KYCType'] : '',
                                "PartnerKYCDocRefID" => "",
                                "IncorporationDate" => $requestData->vehicle_owner_type == 'C' ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',
                                "KYCLogID" => $proposal->ckyc_reference_id,
                            ]);
                        }

                        $proposal_submit_url = '';

                        if ($parent_code == 'PCV') {
                            $proposal_submit_url = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_PCV_SUBMIT_PROPOSAL');
                        } else {
                            $proposal_submit_url = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_GCV_SUBMIT_PROPOSAL');
                        }

                        $get_response = getWsData($proposal_submit_url, $proposal_array, 'magma', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Proposal Generation',
                            'requestMethod' => 'post',
                            'type' => 'ProposalGeneration',
                            'headers'          => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Bearer ' . $token_data['access_token']
                            ],
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'proposal'
                        ]);
                        $result = $get_response['response'];

                        if ($result) {
                            $response = json_decode($result, true);
                
                            if ($response['ErrorText'] == '') {
                                $proposal->proposal_no = $response['OutputResult']['ProposalNumber'];
                                $proposal->ic_vehicle_details = $vehicleDetails;
                                $proposal->save();

                                $basic_od_premium = $basic_tp_premium = $pa_unnamed = $ncb_discount = $liabilities =  $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $cng_od_premium =  $cng_tp_premium = $ncb_discount = $antitheft = $tppd_discount = $voluntary_excess_discount = $other_discount = $geog_Extension_OD_Premium = $geog_Extension_TP_Premium = $imt_23 = $limited_to_own_premises = $roadside_asst_premium = $zero_dep_premium = 0;

                                $add_array = $response['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

                                foreach ($add_array as $add1) {
                                    if ($add1['AddOnCoverType'] == 'Basic - OD') {
                                        $basic_od_premium = (float)($add1['AddOnCoverTypePremium']);
                                    } elseif ($add1['AddOnCoverType'] == "Basic - TP") {
                                        $basic_tp_premium = (float)($add1['AddOnCoverTypePremium']);
                                    } elseif ($add1['AddOnCoverType'] == "LL to Paid Driver IMT 28") {
                                        $liabilities = (float)($add1['AddOnCoverTypePremium']);
                                    } elseif ($add1['AddOnCoverType'] == "PA Owner Driver") {
                                        $pa_owner_driver = (float)($add1['AddOnCoverTypePremium']);
                                    } elseif ($add1['AddOnCoverType'] == "Roadside Assistance") {
                                        $roadside_asst_premium = (float)($add1['AddOnCoverTypePremium']);
                                    } elseif ($add1['AddOnCoverType'] == "Zero Depreciation") {
                                        $zero_dep_premium = (float)($add1['AddOnCoverTypePremium']);
                                    } elseif ($add1['AddOnCoverType'] == "Cover for Lamps Tyres and Tubes etc - IMT23") {
                                        $imt_23 = (float)($add1['AddOnCoverTypePremium']);
                                    }
                                }

                                if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'])) {
                                    $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];

                                    foreach ($optionadd_array as $add) {
                                        if ($add["OptionalAddOnCoversName"] == 'Electrical or electronic Accessories') {
                                            $electrical = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == "Non-Electrical Accessories") {
                                            $non_electrical = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == "Personal Accident Cover-Unnamed") {
                                            $pa_unnamed = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == 'PA for Paid Drivers Cleaners and Conductors') {
                                            $pa_paid_driver = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == 'CNG kit - OD') {
                                            $cng_od_premium = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == "CNG kit - TP") {
                                            $cng_tp_premium = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == "Geographical Extension - OD") {
                                            $geog_Extension_OD_Premium = (float)($add['AddOnCoverTotalPremium']);
                                        } elseif ($add['OptionalAddOnCoversName'] == "Geographical Extension - TP") {
                                            $geog_Extension_TP_Premium = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                    }
                                }

                                if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Discount'])) {
                                    $discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Discount'];

                                    foreach ($discount_array as $discount) {
                                        if ($discount["DiscountType"] == 'Anti-Theft Device - OD') {
                                            $antitheft = (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "Automobile Association Discount") {
                                            $automobile_discount = (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == 'Bonus Discount - OD') {
                                            $ncb_discount = (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "Detariff Discount  (Applicable on Basic OD Rate)-OD") {
                                            $other_discount += (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == 'Detariff Discount on Elecrical Accessories') {
                                            $other_discount += (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "Non-Electrical Accessories - Detariff Discount") {
                                            $other_discount += (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "Detariff Discount on CNG or LPG Kit") {
                                            $other_discount += (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "Voluntary Excess Discount") {
                                            $voluntary_excess_discount = (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "TPPD Discount") {
                                            $tppd_discount = (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == 'Limited to Own Premises - OD') {
                                            $limited_to_own_premises = (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == "Detariff Discount") {
                                            $other_discount += (float) ($discount['DiscountTypeAmount']);
                                        } elseif ($discount['DiscountType'] == 'Detariff Discount for IMT23') {
                                            $other_discount += (float) ($discount['DiscountTypeAmount']);
                                        }
                                    }
                                }

                                $final_total_discount = $ncb_discount + $antitheft + $tppd_discount + $voluntary_excess_discount + $other_discount + $limited_to_own_premises;
                                $final_od_premium = $basic_od_premium - $final_total_discount;
                                $final_tp_premium = $basic_tp_premium + $liabilities + $pa_unnamed + $pa_owner_driver + $cng_tp_premium + $pa_paid_driver + $geog_Extension_TP_Premium;
                                $final_addon_amount = $zero_dep_premium + $roadside_asst_premium + $electrical + $non_electrical + $cng_od_premium + $geog_Extension_OD_Premium + $imt_23 ;
                                $final_net_premium = round($arr_premium['OutputResult']['PremiumBreakUp']['NetPremium']);
                                $final_payable_amount = round($arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium']);
                
                                UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'od_premium' => $final_od_premium,
                                        'tp_premium' => $final_tp_premium,
                                        'ncb_discount' => $ncb_discount,
                                        'total_discount' => $final_total_discount,
                                        'addon_premium' => $final_addon_amount,
                                        'policy_start_date' => str_replace('/', '-', $policy_start_date),
                                        'policy_end_date' => str_replace('/', '-', $policy_end_date),
                                        'proposal_no' => $response['OutputResult']['ProposalNumber'],
                                        'customer_id' => $response['OutputResult']['CustomerID'],
                                        'cpa_premium' => $pa_owner_driver,
                                        'total_premium' => $final_net_premium,
                                        'service_tax_amount' => $final_payable_amount - $final_net_premium,
                                        'final_payable_amount'  => $final_payable_amount,
                                        'product_code' => '4102',
                                        'ic_vehicle_details' => json_encode($vehicleDetails)
                                    ]);                    
                
                                return response()->json([
                                    'status' => true,
                                    'msg' => $response['ServiceResult'],
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'data' => [
                                        'proposalId' => $response['OutputResult']['ProposalNumber'],
                                        'userProductJourneyId' => $enquiryId,
                                        'proposalNo' => $proposal->proposal_no,
                                        'finalPayableAmount' => $final_payable_amount,
                                        'is_breakin' => '',
                                        'inspection_number' => ''
                                    ]
                                ]);
                            } else {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'msg' => $response['ErrorText'] ?? 'Error in proposal generation service'
                                ];
                            }
                        } else {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => 'Error in proposal generation service'
                            ];
                        }
                    } else {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => $arr_premium['ErrorText'] ?? 'Error in premium calculation service'
                        ];
                    }
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Error in premium calculation service'
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => $token_data['ErrorText'] ?? 'Error occured in token generation service'
                ];
            }
        } else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Error occured in token generation service'
            ];
        }
    }
}