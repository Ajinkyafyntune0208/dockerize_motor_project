<?php

use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Http\Controllers\Proposal\Services\RelianceMiscdSubmitProposal;
use App\Quotes\Cv\V1\reliance as RELIANCE_V1;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/reliance_Miscd.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $parent_id = get_parent_code($productData->product_sub_type_id);
    $refer_webservice = $productData->db_config['quote_db_cache'];
    if (config('IC.RELIANCE.V1.CV.ENABLE') == 'Y') {
        return RELIANCE_V1::getQuote($enquiryId, $requestData, $productData);
    } else if (get_parent_code($productData->product_sub_type_id) == 'MISC') {
        return getMiscQuote($enquiryId, $requestData, $productData);
    } else {
        $mmv = get_mmv_details($productData, $requestData->version_id, 'reliance', $parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL);
        if (empty($mmv)) {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'mmv details not found',
                'request' => [
                    'mmv' => $mmv,
                ]
            ];
        }
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv,
                ]
            ];
        }
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv_data,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv_data,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ];
    }
    $vehicle_invoice_date = new DateTime($requestData->vehicle_invoice_date);
    $registration_date = new DateTime($requestData->vehicle_register_date);

    $date1 = !empty($requestData->vehicle_invoice_date) ? $vehicle_invoice_date : $registration_date;

    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    // if ($interval->y >= 15) {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Vehicle age should not be greater than 15 year',
    //         'request' => [
    //             'message' => 'Vehicle age should not be greater than 15 year',
    //             'car_age' => $car_age
    //         ]
    //     ];
    // }

    // $rto_code = $requestData->rto_code;
    // $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);

    // $rto_data = DB::table('reliance_rto_master as rm')
    //     ->where('rm.region_code',$rto_code)
    //     ->select('rm.*')
    //     ->first();
    $NCB_ID = [
        '0'      => '0',
        '20'     => '1',
        '25'     => '2',
        '35'     => '3',
        '45'     => '4',
        '50'     => '5'
    ];

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

    // if ($requestData->ownership_changed == 'Y') {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //         ]
    //     ];
    // }



    if ($requestData->business_type == 'newbusiness') {
        $BusinessType = '1';
        $ISNewVehicle = 'true';
        $Registration_Number = 'NEW';
        $NCBEligibilityCriteria = '1';
        $PreviousNCB = '0';
        // $policy_start_date = date('Y-m-d');
        $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
    } else {
        // $BusinessType = $requestData->previous_policy_type == 'Not sure' ? '6' : '5';//6 means ownership change
        $BusinessType = '5';
        $ISNewVehicle = 'false';
        // if ($requestData->vehicle_registration_no != '') {
        //     $Registration_Number = getRegisterNumberWithHyphen($requestData->vehicle_registration_no);
        // } else {
        //     $reg_no_3 = chr(rand(65, 90));
        //     // $Registration_Number = $rto_code . '-' . $reg_no_3 . $reg_no_3 . '-1234';
        // }

        $date_difference = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);

        $NCBEligibilityCriteria = $requestData->previous_policy_type == 'Third-party' || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New' || ($requestData->is_claim == 'Y') ? '1' : '2';

        $PreviousNCB = $NCB_ID[$requestData->previous_ncb] ?? '0';

        if ($requestData->business_type == 'rollover') {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        } else {
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
        }
    }

    $rto_code = $requestData->rto_code;
    $registration_number = $requestData->vehicle_registration_no;
    $reg_no_3 = chr(rand(65, 90));

    $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
        $registration_number,
        $rto_code,
        $requestData->business_type == 'newbusiness',
        [
            'appendRegNumber' => "-$reg_no_3-1234"
        ]
    );

    if (!$rcDetails['status']) {
        return $rcDetails;
    }

    $Registration_Number = $rcDetails['rcNumber'];
    $registration_no = explode('-', $registration_number);
    if(count($registration_no) === 3)
    {
        $Registration_Number = \App\Helpers\VehicleRegistrationNumberFormatHelper::formatRegistrationNumber($Registration_Number);
    }
    $rto_data = $rcDetails['rtoData'];

    if ($parent_id == 'GCV' && $requestData->business_type == 'breakin') {
        if ($mmv_data->gross_weight < 3500) {
            $policy_start_date = date('Y-m-d', strtotime('+2 day'));
        } else {
            $policy_start_date = date('Y-m-d', strtotime('+1 day'));
        }
    }

    if (in_array($premium_type, ['short_term_3', 'short_term_3_breakin'])) {
        $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : $NCBEligibilityCriteria;
        $policy_end_date = date('Y-m-d', strtotime('-1 days + 3 months', strtotime($policy_start_date)));
    } elseif (in_array($premium_type, ['short_term_6', 'short_term_6_breakin'])) {
        $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : $NCBEligibilityCriteria;
        $policy_end_date = date('Y-m-d', strtotime('-1 days + 6 months', strtotime($policy_start_date)));
    } else {
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }

    $vehicledate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $DateOfPurchase = date('d-m-Y', strtotime($vehicledate));
    $vehicle_register_date = date('d-m-Y', strtotime($requestData->vehicle_register_date));
    $vehicle_manufacture_date = explode('-', '01-' . $requestData->manufacture_year);
    $vehicle_in_90_days = 'N';

    if ($productData->product_sub_type_code == 'TAXI') {
        if ($mmv_data->carrying_capacity <= 6) {
            $productCode = $tp_only == 'true' ? '2353' : '2338';
        } else {
            $productCode = $tp_only == 'true' ? '2355' : '2340';
        }
    } elseif ($productData->product_sub_type_code == 'AUTO-RICKSHAW' || $productData->product_sub_type_code == 'ELECTRIC-RICKSHAW') {
        $productCode = $tp_only == 'true' ? '2354' : '2339';
    } elseif ($productData->product_sub_type_code == 'MISCELLANEOUS-CLASS') {
        $productCode = $tp_only == 'true' ? '2358' : '2343';
    } else {
        if ($requestData->gcv_carrier_type == 'PUBLIC') {
            if ($mmv_data->wheels > 3) {
                $productCode = $tp_only == 'true' ? '2349' : '2334';
            } elseif ($mmv_data->wheels == 3) {
                $productCode = $tp_only == 'true' ? '2351' : '2336';
            }
        } else {
            if ($mmv_data->wheels > 3) {
                $productCode = $tp_only == 'true' ? '2350' : '2335';
            } elseif ($mmv_data->wheels == 3) {
                $productCode = $tp_only == 'true' ? '2352' : '2337';
            }
        }
    }

    $gcv_vehicle_sub_classes = [
        'TRUCK' => 1,
        'DUMPER/TIPPER' => 2,
        'TANKER/BULKER' => 4,
        'PICK UP/DELIVERY/REFRIGERATED VAN' => 5,
        'TRACTOR' => 9
    ];

    $misc_vehicles_sub_classes = [
        'AGRICULTURAL TRACTORS' => 48,
        'AGRICULTURE' => 47,
        'AMBULANCES' => 9,
        'ANGLE DOZERS' => 52,
        'ANTI MALARIAL VANS' => 53,
        'BREAKDOWN VEHICLES' => 54,
        'BULLDOZERS, BULLGRADERS' => 55,
        'CASH VAN' => 86,
        'CEMENT BULKER' => 41,
        'CINEMA FILM RECORDING AND PUBLICITY VANS' => 36,
        'CINEMA FILM RECORDING AND PUBLICITY VANS' => 18,
        'COMPRESSORS' => 49,
        'CPM' => 89,
        'CRANES' => 25,
        'CRANES' => 15,
        'DELIVERY TRUCKS PEDESTRAIN CONTROLLED' => 56,
        'DISPENSARIES' => 57,
        'DRAGLINE EXCAVATORS' => 58,
        'DRILLING RIGS' => 34,
        'DUMPERS' => 1,
        'DUST CARTS WATER CARTS ROAD SWEEPER AND TOWER WAGONS' => 81,
        'ELECTRIC DRIVEN GOODS VEHICLES' => 59,
        'ELECTRIC TROLLEYS OR TRACTORS' => 26,
        'EXCAVATORS' => 21,
        'FIRE BRIGADE AND SALVAGE CORPS VEHICLE' => 60,
        'FIRE FIGHTER' => 40,
        'FOOTPATH ROLLERS' => 61,
        'FORK LIFT TRUCKS' => 22,
        'GARBAGE VAN' => 37,
        'GRABS' => 62,
        'GRITTING MACHINES' => 63,
        'HARVESTER' => 32,
        'HEARSES' => 20,
        'HORSE BOXES' => 64,
        'LADDER CARRIER CARTS' => 65,
        'LAWN MOWERS' => 66,
        'LETOURNA DOZERS' => 67,
        'LEVELLERS' => 68,
        'MECHANICAL NAVVIES, SHOVELS, GRABS AND EXCAVATORS' => 69,
        'MILITARY TEA VANS' => 70,
        'MILK VANS (INSULATED)' => 16,
        'MOBILE CONCRETE MIXER' => 50,
        'MOBILE PLANT' => 31,
        'MOBILE SHOPS AND CANTEENS' => 71,
        'MOBILE SURGERIES AND DISPENSARIES' => 51,
        'OIL FILTERATION MACHINE' => 46,
        'OTHERS' => 90,
        'PLANE LOADERS AND OTHER VEHICLES' => 28,
        'PLANE LOADERS AND OTHER VEHICLES' => 44,
        'POWER TILLER' => 43,
        'PRE-MIX LAYING EQUIPMENT' => 23,
        'PRISON VANS' => 72,
        'RECOVERY VAN' => 42,
        'REFRIGERATION/PRE-COOLING UNIT' => 35,
        'RIPPERS' => 82,
        'ROAD ROLLERS' => 27,
        'ROAD SCRAPPING, SURFACING AND PRE-MIX LAYING EQUIPMENT' => 83,
        'ROAD SPRINKLERS USED ALSO AS FIRE FIGHTING VEHICLES' => 84,
        'ROAD SWEEPERS' => 24,
        'SCIENTIFIC VANS' => 73,
        'SCRAPERS' => 74,
        'SELF PROPELLED COMBINED HARVESTORS' => 29,
        'SHEEP FOOT TAMPING ROLLER' => 75,
        'SHOVELS' => 76,
        'SITE CLEARING AND LEVELLING PLANT' => 77,
        'SPRAYING PLANT' => 78,
        'TANKER' => 2,
        'TAR SPRAYERS (SELF PROPELLED)' => 79,
        'TIPPERS' => 3,
        'TOWER WAGONS' => 85,
        'TRACTORS' => 19,
        'TRAILER' => 4,
        'TRANSIT MIXER' => 45,
        'TRIAL BUILDERS, TREE DOZERS' => 80,
        'TROLLEYS AND GOODS CARRYING TRACTORS' => 17,
        'TYRE HANDLER' => 39,
        'VIBRATORY SOIL COMPACTOR' => 38,
        'WATER CARTS' => 30,
        'WATER SPRINKLER' => 33
    ];

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
    $selected_addons = DB::table('selected_addons')
        ->where('user_product_journey_id', $enquiryId)
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
    $LiabilityToPaidDriver_NoOfItems = '0';
    $IsLiabilityToPaidCleanerCovered = 'false';
    $LiabilityToPaidCleaner_IsChecked = 'false';
    $LiabilityToPaidCleaner_NoOfItems = '0';
    $is_geo_ext = false;
    if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
        $additional_covers = json_decode($selected_addons->additional_covers);

        foreach ($additional_covers as $value) {
            if ($value->name == 'PA cover for additional paid driver' || $value->name == 'PA paid driver/conductor/cleaner') {
                $IsPAToDriverCovered = 'true';
                $PAToPaidDriver_IsChecked = 'true';
                $PAToPaidDriver_NoOfItems = '1';
                $PAToPaidDriver_SumInsured = $value->sumInsured;
            }

            if ($value->name == 'Unnamed Passenger PA Cover') {
                $IsPAToUnnamedPassengerCovered = 'true';
                $PAToUnNamedPassenger_IsChecked = 'true';
                $PAToUnNamedPassenger_NoOfItems = '1';
                $PAToUnNamedPassengerSI = $value->sumInsured;
            }

            if ($value->name == 'LL paid driver') {
                $IsLiabilityToPaidDriverCovered = 'true';
                $LiabilityToPaidDriver_IsChecked = 'true';
                $LiabilityToPaidDriver_NoOfItems = 1;
            }

            if ($value->name == 'LL paid driver/conductor/cleaner') {
                $IsLiabilityToPaidDriverCovered = in_array('DriverLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                $LiabilityToPaidDriver_IsChecked = in_array('DriverLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                $LiabilityToPaidDriver_NoOfItems = $value->LLNumberDriver ?? 0;
                $IsLiabilityToPaidCleanerCovered = in_array('CleanerLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                $LiabilityToPaidCleaner_IsChecked = in_array('CleanerLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                $LiabilityToPaidCleaner_NoOfItems = $value->LLNumberCleaner ?? 0;
            }
            if ($value->name == 'Geographical Extension') {
                $country = $value->countries;
                $is_geo_ext = true;
            }
        }
    }

    $is_tppd = 'false';
    $is_anti_theft_device = 'false';

    if ($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '') {
        $discounts = json_decode($selected_addons->discounts);

        foreach ($discounts as $value) {
            if ($value->name == 'TPPD Cover') {
                $is_tppd = 'true';
            } elseif ($value->name == 'anti-theft device') {
                $is_anti_theft_device = 'true';
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

    if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
        $accessories = json_decode($selected_addons->accessories);
        foreach ($accessories as $value) {
            if ($value->name == 'Electrical Accessories') {
                $IsElectricalItemFitted = 'true';
                $ElectricalItemsTotalSI = $value->sumInsured;
            } else if ($value->name == 'Non-Electrical Accessories') {
                $IsNonElectricalItemFitted = 'true';
                $NonElectricalItemsTotalSI = $value->sumInsured;
            } else if ($value->name == 'External Bi-Fuel Kit CNG/LPG') {
                if ($mmv_data->operated_by == 'BATTERY OPERATED') {
                    return  [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'External LPG/CNG kit is not available for Battery Operated Vehicles',
                        'request' => [
                            'mmv' => $mmv_data,
                            'message' => 'External LPG/CNG kit is not available for Battery Operated Vehicles',
                        ]
                    ];
                }

                $type_of_fuel = '5';
                $Fueltype = 'CNG';
                $BiFuelKitSi = $value->sumInsured;
                $is_bifuel_kit = 'true';
            }
        }
    }

    if ($requestData->is_claim != 'N') {
        $IsNCBApplicable = 'false';
        $IsClaimedLastYear = 'true';
    } elseif ($mmv_data->gross_weight < 3500 && $parent_id == 'GCV' && $requestData->business_type == 'breakin') {
        $IsNCBApplicable = 'false';
        $IsClaimedLastYear = 'true';
    } else {
        $IsNCBApplicable = 'true';
        $IsClaimedLastYear = 'false';
    }

    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    $pos_testing_mode = config('constants.motor.reliance.IS_POS_TESTING_MODE_ENABLE_RELIANCE');
    $posp_type = '';
    $posp_pan_number = '';
    $posp_aadhar_number = '';

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type', 'P')
        ->first();

    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if ($pos_data) {
            $posp_type = '2';
            $posp_pan_number = $pos_data->pan_no;
            $posp_aadhar_number = $pos_data->aadhar_no;
        }

        if ($pos_testing_mode === 'Y') {
            $posp_type = '2';
            $posp_pan_number = 'ASDFC4242K';
            $posp_aadhar_number = '339066355663';
        }
    } elseif ($pos_testing_mode === 'Y') {
        $posp_type = '2';
        $posp_pan_number = 'ASDFC4242K';
        $posp_aadhar_number = '339066355663';
    }


    if ($requestData->previous_policy_type == 'Comprehensive' && $requestData->prev_short_term == '1') {
        $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-3 month +1 day', strtotime($requestData->previous_policy_expiry_date)));
    } else {
        $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
    }

    $PolicyNo = '1234567890';
    if ($requestData->is_renewal == 'Y') {
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $PolicyNo = $user_proposal->previous_policy_number;
    }

    $previous_insurance_details = [
        'PrevYearPolicyType'            => $requestData->previous_policy_type == 'Third-party' ? '2' : '1',
        'PrevYearPolicyStartDate'       => $PrevYearPolicyStartDate,
        'PrevYearPolicyEndDate'         => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)),
        'PrevYearPolicyNo'                      => $PolicyNo,
        'PrevPolicyPeriod'              => '1',
        'IsVehicleOfPreviousPolicySold' => 'false',
        'IsNCBApplicable'               => $IsNCBApplicable,
        'MTAReason'                     => '',
        'IsPreviousPolicyDetailsAvailable' => ($requestData->previous_policy_type == 'Not sure' ? 'false' : 'true'),
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
            'Branch_Code'      => config('constants.IcConstants.cv.reliance.BRANCH_CODE'), //'9202',
            'productcode'      => $productCode, //'2311',
            'OtherSystemName'  => '1',
            'isMotorQuote'     => ($tp_only == 'true' || (in_array($parent_id, ['PCV', 'GCV']) && $requestData->business_type == 'breakin')) ? 'false' : 'true',
            'isMotorQuoteFlow' => ($tp_only == 'true') ? 'false' : 'true',
            'POSType'          => $posp_type,
            'POSAadhaarNumber' => $posp_aadhar_number,
            'POSPANNumber'     => $posp_pan_number
        ],
        'Risk'                     => [
            'VehicleMakeID'         => $mmv_data->make_id_pk,
            'VehicleModelID'        => $mmv_data->model_id_pk,
            'StateOfRegistrationID' => $rto_data->state_id_fk,
            'RTOLocationID'         => $rto_data->model_region_id_pk,
            'Rto_RegionCode'         => $rto_data->region_code,
            'ExShowroomPrice'       => isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No' ? (int)$mmv_data->body_price + (int)$mmv_data->chassis_price : ($mmv_data->ex_showroom_price ?? 0),
            'DateOfPurchase'        => $DateOfPurchase,
            'ManufactureMonth'      => $vehicle_manufacture_date[1],
            'ManufactureYear'       => $vehicle_manufacture_date[2],
            'VehicleVariant'        => $mmv_data->variance,
            'IsHavingValidDrivingLicense' => '',
            'IsOptedStandaloneCPAPolicy'  => '',
            'IDV'                   => ($tp_only == 'true') ? 0 : ''
        ],
        'Vehicle'                  => [
            'TypeOfFuel'          => $type_of_fuel,
            'ISNewVehicle'        => $ISNewVehicle,
            'Registration_Number' => $Registration_Number,
            'Registration_date'   => $vehicle_register_date,
            'MiscTypeOfVehicleID' => '',
            'PCVVehicleCategory' => 1,
            'PCVVehicleUsageType' => 4
        ],
        'Cover'                    => [
            'IsPAToUnnamedPassengerCovered' => $IsPAToUnnamedPassengerCovered,
            'IsElectricalItemFitted'        => $IsElectricalItemFitted,
            'ElectricalItemsTotalSI'        => $ElectricalItemsTotalSI,
            'IsPAToOwnerDriverCoverd'       => ($requestData->vehicle_owner_type == 'I') ? 'true' : 'false',
            'IsLiabilityToPaidDriverCovered' => $IsLiabilityToPaidDriverCovered,
            'IsTPPDCover'                   => $is_tppd,
            'IsBasicODCoverage'             => 'true',
            'IsBasicLiability'              => 'true',
            'IsNonElectricalItemFitted'     => $IsNonElectricalItemFitted,
            'NonElectricalItemsTotalSI'     => $NonElectricalItemsTotalSI,
            'IsPAToDriverCovered'           => $IsPAToDriverCovered,
            'IsBiFuelKit'                   => $is_bifuel_kit,
            'BiFuelKitSi'                   => $BiFuelKitSi,
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
                    'NoOfItems' => $LiabilityToPaidDriver_NoOfItems,
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
            'IsAutomobileAssociationMember' => 'false',
            'IsAntiTheftDeviceFitted'       => $is_anti_theft_device,
        ],
        'PreviousInsuranceDetails' => $previous_insurance_details,
        'NCBEligibility'           => [
            'NCBEligibilityCriteria' => $NCBEligibilityCriteria,
            'NCBReservingLetter'     => '',
            'PreviousNCB'            => $PreviousNCB,
        ],
        'ProductCode'              => $productCode,
        'UserID'                   => config('constants.IcConstants.cv.reliance.USERID_RELIANCE'),
        'SourceSystemID'           => config('constants.IcConstants.cv.reliance.SOURCE_SYSTEM_ID_RELIANCE'),
        'AuthToken'                => config('constants.IcConstants.cv.reliance.AUTH_TOKEN_RELIANCE'),
    ];

    if (in_array($productCode, ['2350', '2349']) && $tp_only == 'true') {
        if ($mmv_data->wheels == 3) {
            $premium_req_array['UserID'] = config('constants.IcConstants.cv.reliance.3W_TP_USERID_RELIANCE');
            $premium_req_array['SourceSystemID'] = config('constants.IcConstants.cv.reliance.3W_TP_SOURCE_SYSTEM_ID_RELIANCE');
            $premium_req_array['AuthToken'] = config('constants.IcConstants.cv.reliance.3W_TP_AUTH_TOKEN_RELIANCE');
        }
    }
    if (in_array($productCode, ['2339', '2340'])) {
        $premium_req_array['Cover']['IsImt23LampOrTyreTubeOrHeadlightCover'] = 'true';
    }

    if (isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No') {
        $premium_req_array['Vehicle']['ISmanufacturerfullybuild'] = 'false';
        $premium_req_array['Vehicle']['BodyPrice'] = $mmv_data->body_price;
        $premium_req_array['Vehicle']['ChassisPrice'] = $mmv_data->chassis_price;
    }

    if ($parent_id == 'GCV' || $parent_id == 'MISCELLANEOUS-CLASS') {
        $premium_req_array['Cover']['IsImt23LampOrTyreTubeOrHeadlightCover'] = 'true';
        $premium_req_array['Cover']['IsLiabilitytoCleaner'] = $IsLiabilityToPaidCleanerCovered;

        $premium_req_array['Cover']['LegalLiabilitytoCleaner']['LegalLiabilitytoCleaner'] = [
            'isChecked' => $LiabilityToPaidCleaner_IsChecked,
            'NoOfItems' => $LiabilityToPaidCleaner_NoOfItems
        ];

        if ($parent_id == 'GCV') {
            $premium_req_array['Vehicle']['GCVGoodTypeOfVehicleID'] = 2; //2 for non-hazardeous
            $premium_req_array['Vehicle']['GCVSubTypeOfVehicleID'] = $gcv_vehicle_sub_classes[$productData->product_sub_type_code];
            $premium_req_array['Vehicle']['GrossVehicleWeight'] = $requestData->selected_gvw;
        } else {
            $premium_req_array['Vehicle']['MiscTypeOfVehicleID'] = $misc_vehicles_sub_classes[strtoupper($mmv_data->veh_sub_type_name)] ?? 90;
        }
    }

    if ($parent_id != 'PCV') {
        unset($premium_req_array['Vehicle']['PCVVehicleCategory']);
        unset($premium_req_array['Vehicle']['PCVVehicleUsageType']);
    }

    if ($requestData->business_type == 'newbusiness') {
        unset($premium_req_array['PreviousInsuranceDetails']);
    }

    if ($is_tppd == 'true') {
        $premium_req_array['Cover']['TPPDCover']['TPPDCover']['SumInsured'] = '6000';
    }

    if (isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No') {
        $premium_req_array['Vehicle']['ISmanufacturerfullybuild'] = 'false';
        $premium_req_array['Vehicle']['BodyPrice'] = $mmv_data->body_price;
        $premium_req_array['Vehicle']['ChassisPrice'] = $mmv_data->chassis_price;
    }

    if ($is_geo_ext) {
        $premium_req_array['Cover']['IsGeographicalAreaExtended'] = 'true';
        $premium_req_array['Cover']['GeographicalExtension']['GeographicalExtension']['IsChecked'] = 'false';
        $premium_req_array['Cover']['GeographicalExtension']['GeographicalExtension']['Countries'] = 1949; #hardcoded
    }

    $agentDiscount = calculateAgentDiscount($enquiryId, 'reliance', strtolower($parent_id));
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
    if (isset($reg_no_3) && !empty($reg_no_3)) {
        unset($premium_req_array['Vehicle']['Registration_Number']);
    }
    $checksum_data = checksum_encrypt($premium_req_array);
    $premium_req_array['Vehicle']['Registration_Number'] = $Registration_Number;
    $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'reliance', $checksum_data, 'CV');
    if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
        $get_response = $is_data_exits_for_checksum;
    } else {
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
                'productName'   => $productData->product_name,
                'transaction_type'     => 'quote',
                'headers' => [
                    'Content-type' => 'text/xml',
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                ],
                'checksum' => $checksum_data
            ]
        );
    }
    $coverage_res_data = $get_response['response'];
    if ($coverage_res_data) {
        $coverage_res_data = json_decode($coverage_res_data);
        if (!isset($coverage_res_data->ErrorMessages)) {
            $nil_dep_rate = 0;

            if (isset($coverage_res_data->LstAddonCovers)) {
                foreach ($coverage_res_data->LstAddonCovers as $k => $v) {
                    if ($v->CoverageName == 'Nil Depreciation') {
                        $nil_dep_rate = $v->rate;
                    }
                }

                if ($productData->zero_dep == 0 && $nil_dep_rate > 0) {
                    $premium_req_array['Cover']['IsNilDepreciation'] = 'true';
                    $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                }
                update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success");

                $temp_data = $premium_req_array;
                $temp_data['type'] = 'Premium Calculation';
                unset($temp_data['Vehicle']['Registration_Number']);
                $checksum_data = checksum_encrypt($temp_data);
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'reliance', $checksum_data, 'CV');
                if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
                    $get_response = $is_data_exits_for_checksum;
                } else {
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
                            'productName'   => $productData->product_name,
                            'transaction_type'    => 'quote',
                            'headers' => [
                                'Content-type' => 'text/xml',
                                'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                            ],
                            'checksum' => $checksum_data
                        ]
                    );
                }
                $premium_res_data = $get_response['response'];
                if ($premium_res_data) {
                    $response = json_decode($premium_res_data)->MotorPolicy ?? '';
                    $skip_second_call = false;
                    if (empty($response)) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => 'Insurer not reachable',
                        ];
                    }

                    unset($premium_res_data);
                    if (trim($response->ErrorMessages) == '') {
                        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success");
                        // $premium_req_array['Vehicle']['BodyPrice']
                        $idvArray = [
                            'defaultBodyIDV' => round((int)($response->BodyIDV ?? 0)),
                            'minBodyIDV' => ceil((int)($response->MinBodyIDV) ?? 0),
                            'maxBodyIDV' => floor((int)($response->MaxBodyIDV) ?? 0),

                            'defaultChassisIDV' => round((int)($response->ChassisIDV) ?? 0),
                            'minChassisIDV' => ceil((int)($response->MinChassisIDV) ?? 0),
                            'maxChassisIDV' => floor((int)($response->MaxChassisIDV) ?? 0),
                        ];

                        $idvArray = idvChangedLogic($requestData, $idvArray);
                        // dump($idvArray, $response, $premium_req_array);

                        if (isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No') {
                            $premium_req_array['Vehicle']['ISmanufacturerfullybuild'] = 'false';
                            // $premium_req_array['Vehicle']['BodyPrice'] = $idvArray['bodyIDV'];
                            // $premium_req_array['Vehicle']['ChassisPrice'] = $idvArray['chassisIDV'];
                            $premium_req_array['Vehicle']['BodyIDV'] = $idvArray['bodyIDV'];
                            $premium_req_array['Vehicle']['ChassisIDV'] = $idvArray['chassisIDV'];
                            $skip_second_call = false;
                        }

                        $min_idv = $response->MinIDV;
                        $max_idv = $response->MaxIDV;
                        if ($requestData->is_idv_changed == 'Y') {
                            $response_det = $response;

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
                                    $skip_second_call = false;
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

                        if (!$skip_second_call) {
                            $temp_data = $premium_req_array;
                            $temp_data['type'] = 'Premium Re-Calculation';
                            unset($temp_data['Vehicle']['Registration_Number']);
                            $checksum_data = checksum_encrypt($temp_data);
                            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId, 'reliance', $checksum_data, 'CV');
                            if ($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']) {
                                $get_response = $is_data_exits_for_checksum;
                            } else {
                                $get_response = getWsData(
                                    config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PREMIUM'),
                                    $premium_req_array,
                                    'reliance',
                                    [
                                        'root_tag'      => 'PolicyDetails',
                                        'section'       => $productData->product_sub_type_code,
                                        'method'        => 'Premium Re Calculation',
                                        'requestMethod' => 'post',
                                        'enquiryId'     => $enquiryId,
                                        'checksum'      => $checksum_data,
                                        'productName'   => $productData->product_name,
                                        'transaction_type'    => 'quote',
                                        'headers' => [
                                            'Content-type' => 'text/xml',
                                            'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY')
                                        ]
                                    ]
                                );
                            }
                        }

                        $response = $get_response['response'];
                        if (! $response) {
                            return [
                                'premium_amount' => 0,
                                'status'         => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message'        => 'Insurer not reachable'
                            ];
                        } else {
                            $response = json_decode($response)->MotorPolicy;
                            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success");
                            //                                    $response['minidv'] = $response_det['minidv'];
                            //                                    $response['maxidv'] = $response_det['maxidv'];
                            if ($response->ErrorMessages != '') {
                                return [
                                    'premium_amount' => 0,
                                    'status'         => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'message'        => $response->ErrorMessages
                                ];
                            }
                        }


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
                        $tppd_discount_amt = 0;
                        $other_addon_amount = 0;
                        $liabilities = 0;
                        $ll_paid_cleaner = 0;
                        $imt_23 = 0;
                        $GeogExtension_od = 0;
                        $GeogExtension_tp = 0;
                        $own_premises_od = 0;
                        $own_premises_tp = 0;
                        $other_discount = 0;
                        $odDiscount = 0;

                        $idv = (int) $response->IDV;
                        if (is_array($response->lstPricingResponse)) {
                            foreach ($response->lstPricingResponse as $k => $v) {
                                $value = round(trim(str_replace('-', '', $v->Premium)));
                                if ($v->CoverageName == 'Basic OD') {
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
                                    $tppd = $value;
                                } elseif ($v->CoverageName == 'PA to Unnamed Passenger') {
                                    $pa_unnamed = $value;
                                } elseif ($v->CoverageName == 'PA to Owner Driver') {
                                    $pa_owner = $value;
                                } elseif ($v->CoverageName == 'PA to Paid Driver') {
                                    $pa_paid_driver = $value;
                                } elseif ($v->CoverageName == 'Liability to Paid Driver') {
                                    $liabilities = $value;
                                } elseif ($v->CoverageName == 'Liability to Cleaner') {
                                    $ll_paid_cleaner = $value;
                                } elseif ($v->CoverageName == 'Bifuel Kit TP') {
                                    $lpg_cng_tp = $value;
                                } elseif ($v->CoverageName == 'Automobile Association Membership') {
                                    $automobile_association = round(abs($value));
                                } elseif ($v->CoverageName == 'Anti-Theft Device') {
                                    $anti_theft = round(abs($value));
                                } elseif ($v->CoverageName == 'IMT 23(Lamp/ tyre tube/ Headlight etc )') {
                                    $imt_23 = round($value);
                                } elseif ($v->CoverageName == 'TPPD') {
                                    $tppd_discount_amt = round(abs($value));
                                } elseif (($v->CoverageName == 'Geographical Extension' || $v->CoverageName == 'Geo Extension') && $v->CoverID == '5') {
                                    $GeogExtension_od = round(abs($value));
                                } elseif ($v->CoverageName == 'Geographical Extension' && ($v->CoverID == '6' || $v->CoverID == '403')) {
                                    $GeogExtension_tp = round(abs($value));
                                } elseif ($v->CoverageName == 'OD Discount') {
                                    $odDiscount = $value;
                                }

                                unset($value);
                            }
                        } else {
                            $tppd = $response->lstPricingResponse->Premium;
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

                        $ic_vehicle_discount = 0;
                        $voluntary_excess = 0;
                        $loading_amount = 0;
                        $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng + $GeogExtension_od;
                        $final_tp_premium = $tppd + $liabilities + $pa_paid_driver + $pa_unnamed + $lpg_cng_tp + $ll_paid_cleaner + $GeogExtension_tp;

                        $total_discount = $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount;

                        $ncb_discount = ($final_od_premium - $total_discount) * ($requestData->applicable_ncb / 100);

                        $final_total_discount = $total_discount + $tppd_discount_amt + $ncb_discount + $odDiscount;
                        $final_total_discount_for_loading_cal = $total_discount + $ncb_discount;

                        $final_od = $final_od_premium - $final_total_discount_for_loading_cal;

                        if ($tp_only == 'false' && $final_od < 100) {
                            $loading_amount = 100 - $final_od;
                        }

                        $final_net_premium     = round($final_od_premium + $final_tp_premium - $final_total_discount);

                        if ($parent_id == 'GCV' || $parent_id == 'MISCELLANEOUS-CLASS') {
                            $final_gst_amount = round(($tppd * 0.12) + (($final_net_premium - $tppd) * 0.18));
                        } else {
                            $final_gst_amount   = round($final_net_premium * 0.18);
                        }

                        $final_payable_amount  = $final_net_premium + $final_gst_amount;

                        $applicable_addons = ['zeroDepreciation', 'imt23'];

                        if ($parent_id == 'PCV' && ! in_array($productCode, ['2339', '2340'])) {
                            array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                        }

                        if ((int) $zero_dep_amount == 0) #((int) $nil_dep_rate == 0)
                        {
                            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                        }

                        $business_types = [
                            'rollover' => 'Rollover',
                            'newbusiness' => 'New Business',
                            'breakin' => 'Break-in'
                        ];

                        $add_ons_data = [];
                        if ($productData->zero_dep == 0) {
                            $add_ons_data = [
                                'in_built' => [
                                    'zero_depreciation' => $zero_dep_amount,
                                ],
                                'additional' => [
                                    'imt23' => $imt_23
                                ],
                            ];
                        } else {
                            $add_ons_data = [
                                'in_built' => [],
                                'additional' => [
                                    'zero_depreciation' => $zero_dep_amount,
                                    'imt23' => $imt_23
                                ],
                            ];
                        }

                        if ($productData->zero_dep == 0 && $zero_dep_amount == 0) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                                'request' => [
                                    'message' => 'Zero Dep Premium not available for Zero Depreciation Product',
                                ]
                            ];
                        }

                        $add_ons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
                        $add_ons_data['additional_premium'] = array_sum($add_ons_data['additional']);
                        $isInspectionWaivedOff = false;
                        $waiverExpiry = null;
                        if (
                            $requestData->business_type == 'breakin' &&
                            !empty($requestData->previous_policy_expiry_date) &&
                            strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
                            !in_array($premium_type, ['third_party', 'third_party_breakin']) &&
                            empty($response->InspectionErrorMessage) &&
                            $parent_id == 'PCV'
                        ) {
                            //inspection is not required if InspectionErrorMessage is empty according to git 27274
                            $ribbonMessage = (config("INSPECTION_RIBBON_TEXT", 'No Inspection Required'));
                            $isInspectionWaivedOff = true;
                            $waiverExpiry = date('d-m-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                        }
                        $data_response = [
                            'status' => true,
                            'msg' => 'Found',
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'Data' => [
                                'idv'                       => round($idv),
                                'min_idv'                   => $min_idv != "" ? round($min_idv) : ((($idvArray['minBodyIDV'] ?? 0) + ($idvArray['minChassisIDV'] ?? 0))),
                                'max_idv'                   => $max_idv != "" ? round($max_idv) : ((($idvArray['maxBodyIDV'] ?? 0) + ($idvArray['maxChassisIDV'] ?? 0))),
                                'vehicle_idv'               => round($idv),
                                'bodyIDV'                   => $idvArray['bodyIDV'] ?? 0,
                                'minBodyIDV'                => $idvArray['minBodyIDV'] ?? 0,
                                'maxBodyIDV'                => $idvArray['maxBodyIDV'] ?? 0,

                                'chassisIDV'                => $idvArray['chassisIDV'] ?? 0,
                                'minChassisIDV'             => $idvArray['minChassisIDV'] ?? 0,
                                'maxChassisIDV'             => $idvArray['maxChassisIDV'] ?? 0,

                                'qdata'                     => NULL,
                                'pp_enddate'                => $requestData->previous_policy_expiry_date,
                                'addonCover'                => NULL,
                                'addon_cover_data_get'      => '',
                                'rto_decline'               => NULL,
                                'rto_decline_number'        => NULL,
                                'mmv_decline'               => NULL,
                                'mmv_decline_name'          => NULL,
                                'policy_type'               => $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 'Third Party' : (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']) ? 'Short Term' : 'Comprehensive'),
                                'business_type'             => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type],
                                'cover_type'                => '1YC',
                                'hypothecation'             => '',
                                'hypothecation_name'        => '',
                                'vehicle_registration_no'   => $rto_code, //$requestData->rto_code,
                                'rto_no'                    => $rto_code,

                                'version_id'                => $requestData->version_id,
                                'selected_addon'            => [],
                                'showroom_price'            => 0,
                                'fuel_type'                 => $mmv_data->fyntune_version['fuel_type'],
                                'ncb_discount'              => (int)$ncb_discount > 0 ? $requestData->applicable_ncb : 0,
                                'tppd_discount'             => $tppd_discount_amt,
                                'company_name'              => $productData->company_name,
                                'company_logo'              => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                                'product_name'              => $productData->product_sub_type_name,
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
                                'motor_manf_date'           => join('-', $vehicle_manufacture_date),
                                'vehicle_register_date'     => $requestData->vehicle_register_date,
                                'vehicleDiscountValues'     => [
                                    'master_policy_id'      => $productData->policy_id,
                                    'product_sub_type_id'   => $productData->product_sub_type_id,
                                    'segment_id'            => 0,
                                    'rto_cluster_id'        => 0,
                                    'car_age'               => 2, //$car_age,
                                    'aai_discount'          => 0,
                                    'ic_vehicle_discount'   => $other_discount + $odDiscount,
                                ],
                                'ic_vehicle_discount'   => $other_discount + $odDiscount,
                                'ribbon' => $ribbonMessage,
                                'basic_premium'                             => $basic_od,
                                'motor_electric_accessories_value'          => $electrical_accessories,
                                'motor_non_electric_accessories_value'      => $non_electrical_accessories,
                                'total_accessories_amount(net_od_premium)'  => $electrical_accessories + $non_electrical_accessories + $lpg_cng,
                                "UnderwritingLoadingAmount"                 => $loading_amount,
                                'total_own_damage'                          => $final_od_premium,

                                'tppd_premium_amount'                       => $tppd,
                                'compulsory_pa_own_driver'                  => $pa_owner,  // Not added in Total TP Premium
                                'cover_unnamed_passenger_value'             => $pa_unnamed,
                                'default_paid_driver'                       => $liabilities + $ll_paid_cleaner,
                                'll_paid_driver_premium'                    => (float) $liabilities,
                                'll_paid_conductor_premium'                 => 0,
                                'll_paid_cleaner_premium'                   => (float) $ll_paid_cleaner,
                                'motor_additional_paid_driver'              => $pa_paid_driver,
                                'seating_capacity'                          => $mmv_data->seating_capacity,
                                'deduction_of_ncb'                          => $ncb_discount,
                                'antitheft_discount'                        => $anti_theft,
                                'aai_discount'                              => $automobile_association,
                                'voluntary_excess'                          => $voluntary_excess,
                                'other_discount'                            => $other_discount,
                                'total_liability_premium'                   => $final_tp_premium,
                                'net_premium'                               => $final_net_premium,
                                'service_tax_amount'                        => $final_gst_amount,
                                'GeogExtension_ODPremium'                   => $GeogExtension_od,
                                'GeogExtension_TPPremium'                   => $GeogExtension_tp,
                                'LimitedtoOwnPremises_OD'                   => round($own_premises_od),
                                'LimitedtoOwnPremises_TP'                   => round($own_premises_tp),
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
                                'service_err_code'                          => NULL,
                                'service_err_msg'                           => NULL,
                                'policyStartDate'                           => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($policy_start_date)),
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

                                'add_ons_data' =>   $add_ons_data,
                                'final_od_premium'      => $final_od_premium,
                                'final_tp_premium'      => $final_tp_premium,
                                'final_total_discount'  => $final_total_discount,
                                'final_net_premium'     => $final_net_premium,
                                'final_gst_amount'      => round($final_gst_amount),
                                'final_payable_amount'  => round($final_payable_amount),
                                'applicable_addons' => $applicable_addons,
                                'mmv_detail' => [
                                    'manf_name'             => $mmv_data->make_name,
                                    'model_name'            => $mmv_data->model_name,
                                    'version_name'          => $mmv_data->variance,
                                    'fuel_type'             => $mmv_data->operated_by,
                                    'seating_capacity'      => $mmv_data->seating_capacity,
                                    'carrying_capacity'     => $mmv_data->carrying_capacity,
                                    'cubic_capacity'        => $mmv_data->cc,
                                    'gross_vehicle_weight'  => $mmv_data->gross_weight,
                                    'vehicle_type'          => $mmv_data->veh_type_name,
                                ]
                            ],
                            'mmv_data' => $mmv_data
                        ];
                        // if($tppd_discount_amt)
                        // {
                        //     $data_response['Data']['tppd_discount'] = $tppd_discount_amt;
                        // }

                        if (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin'])) {
                            $data_response['Data']['premiumTypeCode'] = $premium_type;
                        }

                        if ($tp_only == 'true') {
                            $add_ons_data = [
                                'in_built'   => [],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0
                                ]
                            ];
                        }

                        if ($is_bifuel_kit == 'true') {
                            $data_response['Data']['motor_lpg_cng_kit_value'] = $lpg_cng;
                            $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                            $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                        }
                        if ($isInspectionWaivedOff) {
                            $data_response['Data']['isInspectionWaivedOff'] = true;
                            $data_response['Data']['waiverExpiry'] = $waiverExpiry;
                        }

                        return camelCase($data_response);
                    } else {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message'        => $response->ErrorMessages
                        ];
                    }
                } else {
                    return [
                        'premium_amount' => 0,
                        'status'         => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message'        => 'Insurer not reachable',
                    ];
                }
                die;
            } else {
                return [
                    'premium_amount' => 0,
                    'status'         => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'        => 'Insurer not reachable',
                ];
            }
        } else {
            return [
                'premium_amount' => 0,
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => $coverage_res_data->ErrorMessages
            ];
        }
    } else {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message'        => 'Insurur Not Reachable'
        ];
    }
}

