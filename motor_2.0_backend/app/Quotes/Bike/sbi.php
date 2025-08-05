<?php
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
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
    if($premium_type == 'third_party_breakin')
    {
        $premium_type = 'third_party';
    }
    if($premium_type == 'own_damage_breakin')
    {
        $premium_type = 'own_damage';
    }
    $expdate=(($requestData->previous_policy_expiry_date == 'New') || ($requestData->business_type == 'breakin') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($expdate);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = ceil($age / 12);
    //$vehicle_age = car_age($requestData->vehicle_register_date,$expdate);
    $tp_check = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';
    
    if (($interval->y >= 12) && ($tp_check == 'true')){
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Third Party Quotes are not allowed for Vehicle age greater than 12 year',
        ];
    }
    $motor_manf_date = '01-'.$requestData->manufacture_year;

    if ($requestData->business_type == 'newbusiness') {
        $policy_start_date = date('Y-m-d');
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $expiry_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $nb_date = date('Y-m-d', strtotime('+5 year -1 day', strtotime($policy_start_date)));
        $time = date('h:i:s');
        $business_type = 1;
        $previous_policy_expiry_date = '1900-01-01';
        $previous_policy_start_date = '1900-01-01';
    } elseif ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
        $policy_start_date = ($requestData->business_type == 'breakin') ? date('Y-m-d') : date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        $business_type = 2;
        $previous_policy_expiry_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
        $previous_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
        if ($premium_type == 'own_damage') {

            // $PolicyType = '9';
             $BusinessType = '2';
             $tp_start_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) . 'T00:00:00';
             $tp_end_date = date('Y-m-d', strtotime('+3 year -1 day', strtotime(str_replace('/', '-', $tp_start_date)))) . 'T23:59:59';
             $tp_insurer_name = '15';
             $tpPolicyno = '123455';
         } else {

             //$PolicyType = '1';
             $BusinessType = '2';
             $tp_start_date = '';
             $tp_end_date = '';
             $tp_insurer_name = '';
             $tpPolicyno = '';
         }
         $expdate =($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
         $days = get_date_diff('day', $expdate);
         $time =( $days > 0) ? date('h:i:s'): '00:00:00';
    }
    $kind_of_policy = ($premium_type == 'third_party') ? '2' : (($premium_type == 'own_damage') ? '3' :  '1');

    if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
        $expdate=$requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
        $date_difference = get_date_diff('day', $expdate);
        if ($date_difference > 90) {  
            $requestData->applicable_ncb = 0;
        }
    }
    $city_name = DB::table('master_rto')
        ->where('rto_number', $requestData->rto_code)
        ->get();

    $rto_data = (object)[];
    if ($city_name->isEmpty()) {
        return [
            'status' => false,
            'premium_amount' => 0,
            'message' => 'RTO not available'
        ];
    } else {
        foreach ($city_name as $city) {
            $rto_data = DB::table('sbi_bike_rto_location')
                ->where('RTO_CODE', 'like', $city->rto_number)
                ->first();
            $rto_data = keysToLower($rto_data);
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
    #addon age limit code start
    $engineprotector_age_limit = date('Y-m-d', strtotime($policy_start_date . ' - 28 days - 11 months - 4 year'));
    $rsa_addon_age_limit = date('Y-m-d', strtotime($policy_start_date . ' - 28 days - 11 months - 6 year'));
    $rti_addon_age_limit = date('Y-m-d', strtotime($policy_start_date . ' - 28 days - 11 months - 2 year'));
    if(strtotime($engineprotector_age_limit) > strtotime($requestData->vehicle_register_date))
    {
        $engineprotector_age = false;
        $consumable_age = false;
    }else{
        $engineprotector_age = true;
        $consumable_age = true;
    }
    $consumable_age = false;//CONSUMABLE COVER IS NOT PROVIDED BY IC AS PER MAIL GIT ID 9372
    if(strtotime($rsa_addon_age_limit) > strtotime($requestData->vehicle_register_date))
    {
        $rsa_addon_age= false;
    }else{
        $rsa_addon_age= true;
    }
    if(strtotime($rti_addon_age_limit) > strtotime($requestData->vehicle_register_date))
    {
        $rti_addon_age = false;
    }else{
        $rti_addon_age = true;
    }
    $rti_addon_age = false;
    #addon age limit code end
    $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
    ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts','compulsory_personal_accident')
    ->first();

    $cpa_tenure = 0;

    if ($additional && $additional->compulsory_personal_accident != NULL && $additional->compulsory_personal_accident != '') {
        $addons = $additional->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? $value['tenure'] : 1;

            }
        }
    }

    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 5; 
        }
        else{
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : 1;
        }
    }
   
    $policyEndDate = ($cpa_tenure == 5) 
    ? date('Y-m-d', strtotime('+5 years -1 day', strtotime($policy_start_date))) 
    : $policy_end_date;

    $electrical_accessories = false;
    $electrical_accessories_amount = 0;
    $non_electrical_accessories = false;
    $non_electrical_accessories_amount = 0;
    $external_fuel_kit = false;
    $fuel_type = $mmv_data->fuel_type;
    $external_fuel_kit_amount = 0;
    $is_geo_ext = false;
    $is_geo_code = 0;
    $srilanka = 0;
    $pak = 0;
    $bang = 0;
    $bhutan = 0;
    $nepal = 0;
    $maldive = 0;
    $is_tppd = false;
    if (!empty($additional['accessories'])) {
        foreach ($additional['accessories'] as $key => $data) {
            if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                if ($data['sumInsured'] < 15000 || $data['sumInsured'] > 50000) {
                    return [
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
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


    if (!empty($additional['additional_covers'])) {
        foreach ($additional['additional_covers'] as $key => $data) {
            if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured']) && $requestData->vehicle_owner_type == 'I') {
                $cover_pa_paid_driver = true;
                $cover_pa_paid_driver_amt = $data['sumInsured'];
                $no_of_paid_driver = 1;

            }

            if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']) && $requestData->vehicle_owner_type == 'I') {
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
    if ($premium_type == 'comprehensive' || $premium_type == 'breakin') {
        $policy_type = ($requestData->business_type == 'newbusiness') ? '6' :'1';
    }
    if($premium_type == 'third_party'){
        if ($requestData->business_type == 'newbusiness') {
            $policy_type = '8';
        } else {
            $policy_type = '2';
        }
    }
    if($premium_type == 'own_damage'){
        $policy_type = '9';
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

    if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
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
        if($pos_data) {
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

    $engine_number = '984562';
    $chassis_number = '145263';
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
            $registration_no = config('BIKE_QUOTE_REGISTRATION_NUMBER');
            $registration_no = str_replace('-', '', $registration_no);

            $engine_number = config('BIKE_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('BIKE_QUOTE_CHASSIS_NUMBER');
        }
        
    } else {
        if ($requestData->business_type != 'newbusiness') {
            $registration_no = config('BIKE_QUOTE_REGISTRATION_NUMBER');
            $registration_no = str_replace('-', '', $registration_no);

            $engine_number = config('BIKE_QUOTE_ENGINE_NUMBER');
            $chassis_number = config('BIKE_QUOTE_CHASSIS_NUMBER');
        }
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
            'ChannelType' => '3',
            'CustomerGSTINNo' => '22AAAAA00001Z5',
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'ExpiryDate' => $policy_end_date . 'T23:59:59',
            'HasPrePolicy' => $requestData->business_type == 'newbusiness' ? '0' : '1',
            'InspectionComment' => '',
            'IssuingBranchGSTN' => '22AAAAA00001Z0',
            'KindofPolicy' => $kind_of_policy,
            'NewBizClassification' => (string) $business_type,
            'PolicyCustomerList' => [
                [
                    'AadharNumUHID' => '732545142583', 
                  'BuildingHouseName' => 'Loe', 
                  'City' => 'Bolpur', 
                  'Email' => 'qwerty@thyu.wfdwe', 
                  'ContactEmail' => 'asdfghj@qwer.hjjj', 
                  'ContactPersonTel' => '9849995063', 
                  'CountryCode' => '1000000108', 
                  'Title' => '9000000001', 
                  'FirstName' => 'lokip', 
                  'MiddleName' => 'pikol', 
                  'LastName' => 'odin', 
                  'CustomerName' => 'lokippikolodin', 
                  'DateOfBirth' => $requestData->vehicle_owner_type != 'I' ? '' : '1988-04-19', 
                  'DateOfIncorporation' => '', 
                  'District' => '4000000602', 
                  'EIANumber' => 'EIANumber', 
               //   'EducationCode' => '1', 
                  'GSTRegistrationNum' => '', 
                  'GSTInputState' => '', 
                  'GenderCode' => 'M', 
                  'GroupCompanyName' => 'SBIG', 
                  'GroupEmployeeId' => 'GroupEmployeeId', 
                  'CampaignCode' => 'CampaignCode', 
                  'ISDNum' => 'ISDNum', 
                  'IdNo' => 'BLPPG0173B', 
                  'IdType' => '1', 
                  'IsInsured' => 'N', 
                  'IsOrgParty' => $requestData->vehicle_owner_type == 'I' ? 'N' : 'Y',
                  'IsPolicyHolder' => 'Y', 
                  'Locality' => '1', 
                  'MaritalStatus' => '1', 
                  'Mobile' => '1234578960', 
                  'NationalityCode' => 'IND', 
                  'OccupationCode' => '11', 
                  'PAN' => 'PANPA4652Q', 
                  'PartyStatus' => '1', 
                  'PlotNo' => '123', 
                  'PostCode' => '731204', 
                  'PreferredContactMode' => 'Mail', 
                  'RegistrationName' => 'lokip', 
                  'SBIGCustomerId' => 'SBIGCustomerId', 
                  'STDCode' => '021', 
                  'State' => $rto_data->state_id,
                  'StreetName' => 'There',
                  'CKYCUniqueId' => time(),
                  //Regulatory changes *DUMMY DATA for quote page*
                  "AccountNo" => "124301506743",
                  "IFSCCode"  => "ICIC0001230",
                  "BankName"  => "ICICI Bank",
                  "BankBranch"=> "Parel"
                ], 
            ],
            'PolicyLobList' => [
                [
                    'PolicyRiskList' => [
                        [
                            'VehicleRegistrationType' => isBhSeries($requestData->vehicle_registration_no) ? '2' : '1',
                            'DailyUseDistance' => '1', 
                            'NightParkLoc' => '1', 
                            'NightParkLocPinCode' => '731204', 
                            'DayParkLoc' => '1', 
                            'DayParkLocPinCode' => '731204', 
                            'IsAAMember' => '0', 
                            'IsImportedWithoutDuty' => '0', 
                            'IsHandicappedMod' => '0', 
                            'VehicleCategory' => '1', 
                            'TypeOfPermit' => '1', 
                            'Last3YrDriverClaimPresent' => '0', 
                            'NoOfDriverClaims' => '', 
                            'DriverClaimYr' => '', 
                            'DriverClaimAmt' => '', 
                            'FNBranchName' => 'FNBranchName', 
                            'FNName' => 'FNName', 
                            'FNType' => '3', 
                            'RoadType' => '1', 
                            'IMT34' => '0', 
                            'SiteAddress' => '', 
                            'IMT23' => '0', 
                            'AntiTheftAlarmSystem' => $is_anti_theft, 
                            'ChassisNo' => removeSpecialCharactersFromString($chassis_number),
                            'EngineNo' => $engine_number,
                            'IsGeographicalExtension' => $is_geo_code,
                            'GeoExtnBangladesh' => $bang,
                            'GeoExtnBhutan' => $bhutan,
                            'GeoExtnMaldives' => $maldive,
                            'GeoExtnNepal' => $nepal,
                            'GeoExtnPakistan' => $pak,
                            'GeoExtnSriLanka' => $srilanka, 
                            'IDV_User' => '', 
                            'IsNCB' => 0, //Made changes according to git #30395 $requestData->is_claim == 'Y' ? '1' : '0',
                            'IsNewVehicle' =>$requestData->business_type == 'newbusiness' ? '1' : '0',
                            'ManufactureYear' => $motor_manf_date,
                            'NCB' => $requestData->applicable_ncb / 100,
                            'NCBLetterDate' => $policy_start_date .'T'. $time,
                            'NCBPrePolicy' => $requestData->previous_ncb / 100,
                            'NCBProof' => '1',
                            'PaidDriverCount' => $no_of_paid_driver,//($requestData->vehicle_owner_type == 'I' && ($premium_type != 'own_damage')) ? '1':'0', 
                            'ProductElementCode' => 'R10005',
                            'RTOCityDistric' => $rto_data->rto_dis_code,
                            'RTOCluster' => $rto_data->rto_cluster,
                            'RTOLocation' => $rto_data->location_name,
                            'RTOLocationID' => $rto_data->loc_id,
                            'RegistrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                            'RegistrationNo' => $requestData->business_type == 'newbusiness' ? '' : strtoupper($registration_no),#'MH-05-AB-1111',
                            'Variant' => $mmv_data->variant_id,
                            'VehicleMake' => $mmv_data->vehicle_manufacturer_code,
                            'VehicleModel' => $mmv_data->vehicle_model_code,
                            'VoluntaryDeductible' => $is_voluntary_access ? $voluntary_excess_amt : '',
                            'PolicyCoverageList' => [],
                        ],
                    ],
                    'ProductCode' => 'PM2W001',
                    'PolicyType' => $policy_type,
                    'BranchInfo' => 'HO',
                ],
            ],
            'PremiumCurrencyCode' => 'INR',
            'PremiumLocalExchangeRate' => 1,
            'ProductCode' => 'PM2W001',
            'ProductVersion' => '1.0',
            'ProposalDate' => $policy_start_date,
            'QuoteValidTo' => date('Y-m-d', strtotime('+30 day', strtotime($policy_start_date))),
            'SBIGBranchStateCode' => $rto_data->state_id,
            'SiCurrencyCode' => 'INR',
            'TransactionInitiatedBy' => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY'),
        ]
    ];
    if (config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y') {
        $premium_calculation_request['RequestBody']['SourceType'] = config('constants.IcConstants.sbi.SBI_BIKE_SOURCE_TYPE', '9');
    }
    if($requestData->vehicle_owner_type != 'I'){
        unset($premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['GenderCode']);
    }
    if($premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == '0' || $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == 0){
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate'] = '';
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBProof'] = '';
    }
    if ($requestData->business_type != 'newbusiness' && $requestData->previous_policy_type != 'Not sure') {
        $premium_calculation_request['RequestBody']['PreInsuranceComAddr'] ='PreInsuranceComAddr';
        $premium_calculation_request['RequestBody']['PreInsuranceComName'] ='15';
        $premium_calculation_request['RequestBody']['PrePolicyNo'] ='PrePolicyNo';
        $premium_calculation_request['RequestBody']['PrePolicyEndDate'] =$previous_policy_expiry_date . 'T23:59:59';
        $premium_calculation_request['RequestBody']['PrePolicyStartDate'] =$previous_policy_start_date . 'T00:00:00';
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
       if ($rti_addon_age) {#$vehicle_age <= 3
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'ProductElementCode' => 'C101067',
                // 'SumInsured' => 99999999999,
            ];
        }
        if ($engineprotector_age) {//$vehicle_age <= 5
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'ProductElementCode' => 'C101108',
            ];
        }

        if ($rsa_addon_age) {#$vehicle_age <= 7
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'ProductElementCode' => 'C101069',
            ];
        }
        if ($consumable_age) {#$vehicle_age <= 5
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'ProductElementCode' => 'C101111',
            ];
        }
    }
if($premium_type != 'own_damage')
{
    if ($cover_pa_unnamed_passenger || $cover_pa_paid_driver || $requestData->vehicle_owner_type == 'I') {
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'PolicyBenefitList' => [],
            'OwnerHavingValidDrivingLicence' => '1',
            'OwnerHavingValidPACover' => '1',
            //'ExpiryDate' =>($requestData->business_type == 'newbusiness') ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
            // 'ExpiryDate' => $policy_end_date . 'T23:59:59', // By default we'll pass one year tenure - 25-05-2022
            'ExpiryDate' => $policyEndDate . 'T23:59:59', //for cpa changes
            'ProductElementCode' => 'C101066',
        ];

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
                    // 'SumInsured' => 750000,
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
            'ExpiryDate' => ($requestData->business_type == 'newbusiness' && $cpa_tenure == 5) ? $nb_date.'T23:59:59' : $policy_end_date . 'T23:59:59', //cpa changes the end date
        ];
    }
}
    $add_cover = $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];

    foreach ($add_cover as $add) {
        if ($add['ProductElementCode'] == 'C101064') {
            if ($non_electrical_accessories) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                    'SumInsured' => $non_electrical_accessories_amount,
                    'Description' => 'Description',
                    'ProductElementCode' => 'B00003'
                ];
            }

            if ($electrical_accessories) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                    'SumInsured' => $electrical_accessories_amount,
                    'Description' => 'Description',
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
        }

        if ($add['ProductElementCode'] == 'C101065') {
                            
            if ($external_fuel_kit) {
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
            if ($cover_ll_paid_driver ) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
                    'ProductElementCode' => 'B00013',
                ];
            }

            //IMT29- Legal Liability to Employees Benefit is mandatory for Organization Customer.
            // if ($requestData->vehicle_owner_type == 'C') {
            //     $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$tp_only]['PolicyBenefitList'][] = [
            //         'ProductElementCode' => 'B00012',
            //     ];
            // }
        }

        if ($add['ProductElementCode'] == 'C101066'){
            if ($cover_pa_unnamed_passenger) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                    'SumInsuredPerUnit' => $cover_pa_unnamed_passenger_amt,
                    'TotalSumInsured' => $cover_pa_unnamed_passenger_amt * $mmv_data->carrying_capacity,
                    'ProductElementCode' => 'B00075',
                ];
            }

        }
    }
    if ($productData->zero_dep == '0') {
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'SumInsured' => 1000,
            'ProductElementCode' => 'C101072',
            'DRClaimLimit'      => '1',
            'TypeofGarragePrf'  => '1'
        ];
    }
    if ($premium_type == 'own_damage') {
        $model_config_premium1 = [
            'ActiveLiabilityPolicyEffDate' => $tp_start_date,
            'ActiveLiabilityPolicyExpDate' => $tp_end_date,
            'ActiveLiabilityPolicyNo' => $tpPolicyno,
            'ActiveLiabilityPolicyInsurer' => $tp_insurer_name,
        ];

        $premium_calculation_request['RequestBody'] = array_merge($premium_calculation_request['RequestBody'], $model_config_premium1);
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
    $get_response = cache()->remember('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN.BIKE', 60 * 2.5, function() use ($enquiryId, $productData){
        return getWsData(config('constants.IcConstants.sbi.SBI_END_POINT_URL_GET_TOKEN'), [], 'sbi', [
            'enquiryId' => $enquiryId,
            'requestMethod' =>'get',
            'productName'  => $productData->product_name,
            'company'  => 'sbi',
            'section' => $productData->product_sub_type_code,
            'method' =>'Get Token',
            'transaction_type' => 'quote'
        ]);
    });

    $data = $get_response['response'];
    if ($data) {
        $token_data = json_decode($data, TRUE);
        $temp_data = $premium_calculation_request;
        unset($temp_data['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'], $temp_data['RequestHeader']['requestID'], $temp_data['RequestHeader']['transactionTimestamp'],$temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate']);
        if (isset($index_id)) {
            unset($temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][0]['EffectiveDate']);
        }
        $checksum_data = checksum_encrypt($temp_data);
        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'sbi', $checksum_data, "BIKE");
        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
            $get_response = $is_data_exist_for_checksum;
        }else{
            $get_response = getWsData(
                config('constants.IcConstants.sbi.SBI_END_POINT_URL_BIKE_FULLQUOTE'), $premium_calculation_request, 'sbi', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'authorization' => $token_data['access_token'],
                    'productName'  => $productData->product_name,
                    'company'  => 'sbi',
                    'checksum' => $checksum_data,
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Premium Calculation',
                    'transaction_type' => 'quote'
                    ]
                );
        }

        $data = $get_response['response'];
        $premium_response = json_decode($data, TRUE);
        $skip_second_call = false;
        if(isset($premium_response['PolicyObject'])){
         if (isset($premium_response['UnderwritingResult']['MessageList'])) {
            $error_message = json_encode($premium_response['UnderwritingResult']['MessageList'], true);
                    return [    
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => false,
                        'premium' => '0',
                        'message' => $error_message
                    ];

         } else{
            update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Success", "Success" );
            /* if ($premium_type != 'third_party') {
            $idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            $min_idv = ceil(($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MinIDV_Suggested']));
            $max_idv = floor(($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MaxIDV_Suggested']));
            $default_idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User']; */
            /* if(isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y'){
            if ($requestData->is_idv_changed == 'Y') {                       	
                if ($requestData->edit_idv >= $max_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                } else {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $requestData->edit_idv;
                }
            } else {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
            } */
            if($premium_type != 'third_party')
            {
                $idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
                $min_idv = (($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MinIDV_Suggested']));
                $max_idv = floor(($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MaxIDV_Suggested']));
                $default_idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            }else
            {
                $min_idv = 0;
                $max_idv = 0;
                $default_idv = 0;
            }
            if ($requestData->is_idv_changed == 'Y') {                       	
                if ($requestData->edit_idv >= $max_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                } else {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $requestData->edit_idv;
                }
            } else {
                /* $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv; */
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $default_idv;
                        $skip_second_call = true;
                        break;
                    case 'min_idv':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                        break;
                    case 'max_idv':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                        break;
                    default:
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                        break;
                }
            }
            if(!$skip_second_call){
                $temp_data = $premium_calculation_request;
                unset($temp_data['RequestBody']['PolicyCustomerList'][0]['CKYCUniqueId'], $temp_data['RequestHeader']['requestID'], $temp_data['RequestHeader']['transactionTimestamp'],$temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate']);
                if (isset($index_id)) {
                    unset($temp_data['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][0]['EffectiveDate']);
                }
                $checksum_data = checksum_encrypt($temp_data);
                $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'sbi', $checksum_data, 'BIKE');
                if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status']){
                    $get_response = $is_data_exist_for_checksum;
                }else{
                    $get_response = getWsData(
                        config('constants.IcConstants.sbi.SBI_END_POINT_URL_BIKE_FULLQUOTE'), $premium_calculation_request, 'sbi', [
                            'enquiryId' => $enquiryId,
                            'requestMethod' =>'post',
                            'authorization' => $token_data['access_token'],
                            'productName'  => $productData->product_name,
                            'company'  => 'sbi',
                            'checksum' => $checksum_data,
                            'section' => $productData->product_sub_type_code,
                            'method' =>'Change IDV calcualtion',
                            'transaction_type' => 'quote'
                            ]
                        );
                    }
            }
            $data=$get_response['response'];
            $premium_response = json_decode($data, TRUE);
         // }
         //}
         if(isset($premium_response['PolicyObject'])){
                if (isset($premium_response['UnderwritingResult']['MessageList'])) {
                    $error_message = json_encode($premium_response['UnderwritingResult']['MessageList'], true);
                            return [
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'status' => false,
                                'premium' => '0',
                                'message' => $error_message
                            ];
                        
                } else {
                    $vehicle_idv = ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User']);
                    $default_idv = ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User']);

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
                    $underwriting_loading_amt =0;
                    $geog_Extension_OD_Premium = 0;
                    $geog_Extension_TP_Premium = 0;
                    $tppd_discount = ($is_tppd)? (($requestData->business_type == 'newbusiness') ? 250 : 50) :0;
                    $OD_BasePremium=0;
                    $ncb_discount = $premium_type != 'third_party' ? ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt']): '0';

                    if ($is_voluntary_access) {  //NA for bike
                        $voluntary_excess = (isset($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['VolDeductDiscAmt'])?$premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['VolDeductDiscAmt'] :'0');
                    }

                    foreach ($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'] as $key => $cover) {
                        if ($cover['ProductElementCode'] == 'C101064') {

                            foreach ($cover['PolicyBenefitList'] as $key => $od_cover) {
                                if ($od_cover['ProductElementCode'] == 'B00002') {
                                    $od = ($od_cover['GrossPremium']);
                                } elseif ($od_cover['ProductElementCode'] == 'B00003') {
                                    $non_electrical_accessories_amt = $od_cover['GrossPremium'];
                                } elseif ($od_cover['ProductElementCode'] == 'B00004') {
                                    $electrical_accessories_amt = $od_cover['GrossPremium'];
                                } elseif ($od_cover['ProductElementCode'] == 'B00005') {
                                    $cng_lpg = $od_cover['GrossPremium'];
                                }
                            }
                        } elseif ($cover['ProductElementCode'] == 'C101069') {
                                    $road_side_assistance = $cover['GrossPremium'];
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
                                } elseif ($pa_cover['ProductElementCode'] == 'B00075') {
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
                    if($is_anti_theft =='1' && $premium_type!='third_party')
                    {
                        $anti_theft =isset($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['AntiTheftDiscAmt']) ?($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['AntiTheftDiscAmt']) : '0';
                    }
                    if($is_geo_ext && $is_geo_code == 1){
                        $geog_Extension_OD_Premium = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['GeoExtnODPrem'] ?? 0;
                        $geog_Extension_TP_Premium = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['GeoExtnTPPrem'] ?? 0;
                    }
                    
                    $final_od_premium = $premium_type != 'third_party' ? ($od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt + $geog_Extension_OD_Premium):'0';
                    $final_tp_premium = $premium_type != 'own_damage' ? ($tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium +  $geog_Extension_TP_Premium):'0';
                    $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount+$tppd_discount;
                    $checkvalue=abs(($final_od_premium - $final_total_discount +$tppd_discount));
                    if($checkvalue < 100)
                    {
                        $underwriting_loading_amt= 100-$checkvalue;
                    }
                    $final_net_premium = ($final_od_premium + $final_tp_premium - $final_total_discount);
                    $final_gst_amount = ($final_net_premium * 0.18);
                    $final_payable_amount  = $final_net_premium + $final_gst_amount;

                    $addons_data = [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation' => $productData->zero_dep == '0' ? $zero_depreciation : 0,
                            'road_side_assistance' => $road_side_assistance,
                            'engine_protector' => $engineprotector_age ? $engine_protector: 0,
                            'consumables' => $consumables,
                            'return_to_invoice' => $return_to_invoice,
                        ]
                    ];

                    $addons_data['in_built_premium'] = 0;
                    $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                    $addons_data['other_premium'] = 0;

                    $applicable_addons = [
                        'zeroDepreciation', 'roadSideAssistance', 'engineProtector', 'consumables', 'returnToInvoice'
                    ];

                    if ($engineprotector_age == false) {
                        array_splice($applicable_addons, array_search('engineProtector', $applicable_addons), 1);
                    }

                    if ($rti_addon_age == false) {
                        array_splice($applicable_addons, array_search('returnToInvoice', $applicable_addons), 1);
                    }
                    if ($vehicle_age >= 6) {
                        array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                    }
                    if ($rsa_addon_age == false) {
                        array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                    }
                    if ($consumable_age == false) {
                        array_splice($applicable_addons, array_search('consumables', $applicable_addons), 1);
                    }

                    $return_data = [
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'status' => true,
                        'msg' => 'Found',
                        'Data' => [
                            'idv' => in_array($premium_type,['third_party','third_party_breakin'])? 0 : $vehicle_idv,
                            'min_idv' => in_array($premium_type,['third_party','third_party_breakin']) ? 0 : ($min_idv),
                            'max_idv' => in_array($premium_type,['third_party','third_party_breakin']) ? 0 : ($max_idv),
                            'default_idv' => in_array($premium_type,['third_party','third_party_breakin']) ? 0 :$default_idv,
                            'vehicle_idv' => $default_idv,
                            'qdata' => null,
                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                            'addonCover' => null,
                            'addon_cover_data_get' => '',
                            'rto_decline' => null,
                            'rto_decline_number' => null,
                            'mmv_decline' => null,
                            'mmv_decline_name' => null,
                            'policy_type' => $premium_type == 'third_party' ? 'Third Party' : ($premium_type == 'own_damage' ? 'Own Damage' : 'Comprehensive'),
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => '',
                            'vehicle_registration_no' => $requestData->rto_code,
                            'voluntary_excess' => $voluntary_excess,
                            'version_id' => $mmv_data->ic_version_code,
                            'selected_addon' => [],
                            'showroom_price' => $vehicle_idv,
                            'fuel_type' => $mmv_data->fuel,
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                            'product_name' => $productData->product_name,
                            'mmv_detail' => [
                                'manf_name'             => $mmv_data->vehicle_manufacturer,
                                'model_name'            => $mmv_data->vehicle_model_name,
                                'version_name'          => '',//$mmv_data->variant,
                                'fuel_type'             => $mmv_data->fuel,
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
                            'basic_premium' => $premium_type != 'third_party' ? $od : 0,
                            'deduction_of_ncb' => $premium_type != 'third_party' ? $ncb_discount : 0,
                            'tppd_premium_amount' => $tppd ,
                            'tppd_discount' => $tppd_discount,
                            'underwriting_loading_amount'=> $premium_type == 'third_party' ? 0 :$underwriting_loading_amt,
                            'motor_electric_accessories_value' => $electrical_accessories_amt,
                            'motor_non_electric_accessories_value' => $non_electrical_accessories_amt,
                            'motor_lpg_cng_kit_value' => $cng_lpg,
                            'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                            'multi_year_cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium*5 : 0,
                            'seating_capacity' => $mmv_data->seating_capacity,
                            'default_paid_driver' => $llpaiddriver_premium,
                            'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                            'multi_year_motor_additional_paid_driver' => $cover_pa_paid_driver_premium*5,
                            'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                            'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
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
                            'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : $requestData->business_type,
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
                    if($requestData->business_type == 'newbusiness' && $cpa_tenure  == 5)
                        {
                            // unset($return_data['Data']['compulsory_pa_own_driver']);
                            $return_data['Data']['multi_Year_Cpa'] =  $cover_pa_owner_driver_premium;
                        }
                    }
                    if (!empty($legal_liability_to_employee)) {
                        $return_data['Data']['other_covers'] = [
                            'LegalLiabilityToEmployee' => ($legal_liability_to_employee)
                        ];
                        $return_data['Data']['LegalLiabilityToEmployee'] = ($legal_liability_to_employee);
                    }
                    return camelCase($return_data);
                }
            }else {
                $message = 'Insurer not reachable';
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
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'premium' => '0',
                    'message' =>  $message//$premium_response['messages'][0]['message'] ?? ($premium_response['ValidateResult']['message'] ?? 'Insurer not reachable')
                ];
            }
        } 
    }else {
            $message = 'Insurer not reachable';
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
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'premium' => '0',
                'message' => $message//$premium_response['messages'][0]['message'] ?? ($premium_response['ValidateResult']['message'] ?? 'Insurer not reachable')
            ];
        }
  } else {
        return [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'status' => false,
            'premium' => '0',
            'message' => 'Token Generation Issue'
        ];
    }
}