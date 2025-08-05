<?php
include_once app_path() . '/Helpers/CvWebServiceHelper.php';
use App\Models\MasterRto;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

function getQuote($enquiryId, $requestData, $productData)
{
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
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
    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();
    if (empty($rto_data)) {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO not available'
        ];
    }
    $mmv = get_mmv_details($productData, $requestData->version_id, 'liberty_videocon');

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

    // if (strtotime($requestData->vehicle_register_date) > strtotime('2018-08-31')) {
    //     return [
    //         'status' => false,
    //         'premium_amount' => 0,
    //         'message' => 'Premium not available for vehicles registered after 31-08-2018'
    //     ];
    // }

   $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    } elseif ($mmv_data->ic_version_code == 'DNE') {
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

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
    $is_liability   = (($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? true : false);
    $is_indivisual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = (
        (
            (strpos($requestData->business_type, 'breakin') === false) || (!$is_liability && $requestData->previous_policy_type == 'Third-party')
        ) ? false
        : true);

    $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

    $vehicle_invoice_date = new DateTime($requestData->vehicle_invoice_date);
    $registration_date = new DateTime($requestData->vehicle_register_date);

    $date1 = !empty($requestData->vehicle_invoice_date) ? $vehicle_invoice_date : $registration_date;
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = floor($age / 12);

    $motor_manf_date = '01-'.$requestData->manufacture_year;

    $vehicle_registration_no = explode('-', $requestData->vehicle_registration_no ?? $rto_data->rto_code.'-'.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2).'-'.substr(str_shuffle('1234567890'), 1, 4));

    if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no != null && !in_array(strtoupper($requestData->vehicle_registration_no), ['NEW', 'NONE'])) {
        $vehicle_registration_no = explode('-', $requestData->vehicle_registration_no);
        
        if ($vehicle_registration_no[0] == 'DL') {
            $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0].$vehicle_registration_no[1],true); 
            $vehicle_registration_no = $registration_no.'-'.$vehicle_registration_no[2].'-'.$vehicle_registration_no[3];
            $vehicle_registration_no = explode('-', $vehicle_registration_no);
        }
    } else {
        $vehicle_registration_no = array_merge(explode('-', $rto_data->rto_code), ['MG', rand(1111, 9999)]);
    }

    if($vehicle_registration_no[0] == 'DL')
    {
        $vehicle_registration_no = explode('-',getRegisterNumberWithHyphen($vehicle_registration_no[0].$vehicle_registration_no[1].$vehicle_registration_no[2].$vehicle_registration_no[3]));
        $requestData->rto_code = $vehicle_registration_no[0].'-'.$vehicle_registration_no[1];
    }

    if ($is_new)
    {
        $BusinessType = 'New Business';
        $PreviousPolicyStartDate = '';
        $PreviousPolicyEndDate = '';

        $policy_start_date = date('Y-m-d');
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));

    }
    elseif (!$is_new)
    {
        $BusinessType = 'Roll Over';
        $PreviousPolicyStartDate = date('d/m/Y', strtotime('-1 Year +1 dayr', strtotime($requestData->previous_policy_expiry_date)));
        $PreviousPolicyEndDate = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));

        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));

        if ($is_breakin) {
            $policy_start_date = date('Y-m-d');
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }
    $policyTenure = '1';
    $isShortTermPolicy = false;
    $isThreeMonths = false;
    $prevPolyStartDate = '';
    if (in_array($productData->premium_type_code, ['short_term_3'])) {
        $isShortTermPolicy = $isThreeMonths = true;
        $policyTenure = '3';
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $policy_end_date = Carbon::parse($policy_start_date)->addMonth(3)->subDay(1);
    } elseif (in_array($productData->premium_type_code, ['short_term_6'])) {
        $isShortTermPolicy = true;
        $policyTenure = '6';
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $policy_end_date = Carbon::parse($policy_start_date)->addMonth(6)->subDay(1);
    }

    if (in_array($requestData->previous_policy_type, ['Comprehensive', 'Third-party', 'Own-damage'])) {
        $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
    }

    if ($requestData->prev_short_term == "1") {
        $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subMonth(3)->addDay(1)->format('d/m/Y');
    }

    $zero_depreciation_cover = 'No';
    $road_side_assistance_cover = 'No';
    $consumables_cover = 'No';
    $engine_protection_cover = 'No';
    $key_and_lock_protection_cover = 'No';
    $return_to_invoice_cover = 'No';

    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
    ->first();

    $electrical_accessories = 'No';
    $electrical_accessories_details = '';
    $non_electrical_accessories = 'No';
    $non_electrical_accessories_details = '';
    $external_fuel_kit = 'No';
    $fuel_type = $mmv_data->fuel_type;
    $external_fuel_kit_amount = '';

    if (!empty($additional['accessories'])) {
        foreach ($additional['accessories'] as $key => $data) {
            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                if ($data['sumInsured'] < 15000 || $data['sumInsured'] > 50000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'External Bi-Fuel Kit CNG/LPG value should be between 15000 to 50000',
                        'request' => [
                            'cng_lpg_amt' => $data['sumInsured'],
                            'message' => 'External Bi-Fuel Kit CNG/LPG value should be between 15000 to 50000',
                        ]
                    ];
                } else {
                    $external_fuel_kit = 'Yes';
                    $fuel_type = 'CNG';
                    $external_fuel_kit_amount = $data['sumInsured'];
                }
            }

            if ($data['name'] == 'Non-Electrical Accessories') {
                if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                        'request' => [
                            'non_elec_acc_value' => $data['sumInsured'],
                            'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                        ]
                    ];
                } else {
                    $non_electrical_accessories = 'Yes';
                    $non_electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }
            }

            if ($data['name'] == 'Electrical Accessories') {
                if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle electric accessories value should be between 10000 to 25000',
                        'request' => [
                            'electrical_amount' => $data['sumInsured'],
                            'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                        ]
                    ];
                } else {
                    $electrical_accessories = 'Yes';
                    $electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }
            }
        }
    }

    $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = 'No';
    $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
    $no_of_pa_unnamed_passenger = 1;

    if (!empty($additional['additional_covers'])) {
        foreach ($additional['additional_covers'] as $key => $data) {
            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                $cover_pa_paid_driver = 'Yes';
                $cover_pa_paid_driver_amt = $data['sumInsured'];
            }

            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                $cover_pa_unnamed_passenger = 'Yes';
                $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                $no_of_pa_unnamed_passenger = $mmv_data->seating_capacity;
            }

            if ($data['name'] == 'LL paid driver') {
                $cover_ll_paid_driver = 'Yes';
            }
        }
    }

    $is_anti_theft = 'No';
    $is_anti_theft_device_certified_by_arai = 'false';
    $is_voluntary_access = 'No';
    $voluntary_excess_amt = 0;

    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $key => $data) {
            if ($data['name'] == 'anti-theft device') {
                $is_anti_theft = 'Yes';
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                $is_voluntary_access = 'Yes';
                $voluntary_excess_amt = $data['sumInsured'];
            }
        }
    }

    if (isset($requestData->is_claim) && ($requestData->is_claim == 'Y')){
        $no_of_claims = '1';
        $claim_amount = '50000';
    }
    else{
        $no_of_claims = '0';
        $claim_amount = '0';
    }

    $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

    $pos_name   = '';
    $pos_type   = '';
    $pos_code   = '';
    $pos_aadhar = '';
    $pos_pan    = '';
    $pos_mobile = '';

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if($pos_data) {
            $pos_name   = $pos_data->agent_name;
            $pos_type   = 'POSP';
            $pos_code   = $pos_data->pan_no;
            $pos_aadhar = $pos_data->aadhar_no;
            $pos_pan    = $pos_data->pan_no;
            $pos_mobile = $pos_data->agent_mobile;
        }
    }

    if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_LIBERTY_VIDEOCON') == 'Y')
    {
        $pos_name   = 'Agent';
        $pos_type   = 'POSP';
        $pos_code   = 'ABGTY8890Z';
        $pos_aadhar = '569278616999';
        $pos_pan    = 'ABGTY8890Z';
        $pos_mobile = '8850386204';
    }

    if(!$is_new && !$is_liability && ( $requestData->previous_policy_type == 'Third-party' || $requestData->previous_policy_type == 'Not sure'))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Break-In Quotes Not Allowed',
            'request' => [
                'message' => 'Break-In Quotes Not Allowed',
                'previous_policy_typ' => $requestData->previous_policy_type
            ]
        ];
    }

    //$productcode = ($is_od ? config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_OD') : config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_PACKAGE'));

    if (isset($vehicle_registration_no[1]) && !is_numeric($vehicle_registration_no[1])) {
        preg_match_all("/[a-zA-Z]/", $vehicle_registration_no[1], $matches);
        $vehicle_registration_no[1] = preg_replace("/[a-zA-Z]/", "", $vehicle_registration_no[1]);
        if (isset($matches[0][0])) {
            $extras = implode('', $matches[0]);
            $vehicle_registration_no[2] = $extras.($vehicle_registration_no[2] ?? '');
        }
    }

    $rtoCode = explode('-', $requestData->rto_code);
    if (isset($rtoCode[1]) && !is_numeric($rtoCode[1])) {
        $rtoCode[1] = preg_replace("/[a-zA-Z]/", "", $rtoCode[1]);
    }
    $rtoCode = implode('-', $rtoCode);
    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
        if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
            $addons = $selected_CPA->compulsory_personal_accident;
            foreach ($addons as $value) {
                if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                        $PA_Owner_Driver_Tenure = isset($value['name']) ? '1' : '';
                    
                }
            }
        }

    $premium_request = [
        'QuickQuoteNumber' => config('constants.IcConstants.liberty_videocon.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
        'IMDNumber' =>config('constants.IcConstants.liberty_videocon.IMD_NUMBER_LIBERTY_VIDEOCON_CV'),
        'AgentCode' => '',
        'TPSourceName' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR'),
        'ProductCode' => '3153',
        'IsFullQuote' => 'false',// ($is_breakin ? 'false' : 'true'),
        'BusinessType' => (!$is_new || $is_breakin ? 'Roll Over' : 'New Business'),
        'MakeCode' => $mmv_data->manufacturer_code,
        'ModelCode' => $mmv_data->vehicle_model_code,
        'VehicleClass' => $mmv_data->vehicle_class_code,
        'VehicleSubClassCode' => "",
        'VehicleSegment' => $mmv_data->segment_type,
        'VehicleType' => $mmv_data->vehicle_class_desc,
        'ManfMonth' => date('m', strtotime('01-'.$requestData->manufacture_year)),
        'ManfYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
        'RtoCode' => RtoCodeWithOrWithoutZero($rtoCode,true),
        'RegNo1' => $vehicle_registration_no[0],
        'RegNo2' => $vehicle_registration_no[1],
        'RegNo3' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[2] : '',
        'RegNo4' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[3] : $vehicle_registration_no[2],
        'DeliveryDate' => date('d/m/Y', strtotime($vehicleDate)),
        'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
        'VehicleIDV' => '0',
        'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
        'PolicyEndDate' => date('d/m/Y', strtotime($policy_end_date)),
        'PolicyTenure' => $policyTenure,
        'GeographicalExtn' => 'No',
        'GeographicalExtnList' => '',
        'DrivingTuition' => '',
        'VintageCar' => 'No',
        'LegalLiabilityToPaidDriver' => $cover_ll_paid_driver,
        'NoOfPassengerForLLToPaidDriver' => '1',
        'LegalliabilityToEmployee' => '',
        'NoOfPassengerForLLToEmployee' => '',
        'PAUnnnamed' => $cover_pa_unnamed_passenger,
        'NoOfPerunnamed' => $no_of_pa_unnamed_passenger,
        'UnnamedPASI' => $cover_pa_unnamed_passenger_amt,
        'PAOwnerDriver' => $requestData->vehicle_owner_type == 'I' ? 'Yes' : 'No',
        'PAOwnerDriverTenure' => isset($PA_Owner_Driver_Tenure) ? $PA_Owner_Driver_Tenure : '',
        'LegalLiabilityPassenger' => 'Yes',//mandatory for pcv
        'LegalLiabilityToPaidDriver' => 'Yes',//mandatory for pcv
        'LimtedtoOwnPremise' => '',
        'ElectricalAccessories' => $electrical_accessories,
        'lstAccessories' => $electrical_accessories_details,
        'NonElectricalAccessories' => $non_electrical_accessories,
        'lstNonElecAccessories' => $non_electrical_accessories_details,
        'ExternalFuelKit' => $external_fuel_kit,
        'FuelType' => $fuel_type,
        'FuelSI' => $external_fuel_kit_amount,
        'PANamed' => 'No',
        'NoOfPernamed' => '0',
        'NamedPASI' => '0',
        'PAToPaidDriver' => $cover_pa_paid_driver,
        'NoOfPaidDriverPassenger' => '1',
        'PAToPaidDriverSI' => $cover_pa_paid_driver_amt,
        'AAIMembship' => 'No',
        'AAIMembshipNumber' => '',
        'AAIAssociationCode' => '',
        'AAIAssociationName' => '',
        'AAIMembshipExpiryDate' => '',
        'AntiTheftDevice' => $is_anti_theft,
        'IsAntiTheftDeviceCertifiedByARAI' => $is_anti_theft_device_certified_by_arai,
        'TPPDDiscount' => 'No',
        'ForeignEmbassy' => '',
        'VoluntaryExcess' => $is_voluntary_access,
        'VoluntaryExcessAmt' => $voluntary_excess_amt,
        'NoNomineeDetails' => $requestData->vehicle_owner_type == 'I' ? 'false' : 'true',
        'NomineeFirstName' => 'john',
        'NomineelastName' => 'doe',
        'NomineeRelationship' => 'brother',
        'OtherRelation' => '',
        'IsMinor' => 'false',
        'RepFirstName' => '',
        'RepLastName' => '',
        'RepRelationWithMinor' => '',
        'RepOtherRelation' => '',
        'NoPreviousPolicyHistory'   => ($is_new ? 'No' : 'Yes'),
        'IsNilDepOptedInPrevPolicy' => ($is_new ? 'true' : 'false'),
        'PreviousPolicyInsurerName' => ($is_new ? '' : 'IFFCO TOKIO'),
        'PreviousPolicyType'        => ($is_new ? '' : 'PackagePolicy'),
        'PreviousPolicyStartDate'   => ($is_new ? '' : $prevPolyStartDate),
        'PreviousPolicyEndDate'     => ($is_new ? '' : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date))),
        'PreviousPolicyNumber'      => ($is_new ? '' : '123456789'),
        'PreviousYearNCBPercentage' => ($is_new ? '' : $requestData->previous_ncb),
        'ClaimAmount' => $claim_amount,
        'NoOfClaims' => $no_of_claims,
        'PreviousPolicyTenure'      => $is_new ? '' : ($requestData->prev_short_term == "1" ? '3':'1'),
        'IsInspectionDone' => 'false',
        'InspectionDoneByWhom' => '',
        'ReportDate' => '',
        'InspectionDate' => '',
        'ConsumableCover' => 'No',
        'DepreciationCover' => 'No',
        'RoadSideAsstCover' => 'No',
        'GAPCover' => 'No',
        'GAPCoverSI' => '0',
        'EngineSafeCover' => 'No',
        'KeyLossCover' => 'No',
        'KeyLossCoverSI' => '0',
        'PassengerAsstCover' => 'No',
        'EngineNo' => '',
        'ChassisNo' => '',
        'BuyerState' => 'MAHARASHTRA',
        'POSPName'          => $pos_name,
        'POSPType'          => $pos_type,
        'POSPCode'          => $pos_code,
        'POSPAadhar'        => $pos_aadhar,
        'POSPPAN'           => $pos_pan,
        'POSPMobileNumber'  => $pos_mobile,
        'IsShortTermPolicy' => $isShortTermPolicy,
    ];

    if ($requestData->business_type == 'rollover') {
        $premium_request['lstPreviousAddonDetails'] = [
            [
                'IsConsumableOptedInPrevPolicy' => $consumables_cover == 'Yes' ? 'true' : 'false',
                'IsEngineSafeOptedInPrevPolicy' => $engine_protection_cover == 'Yes' ? 'true' : 'false',
                'IsGAPCoverOptedInPrevPolicy' => $return_to_invoice_cover == 'Yes' ? 'true' : 'false',
                'IsKeyLossOptedInPrevPolicy' => $key_and_lock_protection_cover == 'Yes' ? 'true' : 'false',
                'IsNilDepreicationOptedInPrevPolicy' => $zero_depreciation_cover == 'Yes' ? 'true' : 'false',
                'IsPassengerAsstOptedInPrevPolicy' => 'false',
                'IsRoadSideAsstOptedInPrevPolicy' => $road_side_assistance_cover == 'Yes' ? 'true' : 'false'
            ]
        ];
    }
