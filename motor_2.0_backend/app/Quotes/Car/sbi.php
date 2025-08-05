<?php
include_once app_path().'/Helpers/CarWebServiceHelper.php';
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\UserProposal;

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
    if (empty($requestData->rto_code)) {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'RTO not available'
        ];
    }

    $mmv = get_mmv_details($productData, $requestData->version_id, 'sbi');

    if ($mmv['status'] == 1) {
      $mmv = $mmv['data'];
    } else {
        return  [
            'status' => false,
            'premium_amount' => 0,
            'message' => $mmv['message']
        ];          
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
// print_r($mmv_data);exit;
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'status' => false,
            'premium_amount' => 0,
            'message' => 'Vehicle Not Mapped'
        ];        
    } elseif ($mmv_data->ic_version_code == 'DNE') {
        return [
            'status' => false,
            'premium_amount' => 0,
            'message' => 'Vehicle code does not exist with Insurance company'
        ];        
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();
    if($premium_type =='third_party_breakin'){
        $premium_type ='third_party';
    }
    $expdate=(($requestData->previous_policy_expiry_date == 'New') || ($requestData->business_type == 'breakin') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($expdate);
    $interval = $date1->diff($date2);
    // $age = (($interval->y * 12) + $interval->m) + 1; // One month extra added in vehicle age removed as its failing for addons condtions
    $age = (($interval->y * 12) + $interval->m) ;// + ($interval->d > 0 ? 1 : 0);
    // $vehicle_age = ceil($age / 12); // after ceilng the value 2.7 becomes 3 and some of the addons are not available due to this passing age/12 to the actual age and addons will be get selected
    $vehicle_age = ($age / 12);
    //$vehicle_age = car_age($requestData->vehicle_register_date, $expdate);
    //5 Years = $interval->days = 
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    
    // if (($interval->y >= 12) && ($tp_check == 'true')){
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 12 year',
    //     ];
    // }
    // if($interval->days > 1823 && $productData->zero_dep == 0)//ZD validation
    // {
    //     return [
    //         'status' => false,
    //         'premium' => 0,
    //         'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //         'request' => [
    //             'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
    //             'vehicle_age' => $vehicle_age
    //         ]
    //     ];
    // }

    $motor_manf_date = '01-'.$requestData->manufacture_year;

    if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
        $policy_start_date = date('Y-m-d');
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $expiry_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $nb_date = date('Y-m-d', strtotime('+3 year -1 day', strtotime($policy_start_date)));
    } elseif ($requestData->business_type == 'rollover') {
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
    }
// print_r($requestData);exit;

    $kind_of_policy = ($premium_type == 'third_party') ? '2' : (($premium_type == 'own_damage') ? '3' :  '1');

    if ($requestData->business_type == 'newbusiness') {
        $NewBizClassification = 1;
        $business_type = 1;
        $previous_policy_expiry_date = '1900-01-01';
        $previous_policy_start_date = '1900-01-01';
    } else {
        $NewBizClassification = 2;
        $business_type = 6; //2;    #changes as per git 33646
        $previous_policy_expiry_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
        $previous_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
    }

    $city_name = DB::table('master_rto')
        ->where('rto_number', $requestData->rto_code)
        ->get();

    $rto_data = (object)[];
// print_r($city_name);exit;
    if ($city_name->isEmpty()) {
        return [
            'status' => false,
            'premium_amount' => 0,
            'message' => 'RTO not available'
        ];
    } else {
        foreach ($city_name as $city) {
            $dl_rto = explode('-',$city->rto_number);
            if(isset($dl_rto[0]) && ($dl_rto[0] == 'DL') && $dl_rto[1] < 10)
            {
                $dl_code = strval(intval($dl_rto[1]));
                $city->rto_number = $dl_rto[0].'-'.$dl_code;
            }
            $city->rto_number = preg_replace("/OD/", "OR", $city->rto_number);
            $rto_data = DB::table('sbi_rto_location')
                ->where('rto_location_code', 'like', $city->rto_number)
                ->first();

            if (!empty($rto_data)) {
                break;
            }
        }

        if (empty($rto_data)) {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'RTO not available'
            ];
        }
    }

    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts','compulsory_personal_accident')
    ->first();

    $cpa_tenure = 0;

    if ($additional && $additional->compulsory_personal_accident != NULL && $additional->compulsory_personal_accident != '') {
        $addons = $additional->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? 3 : 1 ;

            }
        }
    }
    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 3; 
        }
        else{
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 1;
        }
    }
   
    $policyEndDate = ($cpa_tenure == 3) 
    ? date('Y-m-d', strtotime('+3 years -1 day', strtotime($policy_start_date))) 
    : $policy_end_date;

    $electrical_accessories = false;
    $electrical_accessories_amount = 0;
    $non_electrical_accessories = false;
    $non_electrical_accessories_amount = 0;
    $external_fuel_kit = false;
    $fuel_type = $mmv_data->fuel_type;
    $external_fuel_kit_amount = 0;
    $is_tppd = false;
    $inbuilt_fuel_kit = false;
    if(in_array($mmv_data->fuel_type, ['LPG (Inbuilt)', 'CNG (Inbuilt)'])) // adding LPG (Inbuilt) 
    {
        $inbuilt_fuel_kit = true;
    }
    if (!empty($additional['accessories'])) {
        foreach ($additional['accessories'] as $key => $data) {
            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 50000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle External Bi-Fuel Kit CNG/LPG value should be between 10000 to 50000',
                    ];
                } else {
                    $external_fuel_kit = true;
                    $fuel_type = 'CNG';
                    $external_fuel_kit_amount = $data['sumInsured'];
                }
            }

            if ($data['name'] == 'Non-Electrical Accessories') {
                $non_electrical_accessories = true;
                $non_electrical_accessories_amount = $data['sumInsured'];
            }

            if ($data['name'] == 'Electrical Accessories') {
                $electrical_accessories = true;
                $electrical_accessories_amount = $data['sumInsured'];
            }
            
        }
    }
    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $data) {
            if ($data['name'] == 'TPPD Cover') {
                $is_tppd = true;
            }
        }
    }
    $is_anti_theft = '0';
    $is_anti_theft_device_certified_by_arai = 'false';
    $is_voluntary_access = false;
    $voluntary_excess_amt = 0;

    if (!empty($additional['discounts'])) {
        foreach ($additional['discounts'] as $key => $data) {
            if ($data['name'] == 'anti-theft device') {
                $is_anti_theft = '1';
                $is_anti_theft_device_certified_by_arai = 'true';
            }

            if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                $is_voluntary_access = true;
                $voluntary_excess_amt = $data['sumInsured'];
            }
        }
    }

    $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = false;
    $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
    $no_of_paid_driver = 0;
    $is_geo_ext = false;
    $is_geo_code = 0;
    $srilanka = 0;
    $pak = 0;
    $bang = 0;
    $bhutan = 0;
    $nepal = 0;
    $maldive = 0;

    if (!empty($additional['additional_covers'])) {
        foreach ($additional['additional_covers'] as $key => $data) {
            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                $cover_pa_paid_driver = true;
                $cover_pa_paid_driver_amt = $data['sumInsured'];
                $no_of_paid_driver = 1;
            }

            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                $cover_pa_unnamed_passenger = true;
                $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                $no_of_pa_unnamed_passenger = $mmv_data->seating_capacity;
            }

            if ($data['name'] == 'LL paid driver') {
                $cover_ll_paid_driver = true;
                $no_of_paid_driver = 1;
            }
            if ($data['name'] == 'Geographical Extension') {
                $is_geo_ext = true;
                $is_geo_code = 1;
                $countries = $data['countries'];
                if(in_array('Sri Lanka',$countries))
                {
                    $srilanka = 1;
                }
                if(in_array('Bangladesh',$countries))
                {
                    $bang = 1; 
                }
                if(in_array('Bhutan',$countries))
                {
                    $bhutan = 1; 
                }
                if(in_array('Nepal',$countries))
                {
                    $nepal = 1; 
                }
                if(in_array('Pakistan',$countries))
                {
                    $pak = 1; 
                }
                if(in_array('Maldives',$countries))
                {
                    $maldive = 1; 
                }
            }
        }
    }
    

    if ($premium_type == 'comprehensive') {
        if ($requestData->business_type == 'newbusiness') {
            $policy_type = '6';
        } else {
            $policy_type = '1';
        }
    }elseif($premium_type == 'own_damage'){
        $policy_type = '9';
        $NewBizClassification = 2;
        $business_type = 6; //2;        #Changes as per git #33646
        $tp_start_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) . 'T00:00:00';
        $tp_end_date = date('Y-m-d', strtotime('+3 year -1 day', strtotime(str_replace('/', '-', $tp_start_date)))) . 'T23:59:59';
        $tp_insurer_name = '15';
        $tpPolicyno = '123455';
    }
     elseif ($premium_type == 'third_party') {
        $requestData->applicable_ncb = 0;
        if ($requestData->business_type == 'newbusiness') {
            $policy_type = '8';
        } else {
            $policy_type = '2';
        }
    }
    $is_pos = false;
    $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_SBI_TESTING');
    $posp_name = '';
    $posp_unique_number = '';
    $posp_pan_number = '';

    $pos_data = DB::table('cv_agent_mappings')
        ->where('user_product_journey_id', $requestData->user_product_journey_id)
        ->where('seller_type','P')
        ->first();

    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') 
    {
        $pos_code = AgentIcRelationship::where('agent_id', $pos_data->agent_id)
                    ->pluck('sbi_code')
                    ->first();
        if(empty($pos_code) || is_null($pos_code))
        {
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Child Id Is Not Available'
            ];
        }
        if($pos_data) 
        {
            $is_pos = true;
            $posp_name = $pos_data->agent_name;
            $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
            $posp_pan_number = $pos_data->pan_no;
        }
    }
    else if($is_pos_enabled_testing == 'Y')
    {
        $is_pos = true;
        $posp_name = 'test';
        $posp_unique_number = '9768574564';
        $posp_pan_number = '569278616999';
    }
