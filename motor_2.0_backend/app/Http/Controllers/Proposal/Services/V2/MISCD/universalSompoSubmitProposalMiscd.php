<?php

namespace App\Http\Controllers\Proposal\Services\V2\MISCD;

use App\Http\Controllers\SyncPremiumDetail\Services\UniversalSompoPremiumDetailController;
use App\Models\CvBreakinStatus;
use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use Carbon\Carbon;
use DateTime;


include_once app_path() . '/Helpers/CvWebServiceHelper.php';
include_once app_path() . '/Quotes/Cv/V2/MISCD/universal_sompo_miscd_v2.php';


class universalSompoSubmitProposalMiscd
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request, $step = '')
    {
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $mmv = get_mmv_details($productData, $requestData->version_id, $productData->company_alias);
        $mmv = $mmv['data'];
        // if ($mmv['status'] == 1) {
        //     $mmv = $mmv['data'];
        // } else {
        //     return  [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => $mmv['message']
        //     ];
        // }
        $GVW = $mmv['GVW'] ?? 0;
        // dD($mmv);
        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
        // dd($mmv_data);
        // if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        //     return  [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Vehicle Not Mapped',
        //     ];
        // } else if ($mmv_data->ic_version_code == 'DNE') {
        //     return  [
        //         'premium_amount' => 0,
        //         'status' => false,
        //         'message' => 'Vehicle code does not exist with Insurance company',
        //     ];
        // }

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();


        if ($premium_type == 'breakin') {
            $premium_type = 'comprehensive';
        }
        if ($premium_type == 'third_party_breakin') {
            $premium_type = 'third_party';
        }
        if ($premium_type == 'own_damage_breakin') {
            $premium_type = 'own_damage';
        }

        // car age calculation
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;

        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $miscd_age = $age / 12;
        $miscd_age = round($miscd_age, 2);

        $additional_details = json_decode($proposal['additional_details']);

        //Owner Age
        $DOB = $additional_details->owner->dob;
        $owner_age = Carbon::createFromFormat('d-m-Y', $DOB)->age;


        // if (strtolower($mmv_data->manufacturer) != 'honda' && strtolower($mmv_data->manufacturer) != 'tata' && strtolower($mmv_data->manufacturer) != 'maruti') {
        //     $vehicle_make_name = 'make_other_than_oem';
        // } else {
        //     $vehicle_make_name = strtolower($mmv_data->manufacturer);
        // }
        $vehicle_make = 'EICHER';

        $usgi_applicable_addons = get_usgi_applicable_addons_v2($miscd_age, $vehicle_make, $interval);


        $quoteData   = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $m_minidv = $quoteData->premium_json['minIdv'] ?? '';
        $m_vehicleidv = $quoteData->premium_json['vehicleIdv'] ?? '';
        $m_maxidv = $quoteData->premium_json['maxIdv'] ?? '';
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();
        // * if usgi quotes ate not comres then please create product based on this product identifier        
        if (in_array($masterProduct->product_identifier, ['basic', 'basic_od', 'basic_tp', 'basic_breakin'])) {
            foreach ($usgi_applicable_addons as $usgi_key => $usgi_value) {
                $usgi_applicable_addons[$usgi_key] = 'False';
            }
        }

        // RTO validation for DL
        $rto_code_check = explode('-', $requestData->rto_code);
        /* if (isset($rto_code_check) && $rto_code_check[0] == 'DL' && $rto_code_check[1] < 10) {
            $rto_code = $rto_code_check[0] . '0' . $rto_code_check[1];
        } else { */
        $rto_code = $rto_code_check[0] . $rto_code_check[1];
        // }

        $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
        $rto_payload = keysToLower($rto_payload);
        if (!$rto_payload && $rto_code_check[0] == 'DL') {
            $getRegisterNumberWithHyphen = explode('-', getRegisterNumberWithHyphen(str_replace('-', '', $requestData->vehicle_registration_no)));
            if (str_starts_with($getRegisterNumberWithHyphen[2], 'S')) {
                $rto_code = $rto_code_check[0] . ($rto_code_check[1] * 1) . 'S';
                $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
                $rto_payload = keysToLower($rto_payload);
            } elseif (strlen($getRegisterNumberWithHyphen[2]) >= 3 && str_starts_with($getRegisterNumberWithHyphen[2], 'C') && is_numeric($rto_code_check[1])) {
                $rto_code = $rto_code_check[0] . ($rto_code_check[1] * 1) . 'C';
                $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
                $rto_payload = keysToLower($rto_payload);
            }
        }

        if (!$rto_payload) {
            return [
                'status' => false,
                'message' => 'Premium is not available for this RTO - ' . $rto_code
            ];
        }

        // default values
        $PolicyStatus = 'Unexpired';
        $covers_payload_zero = 'True';
        $is_breakin_case = 'N';
        // $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
        $prev_claim_settled = '';
        $proposal_date = date('Y-m-d H:i:s');
        $electrical_accessories_flag = 'True';
        $selected_addons_flag = 'True';
        $discount_flag_in_tp = 'True';
        $inspection_date = '';
        $policy_Start_date = $previous_policy_start_date =  '';
        $registration_number = $proposal?->vehicale_registration_number !== 'NEW' ? explode('-', $proposal?->vehicale_registration_number) : '';

        $RegistrationNo_1 = !empty($registration_number) ? $registration_number[0] : '';
        $RegistrationNo_2 = !empty($registration_number) ? $registration_number[1] : '';
        $RegistrationNo_3 = !empty($registration_number) ? $registration_number[2] : '';
        $RegistrationNo_4 = !empty($registration_number) ? $registration_number[3] : '';

        $vehicleBodyColor = !empty($proposal->vehicle_color) ? $proposal->vehicle_color : '';


        $m_previous_policy_end_date = '';
        if ($requestData->business_type == 'newbusiness') {
            $business_type = 'New';
            $document_type = 'Proposal';
            $productname = 'MISCELLANEOUS CARRYING VEHICLE';
            $product_code = '2316';
            $policy_start_date = date('d/m/Y');
            $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
            $RegistrationNumber = 'New';
            $veh_type = 'New';
            $ncb_declaration = 'False';
            $RegistrationNo_1 = 'New';
        } else {

            $previousPolicyExpiryDate = $requestData->previous_policy_expiry_date && $requestData->previous_policy_expiry_date != 'New' ? $requestData->previous_policy_expiry_date : date('d-m-Y', strtotime(date('Y-m-d') . ' - 100 days'));
            $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
            $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
            $previousPolicyExpiryDate = Carbon::createFromDate($previousPolicyExpiryDate);
            $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
            $m_previous_policy_end_date = $previousPolicyExpiryDate->format('d/m/Y');
            $policyNo = $proposal?->previous_policy_number;
            $document_type = 'Proposal';
            $business_type = 'Rollover';
            $veh_type = 'Rollover';
            $RegistrationNumber = $proposal->vehicale_registration_number;
            $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
            $prev_claim_settled = (($requestData->is_claim == 'Y') ? 'Yes' : 'No');

            if ($date_diff_in_prev_policy > 90) {
                $motor_expired_more_than_90_days = 'Y';
            } else {
                $motor_expired_more_than_90_days = 'N';
            }


            if ($requestData->is_claim == 'N' && $motor_expired_more_than_90_days == 'N' && $premium_type != 'third_party') {

                $ncb_declaration = 'True';
                $motor_applicable_ncb = ((int) $requestData->applicable_ncb) / 100;
            } else {
                $ncb_declaration = 'False';
                $motor_applicable_ncb = 0;
                $is_claim_val = 1;
                $claim_no = 1;
            }

            if ($requestData->previous_policy_type == 'Third-party') {
                $ncb_declaration = 'False';
                $motor_applicable_ncb = 0;
                $requestData->business_type = 'breakin';
            }
            $PolicyStatus = 'Unexpired';
            if ($requestData->business_type == 'breakin') {
                $policy_start_date = date('d/m/Y', strtotime("+2 day"));
                $inspection_date =  $step === 'skip' ? '' : date("d/m/Y", strtotime(str_replace("-", "", explode(" ", $proposal->created_date)[0])));
                $PolicyStatus = $premium_type !== 'third_party' ? 'Expired' : 'Unexpired';
                $breakin_flag = 'Y';
            }
            //BREAKIN
            if ($step == 'skip') {
                if ($inspection_date) {
                    $policy_start_date = match ($inspection_date) {
                        date('d/m/Y') => (Carbon::createFromFormat('d/m/Y', $inspection_date))->addDays(3)->format('d/m/Y'),
                        default => ((Carbon::createFromFormat('d/m/Y', $inspection_date))->addDays(1)->format('d/m/Y') === date('d/m/Y')) ? (Carbon::createFromFormat('d/m/Y', date('d/m/Y')))->addDays(2)->format('d/m/Y') : date('d/m/Y'),
                    };
                } else {
                    $inspection_raised_date   = DB::table("cv_breakin_status")
                        ->where("ic_id", $proposal->ic_id)
                        ->where("user_proposal_id", $proposal->user_proposal_id)
                        ->pluck("created_at")
                        ->first();
                    $inspection_raised_date =  date('d/m/Y', strtotime($inspection_raised_date));
                    $policy_start_date = match ($inspection_raised_date) {
                        date('d/m/Y') => (Carbon::createFromFormat('d/m/Y', $inspection_raised_date))->addDays(3)->format('d/m/Y'),
                        default => ((Carbon::createFromFormat('d/m/Y', $inspection_raised_date))->addDays(1)->format('d/m/Y') === date('d/m/Y')) ? (Carbon::createFromFormat('d/m/Y', date('d/m/Y')))->addDays(2)->format('d/m/Y') : date('d/m/Y'),
                    };
                }
            }
            //date changes
            if($premium_type == 'third_party_breakin'){
                $policy_start_date = date('d/m/Y', strtotime("+2 day"));
            }else if($requestData->business_type == 'breakin' && !in_array($premium_type, ['third_party','third_party_breakin'])){
                $policy_start_date = date('d/m/Y');
            }
            $policy_end_date = Carbon::createFromFormat('d/m/Y', $policy_start_date)->addYears(1)->subDay(1)->format('d/m/Y');
            $tp_start_date =   $policy_start_date;
            $tp_end_date = $policy_end_date;
            switch ($premium_type) {
                case 'comprehensive':
                    $productname = 'MISCELLANEOUS CARRYING VEHICLE';
                    $product_code = '2316';
                    break;
                case 'third_party':
                    $productname = 'MISCELLANEOUS VEHICLE LIABILITY POLICY';
                    $product_code = '2318';
                    $selected_addons_flag = 'False';
                    $discount_flag_in_tp = 'False';
                    break;
                case 'own_damage':
                    $document_type = 'Proposal';
                    $productname = 'MISCELLANEOUS CARRYING VEHICLE';
                    $product_code = '2316';
                    break;
            }
        }

        
        $additional_data = $proposal->additonal_data;
        if ($requestData->business_type != 'newbusiness') {


            if ($premium_type == 'own_damage') {
                $insurer_name = DB::table('previous_insurer_mappping')->where('universal_sompo', $proposal->tp_insurance_company)->select('previous_insurer')->first();
                if (!$insurer_name) {
                    return [
                        'status' => false,
                        'message' => 'Previous Insurer Mapping with universal sompo doesn\'t exist'
                    ];
                }
                $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $insurer_name->previous_insurer)->first();
                $tp_insurer_address = keysToLower($tp_insurer_address);
            } else {
                $insurer_name = DB::table('previous_insurer_mappping')->where('universal_sompo', $proposal->previous_insurance_company)->select('previous_insurer')->first();
                if (!$insurer_name) {
                    return [
                        'status' => false,
                        'message' => 'Previous Insurer Mapping with universal sompo doesn\'t exist'
                    ];
                }
                $insurer = DB::table('insurer_address')->where('Insurer', $insurer_name->previous_insurer)->first();
                $insurer = keysToLower($insurer);
            }
        }


        // $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
        // $discount_rate = ((int) $quoteData?->premium_json['vehicleDiscountDetail']['discountRate']/100);

        $discount_rate = !empty($quoteData?->premium_json['vehicleDiscountDetail']['discountRate']) ? $quoteData?->premium_json['vehicleDiscountDetail']['discountRate'] : 0;
        $discount_rate = $discount_rate / 100;


        $engine_protector_cover_diesel = 'False';
        $engine_protector_cover_petrol = 'False';
        $cpa = 'False';
        $elect_accessories = 'False';
        $elect_accessories_sumInsured = 0;
        $non_elect_accessories = 'False';
        $non_elect_accessories_sumInsured = 0;
        $external_lpg_cng_accessories = 'False';
        $external_lpg_cng_accessories_sumInsured = 0;
        $pa_add_paid_driver_cover = 'False';
        $pa_add_paid_driver_cover_sumInsured = 0;
        $unnamed_psenger_cover = 'False';
        $unnamed_psenger_cover_sumInsured = 0;
        $llpd = 'False';
        $anti_theft_device = 'False';
        $voluntry_discount = 'False';
        $voluntry_discount_sumInsured = 0;
        $enable_zero_dep = 'False';
        $key_replacement_flag = 'False';
        $engine_protector_flag = 'False';
        $tyre_secure_flag = 'False';
        $consumable_flag = 'False';
        $return_to_invoice_flag = 'False';
        $tppd_cover_flag = 'False';
        $RegistrationNumber = explode('-', $RegistrationNumber);
        $llpd_driverSI = $llpd_conductorSI = $llpd_cleanerSI = 0;
        $llpd_sumInsured = 0.0;
        $imt23 = 'False';

        if (isset($RegistrationNumber[1], $RegistrationNumber[0]) && $RegistrationNumber[0] == 'DL') {
            $rtoCode = $RegistrationNumber[0] . '-' . $RegistrationNumber[1];
            $rtoCode = explode('-', RtoCodeWithOrWithoutZero($rtoCode, false));
            $RegistrationNumber[1] = $rtoCode[1];
        }
        $RegistrationNumber = implode('-', $RegistrationNumber);

        $addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        if ($addons) {
            if ($addons->compulsory_personal_accident) {
                foreach ($addons->compulsory_personal_accident as $key => $cpaVal) {
                    if (isset($cpaVal['name']) && $cpaVal['name'] == 'Compulsory Personal Accident') {
                        $cpa = 'True';
                    }
                }
            }

            if ($addons->applicable_addons) {
                foreach ($addons->applicable_addons as $key => $addons_val) {
                    if ($selected_addons_flag !== 'False') {
                        if ($addons_val['name'] == 'Zero Depreciation') {
                            $enable_zero_dep = $usgi_applicable_addons['zero_dep'];
                        }
                        if ($addons_val['name'] == 'Key Replacement') {
                            $key_replacement_flag = $usgi_applicable_addons['key_replacement'];
                        }
                        if ($addons_val['name'] == 'Engine Protector') {
                            $engine_protector_flag = $usgi_applicable_addons['engine_protector'];
                        }
                        if ($addons_val['name'] == 'Tyre Secure') {
                            $tyre_secure_flag = $usgi_applicable_addons['tyre_secure'];
                        }
                        if ($addons_val['name'] == 'Consumable') {
                            $consumable_flag = $usgi_applicable_addons['consumable'];
                        }
                        if ($addons_val['name'] == 'Return To Invoice') {
                            $return_to_invoice_flag = $usgi_applicable_addons['return_to_invoice'];
                        }
                        if ($addons_val['name'] == 'IMT - 23') {
                            $imt23 = 'True';
                        }
                    }
                }
            }

            if ($addons->accessories) {
                foreach ($addons->accessories as $key => $accessories) {
                    if ($accessories['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        $external_lpg_cng_accessories = 'True';
                        $external_lpg_cng_accessories_sumInsured = $accessories['sumInsured'];
                    }
                    if ($electrical_accessories_flag !== 'False') {
                        if ($accessories['name'] == 'Non-Electrical Accessories') {
                            $non_elect_accessories = 'True';
                            $non_elect_accessories_sumInsured = $accessories['sumInsured'];
                        }
                        if ($accessories['name'] == 'Electrical Accessories') {
                            $elect_accessories = 'True';
                            $elect_accessories_sumInsured = $accessories['sumInsured'];
                        }
                    }
                }
            }

            if ($addons->additional_covers) {
                foreach ($addons->additional_covers as $key => $additional_covers) {
                    if ($additional_covers['name'] == 'PA paid driver/conductor/cleaner') {
                        $pa_add_paid_driver_cover = 'True';
                        $pa_add_paid_driver_cover_sumInsured = $additional_covers['sumInsured'];
                    }
                    if ($additional_covers['name'] == 'Unnamed Passenger PA Cover') {
                        $unnamed_psenger_cover = 'True';
                        $unnamed_psenger_cover_sumInsured = $additional_covers['sumInsured'];
                    }
                    if ($additional_covers['name'] == 'LL paid driver/conductor/cleaner') {
                        $llpd = 'True';
                        $llpd_driverSI = $additional_covers['LLNumberDriver'];
                        $llpd_cleanerSI = $additional_covers['LLNumberCleaner'];
                        $llpd_conductorSI = $additional_covers['LLNumberConductor'];
                        $llpd_sumInsured = $llpd_cleanerSI + $llpd_conductorSI + $llpd_driverSI;
                    }
                }
            }

            if ($addons->discounts) {
                foreach ($addons->discounts as $key => $discounts) {
                    if ($discount_flag_in_tp !== 'False') {
                        if ($discounts['name'] == 'anti-theft device') {
                            $anti_theft_device = 'True';
                        }
                        if ($discounts['name'] == 'voluntary_insurer_discounts') {
                            $voluntry_discount = 'True';
                            $voluntry_discount_sumInsured = $discounts['sumInsured'];
                        }
                    }
                    if ($discounts['name'] == 'TPPD Cover') {
                        $tppd_cover_flag = 'True';
                    }
                }
            }
        }

        if ($engine_protector_flag == 'True') {
            switch ($mmv_data->cnvm_fuel_type) {
                case 'Petrol':
                    $engine_protector_cover_diesel = 'False';
                    $engine_protector_cover_petrol = 'False';
                    break;
                case 'Diesel':
                    $engine_protector_cover_diesel = 'False';
                    $engine_protector_cover_petrol = 'False';
                    break;
                default:
                    $engine_protector_cover_diesel = 'False';
                    $engine_protector_cover_petrol = 'False';
                    break;
            }
        }
        if(in_array($premium_type ,['own_damage','third_party']))
        {
            $cpa = 'False';
        }
        if($requestData->vehicle_owner_type != 'I')
        {
            $cpa = 'False';
        }

        /*             $expdate = $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date;
            $date_difference = get_date_diff('day', $expdate);
            if ($requestData->business_type == 'breakin' && $premium_type !='third_party' && $date_difference >= 30) {
            $enable_zero_dep = 'False';
            $key_replacement_flag = 'False';
            $engine_protector_flag = 'False';
            $tyre_secure_flag = 'False';
            $consumable_flag = 'False';
            $return_to_invoice_flag = 'False';
            } */
        $de_tariff_discount_section =
            [
                'De-tariffDiscountGroup' => [
                    'De-tariffDiscountGroupData' => [
                        'DiscountAmount' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Discount Amount',
                                'Value' => '0.0'
                            ],
                        ],
                        'DiscountRate' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Discount Rate',
                                'Value' => ''
                            ],
                        ],
                        'SumInsured' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'SumInsured',
                                'Value' => $quoteData->idv
                            ],
                        ],
                        'Rate' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Rate',
                                'Value' => ''
                            ],
                        ],
                        'Premium' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Premium',
                                'Value' => ''
                            ],
                        ],
                        'Applicable' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Applicable',
                                'Value' => 'True'
                            ],
                        ],
                        'Description' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => ' Description',
                                'Value' => 'De-tariff Discount'
                            ],
                        ],
                        '@attributes' => [
                            'Type' => 'GroupData'
                        ],
                    ],
                    '@attributes' => [
                        'Name' => 'De-tariff Discount Group',
                        'Type' => 'Group'
                    ],
                ],
                '@attributes' => [
                    'Name' => 'De-tariffDiscounts'
                ],
            ];

        $cover_data_section =
            [
                '0' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => ($premium_type == 'third_party') ? 'False' : 'True'
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'Basic OD'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '1' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => '0'
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => ($premium_type == 'own_damage') ? 'False' : 'True'
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'Basic TP'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '2' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $elect_accessories_sumInsured,
                        ],
                    ],
                    'Applicable' => [

                        '@value' => '',
                        '@attributes' => [

                            'Name' => 'Applicable',
                            'Value' => ($premium_type == 'third_party') ? 'False' : $elect_accessories
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'ELECTRICAL ACCESSORY OD'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '3' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $non_elect_accessories_sumInsured
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => ($premium_type == 'third_party') ? 'False' : $non_elect_accessories
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'NON ELECTRICAL ACCESSORY OD'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '4' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name'  => 'SumInsured',
                            'Value' => ($requestData->fuel_type == "CNG" && $external_lpg_cng_accessories_sumInsured == "") ? "10000" : (($external_lpg_cng_accessories_sumInsured != '') ? $external_lpg_cng_accessories_sumInsured : '0')
                        ]
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $premium_type == 'third_party' ? 'False' : $external_lpg_cng_accessories
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'CNGLPG KIT OD'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '5' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => ($requestData->fuel_type == "CNG" && $external_lpg_cng_accessories_sumInsured == "") ?
                                "10000" : (($external_lpg_cng_accessories_sumInsured != '') ? $external_lpg_cng_accessories_sumInsured : '0')
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $premium_type == 'own_damage' ? 'False' : $external_lpg_cng_accessories,
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'CNGLPG KIT TP'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '6' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $requestData->fuel_type == 'CNG' ? 'True' : 'False'
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'BUILTIN CNG KIT / LPG KIT OD'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '7' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'FIBRE TANK - OD'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                // '8' => [
                //     'Premium' => [
                //         '@value' => '',
                //         '@attributes' => [
                //             'Name' => 'Premium',
                //             'Value' => ''
                //         ],
                //     ],
                //     'Rate' => [
                //         '@value' => '',
                //         '@attributes' => [
                //             'Name' => 'Rate',
                //             'Value' => ''
                //         ],
                //     ],
                //     'SumInsured' => [
                //         '@value' => '',
                //         '@attributes' => [
                //             'Name' => 'SumInsured',
                //             'Value' => $quoteData->idv
                //         ],
                //     ],
                //     'Applicable' => [
                //         '@value' => '',
                //         '@attributes' => [
                //             'Name' => 'Applicable',
                //             'Value' => 'False'
                //         ],
                //     ],
                //     'CoverGroups' => [
                //         '@value' => '',
                //         '@attributes' => [
                //             'Name' => 'CoverGroups',
                //             'Value' => 'Other OD'
                //         ],
                //     ],
                //     '@attributes' => [
                //         'Type' => 'GroupData'
                //     ],
                // ],
                '9' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $llpd_sumInsured
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $llpd
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'LEGAL LIABILITY TO PAID DRIVER'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '10' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name'  => 'SumInsured',
                            'Value' => $unnamed_psenger_cover_sumInsured
                        ]
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name'  => 'Applicable',
                            'Value' => $unnamed_psenger_cover
                        ]
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'UNNAMED PA COVER TO PASSENGERS'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '11' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $pa_add_paid_driver_cover_sumInsured
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $pa_add_paid_driver_cover
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'PA COVER TO PAID DRIVER'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '12' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ($requestData->business_type == 'newbusiness') ? '3' : '1'
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1500000.00'
                        ],
                    ],
                    'NomineeName' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'NomineeName',
                            'Value' => $proposal?->nominee_name ?? ''
                        ],
                    ],
                    'NomineeRelation' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'NomineeRelation',
                            'Value' => $proposal?->nominee_relationship ?? ''
                        ],
                    ],
                    'NomineeAge' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'NomineeAge',
                            'Value' => $proposal?->nominee_age ?? ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            // 'Value' => (in_array($premium_type ,['own_damage','third_party']) && $requestData->vehicle_owner_type != 'I') ? 'False' : $cpa
                            'Value' =>  $cpa
                        ],
                    ],
                    'CoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'CoverGroups',
                            'Value' => 'PA COVER TO OWNER DRIVER'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
            ];

        $add_on_cover_data_section =
            [
                '0' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Applicable',
                            'Value' => $enable_zero_dep,
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'Nil Depreciation Waiver cover'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '1' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'DAILY CASH ALLOWANCE'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '2' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => '',
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $key_replacement_flag
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'KEY REPLACEMENT'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '3' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $return_to_invoice_flag
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'RETURN TO INVOICE'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '4' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'ACCIDENTAL HOSPITALIZATION'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '5' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $premium_type == 'third_party' || $miscd_age >= 10 ? 'False' : $usgi_applicable_addons['rsa']
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'Road side Assistance'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '6' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'HYDROSTATIC LOCK COVER'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '7' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $consumable_flag,
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'COST OF CONSUMABLES'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '8' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'SECURE TOWING'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '9' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $engine_protector_cover_petrol
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'ENGINE PROTECTOR - PETROL'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '10' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $engine_protector_cover_diesel
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'ENGINE PROTECTOR - DIESEL'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '11' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $tyre_secure_flag,
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'TYRE AND RIM SECURE'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '12' => [
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '1000.00'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'AddonCoverGroups' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AddonCoverGroups',
                            'Value' => 'WRONG FUEL COVER'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
            ];

        $other_discount_data_section =
            [
                '0' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => '0'
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'Detariff discount'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '1' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $anti_theft_device
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Description',
                            'Value' => 'Antitheft device discount'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '2' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'Automobile Association discount'
                        ],
                    ],
                    '@attributes' => [

                        'Type' => 'GroupData'
                    ],
                ],
                '3' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' =>
                            ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' =>
                            ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => ''
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => 'False'
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'Handicap discount'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '4' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => $voluntry_discount_sumInsured,
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' =>
                            ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ''
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => ' Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $voluntry_discount
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'Voluntary deductable'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '5' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => ($requestData->business_type == 'newbusiness') ? 0 : ((int) $requestData->previous_ncb) / 100
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => $quoteData->idv
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => ($requestData->business_type == 'newbusiness') ? 0 : ((int) $requestData->previous_ncb) / 100
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => ($premium_type == 'third_party') ? 'False' : $ncb_declaration
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'No claim bonus'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '6' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => '0'
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '6000.00'
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => '0.0000'
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => ''
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $tppd_cover_flag
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'TPPD Discount'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
                '7' => [
                    'DiscountAmount' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Amount',
                            'Value' => ''
                        ],
                    ],
                    'DiscountRate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Discount Rate',
                            'Value' => ''
                        ],
                    ],
                    'SumInsured' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'SumInsured',
                            'Value' => '6000'
                        ],
                    ],
                    'Rate' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Rate',
                            'Value' => '0'
                        ],
                    ],
                    'Premium' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Premium',
                            'Value' => '0'
                        ],
                    ],
                    'Applicable' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Applicable',
                            'Value' => $imt23
                        ],
                    ],
                    'Description' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Description',
                            'Value' => 'INCLUSION OF IMT23'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'GroupData'
                    ],
                ],
            ];

        //Breakin ID Check
        if ($step == 'skip') {
            $breakin_id = CvBreakinStatus::where('user_proposal_id', $proposal->user_proposal_id)
                ->first();
        }

        $financer_details_section =
            [
                'FinancierDtlGrp' => [
                    'FinancierDtlGrpData' => [
                        'FinancierCode' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Financier Code',
                                'Value' => '1'
                            ],
                        ],
                        'AgreementType' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Agreement Type',
                                'Value' => ($proposal->financer_agreement_type == 'Hypothecation') ? 'Hypothecation' : 'None'
                            ],
                        ],
                        'BranchName' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Branch Name',
                                'Value' => ($proposal->financer_agreement_type == 'Hypothecation') ? $proposal->financer_location : ''
                            ],
                        ],
                        'FinancierName' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Financier Name',
                                'Value' => ($proposal->financer_agreement_type == 'Hypothecation') ? $proposal->name_of_financer : ''
                            ],
                        ],
                        'SrNo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Sr No',
                                'Value' => '1'
                            ],
                        ],
                        '@attributes' => [
                            'Type' => 'GroupData'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'Group',
                        'Name' => 'Financier Dtl Group'
                    ],
                ],
                '@attributes' => [
                    'Name' => 'Financier Details'
                ],
            ];


        $previous_policy_details_section =
            [
                'PreviousPolDtlGroup' => [
                    'PreviousPolDtlGroupData' => [
                        'ProductCode' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Product Code',
                                'Value' => $product_code
                            ],
                        ],
                        'ClaimSettled' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Claim Settled',
                                'Value' => ''
                            ],
                        ],
                        'ClaimPremium' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Claim Premium',
                                'Value' => ''
                            ],
                        ],
                        'ClaimAmount' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Claim Amount',
                                'Value' => $is_claim_val ??  0
                            ],
                        ],
                        'DateofLoss' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Date Of Loss',
                                'Value' => ''
                            ],
                        ],
                        'NatureofLoss' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Nature Of Loss',
                                'Value' => ''
                            ],
                        ],
                        'ClaimNo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Claim No',
                                'Value' => $claim_no ?? ''
                            ],
                        ],
                        'TPPolicyEffectiveTo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Policy Effective To',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? date('d/m/Y', strtotime($proposal->tp_end_date)) : $PreviousPolExpDt))
                            ],
                        ],
                        'TPPolicyEffectiveFrom' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Policy Effective From',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? date('d/m/Y', strtotime($proposal->tp_start_date)) : $previous_policy_start_date))
                            ],
                        ],
                        'TPInsurerPolicyNo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Policy No',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? $proposal->tp_insurance_number : $policyNo))
                            ],
                        ],
                        'TpInsurerName' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Insurer Name',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? $proposal->tp_insurance_company_name : $proposal->insurance_company_name))
                            ],
                        ],
                        'PolicyEffectiveTo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Policy Effective To',
                                // 'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? date('d/m/Y', strtotime($proposal->tp_end_date)) : $PreviousPolExpDt))
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : $m_previous_policy_end_date)
                            ],
                        ],
                        'PolicyEffectiveFrom' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Policy Effective From',
                                // 'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? date('d/m/Y', strtotime($proposal->tp_start_date)) : $previous_policy_start_date))
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : $previous_policy_start_date)
                            ],
                        ],
                        'DateOfInspection' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'DateOfInspection',
                                'Value' => $step === 'skip' ? $inspection_date : ''
                            ],
                        ],
                        'InspectionId' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'InspectionId',
                                'Value'=> $breakin_id['breakin_number'] ?? ''
                            ],
                        ],
                        'PolicyPremium' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'PolicyPremium',
                                'Value' => '1000.00'
                            ],
                        ],
                        'PolicyNo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Policy No',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? $proposal->tp_insurance_number : $policyNo))
                            ],
                        ],
                        'PolicyYear' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Policy Year',
                                'Value' => ''
                            ],
                        ],
                        'OfficeCode' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Office Name',
                                'Value' => ''
                            ],
                        ],
                        'PolicyStatus' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Policy Status',
                                'Value' => $PolicyStatus //unexpired for rollover and expired for break-in policy
                            ],
                        ],
                        'CorporateCustomerId' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Corporate Customer Id',
                                'Value' => '',
                            ],
                        ],
                        'InsurerName' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'InsurerName',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? $proposal->tp_insurance_company_name : $proposal->insurance_company_name))
                            ],
                        ],
                        'InsurerAddress' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'InsurerAddress',
                                'Value' => ($requestData->business_type == 'newbusiness') ? '' : ($premium_type != 'own_damage' ? $insurer->address_line_1 . ' ' . $insurer->address_line_2 : $tp_insurer_address->address_line_1 . ' ' . $tp_insurer_address->address_line_2)
                            ],
                        ],
                        '@attributes' => [
                            'Type' => 'GroupData'
                        ],
                    ],
                    '@attributes' => [
                        'Type' => 'Group',
                        'Name' => 'Previous Pol Dtl Group'
                    ],
                ],
                'PreviousPolicyType' => [
                    '@value' => '',
                    '@attributes' => [

                        'Name' => 'Previous Policy Type',
                        'Value' => 'Package Policy'
                    ],
                ],
                'OfficeAddress' => [
                    '@value' => '',
                    '@attributes' => [
                        'Name' => 'Office Address',
                        'Value' => ''
                    ],
                ],
                '@attributes' => [
                    'Name' => 'Previous Policy Details'
                ],
            ];
        $branchCode_value = config('constants.motorConstant.BRANCH_CODE_VALUE_UNIVERSAL');
        $branchCode_name = config('constants.motorConstant.BRANCH_CODE_NAME_UNIVERSAL');
        $branchName_name = config('constants.motorConstant.BRANCH_NAME_NAME_UNIVERSAL');
        $branchName_value = config('constants.motorConstant.BRANCH_NAME_VALUE_UNIVERSAL');
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_TESTING_UNIVERSAL');
        $pos_name = $pos_pan = $pos_aadhar_no = $pos_email = $pos_mobile_no = '';
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $enquiryId)
            ->where('user_proposal_id', $proposal->user_proposal_id)
            ->where('seller_type', 'P')
            ->first();

        if ($step === 'skip') {
            $breakin_date = DB::table('cv_breakin_status')
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->first();
            $previous_policy_details_section['PreviousPolDtlGroup']['PreviousPolDtlGroupData']['DateOfInspection']['@attributes']['Value'] = date("d/m/Y", strtotime(str_replace("-", "", explode(" ", $breakin_date->created_at)[0])));
        }

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000) {
            if ($pos_data->agent_mobile != '' && $pos_data->agent_name != '' && $pos_data->pan_no != '') {
                $pos_name = $pos_data->agent_name;
                $pos_pan = $pos_data->pan_no;
                $pos_aadhar_no = $pos_data->aadhar_no;
                $pos_email = $pos_data->agent_email;
                $pos_mobile_no = $pos_data->agent_mobile;
            } else {
                return
                    [
                        'status' => false,
                        'message' => 'POS details are missing'
                    ];
            }
        } else if ($is_pos_enabled_testing == 'Y' && $quote->idv <= 5000000) {
            $pos_name = 'Asd';
            $pos_pan = 'ABGTY8890Z';
            $pos_aadhar_no = '569278616999';
            $pos_email = 'asd@gmail.com';
            $pos_mobile_no = '8765434567';
        } else {
            $pos_name = '';
            $pos_pan = '';
            $pos_aadhar_no = '';
            $pos_email = '';
            $pos_mobile_no = '';
        }

        $proposal_request =
            [
                'Authentication' =>
                [
                    'WACode' =>  config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MISCD'),
                    'WAAppCode' => config('constants.IcConstants.universal_sompo.AUTH_APPCODE_SOMPO_MISCD'), //AUTH_APPCODE_SOMPO_MOTOR,
                    'WAUserID' => config('constants.IcConstants.universal_sompo.WEB_USER_ID_SOMPO_MISCD'),   //WEB_USER_ID_SOMPO_MOTOR,
                    'WAUserPwd' => config('constants.IcConstants.universal_sompo.WEB_USER_PASSWORD_SOMPO_MISCD'), // WEB_USER_PASSWORD_SOMPO_MOTOR,
                    'WAType'  => '0',
                    'DocumentType' => $document_type,
                    'Versionid' => '1.1',
                    'GUID' => '', //config('constants.IcConstants.universal_sompo.GUID_UNIVERSAL_SOMPO_MOTOR'), //GUID_UNIVERSAL_SOMPO_MOTOR  'GUID' => config('constants.IcConstants.universal_sompo.GUID_UNIVERSAL_SOMPO_MOTOR'),
                ],
                'Customer' =>
                [
                    'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "Individual" : "Corporate",
                    'CustomerName' => $proposal->first_name . ' ' . $proposal->last_name,
                    'DOB' => $proposal?->dob ?? '',
                    'Gender' => $proposal?->gender ?? '',
                    'CanBeParent' => '0',
                    'ContactTelephoneSTD' => '',
                    'MobileNo' => $proposal->mobile_number,
                    'Emailid' => $proposal?->email,
                    'PresentAddressLine1' => $proposal?->address_line1,
                    'PresentAddressLine2' => $proposal?->address_line2 . ',' . $proposal?->address_line3,
                    'PresentStateCode' => $proposal?->state_id,
                    'PresentCityDistCode' => $proposal?->city_id,
                    'PresentPinCode' => $proposal->pincode,
                    'PermanentAddressLine1' => $proposal?->address_line1,
                    'PermanentAddressLine2' => $proposal?->address_line2 . ',' . $proposal?->address_line3,
                    'PermanentStateCode' => $proposal?->state_id,
                    'PermanentCityDistCode' => $proposal?->city_id,
                    'PermanentPinCode' => $proposal->pincode,
                    'CustGSTNo' => $proposal?->gst_number ?? '',
                    'ProductName' => $productname,
                    'ProductCode' => $product_code, //'2311',
                    'InstrumentNo' => '',
                    'InstrumentDate' => '',
                    'BankID' => '',
                    'PosPolicyNo' => '',
                    'WAURN' => '',
                    'NomineeName' => $proposal?->nominee_name,
                    'NomineeRelation' => $proposal?->nominee_relationship,
                    'PANNo' => $proposal?->pan_number ?? '',
                    'AadhaarNo' => '' //$aadhar_no,
                ],
                // 'POSAGENT' =>
                // [
                //     'Name' => $pos_name,
                //     'PAN' => $pos_pan,
                //     'Aadhar' => $pos_aadhar_no, //TestAadhar',
                //     'Email' => $pos_email,
                //     'MobileNo' => $pos_mobile_no,
                //     'Location' => '',
                //     'Information1' => '',
                //     'Information2' => ''
                // ],

                'Product' => [
                    'GeneralProposal' => [
                        'GeneralProposalGroup' => [
                            'DistributionChannel' => [
                                'BranchDetails' => [
                                    'IMDBranchName' => [
                                        '@value' => '',
                                        '@attributes' => [
                                            'Name' => $branchName_name,
                                            'Value' => $branchName_value
                                        ],
                                    ],
                                    'IMDBranchCode' => [
                                        '@value' => '',
                                        '@attributes' => [
                                            'Name' => $branchCode_name,
                                            'Value' => $branchCode_value
                                        ],
                                    ],
                                    '@attributes' => [
                                        'Name' => 'Branch Details'
                                    ],
                                ],
                                'SPDetails' => [
                                    'SPName' => [
                                        '@value' => '',
                                        '@attributes' => [

                                            'Name' => 'SP Name',
                                            'Value' => ''
                                        ],

                                    ],

                                    'SPCode' => [

                                        '@value' => '',
                                        '@attributes' => [

                                            'Name' => 'SP Code',
                                            'Value' => ''
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'SP Details'
                                    ],

                                ],
                                'IMDDetails' => [
                                    'IMDBName' => [
                                        '@value' => '',
                                        '@attributes' => [
                                            'Name' => 'IMDBName',
                                            'Value' => 'SURESH GANGUMOLU'
                                        ],
                                    ],
                                    'IMDCode' => [
                                        '@value' => '',
                                        '@attributes' => [
                                            'Name' => 'IMDCode',
                                            'Value' => '200758670601'
                                        ],
                                    ],
                                    '@attributes' => [
                                        'Name' => 'IMDDetails'
                                    ],
                                ],

                                '@attributes' => [

                                    'Name' => 'Distribution Channel'
                                ],


                            ],

                            'GeneralProposalInformation' => [

                                'TypeOfBusiness' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'Type Of Business',
                                        'Value' => 'New'
                                    ],

                                ],

                                'ServiceTaxExemptionCategory' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'Service Tax Exemption Category',
                                        'Value' => 'No Exemption'
                                    ],

                                ],

                                'BusinessType' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'Transaction Type',
                                        'Value' => $business_type
                                    ],

                                ],

                                'Sector' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'Sector',
                                        'Value' => 'Others'
                                    ],

                                ],

                                'ProposalDate' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'Proposal Date',
                                        'Value' => date('d/m/Y')
                                    ],

                                ],

                                'DealId' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'Deal Id',
                                        'Value' => '',
                                    ],

                                ],

                                'PolicyNumberChar' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'PolicyNumberChar',
                                        'Value' => ''
                                    ],

                                ],

                                'VehicleLaidUpFrom' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'VehicleLaidUpFrom',
                                        'Value' => ''
                                    ],

                                ],

                                'VehicleLaidUpTo' => [

                                    '@value' => '',
                                    '@attributes' => [

                                        'Name' => 'VehicleLaidUpTo',
                                        'Value' => ''
                                    ]

                                ],

                                'PolicyEffectiveDate' => [

                                    'Fromdate' => [

                                        '@value' => '',
                                        '@attributes' => [

                                            'Name' => 'From Date',
                                            'Value' => $policy_start_date
                                        ],

                                    ],

                                    'Todate' => [

                                        '@value' => '',
                                        '@attributes' => [

                                            'Name' => 'To Date',
                                            'Value' => $policy_end_date
                                        ],

                                    ],

                                    'Fromhour' => [

                                        '@value' => '',
                                        '@attributes' => [

                                            'Name' => 'From Hour',
                                            'Value' => '18:04'
                                        ],

                                    ],

                                    'Tohour' => [

                                        '@value' => '',
                                        '@attributes' => [

                                            'Name' => 'To Hour',
                                            'Value' => '23:59'
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'Policy Effective Date'
                                    ],

                                ],

                                '@attributes' => [

                                    'Name' => 'General Proposal Information'
                                ],

                            ],

                            '@attributes' => [

                                'Name' => 'General Proposal Group'
                            ],

                        ],

                        'FinancierDetails' => $financer_details_section,

                        'PreviousPolicyDetails' => $previous_policy_details_section,

                        '@attributes' => [

                            'Name' => 'General Proposal'
                        ],

                    ],

                    'PremiumCalculation' => [

                        'NetPremium' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Net Premium',
                                'Value' => '0'
                            ],

                        ],

                        'ServiceTax' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Service Tax',
                                'Value' => '0'
                            ],

                        ],

                        'StampDuty2' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Stamp Duty',
                                'Value' => '0'
                            ],

                        ],

                        'CGST' => [

                            '@value' => '0',
                            '@attributes' => [

                                'Name' => 'CGST',
                                'Value' => '0'
                            ],

                        ],

                        'SGST' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'SGST',
                                'Value' => '0'
                            ],

                        ],

                        'UGST' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'UGST',
                                'Value' => '0'
                            ],

                        ],

                        'IGST' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'IGST',
                                'Value' => '0'
                            ],

                        ],

                        'TotalPremium' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Total Premium',
                                'Value' => '0'
                            ],

                        ],

                        '@attributes' => [

                            'Name' => 'Premium Calculation'
                        ],

                    ],

                    'Risks' => [

                        'Risk' => [

                            'RisksData' => [

                                'De-tariffLoadings' => [

                                    'De-tariffLoadingGroup' => [

                                        'De-tariffLoadingGroupData' => [

                                            'LoadingAmount' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'Loading Amount',
                                                    'Value' => ''
                                                ],

                                            ],

                                            'LoadingRate' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'Loading Rate',
                                                    'Value' => ''
                                                ],

                                            ],

                                            'SumInsured' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'SumInsured',
                                                    'Value' => ''
                                                ],

                                            ],

                                            'Rate' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'Rate',
                                                    'Value' => ''
                                                ],

                                            ],

                                            'Premium' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'Premium',
                                                    'Value' => ''
                                                ],

                                            ],

                                            'Applicable' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'Applicable',
                                                    'Value' => 'True'
                                                ],

                                            ],

                                            'Description' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                    'Name' => 'Description',
                                                    'Value' => 'De-tariff Loading'
                                                ],

                                            ],

                                            '@attributes' => [

                                                'Type' => 'GroupData'
                                            ],

                                        ],

                                        '@attributes' => [

                                            'Type' => 'Group',
                                            'Name' => 'De-tariff Loading Group'
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'De-tariffLoadings'
                                    ],

                                ],
                                'De-tariffDiscounts' => $de_tariff_discount_section,

                                'CoverDetails' => [

                                    'Covers' => [

                                        'CoversData' => $cover_data_section,
                                        '@attributes' => [

                                            'Type' => 'Group',
                                            'Name' => 'Covers'
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'CoverDetails'
                                    ],

                                ],

                                'AddonCoverDetails' => [

                                    'AddonCovers' => [

                                        'AddonCoversData' => $add_on_cover_data_section,

                                        '@attributes' => [

                                            'Type' => 'Group',
                                            'Name' => 'AddonCovers'
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'AddonCoverDetails'
                                    ],

                                ],

                                'OtherLoadings' => [

                                    'OtherLoadingGroup' => [

                                        'OtherLoadingGroupData' => [

                                            '0' => [

                                                'LoadingAmount' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Loading Amount',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'LoadingRate' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Loading Rate',
                                                        'Value' =>
                                                        ''
                                                    ],

                                                ],

                                                'SumInsured' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'SumInsured',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'Rate' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Rate',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'Premium' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => ' Premium',
                                                        'Value' => ''
                                                    ],
                                                ],
                                                'Applicable' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Applicable',
                                                        'Value' => 'True'
                                                    ],

                                                ],

                                                'Description' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Description',
                                                        'Value' => 'Adverse Claim Loading'
                                                    ],

                                                ],

                                                '@attributes' => [

                                                    'Type' => 'GroupData'
                                                ],

                                            ],

                                            '1' => [

                                                'LoadingAmount' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Loading Amount',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'LoadingRate' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Loading Rate',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'SumInsured' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'SumInsured',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'Rate' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Rate',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'Premium' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Premium',
                                                        'Value' => ''
                                                    ],

                                                ],

                                                'Applicable' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Applicable',
                                                        'Value' => 'True'
                                                    ],

                                                ],

                                                'Description' => [

                                                    '@value' => '',
                                                    '@attributes' => [

                                                        'Name' => 'Description',
                                                        'Value' => 'BreakIn Loading'
                                                    ],

                                                ],

                                                '@attributes' => [

                                                    'Type' => 'GroupData'
                                                ],

                                            ],

                                        ],

                                        '@attributes' => [

                                            'Type' => 'Group',
                                            'Name' => 'Other Loading Group'
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'OtherLoadings'
                                    ],

                                ],

                                'OtherDiscounts' => [

                                    'OtherDiscountGroup' => [

                                        'OtherDiscountGroupData' => $other_discount_data_section,

                                        '@attributes' => [

                                            'Type' => 'Group',
                                            'Name' => 'Other Discount Group'
                                        ],

                                    ],

                                    '@attributes' => [

                                        'Name' => 'OtherDiscounts'
                                    ],

                                ],

                                '@attributes' => [

                                    'Type' => 'GroupData'
                                ],

                            ],

                            '@attributes' => [

                                'Type' => 'Group',
                                'Name' => 'Risks'
                            ],

                        ],

                        'VehicleClassCode' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleClassCode',
                                'Value' =>  $mmv_data->vehicleclasscode //'35',
                            ],

                        ],

                        'VehicleMakeCode' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleMakeCode',
                                'Value' => $mmv_data->usgi_make_code //'170'
                            ],

                        ],

                        'VehicleModelCode' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleModelCode',
                                'Value' => $mmv_data->vehiclemodelcode //'11049'
                            ],

                        ],

                        'RTOLocationCode' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'RTOLocationCode',
                                'Value' => $rto_payload->rto_location_code
                            ],

                        ],

                        'NoOfClaimsOnPreviousPolicy' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'No Of Claims On Previous Policy',
                                'Value' => ''
                            ],

                        ],

                        'RegistrationNumber' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Registration Number',
                                'Value' => $RegistrationNumber,
                            ],

                        ],

                        'BodyTypeCode' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'BodyTypeCode',
                                'Value' => $mmv_data->bodytypecode //'2'
                            ],

                        ],

                        'ModelStatus' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'ModelStatus',
                                'Value' => ''
                            ],

                        ],

                        'GrossVehicleWeight' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'GrossVehicleWeight',
                                'Value' => $GVW
                            ],

                        ],

                        'CarryingCapacity' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'CarryingCapacity',
                                'Value' => $mmv_data->seatingcapacity //'1'
                            ],

                        ],

                        'VehicleType' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleType',
                                'Value' => $veh_type
                            ],

                        ],

                        'PlaceOfRegistration' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Place Of Registration',
                                'Value' => $rto_payload->rto_location
                            ],

                        ],

                        'VehicleModel' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleModel',
                                'Value' => $mmv_data->vehiclemodel //'11049'. ' ' . $mmv_data->variant
                            ],

                        ],

                        'VehicleExShowroomPrice' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleExShowroomPrice',
                                'Value' => $quoteData->ex_showroom_price_idv
                            ],

                        ],

                        'DateOfDeliveryOrRegistration' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'DateOfDeliveryOrRegistration',
                                'Value' => date('d/m/Y', strtotime($vehicleDate))

                            ],

                        ],

                        'YearOfManufacture' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Year Of Manufacture',
                                'Value' => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                            ],

                        ],

                        'DateOfFirstRegistration' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'DateOfFirstRegistration',
                                'Value' => date('d/m/Y', strtotime($requestData->vehicle_register_date))
                            ],

                        ],
                        'RegistrationNumberSection1' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Regn No. Section 1',
                                'Value' => $RegistrationNo_1
                            ],
                        ],

                        'RegistrationNumberSection2' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Regn No. Section 2',
                                'Value' => $RegistrationNo_2
                            ],

                        ],

                        'RegistrationNumberSection3' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Regn No. Section 3',
                                'Value' => $RegistrationNo_3
                            ],

                        ],

                        'RegistrationNumberSection4' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Regn No. Section 4',
                                'Value' => $RegistrationNo_4
                            ],

                        ],

                        'EngineNumber' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Engine Number',
                                'Value' => $proposal->engine_number
                            ],

                        ],

                        'ChassisNumber' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Chassis Number',
                                'Value' => $proposal->chassis_number
                            ],

                        ],

                        'BodyColour' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Body Colour',
                                'Value' => $vehicleBodyColor
                            ],

                        ],

                        'FuelType' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Fuel Type',
                                'Value' => $mmv_data->cnvm_fuel_type //'Diesel' 
                            ],

                        ],

                        'ExtensionCountryName' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Extension Country Name',
                                'Value' => ''
                            ],

                        ],

                        'RegistrationAuthorityName' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Registration Authority Name',
                                'Value' => 'Mumbai'
                            ],

                        ],

                        'AutomobileAssocnFlag' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'AutomobileAssocnFlag',
                                'Value' => 'False'
                            ],

                        ],

                        'AutomobileAssociationNumber' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Automobile Association Number',
                                'Value' => '1234'
                            ],

                        ],

                        'VoluntaryExcess' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Voluntary Access',
                                'Value' => ''
                            ],

                        ],

                        'TPPDLimit' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'TPPDLimit',
                                'Value' => '6000'
                            ],

                        ],

                        'AntiTheftDiscFlag' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'AntiTheftDiscFlag',
                                'Value' => $anti_theft_device
                            ],

                        ],

                        'HandicapDiscFlag' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'HandicapDiscFlag',
                                'Value' => ''
                            ],

                        ],

                        'NumberOfDrivers' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NumberOfDrivers',
                                'Value' => $llpd_driverSI
                            ],

                        ],

                        'NumberOfEmployees' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NumberOfEmployees',
                                'Value' => ''
                            ],

                        ],

                        'TransferOfNCB' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'TransferOfNCB',
                                'Value' => ''
                            ],

                        ],

                        'TransferOfNCBPercent' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'TransferOfNCBPercent',
                                'Value' => ''
                            ],

                        ],

                        'NCBDeclaration' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NCBDeclaration',
                                'Value' => ''
                            ],

                        ],

                        'PreviousVehicleSaleDate' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'PreviousVehicleSaleDate',
                                'Value' => ''
                            ],

                        ],

                        'BonusOnPreviousPolicy' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'BonusOnPreviousPolicy',
                                'Value' => ''
                            ],

                        ],

                        'VehicleClass' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleClass',
                                'Value' => 'MISCELLANEOUS VEHICLES'
                            ],

                        ],

                        'VehicleMake' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleMake',
                                'Value' => $mmv_data->usgi_make_code //'EICHER TRACTOR'
                            ],

                        ],

                        'BodyTypeDescription' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'BodyTypeDescription',
                                'Value' => ''
                            ],

                        ],

                        'NumberOfWheels' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NumberOfWheels',
                                'Value' => '4'
                            ],

                        ],

                        'CubicCapacity' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'CubicCapacity',
                                'Value' => $mmv_data->cubiccapacity //''2450''
                            ],

                        ],

                        'SeatingCapacity' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'SeatingCapacity',
                                'Value' => $mmv_data->seatingcapacity //'1'
                            ],

                        ],

                        'RegistrationZone' => [

                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'RegistrationZone',
                                'Value' => $rto_payload->zone
                            ],
                        ],

                        'VehiclesDrivenBy' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Vehicles Driven By',
                                'Value' => ''
                            ],

                        ],

                        'DriversAge' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Drivers Age',
                                'Value' => ''
                            ],

                        ],

                        'DriversExperience' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Drivers Experience',
                                'Value' => ''
                            ],

                        ],

                        'DriversQualification' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Drivers Qualification',
                                'Value' => '',
                            ],

                        ],

                        'VehicleModelCluster' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleModelCluster',
                                'Value' => ''
                            ],

                        ],

                        'OpenCoverNoteFlag' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'OpenCoverNote',
                                'Value' => ''
                            ],

                        ],

                        'LegalLiability' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'LegalLiability',
                                'Value' => ''
                            ],

                        ],

                        'PaidDriver' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'PaidDriver',
                                'Value' => ''
                            ],

                        ],

                        'NCBConfirmation' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NCBConfirmation',
                                'Value' => ''
                            ],

                        ],

                        'RegistrationDate' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'RegistrationDate',
                                'Value' => date('d/m/Y', strtotime($requestData->vehicle_register_date))
                            ],

                        ],

                        'TPLoadingRate' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'TPLoadingRate',
                                'Value' => '0'
                            ],

                        ],

                        'ExtensionCountry' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'ExtensionCountry',
                                'Value' => ''
                            ],

                        ],

                        'VehicleAge' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleAge',
                                'Value' => $miscd_age
                            ],

                        ],

                        'LocationCode' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'LocationCode',
                                'Value' => ''
                            ],

                        ],

                        'RegistrationZoneDescription' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'RegistrationZoneDescription',
                                'Value' => $rto_payload->zone ?? ''
                            ],

                        ],

                        'NumberOfWorkmen' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NumberOfWorkmen',
                                'Value' => ''
                            ],

                        ],

                        'VehicCd' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicCd',
                                'Value' => ''
                            ],

                        ],

                        'SalesTax' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Sales Tax',
                                'Value' => ''
                            ],

                        ],

                        'ModelOfVehicle' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Model Of Vehicle',
                                'Value' => ''
                            ],

                        ],

                        'PopulateDetails' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'Populate details',
                                'Value' => ''
                            ],

                        ],

                        'VehicleIDV' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'VehicleInsuredDeclaredValue',
                                'Value' => $quoteData->idv
                            ],

                        ],

                        'ShowroomPriceDeviation' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'ShowroomPriceDeviation',
                                'Value' => ''
                            ],

                        ],

                        'NewVehicle' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'NewVehicle',
                                'Value' => $business_type
                            ],

                        ],
                        'minIDV' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'minIDV',
                                'Value' => $m_minidv
                            ],
                        ],
                        'maxIDV' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'maxIDV',
                                'Value' => $m_maxidv
                            ],
                        ],
                        'isPrevZeroDepPresent' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'isPrevZeroDepPresent',
                                'Value' => 'false'
                            ],
                        ],
                        'isPrevReturnToInvoicePresent' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'isPrevReturnToInvoicePresent',
                                'Value' => 'false'
                            ],
                        ],
                        'NumofFPP' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'NumofFPP',
                                'Value' => ''
                            ],
                        ],
                        'NumofNFPP' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'NumofNFPP',
                                'Value' => ''
                            ],
                        ],
                        'Numoftrailers' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Numoftrailers',
                                'Value' => ''
                            ],
                        ],

                        'PUCDeclaration' => [

                            '@value' => '',
                            '@attributes' => [

                                'Name' => 'PUCDeclaration',
                                'Value' => 'YES'
                            ],

                        ],


                        '@attributes' => [

                            'Name' => 'Risks'
                        ],

                    ],

                    '@attributes' => [

                        'Name' => $productname
                    ],

                ],
                'PaymentDetails' => [
                    'PaymentEntry' => [
                        [
                            'PaymentId' => '',
                            'MICRCheque' => '',
                            'InstrumentDate' => '',
                            'DraweeBankName' => '',
                            'HOUSEBANKNAME' => '',
                            'AmountPaid' => '',
                            'PaymentType' => '',
                            'PaymentDate' => $step === 'skip' || $requestData->business_type == 'breakin' ? date('d/m/Y') : '',
                            'PaymentMode' => '',
                            'InstrumentNo' => '',
                            'Status' => '',
                            'DepositSlipNo' => '',
                            'PayerType' => ''
                        ],
                    ],



                ],
                 //CIS Regulatory tags #29207
                 'RegulatoryInformation' => [
                    'PhysicalCopy' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'PhysicalCopy',
                            'Value' => $additional_details->owner->gogreenStatus ?? 'NO'
                        ],
                    ],
                    'DataPrivacy' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'DataPrivacy',
                            'Value' => 'YES'
                        ],
                    ],
                    'Age' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Age',
                            'Value' => $proposal?->nominee_age ?? ''
                        ],
                    ],
                    'Relationship' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Relationship',
                            'Value' => $proposal?->nominee_relationship ?? ''
                        ],
                    ],
                    'AppointeeName' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AppointeeName',
                            'Value' => ''
                        ],
                    ],
                    'AppointeeRelationWithNomine' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AppointeeRelationWithNomine',
                            'Value' => ''
                        ],
                    ],
                    'BankName' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'BankName',
                            'Value' => $additional_details->owner->bankName ?? ''
                        ],
                    ],
                    'AccNumber' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'AccNumber',
                            'Value' => $additional_details->owner->accountNumber ?? ''
                        ],
                    ],
                    'PEP' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'PEP',
                            'Value' => $additional_details->owner->pepStatus ?? 'NO'
                        ],
                    ],
                    'Disabilty' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'Disabilty',
                            'Value' => 'YES'
                        ],
                    ],
                    'IFSCCode' => [
                        '@value' => '',
                        '@attributes' => [
                            'Name' => 'IFSCCode',
                            'Value' => $additional_details->owner->ifsc ?? ''
                        ],
                    ],
                ],
                'Errors' => [
                    'ErrorCode' => '',
                    'ErrDescription' => ''
                ],

            ];

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $corporate_ckyc_types = [
                'pan_card' => 'PAN',
                'cinNumber' => 'CIN',
                'gstNumber' => 'GSTIN'
            ];

            $proposal_request['Customer']['EkycNo'] = $proposal->ckyc_number;
            $proposal_request['Customer']['Ref_No_Unique_KYC'] = $proposal->ckyc_reference_id;

            if ($proposal->corporate_vehicles_quotes_request->vehicle_owner_type == 'C' && $proposal->ckyc_type != 'ckyc_number') {
                $proposal_request['Customer']['EkycNo'] = $corporate_ckyc_types[$proposal->ckyc_type];
                $proposal_request['Customer']['Ref_No_Unique_KYC'] = $proposal->ckyc_type_value;
            }
        }
        $proposal_request['Product']['GeneralProposal']['PreviousPolicyDetails']['PreviousPolDtlGroup']['PreviousPolDtlGroupData']['ProductCode'] = [
            '@value' => '',
            '@attributes' => [

                'Name' => 'Product Code',
                'Value' =>  $requestData->previous_policy_type == 'Third-party' ? '2318' : $product_code
            ]
        ];

        $xmlQuoteRequest = ArrayToXml::convert($proposal_request, 'Root');

        $additionData = [
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'soap_action' => 'commBRIDGEFusionMOTOR',
            'container'   => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header /><soapenv:Body><tem:commBRIDGEFusionMOTOR><tem:strXdoc><![CDATA[#replace]]></tem:strXdoc></tem:commBRIDGEFusionMOTOR></soapenv:Body></soapenv:Envelope>',
            'method' => 'Proposal Generation',
            'section' => $productData->product_sub_type_code,
            'productName'   => $productData->product_sub_type_name,
            'transaction_type' => 'proposal',
        ];

        $get_response = getWsData(config('IC.UNIVERSAL_SOMPO.V2.MISCD.END_POINT_URL'), $xmlQuoteRequest, 'universal_sompo', $additionData);
        $response = $get_response['response'];
        if ($response) {
            $response = html_entity_decode($response);

            $response = XmlToArray::convert($response);

            $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];

            if ((isset($filter_response['Root']['Errors']['ErrorCode'])) && !empty($filter_response['Root']['Errors']['ErrorCode']) && $filter_response['Root']['Errors']['ErrorCode'] != 'B001') {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $filter_response['Root']['Errors']['ErrDescription'],
                ];
            } elseif (!isset($filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value'])) {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Error coming from service  =>' . $filter_response,
                ];
            }


            $total_OD_premium = $od_premium = $electrical_amount = $non_electrical_amount = $lpg_cng_amount = 0;
            $total_TP_premium = $tp_premium = $liability = $pa_owner =  $pa_unnamed = $pa_cover_driver = $lpg_cng_tp_amount = 0;
            $total_discount = $ncb_discount = $anti_theft_device_discount = $automobile_association_discount = $detariff_discount_amount = $voluntary_deductable_amount = $tppd_discount_amount = 0;

            $tppd_discount_amount = 0;
            $zero_dep_amount = 0;
            $rsa = 0;
            $key_replacement = 0;
            $eng_prot = 0;
            $consumable = 0;
            $tyre_secure = 0;
            $return_to_invoice = 0;
            $imt23_amount = 0;
            $discountData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['OtherDiscounts']['OtherDiscountGroup']['OtherDiscountGroupData'];

            foreach ($discountData as $key => $discount) {
                if ($discount['Description']['@attributes']['Value'] == 'Automobile Association discount') {
                    $automobile_association_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Antitheft device discount') {
                    $anti_theft_device_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Handicap discount') {
                    $handicap_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'De-tariff discount') {
                    $detariff_discount_value = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'No claim bonus') {
                    $ncb_discount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'TPPD Discount') {
                    $tppd_discount_amount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'Voluntary deductable') {
                    $voluntary_deductable_amount = $discount['Premium']['@attributes']['Value'];
                }
                if ($discount['Description']['@attributes']['Value'] == 'INCLUSION OF IMT23') {
                    $imt23_amount = (int)$discount['Premium']['@attributes']['Value'];
                }
            }

            // with GST
            $total_premium_amount       = $filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value'];
            //Without GST
            $net_premium_amount         = $filter_response['Root']['Product']['PremiumCalculation']['NetPremium']['@attributes']['Value'];
            $service_tax                = $filter_response['Root']['Product']['PremiumCalculation']['ServiceTax']['@attributes']['Value'];
            $detariff_discount_amount   = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Premium']['@attributes']['Value'];
            $discount_rate_final        = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['De-tariffDiscounts']['De-tariffDiscountGroup']['De-tariffDiscountGroupData']['Rate']['@attributes']['Value'];
            $ex_showroom_Price          = $filter_response['Root']['Product']['Risks']['VehicleExShowroomPrice']['@attributes']['Value'];

            $coversdata = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['CoverDetails']['Covers']['CoversData'];

            foreach ($coversdata as $key => $cover) {
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic OD') {
                    $od_premium = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Basic TP') {
                    $tp_premium = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'BUILTIN CNG KIT / LPG KIT OD') {
                    $builtin_lpg_cng_kit_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT OD') {
                    $lpg_cng_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'CNGLPG KIT TP') {
                    $lpg_cng_tp_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'ELECTRICAL ACCESSORY OD') {
                    $electrical_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'FIBRE TANK - OD') {
                    $fibre_tank_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'NON ELECTRICAL ACCESSORY OD') {
                    $non_electrical_amount = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'Other OD') {
                    $other_od = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO OWNER DRIVER') {
                    $pa_owner = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'UNNAMED PA COVER TO PASSENGERS') {
                    $pa_unnamed = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'LEGAL LIABILITY TO PAID DRIVER') {
                    $liability = $cover['Premium']['@attributes']['Value'];
                }
                if ($cover['CoverGroups']['@attributes']['Value'] == 'PA COVER TO PAID DRIVER') {
                    $pa_cover_driver = $cover['Premium']['@attributes']['Value'];
                }
            }

            $addonsData = $filter_response['Root']['Product']['Risks']['Risk']['RisksData']['AddonCoverDetails']['AddonCovers']['AddonCoversData'];

            foreach ($addonsData as $key => $add_on_cover) {
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Road side Assistance') {
                    $rsa = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'COST OF CONSUMABLES') {
                    $consumable = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - DIESEL') {
                    $eng_prot_diesel = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'ENGINE PROTECTOR - PETROL') {
                    $eng_prot_petrol = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'KEY REPLACEMENT') {
                    $key_replacement = $add_on_cover['Premium']['@attributes']['Value'];
                }

                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'Nil Depreciation Waiver cover') {
                    $zero_dep_amount = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'RETURN TO INVOICE') {
                    $return_to_invoice = $add_on_cover['Premium']['@attributes']['Value'];
                }
                if ($add_on_cover['AddonCoverGroups']['@attributes']['Value'] == 'TYRE AND RIM SECURE') {
                    $tyre_secure = $add_on_cover['Premium']['@attributes']['Value'];
                }
            }

            if ($mmv_data->cnvm_fuel_type == 'Petrol') {
                $eng_prot = $eng_prot_petrol;
            } elseif ($mmv_data->cnvm_fuel_type == 'Diesel') {
                $eng_prot = $eng_prot_diesel;
            }

            $PosPolicyNo = $filter_response['Root']['Customer']['PosPolicyNo'];

            $total_OD_premium = (int) $od_premium + (int)$electrical_amount + (int)$non_electrical_amount + (int)$lpg_cng_amount;
            $total_TP_premium = (int)$tp_premium + (int)$liability + (int)$pa_owner +  (int)$pa_unnamed + (int)$pa_cover_driver + (int)$lpg_cng_tp_amount - (int)$tppd_discount_amount;
            $total_addon_premiums = (int)$rsa + (int)$consumable + (int)$eng_prot_diesel + (int)$key_replacement + (int)$zero_dep_amount + (int)$return_to_invoice + (int)$tyre_secure + (int)$imt23_amount;
            $od_discount = (int)$ncb_discount + (int)$anti_theft_device_discount + (int)$automobile_association_discount + (int)$detariff_discount_amount + (int)$voluntary_deductable_amount;
            $total_discount = (int)$od_discount + (int)$tppd_discount_amount;

            // UserProposal::where('user_product_journey_id', $enquiryId)
            //     ->where('user_proposal_id', $proposal->user_proposal_id)
            //     ->update([
            //         'proposal_no' => $PosPolicyNo,
            //         'unique_proposal_id' => '',
            //         'policy_start_date' => str_replace('/', '-', $policy_start_date),
            //         'policy_end_date' =>  str_replace('/', '-', $policy_end_date),
            //         'prev_policy_start_date' => $requestData->business_type == 'newbusiness' ? '' : Carbon::createFromFormat('d/m/Y', $previous_policy_start_date)->format('d-m-Y'), //$previous_policy_start_date,
            //         'prev_policy_expiry_date' => $requestData->business_type == 'newbusiness' ? '' : Carbon::createFromFormat('d/m/Y', $m_previous_policy_end_date)->format('d-m-Y'), //$m_previous_policy_end_date,    
            //         'od_premium' => round($total_OD_premium) - round($od_discount),
            //         'tp_premium' => round($total_TP_premium),
            //         'total_premium' => round($net_premium_amount),
            //         'addon_premium' => round($total_addon_premiums),
            //         'cpa_premium' => round((int)$pa_owner),
            //         'service_tax_amount' => round($service_tax),
            //         'total_discount' => round($total_discount),
            //         'final_payable_amount' => round((int)$total_premium_amount),
            //         'ic_vehicle_details' => '',
            //         'discount_percent' => ''
            //     ]);

            $updateData = [
                'proposal_no' => $PosPolicyNo,
                'unique_proposal_id' => '',
                'policy_start_date' =>  date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                'policy_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                'prev_policy_start_date' => $requestData->business_type == 'newbusiness' ? '' : Carbon::createFromFormat('d/m/Y', $previous_policy_start_date)->format('d-m-Y'), //$previous_policy_start_date,
                'prev_policy_expiry_date' => $requestData->business_type == 'newbusiness' ? '' : Carbon::createFromFormat('d/m/Y', $m_previous_policy_end_date)->format('d-m-Y'), //$m_previous_policy_end_date,    
                'od_premium' => round($total_OD_premium) - round($od_discount),
                'tp_premium' => round($total_TP_premium),
                'total_premium' => round($net_premium_amount),
                'addon_premium' => round($total_addon_premiums),
                'cpa_premium' => round((int)$pa_owner),
                'service_tax_amount' => round($service_tax),
                'total_discount' => round($total_discount),
                'final_payable_amount' => round((int)$total_premium_amount),
                'ic_vehicle_details' => '',
                'discount_percent' => '',
                'tp_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $tp_start_date))),
                'tp_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $tp_end_date))),
              
        ];
        if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
            unset($updateData['tp_start_date']);
            unset($updateData['tp_end_date']);
        }
        $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($updateData);

            //BREAKIN CALL
            $productData = getProductDataByIc($productData->policy_id);
            if ($requestData->business_type == 'breakin' && !in_array($premium_type, ['third_party', 'third_party_breakin']) &&  $step != 'skip') {
                $is_breakin_case = 'Y';
                $breakin_inspection_creation = [
                    "InsurerRefNo" => $PosPolicyNo,
                    "AgentName" => $proposal->first_name . ' ' . $proposal->last_name,
                    "AgentEmail" => $proposal?->email,
                    "AgentMobileNo" => $proposal->mobile_number,
                    "InsuredName" => $proposal->first_name . ' ' . $proposal->last_name,
                    "InsuredMobile" => $proposal->mobile_number,
                    "ManufactMonYear" => date('Y', strtotime('01-' . $requestData->manufacture_year)),
                    "InspectToLocation" => $proposal?->city,
                    "VehicleCategory" => "4",                                   //As its misc-d 4 will be passed.
                    "CustomerVehiocleRegNo" => str_replace('-', '', $proposal?->vehicale_registration_number),                                              //explode('-', $proposal?->vehicale_registration_number),
                    "VehicleModel" => $mmv_data->vehiclemodel,
                    "VehicleManufacturer" => $mmv_data->manufacturer,
                    "InspectType" => "1",                                       //1 Value is hardcoded as it indicated Breakin according to the kit
                    "CreatorRemarks" => "new intimation remark",                //Same
                    "State" => $proposal?->state,
                    "City" => $proposal?->city,
                    "DivisionId" => "",
                    "BranchId" => "",
                    "PinCode" => $proposal->pincode,
                    "CaseType" => "Self-Inspection",                            //as mentioned in the kit, hardcoded value for this field
                ];

                $get_response = getWsData(config('constants.IcConstants.universal_sompo.BREAKIN_ID_CREATION_END_POINT_URL_UNIVERSAL_SOMPO_MISCD_V2'), $breakin_inspection_creation, 'universal_sompo', [
                    'requestMethod' => 'post',
                    'enquiryId' => $enquiryId,
                    'method' => 'Breakin Creation',
                    'section' => $productData->product_sub_type_code,
                    'productName'   => $productData->product_sub_type_name,
                    'transaction_type' => 'proposal',
                ]);
                $breakin_data = $get_response['response'];

                $breakin_response = json_decode($breakin_data);
                $breakin_response = (array) $breakin_response;
                if ($breakin_response['ServiceResult'] != 'Failure' && empty($breakin_response['ErrorText'])) {
                    if (!empty($breakin_response['OutputResult'])) {
                        $breakin_resp_data = $breakin_response['OutputResult'];
                        if ($breakin_resp_data->Status == "true" || $breakin_resp_data->Status == "case_drafted" || $breakin_resp_data->Status == 'Fresh Case') {
                            $breakin_response_array = [
                                'unique_reference_number' => $breakin_resp_data->PIReferanceNo,
                                'breakin_request_status' => $breakin_resp_data->Status,
                            ];
                            //Update User proposal
                            UserProposal::where('user_product_journey_id', $enquiryId)
                                ->update([
                                    'is_breakin_case' => 'Y',
                                    'is_inspection_done' => 'N',
                                    'additional_details_data' => $proposal_request
                                ]);

                            updateJourneyStage([
                                'user_product_journey_id' => $enquiryId,
                                'ic_id' => $productData->company_id,
                                'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                                'proposal_id' => $proposal->user_proposal_id,
                            ]);
                            //Insert inspection number in cv_breakin_status
                            CvBreakinStatus::updateOrInsert(
                                    [
                                        'ic_id' => $productData->company_id,
                                        'breakin_number' => $breakin_resp_data->PIReferanceNo,
                                        'breakin_id' => $breakin_resp_data->PIReferanceNo,
                                        'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                                        'breakin_response' => json_encode($breakin_response_array),
                                        'payment_end_date' => Carbon::today()->addDay(3)->toDateString(),
                                        'created_at' => Carbon::today()->toDateString()
                                    ],
                                    ['user_proposal_id' => $proposal->user_proposal_id]
                                );
                            return response()->json([
                                'status' => true,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'msg' => "Proposal Submitted Successfully!",
                                'data' => [
                                    'proposalId' => $proposal->user_proposal_id,
                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                    'is_breakin' => $is_breakin_case,
                                    'inspection_number' =>  $breakin_resp_data->PIReferanceNo,
                                ]
                            ]);
                        } else {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => isset($breakin_response['ErrorText']) ? $breakin_response['ErrorCode'] : 'Insurer not reachable'
                            ];
                        }
                    } else {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Error in Breakin creation Service'
                        ];
                    }
                }else{
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Error in Breakin creation Service'
                    ];
                }
                updateJourneyStage([
                    'user_product_journey_id' => $enquiryId,
                    'ic_id' => $productData->company_id,
                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                    'proposal_id' => $proposal->user_proposal_id
                ]);

                
            }
            UniversalSompoPremiumDetailController::savePremiumDetails($get_response['webservice_id']);
            return [
                'status' => true,
                'message' => "Proposal Submitted Successfully!",
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'data' => [
                    'proposalId' =>  $proposal->user_proposal_id,
                    'proposalNo' => $PosPolicyNo,
                    'userProductJourneyId' => $proposal->user_product_journey_id,
                ]
            ];
        }else {
            return [
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    }
}