#echo json_encode($premium_request,true);die;

$get_response = getWsData(config('constants.IcConstants.liberty_videocon.END_POINT_URL_LIBERTY_VIDEOCON_PREMIUM_CALCULATION'), $premium_request, 'liberty_videocon', [
        'enquiryId' => $enquiryId,
        'requestMethod' =>'post',
        'productName'  => $productData->product_name,
        'company'  => 'liberty_videocon',
        'section' => $productData->product_sub_type_code,
        'method' =>'Premium Calculation',
        'transaction_type' => 'quote',
    ]);

    $data = $get_response['response'];
    if ($data) {
        $premium_response = json_decode($data, TRUE);//dd($premium_response);
         //echo "<pre>";print_r([$premium_request,$premium_response]);echo "</pre>";die();
         $skip_second_call = false;
        if ($premium_response !== null && trim($premium_response['ErrorText']) == "") {
            $vehicle_idv = round($premium_response['CurrentIDV']);
            $min_idv = ceil($premium_response['MinIDV']);
            $max_idv = floor($premium_response['MaxIDV']);
            $default_idv = round($premium_response['CurrentIDV']);

            if ($requestData->is_idv_changed == 'Y') {
                if ($requestData->edit_idv >= $max_idv) {
                    $premium_request['VehicleIDV'] = $max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $premium_request['VehicleIDV'] = $min_idv;
                } else {
                    $premium_request['VehicleIDV'] = $requestData->edit_idv;
                }
            } else {
                #$premium_request['VehicleIDV'] = $min_idv;
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_request['VehicleIDV'] = $vehicle_idv;
                        $skip_second_call = true;
                        break;
                    case 'min_idv':
                        $premium_request['VehicleIDV'] = $min_idv;
                        break;
                    case 'max_idv':
                        $premium_request['VehicleIDV'] = $max_idv;
                        break;
                    default:
                        $premium_request['VehicleIDV'] = $min_idv;
                        break;
                }
            }
            if(!$skip_second_call){
            $get_response = getWsData(config('constants.IcConstants.liberty_videocon.END_POINT_URL_LIBERTY_VIDEOCON_PREMIUM_CALCULATION'), $premium_request, 'liberty_videocon', [
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'liberty_videocon',
                'section' => $productData->product_sub_type_code,
                'method' =>'Premium Recalculation',
                'transaction_type' => 'quote',
            ]);
        }
            $data = $get_response['response'];
            if (!empty($data)) {
                $premium_response = json_decode($data, TRUE);

                if (trim($premium_response['ErrorText']) == "") {
                    $vehicle_idv = round($premium_response['CurrentIDV']);
                    $default_idv = round($premium_response['CurrentIDV']);
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'premium_amount' => 0,
                        'message' => $premium_response['ErrorText']
                    ];
                }
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Insurer not reachable'
                ];
            }

            $llpaiddriver_premium = round($premium_response['LegalliabilityToPaidDriverValue']);
            $llpassenger_premium = round($premium_response['LegalLiabilityPassengerValue']);
            $cover_pa_owner_driver_premium = round($premium_response['PAToOwnerDrivervalue']);
            $cover_pa_paid_driver_premium = round($premium_response['PatoPaidDrivervalue']);
            $cover_pa_unnamed_passenger_premium = round($premium_response['PAToUnnmaedPassengerValue']);
            $voluntary_excess = round($premium_response['VoluntaryExcessValue']);
            $anti_theft = $premium_response['AntiTheftDiscountValue'];
            $ic_vehicle_discount = round($premium_response['Loading']);
            $ncb_discount = round($premium_response['DisplayNCBDiscountvalue']);
            $od = round($premium_response['BasicODPremium']);
            $tppd = round($premium_response['BasicTPPremium']);
            $cng_lpg = round($premium_response['FuelKitValueODpremium']);
            $cng_lpg_tp = round($premium_response['FuelKitValueTPpremium']);
            $electrical_accessories_amt = round($premium_response['ElectricalAccessoriesValue']);
            $non_electrical_accessories_amt = round($premium_response['NonElectricalAccessoriesValue']);

            $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
            $basic_tp = $tppd + $llpassenger_premium;
            $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium +$llpassenger_premium;
            $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount;
            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
            $final_gst_amount = round($final_net_premium * 0.18);
            $final_payable_amount  = $final_net_premium + $final_gst_amount;

            $addons_data = [];

            $addons_data = [
                'in_built'   => [
                ],
                'additional' => [
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ]
            ];

            if (round($premium_response['PassengerAssistCoverValue']) > 0) {
                $addons_data['other'] = [
                    'passenger_assist_cover' => round($premium_response['PassengerAssistCoverValue'])
                ];
            }

            $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
            $addons_data['additional_premium'] = array_sum($addons_data['additional']);
            $addons_data['other_premium'] = $addons_data['other']['passenger_assist_cover'] ?? 0;

            $applicable_addons = [
            ];

            return camelCase([
                'status' => true,
                'msg' => 'Found',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'Data' => [
                    'idv' => $vehicle_idv,
                    'min_idv' => round($min_idv),
                    'max_idv' => round($max_idv),
                    'default_idv' => $default_idv,
                    'vehicle_idv' => $vehicle_idv,
                    'qdata' => null,
                    'pp_enddate' => ($is_new ? '' : $requestData->previous_policy_expiry_date),
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 'Third Party' : (in_array($premium_type, ['short_term_3', 'short_term_6']) ? 'Short Term' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $rto_data->rto_code,
                    'voluntary_excess' => $voluntary_excess,
                    'version_id' => $mmv_data->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $vehicle_idv,
                    'fuel_type' => $mmv_data->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_name,
                    'mmv_detail' => [
                        'manf_name'             => $mmv_data->manufacturer,
                        'model_name'            => $mmv_data->vehicle_model,
                        'version_name'          => $mmv_data->variant,
                        'fuel_type'             => $mmv_data->fuel_type,
                        'seating_capacity'      => $mmv_data->seating_capacity,
                        'carrying_capacity'     => $mmv_data->carrying_capacity,
                        'cubic_capacity'        => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight'  => $mmv_data->gross_vehicle_weight ?? '',
                        'vehicle_type'          => $mmv_data->vehicle_class_desc,
                    ],
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                        'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                        'sum_insured' => $productData->sum_insured,
                        'corp_client_id' => $productData->corp_client_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'insurance_company_id' => $productData->company_id,
                        'status' => $productData->status,
                        'corp_name' => "",
                        'company_name' => $productData->company_name,
                        'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount' => $productData->default_discount,
                        'predefine_series' => "",
                        'is_premium_online' => $productData->is_premium_online,
                        'is_proposal_online' => $productData->is_proposal_online,
                        'is_payment_online' => $productData->is_payment_online
                    ],
                    'motor_manf_date' => $motor_manf_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'ic_vehicle_discount' => $ic_vehicle_discount,
                    'basic_premium' => $od,
                    'deduction_of_ncb' => $ncb_discount,
                    'tppd_premium_amount' => $basic_tp,
                    'motor_electric_accessories_value' => $electrical_accessories_amt,
                    'motor_non_electric_accessories_value' => $non_electrical_accessories_amt,
                    'motor_lpg_cng_kit_value' => $cng_lpg,
                    'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                    'seating_capacity' => $mmv_data->seating_capacity,
                    'default_paid_driver' => $llpaiddriver_premium,
                    'll_paid_driver_premium' => $llpaiddriver_premium,
                    'll_paid_conductor_premium' => 0,
                    'll_paid_cleaner_premium' => 0,
                    'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                    'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $final_od_premium,
                    'cng_lpg_tp' => $cng_lpg_tp,
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount'  => $final_payable_amount,
                    'antitheft_discount' => $anti_theft,
                    'final_od_premium' => $final_od_premium,
                    'final_tp_premium' => $final_tp_premium,
                    'final_total_discount' => round($final_total_discount),
                    'final_net_premium' => $final_net_premium,
                    'final_gst_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => NULL,
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
                    "max_addons_selection"=> NULL,
                    'add_ons_data' => $addons_data,
                    'applicable_addons' => $applicable_addons
                ]
            ]);
        } else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium' => '0',
                'message' => $premium_response['ErrorText'] ?? 'Insurer not reachable'
            ];
        }
    } else {
        return [
            'status' => false,
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium' => '0',
            'message' => 'Insurer not reachable'
        ];
    }
}
