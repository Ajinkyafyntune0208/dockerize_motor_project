<?php
namespace App\Quotes\Car\V2;
use DateTime;
use App\Models\MasterPremiumType;
use App\Models\SelectedAddons;
use App\Models\CvAgentMapping;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Carbon;

class GodigitZDPlan
{
    function getQuote($enquiryId, $requestData, $productData)
    {
        $refer_webservice = $productData->db_config['quote_db_cache'];
        if (empty($requestData->rto_code)) 
        {
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
        if ($requestData->ownership_changed == 'Y') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quotes cannot be generated because ownership change is not allowed.',
                'request' => [
                    'message' => 'Quotes cannot be generated because ownership change is not allowed.',
                ]
            ];
        }
        $mmv = get_mmv_details($productData, $requestData->version_id, 'godigit');
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
                    'mmv' => $mmv,
                ]
            ];
        }
        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle not mapped',
                ]
            ];
        } 
        elseif ($mmv->ic_version_code == 'DNE') 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ]
            ];
        }

        if ($productData->good_driver_discount == 'Yes' && $requestData->business_type == 'rollover') 
        {
            if (strtotime($requestData->previous_policy_expiry_date) <= strtotime(date('d-m-Y', time()))) 
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Good Driver Discount is not available',
                    'request' => [
                        'mmv' => $mmv,
                        'message' => 'Good Driver Discount is not available',
                    ]
                ];
            }
        }

        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        $mmv_data = [
            'manf_name' => $mmv->make_name,
            'model_name' => $mmv->model_name,
            'version_name' => $mmv->variant_name,
            'seating_capacity' => $mmv->seating_capacity,
            'carrying_capacity' => $mmv->seating_capacity - 1,
            'cubic_capacity' => $mmv->cubic_capacity,
            'fuel_type' =>  $mmv->fuel_type,
            'gross_vehicle_weight' => $mmv->gross_vehicle_weight ?? NULL,
            'vehicle_type' => 'CAR',
            'version_id' => $mmv->ic_version_code,
        ];

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
                        ->pluck('premium_type_code')
                        ->first();
        if ($premium_type == 'third_party_breakin') 
        {
            $premium_type = 'third_party';
        }
        if ($premium_type == 'own_damage_breakin') 
        {
            $premium_type = 'own_damage';
        }
        if ($premium_type == 'comprehensive') 
        {
            $OrgPrevPolType = '1OD_1TP';
        } 
        elseif ($premium_type == 'own_damage') 
        {
            $OrgPrevPolType = '1OD_0TP';
        } 
        elseif ($premium_type == 'third_party') 
        {
            $OrgPrevPolType = '0OD_1TP';
        } 
        else 
        {
            $OrgPrevPolType = NULL;
        }
        $no_claim_bonus = [
            '0'  => 'ZERO',
            '20' => 'TWENTY',
            '25' => 'TWENTY_FIVE',
            '35' => 'THIRTY_FIVE',
            '45' => 'FORTY_FIVE',
            '50' => 'FIFTY',
            '55' => 'FIFTY_FIVE',
            '65' => 'SIXTY_FIVE',
        ];
        if ($premium_type == 'third_party') 
        {
            $insurance_product_code = '20102';
            $previousNoClaimBonus = 'ZERO';
        } 
        else 
        {
            $insurance_product_code = '20101';
            $ncb_percent = $requestData->previous_ncb;
            $previousNoClaimBonus = $no_claim_bonus[$ncb_percent] ?? 'ZERO';
        }
        //car age
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = ceil($age / 12);

        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        $motor_manf_date = '01-' . $requestData->manufacture_year;
        $is_vehicle_new = 'false';
        $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $sub_insurance_product_code = 'PB';
        $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin' || $premium_type == 'own_damage') 
        {
            $is_vehicle_new = 'false';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $sub_insurance_product_code = 'PB';
            $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            // $cpa = 1;
            
            if ($requestData->business_type == 'breakin') 
            {
                $breakin_make_time = strtotime('18:00:00');
                if ($premium_type != 'third_party') 
                {
                    if ($breakin_make_time > time()) 
                    {
                        $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                    } 
                    else 
                    {
                        $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));
                    }
                } 
                else 
                {
                    $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                }
            }
        } 
        elseif ($requestData->business_type == 'newbusiness') 
        {
            $policy_type = 'Comprehensive';
            $requestData->vehicle_register_date = date('d-m-Y');
            $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
            if ($date_difference > 0) {
                return [
                    'status' => false,
                    'message' => 'Please Select Current Date for New Business',
                    'request' => [
                        'message' => 'Please Select Current Date for New Business',
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                    ]
                ];
            }
            // $cpa = 3;
            $is_vehicle_new = 'true';
            $policy_start_date = Carbon::today()->format('Y-m-d');
            $sub_insurance_product_code = 31;
            $previousNoClaimBonus = 'ZERO';

            if ($requestData->vehicle_registration_no == 'NEW') 
            {
                $vehicle_registration_no  = str_replace("-", "", RtoCodeWithOrWithoutZero($requestData->rto_code, true));
            } 
            else 
            {
                $vehicle_registration_no  = str_replace("-", "", $requestData->vehicle_registration_no);
            }
        }

        $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        if ($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') 
        {
            $expdate = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $vehicle_in_90_days = $date_difference = get_date_diff('day', $expdate);
            $policy_type = 'Comprehensive';
            if ($date_difference > 90) 
            {
                $previousNoClaimBonus = 'ZERO';
            }
        }
        if ($premium_type == 'third_party') 
        {
            $sub_insurance_product_code = 'PB';
            $policy_type = 'Third Party';
        }
        if ($premium_type == 'own_damage') 
        {
            $insurance_product_code = '20103';
            $policy_type = 'Own Damage';
        }
        if ($requestData->is_claim == 'Y') 
        {
            $previousNoClaimBonus = 'ZERO';
        }
        if ($requestData->previous_policy_type == 'Third-party') 
        {
            $previousNoClaimBonus = 'ZERO';
        }
        if ($requestData->vehicle_owner_type == "C") {
            $cpa = 0;
        }
        $voluntary_deductible_amount = 'ZERO';
        $cng_lpg_amt = $non_electrical_amt = $electrical_amt = null;

        if ($productData->product_identifier == 'zero_dep_double_claim') 
        {
            $claims_covered = 'TWO';
            $zero_dep = 'true';
        } 
        elseif ($productData->product_identifier == 'zero_dep_unlimited_claim') 
        {
            $claims_covered = 'UNLIMITED';
            $zero_dep = 'true';
        } 
        elseif ($productData->product_identifier == 'zero_dep') 
        {
            $claims_covered = 'ONE';
            $zero_dep = 'true';
        } 
        else {
            $claims_covered = NULL;
            $zero_dep = 'false';
        }
        if ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party') 
        {
            $sub_insurance_product_code = 30;
        }
        $is_tppd = false;
        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts','compulsory_personal_accident')
            ->first();
            $cpa = 0;
            if ($additional && $additional->compulsory_personal_accident != NULL && $additional->compulsory_personal_accident != '') {
                $addons = $additional->compulsory_personal_accident;
                foreach ($addons as $value) {
                    if (isset($value['name']) && ($value['name'] == 'Compulsory Personal Accident')) {
                            $cpa = isset($value['tenure']) ? $value['tenure'] : '1';

                    }
                }
            }

            if ($requestData->vehicle_owner_type == 'I' && $premium_type != "own_damage" )
            {
                if($requestData->business_type == 'newbusiness')
                {
                    $cpa = isset($cpa) ? $cpa :3; 
                }
                else{
                    $cpa = isset($cpa) ? $cpa :1;
                }
            }
        if (!empty($additional['discounts'])) 
        {
            foreach ($additional['discounts'] as $data) 
            {
                if ($data['name'] == 'TPPD Cover') 
                {
                    $is_tppd = true;
                }
            }
        }

        $is_lpg_cng = false;
        if (!empty($additional['accessories'])) 
        {
            foreach ($additional['accessories'] as $key => $data) 
            {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') 
                {
                    $cng_lpg_amt = $data['sumInsured'];
                    $is_lpg_cng = true;
                }

                if ($data['name'] == 'Non-Electrical Accessories') 
                {
                    $non_electrical_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'Electrical Accessories') 
                {
                    $electrical_amt = $data['sumInsured'];
                }
            }
        }

        if (isset($cng_lpg_amt) && ($cng_lpg_amt < 15000 || $cng_lpg_amt > 80000)) 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'CNG/LPG Insured amount, min = Rs.15000  & max = Rs.80000',
                'request' => [
                    'message' => 'CNG/LPG Insured amount, min = Rs.15000  & max = Rs.80000',
                    'cng_lpg_amt' => $cng_lpg_amt,
                ]
            ];
        }

        if (isset($non_electrical_amt) && ($non_electrical_amt < 412 || $non_electrical_amt > 82423)) 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Non-Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                'request' => [
                    'message' => 'Non-Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                    'non_electrical_amt' => $non_electrical_amt,
                ]
            ];
        }

        if (isset($electrical_amt) && ($electrical_amt < 412 || $electrical_amt > 82423)) 
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                'request' => [
                    'message' => 'Electrical Accessories Insured amount, min = Rs.412  & max = Rs.82423',
                    'electrical_amt' => $electrical_amt,
                ]
            ];
        }

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = null;
        $no_of_driverLL = 0;
        $paidDriverLL = "false";
        $paidDriverEMP = false;
        if($requestData->vehicle_owner_type == "C")
        {
            $paidDriverEMP = true;
        }
        if (!empty($additional['additional_covers'])) 
        {
            foreach ($additional['additional_covers'] as $data) 
            {
                if ($data['name'] == 'LL paid driver') 
                {
                    $no_of_driverLL = 1;
                    $paidDriverLL = "true";
                }
            }
        }
        if (!empty($additional['additional_covers'])) 
        {
            foreach ($additional['additional_covers'] as $key => $data) 
            {
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) 
                {
                    $cover_pa_paid_driver = $data['sumInsured'];
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured'])) 
                {
                    $cover_pa_unnamed_passenger = $data['sumInsured'];
                }
            }
        }
        $is_pos = 'false';
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_testing_mode = config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_GODIGIT');
        $posp_name = '';
        $posp_unique_number = '';
        $posp_pan_number = '';
        $posp_aadhar_number = '';
        $posp_contact_number = '';
        $posp_location = '';

        $pos_data = CvAgentMapping::where('user_product_journey_id', $requestData->user_product_journey_id)
                    ->where('seller_type', 'P')
                    ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') 
        {
            if ($pos_data) 
            {
                $is_pos = 'true';
                $posp_name = $pos_data->agent_name;
                $posp_unique_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
                $posp_pan_number = $pos_data->pan_no;
                $posp_aadhar_number = $pos_data->aadhar_no;
                $posp_contact_number = $pos_data->agent_mobile != NULL ? $pos_data->agent_mobile : '';
                $posp_location = $pos_data->region_name;
            }
            if ($is_pos_testing_mode == 'Y') 
            {
                $is_pos = 'true';
                $posp_name = 'test';
                $posp_unique_number = '9768574564';
                $posp_pan_number = 'ABGTY8890Z';
                $posp_aadhar_number = '569278616999';
                $posp_contact_number = '9768574564';
            }
        } 
        else if ($is_pos_testing_mode == 'Y') 
        {
            $is_pos = 'true';
            $posp_name = 'test';
            $posp_unique_number = '9768574564';
            $posp_pan_number = 'ABGTY8890Z';
            $posp_aadhar_number = '569278616999';
            $posp_contact_number = '9768574564';
        }
        $returnToInvoice = false;
        $tyreSecure = false;
        $engineProtector = false;
        $consum = false;
        $personal_Belonging = false;
        $keyAndLock_Protect = false;
        $roadSideAssistance = false;
        $zero_dep = false;
        #addon
        switch ($productData->product_identifier) 
        {
            case "PRO":
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                break;
            case "D-PRO":
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DC-PRO":
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DCE-PRO":
                $engineProtector = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DCT-PRO":
                $tyreSecure = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DCET-PRO":
                $tyreSecure = true;
                $engineProtector = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DC-RTIPRO":
                $returnToInvoice = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DCE-RTIPRO":
                $returnToInvoice = true;
                $engineProtector = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DCT-RTIPRO":
                $returnToInvoice = true;
                $tyreSecure = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;

            case "DCET-RTIPRO":
                $returnToInvoice = true;
                $tyreSecure = true;
                $engineProtector = true;
                $consum = true;
                $personal_Belonging = true;
                $keyAndLock_Protect = true;
                $roadSideAssistance = true;
                $zero_dep = true;
                break;
        }
        $access_token_resp = getToken($enquiryId, $productData,'quote','renewal');
        $access_token = ($access_token_resp['token']);
        // $previousInsurerCode = "159";
        if ($premium_type == "own_damage" || $requestData->business_type == 'rollover' || ($requestData->business_type == 'breakin' && $date_difference <= 90)) {
            $previousInsurerCode = PreviousInsurerList::
                  where('name', $requestData->previous_insurer)
                ->where('company_alias', "godigit")
                ->pluck('code')
                ->first();
            $previousInsurerCode = !empty($previousInsurerCode) ? $previousInsurerCode : '159';        
        }
        $isPreviousInsurerKnown = true;
        if (($requestData->previous_policy_type == 'Third-party' && !in_array($premium_type, ['third_party', 'third_party_breakin'])) || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New') 
        {
            $isPreviousInsurerKnown = false;
            $previousInsurerCode = "";
        }
        if($zero_dep)
        {
            $claims_covered = strtoupper(config('godigit_claim_covered'));

            if (!empty($additional['addons'])) {
                foreach ($additional['addons'] as $addons_value) {
                    if ($addons_value['name'] === 'Zero Depreciation' && !empty($addons_value['claimCovered'])) {
                        $claims_covered = strtoupper($addons_value['claimCovered']);
                    }
                }
            }
            
            if(!(in_array($claims_covered,['ONE','TWO','UNLIMITED'])))
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => 'Invalid zero dep cover value : '.$claims_covered
                ];
            }
        }

        $registrationAuthority = "";
        $rto_code_check = explode('-', $requestData->rto_code);
        if (isBhSeries($requestData->vehicle_registration_no) && isset($rto_code_check) && $rto_code_check[0] == 'DL') {
            $parts = explode('-', $requestData->rto_code);
            $registrationAuthority = str_replace('-','',sprintf('%s-%02d', $parts[0] ?? '', is_numeric($parts[1] ?? '') ? $parts[1] : 0));
        } else {
            $registrationAuthority = str_replace('-','',godigitRtoCode(trim($requestData->rto_code)));
        }
        $premium_req_array = [ 
            "motorQuickQuote" => 
            [
            'enquiryId' => ($premium_type == "own_damage") ? 'GODIGIT_QQ_PVT_CAR_SAOD_01' : 'GODIGIT_QQ_PVT_CAR_PACKAGE_01',
            'contract' => [
                'insuranceProductCode' => $insurance_product_code,
                'subInsuranceProductCode' => $sub_insurance_product_code,
                'startDate' => $policy_start_date,
                'endDate' => $policy_end_date,
                'policyHolderType' => $policy_holder_type,
                'externalPolicyNumber' => NULL,
                'isNCBTransfer' => NULL,
                'coverages' => [
                    'voluntaryDeductible' => $voluntary_deductible_amount,
                    'thirdPartyLiability' => [
                        'isTPPD' => $is_tppd,
                    ],
                    'ownDamage' => [
                        'discount' => [
                            'userSpecialDiscountPercent' => 0,
                            'discounts' => [],
                        ],
                        'surcharge' => [
                            'loadings' => [],
                        ],
                    ],
                    'personalAccident' => [
                        'selection' => $requestData->vehicle_owner_type == "I" ? true : false,
                        'insuredAmount' => ($cpa != 0) ? 1500000 : 0,
                        'coverTerm' => ($cpa != 0) ? $cpa : null,
                    ],
                    'accessories' => [
                        'cng' => [
                            'selection' => !empty($cng_lpg_amt) ? true : false,
                            'insuredAmount' => !empty($cng_lpg_amt) ? $cng_lpg_amt : 0,
                        ],
                        'electrical' => [
                            'selection' => !empty($electrical_amt) ? true : false,
                            'insuredAmount' => !empty($electrical_amt) ? $electrical_amt : 0,
                        ],
                        'nonElectrical' => [
                            'selection' => !empty($non_electrical_amt) ? true : false,
                            'insuredAmount' => !empty($non_electrical_amt) ? $non_electrical_amt : 0,
                        ],
                    ],
                    'addons' => [
                        'partsDepreciation' => [
                            'claimsCovered' => $claims_covered,
                            'selection' => $zero_dep,
                        ],
                        'roadSideAssistance' => [
                            'selection' => $roadSideAssistance,
                        ],
                        'personalBelonging' => [
                            'selection' => $personal_Belonging,
                        ],
                        'keyAndLockProtect' => [
                            'selection' => $keyAndLock_Protect,
                        ],
                        'engineProtection' => [
                            'selection' => $engineProtector,
                        ],
                        'tyreProtection' => [
                            'selection' => $tyreSecure,
                        ],
                        'rimProtection' => [
                            'selection' => false,
                        ],
                        'returnToInvoice' => [
                            'selection' => $returnToInvoice,
                        ],
                        'consumables' => [
                            'selection' => $consum,
                        ],
                    ],
                    'legalLiability' => [
                        'paidDriverLL' => [
                            'selection' => $paidDriverLL,
                            'insuredCount' => $no_of_driverLL,
                        ],
                        'employeesLL' => [
                            'selection' => $paidDriverEMP,
                            'insuredCount' => $mmv->seating_capacity - 1,
                        ],
                        'unnamedPaxLL' => [
                            'selection' => false,
                            'insuredCount' => NULL,
                        ],
                        'cleanersLL' => [
                            'selection' => false,
                            'insuredCount' => NULL,
                        ],
                        'nonFarePaxLL' => [
                            'selection' => false,
                            'insuredCount' => NULL,
                        ],
                        'workersCompensationLL' => [
                            'selection' => false,
                            'insuredCount' => NULL,
                        ],
                    ],
                    'unnamedPA' => [
                        'unnamedPax' => [
                            'selection' => !empty($cover_pa_unnamed_passenger) ? true : false,
                            'insuredAmount' => !empty($cover_pa_unnamed_passenger) ? $cover_pa_unnamed_passenger : 0,
                            'insuredCount' => NULL,
                        ],
                        'unnamedPaidDriver' => [
                            'selection' => !empty($cover_pa_paid_driver) ? true : false,
                            'insuredAmount' => !empty($cover_pa_paid_driver) ? $cover_pa_paid_driver : 0,
                            'insuredCount' => NULL,
                        ],
                        'unnamedHirer' => [
                            'selection' => false,
                            'insuredAmount' => NULL,
                            'insuredCount' => NULL,
                        ],
                        'unnamedPillionRider' => [
                            'selection' => false,
                            'insuredAmount' => NULL,
                            'insuredCount' => NULL,
                        ],
                        'unnamedCleaner' => [
                            'selection' => false,
                            'insuredAmount' => NULL,
                            'insuredCount' => NULL,
                        ],
                        'unnamedConductor' => [
                            'selection' => false,
                            'insuredAmount' => NULL,
                            'insuredCount' => NULL,
                        ],
                    ],
                ],
            ],
            'vehicle' => [
                'isVehicleNew' => $is_vehicle_new,
                'vehicleMaincode' => $mmv->vehicle_code,
                'licensePlateNumber' => $vehicle_registration_no != "" ? strtoupper($vehicle_registration_no) : str_replace('-', '', RtoCodeWithOrWithoutZero($requestData->rto_code, true)),
                'vehicleIdentificationNumber' => NULL,
                'registrationAuthority' => $registrationAuthority,
                'engineNumber' => NULL,
                'manufactureDate' => date('Y-m-d', strtotime($motor_manf_date)),
                'registrationDate' => date('Y-m-d', strtotime($requestData->vehicle_register_date)),
                'vehicleIDV' => [
                    'idv' =>  0,
                ],
                // 'usageType' => NULL,
                'permitType' => NULL,
                'motorType' => NULL
            ],
            'previousInsurer' => [
                'isPreviousInsurerKnown' => $isPreviousInsurerKnown,
                'previousInsurerCode' => $previousInsurerCode,
                'previousPolicyNumber' => null,
                  // 'previousPolicyExpiryDate' => $requestData->ownership_changed == 'Y' ? date('Y-m-d', strtotime('-91 days', time())) : (!empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null), #previous logic as per #7087
                'previousPolicyExpiryDate' => (!empty($requestData->previous_policy_expiry_date) ? date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) : null), #removed ownership_changed logic by #31785
                'isClaimInLastYear' => ($requestData->is_claim == 'Y') ? 'true' : 'false',
                'originalPreviousPolicyType' => $OrgPrevPolType,
                'previousPolicyType' => NULL,
                'previousNoClaimBonus' => $requestData->business_type == 'newbusiness' ? 'ZERO' : $previousNoClaimBonus,
                'currentThirdPartyPolicy' => NULL,
            ],
            'pospInfo' =>
            [
                'isPOSP' => $is_pos,
                'pospName' => $posp_name,
                'pospUniqueNumber' => $posp_unique_number,
                'pospLocation' => $posp_location,
                'pospPanNumber' => $posp_pan_number,
                'pospAadhaarNumber' => $posp_aadhar_number,
                'pospContactNumber' => $posp_contact_number
            ],
            'pincode' => null, //'421201',
            'PartnerReqId' => null
        ]];
        
        if ($premium_type == "own_damage") 
        {
            $premium_req_array['motorQuickQuote']['previousInsurer']['originalPreviousPolicyType'] = "1OD_3TP";
            $premium_req_array['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['isCurrentThirdPartyPolicyActive'] = true;
            $premium_req_array['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyInsurerCode'] = "158";
            $premium_req_array['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyNumber'] = "D300073312";
            $premium_req_array['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyStartDateTime'] = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            $premium_req_array['motorQuickQuote']['previousInsurer']['currentThirdPartyPolicy']['currentThirdPartyPolicyExpiryDateTime'] = date('Y-m-d', strtotime('+1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
        }

        if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y')
        {
            $is_renewbuy = true; 
        }
        else
        {
            $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
        }
    
        if ($is_renewbuy) 
        {
            $premium_req_array['motorQuickQuote']['pospInfo']['isPOSP']            = false;
            $premium_req_array['motorQuickQuote']['pospInfo']['pospName']          = NULL;
            $premium_req_array['motorQuickQuote']['pospInfo']['pospUniqueNumber']  = NULL;
            $premium_req_array['motorQuickQuote']['pospInfo']['pospLocation']      = NULL;
            $premium_req_array['motorQuickQuote']['pospInfo']['pospPanNumber']     = NULL;
            $premium_req_array['motorQuickQuote']['pospInfo']['pospAadhaarNumber'] = NULL;
            $premium_req_array['motorQuickQuote']['pospInfo']['pospContactNumber'] = NULL;
        }
        
        if(config('IC.GODIGIT.V2.CAR.ENVIRONMENT') == 'UAT'){
            $premium_req_array_for_api = $premium_req_array['motorQuickQuote'];
        }else{
            $premium_req_array_for_api = $premium_req_array;
        }

        $checksum_data = checksum_encrypt($premium_req_array);
        $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'godigit', $checksum_data, 'CAR');
        if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status'])
        {
            $data = $is_data_exist_for_checksum;
        }
        else
        {
            $data = getWsData(
                config('IC.GODIGIT.V2.CAR.END_POINT_URL'),
                $premium_req_array_for_api,
                'godigit',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' => 'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => $productData->product_sub_type_code,
                    'checksum' =>$checksum_data,
                    'method' => 'Premium Calculation' . ($productData->good_driver_discount == 'Yes' ? ' - GDD' : ''),
                    'authorization' => $access_token,
                    'transaction_type' => 'quote',
                    'integrationId' => config('IC.GODIGIT.V2.CAR.QUOTE_INTEGRATION_ID')
                ]
            );
        }
        if (!empty($data['response'])) 
        {
            $response = json_decode($data['response']);
            $skip_second_call = false;
            if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
            {
                if ($premium_type != 'third_party') 
                {
                    $vehicle_idv = round($response->vehicle->vehicleIDV->idv);
                    $min_idv = $response->vehicle->vehicleIDV->minimumIdv;
                    $max_idv = $response->vehicle->vehicleIDV->maximumIdv;
                    $default_idv = round($response->vehicle->vehicleIDV->defaultIdv);
                } 
                else 
                {
                    $vehicle_idv = 0;
                    $min_idv = 0;
                    $max_idv = 0;
                    $default_idv = 0;
                }
                if ($requestData->is_idv_changed == 'Y') 
                {
                    if ($requestData->edit_idv >= $max_idv) 
                    {
                        $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $max_idv;
                        $vehicle_idv = $max_idv;
                    } 
                    elseif ($requestData->edit_idv <= $min_idv) 
                    {
                        $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $min_idv;
                        $vehicle_idv = $min_idv;
                    } 
                    else 
                    {
                        $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $requestData->edit_idv;
                        $vehicle_idv = $requestData->edit_idv;
                    }
                } 
                else 
                {
                    $getIdvSetting = getCommonConfig('idv_settings');
                    switch ($getIdvSetting) 
                    {
                        case 'default':
                            $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $vehicle_idv;
                            $skip_second_call = true;
                            $vehicle_idv =  $vehicle_idv;
                            break;
                        case 'min_idv':
                            $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $min_idv;
                            $vehicle_idv =  $min_idv;
                            break;
                        case 'max_idv':
                            $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $max_idv;
                            $vehicle_idv =  $max_idv;
                            break;
                        default:
                            $premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] = $min_idv;
                            $vehicle_idv =  $min_idv;
                            break;
                    }
                    /* $premium_req_array['vehicle']['vehicleIDV']['idv'] = $min_idv;
                    $vehicle_idv =  $min_idv; */
                }
                if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y')
                {
                    $is_renewbuy = true; 
                }
                else
                {
                    $is_renewbuy = (config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') ? true : false;
                }
                if ($is_renewbuy) 
                {
                    if ($premium_req_array['motorQuickQuote']['vehicle']['vehicleIDV']['idv'] > 5000000) 
                    {
                        $premium_req_array['motorQuickQuote']['pospInfo'] = [
                            'isPOSP'            => 'false',
                            'pospName'          => '',
                            'pospUniqueNumber'  => '',
                            'pospLocation'      => '',
                            'pospPanNumber'     => '',
                            'pospAadhaarNumber' => '',
                            'pospContactNumber' => ''
                        ];
                    } 
                    else 
                    {
                        $premium_req_array['motorQuickQuote']['pospInfo'] = [
                            'isPOSP'            => $is_pos,
                            'pospName'          => $posp_name,
                            'pospUniqueNumber'  => $posp_unique_number,
                            'pospLocation'      => $posp_location,
                            'pospPanNumber'     => $posp_pan_number,
                            'pospAadhaarNumber' => $posp_aadhar_number,
                            'pospContactNumber' => $posp_contact_number
                        ];
                    }
                }
                update_quote_web_servicerequestresponse($data['table'], $data['webservice_id'], 'success', 'success');
                if(config('IC.GODIGIT.V2.CAR.ENVIRONMENT') == 'UAT'){
                    $premium_req_array_for_api = $premium_req_array['motorQuickQuote'];
                }else{
                    $premium_req_array_for_api = $premium_req_array;
                }
                if (!$skip_second_call) 
                {
                    $checksum_data = checksum_encrypt($premium_req_array);
                    $is_data_exist_for_checksum = get_web_service_data_via_checksum($enquiryId, 'godigit', $checksum_data, 'CAR');
                    if($is_data_exist_for_checksum['found'] && $refer_webservice && $is_data_exist_for_checksum['status'])
                    {
                        $data = $is_data_exist_for_checksum;
                    }
                    else
                    {
                        $data = getWsData(
                            config('IC.GODIGIT.V2.CAR.END_POINT_URL'),
                            $premium_req_array_for_api,
                            'godigit',
                            [
                                'enquiryId' => $enquiryId,
                                'requestMethod' => 'post',
                                'productName'  => $productData->product_name,
                                'company'  => 'godigit',
                                'section' => $productData->product_sub_type_code,
                                'checksum' => $checksum_data,
                                'method' => 'Premium Re-Calculation',
                                'authorization' => $access_token,
                                'transaction_type' => 'quote',
                                'integrationId' => config('IC.GODIGIT.V2.CAR.QUOTE_INTEGRATION_ID')
                            ]
                        );
                    }
                }

                if (!empty($data['response'])) 
                {
                    $response = json_decode($data['response']);
                    if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
                    {
                        // $vehicle_idv = round($response->vehicle->vehicleIDV->idv);
                        // $default_idv = round($response->vehicle->vehicleIDV->defaultIdv);
                    } 
                    elseif (!empty($response->error->validationMessages[0])) 
                    {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => str_replace(",", "", $response->error->validationMessages[0])
                        ];
                    } 
                    elseif (isset($response->error->errorCode) && $response->error->errorCode == '400') 
                    {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'premium_amount' => 0,
                            'status' => false,
                            'message' => str_replace(",", "", $response->error->validationMessages[0])
                        ];
                    }
                } 
                else 
                {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable'
                    ];
                }
                //}

                $contract = $response->contract;
                $llpaiddriver_premium = 0;
                $llpaidemp_premium = 0;
                $cover_pa_owner_driver_premium = 0;
                $cover_pa_paid_driver_premium = 0;
                $cover_pa_unnamed_passenger_premium = 0;
                $voluntary_excess = 0;
                $ic_vehicle_discount = 0;
                $ncb_discount_amt = 0;
                $od = 0;
                $cng_lpg_tp = 0;
                $zero_depreciation = 0;
                $road_side_assistance = 0;
                $engine_protection = 0;
                $tyre_protection = 0;
                $return_to_invoice = 0;
                $consumables = 0;
                $personal_belonging = 0;
                $key_and_lock_protection = 0;
                $tppd = 0;
                $tppd_discount = ($is_tppd) ? (($requestData->business_type == 'newbusiness') ? 300 : 100) : 0;
                $geog_Extension_OD_Premium = 0;
                $geog_Extension_TP_Premium = 0;
                foreach ($contract->coverages as $key => $value) 
                {            
                    switch ($key) 
                    {
                        case 'thirdPartyLiability':
                            if (isset($value->netPremium)) 
                            {
                                $tppd = round(str_replace("INR ", "", $value->netPremium));
                            }
                        break;

                        case 'addons':
                            foreach ($value as $key => $addon) 
                            {
                                switch ($key) 
                                {
                                    case 'partsDepreciation':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $zero_depreciation = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'roadSideAssistance':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $road_side_assistance = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'engineProtection':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $engine_protection = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'tyreProtection':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $tyre_protection = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'returnToInvoice':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $return_to_invoice = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'consumables':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $consumables = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'personalBelonging':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) 
                                        {
                                            $personal_belonging = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;

                                    case 'keyAndLockProtect':
                                        if ($addon->selection == 'true' && ($addon->coverAvailability == 'AVAILABLE' || $addon->coverAvailability == 'MANDATORY')) {
                                            $key_and_lock_protection = round(str_replace('INR ', '', $addon->netPremium));
                                        }
                                    break;
                                }
                            }
                        break;

                        case 'ownDamage':
                            if (isset($value->netPremium)) 
                            {
                                $od = round(str_replace("INR ", "", $value->netPremium));

                                foreach ($value->discount->discounts as $key => $type) 
                                {
                                    if ($type->discountType == "NCB_DISCOUNT") {
                                        $ncb_discount_amt = round(str_replace("INR ", "", $type->discountAmount));
                                    }
                                }
                            }
                        break;

                        case 'legalLiability':
                            foreach ($value as $cover => $subcover) 
                            {
                                if ($cover == "paidDriverLL") 
                                {
                                    if ($subcover->selection == 1) 
                                    {
                                        $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                                if ($cover == "employeesLL") 
                                {
                                    if ($subcover->selection == 1) 
                                    {
                                        $llpaidemp_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                    }
                                }
                            }
                        break;

                        case 'personalAccident':
                            // By default Complusory PA Cover for Owner Driver
                            if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium))) 
                            {
                                $cover_pa_owner_driver_premium = round(str_replace("INR ", "", $value->netPremium));
                            }
                        break;

                        case 'accessories':
                        break;

                        case 'unnamedPA':
                            foreach ($value as $cover => $subcover) 
                            {
                                if ($cover == 'unnamedPaidDriver') 
                                {
                                    if (isset($subcover->selection) && $subcover->selection == 1) 
                                    {
                                        if (isset($subcover->netPremium)) 
                                        {
                                            $cover_pa_paid_driver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                        }
                                    }
                                }

                                if ($cover == 'unnamedPax') 
                                {
                                    if (isset($subcover->selection) && $subcover->selection == 1) 
                                    {
                                        if (isset($subcover->netPremium)) 
                                        {
                                            $cover_pa_unnamed_passenger_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                        }
                                    }
                                }
                            }
                        break;
                    }
                }
                if ((isset($cng_lpg_amt) && !empty($cng_lpg_amt)) || $mmv->fuel_type == 'CNG' || $mmv->fuel_type == 'LPG') 
                {
                    $cng_lpg_tp = ($premium_type == 'own_damage') ? 0 : (($requestData->business_type == 'newbusiness') ? 180 : 60);
                    $tppd = $tppd - $cng_lpg_tp;
                }

                $ncb_discount = $ncb_discount_amt;
                $final_od_premium = $od;
                $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $tppd_discount + $llpaidemp_premium;
                $final_total_discount = round($ncb_discount + $voluntary_excess + $ic_vehicle_discount + $tppd_discount);
                $final_net_premium   = round($final_od_premium + $final_tp_premium - $final_total_discount);
                $final_gst_amount   = round($final_net_premium * 0.18);
                $final_payable_amount  = $final_net_premium + $final_gst_amount;
                #package wise addon
                $add_ons_data = [];
                switch ($productData->product_identifier) 
                {
                    case "PRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'PRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }

                    break;

                    case "D-PRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];

                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'D-PRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }

                    break;

                    case "DC-PRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DC-PRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;

                    case "DCE-PRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'engine_protector' => $engine_protection,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $engine_protection == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DCE-PRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;

                    case "DCT-PRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'tyre_secure' => $tyre_protection,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $tyre_protection == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DCT-PRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;

                    case "DCET-PRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'tyre_secure' => $tyre_protection,
                                    'engine_protector' => $engine_protection,
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $tyre_protection == 0 || $engine_protection == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DCET-PRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;

                    case "DC-RTIPRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'return_to_invoice' => $return_to_invoice
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $return_to_invoice == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DC-RTIPRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;

                    case "DCE-RTIPRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'return_to_invoice' => $return_to_invoice,
                                    'engine_protector' => $engine_protection
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $return_to_invoice == 0 || $engine_protection == 0) 
                        {
                            $returnToInvoice = 'true';
                            $engineProtector = 'true';
                            $consum = 'true';
                            $personal_Belonging = 'true';
                            $keyAndLock_Protect = 'true';
                            $roadSideAssistance = 'true';
                            $zero_dep = 'true';

                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DCE-RTIPRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                        break;

                    case "DCT-RTIPRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'return_to_invoice' => $return_to_invoice,
                                    'tyre_secure' => $tyre_protection
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $return_to_invoice == 0 || $tyre_protection == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DCT-RTIPRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;

                    case "DCET-RTIPRO":
                        $add_ons_data =
                            [
                                'in_built'   => [
                                    'road_side_assistance' => $road_side_assistance,
                                    'lopb' => $personal_belonging,
                                    'key_replace' => $key_and_lock_protection,
                                    'zero_depreciation' => $zero_depreciation,
                                    'consumables' => $consumables,
                                    'return_to_invoice' => $return_to_invoice,
                                    'tyre_secure' => $tyre_protection,
                                    'engine_protector' => $engine_protection
                                ],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                        if ($personal_belonging == 0 || $key_and_lock_protection == 0 || $road_side_assistance == 0 || $zero_depreciation == 0 || $consumables == 0 || $return_to_invoice == 0 || $tyre_protection == 0 || $engine_protection == 0) 
                        {
                            return [
                                'webservice_id' => $data['webservice_id'],
                                'table' => $data['table'],
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'DCET-RTIPRO Package Is Not Applicable Beacause Some Of Addon Having Zero Value.'
                            ];
                        }
                    break;
                    default:
                        $add_ons_data =
                            [
                                'in_built'   => [],
                                'additional' => [
                                    'zero_depreciation' => 0,
                                    'road_side_assistance' => 0,
                                    'engine_protector' => 0,
                                    'ncb_protection' => 0,
                                    'key_replace' => 0,
                                    'consumables' => 0,
                                    'tyre_secure' => 0,
                                    'return_to_invoice' => 0,
                                    'lopb' => 0
                                ]
                            ];
                    break;
                }
                #applicable addon start
                $applicable_addons = [
                    'zeroDepreciation', 'roadSideAssistance', 'keyReplace', 'lopb', 'engineProtector', 'consumables', 'tyreSecure', 'returnToInvoice'
                ];
            
                $data_response = [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status' => true,
                    'msg' => 'Found',
                    'kit_v' => 2.1,
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
                        'policy_type' => $policy_type,
                        'cover_type' => '1YC',
                        'hypothecation' => '',
                        'hypothecation_name' => '',
                        'vehicle_registration_no' => $requestData->rto_code,
                        'voluntary_excess' => 0,
                        'version_id' => $mmv->ic_version_code,
                        'selected_addon' => [],
                        'showroom_price' => $vehicle_idv,
                        'fuel_type' => $mmv->fuel_type,
                        'ncb_discount' => $requestData->applicable_ncb,
                        'company_name' => $productData->company_name,
                        'company_logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                        'product_name' => $productData->product_sub_type_name . ' - ' . strtoupper($productData->product_identifier),
                        'mmv_detail' => $mmv_data,
                        'vehicle_register_date' => $requestData->vehicle_register_date,
                        'master_policy_id' => [
                            'policy_id' => $productData->policy_id,
                            'policy_no' => $productData->policy_no,
                            'policy_start_date' => date('d-m-Y', strtotime($contract->startDate)),
                            'policy_end_date' => date('d-m-Y', strtotime($contract->endDate)),
                            'sum_insured' => $productData->sum_insured,
                            //'corp_client_id' => $productData->corp_client_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'insurance_company_id' => $productData->company_id,
                            'status' => $productData->status,
                            //'corp_name' => "Ola Cab",
                            'company_name' => $productData->company_name,
                            'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                            'product_sub_type_name' => $productData->product_sub_type_name,
                            'flat_discount' => $productData->default_discount,
                            //'predefine_series' => "",
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
                        'deduction_of_ncb' => round($ncb_discount),
                        'tppd_premium_amount' => $tppd + $tppd_discount,
                        'tppd_discount' => $tppd_discount,
                        // 'motor_electric_accessories_value' => 0,
                        // 'motor_non_electric_accessories_value' => 0,
                        //  'motor_lpg_cng_kit_value' => '0',
                        'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                        'seating_capacity' => $mmv->seating_capacity,
                        'default_paid_driver' => $llpaiddriver_premium,
                        'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                        'GeogExtension_ODPremium'                     => $geog_Extension_OD_Premium,
                        'GeogExtension_TPPremium'                     => $geog_Extension_TP_Premium,
                        'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                        'total_accessories_amount(net_od_premium)' => 0,
                        'total_own_damage' => ($premium_type == 'third_party') ? 0 : $final_od_premium,
                        //  'cng_lpg_tp' => $cng_lpg_tp,
                        'total_liability_premium' => $final_tp_premium,
                        'net_premium' => $final_net_premium,
                        'service_tax_amount' => round($final_gst_amount),
                        'service_tax' => 18,
                        'total_discount_od' => 0,
                        'add_on_premium_total' => 0,
                        'addon_premium' => 0,
                        //  'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                        'quotation_no' => '',
                        'premium_amount'  => $final_payable_amount,
                        'antitheft_discount' => 0,
                        'final_od_premium' => ($premium_type == 'third_party') ? 0 : $final_od_premium,
                        'final_tp_premium' => $final_tp_premium,
                        'final_total_discount' => $final_total_discount,
                        'final_net_premium' => $final_net_premium,
                        'final_gst_amount' => $final_gst_amount,
                        'final_payable_amount' => $final_payable_amount,
                        'service_data_responseerr_msg' => 'success',
                        'user_id' => $requestData->user_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'user_product_journey_id' => $requestData->user_product_journey_id,
                        'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : (($requestData->business_type == "breakin" || ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party')) ? 'Breakin' : 'Roll over'),
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
                        "max_addons_selection" => NULL,
                        "applicable_addons" => $applicable_addons,
                        'add_ons_data' => $add_ons_data,
                        'claims_covered' => $claims_covered,
                        'zd_claim_selection' => ''
                    ],
                ];
                $included_additional = [
                    'included' => []
                ];

                if(isset($cpa))
                {
                if($requestData->business_type == 'newbusiness' && $cpa  == 3)
                {
                    // unset($data_response['Data']['compulsory_pa_own_driver']);
                    $data_response['Data']['multi_Year_Cpa'] =  $cover_pa_owner_driver_premium;
                }
                }
                if (!empty($llpaidemp_premium) && $requestData->vehicle_owner_type == 'C') 
                {
                    $data_response['Data']['other_covers']['LegalLiabilityToEmployee'] = $llpaidemp_premium;
                    $data_response['Data']['LegalLiabilityToEmployee'] = $llpaidemp_premium;
                }
                if ($is_lpg_cng || in_array($mmv->fuel_type, ['CNG', 'PETROL+CNG', 'DIESEL+CNG', 'LPG'])) 
                {
                    $data_response['Data']['cng_lpg_tp'] = round($cng_lpg_tp);
                    $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                    $data_response['Data']['motor_lpg_cng_kit_value'] = 0;
                    $included_additional['included'][] = 'motorLpgCngKitValue';
                }
                if(!empty($electrical_amt))
                {
                    $data_response['Data']['motor_electric_accessories_value'] = 0;
                    $included_additional['included'][] = 'motorElectricAccessoriesValue';
                }
                if(!empty($non_electrical_amt))
                {
                    $data_response['Data']['motor_non_electric_accessories_value'] = 0;
                    $included_additional['included'][] = 'motorNonElectricAccessoriesValue';
                }
                $data_response['Data']['included_additional'] = $included_additional;
                return camelCase($data_response);
            } 
            elseif (!empty($response->error->validationMessages[0])) 
            {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => str_replace(",", "", $response->error->validationMessages[0])
                ];
            } 
            elseif (isset($response->error->errorCode) && $response->error->errorCode == '400') 
            {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => str_replace(",", "", $response->error->validationMessages[0])
                ];
            } 
            else 
            {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'status' => false,
                    'premium_amount' => 0,
                    'message' => (isset($response->error->validationMessages[0]) ? str_replace(",", "", $response->error->validationMessages[0]) : 'Invalid repsponse received from IC service')
                ];
            }
        } 
        else 
        {
            return [
                'webservice_id' => $data['webservice_id'],
                'table' => $data['table'],
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }
    }
}