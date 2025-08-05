<?php

use Carbon\Carbon;
use App\Models\MasterRto;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

include_once app_path().'/Helpers/CarWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $refer_webservice = $productData->db_config['quote_db_cache'];
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
    $mmv = get_mmv_details($productData,$requestData->version_id,'magma');

    if($mmv['status'] == 1)
    {
      $mmv = $mmv['data'];
    }
    else
    {
        return  [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv
            ]
        ];
    }

    $get_mapping_mmv_details = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($get_mapping_mmv_details->ic_version_code) || $get_mapping_mmv_details->ic_version_code == '')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ];
    }
    elseif ($get_mapping_mmv_details->ic_version_code == 'DNE')
    {
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

    $mmv_data['manf_name'] = $get_mapping_mmv_details->vehicle_manufacturer;
    $mmv_data['model_name'] = $get_mapping_mmv_details->vehicle_model_name;
    $mmv_data['version_name'] = $get_mapping_mmv_details->variant;
    $mmv_data['seating_capacity'] = $get_mapping_mmv_details->seating_capacity;
    $mmv_data['cubic_capacity'] = $get_mapping_mmv_details->cubic_capacity;
    $mmv_data['fuel_type'] = $get_mapping_mmv_details->fuel;

    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();

    if (empty($rto_data))
    {
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

    $rto_location = DB::table('magma_rto_location')
        ->where('rto_location_code', 'like', '%' . $rto_code . '%')
        ->where('vehicle_class_code', '45')
        ->first();

    if (empty($rto_location))
    {
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
    if ($requestData->previous_policy_type == 'Own-damage' && $tp_only) {
        return [
            'status' => false,
            'message' => 'OD to TP Policy is not Available due to Previous Policy Type',
        ];
    }

    // $rto_code = $requestData->rto_code;
    // // Re-arrange for Delhi RTO code - start 
    // $rto_code = explode('-', $rto_code);

    // if ((int)$rto_code[1] < 10)
    // {
    //     $rto_code[1] = '0'.(int)$rto_code[1];
    // }

    // $rto_code = implode('-', $rto_code);

    $current_date = implode('', explode('-', date('Y-m-d')));

    $motor_manf_date = '01-'.$requestData->manufacture_year;
    $car_age = 0;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = floor($age / 12);

    if ($interval->y >= 10 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('CAR_AGE_VALIDASTRION_ALLOWED_IC'))))
    {
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

    if ($requestData->business_type == 'newbusiness') 
    {
        $businesstype      = 'New Business';
        $policy_start_date = date('d/m/Y');
        $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');
        $policy_end_date_d_m_y = date('d-m-Y', strtotime('+3 years -1 day', strtotime($policy_start_date_d_m_y)));
        $IsPreviousClaim   = '0';
        $prepolstartdate   = '01/01/1900';
        $prepolicyenddate  = '01/01/1900';
        $PolicyProductType = in_array($premium_type, ['third_party']) ? '3TP' : '3TP1OD';
        $proposal_date     = $policy_start_date;
    }
    else
    {
        $businesstype      = 'Roll Over';
        // $PolicyProductType = $premium_type == 'own_damage' ? '1OD' : ($premium_type == 'third_party' ? '1TP' : '1TP1OD');
        $PolicyProductType = $premium_type == 'own_damage' ? '1OD' : (in_array($premium_type, ['third_party', 'third_party_breakin']) ? '1TP' : '1TP1OD');
        $policy_start_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date.' + 1 days'));
        $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');

        if ($requestData->business_type == 'breakin')
        {
            $today = date('d-m-Y');
            $policy_start_date_d_m_y  = date('d-m-Y', strtotime('+2 day', strtotime(date('d-m-Y'))));
            $policy_start_date = date('d/m/Y', strtotime($policy_start_date_d_m_y));

            if ($productData->premium_type_id == 2)
            {
                $today = date('d-m-Y');
                $policy_start_date_d_m_y  = date('d-m-Y', strtotime($today.' + 1 days'));
                $policy_start_date = date('d/m/Y', strtotime($policy_start_date_d_m_y));
            }
            if($requestData->previous_policy_type == 'Not sure'){
                $policy_start_date_d_m_y  = date('d-m-Y', strtotime('+1 day', strtotime(date('d-m-Y'))));
                $policy_start_date = date('d/m/Y', strtotime($policy_start_date_d_m_y));
            }
        }
        

        $policy_end_date_d_m_y = date('d-m-Y', strtotime($policy_start_date_d_m_y.' - 1 days + 1 year'));
        $proposal_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));

        $IsPreviousClaim    = $requestData->is_claim == 'N' ? 1 : 0;
        
        if($requestData->previous_policy_type_identifier_code == '33')
        {
            $prepolstartdate    = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-3 year +1 day', strtotime($policy_expiry_date)))));
        }
        else
        {
           $prepolstartdate    = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($policy_expiry_date))))); 
        }
        $prepolicyenddate   = date('d/m/Y', strtotime($policy_expiry_date));
    }

    $policy_end_date     = date('d/m/Y', strtotime($policy_end_date_d_m_y));
    $prev_policy_end_date= date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
    $manufacturingyear   = !empty($requestData->manufacture_year) ? Carbon::createFromFormat('m-Y',$requestData->manufacture_year)->year : '';
    $first_reg_date      = date('d/m/Y', strtotime($requestData->vehicle_register_date));
    $vehicle_idv         = 0;
    $vehicle_in_90_days  = 0;

    if (isset($term_start_date))
    {
        $vehicle_in_90_days = (strtotime(date('Y-m-d')) - strtotime($term_start_date)) / (60*60*24);

        if ($vehicle_in_90_days > 90)
        {
            $requestData->ncb_percentage = 0;
        }
    }

    $selected_addons = DB::table('selected_addons')
        ->where('user_product_journey_id',$enquiryId)
        ->first();

    if ((date('d/m/Y')) >= $policy_start_date)
    {
        $time = date("H:i", strtotime($policy_start_date));
    }
    else
    {
        $time = '00:00';
    }
    
    // token Generation
    $tokenParam = [
        'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
        'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
        'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
        'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
    ];

    /* Magma Team told not to cache token - @Amit - 12-10-2022 
    // If token API is not working then don't store it in cache - @Amit - 07-10-2022
    $token_cache = Cache::get('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN');
    if(empty($token_cache)) {
        $token_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
            'method' => 'Token Generation',
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => $productData->product_sub_type_code,
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'quote'
        ]);
        $token_decoded = json_decode($token_response, true);
        if(isset($token_decoded['access_token'])) {
            $token = cache()->remember('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN', 60 * 45, function () use ($token_response) {
                return $token_response;
            });
        } else {
            return [
                'status' => false,
                'message' => "Insurer not reachable,Issue in Token Generation service"
            ];
        }
    } else {
        $token = $token_cache;
    } */

    $token = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
        'method' => 'Token Generation',
        'requestMethod' => 'post',
        'type' => 'tokenGeneration',
        'section' => $productData->product_sub_type_code,
        'enquiryId' => $enquiryId,
        'productName' => $productData->product_name,
        'transaction_type' => 'quote'
    ]);

    // $token = cache()->remember('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN', 60 * 45, function () use ($tokenParam, $enquiryId, $productData) {
    //     return getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
    //         'method' => 'Token Generation',
    //         'requestMethod' => 'post',
    //         'type' => 'tokenGeneration',
    //         'section' => $productData->product_sub_type_code,
    //         'enquiryId' => $enquiryId,
    //         'productName' => $productData->product_name,
    //         'transaction_type' => 'quote'
    //     ]);
    // });
    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
    if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
        $addons = $selected_CPA->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure= isset($value['tenure']) ? $value['tenure'] : '1';
                
            }
        }
    }
    if ($requestData->vehicle_owner_type == 'I' && $premium_type != 'own_damage')
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 3;
        }
        else
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 1;
        }
    }
    $is_pos = false;
    $pos_code = '';
    $pos_name = '';
    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    $pos_data = DB::table('cv_agent_mappings')
    ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type', 'P')
        ->first();

    if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if ($pos_data) {
            $is_pos = true;
            $pos_code = config('constants.IcConstants.magma.MAGMA_POSP_CODE');
            $pos_name = config('constants.IcConstants.magma.MAGMA_POSP_NAME');
        }
    }
    if(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_MAGMA') == 'Y'){
        $is_pos = true;
        $pos_code = config('constants.IcConstants.magma.MAGMA_POSP_CODE');
        $pos_name = config('constants.IcConstants.magma.MAGMA_POSP_NAME');
    }

    if ($token['response'])
    {
        $token_data = json_decode($token['response'], true);

        if (isset($token_data['access_token']))
        {
            $vehicle_registration_no = $requestData->business_type == 'newbusiness' ? 'NEW' : explode('-', $rto_code)[0].'-'.explode('-', $rto_code)[1].'-ZZ-0003';
            if(strlen($requestData->vehicle_registration_no) >= 8 && $requestData->business_type != 'newbusiness')
            {
                $vehicle_registration_no = $requestData->vehicle_registration_no;
            }            

            
            $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacement'] = false ;
            $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = null;
            
          
            if($requestData->previous_policy_type == 'Own-damage'){
                $previous_policy_type = "Standalone OD";
            }elseif($requestData->previous_policy_type == 'Third-party'){
                $previous_policy_type = "LiabilityOnly";
            }elseif(in_array($requestData->previous_policy_type, ['Comprehensive','Not sure'])){
                $previous_policy_type = "PackagePolicy";
            }
            $model_config_premium = [
                'BusinessType' => $businesstype,
                'PolicyProductType' => $PolicyProductType,
                'ProposalDate' => $proposal_date,
                'VehicleDetails' => [
                    'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'TempRegistrationDate' => '',
                    // 'RegistrationNumber' => strtoupper($vehicle_registration_no),
                    'RegistrationNumber' => strtoupper(str_replace(' ', '', $vehicle_registration_no)),//$requestData->business_type == 'newbusiness' ? 'NEW' : explode('-', $rto_code)[0].'-'.explode('-', $rto_code)[1].'-ZZ-0003',
                    'ChassisNumber' => 'ASDFFGHJJNHJTY654',
                    'EngineNumber' => 'ERWEWFWEF',
                    'RTOCode' => $rto_code,
                    'RTOName' => $rto_location->rto_location_description,
                    'ManufactureCode' => $get_mapping_mmv_details->manufacturer_code,
                    'ManufactureName' => $get_mapping_mmv_details->vehicle_manufacturer,
                    'ModelCode' => $get_mapping_mmv_details->vehicle_model_code,
                    'ModelName' => $get_mapping_mmv_details->vehicle_model_name,
                    'HPCC' => $get_mapping_mmv_details->cubic_capacity,
                    'MonthOfManufacture' => date('m', strtotime('01-'.$requestData->manufacture_year)),
                    'YearOfManufacture' => $manufacturingyear,
                    'VehicleClassCode' => 45,//$get_mapping_mmv_details->vehicle_class_code,
                    'SeatingCapacity' => $get_mapping_mmv_details->seating_capacity,
                    'CarryingCapacity' => $get_mapping_mmv_details->seating_capacity - 1,
                    'BodyTypeCode' => $get_mapping_mmv_details->body_type_code,
                    'BodyTypeName' => $get_mapping_mmv_details->body_type,
                    'FuelType' => $get_mapping_mmv_details->fuel,
                    'SeagmentType' => $get_mapping_mmv_details->segment_type,
                    'TACMakeCode' => '',
                    'ExShowroomPrice' => '00',
                    'IDVofVehicle' => '',
                    'HigherIDV' => '',
                    'LowerIDV' => '',
                    'IDVofChassis' => '',
                    'Zone' => 'Zone-' . $rto_location->registration_zone,
                    'IHoldValidPUC' => true,
                    'InsuredHoldsValidPUC' => false,
                ],
                'GeneralProposalInformation' => [
                    'CustomerType' => $requestData->vehicle_owner_type,
                    'BusineeChannelType' => config('constants.IcConstants.magma.MAGMA_BUSINEECHANNELTYPE'),
                    'BusinessSource' => config('constants.IcConstants.magma.MAGMA_BUSINESSSOURCE') ?? 'INTERMEDIARY',
                    'EntityRelationShipCode' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPCODE'),
                    'EntityRelationShipName' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPNAME'),
                    'ChannelNumber' => config('constants.IcConstants.magma.MAGMA_CHANNELNUMBER'),
                    'DisplayOfficeCode' => config('constants.IcConstants.magma.MAGMA_DISPLAYOFFICECODE'),
                    'OfficeCode' => config('constants.IcConstants.magma.MAGMA_OFFICECODE'),
                    'OfficeName' => config('constants.IcConstants.magma.MAGMA_OFFICENAME'),
                    'IntermediaryCode' => config('constants.IcConstants.magma.MAGMA_INTERMEDIARYCODE'),
                    'IntermediaryName' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPNAME'),
                    'BusinessSourceType' => $is_pos ? 'P_AGENT' :'C_AGENT',
                    'PolicyEffectiveFromDate' => $policy_start_date,
                    'PolicyEffectiveToDate' => $policy_end_date,
                    'PolicyEffectiveFromHour' => '00:00',//$time,
                    'PolicyEffectiveToHour' => '23:59',
                    'SPCode' => config('constants.IcConstants.magma.MAGMA_SPCode'),
                    'SPName' => config('constants.IcConstants.magma.MAGMA_SPName'),
                ],
                'AddOnsPlanApplicable' => true,
                'AddOnsPlanApplicableDetails' => [
                    'PlanName' => 'Optional Add on',
                     // 'RoadSideAssistance' => $interval->y < 7 ? true : false,
                     'RoadSideAssistance' => $interval->y < 5 ? true : false,
                     'ZeroDepreciation' => $productData->zero_dep == 0 && $interval->y < 5 ? true : false,
                     'ReturnToInvoice' => $interval->y < 3 ? true : false,
                     //'ReturnToInvoice' => $interval->y <= 2 && $interval->m <= 5 && $interval->d <= 1  ? true : false,
                     'NCBProtection' => ($requestData->business_type == 'newbusiness') || ($requestData->is_claim == 'Y') ? false : ($interval->y < 5 ? true : false),
                     // 'EngineProtector' => $interval->y < 10 ? true : false,
                     'EngineProtector' => $interval->y < 5 ? true : false,
                     // 'KeyReplacement' => false,
                     'KeyReplacement' => $interval->y < 5 ? true : false,
                     'KeyReplacementDetails' => [
                        'KeyReplacementSI'  => '50000',
                     ],
                     // 'Consumables' => $interval->y < 10 ? true : false,
                     'Consumables' => $interval->y < 5 ? true : false,
                     // 'KeyReplacement' => $interval->y < 7 ? true : false,
                      'LossOfPerBelongings' => $interval->y < 5 ? true : false,
                      'TyreGuard' => $interval->y < 5 ? true : false,
                    // 'LossOfPerBelongings' =>  $interval->y <= 2 && $interval->m <= 5 && $interval->d <= 1  ? true : false
                ], //All addons set to false to check which one is working and which isn't
                'OptionalCoverageApplicable' => false,
                'OptionalCoverageDetails' => NULL,
                'IsPrevPolicyApplicable' => $requestData->business_type != 'newbusiness' ? true : false,
                'PrevPolicyDetails' => $requestData->business_type == 'newbusiness' ? NULL : [
                    'PrevNCBPercentage' => $requestData->previous_ncb,
                    'PrevInsurerCompanyCode' => 'CMGI',
                    'HavingClaiminPrevPolicy' => $requestData->is_claim == 'N' ? false : true,
                    'NoOfClaims' => $requestData->is_claim == 'N' ? 0 : 1,
                    'PrevPolicyEffectiveFromDate' => $prepolstartdate,
                    'PrevPolicyEffectiveToDate' => $prepolicyenddate,
                    'PrevPolicyNumber' => (string) time(),
                    'PrevPolicyType' => $previous_policy_type,
                    //  ($requestData->previous_policy_type == 'Own-damage') ? 'Standalone OD' : ($requestData->previous_policy_type == 'Third-party' ? 'LiabilityOnly' : 'PackagePolicy'),
                    // 'PrevPolicyType' => ($tp_only ? 'LiabilityOnly' : 'PackagePolicy'),
                    'PrevAddOnAvialable' => $productData->zero_dep == 0 ? true : false,
                    'PrevPolicyTenure' => '1',
                    'IIBStatus' => 'Not Applicable',
                    'PrevInsuranceAddress' => 'ARJUN NAGAR',
                ],
                'CompulsoryExcessAmount' => '1000',
                'VoluntaryExcessAmount' => '',
                'ImposedExcessAmount' => ''
            ];
            // if($requestData->previous_policy_type_identifier_code == '13')
            // {
            //     $model_config_premium['PrevPolicyDetails']['PrevPolicyType'] = 'Bundled Policy';
            // }

           //enable key replacement as per the git id https://github.com/Fyntune/motor_2.0_backend/issues/24430
            //key replacement changes https://github.com/Fyntune/motor_2.0_backend/issues/23427
            // if($model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacement'] == 'false'){

            //     $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacement'] = false ;
            //     $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = null;
            // }           
            if($is_pos)
            {
                $model_config_premium['GeneralProposalInformation']['POSPCode'] = $pos_code;
                $model_config_premium['GeneralProposalInformation']['POSPName'] = $pos_name;
            }

            $policy = array("own_damage", "own_damage_breakin");
            if (in_array($premium_type, $policy))
            {
                $model_config_premium['IsTPPolicyApplicable'] = true;
                $model_config_premium['PrevTPPolicyDetails'] = [
                    'PolicyNumber'      => (string) time(),
                    'PolicyType'        => 'LiabilityOnly',
                    'InsurerName'       => 'BAJAJ',
                    'TPPolicyStartDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'TPPolicyEndDate'   => date('d/m/Y', strtotime('+3 year -1 day', strtotime($requestData->vehicle_register_date)))
                ];
            }

            $selected_addons = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->first();

            if ( ! $model_config_premium['AddOnsPlanApplicableDetails']['RoadSideAssistance'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['ZeroDepreciation'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['ReturnToInvoice'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['NCBProtection'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['EngineProtector'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacement'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['LossOfPerBelongings'] && ! $model_config_premium['AddOnsPlanApplicableDetails']['TyreGuard'])
            {
                $model_config_premium['AddOnsPlanApplicable'] = false;
                $model_config_premium['AddOnsPlanApplicableDetails'] = NULL;
            }

            if ($requestData->vehicle_owner_type == 'I' && $premium_type != 'own_damage')
            {
                $model_config_premium['PAOwnerCoverApplicable'] = true;
                $model_config_premium['PAOwnerCoverDetails'] = [
                    'PAOwnerSI'                => '1500000',
                    'PAOwnerTenure'            => isset($cpa_tenure) ? $cpa_tenure : '1',
                    'ValidDrvLicense'          => true,
                    'DoNotHoldValidDrvLicense' => false,
                    'Ownmultiplevehicles'      => false,
                    'ExistingPACover'          => false
                ];
            }

            if ($interval->y >= 5)
            {
                $model_config_premium['AddOnsPlanApplicable'] = false;
                $model_config_premium['AddOnsPlanApplicableDetails'] = NULL;
            }

            $external_fuel_kit = false;

            if ($selected_addons['accessories'] && $selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '') {
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['accessories'] as $accessory) {
                    // if ((int) $accessory['sumInsured'] < 10000) {
                    //     return [
                    //         'status' => false,
                    //         'premium' => 0,
                    //         'message' => 'Accessories amount should be greater than 10000',
                    //     ];
                    // }
                    if (!($tp_only)) {
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
                    }

                    if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $external_fuel_kit = true;
                        $model_config_premium['OptionalCoverageDetails']['ExternalCNGkitApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['ExternalCNGLPGkitDetails'] = [
                            'CngLpgSI' => (string) $accessory['sumInsured']
                        ];
                    }
                }
            }

            if ($selected_addons['additional_covers'] && $selected_addons['additional_covers'] != NULL && $selected_addons['additional_covers'] != '')
            {
                
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['additional_covers'] as $additional_cover)
                {
                    if ($additional_cover['name'] == 'LL paid driver')
                    {
                        $model_config_premium['OptionalCoverageDetails']['LLPaidDriverCleanerApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['LLPaidDriverCleanerDetails'] = [
                            'NoofPerson' => '1'
                        ];
                    }

                    if ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                    {
                        $model_config_premium['OptionalCoverageDetails']['UnnamedPACoverApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['UnnamedPACoverDetails'] = [
                            'NoOfPerunnamed' => $get_mapping_mmv_details->seating_capacity,
                            'UnnamedPASI' => $additional_cover['sumInsured'],
                        ];
                    }

                    if ($additional_cover['name'] == 'PA cover for additional paid driver')
                    {
                        $model_config_premium['OptionalCoverageDetails']['PAPaidDriverApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['PAPaidDriverDetails'] = [
                            'NoofPADriver' => 1,
                            'PAPaiddrvSI' => $additional_cover['sumInsured'],
                        ];
                    }

                    if ($additional_cover['name'] == 'Geographical Extension')
                    {
                        if(config('MAGMA_CAR_BIKE_ENABLE_GEOGRAPHICAL_EXTENSION') == 'Y')
                        {
                            //MAGMA REQUIRES UW UNDERWRITING APPROVAL FOR GEO EXTENSION IF BROKER AGRESS TO IT PLEASE ENABLE THIS CONFIG
                            $SriLanka = in_array('Sri Lanka',$additional_cover['countries']) ? true : false;
                            $Bhutan = in_array('Bhutan',$additional_cover['countries']) ? true : false;
                            $Nepal = in_array('Nepal',$additional_cover['countries']) ? true : false;
                            $Bangladesh = in_array('Bangladesh',$additional_cover['countries']) ? true : false;
                            $Pakistan = in_array('Pakistan',$additional_cover['countries']) ? true : false;
                            $Maldives = in_array('Maldives',$additional_cover['countries']) ? true : false;

                            $model_config_premium['OptionalCoverageDetails']['GeographicalExtensionApplicable'] = true;
                            $model_config_premium['OptionalCoverageDetails']['GeographicalExtensionDetails'] = [
                                'Bangladesh' => $Bangladesh,
                                'Bhutan' => $Bhutan,
                                'Nepal' => $Nepal
                            ];
                        }
                    }
                }
            }

            if ($selected_addons['discounts'] && $selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '')
            {                
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['discounts'] as $discount)
                {
                    if ($discount['name'] == 'anti-theft device' && !$tp_only)
                    {
                        $model_config_premium['OptionalCoverageDetails']['ApprovedAntiTheftDevice'] = true;
                        $model_config_premium['OptionalCoverageDetails']['CertifiedbyARAI'] = true;
                    }

                    if ($discount['name'] == 'voluntary_insurer_discounts' && !$tp_only)
                    {
                        $model_config_premium['VoluntaryExcessAmount'] = $discount['sumInsured'];
                    }

                    if ($discount['name'] == 'TPPD Cover')
                    {
                        $model_config_premium['OptionalCoverageDetails']['TPPDDiscountApplicable'] = true;
                    }
                }
            }
            
            if((int)$productData->default_discount > 0 && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $model_config_premium['GeneralProposalInformation']['DetariffDis'] = (string) $productData->default_discount;
            }

            if(empty($model_config_premium['OptionalCoverageDetails'])) {
                $model_config_premium['OptionalCoverageApplicable'] = false;
                $model_config_premium['OptionalCoverageDetails'] = null;
            }

            $isagentDiscountAllowed =  false;
            if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
                $agentDiscount = calculateAgentDiscount($enquiryId, 'magma', 'car');
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

            if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $model_config_premium['AddOnsPlanApplicable'] = false;
                $model_config_premium['AddOnsPlanApplicableDetails'] = null;
            }

            $temp_data = $model_config_premium;
            unset($temp_data['PrevPolicyDetails']['PrevPolicyNumber']);
            $checksum_data = checksum_encrypt($temp_data);
            $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'magma',$checksum_data,'CAR');
            if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                $premium_data = $is_data_exist_for_checksum;
            }else{
                $premium_data = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETPREMIUM'), $model_config_premium, 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Premium Calculation',
                    'requestMethod'    => 'post',
                    'type'             => 'premiumCalculation',
                    'token'            => $token_data['access_token'],
                    'checksum'         => $checksum_data,
                    'enquiryId'        => $enquiryId,
                    'productName'      => $productData->product_name,
                    'transaction_type' => 'quote'
                ]);
            }

            if ($premium_data['response'])
            {
                $arr_premium = json_decode($premium_data['response'], true);
                $skip_second_call = false;
                if (isset($arr_premium['ServiceResult']) && $arr_premium['ServiceResult'] == "Success")
                {
                    $max_idv = $arr_premium['OutputResult']['HigherIDV'];
                    $min_idv = $arr_premium['OutputResult']['LowerIDV'];
                    $vehicle_idv = $arr_premium['OutputResult']['IDVofthevehicle'];

                    if (!in_array($premium_type, ['third_party', 'third_party_breakin']))
                    {
                        if ($requestData->is_idv_changed == 'Y')
                        {
                            if ($requestData->edit_idv >= floor($max_idv))
                            {
                                $model_config_premium['VehicleDetails']['IDVofVehicle'] = floor($max_idv);
                            }
                            elseif ($requestData->edit_idv <= ceil($min_idv))
                            {
                                $model_config_premium['VehicleDetails']['IDVofVehicle'] = ceil($min_idv);
                            }
                            else
                            {
                                $model_config_premium['VehicleDetails']['IDVofVehicle'] = $requestData->edit_idv;
                            }
                        }
                        else
                        {
                            #$model_config_premium['VehicleDetails']['IDVofVehicle'] = $min_idv;
                            $getIdvSetting = getCommonConfig('idv_settings');
                            switch ($getIdvSetting) {
                                case 'default':
                                    $model_config_premium['VehicleDetails']['IDVofVehicle'] = $vehicle_idv;
                                    $skip_second_call = false;
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
                        update_quote_web_servicerequestresponse($premium_data['table'], $premium_data['webservice_id'], "Success", "Success" );
                        if(!$skip_second_call){
                            $temp_data = $model_config_premium;
                            unset($temp_data['PrevPolicyDetails']['PrevPolicyNumber']);
                            $checksum_data = checksum_encrypt($temp_data);
                            $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId,'magma',$checksum_data,'CAR');
                            if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                                $premium_data = $is_data_exist_for_checksum;
                            }else{
                                $premium_data = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETPREMIUM'), $model_config_premium, 'magma', [
                                    'section'          => $productData->product_sub_type_code,
                                    'method'           => 'Premium Recalculation',
                                    'requestMethod'    => 'post',
                                    'type'             => 'premiumCalculation',
                                    'token'            => $token_data['access_token'],
                                    'checksum'         =>$checksum_data,
                                    'enquiryId'        => $enquiryId,
                                    'productName'      => $productData->product_name,
                                    'transaction_type' => 'quote'
                                ]);
                            }
                    }
                        if ($premium_data['response'])
                        {
                            $arr_premium = json_decode($premium_data['response'], true);

                            if ( ! isset($arr_premium['ServiceResult']) || $arr_premium['ServiceResult'] != "Success")
                            {
                                $data_response = [
                                    'webservice_id' => $premium_data['webservice_id'] ?? null,
                                    'table' => $premium_data['table'] ?? null,
                                    'premium_amount' => 0,
                                    'status' => false,
                                    'msg' => $arr_premium['ErrorText'] ?? $arr_premium['Message'] ?? 'Error occured in premium re-calculation service'
                                ];
                                return camelCase($data_response);
                            }
                        }
                    }

                    $basic_tp_premium = $basic_od_premium = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $lpg_od_premium = $cng_od_premium = $lpg_tp_premium = $cng_tp_premium = $antitheft = $tppd_discount = $voluntary_excess_discount = $roadside_asst_premium = $zero_dep_premium = $ncb_protection_premium = $eng_protector_premium = $return_to_invoice_premium = $key_replacement_premium = $loss_of_personal_belongings_premium = $tyre_guard_premium = $other_discount = $Consumable_premium = 0;
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;
                    $electrical_discount = $non_electrical_discount = $bifuel_discount = $bifuel_discount_cng = $legal_liability_to_employee = 0;

                    $add_array = $arr_premium['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

                    foreach ($add_array as $add1)
                    {
                        if (in_array($add1['AddOnCoverType'], ["Basic - OD","Basic OD"]))
                        {
                            $basic_od_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Basic - TP","Basic TP"]))
                        {
                            $basic_tp_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["LL to Paid Driver IMT 28"]))
                        {
                            $liabilities = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["PA Owner Driver","PAOwnerCover"]))
                        {
                            $pa_owner_driver = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Basic Roadside Assistance","RoadSideAssistance"]))
                        {
                            $roadside_asst_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Zero Depreciation","ZeroDepreciation"]))
                        {
                            $zero_dep_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["NCB Protection","NCBProtection"]))
                        {
                            $ncb_protection_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Engine Protector","EngineProtector"]))
                        {
                            $eng_protector_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Return to Invoice","ReturnToInvoice"]))
                        {
                            $return_to_invoice_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Inconvenience Allowance","InconvenienceAllowance"]))
                        {
                            $incon_allow_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Key Replacement","KeyReplacement"]))
                        {
                            $key_replacement_premium = (float)($add1['AddOnCoverTypePremium']);
                        }

                        if (in_array($add1['AddOnCoverType'], ["Loss Of Personal Belongings","LossOfPerBelongings"]))
                        {
                            $loss_of_personal_belongings_premium = (float)($add1['AddOnCoverTypePremium']);
                        }
                        if (in_array($add1['AddOnCoverType'], ["Consumables"]))
                        {
                            $Consumable_premium = (float)($add1['AddOnCoverTypePremium']);
                        }
                        if (in_array($add1['AddOnCoverType'], ["Tyre Guard","TyreGuard"]))
                        {
                            $tyre_guard_premium = (float)($add1['AddOnCoverTypePremium']);
                        }
                        if (in_array($add1['AddOnCoverType'], ['Employee Of Insured'])) {
                            $legal_liability_to_employee = (float)($add1['AddOnCoverTypePremium']);
                        }
                    }

                    if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers']))
                    {
                        $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];

                        foreach ($optionadd_array as $add)
                        {
                            $cover_name = !empty($add['OptionalAddOnCoverName']) ? $add['OptionalAddOnCoverName'] : ($add['OptionalAddOnCoversName'] ?? null);
                            $cover_premium = !empty($add['OptionalAddOnCoverPremium']) ? $add['OptionalAddOnCoverPremium'] : ($add['AddOnCoverTotalPremium'] ?? 0);
                            
                            if (empty($cover_name) || empty($cover_premium)) {
                                continue;
                            }

                            $cover_premium = (float) $cover_premium;

                            if ($cover_name == "LLPaidDriverCleaner-TP")
                            {
                                $liabilities = $cover_premium;
                            }
                            if (in_array($cover_name, ['Electrical or Electronic Accessories','Electrical']))
                            {
                                $electrical = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["Non-Electrical Accessories","NonElectrical"]))
                            {
                                $non_electrical = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["Personal Accident Cover-Unnamed","UnnamedPACover"]))
                            {
                                $pa_unnamed = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["PA Paid Drivers, Cleaners and Conductors","PAPaidDriver"]))
                            {
                                $pa_paid_driver = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["LPG Kit-OD", "ExternalCNGkit-OD", "InbuiltCNGkit-OD"]))
                            {
                                $lpg_od_premium = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["LPG Kit-TP", "ExternalCNGkit-TP", "InbuiltCNGkit-TP"]))
                            {
                                $lpg_tp_premium = $cover_premium;
                            }elseif(in_array($cover_name, ["Geographical Extension - OD","Geographical Extension OD" , "GeographicalExtension-OD"]))
                            {
                                $geog_Extension_OD_Premium = $cover_premium;
                            }elseif(in_array($cover_name, ["Geographical Extension - TP","Geographical Extension TP" , "GeographicalExtension-TP"]))
                            {
                                $geog_Extension_TP_Premium = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["CNG Kit-OD"]))
                            {
                                $cng_od_premium = $cover_premium;
                            }
                            elseif (in_array($cover_name, ["CNG Kit-TP"]))
                            {
                                $cng_tp_premium = $cover_premium;
                            }
                        }
                    }

                    if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Discount']))
                    {
                        $discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Discount'];

                        foreach ($discount_array as $discount)
                        {
                            if (in_array($discount['DiscountType'] , ['Anti-Theft Device - OD' , 'ApprovedAntiTheftDevice-Detariff Discount']))
                            {
                                $antitheft = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Automobile Association Discount")
                            {
                                $automobile_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Bonus Discount" || $discount['DiscountType'] == "Bonus Discount - OD") 
                            {
                                $ncb_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'], ["Basic - OD - Detariff Discount","Basic OD-Detariff Discount"]))
                            {
                                $other_discount += (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'], ["Electrical or Electronic Accessories - Detariff Discount on Elecrical Accessories","Elecrical-Detariff Discount"]))
                            {
                                // $other_discount += (float)($discount['DiscountTypeAmount']);
                                $electrical_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'], ["Non-Electrical Accessories - Detariff Discount","NonElecrical-Detariff Discount"]))
                            {
                                // $other_discount += (float)($discount['DiscountTypeAmount']);
                                $non_electrical_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'], ['LPG Kit-OD - Detariff Discount on CNG or LPG Kit', 'ExternalCNGkit-Detariff Discount']))
                            {
                                // $other_discount += (float)($discount['DiscountTypeAmount']);
                                $bifuel_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'], ['CNG Kit-OD - Detariff Discount on CNG or LPG Kit']))
                            {
                                $bifuel_discount_cng = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'] , [ "Voluntary Excess Discount" , "Voluntary Excess Discount-OD"]))
                            {
                                $voluntary_excess_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif (in_array($discount['DiscountType'] ,  ["Basic - TP - TPPD Discount" , "TPPDDiscount"]))
                            {
                                $tppd_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Detariff Discount")
                            {
                                $other_discount += (float)($discount['DiscountTypeAmount']);
                            }
                        }
                    }
                    if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Loading']))
                    {
                        $loadin_discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Loading'];
                        foreach($loadin_discount_array as $loading)
                        {
                            if($loading['LoadingType'] == 'Built in CNG - OD loading - OD')
                            {
                                $lpg_od_premium = (float) $loading['LoadingTypeAmount'];
                            }
                            elseif($loading['LoadingType'] == 'Built in CNG-TP Loading-TP')
                            {
                                $lpg_tp_premium = (float) $loading['LoadingTypeAmount'];
                            }
                        }
                    }

                    $electrical -= $electrical_discount;
                    $non_electrical -= $non_electrical_discount;
                    $lpg_od_premium -= $bifuel_discount;
                    $cng_od_premium -= $bifuel_discount_cng;

                    $ribbonMessage = null;

                    if (($model_config_premium['GeneralProposalInformation']['DetariffDis'] ?? false) && $isagentDiscountAllowed) {
                        $agentDiscountPercentage = $arr_premium['OutputResult']['AppliedDiscount'];
                        if ($model_config_premium['GeneralProposalInformation']['DetariffDis'] != $agentDiscountPercentage) {
                            $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount') . ' ' . $agentDiscountPercentage . '%';
                        }
                    }

                    $final_tp_premium = $basic_tp_premium + $liabilities + $lpg_tp_premium + $cng_tp_premium + $pa_paid_driver + $pa_unnamed + $geog_Extension_TP_Premium + $legal_liability_to_employee;
                    $total_own_damage = $basic_od_premium;
                    $final_od_premium = $total_own_damage + $electrical + $non_electrical + $lpg_od_premium + $cng_od_premium + $geog_Extension_OD_Premium;
                    $total_discount = $antitheft + $voluntary_excess_discount + $other_discount + $tppd_discount;
                    // $ncb_discount = ($final_od_premium - $total_discount) * $requestData->applicable_ncb / 100;
                    $final_total_discount = $total_discount + $ncb_discount;
                    $final_net_premium = ($final_od_premium + $final_tp_premium - $final_total_discount);
                    $final_gst_amount = ($final_net_premium * 0.18);
                    $final_payable_amount = $final_net_premium + $final_gst_amount;
                    $applicable_addons = [];
                    $addons_data = [
                        'in_built' => [],
                        'additional' => [],
                        'other_premium' => []
                    ];

                    if (!in_array($premium_type, ['third_party', 'third_party_breakin']))
                    {
                        $addons_data = [
                            'in_built' => [],
                            'additional' => [
                                'zero_depreciation' => (float)$zero_dep_premium,
                                'road_side_assistance' => (float)$roadside_asst_premium,
                                'ncb_protection' => (float)$ncb_protection_premium,
                                'engine_protector' => (float)$eng_protector_premium,
                                'key_replace' => (float)$key_replacement_premium,
                                'return_to_invoice' => (float)$return_to_invoice_premium,
                                'lopb' => (float)$loss_of_personal_belongings_premium,
                                'consumables' => (float)$Consumable_premium,
                                'tyre_secure' => (float)$tyre_guard_premium
                            ],
                            'other' => [],
                        ];

                        $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                        $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                        $addons_data['other_premium'] = 0;

                        $applicable_addons = [
                            'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'engineProtector', 'ncbProtection', 'returnToInvoice', 'lopb','tyre_secure'
                        ];

                        if ($interval->y >= 5)
                        {
                            array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['return_to_invoice'] == 0)
                        {
                            array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['key_replace'] == 0)
                        {
                            array_splice($applicable_addons, array_search('keyReplace', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['engine_protector'] == 0)
                        {
                            array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['ncb_protection'] == 0)
                        {
                            array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['road_side_assistance'] == 0)
                        {
                            array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                        }

                        if ($addons_data['additional']['lopb'] == 0)
                        {
                            array_splice($applicable_addons, array_search('lopb', $applicable_addons), 1);
                        }
                    }

                    foreach($addons_data['additional'] as $k=>$v){
                        if($v == 0){
                            unset($addons_data['additional'][$k]);
                        }
                    }

                    $business_types = [
                        'newbusiness' => 'New Business',
                        'rollover' => 'Rollover',
                        'breakin' => 'Breakin'
                    ];

                    $data_response = [
                        'webservice_id' => $premium_data['webservice_id'],
                        'table' => $premium_data['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv'           => ($arr_premium['OutputResult']['IDVofthevehicle']),
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
                            'policy_type' => $tp_only ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive'),
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => $vehicle_idv,
                            'vehicle_registration_no' => $requestData->rto_code,
                            'voluntary_excess' => $voluntary_excess_discount,
                            'tppd_discount' => $tppd_discount,
                            'other_discount' => $other_discount,
                            'version_id' => $get_mapping_mmv_details->ic_version_code,
                            'selected_addon' => [],
                            'showroom_price' => $vehicle_idv,
                            'fuel_type' => $requestData->fuel_type,
                            'vehicle_idv' => $vehicle_idv,
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
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
                                'logo' => env('APP_URL').config('constants.motorConstant.logos').$productData->logo,
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
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
                                'car_age' => $car_age,
                                'ic_vehicle_discount' => $other_discount
                            ],
                            'basic_premium' => ($basic_od_premium),
                            'deduction_of_ncb' => (float)($ncb_discount),
                            'tppd_premium_amount' => ($basic_tp_premium),
                            'motor_electric_accessories_value' => (float)($electrical),
                            'motor_non_electric_accessories_value' => (float)($non_electrical),
                            // 'motor_lpg_cng_kit_value' => ($lpg_od_premium + $cng_od_premium),
                            'cover_unnamed_passenger_value' => ($pa_unnamed),
                            'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                            'default_paid_driver' => ($liabilities),
                            'motor_additional_paid_driver' => ($pa_paid_driver),
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                            'compulsory_pa_own_driver' => ($pa_owner_driver),
                            'total_accessories_amount(net_od_premium)' => '',
                            'total_own_damage' => '',
                            // 'cng_lpg_tp' => ($lpg_tp_premium + $cng_tp_premium),
                            'total_liability_premium' => '',
                            'net_premium' => ($arr_premium['OutputResult']['PremiumBreakUp']['NetPremium']),
                            'service_tax_amount' => ($arr_premium['OutputResult']['PremiumBreakUp']['SGST'] + $arr_premium['OutputResult']['PremiumBreakUp']['CGST']),
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'vehicle_lpg_cng_kit_value' => '',
                            'quotation_no' => '',
                            'premium_amount' => ($arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium']),
                            'antitheft_discount' => $antitheft,
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' =>(float) $final_total_discount,
                            'final_net_premium' => $final_net_premium,
                            'final_gst_amount' => ($final_gst_amount),
                            'final_payable_amount' =>  ($final_payable_amount),
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
                            "max_addons_selection"=> NULL,
                            'add_ons_data' => $addons_data,
                            'applicable_addons' => $applicable_addons,
                            'ribbon' =>  $ribbonMessage
                        ]
                    ];

                    if($external_fuel_kit == true || (int)$lpg_od_premium > 0 ||(int)$lpg_tp_premium > 0 || (int)$cng_od_premium > 0 || (int)$cng_tp_premium > 0) {
                        
                        $data_response['Data']['motor_lpg_cng_kit_value'] = ($lpg_od_premium + $cng_od_premium);
                        $data_response['Data']['cng_lpg_tp'] = ($lpg_tp_premium + $cng_tp_premium);
                    }

                    if (!empty($legal_liability_to_employee)) {
                        $data_response['Data']['other_covers'] = [
                            'LegalLiabilityToEmployee' => ($legal_liability_to_employee)
                        ];
                        $data_response['Data']['LegalLiabilityToEmployee'] = ($legal_liability_to_employee);
                    }
                    if(!empty($cpa_tenure)&&$requestData->business_type == 'newbusiness' && $cpa_tenure == '3')
                     {
                        //  unset($data_response['Data']['compulsory_pa_own_driver']);
                         $data_response['Data']['multi_Year_Cpa'] = $pa_owner_driver;
                     }

                }
                else
                {
                    $data_response = array(
                        'webservice_id' => $premium_data['webservice_id'],
                        'table' => $premium_data['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'msg' => isset($arr_premium['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $arr_premium['ErrorText']) : 'Error occured in premium calculation service'
                    );
                }
            }
            else
            {
                $data_response = [
                    'webservice_id' => $premium_data['webservice_id'],
                    'table' => $premium_data['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Error occured in premium calculation service'
                ];
            }
        }
        else
        {
            $data_response = array(
                'webservice_id' => $token['webservice_id'],
                'table' => $token['table'],
                'premium_amount' => 0,
                'status' => false,
                'msg' => isset($token_data['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_data['ErrorText']) : 'Error occured in token generation service'
            );
        }
    }
    else
    {
        $data_response = array(
            'webservice_id' => $token['webservice_id'],
            'table' => $token['table'],
            'premium_amount' => 0,
            'status' => false,
            'msg' => 'Error occured in token generation service'
        );
    }

    return camelCase($data_response);
}
