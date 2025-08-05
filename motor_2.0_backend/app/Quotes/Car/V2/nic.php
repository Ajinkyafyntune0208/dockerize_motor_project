<?php

use Carbon\Carbon;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Proposal\Services\Car\V2\nicSubmitProposal as NIC_V2;
use App\Services\MMVDetailsService;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
function getQuoteV2($enquiryId, $requestData, $productData)
{

    $allowedIdentifiers = ['BASIC', 'BASIC_WITH_ADDON', 'ZERO_DEP', 'TP_ONLY', 'TP_BREAK_IN'];

    if (!in_array($productData->product_identifier, $allowedIdentifiers)) {
        $errorMessage = 'Product Identifier not configured properly';
        return [
            'status' => false,
            'premium_amount' => 0,
            'message' => $errorMessage,
            'request' => [
                'product_identifier' => $productData->product_identifier,
                'message' => $errorMessage,
            ]
        ];
    }
    $product_identifier = $productData->product_identifier;
    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $is_package    = in_array($premium_type, ['comprehensive', 'breakin']);
    $is_liability  = in_array($premium_type, ['third_party', 'third_party_breakin']);
    $is_od         = in_array($premium_type, ['own_damage', 'own_damage_breakin']);
    $is_individual = $requestData->vehicle_owner_type == 'I';
    $is_new        = !in_array($requestData->business_type, ['rollover', 'breakin']);

    $plan_type = $is_od ? 'SAOD' : (($is_liability && $is_new) ? '0OD3TP' : ($is_liability ? '0OD1TP' : ($requestData->business_type == 'newbusiness' ? '1OD3TP' : '1OD1TP')));

    $is_breakin = strpos($requestData->business_type, 'breakin') !== false && ($is_liability || $requestData->previous_policy_type != 'Third-party');
    $is_zero_dep = (($productData->zero_dep == '0') ? true : false);
    $motor_manf_date = '01-' . $requestData->manufacture_year;

    if (empty($requestData->rto_code)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO not available',
            'request' => [
                'rto_code' => $requestData->rto_code,
                'message' => 'RTO not available',
            ]
        ];
    }
    //----x--commented previous mmv logic---x-----//
    // $mmv = get_mmv_details($productData, $requestData->version_id, 'national');

    $manufacture_date = $requestData->manufacture_year;
    $carbonDate = Carbon::createFromFormat('m-Y', $manufacture_date);
    $manufacture_year = $carbonDate->year;

    $mmvService = app(MMVDetailsService::class);
    $mmv = $mmvService->get_mmv_details($productData, $requestData->version_id, 'national',$manufacture_year);
    $mmv_data = nic_mmv_check_v2($mmv);

    if (!$mmv_data['status']) {
        return $mmv_data;
    }
    $mmv = $mmv_data['mmv_data'];

    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date === 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);

    $interval = $date1->diff($date2);
    $age = ($interval->y * 12) + $interval->m;
    $vehicle_age = $interval->y;
    $vehicle_age_years = $interval->y;
    $vehicle_age_months = $interval->m;
    $vehicle_age_days = $interval->d;

    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $addons = $selected_addons->addons ?? [];
    $accessories = $selected_addons->accessories ?? [];
    $additional_covers = $selected_addons->additional_covers ?? [];
    $discounts = $selected_addons->discounts ?? [];

    $Electricalaccess = $externalCNGKIT = $PAforUnnamedPassenger = $PAforaddionaldPaidDriver = $PAforaddionaldPassenger = $NonElectricalaccess = $PAPaidDriverConductorCleaner = $llpaidDriver = $consumable = $key_replacement = $return_to_invoice = "N";

    // additional covers
    $externalCNGKITSI = $ElectricalaccessSI = $PAforaddionaldPaidDriverSI = $PAforUnnamedPassengerSI = $NonElectricalaccessSI = $PAPaidDriverConductorCleanerSI = $llpaidDriverSI = 0;

    $is_anti_theft = $is_voluntary_access = $autoMobileAssociation = $Electricalaccess = $NonElectricalaccess = $externalCNGKIT = $PAPaidDriverConductorCleaner = $PAforaddionaldPaidDriver = $PAforUnnamedPassenger = $llpaidDriver = false;
    $externalCNGKIT = "2";

    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value)) {
            $Electricalaccess = true;
            $ElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('Non-Electrical Accessories', $value)) {
            $NonElectricalaccess = true;
            $NonElectricalaccessSI = $value['sumInsured'];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKIT = "1";
            $externalCNGKITSI = $value['sumInsured'];
        }

        if (in_array('PA To PaidDriver Conductor Cleaner', $value)) {
            $PAPaidDriverConductorCleaner = true;
            $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        }
    }

    foreach ($additional_covers as $key => $value) {
        if (in_array('PA cover for additional paid driver', $value)) {
            $PAforaddionaldPaidDriver = true;
            $PAforaddionaldPaidDriverSI = $value['sumInsured'];
        }

        if (in_array('Unnamed Passenger PA Cover', $value)) {
            $PAforUnnamedPassenger = true;
            $PAforUnnamedPassengerSI = $value['sumInsured'];
        }

        if (in_array('LL paid driver', $value)) {
            $llpaidDriver = true;
            $llpaidDriverSI = $value['sumInsured'];
        }
    }
    $is_tppd = false;
    foreach ($discounts as $key => $data) {
        if ($data['name'] == 'anti-theft device' && !$is_liability) {
            $isAntiTheft = true;
        }

        if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
            $isVoluntaryExccess = true;
            $voluntaryExcessAmt = $data['sumInsured'];
        }

        if ($data['name'] == 'TPPD Cover' && !$is_od) {
            $is_tppd = true;
            $tppd_amt = '9999';
        }
    }
    $VehicleCoverages_od = [];
    if ($Electricalaccess && $ElectricalaccessSI != '0') {
        $VehicleCoverages_od[] = [
            "ProductElementCode" => "B00813",
            "ManufactureYear" => date('Y-m-d', strtotime($motor_manf_date)),
            "ManufacturerSellingPrice" => $ElectricalaccessSI
        ];
    }

    if ($NonElectricalaccess && $NonElectricalaccessSI != '0') {
        $VehicleCoverages_od[] = [
            "ProductElementCode" => "B00814",
            "ManufactureYear" => date('Y-m-d', strtotime($motor_manf_date)),
            "ManufacturerSellingPrice" => $NonElectricalaccessSI
        ];
    }
    if ($externalCNGKIT && $externalCNGKITSI != '0') {
        $VehicleCoverages_od[] = [
            "ProductElementCode" => "B00815",
            "ManufactureYear" => date('Y-m-d', strtotime($motor_manf_date)),
            "ManufacturerSellingPrice" =>  $externalCNGKITSI
        ];
    }

    $LegalLiabilityToEmployee = true;
    $VehicleCoverages_tp = [];
    if (!$is_individual) {
        $VehicleCoverages_tp[] = [
            "ProductElementCode" => "B00817",
            "CountCCC" => ((int) $mmv->seating_capacity) - 1
        ];
    }

    if ($llpaidDriver || !$is_individual) {
        $VehicleCoverages_tp[] = [
            "ProductElementCode" => "B00818",
            "CountCCC" => 1
        ];
    }


    if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
        $addons = $selected_addons->compulsory_personal_accident;
        foreach ($addons as $value) {
            if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                    $cpa_tenure = isset($value['tenure']) ? '3': '1';

            }
        }
    }
    if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
    {
        if($requestData->business_type == 'newbusiness')
        {
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '3'; 
        }
        else{
            $cpa_tenure = isset($cpa_tenure) ? $cpa_tenure : '1';
        }
    }
    $VehicleCoverages = [];
    if($is_individual){
        $VehicleCoverages[] = [
            "ProductElementCode" => "B00811",
            "NumberOfYears" => ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" && isset($cpa_tenure))? $cpa_tenure : '',
            "NomineeName" => "Ramya",
            "NomineeAge" => "21",
            "NomineeRelToProposer" => "60",
            "GuardianName" => "Name",
            "GuardianRelationshipwithNominee" => "60"
        ];
    }
    // $VehicleCoverages[] = [
    //     "ProductElementCode" => "B00820",
    //     "SumInsured" => 200000,
    //     "Name" => "Saranya",
    //     "Age" => "30",
    //     "NomineeName" => "Ramya",
    //     "NomineeAge" => "20",
    //     "NomineeRelToProposer" => "60",
    //     "GuardianName" => "Name",
    //     "GuardianRelationshipwithNominee" => "60"
    // ];
    if ($PAforaddionaldPaidDriver && $PAforaddionaldPaidDriverSI != '0') {
        $VehicleCoverages[] = [
            "ProductElementCode" => "B00821",
            "CountCCC" => 1,
            "SumInsured" => $PAforaddionaldPaidDriverSI
        ];
    }
    if ($PAforUnnamedPassenger && $PAforUnnamedPassengerSI != '0') {
        $VehicleCoverages[] = [
            "ProductElementCode" => "B00822",
            "CountCCC" => 1,
            "SumInsured" => $PAforUnnamedPassengerSI
        ];
    }

    $vehicle_in_90_days = 0;

    $current_date = date('Y-m-d');

    if ($is_new) {
        $policy_start_date  = date('Y-m-d');
        $policy_end_date    = date('Y-m-d', strtotime('+3 years -1 day', strtotime($policy_start_date)));
        $requestData->vehicle_register_date = $policy_start_date;
    } else {
        if ($requestData->business_type == "breakin") {
            $policy_start_date = date('Ymd');
        } else {
            $policy_start_date = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date . ' +1 day'));

            if (strtotime($requestData->previous_policy_expiry_date) < strtotime($current_date)) {
                $policy_start_date = date('Y-m-d', strtotime('+1 day'));
            }
        }
        $policy_end_date = date('Y-m-d', strtotime('+1 years -1 day', strtotime($policy_start_date)));
    }

    if ($requestData->vehicle_registration_no !== 'NEW' && !empty($requestData->vehicle_registration_no)) {
        $vehicle_register_no = explode('-', $requestData->vehicle_registration_no);
    } else {
        $vehicle_register_no = array_merge(explode('-', $requestData->rto_code), ['MGK', rand(1111, 9999)]);
    }

    $vehicle_register_no = implode('-', $vehicle_register_no);

    $rto_data = DB::table('nic_rto_master')->where('rto_number', strtr($requestData->rto_code, ['-' => '']))->first();

    $policy_date = [
        'start' => strtotime($policy_start_date),
        'end' => strtotime($policy_end_date)
    ];

    $prev_policy = [
        'end' => strtotime($requestData->previous_policy_expiry_date),
        'start' => strtotime('-1 year -1 day', strtotime($requestData->previous_policy_expiry_date))
    ];

    $tp_policy_date = [
        'start_date' => strtotime('+1 day', $policy_date['start']),
        'end_date' => strtotime('+3 year -1 day', strtotime('+1 day', $policy_date['start']))
    ];

    $yn_claim = 'Y';
    $ncb = [
        'current' => 0,
        'applicable' => 0,
        'active' => false,
        'level' => 0
    ];

    $ncb_levels = ['0' => '0', '20' => '1', '25' => '2', '35' => '3', '45' => '4', '50' => '5'];

    if ($requestData->is_claim === 'N') {
        $yn_claim = 'N';
        $ncb = [
            'active' => true,
            'current' => $requestData->previous_ncb,
            'applicable' => $requestData->applicable_ncb,
            'level' => $ncb_levels[$requestData->applicable_ncb] ?? '0'
        ];
    }

    if ($is_new) {
        $ncb['level'] = '0';
    }

    // token Generation
    $token_response = NIC_V2::generateToken($productData->product_name, $enquiryId, 'quote');

    if(!$token_response['status']){
        return [
            'status'    => false,
            'webservice_id' => $token_response['webservice_id'],
            'table' => $token_response['table'],
            'msg'       => 'Error in token generation service',
            'stage'     => 'token'
        ];
    }
    //---x---Product-identifier-logic-for addons---x----//
   
    switch ($product_identifier) {
        case 'BASIC_WITH_ADDON':
            $IsNCBProtectOpted = ($vehicle_age_years <= 6) ? "1" : "2";
            $IsRSAOpted = "1";
            $IsEngineProtectOpted = ($vehicle_age_years < 5) ? "1" : "2";
            $IsConsumablesProtectOpted = ($vehicle_age_years <= 5) ? "1" : "2";
            $LockkeySI = ($vehicle_age_years <= 5) ? "5000" : "0";
            $LossOfBelongingSI = ($vehicle_age_years <= 5) ? "20000" : "0";
            $TyreAndRimSI = ($vehicle_age_years < 2 || ($vehicle_age_years == 2 && $vehicle_age_months == 0 && $vehicle_age_days == 0)) ? "200000" : "0";
            $IsNilDeporNilDepPlusOpted = "0";
            break;
        case 'ZERO_DEP':
            $IsNCBProtectOpted = ($vehicle_age_years <= 6) ? "1" : "2";
            $IsRSAOpted = "1";
            $IsEngineProtectOpted = ($vehicle_age_years < 5) ? "1" : "2";
            $IsConsumablesProtectOpted = ($vehicle_age_years <= 5) ? "1" : "2";
            $LockkeySI = ($vehicle_age_years <= 5) ? "5000" : "0";
            $LossOfBelongingSI = ($vehicle_age_years <= 5) ? "20000" : "0";
            $TyreAndRimSI = ($vehicle_age_years < 2 || ($vehicle_age_years == 2 && $vehicle_age_months == 0 && $vehicle_age_days == 0)) ? "200000" : "0";
            $IsNilDeporNilDepPlusOpted = "1";
            break;
        default:
            $IsNCBProtectOpted = "2";
            $IsRSAOpted =  "2";
            $IsEngineProtectOpted = "2";
            $IsConsumablesProtectOpted = "2";
            $LockkeySI = "0";
            $LossOfBelongingSI = "0";
            $IsNilDeporNilDepPlusOpted = "0";
            $TyreAndRimSI = "0";

    }

    $quoteArray = [
        "ProductCode" => "PC",
        "ProductVersion" => "1.0",
        "EffectiveDate" => $policy_start_date,
        "ExpiryDate" => $policy_end_date,
        "ChannelType" => config('IC.NIC.V2.CAR.NIC_CHANNEL_TYPE'),
        "AgreementCode" => config('IC.NIC.V2.CAR.AGREEMENT_CODE_NIC_MOTOR'),
        "AgentCode" => config('IC.NIC.V2.CAR.AGENT_CODE_NIC_MOTOR'),
        "PlanType" => $plan_type,
        "PolicyCustomerList" => [
            [
                "CustomerType" => ($is_individual ? 'IndiCustomer' : 'OrgCustomer'),
                // "CustomerNumber" => "7500000019"
            ]
        ],
        "PolicyLobList" => [
            [
                "ProductCode" => "PC",
                "PolicyRiskList" => [
                    [
                        "ProductElementCode" => "R00004",
                        "VehicleCategory" => $is_new ? "1" : "2",
                        "RegistrationDate" => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                        "DateOfDeliveryPurchase" => date('Y-m-d', strtotime($requestData->vehicle_register_date)), //$is_new ? date('Y-m-d') : '',
                        "RegistrationNo" => $is_new ? '' : $vehicle_register_no,
                        "RTONameCode" => str_replace('-', '', $requestData->rto_code),
                        "Variant" => $mmv->serial_no,
                        "TPPD_SILimit" => "4",//$is_tppd ? "1" : "2",
                        "IsNilDeporNilDepPlusOpted" => $IsNilDeporNilDepPlusOpted,//$is_zero_dep ? "1" : "0", //"0 if not opted, 1 if NilDep opted, 2 if NilDepPlus opted                                 
                        "IsNCBProtectOpted" => $IsNCBProtectOpted,
                        "IsRSAOpted" =>  $IsRSAOpted,
                        "IsInvoiceProtectOpted" => $is_new ? "1" : "2",
                        // "IsFuelFlipOpted" => "0",
                        // "IsPickDropOpted" => "0",
                        // "IsDailyAllowanceOpted" => "0",
                        "IsEngineProtectOpted" => $IsEngineProtectOpted,
                        "IsConsumablesProtectOpted" => $IsConsumablesProtectOpted,
                        // "IsLossofDLOpted" => "2",
                        "LockkeySI" => $LockkeySI,
                        "EMIProtect_EMIAmount" => $is_new ? "10000" : "0",
                        "TyreAndRimSI" => $TyreAndRimSI,
                        "LossOfBelongingSI" => $LossOfBelongingSI,
                        "IsExternalCNG_LPGKit" => $externalCNGKIT,
                        "FirstRegisteredOwner" => $is_new ? "1" : "2",
                        "ActiveLiabilityPolicyNo" => "",
                        "ActiveLiabilityPolicyEffDate" => "",
                        "ActiveLiabilityPolicyExpDate" => "",
                        "ActiveLiabilityPolicyInsurer" => "",
                        "IsAAMember" => "",
                        "ManufactureYear" => explode('-',$requestData->manufacture_year)[1],
                        "PolicyCoverageList" => [
                            [
                                "ProductElementCode" => "C0003006",
                                "PolicyBenefitList" => $VehicleCoverages_od
                            ],
                            [
                                "ProductElementCode" => "C0003007",
                                "PolicyBenefitList" => $VehicleCoverages_tp
                            ],
                            [
                                "ProductElementCode" => "C0003008",
                                "PolicyBenefitList" => $VehicleCoverages
                            ]
                        ]
                    ]
                ]
            ]
        ],
        "PreviousInsuranceDetailsList" => [
            [
                "PrevInsuranceCompanyBranchId" => "598142",
                "PrevInsuranceCompanyId" => "47084735",
                "PrePolicyNo" => "POL12345678",
                "PrePolicyStartDate" => date('Y-m-d', $prev_policy['start']),
                "PrePolicyEndDate" => date('Y-m-d', $prev_policy['end']),
                "NoOfClaims" => ($yn_claim == 'N' ? 0 : 1),
                "NCB" => $ncb['current']
            ]
        ],
        "DiscountDetailsList" => [
            [
                "CoverageId" => "C0003006",
                "DiscountType" => "104",
                "DiscountPercent" => "0"
            ]
        ]
    ];

    if ($requestData->business_type == 'newbusiness') {
        $quoteArray['PreviousInsuranceDetailsList'] = [];
    }
    foreach ($quoteArray['PolicyLobList'] as &$lob) {
        foreach ($lob['PolicyRiskList'] as &$risk) {
            //in case of OD unseting C0003007 and C0003008 CoverCode
            if ($is_od) {
                foreach ($risk['PolicyCoverageList'] as $key => $coverage) {
                    if (in_array($coverage['ProductElementCode'], ['C0003007', 'C0003008'])) {
                        unset($risk['PolicyCoverageList'][$key]);
                    }
                }
            }
            // in case of TP unseting C0003006 CoverCode
            if ($is_liability) {
                foreach ($risk['PolicyCoverageList'] as $key => $coverage) {
                    if ($coverage['ProductElementCode'] == 'C0003006') {
                        unset($risk['PolicyCoverageList'][$key]);
                    }
                }
            }
            $risk['PolicyCoverageList'] = array_values($risk['PolicyCoverageList']);
        }
    }

    // quick quote service input
    $additional_data = [
        'enquiryId'         => $enquiryId,
        'requestMethod'     => 'post',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Premium Calculation',
        'transaction_type'  => 'quote',
        'productName'       => $productData->product_name,
        'content_type'      => 'application/json',
        'headers' => [
            'Content-Type'      => 'application/json',
            'User-Agent'        => $_SERVER['HTTP_USER_AGENT'],
            'Authorization'     => 'Bearer ' . $token_response['token']
        ]
    ];

    $get_response = getWsData(config('IC.NIC.V2.CAR.END_POINT_URL_NIC_MOTOR_PREMIUM'), $quoteArray, 'nic', $additional_data);

    if ($get_response['response']) {
        if (!empty($get_response['response'])) {
            $response = json_decode($get_response['response'], true);
            $msg = '';
            if (!empty($response['messages']) && is_array($response['messages'])) {
                $msg = implode(" | ", array_column($response['messages'], 'message'));
            } elseif (!empty($response['message'])) {
                $msg = $response['message'];
            } elseif (!empty($response['error'])) {
                $msg = $response['error'];
            }
            if (!empty($msg)) {
                return [
                    'webservice_id'  => $get_response['webservice_id'] ?? null,
                    'table'          => $get_response['table'] ?? null,
                    'status'         => false,
                    'msg'            => $msg,
                    'quote_request'  => $response
                ];
            }
        }

        if (!$is_liability) {
            $policyRisk = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0];
            $idv = $policyRisk['BasicIDV'];
            $min_idv = $policyRisk['MinIDV'];
            $max_idv = $policyRisk['MaxIDV'];
        
            // IDV change
            if (isset($requestData->is_idv_changed) && $requestData->is_idv_changed == 'Y') {
                if ($requestData->edit_idv >= $max_idv) {
                    $idv = (string)$max_idv;
                } elseif ($requestData->edit_idv <= $min_idv) {
                    $idv = (string)$min_idv;
                } else {
                    $idv = $requestData->edit_idv;
                }
            } else {
                $idv = (string)$min_idv;
            }
            $idv_quote_array = $quoteArray;

            $idv_quote_array['PolicyLobList'][0]['PolicyRiskList'][0]['BasicIDV'] = $idv;

            $additional_data = [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Premium Re-Calculation',
                'transaction_type'  => 'quote',
                'productName'       => $productData->product_name,
                'content_type'      => 'application/json',
                'headers' => [
                        'Content-Type'      => 'application/json',
                        'User-Agent'        => $_SERVER['HTTP_USER_AGENT'],
                        'Authorization'     => 'Bearer ' . $token_response['token']
                ]
            ];

            $get_response = getWsData(config('IC.NIC.V2.CAR.END_POINT_URL_NIC_MOTOR_PREMIUM'), $idv_quote_array, 'nic', $additional_data);
        }
    
        if (isset($get_response['response']) && !empty($get_response['response'])) {
            $response = json_decode($get_response['response'], true);

            if (!empty($response['error'])) {
                return [
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status'    => false,
                    'msg'       => $response['message'],
                    'message'   => $response['message']
                ];
            }
        } else {
            return  [
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'premium_amount'    => '0',
                'status'            => false,
                'message'           => 'Car Insurer Not found',
            ];
        }
        // $CoverageList = $response['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'];

        $total_od_premium = $total_tp_premium = $total_addons = $total_discount = 0;

        $cover_codes = [
            'C0003009' => 'return_to_invoice',
            'C0003015' => 'rsa',
            'C0003003' => 'zero_dep',
            'C0003010' => 'engine_protect',
            'C0003012' => 'key_replace',
            'C0003011' => 'consumable',
            'C0003014' => 'Loss_of_belonging',
            'C0001004' => 'tyre_secure',
            'C0003017' => 'ncb_protection',
            'C0003013' => 'emi_protect',
            // 'C0003006' => 'own_damage',
            'B00817'   => 'legal_liability_to_employee',
            'B00818'   => 'legal_liability_driver_cleaner',
            'B00823'   => 'third_party_basic',
            'B00811'   => 'compulsory_pa',
            'B00813'   => 'electrical_accessories',
            'B00814'   => 'non_electrical_accessories',
            'B00815'   => 'cng_kit',
            'B00816'   => 'lpg_kit',
            'B00812'   => 'own_damage_basic',
            'B00821'   => 'optional_pa_paid_driver_cleaner',
            'B00822'   => 'optional_unnamed_persons',
        ];

        $covers = [
            'return_to_invoice'             => 0,
            'rsa'                           => 0,
            'zero_dep'                      => 0,
            'engine_protect'                => 0,
            'key_replace'                   => 0,
            'consumable'                    => 0,
            'Loss_of_belonging'             => 0,
            'tyre_secure'                   => 0,
            'ncb_protection'                => 0,
            'emi_protect'                   => 0,
            // 'own_damage'                    => 0,
            'compulsory_pa'                 => 0,
            'electrical_accessories'        => 0,
            'non_electrical_accessories'    => 0,
            'cng_kit'                       => 0,
            'lpg_kit'                       => 0,
            'own_damage_basic'              => 0,
            'legal_liability_driver_cleaner' => 0,
            'third_party_basic'              => 0,
            'optional_pa_paid_driver_cleaner' => 0,
            'optional_unnamed_persons'      => 0,
            'legal_liability_to_employee'   => 0,
        ];

        foreach ($response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'] as $risk) {
            foreach ($risk['PolicyCoverageList'] as $coverage) {
                $productElementCode = $coverage['ProductElementCode'];
                if (isset($cover_codes[$productElementCode])) {
                    $key = $cover_codes[$productElementCode];
                    $covers[$key] = $coverage['GrossPremium'];
                }
                if (isset($coverage['PolicyBenefitList'])) {
                    foreach ($coverage['PolicyBenefitList'] as $benefit) {
                        $benefitCode = $benefit['ProductElementCode'];
                        if (isset($cover_codes[$benefitCode])) {
                            $key = $cover_codes[$benefitCode];
                            $covers[$key] = $benefit['GrossPremium'];
                        }
                    }
                }
            }
        }

        if ($is_liability) {
            $add_on_data = [
                'in_built'   => [],
                'additional' => [
                    'zero_depreciation'             => 0,
                    'road_side_assistance'          => 0,
                    'engine_protector'              => 0,
                    'return_to_invoice'             => 0,
                    'ncb_protection'                => 0,
                    'key_replace'                   => 0,
                    'consumables'                   => 0,
                    'tyre_secure'                   => 0,
                    'lopb'                          => 0,
                    'emi_protection'                => 0,
                ],
                'other'      => [],
            ];
        } else if ($is_zero_dep) {
            $add_on_data = [
                'in_built'   => [

                    'zero_depreciation'   => $covers['zero_dep']
                ],
                'additional' => [
                    'road_side_assistance'          => $covers['rsa'],
                    'engine_protector'              => $covers['engine_protect'],
                    'return_to_invoice'             => $covers['return_to_invoice'],
                    'ncb_protection'                => $covers['ncb_protection'],
                    'key_replace'                   => $covers['key_replace'],
                    'consumables'                   => $covers['consumable'],
                    'tyre_secure'                   => $covers['tyre_secure'],
                    'lopb'                          => $covers['Loss_of_belonging'],
                    'emi_protection'                => $covers['emi_protect'],
                ],
                'other'      => [],
            ];
        } else {
            $add_on_data = [
                'in_built'   => [],
                'additional' => [
                    'zero_depreciation'             => $covers['zero_dep'],
                    'road_side_assistance'          => $covers['rsa'],
                    'engine_protector'              => $covers['engine_protect'],
                    'return_to_invoice'             => $covers['return_to_invoice'],
                    'ncb_protection'                => $covers['ncb_protection'],
                    'key_replace'                   => $covers['key_replace'],
                    'consumables'                   => $covers['consumable'],
                    'tyre_secure'                   => $covers['tyre_secure'],
                    'lopb'                          => $covers['Loss_of_belonging'],
                    'emi_protection'                => $covers['emi_protect'],
                ],
                'other'      => [],
            ];
        }


        $in_built_premium = 0;
        foreach ($add_on_data['in_built'] as $key => $value) {
            $in_built_premium = $in_built_premium + $value;
        }

        $additional_premium = 0;
        // return $add_on_data['additional'];
        foreach ($add_on_data['additional'] as $key => $value) {
            $additional_premium = $additional_premium + $value;
        }

        $other_premium = 0;
        foreach ($add_on_data['other'] as $key => $value) {
            $other_premium = $other_premium + $value;
        }

        $add_on_data['in_built_premium'] = $in_built_premium;
        $add_on_data['additional_premium'] = $additional_premium;
        $add_on_data['other_premium'] = $other_premium;

        $applicable_addons = [
            'zeroDepreciation',
            'roadSideAssistance',
            'engineProtector',
            'returnToInvoice',
            'keyReplacement',
            'Consumable',
            'Loss_Of_Belonging'
        ];

        $covers['ncb'] = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['NCBAmount'] ?? 0;
        $other_discounts = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][0]['AADiscountAmount'] ?? 0;
        $index = ($is_breakin || $is_liability) ? 0 : 1;
        $lpg_cng_tp_amount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][$index]['CNGLPGKitLiabilityPremium'] ?? 0;
        $llpaidemp_premium = $covers['legal_liability_to_employee'];
        $tppd_discount = $response['PolicyObject']['PolicyLobList'][0]['PolicyRiskList'][0]['PolicyCoverageList'][1]['TPPDDiscountAmount'] ?? 0;
        
        $total_od_premium = $covers['own_damage_basic'] + $covers['electrical_accessories'] + $covers['non_electrical_accessories'] + $covers['cng_kit'] + $covers['lpg_kit'];
        $total_tp_premium =  $covers['third_party_basic'] + $covers['legal_liability_driver_cleaner'] + $covers['optional_unnamed_persons'] + $covers['optional_pa_paid_driver_cleaner'] + $lpg_cng_tp_amount + $llpaidemp_premium;
        $total_addons =  $covers['rsa'] + $covers['return_to_invoice'] + $covers['zero_dep'] + $covers['engine_protect'] + $covers['key_replace'] + $covers['consumable'] + $covers['Loss_of_belonging'] + $covers['tyre_secure'] + $covers['ncb_protection'];
        $total_discount = $covers['ncb'] + $other_discounts; //$ncb_discount + $discount_amount;
        $basePremium = $total_od_premium + $total_tp_premium + $total_addons - $total_discount;
        $totalTax = $basePremium * 0.18;
        $final_premium = $basePremium + $totalTax;

        $cng_lpg_od = in_array($requestData->fuel_type, ['CNG', 'LPG']) ? $covers['lpg_kit'] : $covers['cng_kit'];

        $data_response = [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'status' => true,
            'msg' => 'Found',
            'Data' => [
                'idv' => $is_liability ? 0 : $idv,
                'min_idv' => $is_liability ? 0 : ($min_idv),
                'max_idv' => $is_liability ? 0 : ($max_idv),
                'qdata' => NULL,
                'pp_enddate' => ($is_new ? '' : $requestData->previous_policy_expiry_date),
                'addonCover' => NULL,
                'addon_cover_data_get' => '',
                'rto_decline' => NULL,
                'rto_decline_number' => NULL,
                'mmv_decline' => NULL,
                'mmv_decline_name' => NULL,
                'policy_type' => (($is_package) ? 'Comprehensive' : (($is_liability) ? 'Third Party' : 'Own Damage')),
                'cover_type' => '1YC',
                'hypothecation' => '',
                'hypothecation_name' => "", //$premium_type == 'third_party' ? 0 : $result['result']['policy']['idv']['calculated'],
                'vehicle_registration_no' => $requestData->rto_code,
                'voluntary_excess' => 0,
                'version_id' => $requestData->version_id,
                'selected_addon' => [],
                'showroom_price' => $is_liability ? 0 : $idv,
                'fuel_type' => $requestData->fuel_type,
                'vehicle_idv' => $is_liability ? 0 : $idv,
                'ncb_discount' => ($is_liability ? 0 : $requestData->applicable_ncb),
                'company_name' => $productData->company_name,
                'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                'product_name' => $productData->product_name,
                'mmv_detail' => [
                    'manf_name'             => $mmv->make_name,
                    'model_name'            => $mmv->model_name,
                    'version_name'          => $mmv->variant_name,
                    'fuel_type'             => $mmv->fuel_type,
                    'seating_capacity'      => $mmv->seating_capacity,
                    'cubic_capacity'        => $mmv->cubic_capacity,
                ],
                'master_policy_id' => [
                    'policy_id' => $productData->policy_id,
                    'policy_no' => $productData->policy_no,
                    'policy_start_date' => $policy_start_date,
                    'policy_end_date' => $policy_end_date,
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
                    'predefine_series' => "",
                    'is_premium_online' => $productData->is_premium_online,
                    'is_proposal_online' => $productData->is_proposal_online,
                    'is_payment_online' => $productData->is_payment_online
                ],
                'motor_manf_date' => $motor_manf_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => [
                    'master_policy_id' => $productData->policy_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'segment_id' => 0,
                    'rto_cluster_id' => 0,
                    'car_age' => $vehicle_age,
                    'aai_discount' => 0,
                    'ic_vehicle_discount' => '',
                ],
                'basic_premium' => $covers['own_damage_basic'],
                'deduction_of_ncb' => $covers['ncb'],
                'tppd_premium_amount' => $covers['third_party_basic'],
                'motor_electric_accessories_value' => $covers['electrical_accessories'],
                'motor_non_electric_accessories_value' => $covers['non_electrical_accessories'],
                'motor_lpg_cng_kit_value' => $cng_lpg_od,
                'cover_unnamed_passenger_value' => $covers['optional_unnamed_persons'],
                'seating_capacity' => $mmv->seating_capacity,
                // 'default_paid_driver' => $covers['legal_liability_driver_cleaner'],
                'motor_additional_paid_driver' => $covers['optional_pa_paid_driver_cleaner'],
                'GeogExtension_ODPremium' => 0, // $geog_Extension_OD_Premium,
                'GeogExtension_TPPremium' => 0, // $geog_Extension_TP_Premium,
                'compulsory_pa_own_driver' => $covers['compulsory_pa'],
                'total_accessories_amount(net_od_premium)' => "",
                'total_own_damage' => $total_tp_premium,
                'cng_lpg_tp' => $lpg_cng_tp_amount,
                'total_liability_premium' => $total_tp_premium,
                'net_premium' => $basePremium,
                'service_tax_amount' => "",
                'service_tax' => 18,
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => $final_premium,
                'antitheft_discount' => '0', //need included
                'final_od_premium' => $total_od_premium ?? 0,
                'final_tp_premium' => $total_tp_premium ?? 0,
                'final_total_discount' => $total_discount ?? 0,
                'final_net_premium' => $final_premium ?? 0,
                'final_gst_amount' => $totalTax ?? 0,
                'final_payable_amount' => $final_premium ?? 0,
                'service_data_responseerr_msg' => '',
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'business_type' => ($is_new ? 'New Business' : ($is_breakin ? 'Break-in' : 'Roll over')),
                'service_err_code' => NULL,
                'service_err_msg' => NULL,
                'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
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
                'add_ons_data' =>    $add_on_data,
                'applicable_addons' => $applicable_addons,
            ]
        ];

        if(isset($cpa_tenure))
        {
        if($requestData->business_type == 'newbusiness' && $cpa_tenure  == '3')
        {
            // unset($data_response['Data']['compulsory_pa_own_driver']);
            $data_response['Data']['multi_Year_Cpa'] =  $covers['compulsory_pa'];
        }
        }
        if ($is_tppd) {
            $data_response['Data']['tppd_discount'] = $tppd_discount;
        }

        if (!empty($llpaidemp_premium)) {
            $data_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $llpaidemp_premium;
            $data_response['Data']['LegalLiabilityToEmployee'] = $llpaidemp_premium;
        }

        $included_additional = [
            'included' =>[]
        ];
        $data_response['Data']['default_paid_driver'] = $covers['legal_liability_driver_cleaner'];
        if (!empty($covers['legal_liability_driver_cleaner']) && !$is_individual) {
            $included_additional['included'][] = 'defaultPaidDriver';
        }
        $data_response['Data']['included_additional'] = $included_additional;
        return camelCase($data_response);
    } else {
        return  [
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'premium_amount'    => '0',
            'status'            => false,
            'message'           => 'Car Insurer Not found',
        ];
    }
}
function nic_mmv_check_v2($mmv)
{
    if ($mmv['status'] == 1) {
        $mmv_data = $mmv['data']['nic_mmv_details'];
        $mmv_versioncode = $mmv['data']['ic_version_code'];
    } else {
        return    [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ]
        ];
    }
        $mmv = (object) array_change_key_case((array) $mmv_data, CASE_LOWER);

    if (empty($mmv_versioncode) || $mmv_versioncode == '') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle Not Mapped',
            ]
        ]);
    } elseif ($mmv_versioncode == 'DNE') {
        return camelCase([
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'mmv' => $mmv,
                'message' => 'Vehicle code does not exist with Insurance company',
            ]
        ]);
    } else {
        return ['status' => true, 'mmv_data' => $mmv];
    }
}
