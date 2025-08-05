<?php

use App\Models\MasterRto;
use App\Models\MasterState;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

function getQuotePlanSeparate($enquiryId, $requestData, $productData)
{
    
    try {
        $isInspectionApplicable = 'N';
        $error_data = [];
        $premium_type_array = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->select('premium_type_code', 'premium_type')
            ->first();
        $premium_type = $premium_type_array->premium_type_code;
        $policy_type = $premium_type_array->premium_type;

        if ($requestData->ownership_changed == 'Y' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) {
            $isInspectionApplicable = 'Y';
        }

        if ($premium_type == 'breakin') {
            $premium_type = 'comprehensive';
            $policy_type = 'Comprehensive';
        }
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
            $policy_type = 'Third Party';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
            $policy_type = 'Own Damage';
        }

        $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv,
                ],
            ];
        }
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle Not Mapped',
                ],
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ],
            ];
        }

        // vehicle age calculation

        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = ceil($age / 12);

        $master_rto = MasterRto::where('rto_code', $requestData->rto_code)->first();
        if (empty($master_rto->icici_4w_location_code)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $requestData->rto_code . ' RTO Location Code Not Found',
                'request' => [
                    'rto_code' => $requestData->rto_code,
                ],
            ];
        }
        $state_name = MasterState::where('state_id', $master_rto->state_id)->first();
        $state_name = strtoupper($state_name->state_name);
        $rto_data = DB::table('car_icici_lombard_rto_location')
            ->where('txt_rto_location_code', $master_rto->icici_4w_location_code)
            ->first();

        if (empty($rto_data)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $requestData->rto_code . ' RTO Location Not Found',
                'request' => [
                    'rto_code' => $requestData->rto_code,
                ],
            ];
        } else {
            $txt_rto_location_code = $rto_data->txt_rto_location_code;
        }

        $mmv_data->manf_name = $mmv_data->manufacturer_name;
        //$mmv_data->version_name = $mmv_data->model_name;

        // token Generation

        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGeneration',
            'section' => 'car',
            'productName' => $productData->product_name,
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote',
        ];

        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope' => 'esbmotor',
        ];

        // If token API is not working then don't store it in cache - @Amit - 07-10-2022
        $token_cache_name = 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR.car.' . $enquiryId;
        $token_cache = Cache::get($token_cache_name);
        if (empty($token_cache)) {
            $token_response = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
            $token_decoded = json_decode($token_response['response'], true);
            if (isset($token_decoded['access_token'])) {
                update_quote_web_servicerequestresponse($token_response['table'], $token_response['webservice_id'], "Token Generation Success", "Success");
                $token = cache()->remember($token_cache_name, 60 * 45, function () use ($token_response) {
                    return $token_response;
                });
            } else {
                $error_data =  [
                    'webservice_id' => $token_response['webservice_id'],
                    'table' => $token_response['table'],
                    'status' => false,
                    'message' => "Insurer not reachable,Issue in Token Generation service",
                ];
            }
        } else {
            $token = $token_cache;
        }

        if (!empty($token)) {
            $token = json_decode($token['response'], true);

            if (isset($token['access_token'])) {
                $access_token = $token['access_token'];
            } else {
                $error_data =  [
                    'webservice_id' => $token_response['webservice_id'],
                    'table' => $token_response['table'],
                    'status' => false,
                    'message' => "Insurer not reachable,Issue in Token Generation service",
                ];
            }

            $corelationId = getUUID($enquiryId);
            $IsLLPaidDriver = false;
            $IsPAToUnnamedPassengerCovered = false;
            $PAToUnNamedPassenger_IsChecked = false;
            $IsElectricalItemFitted = false;
            $IsNonElectricalItemFitted = false;
            $bifuel = false;
            $tppd_limit = 750000;
            $breakingFlag = false;

            if ($requestData->business_type == 'newbusiness') {
                $BusinessType = 'New Business';
                $PolicyStartDate = date('Y-m-d');
                $IsPreviousClaim = 'N';
                $od_term_type = '13';
                $cpa = '1';
                $od_text = 'od_one_three';
                $applicable_ncb_rate = 0;
                $current_ncb_rate = 0;
            } else {
                if ($requestData->previous_policy_type == 'Not sure') {
                    $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));

                }
                $BusinessType = 'Roll Over';
                $PolicyStartDate = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                $breakin_days = get_date_diff('day', $requestData->previous_policy_expiry_date);
                if (($requestData->business_type == 'breakin')) {
                    // $PolicyStartDate = date('d-M-Y', strtotime('+3 day'));
                    $PolicyStartDate = date('d-M-Y');
                }
                if ($requestData->is_claim == 'N' && $premium_type != 'third_party') {
                    $applicable_ncb_rate = $requestData->applicable_ncb;
                    $current_ncb_rate = $requestData->previous_ncb;
                } else {
                    $applicable_ncb_rate = 0;
                    $current_ncb_rate = 0;
                }

                if ($requestData->is_claim == 'Y' && $premium_type != 'third_party') {
                    $current_ncb_rate = $requestData->previous_ncb;
                }

                if ($breakin_days > 90) {
                    $applicable_ncb_rate = 0;
                    $current_ncb_rate = 0;
                }
                $IsPreviousClaim = ($requestData->is_claim == 'Y') ? 'Y' : 'N';
                if ($requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party' ||
                    $requestData->business_type == 'breakin') {
                    $requestData->business_type = 'breakin';
                    $breakingFlag = true;
                }

            }

            $tenure_year = ($requestData->business_type == 'newbusiness') ? 3 : 1;
            $PolicyEndDate = date('Y-m-d', strtotime(date('Y-m-d', strtotime("+$tenure_year year -1 day", strtotime(strtr($PolicyStartDate, ['-' => '']))))));

            $first_reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));

            if ($requestData->previous_policy_expiry_date == '') {
                $prepolstartdate = '01/01/1900';
                $prepolicyenddate = '01/01/1900';
            } else {
                if ($requestData->previous_policy_type_identifier_code == '33') {
                    $prepolstartdate = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-3 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
                } else {
                    $prepolstartdate = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
                }

                $prepolicyenddate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            }

            $IRDALicenceNumber = '';
            $CertificateNumber = '';
            $PanCardNo = '';
            $AadhaarNo = '';
            $ProductCode = '';
            $IsPos = 'N';

            if ($IsPos == 'N') {
                switch ($premium_type) {
                    case "comprehensive":
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                        break;
                    case "own_damage":
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');

                        break;
                    case "third_party":
                        $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                        break;

                }
                if ($requestData->business_type == 'breakin' && $premium_type != 'third_party') {
                    $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_BREAKIN');
                }

            }

            $IsConsumables = false;
            $IsTyreProtect = false;
            $IsRTIApplicableflag = false;
            $IsEngineProtectPlus = false;
            $LossOfPersonalBelongingPlanName = '';
            $KeyProtectPlan = '';
            $RSAPlanName = '';
            $EMECover_Plane_name = '';
            $ZeroDepPlanName = '';

            global  $bundleCoversAddonsArray ;
            global $bundleCoversAddonsArrayAll ;
            global  $standAloneCoversAddonsArray ;
            global  $dependentCoversAddonsArray ;
            global  $allAddonsFromService ;
            global $allPackagesWithAddons;


            // $all_addons_packages = getAddonsService($enquiryId,$productData,$premium_type,$corelationId,$first_reg_date,$mmv_data,$rto_data,$deal_id,$PolicyStartDate);
                
            // foreach ( $allPackagesWithAddons as $package_key => $package_value) {

                foreach ($requestData->addons as $all_addon_key => $all_addon_value) {

                    if (strtolower($all_addon_value['planname']) == 'tariff') {
                        $all_addon_value['planname'] = true;
                    }

                    if(!empty($all_addon_value['dependentOn']))
                    {
                        // $dependentCoversAddonsArray = explode(',',$all_addon_value['dependentOn']);
                        $dependentCoversAddonsArray = preg_split('/[,&]/', $all_addon_value['dependentOn'], -1, PREG_SPLIT_NO_EMPTY);

                        foreach ($dependentCoversAddonsArray as $d_key => $d_value) 
                        {
                            $d_value = trim($d_value);
                            if (in_array($d_value, ['Zero Depreciation','ZeroDepreciation','Zero Depreciation(ZD)'])) {
                                $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                            }
    
                            
                            if (in_array($d_value, ['Consumable','Consumable(Tariff)','Consumable (Tariff)'])) {
                                $IsConsumables = true;
                            }
                            if (in_array($d_value, ['Engineprotectplus','EngineProtect(Tariff)'])) {
                                $IsEngineProtectPlus = true;
                            }
                            if (in_array($d_value, ['Tyreprotect','TyreProtect(Tariff)'])) {
                                $IsTyreProtect = true;
                            }
                            if (in_array($d_value, ['Roadside Assistance','RSA','RSA Plan(RSAwithKeyProtect)','RSA Plan(any plan)'])) {
                                if (strpos($all_addon_value['planname'], 'RSAwithKP') !== false || strpos($d_value, 'RSAwithKP') !== false || strpos($d_value, 'RSAwithKeyProtect') !== false) 
                                {
                                    $RSAPlanName = 'RSA-With Key Protect';

                                }else if (strpos($all_addon_value['planname'], 'any plan') !== false || strpos($d_value, 'any plan') !== false)
                                {
                                    $RSAPlanName = 'RSA-Plus';
                                }else
                                {
                                    $RSAPlanName = 'RSA-Plus';
    
                                }
                            }
                            if (in_array($d_value,['RetruntoInvoice'])) {
                                $IsRTIApplicableflag = $all_addon_value['planname'];
                            }
                            if (in_array($d_value,['Key Protect'])) {
                                $KeyProtectPlan = ($all_addon_value['dependentOn'] !== '') ? 'KP1' : '';
                            }
                            if (in_array($d_value,['Loss of Personal Belongings'])) {
                                $LossOfPersonalBelongingPlanName = 'PLAN A'; //$all_addon_value['planname'];
                            }
                        }
                        
                    }

                    if (in_array($all_addon_value['covername'],['Zero Depreciation','Zero Depreciation(ZD)'])) {
                        $ZeroDepPlanName = (env('APP_ENV') == 'local') ? 'Silver PVT' : 'ZD';
                    }

                    if (in_array($all_addon_value['covername'],['Consumable','Consumable (Tariff)'])) {
                        $IsConsumables = $all_addon_value['planname'];
                    }
                    if (in_array($all_addon_value['covername'],['Engineprotectplus','Engineprotect','EngineProtect(Tariff)'])) {
                        $IsEngineProtectPlus = $all_addon_value['planname'];
                    }
                    if (in_array($all_addon_value['covername'],['Tyreprotect','TyreProtect(Tariff)'])) {
                        $IsTyreProtect = true;
                    }
                    if (in_array($all_addon_value['covername'],['Roadside Assistance','Road Side Assistance'])) {
                        if (strpos($all_addon_value['planname'], 'RSAwithKP') !== false) 
                        {
                            $RSAPlanName = 'RSA-With Key Protect';
                        }else
                        {
                            $RSAPlanName = 'RSA-Plus';

                        }

                    }
                    if (in_array($all_addon_value['covername'],['RetruntoInvoice','ReturntoInvoice'])) {
                        $IsRTIApplicableflag = $all_addon_value['planname'];
                    }
                    if (in_array($all_addon_value['covername'],['Key Protect'])) {
                        $KeyProtectPlan = ($all_addon_value['dependentOn'] !== '') ? 'KP1' : 'KP1';
                    }
                    if (in_array($all_addon_value['covername'],['Loss of Personal Belongings'])) {
                        $LossOfPersonalBelongingPlanName = 'PLAN A'; //$all_addon_value['planname'];
                    }
                }

                if ($requestData->vehicle_owner_type == 'I') {
                    $customertype = 'INDIVIDUAL';
                    $ispacoverownerdriver = true;
                } else {
                    $customertype = 'CORPORATE';
                    $ispacoverownerdriver = false;
                }

                $ElectricalItemsTotalSI = $NonElectricalItemsTotalSI = $BiFuelKitSi = $PAToUnNamedPassengerSI = 0;

                $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();

                $IsPAToUnnamedPassengerCovered = false;
                $PAToUnNamedPassengerSI = 0;
                $IsElectricalItemFitted = false;
                $ElectricalItemsTotalSI = 0;
                $IsNonElectricalItemFitted = false;
                $NonElectricalItemsTotalSI = 0;
                $bifuel = false;
                $BiFuelKitSi = 0;
                $voluntary_deductible_amount = 0;
                $IsVehicleHaveLPG = false;
                $geoExtension = false;
                $extensionCountryName = '';

                if ($selected_addons && $selected_addons->accessories != null && $selected_addons->accessories != '') {
                    $accessories = ($selected_addons->accessories);
                    foreach ($accessories as $value) {

                        if ($value['name'] == 'Electrical Accessories') {
                            $IsElectricalItemFitted = true;
                            $ElectricalItemsTotalSI = $value['sumInsured'];
                        } else if ($value['name'] == 'Non-Electrical Accessories') {
                            $IsNonElectricalItemFitted = true;
                            $NonElectricalItemsTotalSI = $value['sumInsured'];
                        } else if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                            $type_of_fuel = '5';
                            $bifuel = true;
                            $Fueltype = 'CNG';
                            $IsVehicleHaveLPG = false;
                            $BiFuelKitSi = $value['sumInsured'];
                        }
                    }
                }

                if (isset($mmv_data->fyntune_version['fuel_type']) && $mmv_data->fyntune_version['fuel_type'] == 'CNG') {
                    $requestData->fuel_type = $mmv_data->fyntune_version['fuel_type'];
                    $bifuel = true;
                    $BiFuelKitSi = 0;
                    $IsVehicleHaveLPG = false;
                    $mmv_data->fuelType = $mmv_data->fyntune_version['fuel_type'];
                } else if (isset($mmv_data->fyntune_version['fuel_type']) && $mmv_data->fyntune_version['fuel_type'] == 'LPG') {
                    $requestData->fuel_type = $mmv_data->fyntune_version['fuel_type'];
                    $bifuel = false;
                    $BiFuelKitSi = 0;
                    $IsVehicleHaveLPG = true;
                    $mmv_data->fuelType = $mmv_data->fyntune_version['fuel_type'];
                }
                if ($selected_addons && $selected_addons->additional_covers != null && $selected_addons->additional_covers != '') {
                    $additional_covers = $selected_addons->additional_covers;
                    foreach ($additional_covers as $value) {

                        if ($value['name'] == 'Unnamed Passenger PA Cover') {
                            $IsPAToUnnamedPassengerCovered = true;
                            $PAToUnNamedPassenger_IsChecked = true;
                            $PAToUnNamedPassenger_NoOfItems = '1';
                            $PAToUnNamedPassengerSI = $value['sumInsured'];
                        }
                        if ($value['name'] == 'LL paid driver') {
                            $IsLLPaidDriver = true;
                        }
                        if ($value['name'] == 'Geographical Extension') {
                            $geoExtension = true;
                            $geoExtensionCountryName = array_filter($value['countries'], fn($country) => $country !== false);
                            $extensionCountryName = !empty($geoExtensionCountryName) ? implode(', ', $geoExtensionCountryName) : 'No Extension';
                        }
                    }
                }

                if ($selected_addons && $selected_addons->discounts != null && $selected_addons->discounts != '') {
                    $discounts_opted = $selected_addons->discounts;

                    foreach ($discounts_opted as $value) {

                        if ($value['name'] == 'TPPD Cover') {
                            $tppd_limit = 6000;
                        }
                        if ($value['name'] == 'voluntary_insurer_discounts') {
                            $voluntary_deductible_amount = $value['sumInsured'];
                        }
                    }
                }

                if ($premium_type == 'own_damage') {
                    $tppd_limit = 750000;
                    $ispacoverownerdriver = false;
                    $IsLLPaidDriver = false;
                    $IsPAToUnnamedPassengerCovered = false;
                    $PAToUnNamedPassengerSI = 0;
                }

                if (($requestData->business_type == 'breakin') && config('constants.IcConstants.icici_lombard.IS_TYRE_PROTECT_DISABLED_FOR_BREAKIN') == 'Y') {
                    $IsTyreProtect = false;
                }
                if (config('constants.IcConstants.icici_lombard.IS_EME_COVER_DISABLED_FOR_ICICI') == 'N') {
                    $EMECover_Plane_name = '';
                }

                $model_config_premium =
                    [
                    'BusinessType' => $requestData->ownership_changed == 'Y' ? 'Used' : $BusinessType,
                    'CustomerType' => $customertype,
                    'PolicyStartDate' => date('Y-m-d', strtotime($PolicyStartDate)),
                    'PolicyEndDate' => $PolicyEndDate,
                    'VehicleMakeCode' => $mmv_data->manufacturer_code,
                    'VehicleModelCode' => $mmv_data->model_code,
                    'RTOLocationCode' => $rto_data->txt_rto_location_code,
                    'ManufacturingYear' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    'DeliveryOrRegistrationDate' => $first_reg_date,
                    'FirstRegistrationDate' => $first_reg_date,
                    'ExShowRoomPrice' => 0,
                    'Tenure' => '1',
                    'TPTenure' => ($requestData->business_type == 'newbusiness') ? '3' : '1',
                    'IsValidDrivingLicense' => false,
                    'IsMoreThanOneVehicle' => false,
                    'IsNoPrevInsurance' => ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' || $requestData->previous_policy_type == 'Third-party') ? true : false,
                    'IsTransferOfNCB' => false,
                    'TransferOfNCBPercent' => 0,
                    'IsLegalLiabilityToPaidDriver' => $IsLLPaidDriver,
                    'IsPACoverOwnerDriver' => $ispacoverownerdriver,
                    "isPACoverWaiver" => false, //PACoverWaiver should be true in case already having PACover i.e PAOwner is false
                    "PACoverTenure" => 1,
                    'IsVehicleHaveLPG' => $IsVehicleHaveLPG,
                    'IsVehicleHaveCNG' => $bifuel,
                    'SIVehicleHaveLPG_CNG' => $BiFuelKitSi,
                    'TPPDLimit' => config('constants.ICICI_LOMBARD_TPPD_ENABLE') == 'Y' ? $tppd_limit : 750000,
                    'SIHaveElectricalAccessories' => $ElectricalItemsTotalSI,
                    'SIHaveNonElectricalAccessories' => $NonElectricalItemsTotalSI,
                    'IsPACoverUnnamedPassenger' => $IsPAToUnnamedPassengerCovered,
                    'SIPACoverUnnamedPassenger' => $PAToUnNamedPassengerSI * ($mmv_data->seating_capacity),
                    'IsLegalLiabilityToPaidEmployee' => false,
                    'NoOfEmployee' => 0,
                    'IsLegaLiabilityToWorkmen' => false,
                    'NoOfWorkmen' => 0,
                    'IsFiberGlassFuelTank' => false,
                    'IsVoluntaryDeductible' => ($voluntary_deductible_amount != 0) ? false : false,
                    'VoluntaryDeductiblePlanName' => ($voluntary_deductible_amount != 0) ? 0 : 0,
                    'IsAutomobileAssocnFlag' => false,
                    'IsAntiTheftDisc' => false,
                    'IsHandicapDisc' => false,
                    'IsExtensionCountry' => $geoExtension,
                    'ExtensionCountryName' => $extensionCountryName,
                    'IsGarageCash' => false,
                    'GarageCashPlanName' => 4,
                    'ZeroDepPlanName' => $ZeroDepPlanName,
                    'RSAPlanName' => $RSAPlanName,
                    'IsEngineProtectPlus' => $IsEngineProtectPlus,
                    'IsConsumables' => $IsConsumables,
                    'IsTyreProtect' => $IsTyreProtect,
                    'KeyProtectPlan' => $KeyProtectPlan,
                    'LossOfPersonalBelongingPlanName' => $LossOfPersonalBelongingPlanName,
                    'IsRTIApplicableflag' => $IsRTIApplicableflag,
                    'EMECover' => $EMECover_Plane_name,
                    'NoOfPassengerHC' => $mmv_data->seating_capacity - 1,
                    'IsApprovalRequired' => false,
                    'ProposalStatus' => null,
                    'OtherLoading' => 0,
                    'OtherDiscount' => 0,
                    'GSTToState' => $state_name,
                    'CorrelationId' => getUUID($enquiryId),
                ];

                $IsPos = 'N';
                $is_icici_pos_disabled_renewbuy = config('constants.motorConstant.IS_ICICI_POS_DISABLED_RENEWBUY');
                $is_pos_enabled = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.motorConstant.IS_POS_ENABLED');
                $is_employee_enabled = config('constants.motorConstant.IS_EMPLOYEE_ENABLED');
                $pos_testing_mode = ($is_icici_pos_disabled_renewbuy == 'Y') ? 'N' : config('constants.posTesting.IS_POS_TESTING_MODE_ENABLE');
                $IRDALicenceNumber = $CertificateNumber = $PanCardNo = $AadhaarNo = $ProductCode = '';
                $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type', 'P')
                    ->first();

                if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
                    if ($pos_data) {
                        $IsPos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                        $CertificateNumber = $pos_data->unique_number; #$pos_data->user_name;
                        $PanCardNo = $pos_data->pan_no;
                        $AadhaarNo = $pos_data->aadhar_no;
                    }

                    if ($pos_testing_mode === 'Y') {
                        $IsPos = 'Y';
                        $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                        $CertificateNumber = 'AB000001';
                        $PanCardNo = 'ATAPK3554C';
                        $AadhaarNo = '689505607468';
                    }

                    $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');

                } elseif ($pos_testing_mode === 'Y') {
                    $IsPos = 'Y';
                    $IRDALicenceNumber = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR');
                    $CertificateNumber = 'AB000001';
                    $PanCardNo = 'ATAPK3554C';
                    $AadhaarNo = '689505607468';
                    $ProductCode = config('constants.IcConstants.icici_lombard.PRODUCT_CODE_ICICI_LOMBARD_MOTOR');
                } else {
                    $model_config_premium['DealId'] = $deal_id;
                }

                if ($IsPos == 'Y') {
                    if (isset($model_config_premium['DealId'])) {
                        unset($model_config_premium['DealId']);
                    }
                } else {
                    if (!isset($model_config_premium['DealId'])) {
                        $model_config_premium['DealId'] = $deal_id;
                    }
                }

                if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == 'own_damage') {
                    if (($requestData->previous_policy_type == 'Third-party')) {
                        $model_config_premium['IsNoPrevInsurance'] = false;
                    }
                    $model_config_premium['PreviousPolicyDetails'] = [
                        'previousPolicyStartDate' => $prepolstartdate,
                        'previousPolicyEndDate' => $prepolicyenddate,
                        'ClaimOnPreviousPolicy' => ($IsPreviousClaim == 'Y') ? true : false,
                        'PreviousPolicyType' => ($requestData->previous_policy_type == 'Third-party') ? 'TP' : 'Comprehensive Package',
                        'TotalNoOfODClaims' => ($IsPreviousClaim == 'Y') ? '1' : '0',
                        'NoOfClaimsOnPreviousPolicy' => ($IsPreviousClaim == 'Y') ? '1' : '0',
                        'BonusOnPreviousPolicy' => $requestData->previous_policy_type == 'Third-party' ? 0 : $current_ncb_rate,
                    ];
                } else {
                    $model_config_premium['Tenure'] = 1;
                    $model_config_premium['TPTenure'] = 3;
                    //$model_config_premium['PACoverTenure']= 3;
                    $model_config_premium['PACoverTenure'] = 1; // By default CPA tenure will be 1 Year
                }

                if ($premium_type == 'own_damage') {
                    $model_config_premium['TPStartDate'] = $prepolstartdate;
                    $model_config_premium['TPEndDate'] = date('Y-m-d', strtotime('+3 year -1 day', strtotime($prepolstartdate)));
                    $model_config_premium['TPInsurerName'] = 'GIC'; #'BAJAJALLIANZ';
                    $model_config_premium['TPPolicyNo'] = '123456789';
                    $model_config_premium['Tenure'] = 1;
                    $model_config_premium['TPTenure'] = 0;
                    $model_config_premium['PreviousPolicyDetails']['PreviousPolicyType'] = 'Bundled Package Policy';
                    $model_config_premium['IsLegalLiabilityToPaidDriver'] = false;
                    $model_config_premium['IsPACoverOwnerDriver'] = false;
                    $model_config_premium['IsPACoverUnnamedPassenger'] = false;
                }

                if (($premium_type == 'own_damage') && $requestData->previous_policy_type == 'Third-party') {
                    unset($model_config_premium['PreviousPolicyDetails']);
                }
                if ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') {
                    unset($model_config_premium['PreviousPolicyDetails']);
                }

                $enable_idv_service = config('constants.ICICI_LOMBARD.ENABLE_ICICI_IDV_SERVICE');

                $idv = $max_idv = $min_idv = $minimumprice = $maximumprice = 0;
                if ($premium_type != 'third_party' && $enable_idv_service == 'Y') {
                    $access_token_for_idv = '';

                    $tokenParam =
                        [
                        'grant_type' => 'password',
                        'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
                        'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
                        'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
                        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
                        'scope' => 'esbmotormodel',
                    ];

                    $token = cache()->remember('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR_scope', 60 * 45, function () use ($tokenParam, $additionData) {
                        return getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
                    });
                    if (!empty($token['response'])) {
                        $token_data = json_decode($token['response'], true);

                        if (isset($token_data['access_token'])) {
                            update_quote_web_servicerequestresponse($token['table'], $token['webservice_id'], "Token Generation SuccessFully...!", "Success");
                            $access_token_for_idv = $token_data['access_token'];
                        }
                    } else {
                        $error_data = 
                            [
                            'webservice_id' => $token['webservice_id'],
                            'table' => $token['table'],
                            'status' => false,
                            'message' => 'No response received from IDV service Token Generation',
                        ];
                    }

                    $idv_service_request =
                        [
                        'manufacturercode' => $mmv_data->manufacturer_code,
                        'BusinessType' => $BusinessType,
                        'rtolocationcode' => $rto_data->txt_rto_location_code,
                        'DeliveryOrRegistrationDate' => $first_reg_date,
                        'PolicyStartDate' => date('Y-m-d', strtotime($PolicyStartDate)),
                        'DealID' => $deal_id,
                        'vehiclemodelcode' => $mmv_data->model_code,
                        'correlationId' => $model_config_premium['CorrelationId'],
                    ];

                    if ($IsPos == 'Y') {
                        if (isset($idv_service_request['DealID'])) {
                            unset($idv_service_request['DealID']);
                        }
                    } else {
                        if (!isset($idv_service_request['DealID'])) {
                            $idv_service_request['DealID'] = $deal_id;
                        }
                    }

                    $additionPremData = [
                        'requestMethod' => 'post',
                        'type' => 'idvService',
                        'section' => 'car',
                        'productName' => $productData->product_name ,
                        'token' => $access_token_for_idv,
                        'enquiryId' => $enquiryId,
                        'transaction_type' => 'quote',
                    ];

                    if ($IsPos == 'Y') {
                        $pos_details = [
                            'pos_details' => [
                                'IRDALicenceNumber' => $IRDALicenceNumber,
                                'CertificateNumber' => $CertificateNumber,
                                'PanCardNo' => $PanCardNo,
                                'AadhaarNo' => $AadhaarNo,
                                'ProductCode' => $ProductCode,
                            ],
                        ];
                        $additionPremData = array_merge($additionPremData, $pos_details);
                    }

                    $url = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IDV_SERVICE_END_POINT_URL_MOTOR');
                    $data = getWsData($url, $idv_service_request, 'icici_lombard', $additionPremData);

                    if (!empty($data['response'])) {
                        $idv_service_response = json_decode($data['response'], true);
                        if (isset($idv_service_response['status']) && $idv_service_response['status'] == true) {
                            update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success");
                            $idvDepreciation = (1 - $idv_service_response['idvdepreciationpercent']);
                            if (isset($idv_service_response['maxidv'])) {
                                $max_idv = $idv_service_response['maxidv'];
                            }
                            if (isset($idv_service_response['minidv'])) {
                                $min_idv = $idv_service_response['minidv'];
                            }
                            if (isset($idv_service_response['minimumprice'])) {
                                $minimumprice = $idv_service_response['minimumprice'];
                            }
                            if (isset($idv_service_response['maximumprice'])) {
                                $maximumprice = $idv_service_response['maximumprice'];
                            }

                            $model_config_premium['ExShowRoomPrice'] = ceil($minimumprice);
                        } else {
                            $error_data =
                                [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'status' => false,
                                'message' => isset($idv_service_response['statusmessage']) ? $idv_service_response['statusmessage'] : 'Issue in IDV service',
                            ];
                        }

                    } else {
                        $error_data =
                            [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'status' => false,
                            'message' => 'No response received from IDV service',
                        ];
                    }
                    // idv service end

                } else {
                    $model_config_premium['ExShowRoomPrice'] = 0;
                }

                $additionPremData = [
                    'requestMethod' => 'post',
                    'type' => 'premiumCalculation',
                    'section' => 'car',
                    'productName' => $productData->product_name . " premiumCalculation ",
                    'token' => $access_token,
                    'enquiryId' => $enquiryId,
                    'transaction_type' => 'quote',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                    ],
                ];

                if ($IsPos == 'Y') {
                    $pos_details = [
                        'pos_details' => [
                            'IRDALicenceNumber' => $IRDALicenceNumber,
                            'CertificateNumber' => $CertificateNumber,
                            'PanCardNo' => $PanCardNo,
                            'AadhaarNo' => $AadhaarNo,
                            'ProductCode' => $ProductCode,
                        ],
                    ];
                    $additionPremData = array_merge($additionPremData, $pos_details);
                }

                if ($premium_type == 'third_party') {
                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR_TP');
                } else {

                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR');
                }

                $data = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);

                #offline idv calculation
                if ($enable_idv_service != 'Y' && $requestData->is_idv_changed != 'Y' && $premium_type != 'third_party') {
                    if ($data['response']) {
                        $dataResponse = json_decode($data['response'], true);

                        if (isset($dataResponse['status']) && $dataResponse['status'] == 'Success') {

                            $idvDepreciation = (1 - ($dataResponse['generalInformation']['percentageOfDepriciation'] / 100));
                            $offline_idv = (int) round($dataResponse['generalInformation']['depriciatedIDV']);
                            $idv_data = get_ic_min_max($offline_idv, 0.95, 1.05, 0, 0, 0);
                            $min_idv = $idv_data->min_idv;
                            $max_idv = $idv_data->max_idv;

                            $VehiclebodyPrice = ceil($min_idv / $idvDepreciation);

                            $model_config_premium['ExShowRoomPrice'] = $VehiclebodyPrice;
                            $additionPremData['productName'] = $productData->product_name . " premiumCalculationidv ";
                            $data = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);

                        } else {
                            $error_data = [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'status' => false,
                                'message' => isset($dataResponse['message']) ? $dataResponse['message'] : "Insurer not reachable",
                            ];
                        }
                    }
                }

                if (!empty($data['response'])) {
                    $arr_premium = json_decode($data['response'], true);

                    if (isset($arr_premium['status']) && strtolower($arr_premium['status']) == 'success') {
                        update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success");
                        $idv = round($arr_premium['generalInformation']['depriciatedIDV']);
                        if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true)) {
                            $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
                            $error_data =  [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'status' => false,
                                'message' => $msg,
                            ];

                        }

                        if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true)) {
                            $msg = "Proposal application didn't pass underwriter approval";
                            $error_data = [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'status' => false,
                                'message' => $msg,
                            ];
                        }

                        if ($premium_type != 'third_party') {

                            // idv change condition
                            if ($requestData->is_idv_changed == 'Y') {

                                if ($enable_idv_service != 'Y') {
                                    $offline_idv = (int) round($arr_premium['generalInformation']['depriciatedIDV']);
                                    $idv_data = get_ic_min_max($offline_idv, 0.95, 1.05, 0, 0, 0);
                                    $min_idv = $idv_data->min_idv;
                                    $max_idv = $idv_data->max_idv;

                                    $idvDepreciation = (1 - ($arr_premium['generalInformation']['percentageOfDepriciation'] / 100));
                                    $maximumprice = floor($max_idv / $idvDepreciation);
                                    $minimumprice = ceil($min_idv / $idvDepreciation);

                                }

                                if ($max_idv != "" && $requestData->edit_idv >= floor($max_idv)) {

                                    $model_config_premium['ExShowRoomPrice'] = floor($maximumprice);
                                    $idv = floor($max_idv);

                                } elseif ($min_idv != "" && $requestData->edit_idv <= ceil($min_idv)) {

                                    $model_config_premium['ExShowRoomPrice'] = floor($minimumprice);
                                    $idv = ceil($min_idv);
                                } else {
                                    $model_config_premium['ExShowRoomPrice'] = round($requestData->edit_idv / $idvDepreciation);
                                    $idv = $requestData->edit_idv;
                                }

                                if ($premium_type == 'third_party') {
                                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR_TP');
                                } else {

                                    $url = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_END_POINT_URL_ICICI_LOMBARD_MOTOR');
                                }

                                $additionPremData = [
                                    'requestMethod' => 'post',
                                    'type' => 'premiumRecalculation',
                                    'section' => 'car',
                                    'productName' => $productData->product_name . "premiumCalculationIdvChange ",
                                    'token' => $access_token,
                                    'enquiryId' => $enquiryId,
                                    'transaction_type' => 'quote',
                                ];

                                if ($IsPos == 'Y') {
                                    if (isset($model_config_premium['DealId'])) {
                                        unset($model_config_premium['DealId']);
                                    }
                                } else {
                                    if (!isset($model_config_premium['DealId'])) {
                                        $model_config_premium['DealId'] = $deal_id;
                                    }
                                }

                                if ($IsPos == 'Y') {
                                    $pos_details = [
                                        'pos_details' => [
                                            'IRDALicenceNumber' => $IRDALicenceNumber,
                                            'CertificateNumber' => $CertificateNumber,
                                            'PanCardNo' => $PanCardNo,
                                            'AadhaarNo' => $AadhaarNo,
                                            'ProductCode' => $ProductCode,
                                        ],
                                    ];
                                    $additionPremData = array_merge($additionPremData, $pos_details);
                                }

                                $data = getWsData($url, $model_config_premium, 'icici_lombard', $additionPremData);

                                if ($data['response']) {
                                    $arr_premium = json_decode($data['response'], true);
                                    if (isset($arr_premium['status']) && strtolower($arr_premium['status']) == 'success') {
                                        update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success");
                                        if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true)) {
                                            $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
                                            $error_data = [
                                                'webservice_id' => $data['webservice_id'],
                                                'table' => $data['table'],
                                                'status' => false,
                                                'message' => $msg,
                                            ];

                                        }

                                        if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true)) {
                                            $msg = "Proposal application didn't pass underwriter approval";
                                            $error_data =  [
                                                'webservice_id' => $data['webservice_id'],
                                                'table' => $data['table'],
                                                'status' => false,
                                                'message' => $msg,
                                            ];
                                        }
                                        $idv = round($arr_premium['generalInformation']['depriciatedIDV']);

                                    } else {
                                        $error_data = [
                                            'webservice_id' => $data['webservice_id'],
                                            'table' => $data['table'],
                                            'status' => false,
                                            'message' => isset($arr_premium['message']) ? $arr_premium['message'] : 'Insurer not reachable',

                                        ];

                                    }
                                }
                            }
                        } else {
                            $idv = $min_idv = $max_idv = 0;
                        }

                        if (in_array($premium_type, ['third_party', 'third_party_breakin'])) {
                            return thirdPartyResponse(
                                $data,
                                $arr_premium, $premium_type,
                                $requestData,
                                $productData,
                                $policy_type,
                                $mmv_data,
                                $model_config_premium,
                                $applicable_ncb_rate,
                                $PolicyStartDate,
                                $PolicyEndDate,
                                $car_age,
                                $isInspectionApplicable,
                                $bifuel,
                                $IsVehicleHaveLPG,
                            );
                        }

                        $od_premium = 0;
                        $breakingLoadingAmt = 0;
                        $automobile_assoc = 0;
                        $anti_theft = 0;
                        $voluntary_deductible = 0;
                        $elect_acc = 0;
                        $non_elec_acc = 0;
                        $lpg_cng_od = 0;
                        $lpg_cng_tp = 0;
                        $tp_premium = 0;
                        $llpd_amt = 0;
                        $ncb_discount = 0;
                        $unnamed_pa_amt = 0;
                        $zero_dept = 0;
                        $tppd_discount = 0;
                        $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $tyreSecure = 0;
                        $geog_Extension_OD_Premium = 0;
                        $geog_Extension_TP_Premium = $emeCover = 0;

                        $geog_Extension_OD_Premium = isset($arr_premium['riskDetails']['geographicalExtensionOD'])  ? ($arr_premium['riskDetails']['geographicalExtensionOD']) : '0';
                        $geog_Extension_TP_Premium = isset($arr_premium['riskDetails']['geographicalExtensionTP'])  ? ($arr_premium['riskDetails']['geographicalExtensionTP']) : '0';
                        $od_premium = isset($arr_premium['riskDetails']['basicOD']) ? round($arr_premium['riskDetails']['basicOD']) : '0';
                        $breakingLoadingAmt = isset($arr_premium['riskDetails']['breakinLoadingAmount']) ? $arr_premium['riskDetails']['breakinLoadingAmount'] : '0';
                        $automobile_assoc = isset($arr_premium['riskDetails']['automobileAssociationDiscount']) ? round($arr_premium['riskDetails']['automobileAssociationDiscount']) : '0';
                        $anti_theft = isset($arr_premium['riskDetails']['antiTheftDiscount']) ? round($arr_premium['riskDetails']['antiTheftDiscount']) : '0';
                        $elect_acc = isset($arr_premium['riskDetails']['electricalAccessories']) ? round($arr_premium['riskDetails']['electricalAccessories']) : '0';
                        $non_elec_acc = isset($arr_premium['riskDetails']['nonElectricalAccessories']) ? round($arr_premium['riskDetails']['nonElectricalAccessories']) : '0';
                        $lpg_cng_od = isset($arr_premium['riskDetails']['biFuelKitOD']) ? round($arr_premium['riskDetails']['biFuelKitOD']) : '0';
                        $ncb_discount = isset($arr_premium['riskDetails']['bonusDiscount']) ? $arr_premium['riskDetails']['bonusDiscount'] : '0';
                        $tppd_discount = isset($arr_premium['riskDetails']['tppD_Discount']) ? $arr_premium['riskDetails']['tppD_Discount'] : '0';

                        $tp_premium = round($arr_premium['riskDetails']['basicTP']);
                        $lpg_cng_tp = isset($arr_premium['riskDetails']['biFuelKitTP']) ? round($arr_premium['riskDetails']['biFuelKitTP']) : '0';
                        $llpd_amt = isset($arr_premium['riskDetails']['paidDriver']) ? round($arr_premium['riskDetails']['paidDriver']) : 0;
                        $unnamed_pa_amt = isset($arr_premium['riskDetails']['paCoverForUnNamedPassenger']) ? $arr_premium['riskDetails']['paCoverForUnNamedPassenger'] : '0';
                        $rsa = isset($arr_premium['riskDetails']['roadSideAssistance']) ? $arr_premium['riskDetails']['roadSideAssistance'] : '0';
                        $zero_dept = isset($arr_premium['riskDetails']['zeroDepreciation']) ? $arr_premium['riskDetails']['zeroDepreciation'] : '0';
                        $eng_protect = isset($arr_premium['riskDetails']['engineProtect']) ? $arr_premium['riskDetails']['engineProtect'] : '0';
                        $key_replace = isset($arr_premium['riskDetails']['keyProtect']) ? $arr_premium['riskDetails']['keyProtect'] : '0';
                        $consumable_cover = isset($arr_premium['riskDetails']['consumables']) ? $arr_premium['riskDetails']['consumables'] : '0';
                        $return_to_invoice = isset($arr_premium['riskDetails']['returnToInvoice']) ? $arr_premium['riskDetails']['returnToInvoice'] : '0';
                        $loss_belongings = isset($arr_premium['riskDetails']['lossOfPersonalBelongings']) ? $arr_premium['riskDetails']['lossOfPersonalBelongings'] : '0';
                        $cpa_cover = isset($arr_premium['riskDetails']['paCoverForOwnerDriver']) ? $arr_premium['riskDetails']['paCoverForOwnerDriver'] : '0';
                        $tyreSecure = $arr_premium['riskDetails']['tyreProtect'] ?? 0;
                        $emeCover = $arr_premium['riskDetails']['emeCover'] ?? 0;

                        if (isset($arr_premium['riskDetails']['voluntaryDiscount'])) {
                            $voluntary_deductible = $arr_premium['riskDetails']['voluntaryDiscount'];
                        } else {
                            $voluntary_deductible = voluntary_deductible_calculation($od_premium, $requestData->voluntary_excess_value, 'car');
                        }

                        $add_ons_data = [
                            'in_built' => [
                            ],
                            'additional' => [],
                            'other' => [],
                        ];

                        // if ($package_key == 'standard') {
                        //     $add_ons_data = [
                        //         'in_built' => [
                        //         ],
                        //         'additional' => [
                        //             'zeroDepreciation' => (int) $zero_dept,
                        //             'consumables' => (int) $consumable_cover,
                        //             'keyReplace' => (int) $key_replace,
                        //             'tyreSecure' => (int) $tyreSecure,
                        //             'roadSideAssistance' => (int) $rsa,
                        //             'engineProtector' => (int) $eng_protect,
                        //             'ncbProtection' => 0,
                        //             'returnToInvoice' => (int) $return_to_invoice,
                        //             'lopb' => (int) $loss_belongings,
                        //             //'cpa_cover'                   => $cpa_cover,
                        //         ],
                        //         'other' => [],
                        //     ];
                        // } else {

                            $add_ons_data = [
                                'in_built' => [
                                ],
                                'additional' => [],
                                'other' => [],
                            ];

                            if ($zero_dept > 0) {
                                $add_ons_data['in_built']['zeroDepreciation'] = $zero_dept;
                            }
                            if ($consumable_cover > 0) {
                                $add_ons_data['in_built']['consumables'] = $consumable_cover;
                            }
                            if ($key_replace > 0) {
                                $add_ons_data['in_built']['keyReplace'] = $key_replace;
                            }
                            if ($tyreSecure > 0) {
                                $add_ons_data['in_built']['tyreSecure'] = $tyreSecure;
                            }
                            if ($rsa > 0) {
                                $add_ons_data['in_built']['roadSideAssistance'] = $rsa;
                                if($RSAPlanName == 'RSA-With Key Protect')
                                {
                                    $add_ons_data['in_built']['keyReplace'] = 0;
                                }
                            }
                            if ($eng_protect > 0) {
                                $add_ons_data['in_built']['engineProtector'] = $eng_protect;
                            }
                            if ($return_to_invoice > 0) {
                                $add_ons_data['in_built']['returnToInvoice'] = $return_to_invoice;
                            }
                            if ($loss_belongings > 0) {
                                $add_ons_data['in_built']['lopb'] = $loss_belongings;
                            }
                        // }

                        if ($premium_type != 'third_party') {

                            $applicable_addons = [
                                'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb', 'engineProtector', 'consumables', 'returnToInvoice', 'tyreSecure', //,'emergencyMedicalExpenses'
                            ];
                        } else {
                            $applicable_addons = [];
                        }

                        $total_od = $od_premium + $elect_acc + $non_elec_acc + $lpg_cng_od + $geog_Extension_OD_Premium; #breaking loading amount remove from here
                        $total_tp = $tp_premium + $llpd_amt + $unnamed_pa_amt + $lpg_cng_tp + $geog_Extension_TP_Premium;
                        $total_discount = $ncb_discount + $automobile_assoc + $anti_theft + $voluntary_deductible + $tppd_discount;
                        $basePremium = $total_od + $total_tp - $total_discount;

                        $totalTax = $basePremium * 0.18;

                        $final_premium = $basePremium + $totalTax;

                        $selected_addons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
                        $selected_addons_data['additional_premium'] = array_sum($add_ons_data['additional']);

                        $business_type = '';
                        switch ($requestData->business_type) {
                            case 'newbusiness':
                                $business_type = 'New Business';
                                break;
                            case 'rollover':
                                $business_type = 'Roll Over';
                                break;

                            case 'breakin':
                                $business_type = 'Breakin';
                                if (($requestData->previous_policy_type == 'Third-party' && $premium_type == 'third_party')) {
                                    $business_type = 'Roll Over';
                                }
                                break;

                        }

                        $data_response =
                            [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'status' => true,
                            'msg' => 'Found',
                            'Data' => [
                                'idv' => $premium_type == 'third_party' ? 0 : round($idv),
                                'vehicle_idv' => $idv,
                                'min_idv' => $min_idv,
                                'max_idv' => $max_idv,
                                'rto_decline' => null,
                                'rto_decline_number' => null,
                                'mmv_decline' => null,
                                'mmv_decline_name' => null,
                                'policy_type' => $policy_type,
                                'cover_type' => '1YC',
                                'hypothecation' => '',
                                'hypothecation_name' => '',
                                'vehicle_registration_no' => $requestData->rto_code,
                                'rto_no' => $requestData->rto_code,
                                'version_id' => $mmv_data->ic_version_code,
                                'showroom_price' => $model_config_premium['ExShowRoomPrice'],
                                'fuel_type' => $requestData->fuel_type,
                                'ncb_discount' => $applicable_ncb_rate,
                                'company_name' => $productData->company_name,
                                'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                                'product_name' => $productData->product_sub_type_name . ' - ' . ucwords(str_replace('_', ' ', strtolower($productData->product_identifier)))." - ",
                                'mmv_detail' => $mmv_data,
                                'master_policy_id' => [
                                    'policy_id' => $productData->policy_id,
                                    'policy_no' => $productData->policy_no,
                                    'policy_start_date' => date('d-m-Y', strtotime($PolicyStartDate)),
                                    'policy_end_date' => date('d-m-Y', strtotime($PolicyEndDate)),
                                    'sum_insured' => $productData->sum_insured,
                                    'corp_client_id' => $productData->corp_client_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'insurance_company_id' => $productData->company_id,
                                    'status' => $productData->status,
                                    'corp_name' => '',
                                    'company_name' => $productData->company_name,
                                    'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                                    'product_sub_type_name' => $productData->product_sub_type_name,
                                    'flat_discount' => $productData->default_discount,
                                    'is_premium_online' => $productData->is_premium_online,
                                    'is_proposal_online' => $productData->is_proposal_online,
                                    'is_payment_online' => $productData->is_payment_online,
                                ],
                                'motor_manf_date' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                                'vehicle_register_date' => $requestData->vehicle_register_date,
                                'vehicleDiscountValues' => [
                                    'master_policy_id' => $productData->policy_id,
                                    'product_sub_type_id' => $productData->product_sub_type_id,
                                    'segment_id' => 0,
                                    'rto_cluster_id' => 0,
                                    'car_age' => $car_age,
                                    'aai_discount' => 0,
                                    'ic_vehicle_discount' => 0,
                                ],
                                'basic_premium' => round($od_premium),
                                'deduction_of_ncb' => round($ncb_discount),
                                'voluntary_excess' => $voluntary_deductible,
                                'tppd_premium_amount' => round($tp_premium),
                                'tppd_discount' => round($tppd_discount),
                                'total_loading_amount' => $breakingLoadingAmt,
                                'motor_electric_accessories_value' => round($elect_acc),
                                'motor_non_electric_accessories_value' => round($non_elec_acc),
                                /* 'motor_lpg_cng_kit_value' => round($lpg_cng_od), */
                                'cover_unnamed_passenger_value' => round($unnamed_pa_amt),
                                'seating_capacity' => $mmv_data->seating_capacity,
                                'default_paid_driver' => round($llpd_amt),
                                'motor_additional_paid_driver' => 0,
                                'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
                                'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
                                'compulsory_pa_own_driver' => $cpa_cover,
                                'total_accessories_amount(net_od_premium)' => 0,
                                'total_own_damage' => round($total_od),
                                /* 'cng_lpg_tp' => $lpg_cng_tp, */
                                'total_liability_premium' => round($total_tp),
                                'net_premium' => round($basePremium),
                                'service_tax_amount' => 0,
                                'service_tax' => 18,
                                'total_discount_od' => 0,
                                'add_on_premium_total' => 0,
                                'addon_premium' => 0,
                                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                                'quotation_no' => '',
                                'premium_amount' => round($final_premium),
                                'antitheft_discount' => $anti_theft,
                                'final_od_premium' => round($total_od),
                                'final_tp_premium' => round($total_tp),
                                'final_total_discount' => round($total_discount),
                                'final_net_premium' => round($final_premium),
                                'final_payable_amount' => round($final_premium),
                                'service_data_responseerr_msg' => 'true',
                                'user_id' => $requestData->user_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'user_product_journey_id' => $requestData->user_product_journey_id,
                                'business_type' => $business_type,
                                'service_err_code' => null,
                                'service_err_msg' => null,
                                'policyStartDate' => ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($PolicyStartDate)),
                                'policyEndDate' => date('d-m-Y', strtotime($PolicyEndDate)),
                                'ic_of' => $productData->company_id,
                                'ic_vehicle_discount' => 0,
                                'vehicle_in_90_days' => 0,
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
                                "max_addons_selection" => null,
                                'add_ons_data' => $add_ons_data,
                                'applicable_addons' => $applicable_addons,
                                'isInspectionApplicable' => $isInspectionApplicable,
                                'zresponse_from' => "normal",
                                'zplan_name' => '',
                                'zrsa_plan_name' => $RSAPlanName,
                                'zkey_protect_plan' => $KeyProtectPlan,
                                'company_alias' => 'icici_lombard',
                                'companyAlias' => 'icici_lombard'
                            ],
                        ];

                        if ($bifuel || $IsVehicleHaveLPG) {
                            $data_response['Data']['motor_lpg_cng_kit_value'] = round($lpg_cng_od);
                            $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
                        }
                        // this is response from normal
                        $data_response_final= $data_response;

                    } else {
                        $data_response_final=  [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'status' => false,
                            'message' => isset($arr_premium['message']) ? $arr_premium['message'] : '',

                        ];

                        $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $tyreSecure = 0;
                        $additionPremData = [];
                        $data = '';
                        $IsConsumables = false;
                        $IsTyreProtect = false;
                        $IsRTIApplicableflag = false;
                        $IsEngineProtectPlus = false;
                        $LossOfPersonalBelongingPlanName = '';
                        $KeyProtectPlan = '';
                        $RSAPlanName = '';
                        $EMECover_Plane_name = '';
                        $ZeroDepPlanName = '';
            
                        $bundleCoversAddonsArray = [];
                        $bundleCoversAddonsArrayAll = [];
                        $standAloneCoversAddonsArray = [];
                        $dependentCoversAddonsArray = [];
                        $allAddonsFromService = [];
                        $allPackagesWithAddons['standard'] = [];

                    }

                } else {
                    $data_response_final = [
                        'status' => false,
                        'message' => "Issue in premium calculation service",
                    ];
                }

            // } // final loop end
            return camelCase($data_response_final);
        } else {
            return [
                'status' => false,
                'message' => "Issue in Token Generation service",
            ];
        } // end of loop

        //1769 end of code 175
    } catch (Exception $e) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Car Insurer Not found' . $e->getMessage() . ' line ' . $e->getLine(),
        ];
    }
}

