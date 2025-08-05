<?php

use App\Models\MagmaRtoLocation;
use App\Models\MagmaVehiclePriceMaster;
use Carbon\Carbon;
use App\Models\MasterRto;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
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
            'TXT_STATUS_FLAG' => 'A',
            'ic_version_code' => 12345
        ];
    } else {
        $mmv = get_mmv_details($productData, $requestData->version_id,'magma');

        if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
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
    }

    $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($get_mapping_mmv_details->productcode) || $get_mapping_mmv_details->productcode == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } elseif ($get_mapping_mmv_details->productcode == 'DNE') {
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

    if ($requestData->gcv_carrier_type == 'PRIVATE' && $parent_code == 'GCV') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Private Carrier is not allowed',
            'request' => [
                'message' => 'Private Carrier is not allowed',
                'carrier_type' => $requestData->gcv_carrier_type,
                'product_type' => $parent_code
            ]
        ];
    }

    if ($parent_code == 'PCV' && $get_mapping_mmv_details->seatingcapacity > 5) {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Policy issuance for vehicles having seating capacity greater than 5 is not allowed in PCV',
            'request' => [
                'message' => 'Policy issuance for vehicles having seating capacity greater than 5 is not allowed in PCV',
                'seating_capacity' => $get_mapping_mmv_details->seatingcapacity,
                'product_type' => $parent_code
            ]
        ];
    }

    $mmv_data['manf_name'] = $get_mapping_mmv_details->manufacturer;
    $mmv_data['model_name'] = $get_mapping_mmv_details->vehiclemodel;
    $mmv_data['version_name'] = $get_mapping_mmv_details->txt_variant;
    $mmv_data['seating_capacity'] = $get_mapping_mmv_details->seatingcapacity;
    $mmv_data['cubic_capacity'] = $get_mapping_mmv_details->cubiccapacity;
    $mmv_data['fuel_type'] = $get_mapping_mmv_details->txt_fuel;
    $mmv_data['gross_vehicle_weight'] = $get_mapping_mmv_details->grossvehicleweight;
    $mmv_data['vehicle_type'] = $parent_code;
    $mmv_data['version_id'] = $get_mapping_mmv_details->ic_version_code;

    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();
    //dd($rto_data);
    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO code does not exist',
            'request' => [
                'rto_data' => $requestData->rto_code,
                'message' => 'RTO code does not exist',
            ]
        ];
    }

    $rto_code = $requestData->rto_code;

    $rto_code = preg_replace("/OR/", "OD", $rto_code);

    if (str_starts_with(strtoupper($rto_code), "DL-0")) {
        $rto_code = RtoCodeWithOrWithoutZero($rto_code);
    }

    $rto_location = MagmaRtoLocation::where('rto_location_code', 'like', '%' . $rto_code . '%')->first();

    if (empty($rto_location)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request' => [
                'rto_data' => $requestData->rto_code,
                'message' => 'RTO details does not exist with insurance company',
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);

    // Re-arrange for Delhi RTO code - start 
    // $rto_code = explode('-', $rto_code);

    // if ((int)$rto_code[1] < 10) {
    //     $rto_code[1] = '0' . (int)$rto_code[1];
    // }

    // $rto_code = implode('-', $rto_code);

    $current_date = implode('', explode('-', date('Y-m-d')));

    $motor_manf_date = '01-' . $requestData->manufacture_year;
    $car_age = 0;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    if ($interval->y >= 10 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('CV_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'Zero dep is not allowed for vehicle age greater than 10 years',
            'request' => [
                'car_age' => $car_age,
                'message' => 'Zero dep is not allowed for vehicle age greater than 10 years',
            ]
        ];
    }

    $policy_expiry_date = $requestData->previous_policy_expiry_date;

    if ($requestData->business_type == 'newbusiness') {
        $businesstype      = 'New Business';
        $policy_start_date = date('d/m/Y');
        $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');
        $policy_end_date_d_m_y = date('d-m-Y', strtotime('+1 years -1 day', strtotime($policy_start_date_d_m_y)));
        $IsPreviousClaim   = '0';
        $prepolstartdate   = '01/01/1900';
        $prepolicyenddate  = '01/01/1900';
        $PolicyProductType = $tp_only ? '1TP' : '1TP1OD';
        $proposal_date     = $policy_start_date;
        $time = date('H:i', time());
    } else {
        $businesstype      = 'Roll Over';
        $PolicyProductType = $tp_only ? '1TP' : '1TP1OD';
        $policy_start_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
        $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');

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

        $policy_end_date_d_m_y = date('d-m-Y', strtotime($policy_start_date_d_m_y . ' - 1 days + 1 year'));
        $proposal_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));
        $IsPreviousClaim    = $requestData->is_claim == 'N' ? 1 : 0;
        $prepolstartdate    = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($policy_expiry_date)))));
        $prepolicyenddate   = date('d/m/Y', strtotime($policy_expiry_date));
        $time = '00:00';
    }

    $policy_end_date     = date('d/m/Y', strtotime($policy_end_date_d_m_y));
    $prev_policy_end_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
    $manufacturingyear   = date("Y", strtotime($requestData->manufacture_year));
    $first_reg_date      = date('d/m/Y', strtotime($requestData->vehicle_register_date));
    $vehicle_idv         = 0;
    $vehicle_in_90_days  = 0;

    if (isset($term_start_date)) {
        $vehicle_in_90_days = (strtotime(date('Y-m-d')) - strtotime($term_start_date)) / (60 * 60 * 24);

        if ($vehicle_in_90_days > 90) {
            $requestData->ncb_percentage = 0;
        }
    }

    $selected_addons = DB::table('selected_addons')
        ->where('user_product_journey_id', $enquiryId)
        ->first();

    // token Generation
    $tokenParam = [
        'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
        'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
        'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
        'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME')
    ];
    // $token = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_CV_GETTOKEN'), http_build_query($tokenParam), 'magma', [
    //     'method' => 'Token Generation',
    //     'requestMethod' => 'post',
    //     'type' => 'tokenGeneration',
    //     'section' => $productData->product_sub_type_code,
    //     'enquiryId' => $enquiryId,
    //     'productName' => $productData->product_name,
    //     'transaction_type' => 'quote'
    // ]);
    $token = cache()->remember('constants.IcConstants.magma.END_POINT_URL_MAGMA_CV_GETTOKEN', 60 * 45, function () use ($tokenParam, $enquiryId, $productData) {
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
            'transaction_type' => 'quote'
        ]);
        return $get_response['response'];
    });

    if ($token) {
        $token_data = json_decode($token, true);

        if (isset($token_data['access_token'])) {
            $vehicle_price = MagmaVehiclePriceMaster::where('vehicleclasscode', $get_mapping_mmv_details->vehicleclasscode)
                ->where('vehiclemodelcode', $get_mapping_mmv_details->vehiclemodelcode)
                ->first();

            $model_config_premium = [
                'BusinessType' => $businesstype,
                'PolicyProductType' => $PolicyProductType,
                'ProposalDate' => $proposal_date,
                'VehicleDetails' => [
                    // 'VehicleOwnerShip' => 'NEW',
                    'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'TempRegistrationDate' => '',
                    'RegistrationNumber' => strtoupper($requestData->business_type == 'newbusiness' ? 'NEW' : explode('-', $rto_code)[0] . '-' . explode('-', $rto_code)[1] . '-ZZ-0003'),
                    'ChassisNumber' => 'ASDFFGHJJNHJTY654',
                    'EngineNumber' => 'ERWEWFWEF',
                    'RTOCode' => $rto_code,
                    'RTOName' => $rto_location->rto_location_description,
                    'ManufactureCode' => $get_mapping_mmv_details->manufacturercode,
                    'ManufactureName' => $get_mapping_mmv_details->manufacturer,
                    'ModelCode' => $get_mapping_mmv_details->vehiclemodelcode,
                    'ModelName' => $get_mapping_mmv_details->vehiclemodel,
                    'HPCC' => $get_mapping_mmv_details->cubiccapacity,
                    'MonthOfManufacture' => date('m', strtotime($requestData->vehicle_register_date)),
                    'YearOfManufacture' => date('Y', strtotime($requestData->vehicle_register_date)),
                    'VehicleClassCode' => $get_mapping_mmv_details->vehicleclasscode,
                    'VehicleClassName' => $get_mapping_mmv_details->txt_vehicle_class_short_desc,
                    'SeatingCapacity' => $get_mapping_mmv_details->seatingcapacity,
                    'CarryingCapacity' => $get_mapping_mmv_details->carryingcapacity,
                    'BodyTypeCode' => $get_mapping_mmv_details->bodytypecode,
                    'BodyTypeName' => $get_mapping_mmv_details->vehiclebodytypedescription,
                    'FuelType' => $get_mapping_mmv_details->txt_fuel,
                    'SeagmentType' => $get_mapping_mmv_details->txt_segmenttype,
                    'GVW' => $get_mapping_mmv_details->grossvehicleweight,
                    'TACMakeCode' => $get_mapping_mmv_details->txt_tacmakecode,
                    'ExShowroomPrice' => $vehicle_price->vehiclesellingprice ?? '',
                    'IDVofVehicle' => '',
                    'HigherIDV' => '',
                    'LowerIDV' => '',
                    'IDVofChassis' => '',
                    'Zone' => 'Zone-' . $rto_location->registration_zone,
                    'IHoldValidPUC' => true,
                    //'InsuredHoldsValidPUC' => false,
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
                'AddOnsPlanApplicable' => !$tp_only ? true : false,
                'AddOnsPlanApplicableDetails' => !$tp_only ? [
                    'PlanName' => 'Optional Add on',
                    'RoadSideAssistance' => $interval->y < 5 ?  true : false,
                    'ZeroDepreciation' => $productData->zero_dep == 0 && $interval->y < 5 ?  true : false,
                    // 'Consumables' => true
                ] : null,
                'OptionalCoverageApplicable' => false,
                'OptionalCoverageDetails' => NULL,
                'IsPrevPolicyApplicable' => $requestData->business_type != 'newbusiness' ? true : false,
                'PrevPolicyDetails' => $requestData->business_type == 'newbusiness' ? NULL : [
                    'PrevNCBPercentage' => $requestData->previous_ncb,
                    'PrevInsurerCompanyCode' => 'CMGI',
                    'HavingClaiminPrevPolicy' => $requestData->is_claim == 'N' ? false : true,
                    'PrevPolicyEffectiveFromDate' => $prepolstartdate,
                    'PrevPolicyEffectiveToDate' => $prepolicyenddate,
                    'PrevPolicyNumber' => '123456',
                    'PrevPolicyType' => $premium_type == 'own_damage' ? 'Standalone OD' : ($tp_only ? 'LiabilityOnly' : 'PackagePolicy'),
                    'PrevAddOnAvialable' => $productData->zero_dep == 0 ? true : false,
                    'PrevPolicyTenure' => '1',
                    'IIBStatus' => 'Not Applicable',
                    'PrevInsuranceAddress' => 'ARJUN NAGAR',
                ],
                'CompulsoryExcessAmount' => '1000',
                'VoluntaryExcessAmount' => '',
                'ImposedExcessAmount' => '',
                //'IsPrevPolicyApplicable' => false
            ];

            // if ($premium_type == 'own_damage')
            // {
            //     $model_config_premium['IsTPPolicyApplicable'] = true;
            //     $model_config_premium['PrevTPPolicyDetails'] = [
            //         'PolicyNumber'      => '234589876',
            //         'PolicyType'        => 'LiabilityOnly',
            //         'InsurerName'       => 'BAJAJ',
            //         'TPPolicyStartDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
            //         'TPPolicyEndDate'   => date('d/m/Y', strtotime('+3 year -1 day', strtotime($requestData->vehicle_register_date)))
            //     ];
            // }

            if ($requestData->business_type == 'newbusiness') {
                unset($model_config_premium['VehicleDetails']['VehicleOwnerShip']);
            }

            if ($requestData->vehicle_owner_type == 'I') {
                $model_config_premium['PAOwnerCoverApplicable'] = true;
                $model_config_premium['PAOwnerCoverDetails'] = [
                    'PAOwnerSI'                => '1500000',
                    'PAOwnerTenure'            => '1',
                    'ValidDrvLicense'          => true,
                    'DoNotHoldValidDrvLicense' => false,
                    'Ownmultiplevehicles'      => false,
                    'ExistingPACover'          => false
                ];
            }

            $selected_addons = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->first();

            if (!$tp_only && ! $model_config_premium['AddOnsPlanApplicableDetails']['RoadSideAssistance'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['ZeroDepreciation']) {
                $model_config_premium['AddOnsPlanApplicable'] = false;
                $model_config_premium['AddOnsPlanApplicableDetails'] = NULL;
            }


            if ($interval->y >= 5) {
                $model_config_premium['AddOnsPlanApplicable'] = false;
                $model_config_premium['AddOnsPlanApplicableDetails'] = NULL;
            }

            $is_bifuel_kit = false;

            if ($selected_addons['accessories'] && $selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '') {
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['accessories'] as $accessory) {
                    if ($accessory['name'] == 'Electrical Accessories') {
                        $model_config_premium['OptionalCoverageDetails']['ElectricalApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['ElectricalDetails'] = [
                            [
                                'Description' => 'Head Light',
                                'ElectricalSI' => (string) $accessory['sumInsured'],
                                'SerialNumber' => '2',
                                'YearofManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year))
                            ],
                        ];
                    }

                    if ($accessory['name'] == 'Non-Electrical Accessories') {
                        $model_config_premium['OptionalCoverageDetails']['NonElectricalApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['NonElectricalDetails'] = [
                            [
                                'Description' => 'Head Light',
                                'NonElectricalSI' => (string) $accessory['sumInsured'],
                                'SerialNumber' => '2',
                                'YearofManufacture' => date('Y', strtotime('01-' . $requestData->manufacture_year))
                            ],
                        ];
                    }

                    if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $is_bifuel_kit = true;
                        $model_config_premium['OptionalCoverageDetails']['ExternalCNGkitApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['ExternalCNGLPGkitDetails'] = [
                            'CngLpgSI' => (string) $accessory['sumInsured']
                        ];
                    }
                }
            }

            if ($selected_addons['additional_covers'] && $selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '') {
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['additional_covers'] as $additional_cover) {
                    if (in_array($additional_cover['name'], ['LL paid driver', 'LL paid driver/conductor/cleaner'])) {
                        $model_config_premium['OptionalCoverageDetails']['LLPaidDriverCleanerApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['LLPaidDriverCleanerDetails'] = [
                            'NoofPerson' => $parent_code == 'PCV' ? 1 : $additional_cover['LLNumberDriver'] + $additional_cover['LLNumberConductor'] + $additional_cover['LLNumberCleaner']
                        ];
                    }

                    if ($additional_cover['name'] == 'Unnamed Passenger PA Cover') {
                        $model_config_premium['OptionalCoverageDetails']['UnnamedPACoverApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['UnnamedPACoverDetails'] = [
                            'NoOfPerunnamed' => $get_mapping_mmv_details->seating_capacity,
                            'UnnamedPASI' => $additional_cover['sumInsured'],
                        ];
                    }

                    if (in_array($additional_cover['name'], ['PA cover for additional paid driver', 'PA paid driver/conductor/cleaner'])) {
                        $model_config_premium['OptionalCoverageDetails']['PAPaidDriverApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['PAPaidDriverDetails'] = [
                            'NoofPADriver' => $parent_code == 'PCV' ? 1 : $get_mapping_mmv_details->seatingcapacity,
                            'PAPaiddrvSI' => $additional_cover['sumInsured'],
                        ];
                    }

                    if ($additional_cover['name'] == 'Geographical Extension') {
                        $model_config_premium['OptionalCoverageDetails']['GeographicalExtensionApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['GeographicalExtensionDetails'] = [
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
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['discounts'] as $discount) {
                    if ($discount['name'] == 'anti-theft device') {
                        $model_config_premium['OptionalCoverageDetails']['ApprovedAntiTheftDevice'] = true;
                        $model_config_premium['OptionalCoverageDetails']['CertifiedbyARAI'] = true;
                    }

                    if ($discount['name'] == 'voluntary_insurer_discounts') {
                        $model_config_premium['VoluntaryExcessAmount'] = $discount['sumInsured'];
                    }

                    if ($discount['name'] == 'TPPD Cover') {
                        $model_config_premium['OptionalCoverageDetails']['TPPDLimitApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['TPPDLimitDetails'] = [
                            'TPPDLimitAmount' => 6000
                        ];
                    }

                    if ($discount['name'] == 'Vehicle Limited to Own Premises') {
                        $model_config_premium['OptionalCoverageDetails']['UseofLimitedPermission'] = true;
                    }
                }
            }

            if ( ! isset($model_config_premium['OptionalCoverageDetails']['TPPDLimitApplicable']) && $tp_only) {
                $model_config_premium['OptionalCoverageApplicable'] = true;
                $model_config_premium['OptionalCoverageDetails']['TPPDLimitApplicable'] = false;
            }

            if (!$tp_only) {
                if ($parent_code == 'GCV') {
                    $model_config_premium['OptionalCoverageApplicable'] = true;
                    $model_config_premium['OptionalCoverageDetails']['IMT23Applicable'] = true;
                    $model_config_premium['OptionalCoverageDetails']['IMT23ApplicableDetails'] = [
                        'IMT23InPreviousPolicy' => false
                    ];
                }
            }

            $premium_calculation_url = '';

            if ($parent_code == 'PCV') {
                $premium_calculation_url = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_PCV_GETPREMIUM');
            } else {
                $premium_calculation_url = config('constants.IcConstants.magma.END_POINT_URL_MAGMA_GCV_GETPREMIUM');
            }

            $isagentDiscountAllowed =  false;
            if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
                $agentDiscount = calculateAgentDiscount($enquiryId, 'magma', strtolower($parent_code));
                if ($agentDiscount['status'] ?? false) {
                    $isagentDiscountAllowed =  true;
                    $model_config_premium['GeneralProposalInformation']['DetariffDis'] = $agentDiscount['discount'];
                } else {
                    if (!empty($agentDiscount['message'] ?? '')) {
                        return [
                            'status' => false,
                            'message' => $agentDiscount['message']
                        ];
                    }
                }
            }

            $get_response = getWsData($premium_calculation_url, $model_config_premium, 'magma', [
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
                'transaction_type' => 'quote'
            ]);

            $premium_data = $get_response['response'];
            if ($premium_data) {
                $arr_premium = json_decode($premium_data, true);
                $skip_second_call = false;
                if (isset($arr_premium['ServiceResult']) && $arr_premium['ServiceResult'] == "Success") {
                    $max_idv = $arr_premium['OutputResult']['HigherIDV'];
                    $min_idv = $arr_premium['OutputResult']['LowerIDV'];
                    $vehicle_idv = $arr_premium['OutputResult']['IDVofthevehicle'];

                    if (!$tp_only) {
                        if ($requestData->is_idv_changed == 'Y') {
                            if ($requestData->edit_idv >= floor($max_idv)) {
                                $model_config_premium['VehicleDetails']['IDVofVehicle'] = floor($max_idv);
                            } elseif ($requestData->edit_idv <= ceil($min_idv)) {
                                $model_config_premium['VehicleDetails']['IDVofVehicle'] = ceil($min_idv);
                            } else {
                                $model_config_premium['VehicleDetails']['IDVofVehicle'] = $requestData->edit_idv;
                            }
                        } else {
                            #$model_config_premium['VehicleDetails']['IDVofVehicle'] = $min_idv;
                            $getIdvSetting = getCommonConfig('idv_settings');
                            switch ($getIdvSetting) {
                                case 'default':
                                    $model_config_premium['VehicleDetails']['IDVofVehicle'] = $vehicle_idv;
                                    $skip_second_call = true;
                                    break;
                                case 'min_idv':
                                    $model_config_premium['VehicleDetails']['IDVofVehicle'] = $min_idv;
                                    break;
                                case 'max_idv':
                                    $model_config_premium['VehicleDetails']['IDVofVehicle'] = $max_idv;
                                    break;
                                default:
                                    $model_config_premium['VehicleDetails']['IDVofVehicle'] = $min_idv;
                                    break;
                            }
                        }
                        if(!$skip_second_call){
                        $get_response = getWsData($premium_calculation_url, $model_config_premium, 'magma', [
                            'section'          => $productData->product_sub_type_code,
                            'method'           => 'Premium Recalculation',
                            'requestMethod'    => 'post',
                            'type'             => 'premiumCalculation',
                            'headers'          => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Bearer ' . $token_data['access_token']
                            ],
                            'enquiryId'        => $enquiryId,
                            'productName'      => $productData->product_name,
                            'transaction_type' => 'quote'
                        ]);
                    }
                        $premium_data = $get_response['response'];
                        if ($premium_data) {
                            $arr_premium = json_decode($premium_data, true);

                            if (!isset($arr_premium['ServiceResult']) || $arr_premium['ServiceResult'] != "Success") {
                                $data_response = [
                                    'status' => false,
                                    'msg' => isset($arr_premium['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $arr_premium['ErrorText']) : 'Error occured in premium re-calculation service'
                                ];
                            }
                        }
                    }

                    $basic_tp_premium = $basic_od_premium = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $cng_od_premium = $cng_tp_premium = $antitheft = $tppd_discount = $voluntary_excess_discount = $roadside_asst_premium = $zero_dep_premium = $other_discount = $geog_Extension_OD_Premium = $geog_Extension_TP_Premium = $imt_23 = $limited_to_own_premises = $imt_23_discount = 0;

                    $add_array = $arr_premium['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

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
                                $imt_23_discount = (float) ($discount['DiscountTypeAmount']);
                            }
                        }
                    }

                    $ribbonMessage = null;

                    if (($model_config_premium['GeneralProposalInformation']['DetariffDis'] ?? false) && $isagentDiscountAllowed) {
                        $agentDiscountPercentage = $arr_premium['OutputResult']['AppliedDiscount'];
                        if ($model_config_premium['GeneralProposalInformation']['DetariffDis'] != $agentDiscountPercentage) {
                            $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount') . ' ' . $agentDiscountPercentage . '%';
                        }
                    }

                    $final_tp_premium = $basic_tp_premium + $liabilities + $cng_tp_premium + $pa_paid_driver + $pa_unnamed + $geog_Extension_TP_Premium;
                    $total_own_damage = $basic_od_premium;
                    $final_od_premium = $total_own_damage + $electrical + $non_electrical + $cng_od_premium + $geog_Extension_OD_Premium - $limited_to_own_premises;
                    $total_discount = $antitheft + $voluntary_excess_discount + $other_discount + $tppd_discount;
                    $ncb_discount = ($final_od_premium - $total_discount + $tppd_discount) * $requestData->applicable_ncb / 100; //Excluded tppd discount from NCB calculation
                    $final_total_discount = $total_discount + $ncb_discount;
                    $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
                    $final_gst_amount = round($final_net_premium * 0.18);
                    $final_payable_amount = $final_net_premium + $final_gst_amount;
                    $applicable_addons = [];
                    $addons_data = [
                        'in_built' => [],
                        'additional' => [],
                        'other_premium' => []
                    ];

                    if (!$tp_only) {
                        $addons_data = [
                            'in_built' => [
                                'zero_depreciation' => (float) $zero_dep_premium,
                            ],
                            'additional' => [
                                'road_side_assistance' => (float) $roadside_asst_premium,
                                'imt_23' => (float) $imt_23
                            ],
                            'other' => [],
                        ];

                        $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                        $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                        $addons_data['other_premium'] = 0;

                        $applicable_addons = ['imt23', 'zeroDepreciation', 'roadSideAssistance'];

                        if ($interval->y >= 5) {
                            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['road_side_assistance'] == 0) {
                            array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                        }

                        if ($imt_23 = 0) {
                            array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                        }
                    }

                    $business_types = [
                        'newbusiness' => 'New Business',
                        'rollover' => 'Rollover',
                        'breakin' => 'Breakin'
                    ];

                    $data_response = [
                        'status' => true,
                        'msg' => 'Found',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'Data' => [
                            'idv'           => round($arr_premium['OutputResult']['IDVofthevehicle']),
                            'min_idv'       => ceil($min_idv),
                            'max_idv'       => floor($max_idv),
                            'exshowroomprice' => '',
                            'qdata'         => NULL,
                            'pp_enddate'    => $prev_policy_end_date,
                            'addonCover'    => NULL,
                            'addon_cover_data_get' => '',
                            'rto_decline' => NULL,
                            'rto_decline_number' => NULL,
                            'mmv_decline' => NULL,
                            'mmv_decline_name' => NULL,
                            'policy_type' => $tp_only ? 'Third Party' : 'Comprehensive',
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => $vehicle_idv,
                            'vehicle_registration_no' => $requestData->rto_code,
                            'voluntary_excess' => $voluntary_excess_discount,
                            'tppd_discount' => (float) $tppd_discount,
                            'other_discount' => (float) $other_discount,
                            'version_id' => $get_mapping_mmv_details->ic_version_code,
                            'selected_addon' => [],
                            'showroom_price' => $vehicle_idv,
                            'fuel_type' => $requestData->fuel_type,
                            'vehicle_idv' => $vehicle_idv,
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_name' => $productData->product_sub_type_name,
                            'mmv_detail' => $mmv_data,
                            'master_policy_id' => [
                                'policy_id' => $productData->policy_id,
                                'policy_no' => $productData->policy_no,
                                'policy_start_date' => $policy_start_date_d_m_y,
                                'policy_end_date' => $policy_end_date_d_m_y,
                                'sum_insured' => $productData->sum_insured,
                                'corp_client_id' => $productData->corp_client_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'insurance_company_id' => $productData->company_id,
                                'status' => $productData->status,
                                'corp_name' => '',
                                'company_name' => $productData->company_name,
                                'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                                'product_sub_type_name' => $productData->product_sub_type_name,
                                'flat_discount' => $productData->default_discount,
                                'predefine_series' => "",
                                'is_premium_online' => $productData->is_premium_online,
                                'is_proposal_online' => $productData->is_proposal_online,
                                'is_payment_online' => $productData->is_payment_online
                            ],
                            'motor_manf_date' => $requestData->vehicle_register_date,
                            'vehicle_register_date' => $requestData->vehicle_register_date,
                            'ic_vehicle_discount' => $other_discount,
                            'imt_23_discount' => $imt_23_discount,
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
                                'car_age' => $car_age,
                                'ic_vehicle_discount' => (float) $other_discount
                            ],
                            'basic_premium' => (float) $basic_od_premium,
                            'deduction_of_ncb' => (float) $ncb_discount,
                            'tppd_premium_amount' => (float) $basic_tp_premium,
                            'motor_electric_accessories_value' => (float) $electrical,
                            'motor_non_electric_accessories_value' => (float) $non_electrical,
                            'cover_unnamed_passenger_value' => (float) $pa_unnamed,
                            'seating_capacity' => $get_mapping_mmv_details->seatingcapacity,
                            'default_paid_driver' => (float) $liabilities,
                            'll_paid_driver_premium' => 0,//(float) $liabilities, // commented values coming in addion git id 5878
                            'll_paid_conductor_premium' => 0,
                            'll_paid_cleaner_premium' => 0,
                            'motor_additional_paid_driver' => (float) $pa_paid_driver,
                            'GeogExtension_ODPremium' => (float) $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium' => (float) $geog_Extension_TP_Premium,
                            'compulsory_pa_own_driver' => (float) $pa_owner_driver,
                            'total_accessories_amount(net_od_premium)' => '',
                            'total_own_damage' => '',
                            'total_liability_premium' => '',
                            'limitedtoOwnPremisesOD' => (float) $limited_to_own_premises,
                            'net_premium' => (float) $arr_premium['OutputResult']['PremiumBreakUp']['NetPremium'],
                            'service_tax_amount' => (float) ($arr_premium['OutputResult']['PremiumBreakUp']['SGST'] + $arr_premium['OutputResult']['PremiumBreakUp']['CGST']),
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'quotation_no' => '',
                            'premium_amount' => (float) $arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium'],
                            'antitheft_discount' => (float) $antitheft,
                            'final_od_premium' => (float) $final_od_premium,
                            'final_tp_premium' => (float) $final_tp_premium,
                            'final_total_discount' => (float) $final_total_discount,
                            'final_net_premium' => (float) $final_net_premium,
                            'final_gst_amount' => (float) $final_gst_amount,
                            'final_payable_amount' =>  (float) $final_payable_amount,
                            'service_data_responseerr_msg' => '',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => $business_types[$requestData->business_type],
                            'policyStartDate' => $policy_start_date_d_m_y,
                            'policyEndDate' => $policy_end_date_d_m_y,
                            'ic_of' => $productData->company_id,
                            'vehicle_in_90_days' => $vehicle_in_90_days,
                            'get_policy_expiry_date' => NULL,
                            'get_changed_discount_quoteid' => 0,
                            'vehicle_discount_detail' => [
                                'discount_id' => NULL,
                                'discount_rate' => NULL
                            ],
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online,
                            'policy_id' => $productData->policy_id,
                            'insurane_company_id' => $productData->company_id,
                            "max_addons_selection" => NULL,
                            'add_ons_data' => $addons_data,
                            'applicable_addons' => $applicable_addons,
                            'ribbon' =>  $ribbonMessage
                        ]
                    ];

                    if ($zero_dep_premium == 0)
                    {
                        unset($data_response['Data']['add_ons_data']['in_built']['zero_depreciation']);
                    }

                    if ($tp_only == 'true') {
                        $data_response['Data']['add_ons_data'] = [
                            'in_built'   => [],
                            'additional' => [
                                'zero_depreciation' => 0,
                                'road_side_assistance' => 0
                            ]
                        ];
                    }

                    if ($is_bifuel_kit) {
                        $data_response['Data']['motor_lpg_cng_kit_value'] = (float) $cng_od_premium;
                        $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                        $data_response['Data']['cng_lpg_tp'] = (float) $cng_tp_premium;
                    }
                } else {
                    $data_response = array(
                        'premium_amount' => 0,
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => isset($arr_premium['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $arr_premium['ErrorText']) : 'Error occured in premium calculation service'
                    );
                }
            } else {
                $data_response = [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Error occured in premium calculation service'
                ];
            }
        } else {
            $data_response = array(
                'premium_amount' => 0,
                'status' => false,
                'msg' => isset($token_data['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_data['ErrorText']) : 'Error occured in token generation service'
            );
        }
    } else {
        $data_response = array(
            'premium_amount' => 0,
            'status' => false,
            'msg' => 'Error occured in token generation service'
        );
    }

    return camelCase($data_response);
}
