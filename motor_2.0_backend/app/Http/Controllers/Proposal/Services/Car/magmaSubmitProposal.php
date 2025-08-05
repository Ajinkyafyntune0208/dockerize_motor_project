<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Http\Controllers\SyncPremiumDetail\Car\MagmaPremiumDetailController;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterPolicy;
use App\Models\SelectedAddons;
use App\Models\MagmaFinancierMaster;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class magmaSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        if ($requestData->business_type == 'breakin') {
            $policy_start_date = date('d/m/Y', strtotime('+2 day', strtotime(date('d-m-Y'))));
            if($requestData->previous_policy_type == 'Not sure'){
                $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime(date('d-m-Y'))));
            }
        } elseif ($requestData->business_type == 'newbusiness') {
            $policy_start_date = date('d/m/Y');
        }
        elseif ($requestData->business_type == 'rollover')
        {
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        }

        $policy_end_date = Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d/m/Y');
        $time = '00:00';
        $mmv = get_mmv_details($productData,$requestData->version_id,'magma');

        if ($mmv['status'] == 1)
        {
            $mmv = $mmv['data'];
        }
        else
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        $rto_code = $quote_log->premium_json['vehicleRegistrationNo'];
        $rto_code = explode('-', $rto_code);
        if ($rto_code[0] == 'DL') {
            $remove_zero = $rto_code[1] * 1;
            $rto_code = $rto_code[0] . '-' . $remove_zero;
        } else {
            $rto_code = implode('-', $rto_code);
        }  
        $rto_code = preg_replace("/OR/", "OD", $rto_code);
        $rto_location = DB::table('magma_rto_location')
            ->where('rto_location_code', $rto_code)
            ->where('vehicle_class_code', '45')
            ->first();

        if (str_starts_with(strtoupper($rto_code), "DL-0")) {
            $rto_code = RtoCodeWithOrWithoutZero($rto_code);
        }
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new \DateTime($vehicleDate);
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $carage = floor($age / 12);

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
        $vehicale_registration_number = explode('-', $proposal->vehicale_registration_number);

        if ($requestData->business_type == 'newbusiness')
        {
            $proposal->previous_policy_number = '';
            $proposal->previous_insurance_company = '';
            $PreviousPolicyFromDt = '';
            $PreviousPolicyToDt = '';
            $policy_start_date = today()->format('d/m/Y');
            $policy_end_date = today()->addYear(3)->subDay(1)->format('d/m/Y');
            $PolicyProductType = in_array($premium_type, ['third_party']) ? '3TP' : '3TP1OD';
            $previous_ncb = "";
            $PreviousPolicyType = "";
            $businesstype       = 'New Business';
            $proposal_date = $policy_start_date;
            $time = date('H:i', time());
        }
        else
        {
            if($requestData->previous_policy_type_identifier_code == '33')
            {
                $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(3)->addDay(1)->format('d/m/Y'); 
            }
            else
            {
                $PreviousPolicyFromDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->subYear(1)->addDay(1)->format('d/m/Y');
            }
            $PreviousPolicyToDt = Carbon::parse(str_replace('/', '-', $proposal->prev_policy_expiry_date))->format('d/m/Y');
            $PolicyProductType = $premium_type == 'own_damage' ? '1OD' : (in_array($premium_type, ['third_party', 'third_party_breakin']) ? '1TP' : '1TP1OD');
            $PreviousPolicyType = "MOT-PLT-001";
            $previous_ncb = $requestData->previous_ncb;
            $businesstype       = 'Roll Over';
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
                $pos_code = config('constants.IcConstants.magma.MAGMA_POSP_CODE');
                $pos_name = config('constants.IcConstants.magma.MAGMA_POSP_NAME');
            }
        }
        if(config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE_MAGMA') == 'Y'){
            $is_pos = true;
            $pos_code = config('constants.IcConstants.magma.MAGMA_POSP_CODE');
            $pos_name = config('constants.IcConstants.magma.MAGMA_POSP_NAME');
        }

        // salutaion
        if ($requestData->vehicle_owner_type == "I")
        {
            if ($proposal->gender == "MALE")
            {
                $salutation = 'Mr';
            }
            else
            {
                if ($proposal->gender == "FEMALE" && $proposal->marital_status == "Single")
                {
                    $salutation = 'Miss';
                }
                else
                {
                    $salutation = 'Mrs';
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

        // token Generation
        $tokenParam = [
            'grant_type' => config('constants.IcConstants.magma.MAGMA_GRANT_TYPE'),
            'username' => config('constants.IcConstants.magma.MAGMA_USERNAME'),
            'password' => config('constants.IcConstants.magma.MAGMA_PASSWORD'),
            'CompanyName' => config('constants.IcConstants.magma.MAGMA_COMPANYNAME'),
        ];

        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETTOKEN'), http_build_query($tokenParam), 'magma', [
            'method' => 'Token Generation',
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => $productData->product_sub_type_code,
            'enquiryId' => $enquiryId,
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal'
        ]);

        $token = $get_response['response'];
        if ($token)
        {
            $token_data = json_decode($token, true);
            
            if (isset($token_data['access_token']))
            {
                $UniqueIIBID = '';
                $IIBResponseRequired = 'NA';

                if($requestData->previous_policy_type == 'Own-damage'){
                    $previous_policy_type = "Standalone OD";
                }elseif($requestData->previous_policy_type == 'Third-party'){
                    $previous_policy_type = "LiabilityOnly";
                }elseif(in_array($requestData->previous_policy_type, ['Comprehensive','Not sure'])){
                    $previous_policy_type = "PackagePolicy";
                }
                /* Removing GetIIBClainDetails service as per git #33979
                if ($proposal->previous_previous_insurance_company != 'MAGMA' && $requestData->business_type == 'rollover' && $requestData->is_claim == 'N')
                {
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
                        'PrevNCBPercentage' => (int) $requestData->previous_ncb,
                        'PrevNoOfClaims' => '',
                        'PrevPolicyStartDate' => $PreviousPolicyFromDt,
                        'PrevPolicyEndDate' => $PreviousPolicyToDt,
                        'PrevPolicyNumber' => $proposal->previous_policy_number,
                        'PrevPolicyType' => $previous_policy_type,
                        // ($requestData->previous_policy_type == 'Own-damage') ? 'Standalone OD' : ($premium_type == 'third_party' ? 'LiabilityOnly' : 'PackagePolicy'),
                        // 'PrevPolicyType' => (in_array($premium_type, ['third_party', 'third_party_breakin']) ? 'LiabilityOnly' : 'PackagePolicy'),
                        'PrevPolicyTenure' => '1',
                        'ProductCode' => '4101',
                        'RegistrionNumber' => ($quote_log->premium_json['businessType'] == 'N') ? 'NEW' :  $proposal->vehicale_registration_number,
                        'RelationshipCode' => config('constants.IcConstants.magma.MAGMA_ENTITYRELATIONSHIPCODE'),
                        'BusinessSourceType' => $is_pos ? 'P_AGENT' :'C_AGENT',
                        'VehicleClassCode' => '45',
                    ];

                    $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETIIBCLAIMDETAILS'), $iib_array, 'magma', [
                        'section'          => $productData->product_sub_type_code,
                        'method'           => 'IIB Verification',
                        'requestMethod'    => 'post',
                        'type'             => 'IIBVerification',
                        'token'            => $token_data['access_token'],
                        'enquiryId'        => $enquiryId,
                        'productName'      => $productData->product_sub_type_name,
                        'transaction_type' => 'proposal'
                    ]);
                    $iib_data = $get_response['response'];

                    if ($iib_data)
                    {
                        $iib_details = json_decode($iib_data, true);

                        if (isset($iib_details['ErrorText']) && $iib_details['ErrorText'] == '') {
                
                            $UniqueIIBID = $iib_details['OutputResult']['UniqueIIBID'];
                            $IIBResponseRequired = $iib_details['OutputResult']['IIBResponseRequired'];
                        }

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'uniqueiibid'         => $UniqueIIBID,
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
                }*/

                $corresponding_address = DB::table('magma_motor_pincode_master AS mmpm')
                    ->leftJoin('magma_motor_city_master AS mmcm', 'mmpm.num_citydistrict_cd', '=', 'mmcm.num_citydistrict_cd')
                    ->leftJoin('magma_motor_state_master AS mmsm', 'mmpm.num_state_cd', '=', 'mmsm.num_state_cd')
                    ->where('mmpm.num_pincode', $proposal->pincode)
                    ->first();
                
                $proposal_array = [
                    'BusinessType' => $businesstype,
                    'PolicyProductType' => $PolicyProductType,
                    'ProposalDate' => $proposal_date,
                    'VehicleDetails' => [
                        // 'RegistrationDate' => Carbon::parse($quote_data['vehicle_register_date'])->format('d/m/Y'),
                        'RegistrationDate' => Carbon::parse($requestData->vehicle_register_date)->format('d/m/Y'),
                        'TempRegistrationDate' => '',
                        //'RegistrationNumber' => $requestData->business_type == 'newbusiness' ?  'NEW' : strtoupper($proposal->vehicale_registration_number),
                        'RegistrationNumber' => $requestData->business_type == 'newbusiness' ?  'NEW' :strtoupper(str_replace(' ', '', $proposal->vehicale_registration_number)),
                        "IsVehicleBharatRegistered" => false,
                        'ChassisNumber' => $proposal->chassis_number,
                        "BharatVehicleOwnBy" => null,
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
                        'VehicleClassCode' => '45',
                        'SeatingCapacity' => $mmv_data->seating_capacity,
                        'CarryingCapacity' => $mmv_data->seating_capacity - 1,
                        'BodyTypeCode' => $mmv_data->body_type_code,
                        'BodyTypeName' => $mmv_data->body_type,
                        'FuelType' => $mmv_data->fuel,
                        'SeagmentType' => $mmv_data->segment_type,
                        'TACMakeCode' => '',
                        'ExShowroomPrice' => '00',
                        'IDVofVehicle' => $quote_log->idv,
                        'HigherIDV' => '',
                        'LowerIDV' => '',
                        'IDVofChassis' => '',
                        'Zone' => 'Zone-' . $rto_location->registration_zone,
                        'IHoldValidPUC' => $requestData->business_type == 'newbusiness' ? false : true,
                        'InsuredHoldsValidPUC' => false //$requestData->business_type == 'newbusiness' || $requestData->business_type == 'rollover' || $premium_type == 'third_party' ? false : true,
                        // "IIBClaimSearchDetails" => null,
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
                        'BusinessSourceType' => $is_pos ? 'P_AGENT' :'C_AGENT',#'C_AGENT',
                        'PolicyEffectiveFromDate' => $policy_start_date,
                        'PolicyEffectiveToDate' => $policy_end_date,
                        'PolicyEffectiveFromHour' =>$time,//'00:00',//$time,
                        'PolicyEffectiveToHour' => '23:59',
                        'SPCode' => config('constants.IcConstants.magma.MAGMA_SPCode'),
                        'SPName' => config('constants.IcConstants.magma.MAGMA_SPName'),
                    ],
                    'AddOnsPlanApplicable' => false,
                    'OptionalCoverageApplicable' => false,
                    'OptionalCoverageDetails' => [],
                    'IsPrevPolicyApplicable' => $requestData->business_type == 'newbusiness' ? false : true,
                    'CompulsoryExcessAmount' => '1000',
                    'ImposedExcessAmount' => '',
                    'VoluntaryExcessAmount' => '',
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
                    if ($selected_addons['compulsory_personal_accident'] && $selected_addons['compulsory_personal_accident'] != NULL && $selected_addons['compulsory_personal_accident'] != '' && $requestData->vehicle_owner_type == 'I' && $premium_type != 'own_damage')
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
                                $tenure = isset($tenure) ? $tenure : ($requestData->business_type == 'newbusiness' ? 3 : 1);
                                $proposal_array['PAOwnerCoverApplicable'] = true;
                                $proposal_array['PAOwnerCoverDetails'] = [
                                    'PAOwnerSI'                => '1500000',
                                    'PAOwnerTenure'            => $tenure,#'1',
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

                    if ($selected_addons['accessories'] && $selected_addons['accessories'] != NULL && $selected_addons['accessories'] != '')
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

                            if ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                                $proposal_array['OptionalCoverageDetails']['ExternalCNGkitApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['ExternalCNGLPGkitDetails'] = [
                                        'CngLpgSI' => (string) $accessory['sumInsured']
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

                            if ($additional_cover['name'] == 'PA cover for additional paid driver')
                            {
                                $proposal_array['OptionalCoverageDetails']['PAPaidDriverApplicable'] = true;
                                $proposal_array['OptionalCoverageDetails']['PAPaidDriverDetails'] = [
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
            
                                    $proposal_array['OptionalCoverageDetails']['GeographicalExtensionApplicable'] = true;
                                    $proposal_array['OptionalCoverageDetails']['GeographicalExtensionDetails'] = [
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
                        $proposal_array['OptionalCoverageApplicable'] = true;

                        foreach ($selected_addons['discounts'] as $discount)
                        {
                            if ($discount['name'] == 'anti-theft device' && !$tp_only)
                            {
                                $proposal_array['OptionalCoverageDetails']['ApprovedAntiTheftDevice'] = true;
                                $proposal_array['OptionalCoverageDetails']['CertifiedbyARAI'] = true;
                            }

                            if ($discount['name'] == 'voluntary_insurer_discounts' && !$tp_only)
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
                    $proposal_array['AddOnsPlanApplicable'] = false;
                    $proposal_array['AddOnsPlanApplicableDetails'] = null;
                    $PrevAddOnAvialable = false;
                    if ($selected_addons['applicable_addons'] && $selected_addons['applicable_addons'] != NULL && $selected_addons['applicable_addons'] != '')
                    {                
                        $proposal_array['AddOnsPlanApplicable'] = true;
                        $proposal_array['AddOnsPlanApplicableDetails']['PlanName'] = 'Optional Add on';
                        $PrevAddOnAvialable = true;
                        foreach ($selected_addons['applicable_addons'] as $addon)
                        {
                            if ($addon['name'] == 'Zero Depreciation' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['ZeroDepreciation'] = true;
                            }

                            if ($addon['name'] == 'Return To Invoice' && $interval->y < 3)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['ReturnToInvoice'] = true;
                            }

                            if ($addon['name'] == 'Road Side Assistance' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['RoadSideAssistance'] = true;
                            }
                         //enable key replacement as per the git id https://github.com/Fyntune/motor_2.0_backend/issues/24430
                         //key replacement changes https://github.com/Fyntune/motor_2.0_backend/issues/23427
                            // $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacement'] = false;
                            // $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = null;
                            if ($addon['name'] == 'Tyre Secure' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['TyreGuard'] = true;
                            }
                            if ($addon['name'] == 'Key Replacement' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacement'] = true ;
                                $proposal_array['AddOnsPlanApplicableDetails']['KeyReplacementDetails'] = [
                                    "KeyReplacementSI" => '50000',
                                ];
                            }


                            if ($addon['name'] == 'Engine Protector' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['EngineProtector'] = true;
                            }
                            if ($addon['name'] == 'NCB Protection' && $requestData->business_type != 'newbusiness' && ($requestData->is_claim != 'Y') && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['NCBProtection'] = true;
                            }

                            if ($addon['name'] == 'Loss of Personal Belongings' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['LossOfPerBelongings'] = true;
                            }
                            if ($addon['name'] == 'Consumable' && $interval->y < 5)
                            {
                                $proposal_array['AddOnsPlanApplicableDetails']['Consumables'] = true;
                            }
                        }
                    }
                }
                if ($requestData->business_type != 'newbusiness')
                {
                    $PrevPolicyDetails = [
                        'PrevPolicyDetails' => [
                            'PrevNCBPercentage' => (int) $previous_ncb,
                            'PrevInsurerCompanyCode' => $proposal->previous_insurance_company,
                            'HavingClaiminPrevPolicy' => $requestData->is_claim == 'Y' ? true : false,
                            'NoOfClaims' => $requestData->is_claim == 'N' ? 0 : 1,
                            'PrevPolicyEffectiveFromDate' => $PreviousPolicyFromDt,
                            'PrevPolicyEffectiveToDate' => $PreviousPolicyToDt,
                            'PrevPolicyNumber' => $proposal->previous_policy_number,
                            'PrevPolicyType' => $previous_policy_type,
                            //'PrevPolicyType' => (in_array($premium_type, ['third_party', 'third_party_breakin']) ? 'LiabilityOnly' : 'PackagePolicy'),
                            'PrevAddOnAvialable' => $PrevAddOnAvialable,//($productData->zero_dep == '1') ? false : true,
                            'PrevPolicyTenure' => '1',
                            'IIBStatus' => 'Not Applicable',
                            'PrevInsuranceAddress' => 'ARJUN NAGAR',
                        ],
                    ];

                    $proposal_array = array_merge($proposal_array, $PrevPolicyDetails);
                }

                if ($premium_type == 'own_damage')
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
                if ($requestData->previous_policy_type_identifier_code == '13') {
                    $proposal_array['PrevPolicyDetails']['PrevPolicyType'] = 'Bundled Policy';
                }

                if($requestData->previous_policy_type == 'Not sure')
                {
                    $proposal_array['IsPrevPolicyApplicable'] = false;
                    $proposal_array['PrevPolicyDetails'] = null;
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
                if ($proposal->previous_insurance_company != 'MAGMA' && $requestData->business_type == 'rollover' && $requestData->is_claim == 'N')
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

                if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {

                    // $proposal_array['AddOnsPlanApplicable'] = false;
                    // $proposal_array['AddOnsPlanApplicableDetails'] = null;

                    $agentDiscount = calculateAgentDiscount($enquiryId, 'magma', 'car');
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
                if (in_array($premium_type, ['third_party_breakin', 'third_party'])){
                    $proposal_array['AddOnsPlanApplicable'] = false;
                    $proposal_array['AddOnsPlanApplicableDetails'] = null;
                }
    
                $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETPREMIUM'), $proposal_array, 'magma', [
                    'section'          => $productData->product_sub_type_code,
                    'method'           => 'Premium Calculation',
                    'requestMethod'    => 'post',
                    'type'             => 'premiumCalculation',
                    'token'            => $token_data['access_token'],
                    'enquiryId'        => $enquiryId,
                    'productName'      => $productData->product_name,
                    'transaction_type' => 'proposal'
                ]);

                $premium_data = $get_response['response'];
                if ($premium_data)
                {
                    $arr_premium = json_decode($premium_data, true);

                    if (isset($arr_premium['ServiceResult']) && $arr_premium['ServiceResult'] == "Success")
                    {
                        $vehicleDetails = [
                            'manufacture_name'  => $mmv_data->vehicle_manufacturer,
                            'model_name'        => $mmv_data->vehicle_model_name,
                            'version'           => $mmv_data->variant,
                            'fuel_type'         => $mmv_data->fuel,
                            'seating_capacity'  => $mmv_data->seating_capacity,
                            'carrying_capacity' => $mmv_data->carrying_capacity,
                            'cubic_capacity'    => $mmv_data->cubic_capacity,
                            'gross_vehicle_weight' => $mmv_data->veh_gvw ?? 1,
                            'vehicle_type'      => $mmv_data->veh_ob_type ?? '',
                        ];

                        $ckyc_meta_data = ! empty($proposal->ckyc_meta_data) ? json_decode($proposal->ckyc_meta_data, true) : null;

                        $proposal_array['CustomerDetails'] = [
                            'QuotationNumber' => $arr_premium['OutputResult']['ProposalNumber'] ?? '',
                            'CustomerType' => $requestData->vehicle_owner_type,
                            'CustomerName' => $requestData->vehicle_owner_type == 'I' ? $proposal->first_name . " " . $proposal->last_name : $proposal->first_name,
                            'CountryCode' => '91',
                            'CountryName' => 'India',
                            'ContactNo' => $proposal->mobile_number,
                            'PinCode' => $proposal->pincode,
                            'PincodeLocality' => $proposal->city,
                            'Nationality' => 'Indian',
                            'Salutation' => $requestData->vehicle_owner_type == 'I' ? $salutation : '',
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
                            'PanNo' => config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y' && $proposal->ckyc_type == 'pan_card' && ! empty($ckyc_meta_data) ? ($ckyc_meta_data['KYCData'] ?? null) : $proposal->pan_number,
                            'AnnualIncome' => '1212121',
                            'GSTNumber' => $proposal->gst_number,
                            'UIDNo' => config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y' && $proposal->ckyc_type == 'aadhar_card' && ! empty($ckyc_meta_data) ? ($ckyc_meta_data['KYCData'] ?? null) : null,
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


                        if (!empty($ckyc_meta_data) && isset($ckyc_meta_data['KYCType']) &&  ($ckyc_meta_data['KYCType'] == 'KYC DOCUMENT UPLOAD')) {

                            if ($ckyc_meta_data['DocumentType'] == 'AADHAAR') {
                                $ckyc_meta_data['DocumentID'] =  substr($ckyc_meta_data['DocumentID'], -4);
                            }
                        } else {
                            $ckyc_meta_data['DocumentID'] = '';
                        }

                        if (config('constants.magma.IS_CKYC_ENABLED_FOR_MAGMA') == 'Y') {
                            $proposal_array['CustomerDetails'] = array_merge($proposal_array['CustomerDetails'], [
                                "IsKYCSuccess" => true,
                                "KYCNumber" => $proposal->ckyc_number ?? '',
                                "KYCType" => ! empty($ckyc_meta_data) ? $ckyc_meta_data['KYCType'] : '',
                                "PartnerKYCDocRefID" => "",
                                "IncorporationDate" => $requestData->vehicle_owner_type == 'C' ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',
                                "KYCLogID" => $proposal->ckyc_reference_id,
                                "DocumentID" => $ckyc_meta_data['DocumentID']  //!empty($ckyc_meta_data) ? substr($ckyc_meta_data['DocumentID'], -4) : ''

                            ]);

                            if ( ! empty($ckyc_meta_data['DocumentType'])) {
                                $proposal_array['CustomerDetails']['DocumentType'] = $ckyc_meta_data['DocumentType'];
                            }
                        }

                        MagmaPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        $get_response = getWsData(config('constants.IcConstants.magma.END_POINT_URL_MAGMA_MOTOR_GETPROPOSAL'), $proposal_array, 'magma', [
                            'section' => $productData->product_sub_type_code,
                            'method' => 'Proposal Generation',
                            'requestMethod' => 'post',
                            'type' => 'ProposalGeneration',
                            'token' => $token_data['access_token'],
                            'enquiryId' => $enquiryId,
                            'productName' => $productData->product_sub_type_name,
                            'transaction_type' => 'proposal'
                        ]);
                        $result = $get_response['response'];

                        if ($result)
                        {
                            $response = json_decode($result, true);
                
                            if ($response['ErrorText'] == '')
                            {
                                $proposal->proposal_no = $response['OutputResult']['ProposalNumber'];
                                $proposal->ic_vehicle_details = $vehicleDetails;
                                $proposal->save();

                                $basic_od_premium = $basic_tp_premium = $Nil_dep = $pa_unnamed = $ncb_discount = $liabilities =  $pa_paid_driver = $pa_owner_driver = $electrical = $non_electrical = $lpg_od_premium = $lpg_tp_premium = $cng_od_premium = $cng_tp_premium = $ncb_discount = $antitheft = $ncb_protction = $roadside_asst = $key_replacement = $tyre_secure = $loss_of_personal_belongings = $eng_protector = $return_to_invoice = $tppd_discount = $voluntary_excess_discount = $other_discount = $geog_Extension_OD_Premium = $geog_Extension_TP_Premium = 0;
                                $electrical_discount = $non_electrical_discount = $bifuel_discount = $bifuel_discount_cng = 0;

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
                                    if (in_array($add1['AddOnCoverType'], ["Tyre Guard","TyreGuard"]))
                                    {
                                        $tyre_secure = (float)($add1['AddOnCoverTypePremium']);
                                    }
                                    if (in_array($add1['AddOnCoverType'], ["Key Replacement","KeyReplacement"]))
                                    {
                                        $key_replacement_premium = (float)($add1['AddOnCoverTypePremium']);
                                    }
            
                                    if (in_array($add1['AddOnCoverType'], ["Loss Of Personal Belongings","LossOfPerBelongings"]))
                                    {
                                        $loss_of_personal_belongings_premium = (float)($add1['AddOnCoverTypePremium']);
                                    }
                                }
            
                                if (isset($arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers']))
                                {
                                    $optionadd_array = $arr_premium['OutputResult']['PremiumBreakUp']['OptionalAddOnCovers'];
            
                                    foreach ($optionadd_array as $add)
                                    {
                                        if (in_array($add['OptionalAddOnCoversName'], ['Electrical or Electronic Accessories','Electrical']))
                                        {
                                            $electrical = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["Non-Electrical Accessories","NonElectrical"]))
                                        {
                                            $non_electrical = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["Personal Accident Cover-Unnamed","UnnamedPACover"]))
                                        {
                                            $pa_unnamed = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["PA Paid Drivers, Cleaners and Conductors","PAPaidDriver"]))
                                        {
                                            $pa_paid_driver = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["LPG Kit-OD"]))
                                        {
                                            $lpg_od_premium = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["LPG Kit-TP"]))
                                        {
                                            $lpg_tp_premium = (float)($add['AddOnCoverTotalPremium']);
                                        }elseif(in_array($add['OptionalAddOnCoversName'], ["Geographical Extension - OD","Geographical Extension OD"]))
                                        {
                                            $geog_Extension_OD_Premium = (float)($add['AddOnCoverTotalPremium']);
                                        }elseif(in_array($add['OptionalAddOnCoversName'], ["Geographical Extension - TP","Geographical Extension TP"]))
                                        {
                                            $geog_Extension_TP_Premium = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["CNG Kit-OD"]))
                                        {
                                            $cng_od_premium = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                        elseif (in_array($add['OptionalAddOnCoversName'], ["CNG Kit-TP"]))
                                        {
                                            $cng_tp_premium = (float)($add['AddOnCoverTotalPremium']);
                                        }
                                    }
                                }
            
                                if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Discount']))
                                {
                                    $discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Discount'];
            
                                    foreach ($discount_array as $discount)
                                    {
                                        if ($discount['DiscountType'] == 'Anti-Theft Device - OD')
                                        {
                                            $antitheft = (float)($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Automobile Association Discount")
                                        {
                                            $automobile_discount = (float)($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Bonus Discount")
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
                                        elseif ($discount['DiscountType'] == "LPG Kit-OD - Detariff Discount on CNG or LPG Kit")
                                        {
                                            // $other_discount += (float)($discount['DiscountTypeAmount']);
                                            $bifuel_discount = (float)($discount['DiscountTypeAmount']);
                                        }
                                        elseif (in_array($discount['DiscountType'], ['CNG Kit-OD - Detariff Discount on CNG or LPG Kit']))
                                        {
                                            $bifuel_discount_cng = (float)($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Voluntary Excess Discount")
                                        {
                                            $voluntary_excess_discount = (float)($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Basic - TP - TPPD Discount")
                                        {
                                            $tppd_discount = (float)($discount['DiscountTypeAmount']);
                                        }
                                        elseif ($discount['DiscountType'] == "Detariff Discount")
                                        {
                                            $other_discount += (float)($discount['DiscountTypeAmount']);
                                        }
                                    }
                                }
                                if (isset($arr_premium['OutputResult']['PremiumBreakUp']['Loading'])) {
                                    $loadin_discount_array = $arr_premium['OutputResult']['PremiumBreakUp']['Loading'];
                                    foreach ($loadin_discount_array as $loading) {
                                        if ($loading['LoadingType'] == 'Built in CNG - OD loading - OD') {
                                            $lpg_od_premium = (float) $loading['LoadingTypeAmount'];
                                        } elseif ($loading['LoadingType'] == 'Built in CNG-TP Loading-TP') {
                                            $lpg_tp_premium = (float) $loading['LoadingTypeAmount'];
                                        }
                                    }
                                }

                                $lpg_od_premium -= $bifuel_discount;
                                $cng_od_premium -= $bifuel_discount_cng;
                                $electrical -= $electrical_discount;
                                $non_electrical -= $non_electrical_discount;

                                $final_total_discount = $ncb_discount + $antitheft + $tppd_discount + $voluntary_excess_discount + $other_discount;
                                $final_od_premium = $basic_od_premium - $final_total_discount + $geog_Extension_OD_Premium;
                                $final_tp_premium = $basic_tp_premium + $liabilities + $pa_unnamed + $pa_owner_driver + $lpg_tp_premium + $pa_paid_driver + $geog_Extension_TP_Premium + $cng_tp_premium;
                                $final_addon_amount = $Nil_dep + $return_to_invoice + $roadside_asst + $ncb_protction + $eng_protector + $key_replacement + $tyre_secure + $loss_of_personal_belongings + $electrical + $non_electrical + $lpg_od_premium + $cng_od_premium;
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
                                        'policy_end_date' => $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ? Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(1)->subDay(1)->format('d-m-Y') : ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ? Carbon::parse(str_replace('/', '-', $policy_start_date))->addYear(3)->subDay(1)->format('d-m-Y')  : str_replace('/', '-', $policy_end_date)),
                                        'tp_start_date' =>!empty($proposal->tp_start_date) ? date('d-m-Y',strtotime($proposal->tp_start_date)) :date('d-m-Y',strtotime(str_replace('/','-',$policy_start_date))),
                                        'tp_end_date' =>!empty($proposal->tp_end_date) ? date('d-m-Y',strtotime($proposal->tp_end_date)) :(($requestData->business_type == 'newbusiness') ? date('d-m-Y', strtotime('+3 year -1 day', strtotime(str_replace('/','-',$policy_start_date)))) : date('d-m-Y',strtotime(str_replace('/','-',$policy_end_date)))),
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
                                            'fuel_type' => $mmv_data->fuel,
                                            'seating_capacity' => $mmv_data->seating_capacity,
                                            'carrying_capacity' => $mmv_data->carrying_capacity,
                                            'cubic_capacity' => $mmv_data->cubic_capacity,
                                            'gross_vehicle_weight' => $mmv_data->gross_vehicle_weight ?? 1,
                                            'vehicle_type' => $mmv_data->veh_ob_type ?? '',
                                        ])
                                    ]);

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
                            'msg' => $arr_premium['ErrorText'] ?? 'Error in premium calculation service'
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
            }
            else
            {
                $data_response = array(
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'msg' => $token_data['ErrorText'] ?? 'Error occured in token generation service'
                );
            }
        }
        else
        {
            $data_response = array(
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'msg' => 'Error occured in token generation service'
            );
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