if(!function_exists('thirdPartyResponse'))
{
function thirdPartyResponse(
    $data,
    $arr_premium, $premium_type,
    $requestData,
    $productData,
    $policy_type,
    $mmv_data,
    $model_config_premium,
    $applicable_ncb_rate,
    $PolicyStartDate,
    $PolicyEndDate,
    $car_age,
    $isInspectionApplicable,
    $bifuel,
    $IsVehicleHaveLPG,
    $applicable_addons = []
) {
    update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], "Premium Calculation Success", "Success");
    $idv = round($arr_premium['generalInformation']['depriciatedIDV']);
    if (isset($arr_premium['isQuoteDeviation']) && ($arr_premium['isQuoteDeviation'] == true)) {
        $msg = isset($arr_premium['deviationMessage']) ? $arr_premium['deviationMessage'] : 'Ex-Showroom price provided is not under permissable limits';
        return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => false,
            'message' => $msg,
        ];

    }

    if (isset($arr_premium['breakingFlag']) && isset($arr_premium['isApprovalRequired']) && ($arr_premium['breakingFlag'] == false) && ($arr_premium['isApprovalRequired'] == true)) {
        $msg = "Proposal application didn't pass underwriter approval";
        return [
            'webservice_id' => $data['webservice_id'],
            'table' => $data['table'],
            'status' => false,
            'message' => $msg,
        ];
    }

    $idv = $min_idv = $max_idv = 0;

    $od_premium = 0;
    $breakingLoadingAmt = 0;
    $automobile_assoc = 0;
    $anti_theft = 0;
    $voluntary_deductible = 0;
    $elect_acc = 0;
    $non_elec_acc = 0;
    $lpg_cng_od = 0;
    $lpg_cng_tp = 0;
    $tp_premium = 0;
    $llpd_amt = 0;
    $ncb_discount = 0;
    $unnamed_pa_amt = 0;
    $zero_dept = 0;
    $tppd_discount = 0;
    $rsa = $zero_dept = $eng_protect = $key_replace = $consumable_cover = $return_to_invoice = $loss_belongings = $cpa_cover = $tyreSecure = 0;
    $geog_Extension_OD_Premium = 0;
    $geog_Extension_TP_Premium = $emeCover = 0;

    $geog_Extension_OD_Premium = isset($arr_premium['riskDetails']['geographicalExtensionOD'])  ? ($arr_premium['riskDetails']['geographicalExtensionOD']) : '0';
    $geog_Extension_TP_Premium = isset($arr_premium['riskDetails']['geographicalExtensionTP'])  ? ($arr_premium['riskDetails']['geographicalExtensionTP']) : '0';
    $od_premium = isset($arr_premium['riskDetails']['basicOD']) ? round($arr_premium['riskDetails']['basicOD']) : '0';
    $breakingLoadingAmt = isset($arr_premium['riskDetails']['breakinLoadingAmount']) ? $arr_premium['riskDetails']['breakinLoadingAmount'] : '0';
    $automobile_assoc = isset($arr_premium['riskDetails']['automobileAssociationDiscount']) ? round($arr_premium['riskDetails']['automobileAssociationDiscount']) : '0';
    $anti_theft = isset($arr_premium['riskDetails']['antiTheftDiscount']) ? round($arr_premium['riskDetails']['antiTheftDiscount']) : '0';
    $elect_acc = isset($arr_premium['riskDetails']['electricalAccessories']) ? round($arr_premium['riskDetails']['electricalAccessories']) : '0';
    $non_elec_acc = isset($arr_premium['riskDetails']['nonElectricalAccessories']) ? round($arr_premium['riskDetails']['nonElectricalAccessories']) : '0';
    $lpg_cng_od = isset($arr_premium['riskDetails']['biFuelKitOD']) ? round($arr_premium['riskDetails']['biFuelKitOD']) : '0';
    $ncb_discount = isset($arr_premium['riskDetails']['bonusDiscount']) ? $arr_premium['riskDetails']['bonusDiscount'] : '0';
    $tppd_discount = isset($arr_premium['riskDetails']['tppD_Discount']) ? $arr_premium['riskDetails']['tppD_Discount'] : '0';

    $tp_premium = round($arr_premium['riskDetails']['basicTP']);
    $lpg_cng_tp = isset($arr_premium['riskDetails']['biFuelKitTP']) ? round($arr_premium['riskDetails']['biFuelKitTP']) : '0';
    $llpd_amt = isset($arr_premium['riskDetails']['paidDriver']) ? round($arr_premium['riskDetails']['paidDriver']) : 0;
    $unnamed_pa_amt = isset($arr_premium['riskDetails']['paCoverForUnNamedPassenger']) ? $arr_premium['riskDetails']['paCoverForUnNamedPassenger'] : '0';
    $rsa = isset($arr_premium['riskDetails']['roadSideAssistance']) ? $arr_premium['riskDetails']['roadSideAssistance'] : '0';
    $zero_dept = isset($arr_premium['riskDetails']['zeroDepreciation']) ? $arr_premium['riskDetails']['zeroDepreciation'] : '0';
    $eng_protect = isset($arr_premium['riskDetails']['engineProtect']) ? $arr_premium['riskDetails']['engineProtect'] : '0';
    $key_replace = isset($arr_premium['riskDetails']['keyProtect']) ? $arr_premium['riskDetails']['keyProtect'] : '0';
    $consumable_cover = isset($arr_premium['riskDetails']['consumables']) ? $arr_premium['riskDetails']['consumables'] : '0';
    $return_to_invoice = isset($arr_premium['riskDetails']['returnToInvoice']) ? $arr_premium['riskDetails']['returnToInvoice'] : '0';
    $loss_belongings = isset($arr_premium['riskDetails']['lossOfPersonalBelongings']) ? $arr_premium['riskDetails']['lossOfPersonalBelongings'] : '0';
    $cpa_cover = isset($arr_premium['riskDetails']['paCoverForOwnerDriver']) ? $arr_premium['riskDetails']['paCoverForOwnerDriver'] : '0';
    $tyreSecure = $arr_premium['riskDetails']['tyreProtect'] ?? 0;
    $emeCover = $arr_premium['riskDetails']['emeCover'] ?? 0;

    if (isset($arr_premium['riskDetails']['voluntaryDiscount'])) {
        $voluntary_deductible = $arr_premium['riskDetails']['voluntaryDiscount'];
    } else {
        $voluntary_deductible = voluntary_deductible_calculation($od_premium, $requestData->voluntary_excess_value, 'car');

    }

    $add_ons_data = [
        'in_built' => [
        ],
        'additional' => [
            'consumables' => (int) $consumable_cover,
            'keyReplace' => (int) $key_replace,
            'tyreSecure' => (int) $tyreSecure,
            'roadSideAssistance' => (int) $rsa,
            'engineProtector' => (int) $eng_protect,
            'ncbProtection' => 0,
            'returnToInvoice' => (int) $return_to_invoice,
            'lopb' => (int) $loss_belongings,
            //'cpa_cover'                   => $cpa_cover,
        ],
        'other' => [],
    ];

    $total_od = $od_premium + $elect_acc + $non_elec_acc + $lpg_cng_od + $geog_Extension_OD_Premium; #breaking loading amount remove from here
    $total_tp = $tp_premium + $llpd_amt + $unnamed_pa_amt + $lpg_cng_tp + $geog_Extension_TP_Premium;
    $total_discount = $ncb_discount + $automobile_assoc + $anti_theft + $voluntary_deductible + $tppd_discount;
    $basePremium = $total_od + $total_tp - $total_discount;

    $totalTax = $basePremium * 0.18;

    $final_premium = $basePremium + $totalTax;

    $selected_addons_data['in_built_premium'] = array_sum($add_ons_data['in_built']);
    $selected_addons_data['additional_premium'] = array_sum($add_ons_data['additional']);

    $business_type = '';
    switch ($requestData->business_type) {
        case 'newbusiness':
            $business_type = 'New Business';
            break;
        case 'rollover':
            $business_type = 'Roll Over';
            break;

        case 'breakin':
            $business_type = 'Breakin';
            if (($requestData->previous_policy_type == 'Third-party' && $premium_type == 'third_party')) {
                $business_type = 'Roll Over';
            }
            break;

    }

    $data_response =
        [
        'webservice_id' => $data['webservice_id'],
        'table' => $data['table'],
        'status' => true,
        'msg' => 'Found',
        'Data' => [
            'idv' => $premium_type == 'third_party' ? 0 : round($idv),
            'vehicle_idv' => $idv,
            'min_idv' => $min_idv,
            'max_idv' => $max_idv,
            'rto_decline' => null,
            'rto_decline_number' => null,
            'mmv_decline' => null,
            'mmv_decline_name' => null,
            'policy_type' => $policy_type,
            'cover_type' => '1YC',
            'hypothecation' => '',
            'hypothecation_name' => '',
            'vehicle_registration_no' => $requestData->rto_code,
            'rto_no' => $requestData->rto_code,
            'version_id' => $mmv_data->ic_version_code,
            'showroom_price' => $model_config_premium['ExShowRoomPrice'],
            'fuel_type' => $requestData->fuel_type,
            'ncb_discount' => $applicable_ncb_rate,
            'company_name' => $productData->company_name,
            'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
            'product_name' => $productData->product_sub_type_name . ' - ' . ucwords(str_replace('_', ' ', strtolower($productData->product_identifier))),
            'mmv_detail' => $mmv_data,
            'master_policy_id' => [
                'policy_id' => $productData->policy_id,
                'policy_no' => $productData->policy_no,
                'policy_start_date' => date('d-m-Y', strtotime($PolicyStartDate)),
                'policy_end_date' => date('d-m-Y', strtotime($PolicyEndDate)),
                'sum_insured' => $productData->sum_insured,
                'corp_client_id' => $productData->corp_client_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'insurance_company_id' => $productData->company_id,
                'status' => $productData->status,
                'corp_name' => '',
                'company_name' => $productData->company_name,
                'logo' => env('APP_URL') . config('constants.motorConstant.logos') . $productData->logo,
                'product_sub_type_name' => $productData->product_sub_type_name,
                'flat_discount' => $productData->default_discount,
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
            ],
            'motor_manf_date' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
            'vehicle_register_date' => $requestData->vehicle_register_date,
            'vehicleDiscountValues' => [
                'master_policy_id' => $productData->policy_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'segment_id' => 0,
                'rto_cluster_id' => 0,
                'car_age' => $car_age,
                'aai_discount' => 0,
                'ic_vehicle_discount' => 0,
            ],
            'basic_premium' => round($od_premium),
            'deduction_of_ncb' => round($ncb_discount),
            'voluntary_excess' => $voluntary_deductible,
            'tppd_premium_amount' => round($tp_premium),
            'tppd_discount' => round($tppd_discount),
            'total_loading_amount' => $breakingLoadingAmt,
            'motor_electric_accessories_value' => round($elect_acc),
            'motor_non_electric_accessories_value' => round($non_elec_acc),
            /* 'motor_lpg_cng_kit_value' => round($lpg_cng_od), */
            'cover_unnamed_passenger_value' => round($unnamed_pa_amt),
            'seating_capacity' => $mmv_data->seating_capacity,
            'default_paid_driver' => round($llpd_amt),
            'motor_additional_paid_driver' => 0,
            'GeogExtension_ODPremium' => $geog_Extension_OD_Premium,
            'GeogExtension_TPPremium' => $geog_Extension_TP_Premium,
            'compulsory_pa_own_driver' => $cpa_cover,
            'total_accessories_amount(net_od_premium)' => 0,
            'total_own_damage' => round($total_od),
            /* 'cng_lpg_tp' => $lpg_cng_tp, */
            'total_liability_premium' => round($total_tp),
            'net_premium' => round($basePremium),
            'service_tax_amount' => 0,
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => round($final_premium),
            'antitheft_discount' => '',
            'final_od_premium' => round($total_od),
            'final_tp_premium' => round($total_tp),
            'final_total_discount' => round($total_discount),
            'final_net_premium' => round($final_premium),
            'final_payable_amount' => round($final_premium),
            'service_data_responseerr_msg' => 'true',
            'user_id' => $requestData->user_id,
            'product_sub_type_id' => $productData->product_sub_type_id,
            'user_product_journey_id' => $requestData->user_product_journey_id,
            'business_type' => $business_type,
            'service_err_code' => null,
            'service_err_msg' => null,
            'policyStartDate' => ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($PolicyStartDate)),
            'policyEndDate' => date('d-m-Y', strtotime($PolicyEndDate)),
            'ic_of' => $productData->company_id,
            'ic_vehicle_discount' => 0,
            'vehicle_in_90_days' => 0,
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
            "max_addons_selection" => null,
            'add_ons_data' => $add_ons_data,
            'applicable_addons' => $applicable_addons,
            'isInspectionApplicable' => $isInspectionApplicable,
            'zresponse_from' => "third party",
        ],
    ];

    if ($bifuel || $IsVehicleHaveLPG) {
        $data_response['Data']['motor_lpg_cng_kit_value'] = round($lpg_cng_od);
        $data_response['Data']['cng_lpg_tp'] = $lpg_cng_tp;
    }
    // this is response third party
    return camelCase($data_response);

}
}

