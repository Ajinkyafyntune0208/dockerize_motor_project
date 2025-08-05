<?php

use App\Models\MagmaBikePriceMaster;
use Carbon\Carbon;
use App\Models\MasterRto;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

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
    $mmv = get_mmv_details($productData, $requestData->version_id, 'magma');

    if ($mmv['status'] == 1)
    {
        $mmv = $mmv['data'];
    }
    else
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
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
            'request'=>[
                'mmv'=> $get_mapping_mmv_details,
                'version_id'=>$requestData->version_id
             ]
        ];
    }
    elseif ($get_mapping_mmv_details->ic_version_code == 'DNE')
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $get_mapping_mmv_details,
                'version_id'=>$requestData->version_id
             ]
        ];
    }

    $mmv_data['manf_name'] = $get_mapping_mmv_details->vehicle_manufacturer;
    $mmv_data['model_name'] = $get_mapping_mmv_details->vehicle_model_name;
    $mmv_data['version_name'] = $get_mapping_mmv_details->variant;
    $mmv_data['seating_capacity'] = $get_mapping_mmv_details->seating_capacity;
    $mmv_data['cubic_capacity'] = $get_mapping_mmv_details->cubic_capacity;
    $mmv_data['fuel_type'] = $get_mapping_mmv_details->fuel_type;

    $rto_data = MasterRto::where('rto_code', $requestData->rto_code)->where('status', 'Active')->first();

    if (empty($rto_data))
    {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO code does not exist',
            'request'=> [
                'rto_code'=>$requestData->rto_code,
                'rto_data'=>$rto_data
            ]
        ];
    }

    $rto_code = $requestData->rto_code;

    $rto_code = preg_replace("/OR/", "OD", $rto_code);

    if (str_starts_with(strtoupper($rto_code), "DL-0")) {
        $rto_code = RtoCodeWithOrWithoutZero($rto_code);
    }
    
    $rto_location = DB::table('magma_rto_location')
        ->where('rto_location_code', $rto_code)
        ->where('vehicle_class_code', '37')
        ->first();

    if (empty($rto_location))
    {
        return [
            'status' => false,
            'premium' => 0,
            'message' => 'RTO details does not exist with insurance company',
            'request'=> [
                'rto_number'=> $rto_data->rto_number,
                'rto_location'=>$rto_location
            ]
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    // $rto_code = $requestData->rto_code;
    // Re-arrange for Delhi RTO code - start 
    // $rto_code = explode('-', $rto_code);

    // if ((int) $rto_code[1] < 10)
    // {
    //     $rto_code[1] = '0' . (int) $rto_code[1];
    // }

    // $rto_code = implode('-', $rto_code);

    $motor_manf_date = '01-' . $requestData->manufacture_year;
    $bike_age = 0;
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $bike_age = floor($age / 12);

    if ($interval->y >= 10 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC'))))
    {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'Zero dep is not allowed for vehicle age greater than 10 years',
            'request'=> [
                'bike_age'=> $bike_age,
                'productData'=>$productData->zero_dep
            ]
        ];
    }

    $policy_expiry_date = $requestData->previous_policy_expiry_date;
    $is_tp = (($premium_type == 'third_party') || ($premium_type == 'third_party_breakin'));
    if ($requestData->business_type == 'newbusiness')
    {
        $businesstype = 'New Business';
        $policy_start_date = date('d/m/Y');
        $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');
        $policy_end_date_d_m_y = date('d-m-Y', strtotime('+5 years -1 day', strtotime($policy_start_date_d_m_y)));
        $IsPreviousClaim = '0';
        $prepolstartdate = '01/01/1900';
        $prepolicyenddate = '01/01/1900';
        $PolicyProductType = ($is_tp) ? '5TP' : '5TP1OD';
        $proposal_date = $policy_start_date;
    }
    else
    {
        $businesstype =  'Roll Over';#$premium_type == 'own_damage' ? 'SOD Roll Over' :
        $PolicyProductType = (in_array($premium_type,['own_damage','own_damage_breakin']) ? '1OD' :(($is_tp) ? '1TP' : '1TP1OD'));
        $policy_start_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date . ' + 1 days'));
        $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');

        if ($requestData->business_type == 'breakin')
        {
            $policy_start_date = date('d/m/Y');
            $policy_start_date_d_m_y = Carbon::createFromFormat('d/m/Y', $policy_start_date)->format('d-m-Y');
//            if ($productData->premium_type_id == 2)
//            {
                $today = date('d-m-Y');
                $policy_start_date_d_m_y = date('d-m-Y', strtotime($today . ' + 2 days'));
                $policy_start_date = date('d/m/Y', strtotime($policy_start_date_d_m_y));
            //}
        }
        $policy_end_date_d_m_y = date('d-m-Y', strtotime('+1 year -1 day', strtotime($policy_start_date_d_m_y)));
        $proposal_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));
        $IsPreviousClaim = $requestData->is_claim == 'N' ? 1 : 0;
        if($requestData->is_multi_year_policy == 'Y')
        {
            if($premium_type == 'own_damage')
            {
                $prepolstartdate = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($policy_expiry_date)))));
            }
            else
            {
                //$prepolstartdate = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-5 year +1 day', strtotime($policy_expiry_date)))));
                $prepolstartdate = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($policy_expiry_date)))));
            }   
        }
        else
        {
           $prepolstartdate = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($policy_expiry_date)))));  
        }
        
        $prepolicyenddate = date('d/m/Y', strtotime($policy_expiry_date));
    }

    $policy_end_date = date('d/m/Y', strtotime($policy_end_date_d_m_y));
    $prev_policy_end_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
    $manufacturingyear = date("Y", strtotime($requestData->manufacture_year));
    $first_reg_date = date('d/m/Y', strtotime($requestData->vehicle_register_date));
    $vehicle_idv = 0;
    $vehicle_in_90_days = 0;

    if (isset($term_start_date))
    {
        $vehicle_in_90_days = (strtotime(date('Y-m-d')) - strtotime($term_start_date)) / (60 * 60 * 24);

        if ($vehicle_in_90_days > 90)
        {
            $requestData->ncb_percentage = 0;
        }
    }

    $selected_addons = DB::table('selected_addons')
        ->where('user_product_journey_id', $enquiryId)
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

    $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
        'method' => 'Token Generation',
        'requestMethod' => 'post',
        'type' => 'tokenGeneration',
        'section' => $productData->product_sub_type_code,
        'enquiryId' => $enquiryId,
        'productName' => $productData->product_name,
        'transaction_type' => 'quote'
    ]);

    // $get_response = cache()->remember('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN', 60 * 45, function() use ($tokenParam,$productData, $enquiryId) {
    //     return $token = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
    //         'method' => 'Token Generation',
    //         'requestMethod' => 'post',
    //         'type' => 'tokenGeneration',
    //         'section' => $productData->product_sub_type_code,
    //         'enquiryId' => $enquiryId,
    //         'productName' => $productData->product_name,
    //         'transaction_type' => 'quote'
    //     ]);    
    // });

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
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 5;
        }
        else
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 1;
        }
    }

    $token = $get_response['response'];

    if ($token)
    {
        $token_data = json_decode($token, true);

        if (isset($token_data['access_token']))
        {
            $vehicle_registration_no = $requestData->business_type == 'newbusiness' ? 'NEW' : explode('-', $rto_code)[0].'-'.explode('-', $rto_code)[1].'-ZZ-0003';
            if(strlen($requestData->vehicle_registration_no) >= 8 && $requestData->business_type != 'newbusiness')
            {
                $vehicle_registration_no = $requestData->vehicle_registration_no;
            }

            $vehicle_price = MagmaBikePriceMaster::select('vehicle_selling_price')
            ->where([
                'vehicle_class_code' => '37',
                'vehicle_model_code' => $get_mapping_mmv_details->vehicle_model_code,
                'rto_location_name' => 'MUMBAI'
                // 'rto_location_name' => $rto_location->rto_location_description
            ])->first();

            if (!$is_tp && empty($vehicle_price->vehicle_selling_price ?? null)) {
                return [
                    'status' => false,
                    'msg' => 'Ex-showroom Price not exist in the master',
                    'request' => [
                        'message' => 'Ex-showroom Price not exist in the master',
                        'vehicle_class_code' => 37,
                        'vehicle_model_code' => $get_mapping_mmv_details->vehicle_model_code,
                        'rto_location_name' => $rto_location->rto_location_description
                    ]
                ];
            }

            $model_config_premium = [
                'BusinessType' => $businesstype,
                'PolicyProductType' => $PolicyProductType,
                'ProposalDate' => $proposal_date,
                'CompulsoryExcessAmount' => '100',
                'VoluntaryExcessAmount' => '0',
                'ImposedExcessAmount' => '',
                'VehicleDetails' => [
                    'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'TempRegistrationDate' => '',#date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'RegistrationNumber' => strtoupper($vehicle_registration_no),//($requestData->business_type == 'newbusiness') ? 'NEW' : ( explode('-', $requestData->vehicle_registration_no ?? $requestData->rto_code.'-'.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2).'-'.substr(str_shuffle('1234567890'), 1, 4))),
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
                    'YearOfManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                    'VehicleClassCode' => $get_mapping_mmv_details->vehicle_class_code,
                    'SeatingCapacity' => $get_mapping_mmv_details->seating_capacity,
                    'CarryingCapacity' => $get_mapping_mmv_details->carrying_capacity,
                    'BodyTypeCode' => $get_mapping_mmv_details->body_type_code,
                    'BodyTypeName' => $get_mapping_mmv_details->body_type,
                    'FuelType' => $get_mapping_mmv_details->fuel_type,
                    'SeagmentType' => $get_mapping_mmv_details->segment_type,
                    'TACMakeCode' => '',
                    'ExShowroomPrice' => $vehicle_price->vehicle_selling_price ?? '',
                    'IDVofVehicle' => '',
                    'HigherIDV' => '',
                    'LowerIDV' => '',
                    'IDVofChassis' => '',
                    'Zone' => 'Zone-' . $rto_location->registration_zone,
                    'IHoldValidPUC' => 'true',
                    'InsuredHoldsValidPUC' => 'false',
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
                    'BusinessSourceType' => 'P_AGENT',
                    'PolicyEffectiveFromDate' => $policy_start_date,
                    'PolicyEffectiveToDate' => $policy_end_date,
                    'PolicyEffectiveFromHour' => '00:00',//$time,
                    'PolicyEffectiveToHour' => '23:59',
                    'SPCode' => config('constants.IcConstants.magma.MAGMA_SPCode'),
                    'SPName' => config('constants.IcConstants.magma.MAGMA_SPName'),
                ],
                'AddOnsPlanApplicable' => false,
                'AddOnsPlanApplicableDetails' => NULL,
                'OptionalCoverageApplicable' => false,
                'OptionalCoverageDetails' => NULL,
                'IsPrevPolicyApplicable' => ($requestData->business_type != 'newbusiness') ? 'true' : 'false',
                'PrevPolicyDetails' => $requestData->business_type == 'newbusiness' ? NULL : [
                    'PrevNCBPercentage' => $requestData->previous_ncb,
                    'PrevInsurerCompanyCode' => 'CMGI',
                    'HavingClaiminPrevPolicy' => $requestData->is_claim == 'N' ? 'false' : 'true',
                    'PrevPolicyEffectiveFromDate' => $prepolstartdate,
                    'PrevPolicyEffectiveToDate' => $prepolicyenddate,
                    'PrevPolicyNumber' => (string) time(),#'123456',
                    'PrevPolicyType' => ($requestData->previous_policy_type == 'Own-damage') ? 'Standalone OD' : ($requestData->previous_policy_type == 'Third-party' ? 'LiabilityOnly' :'PackagePolicy'),
                    'PrevAddOnAvialable' => $productData->zero_dep == 0 ? 'true' : 'false',
                    'PrevPolicyTenure' => '1',
                    'IIBStatus' => 'Not Applicable',
                    'PrevInsuranceAddress' => 'ARJUN NAGAR',
                ]
            ];
            if($requestData->previous_policy_type == 'Not sure')
            {
                $model_config_premium['IsPrevPolicyApplicable'] = false;
                $model_config_premium['PrevPolicyDetails'] = null;
            }
            if($requestData->previous_policy_type_identifier_code == '15')
            {
                $model_config_premium['PrevPolicyDetails']['PrevPolicyType'] = 'Bundled Policy';
            }
            if ($requestData->business_type == 'breakin' && $requestData->previous_policy_type != 'Not sure'){
                $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
                ##if breakin with more than 90 days IsPrevPolicyApplicable set to false
                if ($date_difference > 90) {
                    $model_config_premium['IsPrevPolicyApplicable'] = false;
                    $model_config_premium['PrevPolicyDetails'] = null;
                }
            }
            if($is_pos)
            {
                $model_config_premium['GeneralProposalInformation']['POSPCode'] = $pos_code;
                $model_config_premium['GeneralProposalInformation']['POSPName'] = $pos_name;
            }

            if (in_array($premium_type,['own_damage','own_damage_breakin']))
            {
                $model_config_premium['IsTPPolicyApplicable'] = true;
                $model_config_premium['PrevTPPolicyDetails'] = [
                    'PolicyNumber'      => (string) time(),
                    'PolicyType'        => 'LiabilityOnly',
                    'InsurerName'       => 'BAJAJ',
                    'TPPolicyStartDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                    'TPPolicyEndDate'   => date('d/m/Y', strtotime('+5 year -1 day', strtotime($requestData->vehicle_register_date)))
                ];
            }

            $zero_dep_applicable = $productData->zero_dep == 0 && $interval->y < 5 ? true : false;
            $return_to_invoice_applicable = $interval->y < 3 ? true : false;
            $return_to_invoice_applicable = false; #rti cover is not available for tw confirmed by ic
            $consumable_applicable = $requestData->business_type == 'newbusiness' && !$is_tp && $interval->y < 5;
            $rsa_applicable = !$is_tp && $interval->y < 5;
            $key_replacement_applicable = false;//!$is_tp && $interval->y < 5;
            if(config('MAGMA_BIKE_ENABLE_CONSUMABLE') == 'N'){
                $consumable_applicable = false;
            }

            if ($zero_dep_applicable || $return_to_invoice_applicable || $consumable_applicable || $key_replacement_applicable || $rsa_applicable)
            {
                $model_config_premium['AddOnsPlanApplicable'] = true;
                $model_config_premium['AddOnsPlanApplicableDetails'] = [
                    'PlanName' => 'Optional Add on',
                    'ZeroDepreciation' =>  $zero_dep_applicable,
                    'ReturnToInvoice' => $return_to_invoice_applicable,
                    'Consumables' => $consumable_applicable,
                    'RoadSideAssistance' => $rsa_applicable,
                    //'KeyReplacement' => $key_replacement_applicable,
                ];

                // $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacement'] = false ;
                // $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = null;
                
                // if($key_replacement_applicable){
                //     $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacement'] =  true ;
                //     $model_config_premium['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = [
                //         "KeyReplacementSI" => "5000",
                //     ];
                // }
            }

            $selected_addons = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                ->first();

            if ($requestData->vehicle_owner_type == 'I' &&  !in_array($premium_type,['own_damage','own_damage_breakin']))
            {
                $model_config_premium['PAOwnerCoverApplicable'] = true;
                $model_config_premium['PAOwnerCoverDetails'] = [
                    'PAOwnerSI'                => '1500000',
                    'PAOwnerTenure'            => isset($cpa_tenure) ? $cpa_tenure :'1',
                    'ValidDrvLicense'          => true,
                    'DoNotHoldValidDrvLicense' => false,
                    'Ownmultiplevehicles'      => false,
                    'ExistingPACover'          => false,
                ];
            }
            // print_r($selected_addons);exit;
            if ($selected_addons['accessories'] && $selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '' && !($is_tp))
            {
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['accessories'] as $accessory) {
                    if ($accessory['name'] == 'Electrical Accessories') {
                        $model_config_premium['OptionalCoverageDetails']['ElectricalApplicable'] = true;
                        $model_config_premium['OptionalCoverageDetails']['ElectricalDetails'] = [
                            [
                                'Description' => 'Head Light',
                                'ElectricalSI' => (string) $accessory['sumInsured'] ,
                                'SerialNumber' => '2',
                                'YearofManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year))
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
                                'YearofManufacture' => date('Y', strtotime('01-'.$requestData->manufacture_year))
                            ],
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

                    if ($additional_cover['name'] == 'Geographical Extension') {
                        if(config('MAGMA_CAR_BIKE_ENABLE_GEOGRAPHICAL_EXTENSION') == 'Y')
                        {
                            //MAGMA REQUIRES UW UNDERWRITING APPROVAL FOR GEO EXTENSION IF BROKER AGRESS TO IT PLEASE ENABLE THIS CONFIG
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
            }

            if ($selected_addons['discounts'] && $selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '')
            {                
                $model_config_premium['OptionalCoverageApplicable'] = true;

                foreach ($selected_addons['discounts'] as $discount)
                {
                    if ($discount['name'] == 'anti-theft device')
                    {
                        $model_config_premium['OptionalCoverageDetails']['ApprovedAntiTheftDevice'] = true;
                        $model_config_premium['OptionalCoverageDetails']['CertifiedbyARAI'] = true;
                    }

                    if ($discount['name'] == 'voluntary_insurer_discounts')
                    {
                        $model_config_premium['VoluntaryExcessAmount'] = $discount['sumInsured'];
                    }

                    if ($discount['name'] == 'TPPD Cover' && $premium_type != 'comprehensive')
                    {
                        $model_config_premium['OptionalCoverageDetails']['TPPDDiscountApplicable'] = true;
                    }
                }
            }

            if((int)$productData->default_discount > 0 && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                $model_config_premium['GeneralProposalInformation']['DetariffDis'] = (string) $productData->default_discount;
            }

            if(!isset($model_config_premium['OptionalCoverageDetails']))
            {
                $model_config_premium['OptionalCoverageApplicable'] = false;
            }

            $isagentDiscountAllowed = false;
            if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
                $agentDiscount = calculateAgentDiscount($enquiryId, 'magma', 'bike');
                if ($agentDiscount['status'] ?? false) {
                    $isagentDiscountAllowed = true;
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

            $temp_data = $model_config_premium;
            unset($temp_data['PrevPolicyDetails']['PrevPolicyNumber'], $temp_data['PrevTPPolicyDetails']['PolicyNumber']);
            $checksum_data = checksum_encrypt($temp_data);
            $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'magma', $checksum_data, 'BIKE');
            if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                $get_response = $is_data_exist_for_checksum;
            }else{
                $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_BIKE_GETPREMIUM'), $model_config_premium, 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Premium Calculation',
                    'requestMethod'    => 'post',
                    'type'             => 'premiumCalculation',
                    'token'            => $token_data['access_token'],
                    'enquiryId'        => $enquiryId,
                    'checksum'         => $checksum_data,
                    'productName'      => $productData->product_name,
                    'transaction_type' => 'quote',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token_data['access_token']
                    ]
                ]);
            }

            $premium_data = $get_response['response'];
            if ($premium_data)
            {
                $arr_premium = json_decode($premium_data, true);
                $skip_second_call = false;
                if (isset($arr_premium['ServiceResult']) && $arr_premium['ServiceResult'] == "Success")
                {
                    $max_idv = $arr_premium['OutputResult']['HigherIDV'];
                    $min_idv = $arr_premium['OutputResult']['LowerIDV'];
                    $vehicle_idv = $arr_premium['OutputResult']['IDVofthevehicle'];

                    if ($premium_type != 'third_party')
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
                        update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success" );
                        if(!$skip_second_call){
                        //For every API call we need to use unique token
                        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
                            'method' => 'Token Generation',
                            'requestMethod' => 'post',
                            'type' => 'tokenGeneration',
                            'section' => $productData->product_sub_type_code,
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_name,
                            'transaction_type' => 'quote'
                        ]);
                        $token2 = $get_response['response'];
                        $token_data2 = json_decode($token2, true);
                        if (!isset($token_data2['access_token'])) {
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'msg' => isset($token_data2['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_data2['ErrorText']) : 'Error occured in token generation service'
                            ];
                        }

                        $temp_data = $model_config_premium;
                        unset($temp_data['PrevPolicyDetails']['PrevPolicyNumber'], $temp_data['PrevTPPolicyDetails']['PolicyNumber']);
                        $checksum_data = checksum_encrypt($temp_data);
                        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'magma', $checksum_data, 'BIKE');
                        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                            $get_response = $is_data_exist_for_checksum;
                        }else{
                            $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_BIKE_GETPREMIUM'), $model_config_premium, 'magma', [
                                'section'          => $productData->product_sub_type_code,
                                'method'           => 'Premium Recalculation',
                                'requestMethod'    => 'post',
                                'type'             => 'premiumCalculation',
                                'token'            => $token_data2['access_token'],
                                'enquiryId'        => $enquiryId,
                                'checksum'         => $checksum_data,
                                'productName'      => $productData->product_name,
                                'transaction_type' => 'quote',
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $token_data2['access_token']
                                ]
                            ]);
                        }
                    }
                        $premium_data = $get_response['response'];
                        if ($premium_data)
                        {
                            $arr_premium = json_decode($premium_data, true);

                            if ( ! isset($arr_premium['ServiceResult']) || $arr_premium['ServiceResult'] != "Success")
                            {
                                $err = $arr_premium['ErrorText'] ?? $arr_premium['Message'] ?? 'Error occured in premium re-calculation service';
                                $errMsg =  preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $err);
                                return [
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'status' => false,
                                    'msg' => $errMsg
                                ];
                            }
                        }
                    }

                    $Nil_dep = $basic_tp_premium = $basic_od_premium = $pa_unnamed = $ncb_discount = $liabilities = $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $lpg_od_premium = $cng_od_premium = $lpg_tp_premium = $cng_tp_premium = $antitheft = $tppd_discount = $return_to_invoice = $voluntary_excess_discount = $other_discount = 
                    $GeogExtension_od = $GeogExtension_tp = 0;
                    $non_electrical_discount = $electrical_discount = 0;
                    $consumables = 0;
                    $road_side_assistance = 0;

                    if (isset($arr_premium['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'])) {
                        $add_array = $arr_premium['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

                        foreach ($add_array as $add1) {
                            if ($add1['AddOnCoverType'] == 'Basic OD') {
                                $basic_od_premium = (float)($add1['AddOnCoverTypePremium']);
                            }

                            if ($add1['AddOnCoverType'] == "Basic TP") {
                                $basic_tp_premium = (float)($add1['AddOnCoverTypePremium']);
                            }

                            if ($add1['AddOnCoverType'] == "PA Owner Driver") {
                                $pa_owner_driver = (float)($add1['AddOnCoverTypePremium']);
                            }

                            if ($add1['AddOnCoverType'] == "Zero Depreciation") {
                                $Nil_dep = (float)($add1['AddOnCoverTypePremium']);
                            }

                            if ($add1['AddOnCoverType'] == "LL to Paid Driver IMT 28") {
                                $liabilities = (float)($add1['AddOnCoverTypePremium']);
                            }
                            if ($add1['AddOnCoverType'] == "Road Side Assistance") {
                                $road_side_assistance = (float)($add1['AddOnCoverTypePremium']);
                            }
                            if ($add1['AddOnCoverType'] == "Return To Invoice") {
                                $return_to_invoice = (float)($add1['AddOnCoverTypePremium']);
                            }
                            if ($add1['AddOnCoverType'] == "Consumables") {
                                $consumables = (float)($add1['AddOnCoverTypePremium']);
                            }
                            
                        }
                    }

                    if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers']))
                    {
                        $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];

                        foreach ($optionadd_array as $add)
                        {
                            if ($add['OptionalAddOnCoversName'] == "Personal accident cover Unnamed")
                            {
                                $pa_unnamed = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Electrical or Electronic Accessories")
                            {
                                $electrical = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Non Electrical Accessories")
                            {
                                $non_electrical = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Geographical Extension - OD") {
                                $GeogExtension_od = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Geographical Extension - TP") {
                                $GeogExtension_tp = (float)($add['AddOnCoverTotalPremium']);
                            }//below values are getting in optionaladdoncovers
                            if ($add['OptionalAddOnCoversName'] == 'Basic OD') {
                                $basic_od_premium = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Basic TP") {
                                $basic_tp_premium = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "PA Owner Driver") {
                                $pa_owner_driver = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Zero Depreciation") {
                                $Nil_dep = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "LL to Paid Driver IMT 28") {
                                $liabilities = (float)($add['AddOnCoverTotalPremium']);
                            }
                            if ($add['OptionalAddOnCoversName'] == "Road Side Assistance") {
                                $road_side_assistance = (float)($add['AddOnCoverTotalPremium']);
                            }
                            if ($add['OptionalAddOnCoversName'] == "Return To Invoice") {
                                $return_to_invoice = (float)($add['AddOnCoverTotalPremium']);
                            }

                            if ($add['OptionalAddOnCoversName'] == "Consumables") {
                                $consumables = (float)($add['AddOnCoverTotalPremium']);
                            }
                        }
                    }

                    if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Discount']))
                    {
                        $discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Discount'];

                        foreach ($discount_array as $discount)
                        {
                            if ($discount['DiscountType'] == "Anti-Theft Device - OD")
                            {
                                $antitheft = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Basic TP - TPPD Discount")
                            {
                                $tppd_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Voluntary Excess Discount-OD")
                            {
                                $voluntary_excess_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Basic OD - Detariff Discount")
                            {
                                $other_discount += (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Electrical or Electronic Accessories - Detariff Discount on Elecrical Accessories")
                            {
                                // $other_discount += (float)($discount['DiscountTypeAmount']);
                                $electrical_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "Non Electrical Accessories - Detariff Discount")
                            {
                                // $other_discount += (float)($discount['DiscountTypeAmount']);
                                $non_electrical_discount = (float)($discount['DiscountTypeAmount']);
                            }
                            elseif ($discount['DiscountType'] == "No Claim Bonus Discount")
                            {
                                $ncb_discount += (float)($discount['DiscountTypeAmount']);
                            }
                        }
                    }

                    $electrical -= $electrical_discount;
                    $non_electrical -= $non_electrical_discount;

                    $ribbonMessage = null;

                    if (($model_config_premium['GeneralProposalInformation']['DetariffDis'] ?? false) && $isagentDiscountAllowed) {
                        $agentDiscountPercentage = $arr_premium['OutputResult']['AppliedDiscount'];
                        if ($model_config_premium['GeneralProposalInformation']['DetariffDis'] != $agentDiscountPercentage) {
                            $ribbonMessage = config('OD_DISCOUNT_RIBBON_MESSAGE', 'Max OD Discount') . ' ' . $agentDiscountPercentage . '%';
                        }
                    }

                    $final_od_premium = $basic_od_premium + $electrical + $non_electrical + $GeogExtension_od;
                    //$ncb_discount = ($final_od_premium - $antitheft - $voluntary_excess_discount) * ($requestData->applicable_ncb / 100);
                    $final_tp_premium = $basic_tp_premium + $liabilities + $pa_unnamed + $GeogExtension_tp;
                    $final_total_discount = $ncb_discount + $antitheft + $tppd_discount + $voluntary_excess_discount + $other_discount;
                    $final_net_premium = ($basic_od_premium + $final_tp_premium - $final_total_discount);
                    $final_gst_amount = ($final_net_premium * 0.18);
                    $final_payable_amount = $final_net_premium + $final_gst_amount;
                    $applicable_addons = [];
                    $addons_data = [
                        'in_built' => [],
                        'additional' => [],
                        'other_premium' => 0
                    ];

                    if ($premium_type != 'third_party')
                    {
                        $addons_data = [
                            'in_built' => [],
                            'additional' => [
                                'zero_depreciation'    => (float)$Nil_dep,
                                'return_to_invoice'    => (float)$return_to_invoice,
                                'consumables' => (float) $consumables,
                                'roadSideAssistance' => (float)$road_side_assistance
                            ],
                            'other' => []
                        ];

                        $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                        $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                        $addons_data['other_premium'] = 0;
                        $applicable_addons = [
                            'zeroDepreciation', 'returnToInvoice', 'consumables', 'roadSideAssistance'
                        ];

                        if (
                            $interval->y >= 10 &&
                            ($index = array_search('zeroDepreciation', $applicable_addons)) !== false
                        ) {
                            array_splice($applicable_addons, $index, 1);
                        }

                        if (
                            $addons_data['additional']['return_to_invoice'] == 0
                            && ($index = array_search('returnToInvoice', $applicable_addons)) !== false
                        ) {
                            array_splice($applicable_addons, $index, 1);
                        }

                        if (
                            $addons_data['additional']['roadSideAssistance'] == 0
                            && ($index = array_search('roadSideAssistance', $applicable_addons)) !== false
                        ) {
                            array_splice($applicable_addons, $index, 1);
                        }

                        if (
                            $addons_data['additional']['consumables'] == 0
                            && ($index = array_search('consumables', $applicable_addons)) !== false
                        ) {
                            array_splice($applicable_addons, $index, 1);
                        }
                    }

                    if (in_array($premium_type, ['third_party', 'third_party_breakin']))
                    {
                        $policy_type = 'Third Party';
                    }
                    elseif ($premium_type == 'own_damage')
                    {
                        $policy_type = 'Own Damage';
                    }
                    else
                    {
                        $policy_type = 'Comprehensive';
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
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv' => !($is_tp) ? ($arr_premium['OutputResult']['IDVofthevehicle']) : 0,
                            'min_idv' => !($is_tp) ? ceil($min_idv) : 0,
                            'max_idv' => !($is_tp) ? floor($max_idv) : 0,
                            'vehicle_idv' =>  ($arr_premium['OutputResult']['IDVofthevehicle']),
                            'qdata' => null,
                            'pp_enddate' => $prev_policy_end_date,
                            'addonCover' => null,
                            'addon_cover_data_get' => '',
                            'rto_decline' => null,
                            'rto_decline_number' => null,
                            'mmv_decline' => null,
                            'mmv_decline_name' => null,
                            'policy_type' => $policy_type,
                            'business_type' => $business_types[$requestData->business_type],
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => '',
                            'vehicle_registration_no' => $requestData->rto_code,
                            'rto_no' => $requestData->rto_code,
                            'version_id' => $get_mapping_mmv_details->ic_version_code,
                            'selected_addon' => [],
                            'showroom_price' => 0,
                            'fuel_type' => $requestData->fuel_type,
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
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
                                'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                                'product_sub_type_name' => $productData->product_sub_type_name,
                                'flat_discount' => $productData->default_discount,
                                'predefine_series' => '',
                                'is_premium_online' => $productData->is_premium_online,
                                'is_proposal_online' => $productData->is_proposal_online,
                                'is_payment_online' => $productData->is_payment_online,
                            ],
                            'motor_manf_date' => $requestData->vehicle_register_date,
                            'vehicle_register_date' => $requestData->vehicle_register_date,
                            'vehicleDiscountValues' => [
                                'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
                                'bike_age' => $bike_age,
                                'aai_discount' => 0,
                                'ic_vehicle_discount' => (abs($final_total_discount)),
                            ],
                            'other_discount' => (abs($other_discount)),
                            'ic_vehicle_discount' => (abs($other_discount)),
                            'basic_premium' => (float) $basic_od_premium,
                            'deduction_of_ncb' =>  (float) ($ncb_discount),
                            'tppd_premium_amount' =>  ($basic_tp_premium),
                            'motor_electric_accessories_value' => (float) ($electrical),
                            'motor_non_electric_accessories_value' => (float) ($non_electrical),
                            'motor_lpg_cng_kit_value' =>  ($lpg_od_premium + $cng_od_premium),
                            'cover_unnamed_passenger_value' =>  ($pa_unnamed),
                            'seating_capacity' => $get_mapping_mmv_details->seating_capacity,
                            'default_paid_driver' =>  ($liabilities),
                            'motor_additional_paid_driver' =>  ($pa_paid_driver),
                            'compulsory_pa_own_driver' =>  ($pa_owner_driver),
                            'total_accessories_amount(net_od_premium)' => '',
                            'total_own_damage' =>  (float)$basic_od_premium,
                            'cng_lpg_tp' =>  ($lpg_tp_premium + $cng_tp_premium),
                            'total_liability_premium' => '',
                            'net_premium' => ($arr_premium['OutputResult']['PremiumBreakUp']['NetPremium']),
                            'service_tax_amount' =>  ($arr_premium['OutputResult']['PremiumBreakUp']['SGST'] + $arr_premium['OutputResult']['PremiumBreakUp']['CGST']),
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'vehicle_lpg_cng_kit_value' => '',
                            'quotation_no' => '',
                            'premium_amount' => ($arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium']),
                            'antitheft_discount' => $antitheft,
                            'voluntary_excess' => $voluntary_excess_discount,
                            'tppd_discount' => $tppd_discount,
                            'GeogExtension_ODPremium' => $GeogExtension_od,
                            'GeogExtension_TPPremium' => $GeogExtension_tp,
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' => (float)($final_total_discount),
                            'final_net_premium' => $final_net_premium,
                            'final_gst_amount' => ($final_gst_amount),
                            'final_payable_amount' => ($final_payable_amount),
                            'service_data_responseerr_msg' => '',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
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
                    if(!empty($cpa_tenure)&&$requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
                     {
                         $data_response['Data']['multi_Year_Cpa'] = $pa_owner_driver;
                     }
                }
                else
                {
                    $err = $arr_premium['ErrorText'] ?? $arr_premium['Message'] ?? 'Error occured in premium re-calculation service';
                    $errMsg =  preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $err);
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'msg' => $errMsg
                    ];
                }
            }
            else
            {
                $data_response = [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Error occured in premium calculation service'
                ];
            }
        }
        else
        {
            $data_response = array(
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'msg' => isset($token_data['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_data['ErrorText']) : 'Error occured in token generation service'
            );
        }
    }
    else
    {
        $data_response = [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Error occured in token generation service'
        ];
    }

    return camelCase($data_response);
}
