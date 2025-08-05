<?php

use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\UserProposal;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    $is_MISC = policyProductType($productData->policy_id)->parent_id;
    
    if($is_MISC == 3){
        
        include_once app_path() . '/Quotes/Cv/sbi_miscd.php';

        return getQuoteMiscd($enquiryId, $requestData, $productData);
    }
    // try {

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
        # constant for SBI
        $TOKEN_URL = config('constants.IcConstants.sbi.CV_TOKEN_URL');
        $X_IBM_Client_Id = config('constants.IcConstants.sbi.CV_X_IBM_Client_Id');
        $X_IBM_Client_Secret = config('constants.IcConstants.sbi.X_IBM_Client_Secret');
        $X_IBM_Client_Id_Pcv = config('constants.IcConstants.sbi.X_IBM_Client_Id_PCV');#pcv 
        $X_IBM_Client_Secret_Pcv = config('constants.IcConstants.sbi.X_IBM_Client_Secret_PCV');#pcv
        $END_POINT_URL_CV_QUICK_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_CV_QUICK_QUOTE');
        $END_POINT_URL_CV_FULL_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_CV_FULL_QUOTE');
        $END_POINT_URL_GCV_FULL_QUOTE = config('constants.IcConstants.sbi.END_POINT_URL_GCV_FULL_QUOTE');


        $master_product_sub_type_code = MasterPolicy::find($productData->policy_id)->product_sub_type_code->product_sub_type_code;

        if ($master_product_sub_type_code == 'PICK UP/DELIVERY/REFRIGERATED VAN' || $master_product_sub_type_code == 'DUMPER/TIPPER' ||$master_product_sub_type_code == 'TRUCK' ||$master_product_sub_type_code == 'TRACTOR' ||$master_product_sub_type_code == 'TANKER/BULKER') {
            $type = 'GCV';
        }elseif ($master_product_sub_type_code === 'TAXI' || $master_product_sub_type_code == 'ELECTRIC-RICKSHAW' || $master_product_sub_type_code == 'AUTO-RICKSHAW') {
            $type = 'PCV';
        }else{
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Premium is not available for this product',
                'request' => [
                    'message' => 'Premium is not available for this product',
                    'master_product_sub_type_code' => $master_product_sub_type_code
                ]
            ];
        }


        $mmv = get_mmv_details($productData, $requestData->version_id, 'sbi');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            #throw new \Exception($mmv['message']);
            return  [
                'status' => false,
                'premium_amount' => 0,
                'message' => $mmv['message']
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            #throw new \Exception("Vehicle Not Mapped");
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Vehicle Not Mapped'
            ];
        } elseif ($mmv_data->ic_version_code == 'DNE') {
            #throw new \Exception("Vehicle code does not exist with Insurance company");
            return [
                'status' => false,
                'premium_amount' => 0,
                'message' => 'Vehicle code does not exist with Insurance company'
            ];
        }

        $get_response = cache()->remember('constants.IcConstants.sbi.CV_TOKEN_URL', 60 * 2.5, function () use ($TOKEN_URL, $productData, $enquiryId, $type, $X_IBM_Client_Id_Pcv, $X_IBM_Client_Id, $X_IBM_Client_Secret_Pcv, $X_IBM_Client_Secret) {
            return getWsData($TOKEN_URL, '', 'sbi', [
                'requestMethod' => 'get',
                'method' => 'Token Generation',
                'section' => $productData->product_sub_type_code,
                'enquiryId' => $enquiryId,
                'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' - Zero Dep' : ''),
                'transaction_type' => 'quote',
                'client_id' => $type == 'PCV' ? $X_IBM_Client_Id_Pcv : $X_IBM_Client_Id,
                'client_secret' => $type == 'PCV' ? $X_IBM_Client_Secret_Pcv : $X_IBM_Client_Secret
            ]);
        });

        $token = $get_response['response'];
        if ($token) {
            $token_response = json_decode($token, true);
            if ($token_response) {
                $access_token = $token_response['access_token'];
            } else {
                throw new \Exception("Insurer not reachable");
            }
        } else {
            throw new \Exception("Insurer not reachable");
        }


        $premium_type = DB::table('master_premium_type')->where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

        if ($requestData->business_type == 'breakin' || $requestData->business_type == 'newbusiness') {
            $policy_start_date = date('Y-m-d');
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $expiry_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        } elseif ($requestData->business_type == 'rollover') {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        $kind_of_policy = '1';

        if ($requestData->business_type == 'newbusiness') {
            $business_type = 1;
            $previous_policy_expiry_date = '1900-01-01';
            $previous_policy_start_date = '1900-01-01';
            $kind_of_policy = '1';
        } else {
            $business_type = 2;
            $previous_policy_expiry_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            $previous_policy_start_date = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $kind_of_policy = ($premium_type == 'third_party') ? '2' : (($premium_type == 'own_damage') ? '3' :  '1');
        }

        /* $city_name = DB::table('master_rto')->where('rto_number', $requestData->rto_code)->get();

        $rto_data = [];

        if ($city_name->isEmpty()) {
            throw new \Exception("RTO not available");
        } else {
            foreach ($city_name as $city) {
                $rto_data = DB::table('sbi_rto_location')
                    ->where('rto_location_code', 'like', $city->rto_number)
                    ->first();

                if (!empty($rto_data)) {
                    break;
                }
            }

            if (empty($rto_data)) {
                throw new \Exception("RTO not available");
            }
        } */

       /*  if ($premium_type == 'comprehensive') {
            if ($requestData->business_type == 'newbusiness') {
                $policy_type = 1;
            } else {
                $policy_type = 2;
            }
        } elseif ($premium_type == 'third_party') {
            $policy_type = '2';
        } */
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
        }
        $rto_payload = DB::table('sbi_rto_location')
                    ->where('rto_location_code', $requestData->rto_code)
                    ->first();

        $rto_dic_id = DB::table('pcv_t_rto_location_cmv')->where('LOC_NAME', $rto_payload->rto_location_description)
                        ->where('ID', $rto_payload->id)->first();
        $rto_dic_id = keysToLower($rto_dic_id);

        $rto_payload->rto_dis_code = $rto_dic_id->rto_dis_code;
        $rto_payload->LOC_ID = $rto_dic_id->loc_id;


        if ($premium_type == 'comprehensive') {
            $policy_type = 1;
        } else {
            $policy_type = 2; #SBI doesn't offer tp for PCCV
        }

        $vehicle_age = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $electrical_accessories = false;
        $electrical_accessories_amount = 0;
        $non_electrical_accessories = false;
        $non_electrical_accessories_amount = 0;
        $external_fuel_kit = false;
        $fuel_type = 2; // $mmv_data->fuel_type;
        $external_fuel_kit_amount = 0;
        $inbuilt_fuel_kit = false;
        if($mmv_data->fuel_type == 'CNG (Inbuilt)')
        {
            $inbuilt_fuel_kit = true;
        }
        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 50000) {
                        throw new \Exception("Vehicle non-electric accessories value should be between 10000 to 50000");
                    } else {
                        $external_fuel_kit = true;
                        $fuel_type = 'CNG';
                        $external_fuel_kit_amount = $data['sumInsured'];
                    }
                }

                if ($data['name'] == 'Non-Electrical Accessories') {
                    if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                        throw new \Exception("Vehicle non-electric accessories value should be between 10000 to 25000");
                    } else {
                        $non_electrical_accessories = true;
                        $non_electrical_accessories_amount = $data['sumInsured'];
                    }
                }

                if ($data['name'] == 'Electrical Accessories') {
                    if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                        throw new \Exception("Vehicle electric accessories value should be between 10000 to 25000");
                    } else {
                        $electrical_accessories = true;
                        $electrical_accessories_amount = $data['sumInsured'];
                    }
                }
            }
        }

        $is_anti_theft = 'No';
        $is_anti_theft_device_certified_by_arai = 'false';
        $is_voluntary_access = false;
        $voluntary_excess_amt = 0;
        $tppd_status = false;

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'anti-theft device') {
                    $is_anti_theft = 'Yes';
                    $is_anti_theft_device_certified_by_arai = 'true';
                }

                if ($data['name'] == 'TPPD Cover') {
                    $tppd_status = true;
                }

                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                    $is_voluntary_access = true;
                    $voluntary_excess_amt = $data['sumInsured'];
                }
            }
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = $pa_ccc = false;
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $no_of_pa_unnamed_passenger = 1;
        $no_of_driver = 0;
        $no_of_CCC = 0;
        $pa_sumInsured = 0;
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
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) {
                    $cover_pa_unnamed_passenger = true;
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                    $no_of_pa_unnamed_passenger = $mmv_data->seat_capacity;
                }

                if ($data['name'] == 'LL paid driver') {
                    $cover_ll_paid_driver = true;
                }

                if($data['name'] == 'LL paid driver/conductor/cleaner')
                {
                    $cover_ll_paid_driver = true;
                    $no_of_driver = isset($data['LLNumberDriver']) ? $data['LLNumberDriver'] : 0;
                    $no_of_cleaner = isset($data['LLNumberCleaner']) ? $data['LLNumberCleaner'] : 0;
                    $no_of_conductor = isset($data['LLNumberConductor']) ? $data['LLNumberConductor'] : 0;
                    $no_of_CCC = $no_of_cleaner + $no_of_conductor;
                }
                #PA paid driver/conductor/cleaner not applicable in case of company @hrishi 06-02-2023
                if($data['name'] == 'PA paid driver/conductor/cleaner' &&  $requestData->vehicle_owner_type == 'I')
                {
                    $pa_ccc = true;
                    $pa_sumInsured = isset($data['sumInsured']) ? (int) $data['sumInsured'] : 0;
                    if ($no_of_driver) {
                        $no_of_driver = $no_of_driver;#removed addition of driver as per confirmation from ic
                    }else {
                        $no_of_driver = 1;
                    }
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

        $reg_payload = $requestData->rto_code;

        if ($type == 'GCV') {
            $no_of_wheels = $mmv['WHEELS'] ?? $mmv_data->wheels ?? '';
            if ($no_of_wheels == 3 && $requestData->gcv_carrier_type == 'PUBLIC') {
                $gcv_vehicle_sub_class = 17;
            } elseif ($no_of_wheels == 3) {
                $gcv_vehicle_sub_class = 18;
            } elseif ($requestData->gcv_carrier_type == 'PUBLIC') {
                $gcv_vehicle_sub_class = 19;
            } else {
                $gcv_vehicle_sub_class = 20;
            }
        }else {
            $gcv_vehicle_sub_class = '';
        }

        $engine_number = '123456';
        $chassis_number = '8765432345678';
        $registration_no = $requestData->rto_code;

        if (!empty($requestData->vehicle_registration_no) && strtoupper($requestData->vehicle_registration_no) != 'NEW') {
            $registration_no = str_replace('-', '', $requestData->vehicle_registration_no);

            $reg_payload = getRegisterNumberWithHyphen($registration_no);

            $proposal = UserProposal::select('chassis_number', 'engine_number')
            ->where('user_product_journey_id', $enquiryId)->first();

            if (!empty($proposal->chassis_number)) {
                $engine_number = $proposal->engine_number ?? $engine_number;
                $chassis_number = $proposal->chassis_number;
            }
        } else {
            if ($requestData->business_type != 'newbusiness') {
                $registration_no = $type == 'GCV' ? config('GCV_QUOTE_REGISTRATION_NUMBER') : config('PCV_QUOTE_REGISTRATION_NUMBER');
                $registration_no = str_replace('-', '', $registration_no);

                $reg_payload = getRegisterNumberWithHyphen($registration_no);

                $engine_number = $type == 'GCV' ? config('GCV_QUOTE_ENGINE_NUMBER') : config('PCV_QUOTE_ENGINE_NUMBER');
                $chassis_number = $type == 'GCV' ? config('GCV_QUOTE_CHASSIS_NUMBER') : config('PCV_QUOTE_CHASSIS_NUMBER');
            }
        }

        $reg_payload = explode('-', $reg_payload);

        $fuel_type_code = [
            'Petrol'                => 1,
            'Diesel'                => 2,
            'CNG (Inbuilt)'         => 3,
            'CNG'                   => 3, //handle as per #31806
            'LPG (Inbuilt)'         => 4,
            'Hybrid'                => 5,
            'Battery'               => 7,
            'CNG (External Kit)'    => 8,
            'LPG (External Kit)'    => 9
        ];

        $FuelType = $fuel_type_code[$mmv_data->fuel_type];

        $premium_calculation_request = [
            'RequestHeader' => [
                'requestID' => mt_rand(100000, 999999),
                'action' => 'fullQuote',
                'channel' => 'SBIG',
                'transactionTimestamp' => date('d-M-Y-H:i:s')
            ],
            'RequestBody' => [
                // 'AdditonalCompDeductible' => '',
                /* 'AgentType' => 'POSP',
                'AgentFirstName' => '',
                'AgentMiddleName' => '',
                'AgentLastName' => '',
                'AgentPAN' => '',
                'AgentMobile' => '',
                'AgentBranchID' => '',
                'AgentBranch' => '', */
                'AgreementCode' => config('constants.IcConstants.sbi.GCV_AGREEMENT_CODE'), //'6660', //'0006660',
                'BusinessSubType' => '5',
                'BusinessType' => $business_type,
                'giChannelType' => '3',
                // 'AlternatePolicyNo' => 'AlternatePolicyNo',
                // 'CoInsurancePer' => '',
                // 'CustomerGSTINNo' => '22AAAAA00001Z5',
                // 'CustomerSegment' => 'CustomerSegment',
                'EffectiveDate' => $requestData->business_type == 'newbusiness' ? $policy_start_date.'T'.date('H:i:s') : $policy_start_date . 'T00:00:00',
                'ExpiryDate' => $policy_end_date . 'T23:59:59',
                // 'UWDiscount' => 0,
                // 'UWLoading' => 0,
                'GSTType' => 'IGST',
                'HasPrePolicy' => $requestData->business_type == 'newbusiness' ? '0' : '1',
                // 'InspectionDoneBy' => '1',
                // "IssuingBranchCode" => "IssuingBranchCode",
                // 'IssuingBranchGSTN' => '22AAAAA00001Z0',
                'KindofPolicy' => $kind_of_policy,
                "LossNature" => "1",
                'NewBizClassification' => (string) $business_type,
                'PolicyCustomerList' => [
                    [
                        // 'AadharNumUHID' => 'AadharNumUHID',
                        'BuildingHouseName' => 'BuildingHouseName',
                        'City' => 'City',
                        'Email' => 'developer@fyntune.com',
                        'ContactEmail' => 'developer@fyntune.com',
                        'ContactPersonTel' => '9876544567',
                        'CountryCode' => '1000000108',
                        "FirstName" => "Amit",
                        "MiddleName" => "Kumar",
                        "LastName" => "Gupta",
                        'CustomerName' => 'Ms test test',
                        'DateOfBirth' => $requestData->vehicle_owner_type != 'I' ? '' : '1988-04-19',
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
                        'PartyStatus' => '1',
                        'PostCode' => '388215',
                        'PostCode' => '388215',
                        'PreferredContactMode' => 'EMAIL',
                        'SBIGCustomerId' => 'SBIGCustomerId',
                        'STDCode' => '021',
                        'State' => $rto_payload->id,
                        'StreetName' => 'StreetName',
                        'Title' => '9000000001',
                        'EducationCode' => '2300000003',
                        'GroupCompanyName' => 'SBIG',
                        'GroupEmployeeId' => 'GroupEmployeeId',
                        'CampaignCode' => 'CampaignCode',
                        'ISDNum' => 'ISDNum',
                        'MaritalStatus' => '1',
                        'CKYCUniqueId' => time(),
                        //Regulatory changes *DUMMY DATA for quote page*
                        "AccountNo" => "124301506743",
                        "IFSCCode"  => "ICIC0001230",
                        "BankName"  => "ICICI Bank",
                        "BankBranch"=> "Parel",
                    ],
                ],
                'PolicyLobList' => [
                    [
                        'PolicyRiskList' => [
                            [
                                // 'PCVVehicleSubClass' => '1',
                                // 'IsForeignEmbassyVeh' => 0,
                                // 'ProposedUsage' => '1',
                                // 'MonthlyUseDistance' => '1',
                                // 'VehicleCategory' => '1',
                                // 'DayParkLoc' => '1',
                                // 'DayParkLocPinCode' => '388215',
                                'NightParkLoc' => 1,
                                'NightParkLocPinCode' => '388215',
                                # [Todo] : Need to Discuss
                                /* 'Last3YrDriverClaimPresent' => 0,
                                'NoOfDriverClaims' => 1,
                                'DriverClaimAmt' => '10000',
                                'ClaimStatus' => 'false',
                                'ClaimType' => '1', */
                                'CountCCC' => $type == 'GCV' ? $no_of_CCC : 0,
                                "IMT34" => 0, # Commercial Vehicle for Private Usage
                                // "SiteAddress" => "SiteAddress", #WIP -->
                                "IMT23" => $type == 'GCV' ? 1 : 0,
                                "IMT43" => 0,
                                "IMT44" => 0,
                                "AdditionalTowingCharges" => 0,
                                'AntiTheftAlarmSystem' => 0,
                                'BodyType' => 'Sedan',
                                'ChassisNo' => removeSpecialCharactersFromString($chassis_number),
                                'NFPPCount' => 0,
                                'EmployeeCount' => 0,
                                'EngineNo' => $engine_number,
                                # [Todo] : Financier Detail add on proposal
                                /* 'FNBranchName' => 'FNBranchName',
                                'FNName' => 'FNName',
                                'FNType' => '1', */
                                'FuelType' => $FuelType,//$external_fuel_kit ? '8':(($inbuilt_fuel_kit && ($mmv_data->fuel_type == 'CNG (Inbuilt)')) ? '3' : $mmv_data->fuel),
                                'IsGeographicalExtension' => $is_geo_code,
                                'GeoExtnBangladesh' => $bang,
                                'GeoExtnBhutan' => $bhutan,
                                'GeoExtnMaldives' => $maldive,
                                'GeoExtnNepal' => $nepal,
                                'GeoExtnPakistan' => $pak,
                                'GeoExtnSriLanka' => $srilanka,
                                // 'VehicleBodyPrice' => 10000,
                                'IsConfinedOwnPremises' => 0,
                                'IsDrivingTuitionsUse' => 0,
                                'IsFGFT' => 0,
                                'IsNCB' => 0, //Made changes according to git #30395 $requestData->business_type == 'newbusiness' ? '0' : ($requestData->is_claim == 'Y' && $requestData->applicable_ncb == 0 ? 1 : 0),
                                'IsNewVehicle' => $requestData->business_type == 'newbusiness' ? 1 : 0,
                                'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)), 
                                'NCB' => $requestData->applicable_ncb / 100,
                                'NCBLetterDate' => ($type == 'GCV' && $requestData->applicable_ncb == 0) ? "" : '2021-09-09T00:00:00.000',
                                'NCBPrePolicy' => $requestData->previous_ncb / 100,
                                // 'NCBProof' => '1', [Todo] : Need to Discuss
                                'PaidDriverCount' => $type == 'GCV' ? $no_of_driver :1,
                                'ProductElementCode' => 'R10005',
                                'RTOCityDistric' => $rto_payload->rto_dis_code, //'DIS_002', //$rto_data->rto_dis_code,
                                'RTOCluster' => $rto_payload->id,
                                'RTOLocation' => $rto_payload->rto_location_description, // 'Port Blair', // $rto_data->rto_location_code,
                                'RTONameCode' => $rto_payload->id,
                                'RTORegion' => $rto_payload->rto_region,
                                'RTOLocationID' => $rto_dic_id->loc_id,
                                'RegistrationDate' => ($type == 'GCV') ? date('Y-m-d', strtotime($requestData->vehicle_register_date)) : date('Y-m-d', strtotime($requestData->vehicle_register_date)) . 'T00:00:00',
                                'RegistrationNo' => ($type == 'GCV' && $requestData->business_type == 'newbusiness') ? "" : $registration_no,
                                "RegistrationNoBlk1" => $reg_payload[0] ?? "MH",
                                "RegistrationNoBlk2" => $reg_payload[1] ?? "01",
                                "RegistrationNoBlk3" => ($type == 'GCV' && $requestData->business_type == 'newbusiness') ? "" : ($reg_payload[2] ?? "AB"),
                                "RegistrationNoBlk4" => ($type == 'GCV' && $requestData->business_type == 'newbusiness') ? "" : ($reg_payload[3] ?? "1111"),
                                'TrailerCount' => 0,
                                // 'TrailerType' => '',
                                'Variant' => $type == 'GCV' ? $mmv_data->va_id : $mmv_data->var_id, //$mmv_data->variant_id,
                                'VehicleMake' => $mmv_data->mk_id, //$mmv_data->vehicle_manufacturer_code,
                                'VehicleModel' =>  $mmv_data->mod_id, // $mmv_data->vehicle_model_code,
                                'PolicyCoverageList' => [],
                            ],
                        ],
                        'ProductCode' => $type == 'GCV' ? 'CMVGC01' : 'CMVPC01',
                        'PolicyType' => $policy_type,
                    ],
                ],
                /* 'PreInsuranceComAddr' => $requestData->business_type == 'newbusiness' ? '' : 'Mumbai',
                'PreInsuranceComName' => $requestData->business_type == 'newbusiness' ? '' : '15',
                'PrePolicyEndDate' => $requestData->business_type == 'newbusiness' ? '' : $previous_policy_expiry_date . 'T23:59:59',
                'PrePolicyNo' => $requestData->business_type == 'newbusiness' ? '' : '87654345678',
                'PrePolicyStartDate' => $requestData->business_type == 'newbusiness' ? '' : $previous_policy_start_date . 'T00:00:00', */
                'PremiumCurrencyCode' => 'INR',
                'PremiumLocalExchangeRate' => 1,
                'ProductCode' =>  $type == 'GCV' ? 'CMVGC01' : 'CMVPC01',
                'ProductVersion' => '1.0',
                'ProposalDate' => $policy_start_date,
                'QuoteValidTo' => date('Y-m-d', strtotime('+30 day', strtotime($policy_start_date))),
                'SBIGBranchStateCode' => $rto_payload->id,
                'SBIGBranchCode' => 'SBIGBranchCode',
                'SiCurrencyCode' => 'INR',
                "SimFlowId" => "SimFlowId",
                'TransactionInitiatedBy' => config('constants.IcConstants.sbi.SBI_TRANSACTION_INITIATED_BY')
            ]
        ];
        if (config('constants.IcConstants.sbi.SBI_SOURCE_TYPE_ENABLE') == 'Y') {
            $premium_calculation_request['RequestBody']['SourceType'] = config('constants.IcConstants.sbi.SBI_CV_SOURCE_TYPE', '9');
        }
        if($requestData->vehicle_owner_type != 'I'){
            unset($premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['DateOfBirth']);
            unset($premium_calculation_request['RequestBody']['PolicyCustomerList'][0]['GenderCode']);
        }
        
        #previous policy details in new business case
        if ($requestData->business_type != 'newbusiness') {
            $premium_calculation_request['RequestBody']['PreInsuranceComAddr'] ='PreInsuranceComAddr';
            $premium_calculation_request['RequestBody']['PreInsuranceComName'] ='15';
            $premium_calculation_request['RequestBody']['PrePolicyNo'] ='PrePolicyNo';
            $premium_calculation_request['RequestBody']['PrePolicyEndDate'] =$previous_policy_expiry_date . 'T23:59:59';
            $premium_calculation_request['RequestBody']['PrePolicyStartDate'] =$previous_policy_start_date . 'T00:00:00';
        }

        if(($requestData->is_claim == 'N'))
        {
            unset($premium_calculation_request['RequestBody']['LossNature']);
        }

        #pos details
        $is_pos = false;
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_SBI_TESTING');
        $posp_name = '';
        $posp_unique_number = '';
        $posp_pan_number = '';

        /* $pos_data = DB::table('cv_agent_mappings')
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
        }else if($is_pos_enabled_testing == 'Y')
        {
            $is_pos = true;
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = '569278616999';
        } */

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


        if ($type == 'GCV') {
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['GCVVehicleSubClass'] = $gcv_vehicle_sub_class;
        }else {
            $no_of_wheels = $mmv['NO_OF_WHEELS'] ?? $mmv_data->no_of_wheels ?? 0;
            $carry_capacity = $mmv_data->carry_capacity ?? 0;
            $PCVVehicleSubClass = '';
            if ($no_of_wheels == 4) {
                if ($carry_capacity <= 6) {
                    $PCVVehicleSubClass = '1';
                } else if ($carry_capacity > 6) {
                    $PCVVehicleSubClass = '3';
                }
            } else if ($no_of_wheels == 3) {
                if ($carry_capacity <= 6) {
                    $PCVVehicleSubClass = '2';
                } else if ($carry_capacity > 17) {
                    $PCVVehicleSubClass = '3';
                } else if ($carry_capacity > 6 && $carry_capacity <= 17) {
                    $PCVVehicleSubClass = '5';
                }
            }
            if (empty($PCVVehicleSubClass)) {
                return [
                    'status' => false,
                    'message' => 'No. of wheels / Carrying Capacity for this vehicle is not defined.',
                    'request' => [
                        'wheels' => $no_of_wheels,
                        'carrying_capacity' => $carry_capacity
                    ]
                ];
            }
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PCVVehicleSubClass'] = $PCVVehicleSubClass;
        }

        #OD Details
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
        }

        # for TP -
        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
            'ProductElementCode' => 'C101065',
            'EffectiveDate' => $policy_start_date . 'T00:00:00',
            'PolicyBenefitList' => [
                [
                    'ProductElementCode' => 'B00008',
                ]
            ],
            'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
        ];

        # for ZD Addons
        if ($productData->product_identifier == 'zero_dep_sbi') {
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'ProductElementCode' => 'C101072',
                'EffectiveDate' => $policy_start_date . 'T00:00:00',
                'ExpiryDate' => ($policy_type == '6') ? $expiry_date . 'T23:59:59' : $policy_end_date . 'T23:59:59',
            ];
        }

        if ($cover_pa_paid_driver || $requestData->vehicle_owner_type == 'I') {
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                'EffectiveDate' => $policy_start_date . 'T00:00:00',
                'PolicyBenefitList' => [
                    [
                        'SumInsured' => 1500000,
                        'ProductElementCode' => 'B00015',
                        'NomineeName' => 'Sai',
                        'NomineeRelToProposer' => '1',
                        'NomineeDOB' => '1988-04-19',
                        'NomineeAge' => '29',
                        'AppointeeName' => 'AppointeeName',
                        'AppointeeRelToNominee' => '2',
                    ]
                ],
                'OwnerHavingValidDrivingLicence' => '1',
                'OwnerHavingValidPACover' => '1',
                'ExpiryDate' =>($requestData->business_type == 'newbusiness') ? $expiry_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
                'ProductElementCode' => 'C101066',
            ];
        }

         #PA Owner driver/conductor/cleaner
         if ($pa_ccc && $requestData->vehicle_owner_type == 'I') {

            $index_id = search_for_id_sbi('C101066', $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];

            if (isset($index_id) === true) {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                    'Description' => 'Description',
                    'SumInsuredPerUnit' => $pa_sumInsured,
                    'TotalSumInsured' => $pa_sumInsured,
                    'ProductElementCode' => 'B00073'
                ];
            }else {
                $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][] = [
                    'EffectiveDate' => $policy_start_date . 'T00:00:00',
                    'PolicyBenefitList' => [
                        [
                            'Description' => 'Description',
                            'SumInsuredPerUnit' => $pa_sumInsured,
                            'TotalSumInsured' => $pa_sumInsured,
                            'ProductElementCode' => 'B00073'
                        ]
                    ],
                    'ExpiryDate' =>($requestData->business_type == 'newbusiness') ? $expiry_date.'T23:59:59' : $policy_end_date . 'T23:59:59',
                    'ProductElementCode' => 'C101066',
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
                if ($inbuilt_fuel_kit) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['PolicyBenefitList'][] = [
                        'ProductElementCode' => 'B00006'
                    ];
                }
            }

            if($add['ProductElementCode'] == 'C101065')
            {
                $index_id = search_for_id_sbi('C101065', $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'])[0];

                // dd($premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][]);
                if ($external_fuel_kit || $inbuilt_fuel_kit) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                        'SumInsured' => $external_fuel_kit_amount,
                        'Description' => 'Description',
                        'ProductElementCode' => 'B00010'
                    ];
                }

                if($cover_ll_paid_driver)
                {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                        'Description' => 'Description',
                        'ProductElementCode' => 'B00013'
                    ];

                    if ($type == 'GCV' && $no_of_driver == 0) {
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                            'Description' => 'Description',
                            'ProductElementCode' => 'B00069'
                        ];
                    }

                }

                if($tppd_status)
                {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index_id]['PolicyBenefitList'][] = [
                        'Description' => 'Description',
                        'SumInsured' => 6000,
                        'ProductElementCode' => 'B00009'
                    ];
                }
            }

        }
        
        if($premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == '0' || $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IsNCB'] == 0){
            $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBLetterDate'] = '';
        }

        if ($type == 'GCV') {
            $get_response = getWsData(
               $END_POINT_URL_GCV_FULL_QUOTE,
               $premium_calculation_request,
               'sbi',
               [
                   'enquiryId' => $enquiryId,
                   'requestMethod' => 'post',
                   'authorization' => $access_token,
                   'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' - Zero Dep' : ''),
                   'company'  => 'sbi',
                   'section' => $productData->product_sub_type_code,
                   'method' => 'Premium Calculation',
                   'transaction_type' => 'quote',
                   'client_id' => $X_IBM_Client_Id,
                   'client_secret' => $X_IBM_Client_Secret
               ]
           );
           $data = $get_response['response'];
        }else{
            $get_response = getWsData(
                $END_POINT_URL_CV_FULL_QUOTE,
                $premium_calculation_request,
                'sbi',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'authorization' => $access_token,
                    'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' - Zero Dep' : ''),
                    'company'  => 'sbi',
                    'section' => $productData->product_sub_type_code,
                    'method' => 'Premium Calculation',
                    'transaction_type' => 'quote',
                    'client_id' => $X_IBM_Client_Id_Pcv,
                    'client_secret' => $X_IBM_Client_Secret_Pcv
                ]
            );
            $data = $get_response['response'];
        }

        if ($data) {
            $premium_response = json_decode($data, TRUE);
            if (isset($premium_response['message']) || isset($premium_response['messages'][0]['message']) || (isset($premium_response['ValidateResult']['message'])) || isset($premium_response['UnderwritingResult']['MessageList'])) {
                update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], ($premium_response['message'] ?? $premium_response['ValidateResult']['message'] ?? $premium_response['messages'][0]['message'] ??  $premium_response['UnderwritingResult']['MessageList'][0]['Message'] ?? 'Server Error'), "Failed");
            } else {
                update_quote_web_servicerequestresponse($get_response['table'], $get_response['webservice_id'], "Quotation converted to proposal successfully", "Success");
            }
            if(isset($premium_response['messages'][0]['message']) || (isset($premium_response['ValidateResult']['message'])))
            {
                if (isset($premium_response['messages'][0]['message'])) {
                    $error_message = json_encode($premium_response['messages'][0]['message'], true);
                }
                else if (isset($premium_response['ValidateResult']['message']))
                {
                    $error_message = json_encode($premium_response['ValidateResult']['message'], true);
                }
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => isset($error_message) ? $error_message : 'Server Error'
                ];
            }else if(isset($premium_response['UnderwritingResult']['MessageList']))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => isset($premium_response['UnderwritingResult']['MessageList'][0]['Message']) ? $premium_response['UnderwritingResult']['MessageList'][0]['Message'] : 'Server Error'
                ];
            }else if(isset($premium_response['message']))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $premium_response['message'] ?? 'Server Error'
                ];
            }
            $idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            $min_idv = ceil(($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MinIDV_Suggested']));
            $max_idv = floor(($premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['MaxIDV_Suggested']));
            $vehicle_idv = $default_idv = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            $idvsuggested = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_Suggested'];
            $skip_second_call = false;
            if($idv >= 5000000)
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Quotes cannot be displayed as Vehicle IDV  selected is greater or equal to 50 lakh'
                ];
            }

            if ($requestData->is_idv_changed == 'Y') {
                if ($requestData->edit_idv >= $max_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                    $vehicle_idv = $default_idv = $max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                    $vehicle_idv = $default_idv = $min_idv;
                } else {
                    $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $requestData->edit_idv;
                    $vehicle_idv = $default_idv = $requestData->edit_idv;
                }
            } else {
                /* $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                $vehicle_idv = $default_idv =  $min_idv; */
                $getIdvSetting = getCommonConfig('idv_settings');
                switch ($getIdvSetting) {
                    case 'default':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $vehicle_idv;
                        $skip_second_call = true;
                        $vehicle_idv = $default_idv =  $vehicle_idv;
                        break;
                    case 'min_idv':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                        $vehicle_idv = $default_idv =  $min_idv;
                        break;
                    case 'max_idv':
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $max_idv;
                        $vehicle_idv = $default_idv =  $max_idv;
                        break;
                    default:
                        $premium_calculation_request['RequestBody']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'] = $min_idv;
                        $vehicle_idv = $default_idv =  $min_idv;
                        break;
                }
            }
       if(!$skip_second_call){
            if ($type == 'GCV') {
                $get_response = getWsData(
                    $END_POINT_URL_GCV_FULL_QUOTE,
                    $premium_calculation_request,
                    'sbi',
                    [
                        'enquiryId' => $enquiryId,
                        'requestMethod' => 'post',
                        'authorization' => $access_token,
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' - Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Premium Calculation',
                        'transaction_type' => 'quote',
                        'client_id' => $X_IBM_Client_Id,
                        'client_secret' => $X_IBM_Client_Secret
                    ]
                );
                $data = $get_response['response'];
            }else {
                $get_response = getWsData(
                    $END_POINT_URL_CV_FULL_QUOTE,
                    $premium_calculation_request,
                    'sbi',
                    [
                        'enquiryId' => $enquiryId,
                        'requestMethod' => 'post',
                        'authorization' => $access_token,
                        'productName'   => $productData->product_name . ($productData->zero_dep == '0' ? ' - Zero Dep' : ''),
                        'company'  => 'sbi',
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Premium Calculation',
                        'transaction_type' => 'quote',
                        'client_id' => $X_IBM_Client_Id_Pcv,
                        'client_secret' => $X_IBM_Client_Secret_Pcv
                    ]
                );
                $data = $get_response['response'];
            }
        }

        }

        if ($data) {
            $response = json_decode($data, true);
            $idv = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['IDV_User'];
            if($idv >= 5000000)
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Quotes cannot be displayed as Vehicle IDV  selected is greater or equal to 50 lakh'
                ];
            }
            # NCB Discount
            $ncb_discount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['NCBDiscountAmt'] ?? 0;

            $imtPremium = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['imt23prem'] ?? 0;

            $policyCoverageList = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];

            $basic_od_amount = $non_elect_amount = $elect_amount = $cng_lpg_od_amount = $inbuilt_cng_lpg_amount = 0;
            $basic_tp_amount = $cng_lpg_tp_amount = $llpd_amount = $tppd_amount = $zero_dep_value = $cpa = $pa_ccc_amount = $llcleanerconductor_amount = 0;
            $total_od_resp = 0;
            foreach ($policyCoverageList as $policyCoverageListkey => $policyCoverageListvalue) {

                if ($policyCoverageListvalue['ProductElementCode'] == 'C101064') {

                    $total_od_resp = $policyCoverageListvalue['AnnualPremium'];

                    foreach ($policyCoverageListvalue['PolicyBenefitList'] as $PolicyBenefitListkey => $PolicyBenefitListvalue) {
                        # Basic Own Damage
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00002') {
                            $basic_od_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # Non Electrical Accessories
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00003') {
                            $non_elect_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # Electrical Accessories
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00004') {
                            $elect_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # CNG/LPG kit
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00005') {
                            $cng_lpg_od_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # In CNG/LPG kit
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00006') {
                            $inbuilt_cng_lpg_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                    }
                }

                if ($policyCoverageListvalue['ProductElementCode'] == 'C101065') {

                    $total_tp_resp = $policyCoverageListvalue['AnnualPremium'];

                    foreach ($policyCoverageListvalue['PolicyBenefitList'] as $PolicyBenefitListkey => $PolicyBenefitListvalue) {
                        # Basic TP
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00008') {
                            $basic_tp_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # TPPD Discount
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00009') {
                            $tppd_amount = abs($PolicyBenefitListvalue['AnnualPremium']);
                        }
                        # Basic Tp - CNG/LPG kit
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00010') {
                            $cng_lpg_tp_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # legal liability to paid driver
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00013') {
                            $llpd_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # legal liability to coolie conductor driver
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00069') {
                            $llcleanerconductor_amount = $PolicyBenefitListvalue['AnnualPremium'];      //They are giving premium for Cleaner and Conductor in same ProductElementCode
                        }
                    }
                }

                if($policyCoverageListvalue['ProductElementCode'] == 'C101066')
                {
                    foreach ($policyCoverageListvalue['PolicyBenefitList'] as $PolicyBenefitListkey => $PolicyBenefitListvalue) {
                        # CPA
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00015') {
                            $cpa = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                        # PA CCC
                        if ($PolicyBenefitListvalue['ProductElementCode'] == 'B00073') {
                            $pa_ccc_amount = $PolicyBenefitListvalue['AnnualPremium'];
                        }
                    }
                }

                if($policyCoverageListvalue['ProductElementCode'] == 'C101072')
                {
                    $zero_dep_value = (int) $policyCoverageListvalue['AnnualPremium'];
                }

            }

            $ic_vehicle_discount = 0;
            $voluntary_excess = 0;
            $tppd = 0;
            $anti_theft = 0;
            $GeogExtension_tp = $GeogExtension_od = 0;
            // if($is_geo_ext && !in_array($premium_type,['third_party','third_party_breakin'])){
            //     $GeogExtension_tp = 100;
            //     $GeogExtension_od = 400;
            //     $total_od_resp  += $GeogExtension_od;
            //     // $basic_tp_amount  -= $GeogExtension_tp; // because of this 100 rs diff was there in final premium premium mismatch issue fixed git id 11163
            // }

            //Geographical Extension fix
            if($is_geo_ext && $is_geo_code == 1){
                $GeogExtension_od = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['GeoExtnODPrem'] ?? 0;
                $GeogExtension_tp = $premium_response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['GeoExtnTPPrem'] ?? 0;
                $total_od_resp += $GeogExtension_od;
            }
            $llpaiddriver_premium = $llpd_amount + $llcleanerconductor_amount;
            $cover_pa_owner_driver_premium = $cpa;
            $cover_pa_paid_driver_premium = (int) $pa_ccc_amount;

            // Discounting NCB 15% because of IMT23 [ for GCV Case only]
            if($type == 'GCV')
            {
                $ncb_discount = calculateValithoutImt23($ncb_discount);
            }


            $final_od_premium = $total_od_resp ?? 0;
            // $final_tp_premium = $total_tp_resp;
            $final_tp_premium = $basic_tp_amount + $cng_lpg_tp_amount +  $llpd_amount + $cover_pa_paid_driver_premium + $GeogExtension_tp + $llcleanerconductor_amount;
            $final_total_discount = $ncb_discount + $tppd_amount ?? 0;
            $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
            $final_gst_amount = round($final_net_premium * 0.18);

            $final_payable_amount = isset($response['PolicyObject']['DuePremium']) ? $response['PolicyObject']['DuePremium'] : 0;

            $addons_data = [
                "in_built" => [],
                "additional" => [
                    'zero_depreciation' => $zero_dep_value
                ]
            ];


            $applicable_addons = [
                'zeroDepreciation'
            ];

            if ($type == 'GCV') {
                $addons_data['additional']['imt23'] = (int) $imtPremium;
                array_push($applicable_addons, 'imt23');
            }

            $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
            $vehicle_age_interval = car_age_intervals($vehicleDate, $requestData->previous_policy_expiry_date);

            $vehicle_age_interval = json_decode($vehicle_age_interval);
            #($vehicle_age_interval->y >= 4) || ($vehicle_age_interval->y >= 3 && $vehicle_age_interval->m == 12) && $vehicle_age_interval->d >= 28
            if ($vehicle_age > 5) {
                array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                /* if ($type == 'GCV') {
                    array_splice($applicable_addons, array_search('imt23', $applicable_addons), 1);
                } */
            }

            $business_type = '';
            if ($requestData->business_type == 'rollover') {
                $business_type = 'Rollover';
            }elseif($requestData->business_type == 'breakin'){
                $business_type = 'Break-in';
            }elseif($requestData->business_type == 'newbusiness'){
                $business_type = 'New Business';
            }

            $final_response = [
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
                        'manf_name'             => $mmv_data->make_desc,
                        'model_name'            => $type == 'GCV' ? $mmv_data->model_desc : $mmv_data->mod_desc,
                        'version_name'          => $type == 'GCV' ? $mmv_data->var_desc : $mmv_data->variant,
                        'fuel_type'             => $mmv_data->fuel_type,
                        'seating_capacity'      => $mmv_data->seat_capacity,
                        'carrying_capacity'     => $type == 'GCV' ? $mmv_data->carring_capacity : $mmv_data->carry_capacity,
                        'cubic_capacity'        => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight'  => $type == 'GCV' ? $mmv_data->gross_vechicle_weight : '1',//$mmv_data->gross_weight ?? 1,
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
                    'motor_manf_date' => '', //$motor_manf_date,
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'ic_vehicle_discount' => $ic_vehicle_discount,
                    'basic_premium' => (int) $basic_od_amount,
                    'deduction_of_ncb' => (int) $ncb_discount,
                    'tppd_discount' => (int) $tppd_amount,
                    'tppd_premium_amount' => (int) $basic_tp_amount,
                    'motor_electric_accessories_value' => (int) $elect_amount,
                    'motor_non_electric_accessories_value' => (int) $non_elect_amount,
                    /* 'motor_lpg_cng_kit_value' => ($inbuilt_fuel_kit == true ? (int)$inbuilt_cng_lpg_amount :(int) $cng_lpg_od_amount), */
                    'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                    'seating_capacity' => $mmv_data->seat_capacity,
                    'default_paid_driver' => $llpaiddriver_premium,
                    'll_paid_driver_premium' => $llpd_amount,
                    'll_paid_conductor_premium' => $llcleanerconductor_amount,
                    'll_paid_cleaner_premium' => 0,
                    'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                    'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => $final_od_premium,
                    /* 'cng_lpg_tp' => $cng_lpg_tp_amount, */
                    'total_liability_premium' => $final_tp_premium,
                    'net_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'GeogExtension_ODPremium' => $GeogExtension_od,
                    'GeogExtension_TPPremium' => $GeogExtension_tp,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => $response['PolicyObject']['QuotationNo'],
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
                    'business_type' => $business_type, //$requestData->business_type,
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

            if($external_fuel_kit || $inbuilt_fuel_kit)
            {
                $final_response['Data']['motor_lpg_cng_kit_value'] = ($inbuilt_fuel_kit == true ? (int)$inbuilt_cng_lpg_amount :(int) $cng_lpg_od_amount);
                $final_response['Data']['cng_lpg_tp'] = $cng_lpg_tp_amount;
            }

            return camelCase($final_response);

        } else {
            throw new \Exception("Something Went wrong");
        }
    // } catch (\Exception $e) {
    //     return [
    //         'status' => false,
    //         'webservice_id' => $get_response['webservice_id'],
    //         'table' => $get_response['table'],
    //         'message' => $e->getMessage(),
    //         'line_no' => $e->getLine(),
    //         'file' => pathinfo($e->getFile())['basename']
    //     ];
    // }


}


// utitlity function list
function calculateValithoutImt23($payload)
{
    $result = (int) ($payload - ($payload - $payload * 100/115));
    return $result;
}
