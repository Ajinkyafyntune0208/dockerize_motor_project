<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use App\Models\MasterRto;
use App\Models\MasterState;
function getRenewalQuoteIdv($enquiryId, $requestData, $productData)
{
    // vehicle age calculation
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
    $bike_age = ceil($age / 12);
    
    if (($bike_age > 5) && ($productData->zero_dep == '0'))
    {
        return [
            'premium_amount' => 0,
            'status'         => false,
            'message'        => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request'=> [
                'bike_age'      =>  $bike_age,
                'product_Data'  =>  $productData->zero_dep
            ]
        ];
    }
    include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
    $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
    if ($mmv['status'] == 1) 
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

    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } elseif ($mmv->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    }
    $mmv_data = [
        'manf_name'             => $mmv->manufacturer_name,
        'model_name'            => $mmv->vehiclemodel,
        'version_name'          => '',
        'seating_capacity'      => $mmv->seatingcapacity,
        'carrying_capacity'     => $mmv->carryingcapacity,
        'cubic_capacity'        => $mmv->cubiccapacity,
        'fuel_type'             => $mmv->fueltype,
        'gross_vehicle_weight'  => '',
        'vehicle_type'          => 'BIKE',
        'version_id'            => $mmv->ic_version_code,
    ];
    
    $additionData = [
        'requestMethod'     => 'post',
        'type'              => 'tokenGeneration',
        'section'           => 'BIKE',
        'productName'       => $productData->product_name,
        'enquiryId'         => $enquiryId,
        'transaction_type'  => 'quote'
    ];

    $tokenParam = [
        'grant_type'    => 'password',
        'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_BIKE'),
        'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_BIKE'),
        'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_BIKE'),
        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_BIKE'),
        'scope'         => 'esbmotor',
    ];

    $url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE');
    $get_response = getWsData($url, http_build_query($tokenParam), 'icici_lombard', $additionData);
    $token = $get_response['response'];
    if (!empty($token))
    {
        $token = json_decode($token, true);
        if(isset($token['access_token']))
        {
            $access_token = $token['access_token'];
        }
        else
        {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $token['error'] ?? "Issue in Token Generation service"
            ];
        }
        $IsPos = 'N';
        
        $premium_type_array = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->select('premium_type_code','premium_type')
            ->first();
        $premium_type = $premium_type_array->premium_type_code;
        $policy_type = $premium_type_array->premium_type;

        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
            $policy_type = 'Comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
            $policy_type = 'Third Party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
            $policy_type = 'Own Damage';
        }
        
        switch($premium_type)
        {
            case "comprehensive":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE');
            break;
        
            case "own_damage":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD');
            break;
        
            case "third_party":
                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP');
            break;
        }

        $businessType = '';
        switch ($requestData->business_type)
        {
            case 'newbusiness':
                $businessType = 'New Business';
            break;
            case 'rollover':
                $businessType = 'Roll Over';
            break;

            case 'breakin':
                $businessType = 'Break- In';
            break;

        }
               
        $corelationId = getUUID($enquiryId);
        $DealID = config('DEAL_ID_ICICI_LOMBARD_TESTING') == '' ? $deal_id : config('DEAL_ID_ICICI_LOMBARD_TESTING');
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $fetch_policy_data = [	
            "PolicyNumber"              => $proposal->previous_policy_number,
            "CorrelationId"             => $corelationId,
            "EngineNumberLast5Char"     => substr($proposal->engine_number,-5),
            "ChassisNumberLast5Char"    => substr($proposal->chassis_number,-5),
            "DealID"                    => $DealID,
            "TenureList"               => $premium_type == 'own_damage' ? [6] : [1]
        ];
        
        $additionPremData = [
            'requestMethod'     => 'post',
            'type'              => 'Fetch Policy Details',
            'section'           => 'BIKE',
            'productName'       => $productData->product_name,
            'enquiryId'         => $enquiryId,
            'transaction_type'  => 'quote',
            'token'             => $access_token
        ];
        $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_FETCH_POLICY_DATA');
        $get_response = getWsData($url, $fetch_policy_data, 'icici_lombard', $additionPremData);
        $data = $get_response['response'];
        
        $response  = json_decode($data,true);
        if(isset($response['status']) && $response['status'] == true && 
        $response['proposalDetails'][0]['isQuoteDeviation'] == false && 
        $response['proposalDetails'][0]['breakingFlag'] == false &&
        $response['proposalDetails'][0]['isApprovalRequired'] == false
        )
        {
            $proposalDetails        = $response['proposalDetails'][0]; 
            $previousPolicyDetails  = $response['previousPolicyDetails'];
            $vehicleDetails         = $response['vehicleDetails'];
            $riskDetails        = $proposalDetails['riskDetails'];
            $generalInformation = $proposalDetails['generalInformation'];            
            $registrationDate   = str_replace('/','-',$generalInformation['registrationDate']);
            $policy_start_date  = str_replace('/','-',$generalInformation['policyInceptionDate']);
            $policy_end_date    = str_replace('/','-',$generalInformation['policyEndDate']);

            ### => Token Service for IDV start
            $allow_idv_calculation = true;
            if($allow_idv_calculation)
            {
                $tokenParam ['scope'] = 'esbmotormodel';

                $token_for_idv_url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE');
                $token_for_idv = getWsData($token_for_idv_url, http_build_query($tokenParam), 'icici_lombard', $additionData);
                $token_for_idv = $token_for_idv['response'];
                if(!empty($token_for_idv))
                {
                    $token_for_idv = json_decode($token_for_idv, true);
                    if(isset($token_for_idv['access_token']))
                    {
                        $token_for_idv = $token_for_idv['access_token'];
                    }
                }
                else
                {
                    return
                    [
                        'webservice_id' => $get_response['webservice_id'],
                        'table'         => $get_response['table'],
                        'status'        => false,
                        'message'       => 'No response received from IDV service Token Generation'
                    ];
                }

                $idv_service_request =
                [
                    'manufacturercode'              => $mmv->manufacturer_code,
                    'BusinessType'                  => $businessType,
                    'rtolocationcode'               => $generalInformation['rtoLocationCode'],
                    'DeliveryOrRegistrationDate'    => date('Y-m-d', strtotime($registrationDate)),
                    'PolicyStartDate'               => date('Y-m-d', strtotime($policy_start_date)),
                    'DealID'                        => $DealID,
                    'vehiclemodelcode'              => $mmv->vehiclemodelcode,
                    'correlationId'                 => $corelationId,
                ];
                
                if($IsPos == 'Y')
                {
                    if(isset($idv_service_request['DealID']))
                    {
                        unset($idv_service_request['DealID']);
                    }
                }
                else
                {
                    if(!isset($idv_service_request['DealID']))
                    {
                        $idv_service_request['DealID'] = $DealID;
                    }
                }
                
                $additionPremData = [
                    'requestMethod'     => 'post',
                    'type'              => 'idvService',
                    'section'           => 'bike',
                    'productName'       => $productData->product_name,
                    'token'             => $token_for_idv,
                    'enquiryId'         => $enquiryId,
                    'transaction_type'  => 'quote',
                    'headers' => [
                        'Authorization'     => 'Bearer ' . $access_token
                    ]
                ];
                $idv_url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IDV_SERVICE_END_POINT_URL_MOTOR');
                $idv_response = getWsData($idv_url, $idv_service_request, 'icici_lombard', $additionPremData);
                $idv_response = $idv_response['response'];
                if(!empty($idv_response))
                {
                    $idv_service_response = json_decode($idv_response, true);
                    if(isset($idv_service_response['status']) && $idv_service_response['status'] == true)
                    {
                        $idvDepreciation = (1 - $idv_service_response['idvdepreciationpercent']);
                        if(isset($idv_service_response['maxidv']))
                        {
                            $max_idv = $idv_service_response['maxidv'];
                        }
                        if(isset($idv_service_response['minidv']))
                        {
                            $min_idv = $idv_service_response['minidv'];
                        }

                        if(isset($idv_service_response['minimumprice']))
                        {
                            $minimumprice = $idv_service_response['minimumprice'];
                        }
                        if(isset($idv_service_response['maximumprice']))
                        {
                            $maximumprice = $idv_service_response['maximumprice'];
                        }

                        $showroomPrice = ceil($minimumprice);
                        $idv = $min_idv;

                        if ($premium_type != 'third_party' && $requestData->is_idv_changed == 'Y') {
                            if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {
                                $showroomPrice = floor($maximumprice);
                                $idv = floor($max_idv);
                            } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {
                                $showroomPrice = floor($minimumprice);
                                $idv = ceil($min_idv);
                            } else {
                                $showroomPrice = round($requestData->edit_idv / $idvDepreciation);
                                $idv = $requestData->edit_idv;
                            }
                        }
                    }
                    else
                    {
                        return
                        [
                            'webservice_id'=> $get_response['webservice_id'],
                            'table'=> $get_response['table'],
                            'status'=> false,
                            'message'=> isset($idv_service_response['statusmessage']) ? $idv_service_response['statusmessage'] : 'Issue in IDV service'
                        ];
                    }
                }
                else
                {
                    return
                    [
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'status'=> false,
                        'message'=> 'No response received from IDV service'
                    ];
                }
                
                $RSAPlanName = '';               //valid upto 15 years
                $ZeroDepPlanName = !empty($riskDetails['zeroDepreciation']) ? (env('APP_ENV') == 'local' ? 'Silver TW' : 'Silver') : '';
                $IsEngineProtectPlus = !empty($riskDetails['engineProtect']) ? true : false;
                $KeyProtectPlan = !empty($riskDetails['keyProtect']) ? 'KP1' : '';

                if (!empty($riskDetails['roadSideAssistance'])) {
                    $rsa_amount = $riskDetails['roadSideAssistance'];
                    if ($rsa_amount == '199.0') {
                        $RSAPlanName = 'TW-199';
                    } elseif ($rsa_amount == '299.0') {
                        $RSAPlanName = 'TW-299';
                    } else {
                        $RSAPlanName = 'TW-199';
                    }
                }
                $LossOfPersonalBelongingPlanName = !empty($riskDetails['lossOfPersonalBelongings']) ? 'PLAN A' : '';
                $IsConsumables = !empty($riskDetails['consumables']) ? true : false;
                $IsRTIApplicableflag = !empty($riskDetails['returnToInvoice']) ? true : false;

                $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

                if($premium_type == 'third_party')
                {
                    $IsConsumables = false;
                    $IsRTIApplicableflag = false;
                    $IsEngineProtectPlus = false;
                    $LossOfPersonalBelongingPlanName = '';
                    $KeyProtectPlan = '';
                    $RSAPlanName = '';
                    $ZeroDepPlanName = '';
                }

                $paidDriver                 = $riskDetails['paidDriver'];
                $IsLLPaidDriver             = $paidDriver > 0 ? true : false;
                $paCoverForOwnerDriver      = $riskDetails['paCoverForOwnerDriver'];
                $ispacoverownerdriver       = $paCoverForOwnerDriver > 0 ? 'true' : 'false';

                $IsVehicleHaveCNG = 'false';
                $IsVehicleHaveLPG = 'false';
                $SIVehicleHaveLPG_CNG = 0;
                $SIHaveElectricalAccessories = 0;
                $SIHaveNonElectricalAccessories = 0;
                $IsPACoverUnnamedPassenger = 'false';
                $SIPACoverUnnamedPassenger = 0;

                if(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'CNG') {
                    $bifuel_type = 'CNG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = 0;
                }elseif(isset($mmv->fyntune_version['fuel_type']) && $mmv->fyntune_version['fuel_type'] == 'LPG') {
                    $bifuel_type = 'LPG';
                    $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                    $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                    $SIVehicleHaveLPG_CNG = 0;
                }

                if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
                    $accessories = ($selected_addons->accessories);
                    foreach ($accessories as $value) {
                        if($value['name'] == 'Electrical Accessories')
                        {
                            $SIHaveElectricalAccessories = $value['sumInsured'];
                        }
                        elseif($value['name'] == 'Non-Electrical Accessories')
                        {
                            $SIHaveNonElectricalAccessories = $value['sumInsured'];
                        }
                        elseif($value['name'] == 'External Bi-Fuel Kit CNG/LPG')
                        {
                            $bifuel_type = 'CNG';
                            $IsVehicleHaveCNG = ($bifuel_type == 'CNG') ? 'true' : 'false';
                            $IsVehicleHaveLPG = ($bifuel_type == 'LPG') ? 'true' : 'false';
                            $SIVehicleHaveLPG_CNG = $value['sumInsured'];
                        }
                    }
                }
                if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
                {
                    $additional_covers = $selected_addons->additional_covers;
                    foreach ($additional_covers as $value) {
                       if($value['name'] == 'Unnamed Passenger PA Cover')
                       {
                            $IsPACoverUnnamedPassenger = 'true';
                            $SIPACoverUnnamedPassenger = ($value['sumInsured'] * $mmv->seatingcapacity);

                       }
                    }
                }

                $voluntary_deductible_amount = 0;

                if($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '')
                {
                    $discounts_opted = $selected_addons->discounts;
                    foreach ($discounts_opted as $value) {
                       if($value['name'] == 'voluntary_insurer_discounts')
                       {
                           $voluntary_deductible_amount = $value['sumInsured'];
                       }
                    }
                }
                //Proposal Submit

                $rto_cities = MasterRto::where('rto_code', $requestData->rto_code)->first();
                $state_id = $rto_cities->state_id;
                $state_name = MasterState::where('state_id', $state_id)->first();
                $state_name = strtoupper($state_name->state_name);

                $proposal_submit_data = [
                    'PolicyNumber'                  => $previousPolicyDetails['previousPolicyNumber'],
                    'ProposalRefNo'                 => !empty($generalInformation['referenceProposalNo']) ? $generalInformation['referenceProposalNo'] : 0,
                    'CustomerID'                    => $generalInformation['customerId'],
                    'DealID'                        => $DealID,
                    'EngineNumberLast5Char'         => substr($vehicleDetails['engineNumber'],-5),
                    'ChassisNumberLast5Char'        => substr($vehicleDetails['chassisNumber'],-5),
                    'IsCustomerModified'            => false,
                    'CorrelationId'                 => $corelationId,
                    "TenureList"                    => $premium_type == 'own_damage' ? [6] : [1],
                    'ProposalDetails'               => [
                        'BusinessType'                  => $generalInformation['transactionType'],
                        'CustomerType'                  => $generalInformation['customerType'] ? 'INDIVIDUAL' : 'CORPORATE',
                        'PolicyStartDate'               => date('Y-m-d', strtotime($policy_start_date)),
                        'PolicyEndDate'                 => date('Y-m-d', strtotime($policy_end_date)),
                        'VehicleMakeCode'               => $vehicleDetails['vehicleMakeCode'],
                        'VehicleModelCode'              => $vehicleDetails['vehicleModelCode'],
                        'RTOLocationCode'               => $generalInformation['rtoLocationCode'],
                        'EngineNumber'                  => $vehicleDetails['engineNumber'],
                        'ChassisNumber'                 => $vehicleDetails['chassisNumber'],
                        'RegistrationNumber'            => $vehicleDetails['registrationNumber'],
                        'ManufacturingYear'             => $generalInformation['manufacturingYear'],
                        'DeliveryOrRegistrationDate'    => date('Y-m-d', strtotime($registrationDate)),
                        'FirstRegistrationDate'         => date('Y-m-d', strtotime($registrationDate)),
                        'ExShowRoomPrice'               => $showroomPrice,
                        'IsValidDrivingLicense'         => false,
                        'IsMoreThanOneVehicle'          => false,
                        'IsNoPrevInsurance'             => false,
                        'IsTransferOfNCB'               => false,
                        'TransferOfNCBPercent'          => 0,
                        'IsLegalLiabilityToPaidDriver'  => $IsLLPaidDriver ,
                        'IsPACoverOwnerDriver'          => $ispacoverownerdriver,
                        'isPACoverWaiver'               => ($ispacoverownerdriver == 'true') ? 'false' : 'true',
                        'PACoverTenure'                 => 1,
                        'Tenure'                        => 1,
                        'TPTenure'                      => 1,
                        "TPStartDate" => $proposalDetails['tpStartDate'] ?? null,
                        "TPEndDate" => $proposalDetails['tpEndDate'] ?? null,
                        "tpInsurerName" => $proposalDetails['tpInsurerName'] ?? null,
                        "TPPolicyNo" => $proposalDetails['tpPolicyNo'] ?? null,
                        'IsVehicleHaveLPG'              => $IsVehicleHaveLPG,
                        'IsVehicleHaveCNG'              => $IsVehicleHaveCNG,
                        'SIVehicleHaveLPG_CNG'          => $SIVehicleHaveLPG_CNG,
                        // 'TPPDLimit'                 => config('constants.ICICI_LOMBARD_TPPD_ENABLE')  == 'Y' ? $tppd_limit : 750000,
                        'TPPDLimit'                     => 100000, // As per git id 15093
                        'SIHaveElectricalAccessories'   => $SIHaveElectricalAccessories,
                        'SIHaveNonElectricalAccessories' => $SIHaveNonElectricalAccessories,
                        'IsPACoverUnnamedPassenger'     => $IsPACoverUnnamedPassenger,
                        'SIPACoverUnnamedPassenger'     => $SIPACoverUnnamedPassenger,
                        'IsFiberGlassFuelTank'          => false,
                        'IsVoluntaryDeductible'         => ($voluntary_deductible_amount != 0) ? false : false,
                        'VoluntaryDeductiblePlanName'   => ($voluntary_deductible_amount != 0) ? 0 : 0,
                        'IsAutomobileAssocnFlag'        => false,
                        'IsAntiTheftDisc'               => false,
                        'IsHandicapDisc'                => false,
                        'IsExtensionCountry'            => false,
                        'ExtensionCountryName'          => NULL,
                        'IsGarageCash'                  => false,
                        'GarageCashPlanName'            => NULL,
                        'ZeroDepPlanName'               => $ZeroDepPlanName,
                        'RSAPlanName'                   => $RSAPlanName,
                        'KeyProtectPlan'                => $KeyProtectPlan,
                        'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
                        'IsRTIApplicableflag'               => $IsRTIApplicableflag,
                        'IsEngineProtectPlus'               =>$IsEngineProtectPlus,
                        'IsConsumables'                     => $IsConsumables,
                        'OtherLoading'                      => 0,
                        'OtherDiscount'                     => 0,
                        'GSTToState'                        => $state_name,
                        'CorrelationId'                     => $corelationId,
                        'PreviousPolicyDetails'         => [
                            'previousPolicyStartDate'   => $previousPolicyDetails['previousPolicyStartDate'],//'2018-07-02',
                            'previousPolicyEndDate'     => $previousPolicyDetails['previousPolicyEndDate'],
                            'ClaimOnPreviousPolicy'     => 0,
                            'PreviousPolicyType'        => $previousPolicyDetails['previousPolicyType'],
                            'PreviousPolicyNumber'      => $previousPolicyDetails['previousPolicyNumber'],
                            'PreviousInsurerName'       => 'GIC',
                        ]
                    ]
                  ];
                $additionPremData = [
                    'requestMethod'     => 'post',
                    'type'              => 'Renewal Proposal Service',
                    'section'           => 'BIKE',
                    'token'             => $access_token,
                    'enquiryId'         => $enquiryId,
                    'transaction_type'  => 'quote',
                    'productName'       => $productData->product_name
                ];

                $url = config('constants.IcConstants.icici_lombard.END_POINT_URL_ICICI_LOMBARD_BIKE_PROPOSAL_RENEWAL_SUBMIT');
                $get_response = getWsData($url, $proposal_submit_data, 'icici_lombard', $additionPremData);
                $proposalServiceResponse = json_decode($get_response['response'],true);
                if(($proposalServiceResponse['status'] ?? '') != 'Success') {
                    return [
                        'status' => false,
                        'premium' => '0',
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => $proposalServiceResponse['message'] ?? 'Service Issue'
                    ];
                }
                $riskDetails        = $proposalServiceResponse['riskDetails'];
                $generalInformation = $proposalServiceResponse['generalInformation'];
                $proposalDetails    = $proposalServiceResponse;
            } else {
                $proposalDetails    = $response['proposalDetails'][0];
                $riskDetails        = $proposalDetails['riskDetails'];
                $generalInformation = $proposalDetails['generalInformation'];
            }
            $policy_start_date = str_replace('/','-',$generalInformation['policyInceptionDate']);
            $policy_end_date = str_replace('/','-',$generalInformation['policyEndDate']);
            
            $zeroDepreciation           = $riskDetails['zeroDepreciation'];
            $engineProtect              = $riskDetails['engineProtect'];
            $keyProtect                 = $riskDetails['keyProtect'];
            //$tyreProtect                = $riskDetails['tyreProtect'];
            $returnToInvoice            = $riskDetails['returnToInvoice'];
            $lossOfPersonalBelongings   = $riskDetails['lossOfPersonalBelongings'];
            $roadSideAssistance         = $riskDetails['roadSideAssistance'];
            $consumables                = $riskDetails['consumables'];
            
            $addons_list = [
                'zero_depreciation'     => round($zeroDepreciation),
                'engine_protector'      => round($engineProtect),
                'ncb_protection'        => 0,
                'key_replace'           => round($keyProtect),
                //'tyre_secure'           => round($tyreProtect),
                'return_to_invoice'     => round($returnToInvoice),
                'lopb'                  => round($lossOfPersonalBelongings),
                'consumables'           => round($consumables),
                'road_side_assistance'  => round($roadSideAssistance)
            ];
            if($productData->zero_dep == '1')
            {
               $addons_list['zero_depreciation'] = 0;
            }
            $in_bult = [];
            $additional = [];
            foreach ($addons_list as $key => $value)
            {
                if($value > 0)
                {
                  $in_bult[$key] =  $value;
                }
                else
                {
                  $additional[$key] =  $value;
                }
            }
            $addons_data = [
                'in_built'   => $in_bult,
                'additional' => $additional,
                'other'=>[]
            ];
            $applicable_addons = array_keys($in_bult);

            $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
            $paCoverForUnNamedPassengerAmount = 0;
            if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
            {
                $additional_covers = $selected_addons->additional_covers;
                foreach ($additional_covers as $value) {
                   if($value['name'] == 'Unnamed Passenger PA Cover')
                   {
                    $paCoverForUnNamedPassengerAmount = $riskDetails['paCoverForUnNamedPassenger'];

                   }
                }
            }
            
            $breakinLoadingAmount           = $riskDetails['breakinLoadingAmount'] ?? 0;
            $biFuelKitOD                    = $riskDetails['biFuelKitOD'] ?? 0;
            $biFuelKitTP                    = $riskDetails['biFuelKitTP']?? 0;
            $basicOD                        = $riskDetails['basicOD'];
            $geographicalExtensionOD        = $riskDetails['geographicalExtensionOD'];
            $electricalAccessories          = $riskDetails['electricalAccessories'];
            $nonElectricalAccessories       = $riskDetails['nonElectricalAccessories'];
            
            $voluntaryDiscount              = $riskDetails['voluntaryDiscount'];
            $antiTheftDiscount              = $riskDetails['antiTheftDiscount'];
            $automobileAssociationDiscount  = $riskDetails['automobileAssociationDiscount'];
            $basicTP                        = $riskDetails['basicTP'];
            
            $geographicalExtensionTP        = $riskDetails['geographicalExtensionTP'];
            $paCoverForUnNamedPassenger     = $paCoverForUnNamedPassengerAmount;
            //$paCoverForOwnerDriver          = $riskDetails['paCoverForOwnerDriver'];
            $tppD_Discount                  = $riskDetails['tppD_Discount'];
            $bonusDiscount                  = $riskDetails['bonusDiscount'];
            
            $idv                            = $generalInformation['depriciatedIDV'];
            
            $totalOwnDamagePremium          = $proposalDetails['totalOwnDamagePremium'];
            $totalLiabilityPremium          = $proposalDetails['totalLiabilityPremium'];
            $netPremium                     = $proposalDetails['packagePremium'];
            $totalTax                       = $proposalDetails['totalTax'];
            $finalPremium                   = $proposalDetails['finalPremium'];
            $final_od_premium = $basicOD + $electricalAccessories + $nonElectricalAccessories + $geographicalExtensionOD;   
            $final_total_discount = $voluntaryDiscount + $antiTheftDiscount + $automobileAssociationDiscount + $tppD_Discount + $bonusDiscount;
            $final_tp_premium = $basicTP + $biFuelKitTP + $paidDriver + $geographicalExtensionTP + $paCoverForUnNamedPassenger;
            $motor_manf_date = '01-'.$requestData->manufacture_year;
            
            $data = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'Data' => [
                    'isRenewal'                 => 'Y',
                    'quote_id'                  =>  $corelationId,
                    'idv'                       =>  round($idv),
                    'min_idv'                   =>  isset($min_idv) ? round($min_idv) : 0,
                    'max_idv'                   =>  isset($max_idv) ? round($max_idv) : 0,
                    'vehicle_idv'               =>  round($idv),
                    'showroom_price'            =>  isset($showroomPrice) ? round($showroomPrice) : round($idv),
                    'pp_enddate'                =>  $requestData->previous_policy_expiry_date,
                    'policy_type'               => $policy_type,
                    'cover_type'                => '1YC',
                    'vehicle_registration_no'   => $requestData->rto_code,
                    'voluntary_excess'          => (int) $voluntaryDiscount,
                    'version_id'                => $mmv->ic_version_code,
                    'fuel_type'                 => $mmv->fueltype,
                    'ncb_discount'              => (int) $requestData->applicable_ncb,
                    'company_name'              => $productData->company_name,
                    'company_logo'              => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name'              => $productData->product_sub_type_name,
                    'mmv_detail'                => $mmv_data,
                    'vehicle_register_date'     => $requestData->vehicle_register_date,
                    'master_policy_id'          => [
                        'policy_id'             => $productData->policy_id,
                        //'policy_no'           => $productData->policy_no,
                        //'policy_start_date'   => date('d-m-Y', strtotime($policy_start_date)),
                        //'policy_end_date'     => date('d-m-Y', strtotime($policy_end_date)),
                       // 'sum_insured'         => $productData->sum_insured,
                        //'corp_client_id'      => $productData->corp_client_id,
                        'product_sub_type_id'   => $productData->product_sub_type_id,
                        'insurance_company_id'  => $productData->company_id,
                        //'status'              => $productData->status,
                        //'corp_name'           => "Ola Cab",
                        'company_name'          => $productData->company_name,
                        'logo'                  => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount'         => $productData->default_discount,
                        //'predefine_series'    => "",
                        'is_premium_online'     => $productData->is_premium_online,
                        'is_proposal_online'    => $productData->is_proposal_online,
                        'is_payment_online'     => $productData->is_payment_online
                    ],
                    'motor_manf_date'           => $motor_manf_date,
                    'basic_premium'             => (int)$basicOD,
                    'deduction_of_ncb'          => (int)$bonusDiscount,
                    'tppd_premium_amount'       => (int)$basicTP,
                    'tppd_discount'             => (int)$tppD_Discount,
                    'total_loading_amount'      => $breakinLoadingAmount,
                    'motor_electric_accessories_value' => (int)$electricalAccessories,
                    'motor_non_electric_accessories_value' => (int)$nonElectricalAccessories,
                    'motor_lpg_cng_kit_value'   => (int)$biFuelKitOD,
                    'cover_unnamed_passenger_value' => (int)$paCoverForUnNamedPassenger,
                    'seating_capacity'          => $mmv->seatingcapacity,
                    'default_paid_driver'       => (int)$paidDriver,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver'  => (int)$paCoverForOwnerDriver,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage'          => (int)$totalOwnDamagePremium,
                    'cng_lpg_tp'                => (int)$biFuelKitTP,
                    'total_liability_premium'   => (int)$totalLiabilityPremium,
                    'net_premium'               => (int)$netPremium,
                    'service_tax_amount'        => (int)$totalTax,
                    'service_tax'               => 18,
                    'total_discount_od'         => 0,
                    'add_on_premium_total'      => 0,
                    'addon_premium'             => 0,
                    'vehicle_lpg_cng_kit_value' => (int)$requestData->bifuel_kit_value,
                    'premium_amount'            => (int)$finalPremium,
                    'antitheft_discount'        => (int)$antiTheftDiscount,
                    'final_od_premium'          => (int)$final_od_premium,
                    'final_tp_premium'          => (int)$final_tp_premium,
                    'final_total_discount'      => round($final_total_discount),
                    'final_net_premium'         => (int)$netPremium,
                    'final_gst_amount'          => (int)$totalTax,
                    'final_payable_amount'      => (int)$finalPremium,
                    'product_sub_type_id'       => $productData->product_sub_type_id,
                    'user_product_journey_id'   => $requestData->user_product_journey_id,
                    'business_type'             => $businessType,
                    'policyStartDate'           => date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate'             => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_of'                     => $productData->company_id,
                    'vehicle_in_90_days'        => NULL,
                    'vehicle_discount_detail'   => [
                        'discount_id'           => NULL,
                        'discount_rate'         => NULL
                    ],
                    'is_premium_online'         => $productData->is_premium_online,
                    'is_proposal_online'        => $productData->is_proposal_online,
                    'is_payment_online'         => $productData->is_payment_online,
                    'policy_id'                 => $productData->policy_id,
                    'insurane_company_id'       => $productData->company_id,
                    'add_ons_data'              => $addons_data,
                    'applicable_addons'         => $applicable_addons
                ]
            ];
            return camelCase($data);
        }
        else
        {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $response['message'] ?? 'Service Issue'
            ];
        }
        
    }
    else
    {
        return [
            'status' => false,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'message' => "Issue in Token Generation service"
        ];
    }
}