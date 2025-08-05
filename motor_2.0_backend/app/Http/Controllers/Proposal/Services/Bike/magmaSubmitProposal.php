<?php

namespace App\Http\Controllers\Proposal\Services\Bike;

use App\Http\Controllers\SyncPremiumDetail\Bike\MagmaPremiumDetailController;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MagmaBikePriceMaster;
use App\Models\MagmaFinancierMaster;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class magmaSubmitProposal {
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $enquiryId = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $time = '00:00';
        if ($requestData->business_type == 'newbusiness')
        {
            $policy_start_date = date('d/m/Y');
        }
        elseif ($requestData->business_type == 'rollover')
        {
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }
        elseif ($requestData->business_type == 'breakin')
        {
            $policy_start_date = date('d/m/Y', strtotime('+2 day', strtotime(date('Y-m-d'))));
        }

        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');

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
                'message' => $mmv['message']
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
        $rto_code = preg_replace("/OR/", "OD", $rto_code);

        if (str_starts_with(strtoupper($rto_code), "DL-0")) {
            $rto_code = RtoCodeWithOrWithoutZero($rto_code);
        }

        $rto_location = DB::table('magma_rto_location')
            ->where('rto_location_code', $rto_code)
            ->where('vehicle_class_code', '37')
            ->first();

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new \DateTime($vehicleDate);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $carage = floor($age / 12);

        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);
        $break_in = (Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->diffInDays(str_replace('/', '-', $policy_start_date)) > 0) ? 'YES' : 'NO';

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        $is_tp = (($premium_type == 'third_party') || ($premium_type == 'third_party_breakin'));
        // new business
        $PreviousNilDepreciation = '0'; // addon
        if ($requestData->business_type == 'newbusiness')
        {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = date('d/m/Y', time());
            $policy_end_date = date('d/m/Y', strtotime('+5 year -1 day', time()));
            $PolicyProductType = ($is_tp) ? '5TP' : '5TP1OD';
            $previous_ncb = "";
            $businesstype = 'New Business';
            $proposal_date = $policy_start_date;
            $time = date('H:i', time());
        }
        else
        {
            if($requestData->is_multi_year_policy == 'Y')
            {
                if($premium_type == 'own_damage')
                {
                    $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y'); 
                }
                else
                {
                    $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(5)->addDay(1)->format('d/m/Y');                     
                }
            }
            else
            {
               $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y'); 
            }
            
            $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
            $PolicyProductType = (in_array($premium_type,['own_damage','own_damage_breakin']) ? '1OD' :(($is_tp) ? '1TP' : '1TP1OD'));
            //$previous_ncb = $quote_log->quote_details['previous_ncb'];
            $businesstype = 'Roll Over';#$premium_type == 'own_damage' ? 'SOD Roll Over' :
            $proposal_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime(str_replace('/', '-', date('d/m/Y'))))));
            $date_diff = (strtotime(date("d-m-Y")) - strtotime($requestData->previous_policy_expiry_date)) / (60 * 60 * 24);
            if ($date_diff == 1)  
            {
                $time = date("H:i", time());
            } 
            else 
            {
                $time = '00:00';
            }

        }
        
        
        // salutaion
        if ($requestData->vehicle_owner_type == "I")
        {
            if ($proposal->gender == "M" || strtolower($proposal->gender) == "male")
            {
                $salutation = 'Mr';
            }
            else
            {
                if ($proposal->gender == "F" || strtolower($proposal->gender) == "female" && $proposal->marital_status == "Single")
                {
                    $salutation = 'Mrs';
                }
                else 
                {
                    $salutation = 'Miss';
                }
            }
        }
        else
        {
            $salutation = 'Ms';
        }
        // salutaion
        // CPA

        if ($vehicale_registration_number[0] == 'NEW')
        {
            $vehicale_registration_number[0] = '';
        }
        // if ($token)
        // {
            // $token_data = json_decode($token, true);

            // if (isset($token_data['access_token']))
            // {
                $UniqueIIBID = '';
                $IIBResponseRequired = 'NA';

                if ($proposal->previous_previous_insurance_company != 'MAGMA' && $requestData->business_type == 'rollover' && $requestData->is_claim == 'N')
                {
                	// token Generation
			        $token_data = self::generateToken($productData, $enquiryId);
			        if(!$token_data['status']) {
			            return [
			                'status' => false,
			                'msg' => $token_data['msg']
			            ];
			        }
                    $iib_array = [
                        'BusinessType' => 'Roll Over',
                        'PolicyProductType' => $PolicyProductType,
                        'ChassisNumber' => $proposal->chassis_number,
                        'EngineNumber' => $proposal->engine_number,
                        'PolicyNumber' => '',
                        'PolicyType' => 'PackagePolicy',
                        'PolicyStartDate' => $policy_start_date,
                        'PolicyEndDate' => $policy_end_date,
                        'PrevInsurerCompanyCode' => $proposal->previous_insurance_company,
                        'PrevNCBPercentage' => !($is_tp) ?(int) $requestData->previous_ncb :0,
                        'PrevNoOfClaims' => '',
                        'PrevPolicyStartDate' => $PreviousPolicyFromDt,
                        'PrevPolicyEndDate' => $PreviousPolicyToDt,
                        'PrevPolicyNumber' => $proposal->previous_policy_number,
                        'PrevPolicyType' => ($requestData->previous_policy_type == 'Own-damage') ? 'Standalone OD' : ($requestData->previous_policy_type == 'Third-party' ? 'LiabilityOnly' :'PackagePolicy'),
                        'PrevPolicyTenure' => '1',
                        'ProductCode' => '4102',
                        'RegistrionNumber' => $proposal->vehicale_registration_number,
                        'RelationshipCode' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPCODE'),
                        'BusinessSourceType' => 'P_AGENT',
                        'VehicleClassCode' => $mmv_data->vehicle_class_code
                    ];
            
                    $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_BIKE_GETIIBCLAIMDETAILS'), $iib_array, 'magma', [
                        'section'          => $productData->product_sub_type_code,
                        'method'           => 'IIB Verification',
                        'requestMethod'    => 'post',
                        'type'             => 'IIBVerification',
                        'token'            => $token_data['access_token'],
                        'enquiryId'        => $enquiryId,
                        'productName'      => $productData->product_sub_type_name,
                        'transaction_type' => 'proposal',
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $token_data['access_token']
                        ]
                    ]);
                    $iib_data = $get_response['response'];

                    if ($iib_data)
                    {
                        $iib_details = json_decode($iib_data, true);
                
                        if ($iib_details['ErrorText'] == '')
                        {
                            $UniqueIIBID = $iib_details['OutputResult']['UniqueIIBID'];
                            $IIBResponseRequired = $iib_details['OutputResult']['IIBResponseRequired'];
                        }
                        
                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'uniqueiibid' => $UniqueIIBID,
                                'iibresponserequired' => $IIBResponseRequired,
                            ]);
                    }
                    else
                    {
                        return response()->json([
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => 'Error in GetIIBClainDetails service',
                        ]);
                    }
                }

                $corresponding_address = DB::table('magma_motor_pincode_master AS mmpm')
                    ->leftJoin('magma_motor_city_master AS mmcm', 'mmpm.num_citydistrict_cd', '=', 'mmcm.num_citydistrict_cd')
                    ->leftJoin('magma_motor_state_master AS mmsm', 'mmpm.num_state_cd', '=', 'mmsm.num_state_cd')
                    ->where('mmpm.num_pincode', $proposal->pincode)
                    ->first();

                $is_pos = false;
                $pos_code = '';
                $pos_name = '';
                $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
                $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('user_proposal_id', $proposal->user_proposal_id)
                    ->where('seller_type', 'P')
                    ->first();
                if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote_log->premium_json['idv'] <= 5000000) {
                    if ($pos_data) {
                        $is_pos = true;
                        $pos_code = '200203';
                        $pos_name = 'NANDKISHOR BAVISKAR';
                    }
                }
                if(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_MAGMA') == 'Y'){
                    $is_pos = true;
                    $pos_code = '200203';
                    $pos_name = 'NANDKISHOR BAVISKAR';
                }

                $vehicle_price = MagmaBikePriceMaster::select('vehicle_selling_price')
                ->where([
                    'vehicle_class_code' => '37',
                    'vehicle_model_code' => $mmv_data->vehicle_model_code,
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
                            'vehicle_model_code' => $mmv_data->vehicle_model_code,
                            'rto_location_name' => $rto_location->rto_location_description
                        ]
                    ];
                }

                $proposal_array = [
                    'BusinessType' => $businesstype,
                    'PolicyProductType' => $PolicyProductType,
                    'ProposalDate' => $proposal_date,
                    'CompulsoryExcessAmount' => '100',
                    'VoluntaryExcessAmount' => '0',
                    'ImposedExcessAmount' => '',
                    'VehicleDetails' => [
                        'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
                        'TempRegistrationDate' => '',#Carbon::parse($quote_data['vehicle_register_date'])->format('d/m/Y'),
                        'RegistrationNumber' => $quote_log->premium_json['businessType'] == 'N' ? 'NEW' : strtoupper($proposal->vehicale_registration_number),
                        'ChassisNumber' => $proposal->chassis_number,
                        'EngineNumber' => $proposal->engine_number,
                        'RTOCode' => $rto_code,
                        'RTOName' => $rto_location->rto_location_description,
                        'ManufactureCode' => $mmv_data->manufacturer_code,
                        'ManufactureName' => $mmv_data->vehicle_manufacturer,
                        'ModelCode' => $mmv_data->vehicle_model_code,
                        'ModelName' => $mmv_data->vehicle_model_name,
                        'HPCC' => $mmv_data->cubic_capacity,
                        'MonthOfManufacture' => Carbon::parse('01-'.$requestData->manufacture_year)->format('m'),
                        'YearOfManufacture' => Carbon::parse('01-'.$requestData->manufacture_year)->format('Y'),
                        'VehicleClassCode' => $mmv_data->vehicle_class_code,
                        'SeatingCapacity' => $mmv_data->seating_capacity,
                        'CarryingCapacity' => $mmv_data->carrying_capacity,
                        'BodyTypeCode' => $mmv_data->body_type_code,
                        'BodyTypeName' => $mmv_data->body_type,
                        'FuelType' => $mmv_data->fuel_type,
                        'SeagmentType' => $mmv_data->segment_type,
                        'TACMakeCode' => '',
                        'ExShowroomPrice' => $vehicle_price->vehicle_selling_price ?? '',
                        'IDVofVehicle' => $quote_log->idv,
                        'HigherIDV' => '',
                        'LowerIDV' => '',
                        'IDVofChassis' => '',
                        'Zone' => 'Zone-' . $rto_location->registration_zone,
                        'IHoldValidPUC' => $proposal->is_valid_puc == '1' && $requestData->business_type != 'newbusiness' ? true : false,#$requestData->business_type == 'newbusiness' ? false : true,
                        'InsuredHoldsValidPUC' => false,#$requestData->business_type == 'newbusiness' ? false : true,
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
                        'PolicyEffectiveFromHour' => $time,//'00:00',//$time,
                        'PolicyEffectiveToHour' => '23:59',
                        'SPCode' => config('constants.IcConstants.magma.MAGMA_SPCode'),
                        'SPName' => config('constants.IcConstants.magma.MAGMA_SPName'),
                    ],
                    'PAOwnerCoverApplicable' => false,
                    'PAOwnerCoverDetails' => NULL,
                    'NomineeDetails' => NULL,
                    'AddOnsPlanApplicable' => false,
                    'AddOnsPlanApplicableDetails' => NULL,
                    'OptionalCoverageApplicable' => false,
                    'OptionalCoverageDetails' => NULL,
                    'IsPrevPolicyApplicable' => ($requestData->business_type == 'newbusiness') ? false : true,
                    'FinancierDetailsApplicable' => false,
                    'FinancierDetails' => NULL
                ];

                if($is_pos)
                {
                    $proposal_array['GeneralProposalInformation']['POSPCode'] = $pos_code;
                    $proposal_array['GeneralProposalInformation']['POSPName'] = $pos_name;
                }

                $selected_addons = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->first();

                if ($selected_addons)
                {
                    if ($selected_addons['compulsory_personal_accident'] && $selected_addons['compulsory_personal_accident'] != NULL && $selected_addons['compulsory_personal_accident'] != '' && $requestData->vehicle_owner_type == 'I' && !in_array($premium_type,['own_damage','own_damage_breakin']))
                    {
                        $proposal_array['PAOwnerCoverDetails'] = [
                            'PAOwnerSI'                => '',
                            'PAOwnerTenure'            => '',
                            'ValidDrvLicense'          => false,
                            'DoNotHoldValidDrvLicense' => false,
                            'Ownmultiplevehicles'      => false,
                            'ExistingPACover'          => false
                        ];
                        $tenure = '1';
                        foreach ($selected_addons['compulsory_personal_accident'] as $compulsory_personal_accident) {
                            if (isset($compulsory_personal_accident['name']) && $compulsory_personal_accident['name'] == 'Compulsory Personal Accident')
                            {
                                $tenure = isset($compulsory_personal_accident['tenure'])? $compulsory_personal_accident['tenure'] : $tenure;
                                $tenure = isset($tenure) ? $tenure : ($requestData->business_type == 'newbusiness' ? 5 : 1);
                                $proposal_array['PAOwnerCoverApplicable'] = true;
                                $proposal_array['PAOwnerCoverDetails'] = [
                                    'PAOwnerSI'                => '1500000',
                                    'PAOwnerTenure'            => $tenure,
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
                            }
                            else
                            {
                                if (isset($compulsory_personal_accident['reason']))
                                {
                                    if ($compulsory_personal_accident['reason'] == 'I do not have a valid driving license.')
                                    {
                                        $proposal_array['PAOwnerCoverDetails']['DoNotHoldValidDrvLicense'] = true;
                                    }
                                    elseif ($compulsory_personal_accident['reason'] == 'I have another motor policy with PA owner driver cover in my name')
                                    {
                                        $proposal_array['PAOwnerCoverDetails']['Ownmultiplevehicles'] = true;
                                    }
                                    elseif ($compulsory_personal_accident['reason'] == 'I have another PA policy with cover amount of INR 15 Lacs or more')
                                    {
                                        $proposal_array['PAOwnerCoverDetails']['ExistingPACover'] = true;
                                    }
                                }

                                unset($proposal_array['NomineeDetails']);
                            }
                        }                        
                    }

                    if ($selected_addons['accessories'] && $selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '' && !($is_tp))
                    {
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
                            }

                            if ($accessory['name'] == 'Non-Electrical Accessories') {
                                $proposal_array['OptionalCoverageDetails']['NonElectricalApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['NonElectricalDetails'] = [
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
                        
                        $proposal_array['OptionalCoverageApplicable'] = true;

                        foreach ($selected_addons['additional_covers'] as $additional_cover)
                        {
                            if ($additional_cover['name'] == 'LL paid driver')
                            {
                                $proposal_array['OptionalCoverageDetails']['LLPaidDriverCleanerApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['LLPaidDriverCleanerDetails'] = [
                                    'NoofPerson' => '1'
                                ];
                            }

                            if ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                            {
                                $proposal_array['OptionalCoverageDetails']['UnnamedPACoverApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['UnnamedPACoverDetails'] = [
                                    'NoOfPerunnamed' => $mmv_data->seating_capacity,
                                    'UnnamedPASI' => $additional_cover['sumInsured'],
                                ];
                            }

                            if ($additional_cover['name'] == 'Geographical Extension') {
                                if(config('MAGMA_CAR_BIKE_ENABLE_GEOGRAPHICAL_EXTENSION') == 'Y')
                                {
                                    //MAGMA REQUIRES UW UNDERWRITING APPROVAL FOR GEO EXTENSION IF BROKER AGRESS TO IT PLEASE ENABLE THIS CONFIG
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
                    }

                    if ($selected_addons['discounts'] && $selected_addons['discounts'] != NULL && $selected_addons['discounts'] != '')
                    {                
                        $proposal_array['OptionalCoverageApplicable'] = true;

                        foreach ($selected_addons['discounts'] as $discount)
                        {
                            if ($discount['name'] == 'anti-theft device')
                            {
                                $proposal_array['OptionalCoverageDetails']['ApprovedAntiTheftDevice'] = true;
                                $proposal_array['OptionalCoverageDetails']['CertifiedbyARAI'] = true;
                            }

                            if ($discount['name'] == 'voluntary_insurer_discounts')
                            {
                                $proposal_array['VoluntaryExcessAmount'] = $discount['sumInsured'];
                            }

                            if ($discount['name'] == 'TPPD Cover')
                            {
                                $proposal_array['OptionalCoverageDetails']['TPPDDiscountApplicable'] = true;
                            }
                        }
                    }

                    if(!isset($proposal_array['OptionalCoverageDetails']))
                    {
                        $proposal_array['OptionalCoverageApplicable'] = false;
                    }

                    if ($selected_addons['applicable_addons'] && $selected_addons['applicable_addons'] != NULL && $selected_addons['applicable_addons'] != '')
                    {                
                        $proposal_array['AddOnsPlanApplicable'] = true;
                        $proposal_array['AddOnsPlanApplicableDetails']['PlanName'] = 'Optional Add on';

                        foreach ($selected_addons['applicable_addons'] as $addon)
                        {
                            if ($addon['name'] == 'Zero Depreciation')
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['ZeroDepreciation'] = true;
                            }

                            if ($addon['name'] == 'Return To Invoice')
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['ReturnToInvoice'] = true;
                            }

                            if ($addon['name'] == 'Consumable' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['Consumables'] = true;
                            }

                            if ($addon['name'] == 'Road Side Assistance' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['RoadSideAssistance'] = true;
                            }

                            // $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacement'] = false;
                            // $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = null;
                            
                            // if ($addon['name'] == 'Key Replacement' && $interval->y < 5)
                            // {
                            //     $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacement'] =  true;
                            //     $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = [
                            //         "KeyReplacementSI" => "5000",
                            //     ];
                            // }
                        }
                    }
                }

                if ($requestData->business_type != 'newbusiness')
                {
                    $PrevPolicyDetails = [
                        'PrevPolicyDetails' => [
                            'PrevNCBPercentage' => !($is_tp) ? (int) $requestData->previous_ncb :0,
                            'PrevInsurerCompanyCode' => $proposal->previous_insurance_company,
                            'HavingClaiminPrevPolicy' => $requestData->is_claim == 'Y' ? true : false,
                            'PrevPolicyEffectiveFromDate' => $PreviousPolicyFromDt,
                            'PrevPolicyEffectiveToDate' => $PreviousPolicyToDt,
                            'PrevPolicyNumber' => $proposal->previous_policy_number,
                            'PrevPolicyType' => ($requestData->previous_policy_type == 'Own-damage') ? 'Standalone OD' : ($requestData->previous_policy_type == 'Third-party' ? 'LiabilityOnly' :'PackagePolicy'),
                            'PrevAddOnAvialable' => ($productData->zero_dep == '1') ? false : true,
                            'PrevPolicyTenure' => '1',
                            'IIBStatus' => 'Not Applicable',
                            'PrevInsuranceAddress' => 'ARJUN NAGAR',
                        ],
                    ];

                    $proposal_array = array_merge($proposal_array, $PrevPolicyDetails);
                }

                if (in_array($premium_type,['own_damage_breakin','own_damage']))
                {
                    $proposal_array['IsTPPolicyApplicable'] = true;
                    $proposal_array['PrevTPPolicyDetails'] = [
                        'PolicyNumber'      => $proposal->tp_insurance_number,
                        'PolicyType'        => 'LiabilityOnly',
                        'InsurerName'       => $proposal->tp_insurance_company,
                        'TPPolicyStartDate' => date('d/m/Y', strtotime($proposal->tp_start_date)),
                        'TPPolicyEndDate'   => date('d/m/Y', strtotime($proposal->tp_end_date))
                    ];
                }

                if($requestData->previous_policy_type == 'Not sure')
                {
                    $proposal_array['IsPrevPolicyApplicable'] = false;
                    $proposal_array['PrevPolicyDetails'] = null;
                }
                if ($requestData->previous_policy_type_identifier_code == '15') {
                    $proposal_array['PrevPolicyDetails']['PrevPolicyType'] = 'Bundled Policy';
                }
                if ($requestData->business_type == 'breakin' && $requestData->previous_policy_type != 'Not sure'){
                    $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
                    ##if breakin with more than 90 days IsPrevPolicyApplicable set to false
                    if ($date_difference > 90) {
                        $proposal_array['IsPrevPolicyApplicable'] = false;
                        $proposal_array['PrevPolicyDetails'] = null;
                    }
                }
                //Hypothecation
                if ($proposal->is_vehicle_finance == 1)
                {
                    $financer = MagmaFinancierMaster::where('code', $proposal->name_of_financer)
                        ->first();

                    $proposal_array['FinancierDetailsApplicable'] = true;

                    $proposal_array['FinancierDetails'] = [
                        'FinancierName' => $financer['name'],
                        'FinancierCode' => $financer['code'],
                        'FinancierAddress' => $proposal->hypothecation_city,
                        'AgreementType' => $proposal->financer_agreement_type,
                        'BranchName' => $proposal->hypothecation_city, //$Financier_Branch,
                        'CityCode' => $corresponding_address->num_citydistrict_cd,
                        'CityName' => $proposal->hypothecation_city, //$finance_City,
                        'DistrictCode' => $corresponding_address->num_citydistrict_cd,
                        'DistrictName' => $proposal->city,
                        'PinCode'     => $proposal->pincode,
                        'PincodeLocality' => $proposal->city,
                        'StateCode' => $corresponding_address->num_state_cd,
                        'StateName' => $proposal->state,
                        'FinBusinessType' => '',
                        'LoanAccountNumber' => ''
                    ];
                }
                //Hypothecation*/
                // FOr IIB Verification Pending
                if ($proposal->previous_previous_insurance_company != 'MAGMA' && $requestData->business_type == 'rollover' && $requestData->is_claim == 'N')
                {
                    $proposal_array['IIBClaimSearchDetails'] = [
                        'AcceptIIBResponse' => false,
                        'RejectIIBResponse' => false,
                        'IIBResponseRemarks' => $IIBResponseRequired,
                        'UniqueIIBID' => $UniqueIIBID
                    ];
                }
                if((int)$productData->default_discount > 0 && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                    $proposal_array['GeneralProposalInformation']['DetariffDis'] = (string) $productData->default_discount;
                }
                // token Generation 2
                $token_data2 = self::generateToken($productData, $enquiryId);
                if(!$token_data2['status']) {
                    return [
                        'status' => false,
                        'msg' => $token_data2['msg']
                    ];
                }

                if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
                    $agentDiscount = calculateAgentDiscount($enquiryId, 'magma', 'bike');
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

                $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_BIKE_GETPREMIUM'), $proposal_array, 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Premium Calculation',
                    'requestMethod'    => 'post',
                    'type'             => 'premiumCalculation',
                    'token'            => $token_data2['access_token'],
                    'enquiryId'        => $enquiryId,
                    'productName'      => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token_data2['access_token']
                    ]
                ]);
                $premium_data = $get_response['response'];

                if ($premium_data)
                {
                    $arr_premium = json_decode($premium_data, true);

                    if (isset($arr_premium['ServiceResult']) && $arr_premium['ServiceResult'] == "Success")
                    {
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
                            'Salutation' =>$requestData->vehicle_owner_type == 'I' ? $salutation : '',
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
                            'UIDNo' => config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y' && $proposal->ckyc_type == 'aadhar_card' && ! empty($ckyc_meta_data) ? $ckyc_meta_data['KYCData'] : null,
                            'FatherName' => '',
                            'SpouseName' => '',
                            'IncorporationPlace' => $proposal->city,
                        ];

                        if($requestData->vehicle_owner_type == 'I'){
                            unset($proposal_array['CustomerDetails']['IncorporationPlace']);
                        }else{
                            unset($proposal_array['CustomerDetails']['FatherName']);
                            unset($proposal_array['CustomerDetails']['SpouseName']);
                        }
                        
                        if(!empty($proposal->proposer_ckyc_details)){
                            if($proposal->proposer_ckyc_details->relationship_type == 'fatherName'){
                                $proposal_array['CustomerDetails']['FatherName'] = $proposal->proposer_ckyc_details->related_person_name ?? '';
                            }else{
                                $proposal_array['CustomerDetails']['SpouseName'] = $proposal->proposer_ckyc_details->related_person_name ?? '';
                            }
                        }
                        
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

                        // token Generation 3
                        $token_data3 = self::generateToken($productData, $enquiryId);
                        if(!$token_data3['status']) {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => $token_data3['msg']
                            ];
                        }

                        MagmaPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_BIKE_GETPROPOSAL'), $proposal_array, 'magma', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Proposal Generation',
                            'requestMethod' => 'post',
                            'type' => 'ProposalGeneration',
                            'token' => $token_data3['access_token'],
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_sub_type_name,
                            'transaction_type' => 'proposal',
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Bearer ' . $token_data3['access_token']
                            ]
                        ]);
                        $result = $get_response['response'];

                        if ($result)
                        {
                            $response = json_decode($result, true);
                            $err = $response['ErrorText'] ?? $response['Message'] ?? '';
                            if(!empty($err)) {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'msg' => $err
                                ];
                            }
                
                            if ($response['ErrorText'] == '')
                            {
                                $Nil_dep = $consumables = $basic_tp_premium = $basic_od_premium = $pa_unnamed = $ncb_discount = $liabilities = $pa_owner_driver = $electrical = $non_electrical = $antitheft = $tppd_discount = $road_side_assistance = $return_to_invoice = $voluntary_excess_discount = $GeogExtension_od = $GeogExtension_tp = 0;
                
                                $add_array = $response['OutputResult']['PremiumBreakUp']['VehicleBaseValue']['AddOnCover'];

                                if(isset($arr_premium['OutputResult']['IsProposalPending']) && $arr_premium['OutputResult']['IsProposalPending'] === true)
                                {
                                    $checkProposalStatusResponse = self::checkProposalStatus($proposal, $arr_premium['OutputResult']['ProposalNumber'], $token_data, $productData);
                                    return [
                                        'status' => false,
                                        'message' => $checkProposalStatusResponse['message']
                                    ];
                                }

                                foreach ($add_array as $add1)
                                {
                                    if ($add1['AddOnCoverType'] == 'Basic OD')
                                    {
                                        $basic_od_premium = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "Basic TP")
                                    {
                                        $basic_tp_premium = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "PA Owner Driver")
                                    {
                                        $pa_owner_driver = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "Zero Depreciation")
                                    {
                                        $Nil_dep = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "LL to Paid Driver IMT 28")
                                    {
                                        $liabilities = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "Road Side Assistance")
                                    {
                                        $road_side_assistance = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "Return To Invoice")
                                    {
                                        $return_to_invoice = ($add1['AddOnCoverTypePremium']);
                                    }

                                    if ($add1['AddOnCoverType'] == "Consumables")
                                    {
                                        $consumables = ($add1['AddOnCoverTypePremium']);
                                    }
                                }

                                if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers']))
                                {
                                    $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];

                                    foreach ($optionadd_array as $add)
                                    {
                                        if ($add['OptionalAddOnCoversName'] == "Personal accident cover Unnamed")
                                        {
                                            $pa_unnamed = ($add['AddOnCoverTotalPremium']);
                                        }

                                        if ($add['OptionalAddOnCoversName'] == "Electrical or Electronic Accessories")
                                        {
                                            $electrical = ($add['AddOnCoverTotalPremium']);
                                        }

                                        if ($add['OptionalAddOnCoversName'] == "Non Electrical Accessories")
                                        {
                                            $non_electrical = ($add['AddOnCoverTotalPremium']);
                                        }

                                        if ($add['OptionalAddOnCoversName'] == "Geographical Extension - OD") {
                                            $GeogExtension_od = ($add['AddOnCoverTotalPremium']);
                                        }
            
                                        if ($add['OptionalAddOnCoversName'] == "Geographical Extension - TP") {
                                            $GeogExtension_tp = ($add['AddOnCoverTotalPremium']);
                                        }

                                        if ($add['OptionalAddOnCoversName'] == 'Basic OD') {
                                            $basic_od_premium = ($add['AddOnCoverTotalPremium']);
                                        }
            
                                        if ($add['OptionalAddOnCoversName'] == "Basic TP") {
                                            $basic_tp_premium = ($add['AddOnCoverTotalPremium']);
                                        }
            
                                        if ($add['OptionalAddOnCoversName'] == "PA Owner Driver") {
                                            $pa_owner_driver = ($add['AddOnCoverTotalPremium']);
                                        }
            
                                        if ($add['OptionalAddOnCoversName'] == "Zero Depreciation") {
                                            $Nil_dep = ($add['AddOnCoverTotalPremium']);
                                        }
            
                                        if ($add['OptionalAddOnCoversName'] == "LL to Paid Driver IMT 28") {
                                            $liabilities = ($add['AddOnCoverTotalPremium']);
                                        }

                                        if ($add['OptionalAddOnCoversName'] == "Road Side Assistance") {
                                            $road_side_assistance = ($add['AddOnCoverTotalPremium']);
                                        }
            
                                        if ($add['OptionalAddOnCoversName'] == "Return To Invoice") {
                                            $return_to_invoice = ($add['AddOnCoverTotalPremium']);
                                        }
                                        if ($add['OptionalAddOnCoversName'] == "Consumables") {
                                            $consumables = ($add['AddOnCoverTotalPremium']);
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
                                            $antitheft = ($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Basic TP - TPPD Discount")
                                        {
                                            $tppd_discount = ($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Voluntary Excess Discount-OD")
                                        {
                                            $voluntary_excess_discount = ($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "No Claim Bonus Discount")
                                        {
                                            $ncb_discount = ($discount['DiscountTypeAmount']);
                                        }
                                    }
                                }
                
                                $final_total_discount = $ncb_discount + $antitheft + $tppd_discount + $voluntary_excess_discount;
                                $final_od_premium = $basic_od_premium + $GeogExtension_od - $final_total_discount;
                                $final_tp_premium = $basic_tp_premium + $liabilities + $pa_unnamed + $pa_owner_driver + $GeogExtension_tp;
                                $final_addon_amount = $Nil_dep + $return_to_invoice + $electrical + $non_electrical + $consumables + $road_side_assistance;
                                $final_net_premium = ($arr_premium['OutputResult']['PremiumBreakUp']['NetPremium']);
                                $final_payable_amount = ($arr_premium['OutputResult']['PremiumBreakUp']['TotalPremium']);
                
                                UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'od_premium' => $final_od_premium,
                                        'tp_premium' => $final_tp_premium,
                                        'ncb_discount' => $ncb_discount,
                                        'total_discount' => $final_total_discount,
                                        'addon_premium' => $final_addon_amount,
                                        'policy_start_date' => str_replace('/', '-', $policy_start_date),
                                        'policy_end_date' =>$requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d-m-Y') : ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ? Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(5)->subDay(1)->format('d-m-Y')  : str_replace('/', '-', $policy_end_date)),
                                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+5 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
                                        'proposal_no' => $response['OutputResult']['ProposalNumber'],
                                        'customer_id' => $response['OutputResult']['CustomerID'],
                                        'cpa_premium' => $pa_owner_driver,
                                        'total_premium' => $final_net_premium,
                                        'service_tax_amount' => $final_payable_amount - $final_net_premium,
                                        'final_payable_amount'  => $final_payable_amount,
                                        'product_code' => '4102',
                                        'ic_vehicle_details' => json_encode([
                                            'manufacture_name' => $mmv_data->vehicle_manufacturer,
                                            'model_name' => $mmv_data->vehicle_model_name,
                                            'version' => $mmv_data->variant,
                                            'fuel_type' => $mmv_data->fuel_type,
                                            'seating_capacity' => $mmv_data->seating_capacity,
                                            'carrying_capacity' => $mmv_data->carrying_capacity,
                                            'cubic_capacity' => $mmv_data->cubic_capacity,
                                            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
                                            'vehicle_type' => $mmv_data->veh_ob_type ?? '',
                                        ])
                                    ]);                    
                
                                #for updating proposal stage
                                updateJourneyStage([
                                    'user_product_journey_id' => $enquiryId,
                                    'ic_id' => $productData->company_id,
                                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                                    'proposal_id' => $proposal->user_proposal_id,
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
                            }
                            else
                            {
                                return [
                                    'status' => false,
                                    'webservice_id' => $get_response['webservice_id'],
                                    'table' => $get_response['table'],
                                    'msg' => $response['ErrorText'] ?? 'Error in proposal generation service'
                                ];
                            }
                        }
                        else
                        {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => 'Error in proposal generation service'
                            ];
                        }
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'msg' => $arr_premium['ErrorText'] ?? $arr_premium['Message'] ?? 'Error in premium calculation service'
                        ];
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'msg' => 'Error in premium calculation service'
                    ];
                }
            //}
            // else
            // {
            //     return [
            //         'status' => false,
            //         'msg' => $token_data['ErrorText'] ?? 'Error in token generation service'
            //     ];
            // }
        // }
        // else
        // {
        //     return [
        //         'status' => false,
        //         'msg' => 'Error in token generation service'
        //     ];
        // }
    }

    /**
     * Generate token
     * @param Object $productData : Product related Data
     * @param Integer $enquiryId : Decrypted Trace ID
     * @return Array [status, msg|access_token]
     */
    public static function generateToken($productData, $enquiryId) {
        // token Generation
        $tokenParam = [
            'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
            'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
            'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
            'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
        ];
        //For every API call we need to use unique token
        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
            'section'          => $productData->product_sub_type_code,
            'method'           => 'Token Generation',
            'requestMethod'    => 'post',
            'type'             => 'tokenGeneration',
            'enquiryId'        => $enquiryId,
            'productName'      => $productData->product_sub_type_name,
            'transaction_type' => 'proposal'
        ]);
        $token = $get_response['response'];
        $token_data = json_decode($token, true);
        if (!isset($token_data['access_token'])) {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => isset($token_data['ErrorText']) ? preg_replace("/Audit Log Transaction ID - .(\d+)./", "", $token_data['ErrorText']) : 'Error occured in token generation service'
            ];
        } else {
            return [
                'status' => true,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'access_token' => $token_data['access_token']
            ];
        }
    }

    public static function checkProposalStatus($proposal, $proposal_no, $token_data, $productData)
    {
        $proposal_status_requset = [
            'ProposalNumber' => $proposal->proposal_no
        ];

        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_PROPOSAL_STATUS'), $proposal_status_requset, 'magma', [
            'section'          => $productData->product_sub_type_code,
            'method'           => 'Proposal Status',
            'requestMethod'    => 'post',
            'type'             => 'proposalStatus',
            'enquiryId'        => $proposal->user_product_journey_id,
            'token'            => $token_data['access_token'],
            'productName'      => $productData->product_sub_type_name,
            'transaction_type' => 'proposal'
        ]);
        $ProposalStatusResponse = $get_response['response'];
        if ($ProposalStatusResponse)
        {
            $ProposalStatusResponse = json_decode($ProposalStatusResponse, TRUE);

            return [
                'status' => true,
                'message' => $ProposalStatusResponse['OutputResult']['PendingUWApproval'] ?? 'UW approval required'
            ];
        }
        else{
            return [
                'status' => true,
                'message' => 'UW approval required'
            ];
        }
    }
}