// print_r($rto_data);exit;

    $engine_number = config('CAR_QUOTE_TEST_ENGINE_NUMBER');
    $chassis_number = config('CAR_QUOTE_TEST_CHASSIS_NUMBER');
    $registration_no = $requestData->rto_code;

    if (!empty($requestData->vehicle_registration_no) && strtoupper($requestData->vehicle_registration_no) != 'NEW') {
        $registration_no = str_replace('-', '', $requestData->vehicle_registration_no);

        $proposal = UserProposal::select('chassis_number', 'engine_number')
        ->where('user_product_journey_id', $enquiryId)->first();

        if (!empty($proposal->chassis_number)) {
            $engine_number = $proposal->engine_number ?? $engine_number;
            $chassis_number = $proposal->chassis_number;
        } else {
            //if chassis number is not found then pass default vehicle number and chassis
            $registration_no = config('CAR_QUOTE_REGISTRATION_NUMBER');
            $registration_no = str_replace('-', '', $registration_no);

            $engine_number = config('CAR_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('CAR_QUOTE_CHASSIS_NUMBER');
        }
        
    } else {
        if ($requestData->business_type != 'newbusiness') {
            $registration_no = config('CAR_QUOTE_REGISTRATION_NUMBER');
            $registration_no = str_replace('-', '', $registration_no);

            $engine_number = config('CAR_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('CAR_QUOTE_CHASSIS_NUMBER');
        }
    }
    if(isBhSeries($requestData->vehicle_registration_no)){
        $registration_no = config('CAR_QUOTE_BH_REGISTRATION_NUMBER');
        $registration_no = str_replace('-', '', $registration_no);

        $engine_number = config('CAR_QUOTE_BH_ENGINE_NUMBER');
        $chassis_number = config('CAR_QUOTE_BH_CHASSIS_NUMBER');
    }

    if (
        str_starts_with(strtoupper($registration_no), 'DL0')
    ) {
        $registration_no = getRegisterNumberWithHyphen($registration_no);
        $registration_no = explode('-', $registration_no);
        $registration_no[1] = ((int) $registration_no[1] * 1);
        $registration_no = implode('-', $registration_no);
        $registration_no = str_replace('-', '', $registration_no);
    }
    if ($requestData->is_claim == 'Y'){
        $requestData->applicable_ncb = 0;
    }
    $premium_calculation_request = [
        'RequestHeader' => [
            'requestID' => mt_rand(100000, 999999),
            'action' => 'fullQuote',
            'channel' => 'SBIG',
            'transactionTimestamp' => date('d-M-Y-H:i:s')
        ],
        'RequestBody' => [
            'AdditonalCompDeductible' => '',
            'AgreementCode' => config('constants.IcConstants.sbi.SBI_AGREEMENT_ID'),
            'BusinessSubType' => '1',
            'BusinessType' => $business_type,
            'ChannelType' => '5',   //'3', Changes as pert git #33646
            'CustomerGSTINNo' => '22AAAAA00001Z5',
            'CustomerSegment' => 'CustomerSegment',
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'ExpiryDate' => $policy_end_date . 'T23:59:59',
            'GSTType' => 'IGST',
            'HasPrePolicy' => ($requestData->business_type == 'newbusiness' ||  $premium_type == 'third_party_breakin' || $requestData->previous_policy_type == 'Not sure') ? '0' : '1',
            'InspectionComment' => '',
            'IssuingBranchGSTN' => '22AAAAA00001Z0',
            'KindofPolicy' => $kind_of_policy,
            'NewBizClassification' => (string) $NewBizClassification,
            'PolicyCustomerList' => [
                [
                    'BuildingHouseName' => 'BuildingHouseName',
                    'City' => 'City',
                    'ContactEmail' => 'sfdsf@gmail.com',
                    'ContactPersonTel' => '',
                    'CountryCode' => '1000000108',
                    'CustomerName' => 'Ms test test',
                    'DateOfBirth' => $requestData->vehicle_owner_type != 'I' ? '' : '1988-04-19',
                    'DateOfIncorporation' => '',
                    'District' => '4000000312',
                    'GSTRegistrationNum' => '22AAAAA00001Z5',
                    'GenderCode' => 'M',
                    'IdNo' => 'BLPPG0173B',
                    'IdType' => '1',
                    'IsInsured' => 'N',
                    'IsOrgParty' => $requestData->vehicle_owner_type == 'I' ? 'N' : 'Y',
                    'IsPolicyHolder' => 'Y',
                    'Locality' => 'xvxcgdf',
                    'Mobile' => '9766495689',
                    'NationalityCode' => 'IND',
                    'OccupationCode' => '11',
                    'PartyStatus' => '1',//'Active',
                    'PostCode' => '388215',
                    'PostCode' => '388215',
                    'PreferredContactMode' => 'EMAIL',
                    'SBIGCustomerId' => '',
                    'STDCode' => '91',
                    'State' => $rto_data->id ?? $rto_data->state_id,
                    'StreetName' => 'StreetName',
                    'Title' => '9000000001',
                    'CKYCUniqueId' => time(),
                    //Regulatory changes *DUMMY DATA for quote page*
                    "AccountNo" => "124301506743",
                    "IFSCCode"  => "ICIC0001230",
                    "BankName"  => "ICICI Bank",
                    "BankBranch"=>"Parel",
                ],
            ],
            'PolicyLobList' => [
                [
                    'PolicyRiskList' => [
                        [
                            'VehicleRegistrationType' => isBhSeries($requestData->vehicle_registration_no) ? '2' : '1',
                            'AAMemberExpiryDate' => '',
                            'AAMemberNo' => 'AAMemberNo',
                            'AntiTheftAlarmSystem' => $is_anti_theft,
                            'BodyStyle' => $mmv_data->body_style,
                            'BodyType' => 'Sedan',
                            'CarryingCapacity' => $mmv_data->carrying_capacity,
                            'ChassisNo' => ($chassis_number), //by #33723
                            'DailyUseDistance' => '1',
                            'DayParkLoc' => '1',
                            'DayParkLocPinCode' => '388215',
                            'EmployeeCount' => 0,
                            'EngineCapacity' => $mmv_data->cubic_capacity,
                            'EngineNo' => $engine_number,
                            'FEVehicleRegNo' => 'FEVehicleRegNo',
                            'FNBranchName' => 'FNBranchName',
                            'FNName' => 'FNName',
                            'FNType' => '1',
                            'FuelType' => $external_fuel_kit ? '8':(($inbuilt_fuel_kit && ($mmv_data->fuel_type == 'CNG (Inbuilt)')) ? '3' : $mmv_data->fuel),
                            'GeoExtnBangladesh' => $bang,
                            'GeoExtnBhutan' => $bhutan,
                            'GeoExtnMaldives' => $maldive,
                            'GeoExtnNepal' => $nepal,
                            'GeoExtnPakistan' => $pak,
                            'GeoExtnSriLanka' => $srilanka,
                            'IDV_User' => '',
                            'IsAAMember' => '0',
                            'IsCertifiedVintageCar' => '0',
                            'IsConfinedOwnPremises' => '0',
                            'IsDrivingTuitionsUse' => '0',
                            'IsFEVehicle' => '0',
                            'IsFGFT' => '0',
                            'IsGeographicalExtension' => $is_geo_code,
                            'IsHandicappedMod' => '0',
                            'IsImportedWithoutDuty' => '0',
                            'IsNCB' => 0, //Made changes according to git #30395 //($requestData->is_claim == 'Y' && $requestData->applicable_ncb == 0) ? '1' : '0',                            
                            'IsNewVehicle' =>$requestData->business_type == 'newbusiness' ? '1' : '0',
                            'LLSSACount' => 0,
                            'LoanAccountNumber' => 'LoanAccountNumber',
                            'ManufactureYear' => $motor_manf_date,
                            'ModificationType' => '1',
                            'NCB' => $requestData->applicable_ncb / 100,
                            'NCBLetterDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
                            'NCBPrePolicy' => $requestData->previous_ncb / 100,
                            'NCBProof' => '1',
                            'NightParkLoc' => '1',
                            'NightParkLocPinCode' => '400006',
                            'PaidDriverCount' => $no_of_paid_driver,
                            'ProductElementCode' => 'R10005',
                            'RTOCityDistric' => $rto_data->rto_dis_code,
                            'RTOCluster' => $rto_data->id ?? $rto_data->state_id,
                            'RTOLocation' => $rto_data->rto_location_code,
                            'RTOLocationID' => $rto_data->loc_id,
                            'RTONameCode' => $rto_data->id ?? $rto_data->state_id,
                            'RTORegion' => $rto_data->rto_region,
                            'RegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            'RegistrationNo' => $requestData->business_type == 'newbusiness' ? '' : strtoupper($registration_no),#'MH-05-AB-1111',
                            'RoadType' => '1',
                            'SeatingCapacity' => $mmv_data->seating_capacity,
                            'TrailerCount' => 0,
                            'Variant' => $mmv_data->variant_id,
                            'VehicleMake' => $mmv_data->vehicle_manufacturer_code,
                            'VehicleModel' => $mmv_data->vehicle_model_code,
                            'VehicleSegment' => $mmv_data->body_style,
                            'VehicleUsage' => '1',
                            'VoluntaryDeductible' => $is_voluntary_access ? $voluntary_excess_amt : '',
                            'WheelNum' => 4,
                            'Zone' => $rto_data->registration_zone,
                            'PolicyCoverageList' => [],
                        ],
                    ],
                    'ProductCode' => 'PMCAR001',
                    'PolicyType' => $policy_type,
                    'BranchInfo' => 'HO',
                ],
            ],
            'PremiumCurrencyCode' => 'INR',
            'PremiumLocalExchangeRate' => 1,
            'ProductCode' => 'PMCAR001',
            'ProductVersion' => '1.0',
            'ProposalDate' => $policy_start_date,
            'QuoteValidTo' => date('Y-m-d', strtotime('+30 day', strtotime($policy_start_date))),
            'SBIGBranchStateCode' => $rto_data->id ?? $rto_data->state_id,
            'SiCurrencyCode' => 'INR',
            'TransactionInitiatedBy' => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
        ]
    ];
    if (config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y') {
        $premium_calculation_request['RequestBody']['SourceType'] = config('constants.IcConstants.sbi.SBI_CAR_SOURCE_TYPE', '9');
    }
    if($requestData->vehicle_owner_type != 'I'){
        unset($premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['GenderCode']);
    }
    if ($requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure') {
        $premium_calculation_request['RequestBody']['PreInsuranceComAddr'] ='PreInsuranceComAddr';
        $premium_calculation_request['RequestBody']['PreInsuranceComName'] ='15';
        $premium_calculation_request['RequestBody']['PrePolicyNo'] ='PrePolicyNo';
        $premium_calculation_request['RequestBody']['PrePolicyEndDate'] =$previous_policy_expiry_date . 'T23:59:59';
        $premium_calculation_request['RequestBody']['PrePolicyStartDate'] =$previous_policy_start_date . 'T00:00:00';
    }
    if($premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == '0' || $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == 0){
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate'] = '';
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBProof'] = '';
    }
    if ($premium_type != 'third_party') {
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'ProductElementCode' => 'C101064',
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'PolicyBenefitList' => [
                [
                    'ProductElementCode' => 'B00002',
                ]
            ],
            'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
        ];
        // Key Replacement
        if($interval->days < 3470){
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'ProductElementCode' => 'C101073',
        ];
        }

        //loss of personal belonging
        if($interval->days < 3470){
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'SumInsured' => 10000,
            'ProductElementCode' => 'C101075',
        ];
        }
        //if ($vehicle_age < 5) { 
        if ($interval->days < 2374) { //Consumable Till 6.5 Years
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'ProductElementCode' => 'C101111',
        ];
    }
        //if ($vehicle_age < 8) {  Road Side Assistance
        if ($interval->days < 2374) {
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'EffectiveDate' => $policy_start_date . 'T00:00:00',
                'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
                'ProductElementCode' => 'C101069',
                'PolicyBenefitList' => [
                    [
                        'ProductElementCode' => 'B00025',
                        'ProviderName' => '',//'ProviderName',
                    ]
                ]
            ];

            //if ($vehicle_age < 3) {
            //Return to invoice
            if ($interval->days < 913) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101067',
                    // 'SumInsured' => 99999999999,
                ];
            }
            //engine
            if (!(($interval->y > 6) || ($interval->y == 6 && $interval->m > 6) || ($interval->y == 6 && $interval->m == 6 && $interval->d > 0))) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101108',
                ];
            }

            if ($requestData->is_claim == 'N' && $requestData->business_type != 'newbusiness') {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'ProductElementCode' => 'C101068',
                    'NoClaimDiscount' => ($requestData->applicable_ncb / 100),
                ];
            }
        }
    }