if(!function_exists('getAddonsService'))
{
function getAddonsService($enquiryId,$productData,$premium_type,$corelationId,$first_reg_date,$mmv_data,$rto_data,$deal_id,$PolicyStartDate)
{

    global  $bundleCoversAddonsArray ;
    global $bundleCoversAddonsArrayAll ;
    global  $standAloneCoversAddonsArray ;
    global  $dependentCoversAddonsArray ;
    global  $allAddonsFromService ;
    global $allPackagesWithAddons;
            $bundleCoversAddonsArray = [];
            $bundleCoversAddonsArrayAll = [];
            $standAloneCoversAddonsArray = [];
            $dependentCoversAddonsArray = [];
            $allAddonsFromService = [];
            $allPackagesWithAddons['standard'] = []; 
    if (!in_array($premium_type, ['third_party', 'third_party_breakin'])) {
        // token Generation for addons

        $additionData = [
            'requestMethod' => 'post',
            'type' => 'tokenGenerationAddons',
            'section' => 'car',
            'productName' => $productData->product_name,
            'enquiryId' => $enquiryId,
            'transaction_type' => 'quote',
        ];

        $tokenParam = [
            'grant_type' => 'password',
            'username' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
            'password' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
            'client_id' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
            'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
            'scope' => 'esbmotoraddoncovergrid',
        ];
        // TOKEN API for addons
        $token_cache_name_addons = 'constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR.car_addons.' . $enquiryId;
        $token_cache_addons = Cache::get($token_cache_name_addons);
        if (empty($token_cache_addons)) {
            $token_response_addons = getWsData(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR'), http_build_query($tokenParam), 'icici_lombard', $additionData);
            $token_decoded_response = json_decode($token_response_addons['response'], true);
            if (isset($token_decoded_response['access_token'])) {
                update_quote_web_servicerequestresponse($token_response_addons['table'], $token_response_addons['webservice_id'], "Token Generation Success", "Success");
                $token_addons = cache()->remember($token_cache_name_addons, 60 * 45, function () use ($token_response_addons) {
                    return $token_response_addons;
                });
            } else {
                $error_data =  [
                    'webservice_id' => $token_response_addons['webservice_id'],
                    'table' => $token_response_addons['table'],
                    'status' => false,
                    'message' => "Insurer not reachable,Issue in Token Generation service for Addons",
                ];
            }
        } else {
            $token_addons = $token_cache_addons;
        }

        if (!empty($token_addons)) {
            $token_addons_resp = json_decode($token_addons['response'], true);

            if (isset($token_addons_resp['access_token'])) {
                $access_token_addons = $token_addons_resp['access_token'];
            } else {
                $error_data = [
                    'webservice_id' => $token_addons_resp['webservice_id'],
                    'table' => $token_addons_resp['table'],
                    'status' => false,
                    'message' => "Insurer not reachable,Issue in Token Generation service",
                ];
            }
            $additionPremDataAddons = [
                'requestMethod' => 'post',
                'type' => 'addonsCoverService',
                'section' => 'car',
                'productName' => $productData->product_name,
                'token' => $access_token_addons,
                'enquiryId' => $enquiryId,
                'transaction_type' => 'quote',
            ];
            $addons_array_input = [
                "correlationId" => $corelationId,
                "registrationDate" => $first_reg_date,
                "makeCode" => $mmv_data->manufacturer_code,
                "modelCode" => $mmv_data->model_code,
                "rtoCode" => $rto_data->txt_rto_location_code,
                "dealId" => $deal_id,
                "policyStartDate" => date('Y-m-d', strtotime($PolicyStartDate)),
            ];
            $url_addons = config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_ADDONS_COVER_URL');
            $data_addons = getWsData($url_addons, $addons_array_input, 'icici_lombard', $additionPremDataAddons);

            if ($data_addons['response']) {
                $addons_array_response = json_decode($data_addons['response'], true);
                if (isset($addons_array_response['status']) && $addons_array_response['status']) {
                    update_quote_web_servicerequestresponse($data_addons['table'], $data_addons['webservice_id'], "Premium Addons Covers Success", "Success");
                    if (isset($addons_array_response['alCarteCovers']) && count($addons_array_response['alCarteCovers']) > 0) {
                        foreach ($addons_array_response['alCarteCovers'] as $al_key => $al_value) {
                            if ($al_value['coverName'] !== 'PAYU') {
                                $standAloneCoversAddonsArray[] = $al_value['coverName'];
                                $addonForStandard = [
                                    'covername' => $al_value['coverName'],
                                    'planname' => $al_value['planName'],
                                    'dependentOn' => $al_value['dependentOn'],
                                ];
                                $allPackagesWithAddons['standard'][] = $addonForStandard;
                            }
                        }
                    }
                    if (isset($addons_array_response['bundleCovers']) && count($addons_array_response['bundleCovers']) > 0) {
                        foreach ($addons_array_response['bundleCovers'] as $bundled_key => $bundled_value) {

                            if (isset($bundled_value['coverDetails']) && count($bundled_value['coverDetails']) > 0) {
                                foreach ($bundled_value['coverDetails'] as $bundled_key_key => $bundled_value_value) {

                                    if ($bundled_value_value['coverName'] != 'PAYU') {
                                        $bundleCoversAddonsArray[$bundled_value['bundleName']][] = $bundled_value_value['coverName'];
                                        $bundleCoversAddonsArrayAll[] = $bundled_value_value['coverName'];
                                        $addonForStandard = [
                                            'covername' => $bundled_value_value['coverName'],
                                            'planname' => $bundled_value_value['planName'],
                                            'dependentOn' => $bundled_value_value['dependentOn'],
                                        ];
                                        $allPackagesWithAddons[$bundled_value['bundleName']][] = $addonForStandard;
                                    }

                                }
                            }
                        }
                    }

                    if (isset($addons_array_response['alCarteCovers']) && count($addons_array_response['alCarteCovers']) > 0) {
                        //This block is for dependent addons logics we will have to develop this as per response i havent found same
                    }

                    return $allPackagesWithAddons;
                }
            }

        }

    }
}
}