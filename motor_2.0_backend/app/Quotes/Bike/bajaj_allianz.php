<?php
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
use App\Models\MasterProduct;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Str;
use App\Quotes\Bike\V1\bajaj_allianz as BAJAJ_ALLIANZ_V1;

function getQuote($enquiryId, $requestData, $productData)
{
    if(config('IC.BAJAJ_ALLIANZ.V1.BIKE.ENABLE') == 'Y'){
        return BAJAJ_ALLIANZ_V1::getQuote($enquiryId, $requestData, $productData);
    }
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
   
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $bike_age = $interval->y;
    $bike_age = car_age($requestData->vehicle_register_date,$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    if ($bike_age >= 5 && $productData->zero_dep == 0 && in_array($productData->company_alias, explode(',', config('BIKE_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request'=>array('bike age'=>$bike_age)
        ];
    }
    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    if (($interval->y >= 19) && ($tp_check == 'true')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 19 years',
            ];
        }
    if (($bike_age > 14) && in_array($premium_type,['comprehensive','own_damage','breakin','own_damage_breakin']))
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Policy not allowed for vehicle age more than 15 years',
            'request'=>array('bike age'=>$bike_age)
        ];
    }
    $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
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
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

    $is_breakin     = ($requestData->business_type != "breakin" ? false : true);

    if($premium_type == 'breakin')
    {
        $premium_type = 'comprehensive';
    }
    if($premium_type == 'third_party_breakin')
    {
        $premium_type = 'third_party';
    }
    if($premium_type == 'own_damage_breakin')
    {
        $premium_type = 'own_damage';
    }

    $tp_only   = ($premium_type == 'third_party');
    $is_od          = ($premium_type == 'own_damage');
    
    $noPreviousPolicyData = ($requestData->previous_policy_type == 'Not sure');
    $selected_CPA = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();  
    if ($selected_CPA && $selected_CPA->compulsory_personal_accident != NULL && $selected_CPA->compulsory_personal_accident != '') {
        $addons = $selected_CPA->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : '1';
                
            }
        }
    }

    if ($requestData->business_type == 'newbusiness') {
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        if($date_difference > 0) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Registration date cannot be less than today',
                'request'=>[
                    'business_type'=>$requestData->business_type,
                    'date_difference' => $date_difference
                ]
            ];
        }

        
        $BusinessType = '1';
        $polType = ($premium_type == 'third_party') ? '3' : '1';
        $termStartDate = Carbon::today()->format('d-M-Y');
        $termEndDate   =  Carbon::parse($termStartDate)->addYear(5)->subDay(1)->format('d-M-Y');
        // $extCol24 = '5';
        $extCol24 = isset($cpa_tenure) ? $cpa_tenure : '5';
    } else if($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
        // $extCol24 = '1';
        $extCol24 = isset($cpa_tenure) ? $cpa_tenure : '1';
        $polType = '3';
        $BusinessType = '2';
        if($noPreviousPolicyData)
        {
            $requestData->applicable_ncb = 0;
            if ($premium_type == 'own_damage') {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'OD break-in quotes not available',
                    'request'=>[
                        'business_type'=>$requestData->business_type,
                        'policy_type' => $premium_type
                    ]
                ];
            } else if($premium_type == "third_party") {
                $termStartDate = Carbon::today()->addDay(3)->format('d-M-Y');
            } else {
                $termStartDate = Carbon::today()->addDay(2)->format('d-M-Y');
            }
        }
        else
        {
            $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);

            if ($requestData->is_claim == 'N') { ##if breakin with more than 90 days ncb set to 0
                if ($date_difference > 90 || $requestData->previous_policy_type == 'Third-party') {
                    $requestData->applicable_ncb = 0;
                }
            }

            if ($noPreviousPolicyData || in_array($requestData->previous_policy_expiry_date, [null, 'New']) || $date_difference > 0) {
                if ($premium_type == 'own_damage') {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'OD break-in quotes not available',
                        'request'=>[
                            'business_type'=>$requestData->business_type,
                            'date_difference' => $date_difference,
                            'policy_type' => $premium_type
                        ]
                    ];
                } else if($premium_type == "third_party") {
                    $termStartDate = Carbon::today()->addDay(3)->format('d-M-Y');
                } else {
                    $termStartDate = Carbon::today()->addDay(2)->format('d-M-Y');
                }
            } else {
                $termStartDate = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            }   
        }
        $termEndDate = Carbon::parse($termStartDate)->addYear(1)->subDay(1)->format('d-M-Y');
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'No quotes available',
            'request'=>[
                'business_type'=>$requestData->business_type,
                'policy_type' => $premium_type
            ]
        ];
    }

    if ($premium_type == "third_party") {
        $product_code = config("constants.motor.bajaj_allianz.PRODUCT_CODE_TP_BAJAJ_ALLIANZ_BIKE");
        $requestData->applicable_ncb = 0;
        $requestData->previous_ncb = 0;
    } else if ($requestData->business_type == 'newbusiness') {
        $product_code = config("constants.motor.bajaj_allianz.PRODUCT_CODE_NEW_BUSINESS_BAJAJ_ALLIANZ_BIKE");
    } else if ($premium_type == 'own_damage') {
        $product_code = config("constants.motor.bajaj_allianz.PRODUCT_CODE_OD_BAJAJ_ALLIANZ_BIKE");
    } else {
        $product_code = config("constants.motor.bajaj_allianz.PRODUCT_CODE_BAJAJ_ALLIANZ_BIKE");
    }

    if (trim($mmv_data->ic_version_code) == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle not mapped',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } else if(trim($mmv_data->ic_version_code) == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle does not exist with insurance company',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } else {
        $is_pos    = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_name  = $pos_type  = $pos_code  = $pos_aadhar = $pos_pan  = $pos_mobile = $extCol40 = '';

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
                $extCol40   = $pos_data->pan_no;
            }
        }

        $cover_data = [];
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        ##need to handle NCB things
        $is_geo_ext = false;
        $NonElectricalaccessSI = $ElectricalaccessSI = 0;

        $is_electrical = $is_non_electrical = false;

        foreach ($accessories as $key => $value) {
            if(!$tp_only)
            {
                if (in_array('Electrical Accessories', $value) && $value['sumInsured'] != '0') {
                    $ElectricalaccessSI = $value['sumInsured'];
                    $is_electrical = true;
                    $cover_data[] = [
                        'typ:paramDesc' => 'ELECACC',
                        'typ:paramRef' => 'ELECACC',
                    ];
                }

                if (in_array('Non-Electrical Accessories', $value) && $value['sumInsured'] != '0') {
                    $NonElectricalaccessSI = $value['sumInsured'];
                    $is_non_electrical = true;
                    $cover_data[] = [
                        'typ:paramDesc' => 'NELECACC',
                        'typ:paramRef' => 'NELECACC',
                    ];
                }
            }
        }

        foreach($additional_covers as $key => $value) {
            /*  
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = '1';
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }
            // */
            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
                $cover_data[] = [
                    'typ:paramDesc' => 'PA',
                    'typ:paramRef' => 'PA',
                ];
            }
            if (in_array('Geographical Extension', $value)) {
                $is_geo_ext = true;
                $countries = $value['countries'];
            }
        }
        $voluntary_insurer_discounts = 0;
        $is_anti_theft = false;
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                $voluntary_insurer_discounts = isset($value['sumInsured']) ? $value['sumInsured'] : 0;
             }

            if (in_array('TPPD Cover', $value)) {
                 $cover_data[] = [
                    'typ:paramDesc' => 'TPPD_RES',
                    'typ:paramRef' => 'TPPD_RES',
                ];
            }
            if (!$tp_only && in_array('anti-theft device', $value)) {
                $is_anti_theft = true;
            }
        }

        if ($voluntary_insurer_discounts != 0) {
            $cover_data[] = [
                'typ:paramDesc' => 'VOLEX',
                'typ:paramRef' => 'VOLEX',
            ];
        }

        if ($is_anti_theft) {
             $cover_data[] = [
                'typ:paramDesc' => 'ATHEFT',
                'typ:paramRef' => 'ATHEFT',
            ];
        }

        if ($requestData->rto_code == 'HP-40') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'This RTO is not available with the Insurance Company',
                'request'=>[
                    'rto_code'=>$requestData->rto_code
                ]
            ];
        }
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
        if (empty($masterProduct)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Master Product mapping not found',
                'request'=>[
                    'masterProduct'=>$masterProduct,
                    'policy_id' => $productData->policy_id
                ]
            ];
        }
        $rto_code = $requestData->rto_code;
        // Re-arrange for Delhi RTO code - start
        $rto_code = explode('-', $rto_code);
        if ((int) $rto_code[1] < 10) {
            $rto_code[1] = '0' . (int) $rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        // Re-arrange for Delhi RTO code - End
        if ($rto_code == 'HP-40') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Blocked RTO - HP-40',
                'request'=>[
                    'rto_code'=>$rto_code
                ]
            ];
        }

        $rto_code = $requestData->rto_code;  
        $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);
        $rto_details = DB::table('bajaj_allianz_master_rto')->where('registration_code', str_replace('-', '', $rto_code))->first();
        if(empty($rto_details))
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO Not Available',
                'request' => [
                    'message' => 'RTO Not Available',
                    'rto_code' => $requestData->rto_code,
                ],
            ]; 
        }
        $zone_A = ['AHMEDABAD', 'BANGALORE', 'CHENNAI', 'HYDERABAD', 'KOLKATA', 'MUMBAI', 'NEW DELHI', 'PUNE', 'DELHI'];
        $zone = ((in_array(strtoupper($rto_details->city_name), $zone_A)) ? 'A' : 'B');
        $vehicale_reg_no = str_replace('-', '', !empty($requestData->vehicle_registration_no) ? $requestData->vehicle_registration_no : implode('-',array_merge(explode('-', $rto_code), ['MK', rand(1111, 9999)])));//($rto_code.'-')); // there was premium missmatch if we do not add complete reg no in rollover
        if(!empty($rto_details)) {
            $pUserId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE");

            $is_pos_disable_in_quote = config('constants.motorConstant.IS_BAJAJ_POS_DISABLED_QOUTE');
            $is_pos_enabled = (($is_pos_disable_in_quote == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED'));
            $extCol40 = '';
        
            $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
        
            $pUserId = config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE");
            $bajaj_new_tp_url = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_NEW_TP_URL_ENABLE");
            
            if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_TP");
            }

            if ($is_pos_enabled == 'Y')
            {
                if(isset($pos_data->seller_type) && $pos_data->seller_type == 'P')
                {
                    $extCol40 = $pos_data->pan_no;
                    $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP") : config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE_POS");
                }
                if(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y') {
                    $extCol40 = 'DNPPS5548E';
                    $pUserId = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_USERNAME_POS_TP") : config("constants.motor.bajaj_allianz.AUTH_NAME_BAJAJ_ALLIANZ_BIKE_POS");
                }
            }

            $pPassword = ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') ? config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_PASSWORD_TP") : config("constants.motor.bajaj_allianz.AUTH_PASS_BAJAJ_ALLIANZ_BIKE");
            $branch =  config("constants.motor.bajaj_allianz.BRANCH_OFFICE_CODE_BAJAJ_ALLIANZ_BIKE");
            $check_rto_name = !Str::is(Str::lower($requestData->rto_city),Str::lower($rto_details->city_name)) ? true : false;
            $chk_rei_name = Str::contains($rto_details->city_name, '/');
            if($chk_rei_name && $check_rto_name)
            {
                $sep_city_name = explode('/',$rto_details->city_name);
                foreach($sep_city_name as $val)
                {
                    $city_to_lowercase = Str::lower($val);
                    $inp_rto = Str::lower($requestData->rto_city);
                    if(Str::is($inp_rto, $city_to_lowercase))
                    {
                        $rto_details->city_name = $val;
                        break;
                    }
                    else
        
                    {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'RTO Name Not Available',
                            'request' => [
                                'message' => 'RTO Name Not Available',
                                'rto_code' => $requestData->rto_code,
                            ],
                        ]; 
                    }
                }
            }
            if($requestData->previous_policy_type == 'Not sure')
            {
                $requestData->previous_ncb = $requestData->applicable_ncb   = 0;
            }
        
            if (!empty($requestData->vehicle_registration_no)) {
                $vehicle_register_no = $requestData->vehicle_registration_no;
              } else {
                $vehicle_register_no = $rto_code;
              }

            $premium_req_array = [
                'soapenv:Header' => [],
                'soapenv:Body' => [
                    'web:calculateMotorPremiumSig' => [
                        'pUserId' => $pUserId,
                        'pPassword' => $pPassword,
                        'pVehicleCode' => $mmv_data->vehicle_code,
                        'pCity' => strtoupper($rto_details->city_name),
                        'pWeoMotPolicyIn_inout' => [
                            'typ:contractId' => '0',
                            'typ:polType' => $polType,
                            'typ:product4digitCode' => $product_code,
                            'typ:deptCode' => '18',
                            'typ:branchCode' => $branch,
                            'typ:termStartDate' => $termStartDate,
                            'typ:termEndDate' => $termEndDate,
                            'typ:tpFinType' => '',
                            'typ:hypo' => '',
                            'typ:vehicleTypeCode' => 21, // 22 is for car and 21 is for two-wheeler
                            'typ:vehicleType' => $mmv_data->vehicle_type,
                            'typ:miscVehType' => '0',
                            'typ:vehicleMakeCode' => $mmv_data->vehicle_make_code,
                            'typ:vehicleMake' => $mmv_data->vehicle_make,
                            'typ:vehicleModelCode' => $mmv_data->vehicle_model_code,
                            'typ:vehicleModel' => $mmv_data->vehicle_model,
                            'typ:vehicleSubtypeCode' => $mmv_data->vehicle_subtype_code,
                            'typ:vehicleSubtype' => $mmv_data->vehicle_subtype,
                            'typ:fuel' => $mmv_data->fuel,
                            'typ:zone' => $zone,
                            'typ:engineNo' => '123123123',
                            'typ:chassisNo' => '12323123',
                            'typ:registrationNo' => $BusinessType == '1' ? "NEW" : $vehicle_register_no,
                            'typ:registrationDate' =>  date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                            'typ:registrationLocation' => $rto_details->city_name,
                            'typ:regiLocOther' => $rto_details->city_name,
                            'typ:carryingCapacity' => $mmv_data->carrying_capacity,
                            'typ:cubicCapacity' =>$mmv_data->cubic_capacity,
                            'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                            'typ:color' => 'WHITE',
                            'typ:vehicleIdv' => '',
                            'typ:ncb' => $requestData->applicable_ncb,
                            'typ:addLoading' => '0',
                            'typ:addLoadingOn' => '0',
                            'typ:spDiscRate' => '0',
                            'typ:elecAccTotal' => $ElectricalaccessSI,
                            'typ:nonElecAccTotal' => $NonElectricalaccessSI,
                            'typ:prvPolicyRef' => '',
                            'typ:prvExpiryDate' => (($BusinessType == '1' || $noPreviousPolicyData) ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                            'typ:prvInsCompany' => ($noPreviousPolicyData ? '' : '33'),
                            'typ:prvNcb' => $requestData->previous_ncb,
                            'typ:prvClaimStatus' => (($requestData->is_claim == 'Y') ? '1' : '0'),
                            'typ:autoMembership' => '',
                            'typ:partnerType' => (($requestData->vehicle_owner_type == 'I') ? 'P' : 'I'),
                        ],
                        'accessoriesList_inout' => [
                            'typ:WeoMotAccessoriesUser' => [
                                'typ:contractId' => '',
                                'typ:accCategoryCode' => '',
                                'typ:accTypeCode' => '',
                                'typ:accMake' => '',
                                'typ:accModel' => '',
                                'typ:accIev' => '',
                                'typ:accCount' => '',
                            ],
                        ],
                        'paddoncoverList_inout' => [
                            'typ:WeoMotGenParamUser' => $cover_data,
                        ],
                        'motExtraCover' => [
                            'typ:geogExtn' => '',
                            'typ:noOfPersonsPa' => isset($PAforUnnamedPassenger) ? $mmv_data->carrying_capacity : '',
                            'typ:sumInsuredPa' => isset($PAforUnnamedPassengerSI) ? $PAforUnnamedPassengerSI : '',
                            'typ:sumInsuredTotalNamedPa' => '',
                            'typ:cngValue' => '',
                            'typ:noOfEmployeesLle' => '',
                            'typ:noOfPersonsLlo' => '0' ,
                            'typ:fibreGlassValue' => '',
                            'typ:sideCarValue' => '',
                            'typ:noOfTrailers' => '',
                            'typ:totalTrailerValue' => '',
                            'typ:voluntaryExcess' => $voluntary_insurer_discounts,
                            'typ:covernoteNo' => '',
                            'typ:covernoteDate' => '',
                            'typ:subImdcode' => '',
                            'typ:extraField1' => '400002',
                            'typ:extraField2' => '',
                            'typ:extraField3' => '',
                        ],
                        'pQuestList_inout' => [
                            'typ:WeoBjazMotQuestionaryUser' => [
                                'typ:questionRef' => '',
                                'typ:contractId' => '',
                                'typ:questionVal' => '',
                            ],
                        ],
                        'pDetariffObj_inout' => [
                            'typ:vehPurchaseType' => '',
                            'typ:vehPurchaseDate' => !empty($requestData->vehicle_invoice_date) ? date('d-M-Y', strtotime($requestData->vehicle_invoice_date)) : "",
                            'typ:monthOfMfg' => '',
                            'typ:bodyType' => '',
                            'typ:goodsTransType' => '',
                            'typ:natureOfGoods' => '',
                            'typ:otherGoodsFrequency' => '',
                            'typ:permitType' => '',
                            'typ:roadType' => '',
                            'typ:vehDrivenBy' => '',
                            'typ:driverExperience' => '',
                            'typ:clmHistCode' => '',
                            'typ:incurredClmExpCode' => '',
                            'typ:driverQualificationCode' => '',
                            'typ:tacMakeCode' => '',
                            'typ:registrationAuth' => '',
                            'typ:extCol1' => '',
                            'typ:extCol2' => '',
                            'typ:extCol3' => '',
                            'typ:extCol4' => '',
                            'typ:extCol5' => '',
                            'typ:extCol6' => '',
                            'typ:extCol7' => '',
                            'typ:extCol8' => 'MCPA',
                            'typ:extCol9' => '',
                            'typ:extCol10' => $masterProduct->product_identifier,
                            'typ:extCol11' => '',
                            'typ:extCol12' => '',
                            'typ:extCol13' => '',
                            'typ:extCol14' => '',
                            'typ:extCol15' => '',
                            'typ:extCol16' => '',
                            'typ:extCol17' => '',
                            'typ:extCol18' => 'CPA',
                            'typ:extCol19' => '',
                            'typ:extCol21' => '',
                            'typ:extCol20' => '',
                            'typ:extCol22' => '',
                            'typ:extCol23' => '',
                            'typ:extCol24' => $extCol24,
                            'typ:extCol25' => '',
                            'typ:extCol26' => '',
                            'typ:extCol29' => '',
                            'typ:extCol27' => '',
                            'typ:extCol28' => '',
                            'typ:extCol30' => '',
                            'typ:extCol31' => '',
                            'typ:extCol32' => '',
                            'typ:extCol33' => '',
                            'typ:extCol34' => '',
                            'typ:extCol35' => '',
                            'typ:extCol36' => '',
                            'typ:extCol37' => '',
                            'typ:extCol38' => '',
                            'typ:extCol39' => '',
                            'typ:extCol40' => $extCol40,
                        ],
                        'premiumDetailsOut_out' => [
                            'typ:serviceTax' => '',
                            'typ:collPremium' => '',
                            'typ:totalActPremium' => '',
                            'typ:netPremium' => '',
                            'typ:totalIev' => '',
                            'typ:addLoadPrem' => '',
                            'typ:totalNetPremium' => '',
                            'typ:imtOut' => '',
                            'typ:totalPremium' => '',
                            'typ:ncbAmt' => '',
                            'typ:stampDuty' => '',
                            'typ:totalOdPremium' => '',
                            'typ:spDisc' => '',
                            'typ:finalPremium' => '',
                        ],
                        'premiumSummeryList_out' => [
                            'typ:WeoMotPremiumSummaryUser' => [
                                'typ:od' => '',
                                'typ:paramDesc' => '',
                                'typ:paramRef' => '',
                                'typ:net' => '',
                                'typ:act' => '',
                                'typ:paramType' => '',
                            ],
                        ],
                        'pError_out' => [
                            'typ:WeoTygeErrorMessageUser' => [
                                'typ:errNumber' => '',
                                'typ:parName' => '',
                                'typ:property' => '',
                                'typ:errText' => '',
                                'typ:parIndex' => '',
                                'typ:errLevel' => '',
                            ],
                        ],
                        'pErrorCode_out' => '',
                        'pTransactionId_inout' => '',
                        'pTransactionType' => '',
                        'pContactNo' => '',
                    ],
                ],
                
            ];
            $additional_data = [
                'enquiryId' => $enquiryId,
                'headers' => [
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ],
                'requestMethod' => 'post',
                'requestType' => 'xml',
                'section' => 'bike',
                'method' => 'Premium Calculation',
                'productName' => $masterProduct->product_identifier,
                'transaction_type' => 'quote',
            ];

            $root = [
                'rootElementName' => 'soapenv:Envelope',
                '_attributes' => [
                    "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
                    "xmlns:web" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl",
                    "xmlns:typ" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl/types/",
                ],
            ];

            $input_array = ArrayToXml::convert($premium_req_array, $root, false, 'utf-8');
            $temp_data = $premium_req_array;
            unset($temp_data['soapenv:Body']['web:calculateMotorPremiumSig']['pTransactionId_inout']);
            $checksum_data = checksum_encrypt($temp_data);
            $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'bajaj_allianz',$checksum_data,'BIKE');
            $additional_data['checksum'] = $checksum_data;
            if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
            {
                $get_response = $is_data_exits_for_checksum;
            }else{
                if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                    $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_BIKE_QUOTE_TP_URL'), $input_array, 'bajaj_allianz', $additional_data);
                } else {
                    $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE'), $input_array, 'bajaj_allianz', $additional_data);
                }
            }
            $response = $get_response['response'];
            
            if (empty($response)) {
                return [
                    'webservice_id'=>$get_response['webservice_id'],
                    'table'=>$get_response['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Insurer not reachable',
                ];
            }
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success" );
            $response = XmlToArray::convert($response);
            if(isset($response['env:Body']['env:Fault']['faultstring']) && $response['env:Body']['env:Fault']['faultstring'] != '')
            {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'msg' => $response['env:Body']['env:Fault']['faultstring'],
                    'message' => $response['env:Body']['env:Fault']['faultstring'],
                ];
            }
            if (!isset($response['env:Body']['m:calculateMotorPremiumSigResponse']) && !isset($response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'])) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => 'Invalid response received from the quote calculation API.',
                ];
            }
            
            if ($tp_only == 'true' && $bajaj_new_tp_url == 'Y') {
                $service_response = $response['SOAP-ENV:Body']['m:calculateMotorPremiumSigResponse'];
            } else {
                $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
            }

            if ($service_response['pErrorCode_out'] != '0') {
                $error_msg = 'Insurer not reachable';
                if (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'])) {
                    $error_msg = $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                } elseif (is_array($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) && count($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) > 0) {
                    $error_msg = implode(', ', array_column($service_response['pError_out']['typ:WeoTygeErrorMessageUser'], 'typ:errText'));
                }
                return [
                    'premium_amount' => 0,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'status' => false,
                    'message' => $error_msg,
                    'request'=>[
                        'error_msg'=>$error_msg
                    ]
                ];
            }