if($premium_type != 'own_damage')
{
    if ($cover_pa_unnamed_passenger || $cover_pa_paid_driver || $requestData->vehicle_owner_type == 'I') {
        if ($cover_pa_unnamed_passenger) {
            $coverage['PolicyBenefitList'][] = 
                    [
                        'SumInsuredPerUnit' => $cover_pa_unnamed_passenger_amt,
                        'TotalSumInsured'   => $cover_pa_unnamed_passenger_amt * $mmv_data->carrying_capacity,
                        'ProductElementCode' => 'B00016'
                    ];
        }
        if ($cover_pa_paid_driver){
            $coverage['PolicyBenefitList'][] = 
                    [
                        'SumInsuredPerUnit' => $cover_pa_paid_driver_amt,
                        'TotalSumInsured'   => $cover_pa_paid_driver_amt * $no_of_paid_driver,
                        'ProductElementCode' => 'B00027'
                    ];
        }
        $coverage['EffectiveDate'] = $policy_start_date . 'T00:00:00';
        $coverage['OwnerHavingValidDrivingLicence'] = '1';
        $coverage['OwnerHavingValidPACover'] = '1';
        // $coverage['ExpiryDate'] = $policy_end_date . 'T23:59:59'; // Default to one-year tenure
        $coverage['ExpiryDate'] = $policyEndDate . 'T23:59:59'; //for cpa changes
        $coverage['ProductElementCode'] = 'C101066';
        
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = $coverage;
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'PolicyBenefitList' => [
                [
                    // 'SumInsured' => 750000,
                    'ProductElementCode' => 'B00008',
                ]
            ],
            'ExpiryDate' => ($requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' :$policy_end_date . 'T23:59:59',
            'ProductElementCode' => 'C101065',
        ];
    } else {
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'PolicyBenefitList' => [
                [
                    // 'SumInsured' => 9999999,
                    'ProductElementCode' => 'B00008',
                ]
            ],
            'ExpiryDate' => ($requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' :$policy_end_date . 'T23:59:59',
            'ProductElementCode' => 'C101065',
        ];
    }


    $tp_only = search_for_id_sbi('C101065', $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];
    if ($requestData->vehicle_owner_type == 'I') {
    $index_id = search_for_id_sbi('C101066', $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];


        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
            'SumInsured' => 1500000,
            'ProductElementCode' => 'B00015',
            'NomineeName' => 'Sai',
            'NomineeRelToProposer' => '1',
            'NomineeDOB' => '1988-04-19',
            'NomineeAge' => '29',
            'AppointeeName' => 'AppointeeName',
            'AppointeeRelToNominee' => '2',
            'EffectiveDate' => ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date.'T00:00:00',
            'ExpiryDate' => ($requestData->business_type == 'newbusiness' && $cpa_tenure == 3) ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59', //for cpa changes
        ];
    }
}
    $add_cover = $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];

    foreach ($add_cover as $add) {
        if ($add['ProductElementCode'] == 'C101064') {
            if ($non_electrical_accessories) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                    'SumInsured' => $non_electrical_accessories_amount,
                    'Description' => '',//'Description',       #Passing as empty as per git #33646
                    'ProductElementCode' => 'B00003'
                ];
            }

            if ($electrical_accessories) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                    'SumInsured' => $electrical_accessories_amount,
                    'Description' => '',//'Description',        #Passing as empty as per git #33646
                    'ProductElementCode' => 'B00004'
                ];
            }

            if ($external_fuel_kit) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                    'SumInsured' => $external_fuel_kit_amount,
                    'Description' => 'Description',
                    'ProductElementCode' => 'B00005'
                ];
            }
            if ($inbuilt_fuel_kit) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                    // 'SumInsured' => 0,
                    'Description' => '', //'Description',   #Passing as empty as per git #33646
                    'ProductElementCode' => 'B00006'
                ];
            }
        }

        if ($add['ProductElementCode'] == 'C101065') {
                            
            if ($external_fuel_kit || $inbuilt_fuel_kit) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                    'ProductElementCode' => 'B00010',
                ];
            }
            if ($is_tppd) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                    'SumInsured' => 6000,
                    'ProductElementCode' => 'B00009',
                ];
            }
            if ($cover_ll_paid_driver || $cover_pa_paid_driver) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                    'ProductElementCode' => 'B00013',
                ];
            }

            //IMT29- Legal Liability to Employees Benefit is mandatory for Organization Customer.
            if ($requestData->vehicle_owner_type == 'C') {
                $emloyee_count = 0;
                if (!empty($mmv_data->seating_capacity)) {
                    $emloyee_count = (int) $mmv_data->seating_capacity;
                }
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['EmployeeCount'] = $emloyee_count;
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                    'ProductElementCode' => 'B00012',
                ];
            }
        }

        if ($add['ProductElementCode'] == 'C101066'){
            if ($cover_pa_unnamed_passenger && $requestData->vehicle_owner_type == 'I') {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                    'SumInsuredPerUnit' => $cover_pa_unnamed_passenger_amt,
                    'TotalSumInsured' => $cover_pa_unnamed_passenger_amt * $mmv_data->carrying_capacity,
                    'ProductElementCode' => 'B00016',
                ];
            }

            if ($cover_pa_paid_driver && $requestData->vehicle_owner_type == 'I') {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                    'SumInsuredPerUnit' => $cover_pa_paid_driver_amt,
                    'TotalSumInsured' => $cover_pa_paid_driver_amt * $no_of_paid_driver,
                    'ProductElementCode' => 'B00027',
                ];
            }
        }
    }
    //nil depreciation
    if ($productData->product_identifier == 'zerodep' && $interval->days < 2374) {
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'SumInsured' => 1000,
            'ProductElementCode' => 'C101072',
            'DRClaimLimit'      => '2',
            'TypeofGarragePrf'  => '1'
        ];
    }
    if ($premium_type == 'own_damage') {
        $own_damage_req = [
            'ActiveLiabilityPolicyEffDate' => $tp_start_date,
            'ActiveLiabilityPolicyExpDate' => $tp_end_date,
            'ActiveLiabilityPolicyNo' => $tpPolicyno,
            'ActiveLiabilityPolicyInsurer' => $tp_insurer_name,
        ];

        $premium_calculation_request['RequestBody'] = array_merge($premium_calculation_request['RequestBody'], $own_damage_req);
    }
    if($is_pos)
    {
        $premium_calculation_request['RequestBody']['AgentType']='POSP';
        $premium_calculation_request['RequestBody']['AgentFirstName']=$posp_name;
        $premium_calculation_request['RequestBody']['AgentMiddleName']='';
        $premium_calculation_request['RequestBody']['AgentLastName']='';
        $premium_calculation_request['RequestBody']['AgentPAN']=$posp_pan_number;
        $premium_calculation_request['RequestBody']['AgentMobile']=$posp_unique_number;
        $premium_calculation_request['RequestBody']['AgentBranchID']='';
        $premium_calculation_request['RequestBody']['AgentBranch']='';
    }
    $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;

    if($is_renewbuy){

        $premium_calculation_request['RequestBody']['AgentType']='';
        $premium_calculation_request['RequestBody']['AgentFirstName']='';
        $premium_calculation_request['RequestBody']['AgentMiddleName']='';
        $premium_calculation_request['RequestBody']['AgentLastName']='';
        $premium_calculation_request['RequestBody']['AgentPAN']='';
        $premium_calculation_request['RequestBody']['AgentMobile']='';
        $premium_calculation_request['RequestBody']['AgentBranchID']='';
        $premium_calculation_request['RequestBody']['AgentBranch']='';
    
    }
    $data = cache()->remember('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN.CAR', 60 * 2.5, function () use ($enquiryId, $productData) {
        return getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
            'enquiryId' => $enquiryId,
            'requestMethod' => 'get',
            'productName'  => $productData->product_name,
            'company'  => 'sbi',
            'section' => $productData->product_sub_type_code,
            'method' => 'Get Token',
            'transaction_type' => 'quote'
        ]);
    });

    if ($data['response']) {
        $token_data = json_decode($data['response'], TRUE);
        if (empty($token_data['access_token'])) {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => false,
                'message' => 'Token Generation Issue'
            ];
        }
        $temp_data = $premium_calculation_request;
        unset($temp_data['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'], $temp_data['RequestHeader']['requestID'], $temp_data['RequestHeader']['transactionTimestamp'],$temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate']);
        if (isset($index_id)) {
            unset($temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][0]['EffectiveDate']);
        }
        $checksum_data = checksum_encrypt($temp_data);
        $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'sbi',$checksum_data,'CAR');
        if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
        {
            $data = $is_data_exits_for_checksum;
        }
        else
        {
            $data = getWsData(
                config('constants.IcConstants.sbi.SBI_END_POINT_URL_MOTOR_FULLQUOTE'), $premium_calculation_request, 'sbi', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'authorization' => $token_data['access_token'],
                    'productName'  => $productData->product_name,
                    'company'  => 'sbi',
                    'section' => $productData->product_sub_type_code,
                    'checksum' => $checksum_data,
                    'method' =>'Premium Calculation',
                    'transaction_type' => 'quote'
                ]
            );
        }
        if ($data['response']) {
            $premium_response = json_decode($data['response'], TRUE);
            if(isset($premium_response['httpMessage']) && isset($premium_response['moreInformation']))
            {
                return 
                [
                    'premium_amount'    => 0,
                    'status'            => false,
                    'message'           => $premium_response['moreInformation'].'.'.$premium_response['httpMessage']
                ];
            }
            if(isset($premium_response['UnderwritingResult']['MessageList'][0]['Message']))
            {
                return 
                [
                    'premium_amount' => 0,
                    'status'         => false,
                    'message'        => $premium_response['UnderwritingResult']['MessageList'][0]['Message']
                ];
            }
            $skip_second_call = false;
            if(isset($premium_response['PolicyObject']))
            {
            if($premium_type != 'third_party'){
            $idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            $min_idv = (($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MinIDV_Suggested']));
            $max_idv = (($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MaxIDV_Suggested']));
            $default_idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            $idvsuggested = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_Suggested'];
            if($idv >= 5000000 && config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') != 'Y')
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Quotes cannot be displayed as Vehicle IDV  selected is greater or equal to 50 lakh'
                ];
            }
            }else{
            $min_idv = 0;
            $max_idv = 0;
            $default_idv = 0;
            }
            update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], 'success', 'success');

            if ($requestData->is_idv_changed == 'Y') {                       	
                if ($requestData->edit_idv >= $max_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                    $vehicle_idv = $max_idv ;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                    $vehicle_idv = $min_idv ;
                } else {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $requestData->edit_idv;
                    $vehicle_idv = $requestData->edit_idv ;
                }
            } else {
                #$premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $default_idv;
                        $vehicle_idv = $default_idv ;
                        $skip_second_call = true;
                        break;
                    case 'min_idv':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                        $vehicle_idv = $min_idv ;
                        break;
                    case 'max_idv':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                        $vehicle_idv = $max_idv ;
                        break;
                    default:
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                        $vehicle_idv = $min_idv;
                        break;
                }
            }

            if (config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $vehicle_idv >= 5000000 || $is_renewbuy)
            {
                $premium_calculation_request['RequestBody']['AgentType']='';
                $premium_calculation_request['RequestBody']['AgentFirstName']='';       
                $premium_calculation_request['RequestBody']['AgentMiddleName']='';
                $premium_calculation_request['RequestBody']['AgentLastName']='';
                $premium_calculation_request['RequestBody']['AgentPAN']='';
                $premium_calculation_request['RequestBody']['AgentMobile']='';
                $premium_calculation_request['RequestBody']['AgentBranchID']='';
                $premium_calculation_request['RequestBody']['AgentBranch']='';
            }elseif(!empty($pos_data))
            {
                $premium_calculation_request['RequestBody']['AgentType']='POSP';
                $premium_calculation_request['RequestBody']['AgentFirstName']=$pos_data->agent_name;
                $premium_calculation_request['RequestBody']['AgentMiddleName']='';
                $premium_calculation_request['RequestBody']['AgentLastName']='';
                $premium_calculation_request['RequestBody']['AgentPAN']=$pos_data->agent_mobile;
                $premium_calculation_request['RequestBody']['AgentMobile']=$pos_data->agent_mobile;
                $premium_calculation_request['RequestBody']['AgentBranchID']='';
                $premium_calculation_request['RequestBody']['AgentBranch']='';
            }
            if(!$skip_second_call){
                $temp_data = $premium_calculation_request;
                unset($temp_data['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'], $temp_data['RequestHeader']['requestID'], $temp_data['RequestHeader']['transactionTimestamp'],$temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate']);
                if (isset($index_id)) {
                    unset($temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][0]['EffectiveDate']);
                }
                $checksum_data = checksum_encrypt($temp_data);
                $is_data_exits_for_checksum = get_web_service_data_via_checksum($enquiryId,'sbi',$checksum_data,'CAR');
                if($is_data_exits_for_checksum['found'] && $refer_webservice && $is_data_exits_for_checksum['status'])
                {
                    $data = $is_data_exits_for_checksum;
                }
                else
                {
                    $data = getWsData(
                        config('constants.IcConstants.sbi.SBI_END_POINT_URL_MOTOR_FULLQUOTE'), $premium_calculation_request, 'sbi', [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'authorization' => $token_data['access_token'],
                            'productName'  => $productData->product_name,
                            'company'  => 'sbi',
                            'section' => $productData->product_sub_type_code,
                            'checksum' => $checksum_data,
                            'method' =>'Premium Recalculation',
                            'transaction_type' => 'quote'
                        ]
                    );
                }
        }
            if ($data['response']) {
                $premium_response = json_decode($data['response'], TRUE);
                if(isset($premium_response['httpMessage']) && isset($premium_response['moreInformation']))
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'status' 	 => false,
                        'message'        => $premium_response['moreInformation'].'.'.$premium_response['httpMessage']
                    ];
                }
                if(isset($premium_response['UnderwritingResult']['MessageList'][0]['Message']))
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'status'         => false,
                        'message'        => $premium_response['UnderwritingResult']['MessageList'][0]['Message']
                    ];
                }
                if(!isset($premium_response['PolicyObject']))
                {
                    $message = 'Invalid response received from insurance company';
                    if(!empty($premium_response['messages'][0]['message']))
                    {
                        $message = $premium_response['messages'][0]['message'];
                    }
                    else if(!empty($premium_response['ValidateResult']['message']))
                    {
                        $message = $premium_response['ValidateResult']['message'];
                    }
                    else if(!empty($premium_response['message']))
                    {
                        $message = $premium_response['message'];
                    }
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => false,
                        'premium' => '0',
                        'message' => $message//isset($premium_response['messages'][0]['message']) ? $premium_response['messages'][0]['message'] : (isset($premium_response['ValidateResult']['message']) ? $premium_response['ValidateResult']['message'] : 'Insurer not reachable')
                    ];
                }
                $idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] ?? 0;
                if($idv >= 5000000)
                {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Quotes cannot be displayed as Vehicle IDV  selected is greater or equal to 50 lakh'
                    ];
                }
                if (isset($premium_response['UnderwritingResult']['MessageList'])) {

                } else {
                    $vehicle_idv = ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] ?? 0);
                    $default_idv = ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] ?? 0);

                    $llpaiddriver_premium = 0;
                    $legal_liability_to_employee = 0;
                    $cover_pa_owner_driver_premium = 0;
                    $cover_pa_paid_driver_premium = 0;
                    $cover_pa_unnamed_passenger_premium = 0;
                    $voluntary_excess = 0;
                    $anti_theft = 0;
                    $ic_vehicle_discount = 0;
                    $ncb_discount = 0;
                    $od = 0;
                    $tppd = 0;
                    $cng_lpg = 0;
                    $cng_lpg_tp = 0;
                    $electrical_accessories_amt = 0;
                    $non_electrical_accessories_amt = 0;
                    $road_side_assistance = 0;
                    $zero_depreciation = 0;
                    $return_to_invoice = 0;
                    $ncb_protection = 0;
                    $key_replacement_cover = 0;
                    $loss_of_personal_belongings = 0;
                    $engine_protector = 0;
                    $consumables = 0;
                    $OD_BasePremium =0;
                    $inbuilt_cng_lpg = 0;
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;
                    $LoadingAmt = 0;
                    $tppd_discount = ($is_tppd)? (($requestData->business_type == 'newbusiness') ? 300 : 100) :0;
                    if($premium_type != 'third_party'){
                    $ncb_discount = ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['OD_NCBAmount']);
                    }
                    if ($is_voluntary_access) {
                        $voluntary_excess = (isset($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['DeductibleAmount'])? $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['DeductibleAmount'] :'0');
                    }
                    foreach ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'] as $key => $cover) {
                        if ($cover['ProductElementCode'] == 'C101064') {
                            foreach ($cover['PolicyBenefitList'] as $key => $od_cover) {
                                if ($od_cover['ProductElementCode'] == 'B00002') {
                                    $od = $od_cover['GrossPremium']; //+ $cover['LoadingAmount'];
                                } elseif ($od_cover['ProductElementCode'] == 'B00003') {
                                    $non_electrical_accessories_amt = $od_cover['GrossPremium'];
                                } elseif ($od_cover['ProductElementCode'] == 'B00004') {
                                    $electrical_accessories_amt = $od_cover['GrossPremium'];
                                } elseif ($od_cover['ProductElementCode'] == 'B00005') {
                                    $cng_lpg = $od_cover['GrossPremium'];
                                }elseif ($od_cover['ProductElementCode'] == 'B00006') {
                                    $inbuilt_cng_lpg = $od_cover['GrossPremium'];
                                }
                            }
                        } elseif ($cover['ProductElementCode'] == 'C101069') {
                            foreach ($cover['PolicyBenefitList'] as $key => $ra_cover) {
                                if ($ra_cover['ProductElementCode'] == 'B00025') {
                                    $road_side_assistance = $ra_cover['GrossPremium'];
                                }
                            }
                        } elseif ($cover['ProductElementCode'] == 'C101065') {
                            foreach ($cover['PolicyBenefitList'] as $key => $tp_cover) {
                                if ($tp_cover['ProductElementCode'] == 'B00008') {
                                    $tppd = $tp_cover['GrossPremium'];
                                } elseif ($tp_cover['ProductElementCode'] == 'B00010') {
                                    $cng_lpg_tp = $tp_cover['GrossPremium'];
                                } elseif ($tp_cover['ProductElementCode'] == 'B00013') {
                                    $llpaiddriver_premium = $tp_cover['GrossPremium'];
                                } elseif ($tp_cover['ProductElementCode'] == 'B00012') {
                                    $legal_liability_to_employee = $tp_cover['GrossPremium'];
                                }
                            }
                        } elseif ($cover['ProductElementCode'] == 'C101066') {
                            foreach ($cover['PolicyBenefitList'] as $key => $pa_cover) {
                                if ($pa_cover['ProductElementCode'] == 'B00015') {
                                    $cover_pa_owner_driver_premium = $pa_cover['GrossPremium'];
                                } elseif ($pa_cover['ProductElementCode'] == 'B00016') {
                                    $cover_pa_unnamed_passenger_premium = $pa_cover['GrossPremium'];
                                } elseif ($pa_cover['ProductElementCode'] == 'B00027') {
                                    $cover_pa_paid_driver_premium = $pa_cover['GrossPremium'];
                                }
                            }
                        } elseif ($cover['ProductElementCode'] == 'C101072') {
                            $zero_depreciation = ($cover['GrossPremium']);
                        } elseif ($cover['ProductElementCode'] == 'C101067') {
                            $return_to_invoice = ($cover['GrossPremium']);
                        } elseif ($cover['ProductElementCode'] == 'C101068') {
                            $ncb_protection = ($cover['GrossPremium']);
                        } elseif ($cover['ProductElementCode'] == 'C101073') {
                            $key_replacement_cover = ($cover['GrossPremium']);
                        } elseif ($cover['ProductElementCode'] == 'C101075') {
                            $loss_of_personal_belongings = ($cover['GrossPremium']);
                        } elseif ($cover['ProductElementCode'] == 'C101108') {
                            $engine_protector = ($cover['GrossPremium']);
                        } elseif ($cover['ProductElementCode'] == 'C101111') {
                            $consumables = ($cover['GrossPremium']);
                        }
                    }
                    if($is_geo_ext && $is_geo_code == 1){
                        $geog_Extension_OD_Premium = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['GeoExtnODPrem'] ?? 0;
                        $geog_Extension_TP_Premium = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['GeoExtnTPPrem'] ?? 0;
                    }
                    if($is_anti_theft =='1' && $premium_type!='third_party')
                    {
                        $OD_BasePremium =$premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['OD_BasePremium'];
                        $anti_theft = (0.025 * $OD_BasePremium);
                        if($anti_theft > 500)
                        {
                            $anti_theft = 500;#antitheft max amount is 500 only
                        }
                    }
                    if(isset($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['LoadingAmount'])){
                        $LoadingAmt = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['LoadingAmount'] ?? 0;
                    }
                    $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt + $inbuilt_cng_lpg;
                    $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $legal_liability_to_employee + $geog_Extension_TP_Premium;

                        if (!$cover_ll_paid_driver) {
                            $final_tp_premium = $final_tp_premium - $llpaiddriver_premium;
                        }

                    $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount + $tppd_discount;
                    $final_net_premium = ($final_od_premium + $final_tp_premium - $final_total_discount);
                    $final_gst_amount = ($final_net_premium * 0.18);
                    $final_payable_amount  = $final_net_premium + $final_gst_amount;

                    $addons_data = [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation' => $productData->product_identifier == 'zerodep' ? $zero_depreciation : 0,
                            'road_side_assistance' => $road_side_assistance,
                            'key_replace' => $key_replacement_cover,
                            'engine_protector' =>  $engine_protector,
                            'ncb_protection' => $ncb_protection,
                            'consumables' => $consumables,
                            'tyre_secure' => 0,
                            'return_to_invoice' => $return_to_invoice,
                            'lopb' => $loss_of_personal_belongings
                        ]
                    ];

                    $addons_data['in_built_premium'] = 0;
                    $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                    $addons_data['other_premium'] = 0;

                    $applicable_addons = [
                        'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'engineProtector', 'ncbProtection', 'consumables', 'returnToInvoice', 'lopb'
                    ];

                    if ($vehicle_age > 5) {
                        array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                    }

                    if ($vehicle_age >= 3) {
                        array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                    }
                    if($ncb_protection == 0)
                    {
                        array_splice($applicable_addons, array_search('ncbProtection', $applicable_addons), 1);
                    }
                    if($vehicle_age > 5)
                    {
                        array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    }
                    if($vehicle_age >= 5)
                    {
                        array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                    }
                    if ($vehicle_age >= 8) 
                    {
                        array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                    }

                    $return_data = [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv' => $vehicle_idv,
                            'min_idv' => ($min_idv),
                            'max_idv' => ($max_idv),
                            'default_idv' => $default_idv,
                            'vehicle_idv' => $vehicle_idv,
                            'qdata' => null,
                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                            'addonCover' => null,
                            'addon_cover_data_get' => '',
                            'rto_decline' => null,
                            'rto_decline_number' => null,
                            'mmv_decline' => null,
                            'mmv_decline_name' => null,
                            'policy_type' => ($premium_type=='third_party' ? 'Third Party' : ($premium_type=='own_damage' ?'Own Damage':'Comprehensive')),
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => '',
                            'vehicle_registration_no' => $requestData->rto_code,
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
                                'manf_name'             => $mmv_data->vehicle_manufacturer,
                                'model_name'            => $mmv_data->vehicle_model_name,
                                'version_name'          => $mmv_data->variant,
                                'fuel_type'             => $mmv_data->fuel_type,
                                'seating_capacity'      => $mmv_data->seating_capacity,
                                'carrying_capacity'     => $mmv_data->carrying_capacity,
                                'cubic_capacity'        => $mmv_data->cubic_capacity,
                                'gross_vehicle_weight'  => 1,//$mmv_data->gross_weight ?? 1,
                                'vehicle_type'          => '',//$mmv_data->vehicle_class_desc,
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
                                'corp_name' => "Ola Cab",
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
                            'tppd_premium_amount' => $tppd,  
                            'tppd_discount' => $tppd_discount,
                            'motor_electric_accessories_value' => $electrical_accessories_amt,
                            'motor_non_electric_accessories_value' => $non_electrical_accessories_amt,
                            /* 'motor_lpg_cng_kit_value' => ($inbuilt_fuel_kit == true ? $inbuilt_cng_lpg : $cng_lpg), */
                            'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                            'multi_year_cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium*3 : 0,
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'default_paid_driver' => $llpaiddriver_premium,
                            'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                            'multi_year_motor_additional_paid_driver' => $cover_pa_paid_driver_premium*3,
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                            'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                            'total_accessories_amount(net_od_premium)' => 0,
                            'total_own_damage' => $final_od_premium,
                            /* 'cng_lpg_tp' => $cng_lpg_tp, */
                            'total_liability_premium' => $final_tp_premium,
                            'total_loading_amount' => $LoadingAmt,
                            'net_premium' => $final_net_premium,
                            'service_tax_amount' => $final_gst_amount,
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => 0,
                            'addon_premium' => 0,
                            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                            'quotation_no' => $premium_response['PolicyObject']['QuotationNo'],
                            'premium_amount'  => $final_payable_amount,
                            'antitheft_discount' => $anti_theft,
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' => ($final_total_discount),
                            'final_net_premium' => $final_net_premium,
                            'final_gst_amount' => $final_gst_amount,
                            'final_payable_amount' => $final_payable_amount,
                            'service_data_responseerr_msg' => 'success',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => ($requestData->business_type =='newbusiness') ? 'New Business' : ((($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party' ) || $requestData->previous_policy_type == 'Not sure') ? 'Break-in' :$requestData->business_type),
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
                        ];


                        if(isset($cpa_tenure))
                        {
                            if($requestData->business_type == 'newbusiness' && $cpa_tenure  == 3)
                            {
                                // unset($return_data['Data']['compulsory_pa_own_driver']);
                                $return_data['Data']['multi_Year_Cpa'] =  $cover_pa_owner_driver_premium;
                            }
                        }
                        if ($premium_type == 'third_party' || $premium_type == 'third_party_breakin')
                        {
                            unset($return_data['Data']['motor_lpg_cng_kit_value']);
                        }
                        if($external_fuel_kit || $inbuilt_fuel_kit)
                        {
                            $return_data['Data']['motor_lpg_cng_kit_value'] = ($inbuilt_fuel_kit == true ? $inbuilt_cng_lpg : $cng_lpg);
                            $return_data['Data']['cng_lpg_tp'] = $cng_lpg_tp;
                        }

                        if (!empty($legal_liability_to_employee)) {
                            $return_data['Data']['other_covers'] = [
                                'LegalLiabilityToEmployee' => ($legal_liability_to_employee)
                            ];
                            $return_data['Data']['LegalLiabilityToEmployee'] = ($legal_liability_to_employee);
                        }

                    return camelCase($return_data);
                }
            } else {

            }
        } else {
            $message = 'Invalid response received from insurance company';
            if(!empty($premium_response['messages'][0]['message']))
            {
                $message = $premium_response['messages'][0]['message'];
            }
            else if(!empty($premium_response['ValidateResult']['message']))
            {
                $message = $premium_response['ValidateResult']['message'];
            }
            else if(!empty($premium_response['message']))
            {
                $message = $premium_response['message'];
            }
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => false,
                'premium' => '0',
                'message' => $message//isset($premium_response['messages'][0]['message']) ? $premium_response['messages'][0]['message'] : (isset($premium_response['ValidateResult']['message']) ? $premium_response['ValidateResult']['message'] : 'Insurer not reachable')
            ];
        }
        } else {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'status' => false,
                'premium' => '0',
                'message' => 'Insurer not reachable'
            ];
        }
    } else {
        return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => false,
            'premium' => '0',
            'message' => 'Token Generation Issue'
        ];
    }
}