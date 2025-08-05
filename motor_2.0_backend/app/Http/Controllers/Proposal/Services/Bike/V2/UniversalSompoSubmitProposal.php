<?php


namespace App\Http\Controllers\Proposal\Services\Bike\V2;

use App\Http\Controllers\SyncPremiumDetail\Bike\UniversalSompoPremiumDetailController;
use App\Models\MasterProduct;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class UniversalSompoSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request, $step='')
    {
        $dob = $proposal->dob;
        $carbondate = Carbon::parse($dob);
        $formattedDate = $carbondate->format('d/m/Y');
        
        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $policy_start_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
        $policy_end_date = date('d/m/Y',  strtotime(date('Y-m-d', strtotime('+1 year', strtotime($policy_start_date)))));
        $tp_start_date      =  $policy_start_date;
        $tp_end_date        = $policy_end_date;
    
        $previous_policy_end_date = $policy_start_date = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
        $previous_policy_start_date = date('d/m/Y',  strtotime(date('Y-m-d', strtotime('-1 year', strtotime($previous_policy_end_date)))));
        $productData = getProductDataByIc($request['policyId']);
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        
        $additional_details = json_decode($proposal['additional_details']);

        //Owner Age
        $DOB = $additional_details->owner->dob;
        // $owner_age = Carbon::createFromFormat('d-m-Y', $DOB)->age;

        $mmv = get_mmv_details($productData, $requestData->version_id, $productData->company_alias);

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $GVW = $mmv['GVW'] ?? 0;

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
            ];
        }

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        if($premium_type == 'third_party' && $requestData->business_type == 'newbusiness')
        {

            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Newbussiness third party policy not allowed ',
            ];
        }
        /* $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $bike_age = $age / 12;
        $bike_age = round($bike_age,2); */
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $bike_age = car_age($vehicleDate, $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date,'ceil');

        $interval = car_age_intervals($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
         $intval =json_decode($interval,true);
        $quoteData   = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $m_minidv = $quoteData->premium_json['minIdv'] ?? '';
        $m_vehicleidv = $quoteData->premium_json['vehicleIdv'] ?? '';
        $m_maxidv = $quoteData->premium_json['maxIdv'] ?? '';
        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();
        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';   
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party'; 
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
        }

        // RTO validation for DL
        $rto_code_check = explode('-', $requestData->rto_code);
        /* if (isset($rto_code_check) && $rto_code_check[0] == 'DL' && $rto_code_check[1] < 10) {
            $rto_code = $rto_code_check[0] . '0' . $rto_code_check[1];
        } else { */
            $rto_code = $rto_code_check[0] . $rto_code_check[1];
        /* } */

        $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
        $rto_payload = keysToLower($rto_payload);
        if(!$rto_payload && $rto_code_check[0] == 'DL') {
            $getRegisterNumberWithHyphen = explode('-', getRegisterNumberWithHyphen(str_replace('-', '', $requestData->vehicle_registration_no)));
            if(str_starts_with($getRegisterNumberWithHyphen[2],'S'))
            {
                $rto_code = $rto_code_check[0] . ($rto_code_check[1]*1).'S';
                $rto_payload = DB::table('universal_sompo_rto_master')->where('Region_Code', $rto_code)->first();
                $rto_payload = keysToLower($rto_payload);
            } elseif (strlen($getRegisterNumberWithHyphen[2]) >= 3 && str_starts_with($getRegisterNumberWithHyphen[2], 'C') && is_numeric($rto_code_check[1])) {
                $rto_code = $rto_code_check[0] . ($rto_code_check[1]*1).'C';
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
        $PolicyStatus = 'UnExpired';
        $covers_payload_zero = 'True';
        // $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
        $prev_claim_settled = '';
        $proposal_date = date('Y-m-d H:i:s');
        $electrical_accessories_flag = 'True';
        $selected_addons_flag = 'True';
        $discount_flag_in_tp = 'True';
        $inspection_date = '';
        $covers_payload_first = 'True';

        switch ($premium_type) {

            case 'comprehensive':
                $document_type = 'Proposal';
                $productname = ($requestData->business_type == 'newbusiness') ? 'MOTOR - MOTOR TWO WHEELER  - BUNDLED' : 'Two Wheeler Package Policy'; //
                $product_code = ($requestData->business_type == 'newbusiness') ? '2369' : '2312';
                $basic_od_flag = 'True';
                $ncb_declaration = 'True';
                $vehicleBodyColor = $proposal?->vehicle_color;
                $policyNo = $proposal?->previous_policy_number;
                $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
                $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
                break;

            case 'third_party':
                $document_type = 'Proposal';
                $productname = 'TWO WHEELER LIABILITY POLICY';
                $product_code = '2320';
                $covers_payload_zero = 'False';
                $basic_od_flag = 'False';
                $vehicleBodyColor = $proposal?->vehicle_color;
                $electrical_accessories_flag = 'False';
                $selected_addons_flag = 'False';
                $discount_flag_in_tp = 'False';
                $ncb_declaration = 'False';
                $policyNo = $proposal?->previous_policy_number;
                $PreviousPolExpDt = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));
                $previous_policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)))));
                break;

            case 'own_damage':
                $document_type = 'Proposal';
                $productname = 'TWO WHEELER - OD';
                $product_code = '2397';
                $ncb_declaration = 'True';
                $vehicleBodyColor = $proposal?->vehicle_color;
                $policyNo = $proposal?->tp_insurance_number;
                $PreviousPolExpDt = $proposal?->tp_end_date;
                $previous_policy_start_date = $proposal?->tp_start_date;
                $p_policy_end_date = Carbon::createFromFormat('d-m-Y', $proposal->prev_policy_expiry_date)->format('d/m/Y');$proposal->prev_policy_expiry_date;
                $p_policy_start_date = Carbon::createFromFormat('d/m/Y', $p_policy_end_date)->subYears(1)->addDays(1)->format('d/m/Y');
                $covers_payload_first = 'False';
                $basic_od_flag = 'True';
                break;

        }
        
        switch ($requestData->business_type) {

            case 'rollover':
                $business_type = 'Rollover';
                $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                $tp_start_date      =  $policy_start_date;
                $tp_end_date        = $policy_end_date;
                $RegistrationNumber = $proposal?->vehicale_registration_number;//$rto_code . '-GA-8819';
                $veh_type = 'Rollover';
                $prev_claim_settled = (($requestData->is_claim == 'Y') ? 'Yes' : 'No');
                break;

            case 'newbusiness':
                $business_type = 'New';
                if($premium_type == 'third_party')
                {
                    $policy_start_date = date('d/m/Y',strtotime('+1 day'));
                    $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                    $tp_start_date      =  $policy_start_date;
                    $tp_end_date        = $policy_end_date;
    
                }else
                {
                    $policy_start_date = date('d/m/Y');
                    $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                    $tp_start_date      =  $policy_start_date;
                }
                $tp_end_date        =   date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                $RegistrationNumber = 'New';
                $veh_type = 'New';
                break;

            case 'breakin':
                $policyType = 'Breakin';
                $business_type = 'Rollover';
                $policy_start_date = date('d/m/Y', strtotime("+2 day"));
                $PolicyStatus = $premium_type !== 'third_party' ? 'Expired' : 'UnExpired';
                $RegistrationNumber = $proposal?->vehicale_registration_number;//$requestData->rto_code . '-GA-8819';
                $veh_type = '';
                $vehicleBodyColor = '';
                $usedCar = 'Y';
                $breakin_flag = 'Y';
                $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));
                $tp_start_date      =  $policy_start_date;
                $tp_end_date        = $policy_end_date;
                $previousPolicyExpiryDate = Carbon::createFromDate(($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date));
                $date_diff_in_prev_policy = $previousPolicyExpiryDate->diffInDays(Carbon::today());
                if ($date_diff_in_prev_policy < 90) {
                    $ncb_declaration = 'True';
                }else{
                    $ncb_declaration = 'False';
                }
                $inspection_date =  $step === 'skip' ? '' :date("d/m/Y", strtotime(str_replace("-", "", explode(" ", $proposal->created_date)[0])));
                break;

        }


            $additional_data = $proposal->additonal_data;
            $insurer = DB::table('insurer_address')->where('Insurer', $proposal->insurance_company_name)->first();
            $insurer = keysToLower($insurer);
            if($premium_type == 'own_damage')
            {
                $tp_insurer_address = DB::table('insurer_address')->where('Insurer', $proposal->tp_insurance_company_name)->first();
                $tp_insurer_address = keysToLower($tp_insurer_address);
               
            }

            $RegistrationNumber = explode('-', $RegistrationNumber);

        
            if (isset($RegistrationNumber[1], $RegistrationNumber[0]) && $RegistrationNumber[0] == 'DL') {
                $rtoCode = $RegistrationNumber[0] . '-' . $RegistrationNumber[1];
                $rtoCode = explode('-', RtoCodeWithOrWithoutZero($rtoCode, false));
                $RegistrationNumber[1] = $rtoCode[1];
            }
            $RegistrationNumber = implode('-', $RegistrationNumber);
           

            // $discount_rate = ((int) $quoteData?->premium_json['vehicleDiscountDetail']['discountRate']/100);
            $discount_rate = !empty($quoteData?->premium_json['vehicleDiscountDetail']['discountRate']) ? $quoteData?->premium_json['vehicleDiscountDetail']['discountRate'] : 0;
            $discount_rate = $discount_rate / 100;

            $discount_loading_rate = ((int) $quoteData?->premium_json['vehicleDiscountDetail']['deTariffLoadingRate']/100);
            $discount_loading = $quoteData?->premium_json['vehicleDiscountDetail']['deTariffLoading'];

            $registration_number = $proposal?->vehicale_registration_number !== 'NEW' ? explode('-', $proposal?->vehicale_registration_number) : '';
            $RegistrationNo_1 = !empty($registration_number) ? $registration_number[0] : '';
            $RegistrationNo_2 = !empty($registration_number) ? $registration_number[1] : '';
            $RegistrationNo_3 = !empty($registration_number) ? $registration_number[2] : '';
            $RegistrationNo_4 = !empty($registration_number) ? $registration_number[3] : '';

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
            $rsa_flag = 'False';

            $addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
            if ($addons) {
                if ($addons->compulsory_personal_accident) {
                    $cpaVal = $addons->compulsory_personal_accident;

                    if (isset($cpaVal[0]['reason'])) {
                        $cpa = 'False';
                        $cparate = '';
                    } elseif ($cpaVal[0]['name'] == 'Compulsory Personal Accident' && (isset($cpaVal[0]['tenure'])) && $cpaVal[0]['tenure'] == '5') {
                        $cpa = 'True';
                        $cparate = '5';
                    } elseif ($cpaVal[0]['name'] == 'Compulsory Personal Accident') {
                        $cpa = 'True';
                        $cparate = '1';
                    }
                }
            // if ($addons) {
            //     if ($addons->compulsory_personal_accident) {
            //         foreach ($addons->compulsory_personal_accident as $key => $cpaVal) {
            //             if (isset($cpaVal['name']) && $cpaVal['name'] == 'Compulsory Personal Accident') {
            //                 $cpa = 'True';
            //             }
            //         }
            //     }

                if ($addons->applicable_addons) {
                    foreach ($addons->applicable_addons as $key => $addons_val) {
                        if($selected_addons_flag !== 'False'){
                            if ($addons_val['name'] == 'Zero Depreciation') {
                                $product_data = MasterPolicy::where('policy_id', $masterProduct->master_policy_id)->first();
                                if ($product_data['zero_dep'] == '0' && $bike_age <= 5 && $premium_type != 'third_party') {
                                    $usgi_applicable_addons['zero_dep'] = 'true';
                                    $enable_zero_dep = 'True';
                                }
                            }   
                            if ($addons_val['name'] == 'Key Replacement' && $bike_age <=5) {
                                $key_replacement_flag = 'True';
                            }
                            if ($addons_val['name'] == 'Engine Protector' && $bike_age <=5) {
                                $engine_protector_flag = 'True';
                            }
                            if ($addons_val['name'] == 'Tyre Secure' && $bike_age <=5) {
                                $tyre_secure_flag = 'True';
                            }
                            if (($addons_val['name'] == 'Consumable') && ($intval['y'] < 5)) {
                                $consumable_flag = 'True';
                            }
                            if ($addons_val['name'] == 'Return To Invoice' && $bike_age <=5) {
                                $return_to_invoice_flag = 'True';
                            }
                            if ($addons_val['name'] == 'Road Side Assistance' && $bike_age <=5) {
                                $rsa_flag = 'True';
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
                        if($electrical_accessories_flag !== 'False'){
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
                        if ($additional_covers['name'] == 'PA cover for additional paid driver') {
                            $pa_add_paid_driver_cover = 'True';
                            $pa_add_paid_driver_cover_sumInsured = $additional_covers['sumInsured'];
                        }
                        if ($additional_covers['name'] == 'Unnamed Passenger PA Cover') {
                            $unnamed_psenger_cover = 'True';
                            $unnamed_psenger_cover_sumInsured = $additional_covers['sumInsured'];
                        }
                        if ($additional_covers['name'] == 'LL paid driver') {
                            $llpd = 'True';
                        }
                    }
                }

                if ($addons->discounts) {
                    foreach ($addons->discounts as $key => $discounts) {
                        if($discount_flag_in_tp !== 'False'){
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
            if($requestData->business_type == 'newbusiness')
            {
                $cparate = isset($cparate) ? $cparate : 5;
            }
            else
            {
                $cparate = isset($cparate) ? $cparate : 1;
            }

            if($engine_protector_flag == 'True'){
                switch ($mmv_data->fuel_type) {
                    case 'Petrol':
                        $engine_protector_cover_diesel = 'False';
                        $engine_protector_cover_petrol = 'True';
                        break;
                    case 'Diesel':
                        $engine_protector_cover_diesel = 'True';
                        $engine_protector_cover_petrol = 'False';
                        break;
                    default:
                        $engine_protector_cover_diesel = 'False';
                        $engine_protector_cover_petrol = 'False';
                        break;
                }
            }
            $branchCode_value = config('constants.motorConstant.BRANCH_CODE_VALUE_UNIVERSAL');
            $branchCode_name = config('constants.motorConstant.BRANCH_CODE_NAME_UNIVERSAL');
            $branchName_name = config('constants.motorConstant.BRANCH_NAME_NAME_UNIVERSAL');
            $branchName_value = config('constants.motorConstant.BRANCH_NAME_VALUE_UNIVERSAL');
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $is_pos_enabled_testing = config('constants.motorConstant.IS_POS_ENABLED_TESTING_UNIVERSAL');
            $pos_name = $pos_pan = $pos_aadhar_no = $pos_email = $pos_mobile_no = '';
            $pos_data = DB::table('cv_agent_mappings')
                    ->where('user_product_journey_id',$enquiryId)
                    ->where('user_proposal_id',$proposal->user_proposal_id)
                    ->where('seller_type','P')
                    ->first();

            if($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000)
            {
                if($pos_data->agent_mobile != '' && $pos_data->agent_name != '' && $pos_data->pan_no != '')
                {
                    $pos_name = $pos_data->agent_name;
                    $pos_pan = $pos_data->pan_no;
                    $pos_aadhar_no = $pos_data->aadhar_no;
                    $pos_email = $pos_data->agent_email;
                    $pos_mobile_no = $pos_data->agent_mobile;
                }
                else
                {
                    return 
                    [
                        'status' => false,
                        'message' => 'POS details are missing'
                    ];
                }

            }else if($is_pos_enabled_testing == 'Y' && $quote->idv <= 5000000)
            {
                $pos_name = 'Asd';
                $pos_pan = 'ABGTY8890Z';
                $pos_aadhar_no = '569278616999';
                $pos_email = 'asd@gmail.com';
                $pos_mobile_no = '8765434567';
            }
            else
            {
                $pos_name = '';
                $pos_pan = '';
                $pos_aadhar_no = '';
                $pos_email = '';
                $pos_mobile_no = '';
                
            }


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
                                    'Value' => 0 //$discount_rate
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
                                'Value' =>''
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
                                'Value' => $basic_od_flag
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
                                'Value' => $covers_payload_first
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
                                'Value' => $elect_accessories
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
                                'Value' => $non_elect_accessories
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
                                'Value' => $external_lpg_cng_accessories,
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
                    '8' => [
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
                                'Value' => 'Other OD'
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
                                'Value' => '100000'
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
                                'Value' =>  $cparate,
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
                                'Value' => $requestData->policy_type == 'own_damage' || $requestData->vehicle_owner_type == 'C' ? 'False' : $cpa
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
                                'Value' => $rsa_flag
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
                                'Value' => $consumable_flag
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
                                'Value' =>''
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
                                'Value' => $ncb_declaration
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
                ];

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
                                    'Value' =>  $product_code,
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
                                    'Value' => ''
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
                                    'Value' => ''
                                ],
                            ],
                            
                        'TPPolicyEffectiveTo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Policy Effective To',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? date('d/m/Y', strtotime($proposal->tp_end_date)) : $PreviousPolExpDt)),
                            ],
                        ],
                        'TPPolicyEffectiveFrom' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Policy Effective From',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? date('d/m/Y', strtotime($proposal->tp_start_date)) : $previous_policy_start_date)),
                            ],
                        ],
                        'TPInsurerPolicyNo' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Policy No',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? $proposal->tp_insurance_number : $policyNo)),
                            ],
                        ],
                        'TpInsurerName' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'TP Insurer Name',
                                'Value' => ($requestData->business_type == 'newbusiness' ? '' : ($premium_type == 'own_damage' ? $proposal->tp_insurance_company_name : $proposal->insurance_company_name)),
                            ],
                        ],
                            'PolicyEffectiveTo' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Policy Effective To',
                                    'Value' =>  ($requestData->business_type == 'newbusiness')? null:($premium_type == 'own_damage' ? $p_policy_end_date :$PreviousPolExpDt), //$policy_end_date
                                ],
                            ],
                            'PolicyEffectiveFrom' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'Policy Effective From',
                                    'Value' => ($requestData->business_type == 'newbusiness')? null: ($premium_type == 'own_damage' ? $p_policy_start_date :$previous_policy_start_date),
                                ],
                            ],
                            'DateOfInspection' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'DateOfInspection',
                                    'Value' => $step === 'skip' ? $inspection_date : ''
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
                                    'Value' => ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' ? '' :($premium_type == 'own_damage' ? $proposal->tp_insurance_number : $policyNo))
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
                                    'Value' => 'Unexpired' //unexpired for rollover and expired for break-in policy
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
                                    'Value' =>  ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure' ? '' : ($premium_type == 'own_damage' ? $proposal->tp_insurance_company_name : $proposal->insurance_company_name))
                                ],
                            ],
                            'InsurerAddress' => [
                                '@value' => '',
                                '@attributes' => [
                                    'Name' => 'InsurerAddress',
                                    'Value' => ($requestData->business_type == 'newbusiness' || $requestData->previous_policy_type == 'Not sure') ? '' : ($premium_type != 'own_damage' ? $insurer->address_line_1.' '.$insurer->address_line_2 : $tp_insurer_address->address_line_1.' '.$tp_insurer_address->address_line_2)
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
            
            $proposal_request =
                    [
                        'Authentication' =>
                            [
                                    'WACode' => config('constants.IcConstants.universal_sompo.AUTH_CODE_SOMPO_MOTOR'),
                                    'WAAppCode' => config('constants.IcConstants.universal_sompo.AUTH_APPCODE_SOMPO_MOTOR'),
                                    'WAUserID' => config('constants.IcConstants.universal_sompo.WEB_USER_ID_SOMPO_MOTOR'),
                                    'WAUserPwd' =>config('constants.IcConstants.universal_sompo.WEB_USER_PASSWORD_SOMPO_MOTOR'),
                                    'WAType'  => '0',
                                    'DocumentType' => $document_type,
                                    'Versionid' => '1.1',
                                    'GUID' => config('constants.IcConstants.universal_sompo.GUID_UNIVERSAL_SOMPO_MOTOR'),
                            ],
                        'Customer' =>
                            [
                                    'CustomerType' => $requestData->vehicle_owner_type == 'I' ? "Individual" : "Corporate",
                                    'CustomerName' => $proposal->first_name.' '.$proposal->last_name,
                                    'DOB' =>$formattedDate ?? '',
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
                        'POSAGENT' =>
                        [
                            'Name' => $pos_name,
                            'PAN'=> $pos_pan,
                            'Aadhar' => $pos_aadhar_no,//TestAadhar',
                            'Email' => $pos_email,
                            'MobileNo' => $pos_mobile_no,
                            'Location' => '', 
                            'Information1' => '',
                            'Information2' => ''
                        ],
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
                                                        'IMDBranchName' => [
                                                            '@value' => '',
                                                            '@attributes' => [
                                                                'Name' => $branchName_name,
                                                                'Value' => $branchName_value,
                                                            ],
                                                        ],
                                                        'IMDBranchCode' => [
                                                            '@value' => '',
                                                            '@attributes' => [
                                                                'Name' => $branchCode_name,
                                                                'Value' => $branchCode_value,
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
                                                                        'Value' => 'FROM INTERMEDIARY'
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
                                                                        'Value' => $requestData->previous_policy_type == 'Not sure' ? 'Notsure': $business_type
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
                                                                                                'Value' => $discount_loading
                                                                                        ],

                                                                                ],

                                                                                'LoadingRate' => [

                                                                                        '@value' => '',
                                                                                        '@attributes' => [

                                                                                                'Name' => 'Loading Rate',
                                                                                                'Value' => $discount_loading_rate
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
                                                                                                        'Value' => 'false'
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
                                                                                                        'Value' => 'false'
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
                                                        'Value' => '37', //$mmv_data['USGI_CLASS_CODE']
                                                ],

                                        ],

                                        'VehicleMakeCode' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'VehicleMakeCode',
                                                        'Value' => $mmv_data->make_id
                                                ],

                                        ],

                                        'VehicleModelCode' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'VehicleModelCode',
                                                        'Value' => $mmv_data->model_code
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
                                                        'Value' => $mmv_data->body_type_code
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
                                                        'Value' => $mmv_data->seating_capacity - 1//'5'
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
                                                        'Value' => $mmv_data->model . ' ' . $mmv_data->variant
                                                ],

                                        ],

                                        'VehicleExShowroomPrice' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'VehicleExShowroomPrice',
                                                        'Value' => $quote->ex_showroom_price_idv ?? 0
                                                ],

                                        ],

                                        'DateOfDeliveryOrRegistration' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'DateOfDeliveryOrRegistration',
                                                        'Value' => date('d/m/Y', strtotime($vehicleDate)),

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
                                                        'Value' =>  date('d/m/Y', strtotime($requestData->vehicle_register_date))
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
                                                        'Value' => strtoupper($mmv_data->fuel_type) == 'ELECTRIC BATTERY' ? 'Electrical' : $mmv_data->fuel_type ///'Petrol'
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
                                                        'Value' => '0'
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
                                                        'Value' => '0'
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
                                                        'Value' => 'Two Wheeler'
                                                ],

                                        ],

                                        'VehicleMake' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'VehicleMake',
                                                        'Value' => $mmv_data->make
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
                                                        'Value' => $mmv_data->seating_capacity#'4'
                                                ],

                                        ],

                                        'CubicCapacity' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'CubicCapacity',
                                                        'Value' => $mmv_data->cubic_capacity
                                                ],

                                        ],

                                        'SeatingCapacity' => [

                                                '@value' => '',
                                                '@attributes' => [

                                                        'Name' => 'SeatingCapacity',
                                                        'Value' => $mmv_data->seating_capacity
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
                                                        'Value' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
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
                                                        'Value' => $bike_age
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

                                                        'Name' => 'New Vehicle',
                                                        'Value' => 'New'
                                                ],

                                        ],
                                        
                        'minIDV' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'minIDV',
                                'Value' => $m_minidv,
                            ],
                        ],
                        'maxIDV' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'maxIDV',
                                'Value' => $m_maxidv,
                            ],
                        ],
                        'isPrevZeroDepPresent' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'isPrevZeroDepPresent',
                                'Value' => 'true',
                            ],
                        ],
                        'isPrevReturnToInvoicePresent' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'isPrevReturnToInvoicePresent',
                                'Value' => 'true',
                            ],
                        ],
                        'NumofFPP' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'NumofFPP',
                                'Value' => '',
                            ],
                        ],
                        'NumofNFPP' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'NumofNFPP',
                                'Value' => '',
                            ],
                        ],
                        'Numoftrailers' => [
                            '@value' => '',
                            '@attributes' => [
                                'Name' => 'Numoftrailers',
                                'Value' => '',
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
                                                'PaymentDate' => $step === 'skip' ? date('d/m/Y') : '',
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
                                    'Value' => $additional_details->owner->pepStatus ?? ''
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

            $get_response = getWsData(config('IC.UNIVERSAL_SOMPO.V2.BIKE.ENDPOINT.URL'), $xmlQuoteRequest, 'universal_sompo', $additionData);
            $response = $get_response['response'];
            if ($response) {
                $response = html_entity_decode($response);
                $response = XmlToArray::convert($response);

                $filter_response = $response['s:Body']['commBRIDGEFusionMOTORResponse']['commBRIDGEFusionMOTORResult'];

                if ((isset($filter_response['Root']['Errors']['ErrorCode'])) && !empty($filter_response['Root']['Errors']['ErrorCode'])) 
                {
                    $condition_3 = isset($filter_response['Root']['Authentication']['DocumentType']) && $filter_response['Root']['Authentication']['DocumentType'] == 'Proposal' && $filter_response['Root']['Errors']['ErrorCode'] != '0' && $filter_response['Root']['Errors']['ErrorCode'] != 'B001';
                    if($condition_3)
                    {
                             
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $filter_response['Root']['Errors']['ErrDescription'],
                        ];
                    }
                }
                elseif(!isset($filter_response['Root']['Product']['PremiumCalculation']['TotalPremium']['@attributes']['Value']))
                {
                    return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Error coming from service  =>' . $filter_response['Root']['Errors']['ErrDescription'],
                        ];
                }

                $tppd_discount_amount = 0;
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

                $zero_dep_amount = 0;
                $rsa = 0;
                $key_replacement = 0;
                $eng_prot = 0;
                $consumable = 0;
                $tyre_secure = 0;
                $return_to_invoice = 0;
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

                if ($mmv_data->fuel_type == 'Petrol') {
                    $eng_prot = $eng_prot_petrol;
                } elseif ($mmv_data->fuel_type == 'Diesel') {
                    $eng_prot = $eng_prot_diesel;
                }

                $PosPolicyNo = $filter_response['Root']['Customer']['PosPolicyNo'];

                $total_OD_premium = (int)$od_premium + (int)$electrical_amount + (int)$non_electrical_amount + (int)$lpg_cng_amount;
                $total_TP_premium = (int)$tp_premium + (int)$liability + (int)$pa_owner +  (int)$pa_unnamed + (int)$pa_cover_driver + (int)$lpg_cng_tp_amount - (int)$tppd_discount_amount;
                $total_addon_premiums = (int)$rsa + (int)$consumable + (int)$eng_prot_diesel + (int)$key_replacement + (int)$zero_dep_amount + (int)$return_to_invoice + (int)$tyre_secure;
                $od_discount = (int)$ncb_discount + (int)$anti_theft_device_discount + (int)$automobile_association_discount + (int)$detariff_discount_amount + (int)$voluntary_deductable_amount;
                $total_discount = (int)$od_discount + (int)$tppd_discount_amount;

                // UserProposal::where('user_product_journey_id', $enquiryId)
                // ->where('user_proposal_id', $proposal->user_proposal_id)
                //     ->update([
                //         'proposal_no' => $PosPolicyNo,
                //         'unique_proposal_id' => '',
                //         'policy_start_date' => str_replace('/', '-', $policy_start_date),
                //         'policy_end_date' =>  str_replace('/', '-', $policy_end_date),
                //         'od_premium' => round($total_OD_premium) - round($od_discount),
                //         'tp_premium' => round($total_TP_premium),
                //         'addon_premium' => round($total_addon_premiums),
                //         'cpa_premium' => round((int)$pa_owner),
                //         'service_tax_amount' => round($service_tax),
                //         'final_premium' => $net_premium_amount,
                //         'total_premium' => $net_premium_amount,
                //         'total_discount' => round($total_discount),
                //         'final_payable_amount' => round((int)$total_premium_amount),
                //         'ic_vehicle_details' => '',
                //         'discount_percent' => ''
                //     ]);

                $updateData = [
                    'proposal_no' => $PosPolicyNo,
                    'unique_proposal_id' => '',
                    'policy_start_date' => date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date))),
                    'policy_end_date' =>  date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date))),
                    'od_premium' => round($total_OD_premium) - round($od_discount),
                    'tp_premium' => round($total_TP_premium),
                    'addon_premium' => round($total_addon_premiums),
                    'cpa_premium' => round((int)$pa_owner),
                    'service_tax_amount' => round($service_tax),
                    'final_premium' => $net_premium_amount,
                    'total_premium' => $net_premium_amount,
                    'total_discount' => round($total_discount),
                    'final_payable_amount' => round((int)$total_premium_amount),
                    'ic_vehicle_details' => '',
                    'discount_percent' => '',
                    'tp_start_date' =>date('d-m-Y', strtotime(str_replace('/', '-', $tp_start_date))),
                    'tp_end_date' => date('d-m-Y', strtotime(str_replace('/', '-', $tp_end_date))),
                  
            ];
            if ($premium_type == 'own_damage' || $premium_type == 'own_damage_breakin') {
                unset($updateData['tp_start_date']);
                unset($updateData['tp_end_date']);
            }

            $save = UserProposal::where('user_proposal_id', $proposal->user_proposal_id)->update($updateData);

                UniversalSompoPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                    updateJourneyStage([
                        'user_product_journey_id' => $enquiryId,
                        'ic_id' => $productData->company_id,
                        'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                        'proposal_id' => $proposal->user_proposal_id
                    ]);

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

            } else {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Insurer not reachable'
                ];
            }
        
    }
}