function idvChangedLogic($requestData, $idvArray)
{
    $bodyIDV = 0;
    $chassisIDV = 0;

    if (($requestData->is_body_idv_changed ?? 'N') == 'Y') {
        if (($idvArray['maxBodyIDV'] != "") && ($requestData->edit_body_idv >= floor($idvArray['maxBodyIDV']))) {

            $bodyIDV = floor($idvArray['maxBodyIDV']);
        } elseif (($idvArray['minBodyIDV'] != "") && ($requestData->edit_body_idv <= ceil($idvArray['minBodyIDV']))) {

            $bodyIDV = ceil($idvArray['minBodyIDV']);
        } else {

            $bodyIDV = $requestData->edit_body_idv;
        }
    } else {

        $bodyIDV = $idvArray['defaultBodyIDV'];
    }

    if (($requestData->is_chassis_idv_changed ?? 'N') == 'Y') {

        if (($idvArray['maxChassisIDV'] != "") && ($requestData->edit_chassis_idv >= floor($idvArray['maxChassisIDV']))) {

            $chassisIDV = floor($idvArray['maxChassisIDV']);
        } elseif (($idvArray['minChassisIDV'] != "") && ($requestData->edit_chassis_idv <= ceil($idvArray['minChassisIDV']))) {

            $chassisIDV = ceil($idvArray['minChassisIDV']);
        } else {

            $chassisIDV = $requestData->edit_chassis_idv;
        }
    } else {

        $chassisIDV = $idvArray['defaultChassisIDV'];
    }

    $idvArray['bodyIDV']  = $bodyIDV;
    $idvArray['chassisIDV']  = $chassisIDV;
    return $idvArray;
}