//            if (is_array($service_response['premiumDetailsOut_out']['typ:finalPremium']) || $service_response['premiumDetailsOut_out']['typ:finalPremium'] != null) { 
//                return [
//                    'webservice_id' => $get_response['webservice_id'],
//                    'table' => $get_response['table'],
//                    'status' => false,
//                    'message' => 'Invalid response received from the quote calculation API service.',
//                ];
//            }

            if ($tp_only) {
                $idv = $idv_min = $idv_max = 0;
            } else {
                $idv = $service_response['pWeoMotPolicyIn_inout']['typ:vehicleIdv'];
                $idv_min = (string) $idv;
                $idv_max = (string) (1.20 * $idv);
                if (config('constants.motorConstant.SMS_FOLDER') == 'spa')
                {
                    $idv_min = (string) ($idv - ($idv * 0.10));
                    $idv_max = (string) (1.10 * $idv);
                }
            }

            if($is_electrical && ((($ElectricalaccessSI) + ($NonElectricalaccessSI)) < ($idv * 30/100)))
            {
                $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:elecAccTotal'] = $ElectricalaccessSI;
            }
            else{
                $is_electrical = false;
            }
            if($is_non_electrical && ((($ElectricalaccessSI) + ($NonElectricalaccessSI)) < ($idv * 30/100)))
            {
                //$is_non_electrical = false;
                $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:nonElecAccTotal'] = $NonElectricalaccessSI;
            }
            else{
                $is_non_electrical = false;
            }

            if (!$tp_only) {
                if($requestData->is_idv_changed == 'Y'){
                    if ($requestData->edit_idv >= ($idv_max)) {
                        $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = ($idv_max);
                    } elseif ($requestData->edit_idv <= ($idv_min)) {
                        $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = ($idv_min);
                    } else {
                        $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = $requestData->edit_idv;
                    }
                }
                else{
                    $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = ($idv_min);
                }
                //removed as per git #32283
                // $premium_req_array['soapenv:Body']['web:calculateMotorPremiumSig']['pTransactionId_inout'] = $service_response['pTransactionId_inout'];
                $additional_data['method'] = 'Premium Re-Calculation';
                $input_array = ArrayToXml::convert($premium_req_array, $root, false, 'utf-8');
                $temp_data = $premium_req_array;
                unset($temp_data['soapenv:Body']['web:calculateMotorPremiumSig']['pTransactionId_inout']);
                $checksum_data = checksum_encrypt($temp_data);
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'bajaj_allianz',$checksum_data,'BIKE');
                $additional_data['checksum'] = $checksum_data;
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status']){
                    $get_response = $is_data_exits_for_checksum;
                }else{
                    $get_response = getWsData(config('constants.motor.bajaj_allianz.END_POINT_URL_BAJAJ_ALLIANZ_BIKE'), $input_array, 'bajaj_allianz', $additional_data);
                }
                
                $response = $get_response['response'];
                
                if (empty($response)) {
                    return [
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable',
                    ];
                }
                $response = XmlToArray::convert($response);

                if(isset($response['env:Body']['env:Fault']['faultstring']) && $response['env:Body']['env:Fault']['faultstring'] != '')
                {
                    return [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'msg' => $response['env:Body']['env:Fault']['faultstring'],
                        'message' => $response['env:Body']['env:Fault']['faultstring'],
                    ];
                }
                if (!isset($response['env:Body']['m:calculateMotorPremiumSigResponse'])) {
                    return [
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable',
                    ];
                }
                $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
                if ($service_response['pErrorCode_out'] != '0') {
                    $message = 'Insurer not reachable';
                    if(!empty($service_response['pError_out']['WeoTygeErrorMessageUser']['errText']))
                    {
                        $message = $service_response['pError_out']['WeoTygeErrorMessageUser']['errText'];
                    }
                    else if(!empty($service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText']))
                    {
                        $message = $service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText'];
                    }
                    else if(!empty($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText']))
                    {
                        $message = $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                    }
                    return [
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => $message//$service_response['pError_out']['WeoTygeErrorMessageUser']['errText'] ?? $service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText'] ?? 'Insurer not reachable',
                    ];
                }
            }
            $transaction_id = $service_response['pTransactionId_inout'] ?? '';
            $idv = $service_response['pWeoMotPolicyIn_inout']['typ:vehicleIdv'];
            $zero_dep_amount = $key_replacement = $basic_od = $tppd = $pa_owner = $non_electrical_accessories = $electrical_accessories = $pa_unnamed = $ll_paid_driver = $lpg_cng = $lpg_cng_tp = $other_discount = $ncb_discount = $engine_protector = $lossbaggage = $rsa = $accident_shield = $conynBenef = $consExps = $voluntary_deductible = $additionalDiscount = $restricted_tppd = 0;

            $non_electrical_accessories = $electrical_accessories = $antitheft_discount_amount = 0;

            $finalPremium = $service_response['premiumDetailsOut_out']['typ:finalPremium'];
            // $base_premium_amount = ($finalPremium / (1.18));
            if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) { ## revisit
                $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];
                /*
                if ($premium_type == 'own_damage' && $requestData->is_claim == 'Y' && ($productData->zero_dep == '1' || $productData->zero_dep == 'NA' ) && isset($covers['od'])) {
                    $basic_od = ($covers['od']);
                } else {
                    foreach ($covers as $key => $cover) {
                        if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                            $zero_dep_amount = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') {
                            $basic_od = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                            $rsa = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                            $tppd = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                            $pa_owner = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                            $pa_unnamed = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'Commercial Discount' || $cover['typ:paramDesc'] === 'Commercial Discount8' ) {
                            $other_discount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                            $ncb_discount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                            $voluntary_deductible = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'CHDH Additional Discount/Loading') {
                            $voluntary_deductible = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                            $consExps = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                            $engine_protector = ($cover['typ:od']);
                        }
                    }
                }
                // */

                $common_code = false;
                if ($premium_type == 'own_damage' && $requestData->is_claim == 'Y') {
                    if (($productData->zero_dep == '1')  && !isset($covers[0])) {
                        $basic_od = ($covers['od']) ?? ($covers['typ:od']) ?? 0;
                    } else if(!isset($covers[0])) {
                        foreach ($covers as $key => $cover) {
                            if (($productData->zero_dep == '0') && ($cover === 'Depreciation Shield')) {
                                $zero_dep_amount = ($cover['od']);
                            } elseif (in_array($cover, ['Basic Own Damage','Basic Own Damage 1'])) {
                                $basic_od = ($cover['od']);
                            } elseif ($cover === 'Basic Third Party Liability') {
                                $tppd = ($cover);
                            } elseif ($cover === 'PA Cover For Owner-Driver') {
                                $pa_owner = ($cover);
                            } elseif ($cover === 'PA for unnamed Passengers') {
                                $pa_unnamed = ($cover);
                            } elseif ($cover === 'Commercial Discount' || ($cover === "Commercial Discount8")) {
                                $other_discount = (abs($cover['od']));
                            } elseif ($cover === 'Bonus / Malus') {
                                $ncb_discount = (abs($cover['od']));
                            } elseif ($cover['paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                                $voluntary_deductible = (abs($cover['od']));
                            } elseif ($cover['paramDesc'] === 'CHDH Additional Discount/Loading') {
                                $additionalDiscount = (abs($cover['od']));
                            } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                                $restricted_tppd = ($cover['typ:act']);
                            }
                            elseif(in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)', 'Anti-Theft Device (IMT.10)']))
                            {
                                $antitheft_discount_amount = (abs($cover['typ:od']));
                            } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                                $non_electrical_accessories = ($cover['typ:od']);
                            } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                                $electrical_accessories = ($cover['typ:od']);
                            }
                        }
                    } else {
                        $common_code = true;
                    }
                } else if(!isset($covers[0])) {
                    if ($covers['typ:paramDesc'] == 'Basic Third Party Liability') {
                        $tppd = $covers['typ:act'];
                    }
                } else {
                    $common_code = true;
                }

                if($common_code) {
                    foreach ($covers as $key => $cover) {
                        if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                            $zero_dep_amount = ($cover['typ:od']);
                        } elseif (in_array($cover['typ:paramDesc'], ['Basic Own Damage','Basic Own Damage 1'])) {
                            $basic_od = (float)($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                            $rsa = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                            $tppd = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                            $pa_owner = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                            $pa_unnamed = ($cover['typ:act']);
                        } elseif ($cover['typ:paramDesc'] === 'Commercial Discount' || $cover['typ:paramDesc'] === 'Commercial Discount8' ) {
                            $other_discount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                            $ncb_discount = (float)(abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                            $voluntary_deductible = (float)(abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'CHDH Additional Discount/Loading') {
                            $additionalDiscount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                            $consExps = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                            $engine_protector = ($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                            $restricted_tppd = ($cover['typ:act']);
                        }
                        elseif(in_array($cover['typ:paramDesc'], ['10Anti-Theft Device (IMT.10)', 'Anti-Theft Device (IMT.10)']))
                        {
                            $antitheft_discount_amount = (abs($cover['typ:od']));
                        } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                            $non_electrical_accessories = (float)($cover['typ:od']);
                        } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                            $electrical_accessories = ($cover['typ:od']);
                        }
                    }
                }
            }

            $applicable_addons = $add_ons_data = [];
            switch ($masterProduct->product_identifier) {
                case 'DRIVE_ASSURE_BASIC':
                    $add_ons_data = [
                        'in_built' => [
                            'zero_depreciation' => $zero_dep_amount,
                        ],
                        'additional' => [],
                        'other' => [],
                    ];
                    array_push($applicable_addons, "zeroDepreciation");
                break;

                case 'DRIVE_ASSURE_SPOT':
                    if(!$rsa)
                    {
                        return [
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => 'RSA not available',
                            'request' => [
                                'rsa' => $rsa,
                                'product_identifier' => 'DRIVE_ASSURE_SPOT'
                            ]
                        ];
                    }
                    $add_ons_data = [
                        'in_built' => [
                            'road_side_assistance' => $rsa,
                        ],
                        'additional' => [
                            // 'zero_depreciation' => 0,
                        ],
                        'other' => [],
                    ];
                    array_push($applicable_addons, "roadSideAssistance");
                break;

                case 'DRIVE_ASSURE_SILVER':
                    $add_ons_data = [
                        'in_built' => [
                            'zero_depreciation' => $zero_dep_amount,
                            'consumables' => $consExps,
                            'engine_protector' => $engine_protector,
                        ],
                        'additional' => [],
                        'other' => [],
                    ];
                    array_push($applicable_addons, "zeroDepreciation", "engineProtector", "consumables");
                break;

                default:
                    $add_ons_data = [
                        'in_built' => [],
                        'additional' => [
                            // 'zero_depreciation' => 0,
                            // 'road_side_assistance' => 0,
                        ],
                        'other' => [],
                    ];

                break;
            }

            // $applicable_addons = [
            //     "zeroDepreciation",
            //     "engineProtector",
            //     "consumables",
            // ];

            if(config('constants.motorConstant.IS_RSA_ENABLED') == 'Y')
            {
                array_push($applicable_addons, "roadSideAssistance");
            }

            if($bike_age > 5)
            {
                $applicable_addons = [];
            }
            if($bike_age == 5 && $interval->m > 5)
            {
                $applicable_addons = [];
            }

            if($tp_only)
            {
                $applicable_addons = [];
            }
            
            $addLoadPrem = $service_response['premiumDetailsOut_out']['typ:addLoadPrem'] ?? 0;
            $ExtraPremiumForRejectedRTO = is_array($service_response['pDetariffObj_inout']['typ:extCol22']) ? 0 : $service_response['pDetariffObj_inout']['typ:extCol22'];
            $finalPremium = $base_premium_amount = $service_response['premiumDetailsOut_out']['typ:finalPremium'];
            $tppd = $tppd + $restricted_tppd;
            $addLoadPrem += $ExtraPremiumForRejectedRTO += $additionalDiscount;
            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories; //A
            $totalTP = $tppd + $pa_unnamed + $ll_paid_driver ; //B
            $totalDiscount = $ncb_discount + $other_discount + $voluntary_deductible + $additionalDiscount + $restricted_tppd + $antitheft_discount_amount; //C
            $totalBasePremium = $final_od_premium + $totalTP - $totalDiscount; //A + B - C
            $final_gst_amount = $totalBasePremium * 0.18;
            $final_payable_amount = $totalBasePremium * (1.18);

            switch ($requestData->business_type) 
            {
                case 'newbusiness':
                    $business_type = 'New Business';
                break;
                case 'rollover':
                    $business_type = 'Roll Over';
                break;

                case 'breakin':
                    $business_type = 'Break- In';
                break;
                
            }
            $data_response = [
                'webservice_id'=>$get_response['webservice_id'],
                'table'=> $get_response['table'],
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => $premium_type == "third_party" ? 0 : ($idv),
                    'min_idv' => $premium_type == "third_party" ? 0 : ($idv_min),
                    'max_idv' => $premium_type == "third_party" ? 0 : ($idv_max),
                    'vehicle_idv' => $premium_type == "third_party" ? 0 : ($idv),
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'Third Party' :(($premium_type == "own_damage" || $premium_type == "own_damage_breakin") ? 'Own Damage' : 'Comprehensive'),//$tp_only == 'true' ? 'Third Party' : 'Comprehensive',
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $rto_code,
                    'rto_no' => $rto_code,
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                    'product_name' => $productData->product_sub_type_name . ' - ' . ucwords(str_replace('_', ' ', strtolower($masterProduct->product_identifier))),
                    'mmv_detail' => $mmv_data,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => $termStartDate,
                        'policy_end_date' => $termEndDate,
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
                        'car_age' => $bike_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => (abs($other_discount)),
                    ],
                    'ic_vehicle_discount' => (abs($other_discount)),
                    'basic_premium' => (float)($basic_od),
                    // 'motor_electric_accessories_value' => ($electrical_accessories),
                    // 'motor_non_electric_accessories_value' => ($non_electrical_accessories),
                    'motor_lpg_cng_kit_value' => ($lpg_cng),
                    'total_accessories_amount(net_od_premium)' => ($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                    'total_own_damage' => (float)($basic_od),
                    'tppd_premium_amount' => ($tppd),
                    'tppd_discount' => $restricted_tppd,
                    'compulsory_pa_own_driver' => ($pa_owner), // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    // 'default_paid_driver' => $ll_paid_driver,
                    'motor_additional_paid_driver' => '', //($pa_paid_driver),
                    'cng_lpg_tp' => ($lpg_cng_tp),
                    'seating_capacity' => $mmv_data->carrying_capacity,
                    'deduction_of_ncb' => (abs($ncb_discount)),
                    // 'antitheft_discount' => '', //(abs($anti_theft)),
                    'aai_discount' => '', //$automobile_association,
                    'voluntary_excess' => $voluntary_deductible,
                    'other_discount' => (abs($other_discount)),
                    'total_liability_premium' => ($totalTP),
                    'net_premium' => ($totalBasePremium),
                    'service_tax_amount' => ($final_gst_amount),
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount' => ($final_payable_amount),
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
                    'service_err_code' => null,
                    'service_err_msg' => null,
                    'policyStartDate' => date('d-m-Y', strtotime($termStartDate)),
                    'policyEndDate' => date('d-m-Y', strtotime($termEndDate)),
                    'ic_of' => $productData->company_id,
                    'vehicle_in_90_days' => NULL, ##
                    'get_policy_expiry_date' => null,
                    'get_changed_discount_quoteid' => 0,
                    'vehicle_discount_detail' => [
                        'discount_id' => null,
                        'discount_rate' => null,
                    ],
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online,
                    'policy_id' => $productData->policy_id,
                    'insurane_company_id' => $productData->company_id,
                    'max_addons_selection' => null,
                    'add_ons_data' => $add_ons_data,
                    'applicable_addons' => $applicable_addons,
                    'final_od_premium' => ($final_od_premium),
                    'total_loading_amount' => ($addLoadPrem),
                    'final_tp_premium' => ($totalTP),
                    'final_total_discount' => (float)(abs($totalDiscount)),
                    'final_net_premium' => ($totalBasePremium),
                    'final_gst_amount' => ($final_gst_amount),
                    'final_payable_amount' => ($final_payable_amount),
                    'underwriting_loading_amount'=> $additionalDiscount,
                    'mmv_detail' => [
                        'manf_name' => $mmv_data->vehicle_make,
                        'model_name' => $mmv_data->vehicle_model,
                        'version_name' => $mmv_data->vehicle_subtype,
                        'fuel_type' => ($mmv_data->fuel == 'B' ? 'Electric' : $mmv_data->fuel),
                        'seating_capacity' => $mmv_data->carrying_capacity,
                        'carrying_capacity' => $mmv_data->carrying_capacity,
                        'cubic_capacity' => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight' => '',
                        'vehicle_type' => $mmv_data->vehicle_type,
                    ],
                    'product_identifier' => $masterProduct->product_identifier,
                    'transaction_id' => $transaction_id
                ],
            ];
            if($mmv_data->fuel == 'B')
            {
                $data_response['Data']['mmv_detail']['kw'] = $mmv_data->cubic_capacity;
            }
            if($is_anti_theft)
            {
                $data_response['Data']['antitheft_discount'] = ($antitheft_discount_amount);
            }
            if($is_electrical)
            {
                $data_response['Data']['motor_electric_accessories_value'] = ($electrical_accessories);
            }
            if($is_non_electrical)
            {
                $data_response['Data']['motor_non_electric_accessories_value'] = (float)($non_electrical_accessories);
            }
            if($ll_paid_driver)
            {
                $data_response['Data']['default_paid_driver'] = ($ll_paid_driver);
            }
            if($is_geo_ext)
            {
                $data_response['Data']['GeogExtension_ODPremium'] = 0;
                $data_response['Data']['GeogExtension_TPPremium'] = 0;
            }
            if(isset($cpa_tenure) && $requestData->business_type == 'newbusiness' && $cpa_tenure == '5')
            {
                
                $data_response['Data']['multi_Year_Cpa'] = $pa_owner;
            }

            return camelCase($data_response);
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'RTO not available',
                'request'=>[
                    'rto_details'=>$rto_details
                ]
            ];
        }
    }
}
?>